<?php

declare(strict_types=1);

namespace Minn\Cli;

use Minn\Content\ContentScan;
use Minn\Extension\Manifest;
use Minn\Support\Serialized;
use mysqli;
use Throwable;

/**
 * What a site will and will not get from the engine, before anything
 * moves: the config it can read, the database it can reach, the theme's
 * kind, the plugins the extensions cover, and the content survey (the
 * shortcodes, third-party blocks, menus, extra tables, and extra post
 * types in the database). Every finding is a light; the worst decides.
 */
final class Preflight
{
    /** @var list<string> */
    private array $lines = [];
    private string $worst = 'GREEN';

    /** The report, one line per finding. @return list<string> */
    public function lines(): array
    {
        return $this->lines;
    }

    /** What the site will and will not get; the worst light decides install. */
    /** Runs every check and returns the worst light: GREEN, AMBER, or RED. */
    public function run(string $root): string
    {
        $this->say("Preflight: {$root}");
        if (!is_file("{$root}/wp-config.php")) {
            $this->light('RED', 'no wp-config.php here');
            return $this->worst;
        }
        $state = Installer::state($root);
        $this->light($state === 'wordpress' ? 'GREEN' : ($state === 'minn' ? 'RED' : 'AMBER'), "webroot is {$state}");
        $config = self::readConfig("{$root}/wp-config.php");
        if (!isset($config['DB_NAME'], $config['DB_USER'], $config['DB_HOST'])) {
            $this->light('RED', 'wp-config.php has no database constants the engine can read');
            return $this->worst;
        }
        try {
            $db = @new mysqli($config['DB_HOST'], $config['DB_USER'], $config['DB_PASSWORD'] ?? '', $config['DB_NAME']);
        } catch (Throwable $e) {
            $this->light('RED', 'database unreachable: ' . $e->getMessage());
            return $this->worst;
        }
        $this->light('GREEN', "database {$config['DB_NAME']} reachable, prefix {$config['prefix']}");
        $option = self::optionReader($db, $config['prefix']);
        $this->say('  home ' . ($option('home') ?? '?') . ', permalinks ' . ($option('permalink_structure') ?: '(plain)'));

        $this->preflightTheme($root, $option);
        [$coveredShortcodes, $coveredBlocks, $coveredTypes] = $this->preflightPlugins($root, $option);
        $this->surveyContent($db, $config['prefix'], $coveredShortcodes, $coveredBlocks, $coveredTypes);
        $db->close();
        $this->say("Result: {$this->worst}");
        return $this->worst;
    }

    /**
     * Same line Theme::active / ClassicTheme::active draw: a block template
     * index (child or parent) is a block theme; otherwise a parent index.php
     * is a classic theme.
     *
     * @return 'missing'|'block'|'classic'|'none'
     */
    public static function themeKind(string $dir, string $parentDir): string
    {
        if (!is_dir($dir)) {
            return 'missing';
        }
        $parent = is_dir($parentDir) ? $parentDir : $dir;
        if (is_file($dir . '/templates/index.html') || is_file($parent . '/templates/index.html')) {
            return 'block';
        }
        if (is_file($parent . '/index.php')) {
            return 'classic';
        }
        return 'none';
    }

    /** The database constants and prefix, read from the file as text: nothing in it runs. */
    private static function readConfig(string $file): array
    {
        $source = (string) file_get_contents($file);
        $config = ['prefix' => 'wp_'];
        if (preg_match_all('/define\s*\(\s*[\'"](DB_[A-Z_]+)[\'"]\s*,\s*[\'"](.*?)[\'"]\s*\)/', $source, $m, PREG_SET_ORDER)) {
            foreach ($m as $pair) {
                $config[$pair[1]] = stripslashes($pair[2]);
            }
        }
        // Docker official image: define('DB_NAME', getenv('WORDPRESS_DB_NAME'))
        if (preg_match_all('/define\s*\(\s*[\'"](DB_[A-Z_]+)[\'"]\s*,\s*getenv\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/', $source, $m, PREG_SET_ORDER)) {
            foreach ($m as $pair) {
                $value = self::env($pair[2]);
                if ($value !== null) {
                    $config[$pair[1]] = $value;
                }
            }
        }
        // Docker official image: define('DB_NAME', getenv_docker('WORDPRESS_DB_NAME', 'wordpress'))
        if (preg_match_all('/define\s*\(\s*[\'"](DB_[A-Z_]+)[\'"]\s*,\s*getenv_docker\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*[\'"]([^\'"]*)[\'"]\s*\)/', $source, $m, PREG_SET_ORDER)) {
            foreach ($m as $pair) {
                $config[$pair[1]] = self::env($pair[2], stripslashes($pair[3]));
            }
        }
        if (preg_match('/\$table_prefix\s*=\s*[\'"]([A-Za-z0-9_]+)[\'"]/', $source, $p)) {
            $config['prefix'] = $p[1];
        }
        return $config;
    }

