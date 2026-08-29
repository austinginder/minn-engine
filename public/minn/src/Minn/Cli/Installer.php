<?php

declare(strict_types=1);

namespace Minn\Cli;

use Minn\Content\ContentScan;
use Minn\Extension\Manifest;
use Minn\Support\Serialized;
use mysqli;
use Throwable;

/**
 * The swap, both ways. Install parks WordPress's own files beside the
 * webroot, lays the engine and its four shape files down, and leaves
 * wp-config.php and wp-content untouched; eject puts every parked file
 * back and removes what install wrote. A manifest in minn/.install.json
 * is the record eject works from. Preflight says what the site will and
 * will not get before anything moves.
 */
final class Installer
{
    private const MANIFEST = '.install.json';
    /** The files WordPress keeps at the webroot, moved aside as a set. */
    private const CORE_ENTRIES = [
        'wp-admin', 'wp-includes', 'index.php', 'wp-activate.php', 'wp-blog-header.php', 'wp-comments-post.php',
        'wp-config-sample.php', 'wp-cron.php', 'wp-links-opml.php', 'wp-load.php', 'wp-login.php', 'wp-mail.php',
        'wp-settings.php', 'wp-signup.php', 'wp-trackback.php', 'xmlrpc.php', 'license.txt', 'readme.html',
    ];
    private const LAYOUT = ['index.php', 'wp-settings.php', 'wp-cli.yml', 'wp-includes/version.php'];
    /** Development-only trees inside the engine or the admin bundle that never ship. */
    private const SKIP = ['.git', 'node_modules', 'tests', 'docs', '.DS_Store', self::MANIFEST];

    /** @var list<string> */
    private array $lines = [];
    private string $worst = 'GREEN';

    private function __construct(private readonly string $engineDir)
    {
    }

    /** @param list<string> $argv */
    public static function main(array $argv, string $engineDir): int
    {
        $command = $argv[0] ?? 'help';
        $root = isset($argv[1]) && !str_starts_with($argv[1], '--') ? rtrim($argv[1], '/') : getcwd();
        $options = [];
        foreach ($argv as $arg) {
            if (preg_match('/^--([a-z-]+)(?:=(.*))?$/', $arg, $m)) {
                $options[$m[1]] = $m[2] ?? true;
            }
        }
        $self = new self($engineDir);
        try {
            $code = match ($command) {
                'preflight' => $self->preflight($root) === 'RED' ? 1 : 0,
                'install' => $self->install($root, $options),
                'eject' => $self->eject($root),
                'status' => $self->status($root),
                default => $self->help(),
            };
        } catch (Throwable $e) {
            $self->say('Error: ' . $e->getMessage());
            $code = 1;
        }
        fwrite(STDOUT, implode("\n", $self->lines) . "\n");
        return $code;
    }

    private function help(): int
    {
        $this->say("Usage: minn <preflight|install|eject|status> <webroot> [--park=<dir>] [--force]");
        return 0;
    }

    public function status(string $root): int
    {
        $state = $this->state($root);
        $this->say("{$root}: {$state}");
        if ($state === 'minn') {
            $manifest = $this->manifest($root);
            $this->say('  installed ' . ($manifest['installed'] ?? '?') . ', engine ' . ($manifest['engine_version'] ?? '?') . ', parked at ' . ($manifest['park'] ?? '?'));
        }
        return 0;
    }

