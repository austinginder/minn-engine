<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Front\Permalinks;
use Minn\Http\NoCache;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Support\FileHeaders;

/**
 * WordPress's own browser files, for the plugins whose screens are built on
 * them: a page builder's editor loads WordPress's media library, its classic
 * editor and the libraries under them, by the addresses WordPress serves
 * them from (/wp-includes/js/..., /wp-admin/css/...), and some of that code
 * names those addresses itself.
 *
 * The engine carries none of them. They are GPL, and they come with the
 * Minn Legacy Pack, a plugin the site installs like any other (the way a
 * site installs the script pack, ScriptPack), which keeps them under the
 * same paths and registers their handles. While the pack is active the
 * engine answers those addresses from it, ahead of loading any plugin, so a
 * page that loads a hundred of them stays fast. A file the engine serves
 * itself (its jQuery, its embed scripts) is not in the pack and still comes
 * from the engine.
 *
 * The pack is published on GitHub and delivered through the Minn update
 * service like any plugin that publishes its own releases. Where a site runs
 * a plugin whose editor is known to need it (EDITORS, and what plugins add
 * through minn_legacy_pack_editors) and the pack is not active, Minn Admin
 * offers to install it, and the wp-admin doorway sends a click on that
 * editor to the offer rather than to an editor that cannot load.
 */
final class LegacyPack
{
    /** The pack, as the active plugins list names it. */
    public const PLUGIN = 'minn-legacy-pack/minn-legacy-pack.php';

    /** Where the pack is published, as its Update URI names it. */
    public const REPOSITORY = 'austinginder/minn-legacy-pack';

    /**
     * The editors proven to open on Minn with the pack and not without it,
     * by the plugin (its file) or theme (its folder, with `theme`) that
     * brings them: the editor's name, and what opens it, a wp-admin action
     * or a query on the page it edits (`name`, or `name=value` for that
     * value only).
     */
    private const EDITORS = [
        'elementor/elementor.php' => ['name' => 'Elementor', 'action' => 'elementor'],
        'brizy/brizy.php' => ['name' => 'Brizy', 'action' => 'in-front-editor'],
        'bb-plugin/fl-builder.php' => ['name' => 'Beaver Builder', 'query' => 'fl_builder'],
        'beaver-builder-lite-version/fl-builder.php' => ['name' => 'Beaver Builder', 'query' => 'fl_builder'],
        'bricks' => ['name' => 'Bricks', 'query' => 'bricks=run', 'theme' => true],
    ];

    /** Where noteScripts keeps the plugins and themes whose public pages need the pack. */
    private const WANTED = 'minn_legacy_pack_wanted';

    /** How long a plugin noteScripts saw needing the pack is still offered it without being seen again. */
    private const KEPT_FOR = 30 * 86400;

    /** WordPress's own scripts the pack does not carry either (nor does the engine): a page needing them is not helped by it. */
    private const NOT_CARRIED = '#^(?:prototype|scriptaculous(?:-[a-z]+)?|wp-tinymce(?:-[a-z]+)?)$#';

    private const PATH = '#^/(?:wp-includes/(?:js|css|images|fonts)|wp-admin/(?:js|css|images))/[A-Za-z0-9._/-]+$#';

    private const TYPES = [
        'js' => 'application/javascript',
        'css' => 'text/css',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'svg' => 'image/svg+xml',
        'webp' => 'image/webp',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'eot' => 'application/vnd.ms-fontobject',
        'json' => 'application/json',
        'html' => 'text/html; charset=UTF-8',
        'txt' => 'text/plain; charset=UTF-8',
    ];

    /** Whether the pack is active on the site the runtime serves. */
    public static function active(): bool
    {
        $active = Runtime::options()->get('active_plugins');
        return is_array($active) && in_array(self::PLUGIN, $active, true);
    }

    /**
     * The pack's state for the app: whether it is installed and active; the
     * editors of the active plugins and theme that need it, each with the
     * action or query that opens it ('' for the one it has not); and the
     * active plugins and theme whose scripts were seen needing it on the
     * public site (noteScripts), with the scripts they needed and a page
     * that showed it.
     *
     * @return array{installed: bool, active: bool, editors: list<array{plugin: string, theme: string, name: string, action: string, query: string}>, wanted: list<array{plugin: string, theme: string, name: string, handles: list<string>, page: string, seen: int}>}
     */
    public static function status(): array
    {
        return [
            'installed' => is_file(WP_PLUGIN_DIR . '/' . self::PLUGIN),
            'active' => self::active(),
            'editors' => self::editors(),
            'wanted' => self::seenWanting(),
        ];
    }

