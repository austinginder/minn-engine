<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Http\Response;

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
     * The editors proven to open on Minn with the pack and not without it:
     * the plugin, its name, and the wp-admin action that opens its editor.
     */
    private const EDITORS = [
        'elementor/elementor.php' => ['name' => 'Elementor', 'action' => 'elementor'],
    ];

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
     * The pack's state for the app: whether it is installed and active, and
     * the active plugins whose editors need it.
     *
     * @return array{installed: bool, active: bool, editors: list<array{plugin: string, name: string, action: string}>}
     */
    public static function status(): array
    {
        $active = Runtime::options()->get('active_plugins');
        $active = is_array($active) ? $active : [];
        $editors = [];
        foreach ((array) Runtime::hooks()->filter('minn_legacy_pack_editors', [self::EDITORS]) as $plugin => $editor) {
            if (is_string($plugin) && in_array($plugin, $active, true) && is_array($editor) && is_string($editor['name'] ?? null) && is_string($editor['action'] ?? null)) {
                $editors[] = ['plugin' => $plugin, 'name' => $editor['name'], 'action' => $editor['action']];
            }
        }
        return [
            'installed' => is_file(WP_PLUGIN_DIR . '/' . self::PLUGIN),
            'active' => in_array(self::PLUGIN, $active, true),
            'editors' => $editors,
        ];
    }

    /** The editor a wp-admin action opens that needs the pack while it is not active, or null. @return array{plugin: string, name: string, action: string}|null */
    public static function wantedFor(string $action): ?array
    {
        $status = self::status();
        if ($status['active']) {
            return null;
        }
        foreach ($status['editors'] as $editor) {
            if ($editor['action'] === $action) {
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