    /** What the site will and will not get; the worst light decides install. */
    public function preflight(string $root): string
    {
        $this->say("Preflight: {$root}");
        if (!is_file("{$root}/wp-config.php")) {
            $this->light('RED', 'no wp-config.php here');
            return $this->worst;
        }
        $state = $this->state($root);
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
        $option = static function (string $name) use ($db, $config): ?string {
            $stmt = $db->prepare("SELECT option_value FROM {$config['prefix']}options WHERE option_name = ? LIMIT 1");
            $stmt->bind_param('s', $name);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_row();
            return $row === null ? null : (string) $row[0];
        };
        $this->say('  home ' . ($option('home') ?? '?') . ', permalinks ' . ($option('permalink_structure') ?: '(plain)'));

        $stylesheet = (string) ($option('stylesheet') ?? '');
        $template = (string) ($option('template') ?? $stylesheet);
        foreach (array_unique([$stylesheet, $template]) as $slug) {
            $dir = "{$root}/wp-content/themes/{$slug}";
            if (!is_dir($dir)) {
                $this->light('RED', "theme {$slug} is not in wp-content/themes");
            } elseif (!is_file("{$dir}/theme.json") || !(is_dir("{$dir}/templates") || ($slug !== $template && is_dir("{$root}/wp-content/themes/{$template}/templates")))) {
                $this->light('RED', "theme {$slug} is a classic (PHP) theme; the engine renders block themes only");
            } else {
                $this->light('GREEN', "theme {$slug} is a block theme");
                $classic = array_filter(glob("{$dir}/*.php") ?: [], static fn (string $f) => basename($f) !== 'functions.php');
                if ($classic !== []) {
                    $this->light('AMBER', "theme {$slug} carries PHP templates that will not run: " . implode(', ', array_map('basename', $classic)));
                }
            }
        }
        $plugins = Serialized::stringList($option('active_plugins'));
        $own = json_decode((string) ($option('minn_active_extensions') ?? '[]'), true);
        $own = is_array($own) ? array_map('strval', $own) : [];
        $provided = [];
        $coveredShortcodes = [];
        $coveredBlocks = [];
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
        $this->surveyContent($db, $config['prefix'], $coveredShortcodes, $coveredBlocks);
        $db->close();
        $this->say("Result: {$this->worst}");
        return $this->worst;
    }