    /**
     * Notes, on a public page while the pack is not active, the plugins and
     * the theme whose scripts lined up for printing need one of WordPress's
     * own scripts that the engine carries no file for and the pack does
     * (WooCommerce's variation form needs wp-util, its block cart
     * wp-element and the rest). Such a script prints and fails in the
     * visitor's browser. Each is kept with the scripts it needed and a page
     * that showed it, at most once a day, for Minn Admin to offer the pack.
     *
     * @param list<string> $lined the handles lined up for printing
     */
    public static function noteScripts(Assets $scripts, array $lined): void
    {
        if (PHP_SAPI === 'cli' || Runtime::current()->isAdmin || $scripts->kind() !== 'script' || self::active()) {
            return;
        }
        $found = [];
        foreach ($lined as $handle) {
            if (!is_string($handle) || !$scripts->unshipped($handle) || preg_match(self::NOT_CARRIED, $handle) === 1) {
                continue;
            }
            foreach (array_intersect($scripts->dependents($handle), $lined) as $dependent) {
                $owner = self::owner((string) ($scripts->item($dependent)['src'] ?? ''));
                if ($owner !== null) {
                    $found[$owner[0]]['theme'] = $owner[1];
                    $found[$owner[0]]['handles'][] = $handle;
                }
            }
        }
        if ($found === []) {
            return;
        }
        $options = Runtime::options();
        $kept = $options->get(self::WANTED);
        $kept = is_array($kept) ? $kept : [];
        $now = time();
        $changed = false;
        foreach ($found as $key => $seen) {
            $row = is_array($kept[$key] ?? null) ? $kept[$key] : [];
            $had = array_values(array_filter((array) ($row['handles'] ?? []), is_string(...)));
            $handles = array_values(array_unique([...$had, ...$seen['handles']]));
            sort($handles);
            if ($handles === $had && (int) ($row['seen'] ?? 0) > $now - 86400) {
                continue;
            }
            $kept[$key] = ['theme' => $seen['theme'], 'handles' => $handles, 'page' => Runtime::current()->request->path, 'seen' => $now];
            $changed = true;
        }
        if ($changed) {
            $options->update(self::WANTED, $kept, 'off');
        }
    }

    /**
     * The active plugin (its file) or theme (its folder, true) a script's
     * address is under, or null.
     *
     * @return array{0: string, 1: bool}|null
     */
    private static function owner(string $src): ?array
    {
        $path = (string) parse_url($src, PHP_URL_PATH);
        $options = Runtime::options();
        if (preg_match('#/plugins/([^/]+)/#', $path, $match) === 1) {
            foreach ((array) $options->get('active_plugins') as $plugin) {
                if (is_string($plugin) && str_starts_with($plugin, $match[1] . '/')) {
                    return [$plugin, false];
                }
            }
            return null;
        }
        if (preg_match('#/themes/([^/]+)/#', $path, $match) === 1 && in_array($match[1], [(string) $options->get('template'), (string) $options->get('stylesheet')], true)) {
            return [$match[1], true];
        }
        return null;
    }

    /** @return list<array{plugin: string, theme: string, name: string, action: string, query: string}> */
    private static function editors(): array
    {
        $options = Runtime::options();
        $active = $options->get('active_plugins');
        $active = is_array($active) ? $active : [];
        $themes = [(string) $options->get('template'), (string) $options->get('stylesheet')];
        $editors = [];
        foreach ((array) Runtime::hooks()->filter('minn_legacy_pack_editors', [self::EDITORS]) as $key => $editor) {
            if (!is_string($key) || !is_array($editor) || !is_string($editor['name'] ?? null)) {
                continue;
            }
            $theme = ($editor['theme'] ?? false) === true;
            $action = is_string($editor['action'] ?? null) ? $editor['action'] : '';
            $query = is_string($editor['query'] ?? null) ? $editor['query'] : '';
            if (($action === '' && $query === '') || !in_array($key, $theme ? $themes : $active, true)) {
                continue;
            }
            $editors[] = ['plugin' => $theme ? '' : $key, 'theme' => $theme ? $key : '', 'name' => $editor['name'], 'action' => $action, 'query' => $query];
        }
        return $editors;
    }