    /**
     * getenv, then {NAME}_FILE (Docker secrets), then the fallback.
     * Matches the official image's getenv_docker without running that PHP.
     */
    private static function env(string $name, ?string $default = null): ?string
    {
        $file = getenv($name . '_FILE');
        if (is_string($file) && $file !== '' && is_readable($file)) {
            return rtrim((string) file_get_contents($file), "\r\n");
        }
        $value = getenv($name);
        return $value === false ? $default : $value;
    }

    /** A reader for one option's raw value, on the site's own connection. @return \Closure(string): ?string */
    private static function optionReader(mysqli $db, string $prefix): \Closure
    {
        return static function (string $name) use ($db, $prefix): ?string {
            $stmt = $db->prepare("SELECT option_value FROM {$prefix}options WHERE option_name = ? LIMIT 1");
            $stmt->bind_param('s', $name);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_row();
            return $row === null ? null : (string) $row[0];
        };
    }

    /** The active theme: present, and block or classic. @param \Closure(string): ?string $option */
    private function preflightTheme(string $root, \Closure $option): void
    {
        $stylesheet = (string) ($option('stylesheet') ?? '');
        $template = (string) ($option('template') ?? $stylesheet);
        if ($template === '') {
            $template = $stylesheet;
        }
        $themes = "{$root}/wp-content/themes";
        $childDir = "{$themes}/{$stylesheet}";
        $parentDir = "{$themes}/{$template}";
        if ($stylesheet === '' || !is_dir($childDir)) {
            $this->light('RED', "theme {$stylesheet} is not in wp-content/themes");
        } elseif ($template !== $stylesheet && !is_dir($parentDir)) {
            $this->light('RED', "theme {$template} is not in wp-content/themes");
        } else {
            $kind = self::themeKind($childDir, $parentDir);
            $label = $stylesheet === $template ? $stylesheet : "{$stylesheet} (parent {$template})";
            if ($kind === 'block') {
                $this->light('GREEN', "theme {$label} is a block theme");
                $leftover = array_filter(glob("{$childDir}/*.php") ?: [], static fn (string $f) => basename($f) !== 'functions.php');
                if ($leftover !== []) {
                    $this->light('AMBER', "theme {$stylesheet} carries PHP templates the block renderer will not run: " . implode(', ', array_map('basename', $leftover)));
                }
            } elseif ($kind === 'classic') {
                $this->light('GREEN', "theme {$label} is a classic PHP theme");
            } else {
                $this->light('RED', "theme {$label} has no templates/index.html and no index.php");
            }
        }
    }