    /**
     * Shortcodes, third-party blocks, menu storage, extra tables, extra
     * post types. The prefix is the identifier read from wp-config as text.
     *
     * @param array<string, string> $coveredShortcodes tag => extension slug
     * @param array<string, string> $coveredBlocks block name => extension slug
     */
    private function surveyContent(mysqli $db, string $prefix, array $coveredShortcodes, array $coveredBlocks): void
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
            $this->light('AMBER', $classic . ' classic nav_menu' . ($classic === 1 ? '' : 's') . ' with ' . $items . ' item' . ($items === 1 ? '' : 's') . ' (the engine reads wp_navigation posts only)');
        } elseif ($nav === 0) {
            $this->light('GREEN', 'no menus');
        }

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

        $typeRows = $db->query("SELECT DISTINCT post_type FROM {$prefix}posts WHERE post_status NOT IN ('trash','auto-draft','inherit')");
        $types = [];
        if ($typeRows) {
            while ($row = $typeRows->fetch_row()) {
                $types[] = (string) $row[0];
            }
        }
        $extraTypes = ContentScan::extraTypes($types);
        if ($extraTypes === []) {
            $this->light('GREEN', 'no extra post types');
        } else {
            $this->light('AMBER', count($extraTypes) . ' extra post types the engine does not serve: ' . ContentScan::listed($extraTypes));
        }
    }

    /** @param array<string, string|true> $options */
    public function install(string $root, array $options): int
    {
        $light = $this->preflight($root);
        if ($light === 'RED' && empty($options['force'])) {
            $this->say('Install refused (RED). Pass --force to install anyway.');
            return 1;
        }
        if ($this->state($root) === 'minn') {
            $this->say('Install refused: the engine is already installed here.');
            return 1;
        }
        $park = is_string($options['park'] ?? null) ? rtrim($options['park'], '/') : dirname($root) . '/wp-parked';
        if (is_dir($park) && count(scandir($park) ?: []) > 2) {
            $this->say("Install refused: {$park} exists and is not empty. Pass --park=<dir>.");
            return 1;
        }
        if (!is_dir($park) && !mkdir($park, 0755, true)) {
            $this->say("Install refused: cannot create {$park}.");
            return 1;
        }
        $moved = [];
        foreach (self::CORE_ENTRIES as $entry) {
            if (file_exists("{$root}/{$entry}") || is_link("{$root}/{$entry}")) {
                self::move("{$root}/{$entry}", "{$park}/{$entry}");
                $moved[] = $entry;
            }
        }
        $this->say('Parked ' . count($moved) . " entries in {$park}");

        $target = "{$root}/minn";
        if (realpath($target) !== realpath($this->engineDir)) {
            self::copyTree($this->engineDir, $target);
            $this->say("Copied the engine to {$target}");
        } else {
            $this->say("Engine already at {$target}");
        }
        @mkdir("{$root}/wp-includes", 0755, true);
        foreach (self::LAYOUT as $file) {
            copy("{$this->engineDir}/layout/{$file}", "{$root}/{$file}");
        }
        $this->say('Wrote ' . implode(', ', self::LAYOUT));
        file_put_contents("{$target}/" . self::MANIFEST, json_encode([
            'installed' => gmdate('c'),
            'engine_version' => self::version(),
            'park' => $park,
            'moved' => $moved,
            'layout' => self::LAYOUT,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        $this->say('Installed. wp-config.php and wp-content were not touched; `minn eject` reverses this.');
        $this->say('Note: a PHP opcode cache may serve the old index.php or wp-settings.php for a few seconds (opcache.revalidate_freq); clear it if the host offers a way.');
        return 0;
    }

    public function eject(string $root): int
    {
        if ($this->state($root) !== 'minn') {
            $this->say('Eject refused: the engine is not installed here.');
            return 1;
        }
        $manifest = $this->manifest($root);
        $park = (string) ($manifest['park'] ?? '');
        if ($park === '' || !is_dir($park)) {
            $this->say("Eject refused: parked files not found at {$park}.");
            return 1;
        }
        foreach ((array) ($manifest['layout'] ?? self::LAYOUT) as $file) {
            @unlink("{$root}/{$file}");
        }
        if (is_dir("{$root}/wp-includes") && count(scandir("{$root}/wp-includes") ?: []) <= 2) {
            rmdir("{$root}/wp-includes");
        }
        $engine = "{$root}/minn";
        if (is_link($engine)) {
            unlink($engine);
        } else {
            self::removeTree($engine);
        }
        $restored = 0;
        foreach ((array) ($manifest['moved'] ?? []) as $entry) {
            if (file_exists("{$park}/{$entry}") || is_link("{$park}/{$entry}")) {
                self::move("{$park}/{$entry}", "{$root}/{$entry}");
                $restored++;
            }
        }
        if (count(scandir($park) ?: []) <= 2) {
            rmdir($park);
        }
        $this->say("Ejected. Restored {$restored} entries; the engine and its shape files are gone; wp-config.php and wp-content were not touched.");
        $this->say('Note: a PHP opcode cache may serve the engine\'s wp-settings.php for a few seconds (opcache.revalidate_freq); clear it if the host offers a way.');
        return 0;
    }

    private function state(string $root): string
    {
        if (is_file("{$root}/minn/" . self::MANIFEST) || (is_file("{$root}/minn/bootstrap.php") && !is_file("{$root}/wp-load.php"))) {
            return 'minn';
        }
        if (is_file("{$root}/wp-load.php") && is_file("{$root}/wp-includes/version.php")) {
            return 'wordpress';
        }
        return 'unknown';
    }

    private function manifest(string $root): array
    {
        $file = "{$root}/minn/" . self::MANIFEST;
        return is_file($file) ? (array) json_decode((string) file_get_contents($file), true) : [];
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

    /** rename() first; copy+remove when the park is on another filesystem. */
    private static function move(string $from, string $to): void
    {
        if (@rename($from, $to)) {
            return;
        }
        if (is_link($from) || !is_dir($from)) {
            if (!@copy($from, $to)) {
                throw new \RuntimeException("Cannot move {$from} to {$to}.");
            }
            unlink($from);
            return;
        }
        self::copyTree($from, $to);
        self::removeTree($from);
    }

    private static function copyTree(string $from, string $to): void
    {
        if (is_link($from) && !is_dir($from)) {
            copy($from, $to);
            return;
        }
        if (!is_dir($from)) {
            copy($from, $to);
            return;
        }
        @mkdir($to, 0755, true);
        foreach (scandir($from) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || in_array($entry, self::SKIP, true)) {
                continue;
            }
            self::copyTree("{$from}/{$entry}", "{$to}/{$entry}");
        }
    }

    private static function removeTree(string $path): void
    {
        if (!file_exists($path) && !is_link($path)) {
            return;
        }
        if (is_link($path) || !is_dir($path)) {
            unlink($path);
            return;
        }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                self::removeTree("{$path}/{$entry}");
            }
        }
        rmdir($path);
    }

    private static function version(): string
    {
        $bootstrap = (string) file_get_contents(dirname(__DIR__, 3) . '/bootstrap.php');
        return preg_match("/MINN_ENGINE_VERSION', '([^']+)'/", $bootstrap, $m) ? $m[1] : '0.0.0';
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