    /**
     * What noteScripts kept, for the plugins and theme still active, seen in
     * the last thirty days, each named by its header.
     *
     * @return list<array{plugin: string, theme: string, name: string, handles: list<string>, page: string, seen: int}>
     */
    private static function seenWanting(): array
    {
        $options = Runtime::options();
        $kept = $options->get(self::WANTED);
        if (!is_array($kept) || $kept === []) {
            return [];
        }
        $active = $options->get('active_plugins');
        $active = is_array($active) ? $active : [];
        $themes = [(string) $options->get('template'), (string) $options->get('stylesheet')];
        $wanted = [];
        foreach ($kept as $key => $row) {
            if (!is_string($key) || !is_array($row) || (int) ($row['seen'] ?? 0) < time() - self::KEPT_FOR) {
                continue;
            }
            $theme = ($row['theme'] ?? false) === true;
            if (!in_array($key, $theme ? $themes : $active, true)) {
                continue;
            }
            $name = $theme
                ? FileHeaders::values(WP_CONTENT_DIR . '/themes/' . $key . '/style.css', ['Theme Name'])['Theme Name']
                : FileHeaders::values(WP_PLUGIN_DIR . '/' . $key, ['Plugin Name'])['Plugin Name'];
            $wanted[] = [
                'plugin' => $theme ? '' : $key,
                'theme' => $theme ? $key : '',
                'name' => $name !== '' ? $name : ($theme ? $key : dirname($key)),
                'handles' => array_values(array_filter((array) ($row['handles'] ?? []), is_string(...))),
                'page' => (string) ($row['page'] ?? ''),
                'seen' => (int) $row['seen'],
            ];
        }
        return $wanted;
    }

    /** The editor a wp-admin action opens that needs the pack while it is not active, or null. @return array{plugin: string, theme: string, name: string, action: string, query: string}|null */
    public static function wantedFor(string $action): ?array
    {
        return self::wantedWhere(static fn (array $editor): bool => $editor['action'] !== '' && $editor['action'] === $action);
    }

    /**
     * The editor a page's query opens that needs the pack while it is not
     * active (Beaver Builder's ?fl_builder, Bricks' ?bricks=run), or null.
     *
     * @param array<string, mixed> $query
     * @return array{plugin: string, theme: string, name: string, action: string, query: string}|null
     */
    public static function wantedOn(array $query): ?array
    {
        if ($query === []) {
            return null;
        }
        return self::wantedWhere(static function (array $editor) use ($query): bool {
            if ($editor['query'] === '') {
                return false;
            }
            [$name, $value] = array_pad(explode('=', $editor['query'], 2), 2, null);
            return array_key_exists($name, $query) && ($value === null || $query[$name] === $value);
        });
    }

    /**
     * Minn Admin's offer to install the pack for an editor, with the way
     * back to the page that opens it: `?legacy-pack=<action or query>&return=<page>`.
     *
     * @param array{action: string, query: string} $editor
     */
    public static function offer(array $editor, Permalinks $permalinks, Request $request): Response
    {
        $back = $permalinks->url($request->path) . $request->queryStringWithout();
        return new Response(302, ['Location' => $permalinks->url('/minn-admin/') . '?' . http_build_query(['legacy-pack' => $editor['action'] !== '' ? $editor['action'] : $editor['query'], 'return' => $back])] + NoCache::headers());
    }

    /** @param \Closure(array{plugin: string, theme: string, name: string, action: string, query: string}): bool $opens */
    private static function wantedWhere(\Closure $opens): ?array
    {
        if (self::active()) {
            return null;
        }
        foreach (self::editors() as $editor) {
            if ($opens($editor)) {
                return $editor;
            }
        }
        return null;
    }

    /** Whether a path is one of the addresses WordPress serves its browser files from. */
    public static function claims(string $path): bool
    {
        return preg_match(self::PATH, $path) === 1 && !str_contains($path, '..');
    }

    /**
     * The file in the active pack a path names, or null: the pack is not
     * active, the file is not in it, or its type is not one a browser loads.
     *
     * @param list<mixed> $activePlugins the active_plugins option
     */
    public static function file(string $contentDir, string $path, array $activePlugins): ?string
    {
        if (!self::claims($path) || !in_array(self::PLUGIN, $activePlugins, true)) {
            return null;
        }
        $root = realpath(rtrim($contentDir, '/') . '/plugins/' . dirname(self::PLUGIN));
        if ($root === false || !isset(self::TYPES[strtolower(pathinfo($path, PATHINFO_EXTENSION))])) {
            return null;
        }
        $file = realpath($root . $path);
        return $file !== false && is_file($file) && str_starts_with($file, $root . '/') ? $file : null;
    }

    /** The file as the answer to the request for it. */
    public static function response(string $file): Response
    {
        return new Response(200, [
            'Content-Type' => self::TYPES[strtolower(pathinfo($file, PATHINFO_EXTENSION))],
            'Cache-Control' => 'public, max-age=86400',
            'Last-Modified' => gmdate('D, d M Y H:i:s', (int) filemtime($file)) . ' GMT',
        ], (string) file_get_contents($file));
    }
}