    /**
     * The active plugins against the extensions on disk: what each provides,
     * what will not run, and the shortcodes, blocks, and types the active
     * extensions cover, for the content survey.
     *
     * @param \Closure(string): ?string $option
     * @return array{array<string, string>, array<string, string>, array<string, string>}
     */
    private function preflightPlugins(string $root, \Closure $option): array
    {
        $plugins = Serialized::stringList($option('active_plugins'));
        $own = json_decode((string) ($option('minn_active_extensions') ?? '[]'), true);
        $own = is_array($own) ? array_map('strval', $own) : [];
        $provided = [];
        $coveredShortcodes = [];
        $coveredBlocks = [];
        $coveredTypes = [];
        foreach (glob("{$root}/wp-content/{plugins,mu-plugins}/*/minn.json", GLOB_BRACE) ?: [] as $manifestFile) {
            $manifest = Manifest::read(dirname($manifestFile));
            if ($manifest === null) {
                continue;
            }
            foreach ($manifest->replaces as $file) {
                $provided[$file] = $manifest->slug . ($manifest->covers === '' ? '' : ' extension (' . $manifest->covers . ')');
            }
            $active = array_intersect($manifest->replaces, $plugins) !== []
                || in_array($manifest->slug, $own, true)
                || str_contains($manifest->dir, '/mu-plugins/')
                || array_filter($plugins, static fn (string $p) => str_starts_with($p, $manifest->slug . '/')) !== [];
            if ($active) {
                foreach ($manifest->shortcodes as $tag) {
                    $coveredShortcodes[$tag] = $manifest->slug;
                }
                foreach ($manifest->blocks as $name) {
                    $coveredBlocks[$name] = $manifest->slug;
                }
                foreach ($manifest->types as $row) {
                    $slug = (string) ($row['slug'] ?? '');
                    if ($slug !== '') {
                        $coveredTypes[$slug] = $manifest->slug;
                    }
                }
            }
        }
        $missing = array_values(array_filter($plugins, static fn (string $p) => !isset($provided[$p])));
        foreach ($plugins as $plugin) {
            if (isset($provided[$plugin])) {
                $this->light(str_contains($provided[$plugin], '(') ? 'AMBER' : 'GREEN', explode('/', $plugin)[0] . ' is provided by the ' . $provided[$plugin] . (str_contains($provided[$plugin], '(') ? '' : ' extension'));
            }
        }
        if ($missing !== []) {
            $this->light('AMBER', count($missing) . ' active plugins will not run: ' . implode(', ', array_map(static fn (string $p) => explode('/', $p)[0], $missing)));
        } elseif ($plugins === []) {
            $this->light('GREEN', 'no active plugins');
        }
        $mu = array_map('basename', glob("{$root}/wp-content/mu-plugins/*.php") ?: []);
        if ($mu !== []) {
            $this->light('AMBER', 'mu-plugins will not run: ' . implode(', ', $mu));
        }
        return [$coveredShortcodes, $coveredBlocks, $coveredTypes];
    }

    /**
     * What the content holds that the engine must serve: shortcodes and
     * third-party blocks against the extensions covering them, menus, extra
     * tables, and extra post types.
     *
     * @param array<string, string> $coveredShortcodes
     * @param array<string, string> $coveredBlocks
     * @param array<string, string> $coveredTypes
     */
    private function surveyContent(mysqli $db, string $prefix, array $coveredShortcodes, array $coveredBlocks, array $coveredTypes): void
    {
        $this->surveyMarkup($db, $prefix, $coveredShortcodes, $coveredBlocks);
        $this->surveyMenus($db, $prefix);
        $this->surveyTables($db, $prefix);
        $this->surveyTypes($db, $prefix, $coveredTypes);
    }

    /** @param array<string, string> $coveredShortcodes @param array<string, string> $coveredBlocks */
    private function surveyMarkup(mysqli $db, string $prefix, array $coveredShortcodes, array $coveredBlocks): void
    {
        $typesIn = "'" . implode("','", ContentScan::CONTENT_TYPES) . "'";
        $shortcodes = [];
        $blocks = [];
        $result = $db->query("SELECT post_content FROM {$prefix}posts WHERE post_type IN ({$typesIn}) AND post_status NOT IN ('trash','auto-draft','inherit') AND post_content != ''");
        if ($result) {
            while ($row = $result->fetch_row()) {
                $content = (string) $row[0];
                foreach (ContentScan::shortcodes($content) as $tag => $count) {
                    $shortcodes[$tag] = ($shortcodes[$tag] ?? 0) + $count;
                }
                foreach (ContentScan::blocks($content) as $name => $count) {
                    $blocks[$name] = ($blocks[$name] ?? 0) + $count;
                }
            }
        }
        $unknownShortcodes = array_keys(array_diff_key($shortcodes, $coveredShortcodes));
        $providedShortcodes = array_keys(array_intersect_key($shortcodes, $coveredShortcodes));
        if ($providedShortcodes !== []) {
            $this->light('GREEN', count($providedShortcodes) . ' shortcode' . (count($providedShortcodes) === 1 ? '' : 's') . ' provided by extensions: ' . ContentScan::listed($providedShortcodes));
        }
        if ($unknownShortcodes !== []) {
            $this->light('AMBER', count($unknownShortcodes) . ' shortcode' . (count($unknownShortcodes) === 1 ? '' : 's') . ' in content have no extension: ' . ContentScan::listed($unknownShortcodes));
        } elseif ($shortcodes === []) {
            $this->light('GREEN', 'no shortcodes in content');
        }

        $thirdParty = ContentScan::thirdParty($blocks);
        $unknownBlocks = array_keys(array_diff_key($thirdParty, $coveredBlocks));
        $providedBlocks = array_keys(array_intersect_key($thirdParty, $coveredBlocks));
        if ($providedBlocks !== []) {
            $this->light('GREEN', count($providedBlocks) . ' third-party block' . (count($providedBlocks) === 1 ? '' : 's') . ' provided by extensions: ' . ContentScan::listed($providedBlocks));
        }
        if ($unknownBlocks !== []) {
            $this->light('AMBER', count($unknownBlocks) . ' third-party block' . (count($unknownBlocks) === 1 ? '' : 's') . ' have no extension: ' . ContentScan::listed($unknownBlocks));
        } elseif ($thirdParty === []) {
            $this->light('GREEN', 'no third-party blocks in content');
        }
    }

    private function surveyMenus(mysqli $db, string $prefix): void
    {
        $nav = 0;
        $classic = 0;
        $items = 0;
        $navRow = $db->query("SELECT COUNT(*) FROM {$prefix}posts WHERE post_type = 'wp_navigation' AND post_status = 'publish'");
        if ($navRow) {
            $nav = (int) $navRow->fetch_row()[0];
        }
        $classicRow = $db->query("SELECT COUNT(*) FROM {$prefix}term_taxonomy WHERE taxonomy = 'nav_menu' AND count > 0");
        if ($classicRow) {
            $classic = (int) $classicRow->fetch_row()[0];
        }
        $itemRow = $db->query("SELECT COUNT(*) FROM {$prefix}posts WHERE post_type = 'nav_menu_item' AND post_status != 'trash'");
        if ($itemRow) {
            $items = (int) $itemRow->fetch_row()[0];
        }
        if ($nav > 0) {
            $this->light('GREEN', $nav . ' wp_navigation menu' . ($nav === 1 ? '' : 's'));
        }
        if ($classic > 0) {
            $this->light('GREEN', $classic . ' classic nav_menu' . ($classic === 1 ? '' : 's') . ' with ' . $items . ' item' . ($items === 1 ? '' : 's') . ' (read when a navigation block has no wp_navigation post)');
        } elseif ($nav === 0) {
            $this->light('GREEN', 'no menus');
        }
    }

    private function surveyTables(mysqli $db, string $prefix): void
    {
        $shown = $db->query('SHOW TABLES');
        $tables = [];
        if ($shown) {
            while ($row = $shown->fetch_row()) {
                $tables[] = (string) $row[0];
            }
        }
        $extra = ContentScan::extraTables($tables, $prefix);
        if ($extra === []) {
            $this->light('GREEN', 'no extra tables');
        } else {
            $families = ContentScan::tableFamilies($extra);
            $this->light('AMBER', count($extra) . ' extra tables in ' . count($families) . ' famil' . (count($families) === 1 ? 'y' : 'ies') . ' the engine does not read: ' . ContentScan::listed($families));
        }
    }

    /** @param array<string, string> $coveredTypes */
    private function surveyTypes(mysqli $db, string $prefix, array $coveredTypes): void
    {
        $typeRows = $db->query("SELECT DISTINCT post_type FROM {$prefix}posts WHERE post_status NOT IN ('trash','auto-draft','inherit')");
        $types = [];
        if ($typeRows) {
            while ($row = $typeRows->fetch_row()) {
                $types[] = (string) $row[0];
            }
        }
        $extraTypes = ContentScan::extraTypes($types);
        $providedTypes = array_values(array_filter($extraTypes, static fn (string $slug) => isset($coveredTypes[$slug])));
        $unknownTypes = array_values(array_filter($extraTypes, static fn (string $slug) => !isset($coveredTypes[$slug])));
        if ($providedTypes !== []) {
            $this->light('GREEN', count($providedTypes) . ' extra post type' . (count($providedTypes) === 1 ? '' : 's') . ' declared by extensions: ' . ContentScan::listed($providedTypes));
        }
        if ($unknownTypes !== []) {
            $this->light('AMBER', count($unknownTypes) . ' extra post types the engine does not serve: ' . ContentScan::listed($unknownTypes));
        } elseif ($extraTypes === []) {
            $this->light('GREEN', 'no extra post types');
        }
    }

    private function light(string $light, string $text): void
    {
        $rank = ['GREEN' => 0, 'AMBER' => 1, 'RED' => 2];
        if ($rank[$light] > $rank[$this->worst]) {
            $this->worst = $light;
        }
        $this->say("  [{$light}] {$text}");
    }

    private function say(string $line): void
    {
        $this->lines[] = $line;
    }
}
