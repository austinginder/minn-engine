<?php

declare(strict_types=1);

namespace Minn\Runtime;

/**
 * The site-supplied script pack: the `wp-*` JavaScript packages the engine
 * does not reimplement, which a few plugins' front ends need (WooCommerce's
 * block cart and checkout are the reason it exists).
 *
 * These packages are GPL, so the engine never ships them: the SITE installs
 * them into its own `wp-content`, the way it installs a language pack, and
 * the engine registers the handles only when they are actually there. A
 * site owner adding GPL files to a site that already runs GPL plugins is
 * not the engine distributing GPL; nothing under `minn/` changes.
 *
 * The engine's own MIT packages under `minn/assets/wp` always win: the pack
 * fills the gaps around them, it does not replace them.
 */
final class ScriptPack
{
    /** Under the site's wp-content, so backups and migrations carry it. */
    public const RELATIVE_DIR = 'minn-packages/wp-scripts';
    public const MANIFEST = 'script-loader-packages.php';

    /**
     * The vendor handles WordPress registers outside the packages manifest
     * (captured from the reference). `moment` matters as much as `react`:
     * one unregistered handle anywhere in a dependency tree drops every
     * script above it, which is how a missing moment silently cost the
     * whole WooCommerce cart bundle.
     *
     * @var array<string, list<string>>
     */
    private const VENDOR = [
        'lodash' => [],
        'moment' => [],
        'react' => [],
        'react-dom' => ['react'],
        'react-jsx-runtime' => ['react'],
        'regenerator-runtime' => [],
        'wp-polyfill' => [],
        'wp-polyfill-dom-rect' => [],
        'wp-polyfill-element-closest' => [],
        'wp-polyfill-fetch' => [],
        'wp-polyfill-formdata' => [],
        'wp-polyfill-inert' => [],
        'wp-polyfill-node-contains' => [],
        'wp-polyfill-object-fit' => [],
        'wp-polyfill-url' => [],
    ];

    public static function dir(string $contentDir): string
    {
        return rtrim($contentDir, '/') . '/' . self::RELATIVE_DIR;
    }

    public static function installed(string $contentDir): bool
    {
        return is_file(self::dir($contentDir) . '/' . self::MANIFEST);
    }

    /**
     * Every handle the pack can register: the packages manifest keyed by
     * `name.js` becomes `wp-name`, plus the three vendor handles. A handle
     * whose file is missing from the pack is skipped rather than
     * registered against a 404.
     *
     * @return array<string, array{file: string, deps: list<string>, ver: string}>
     */
    public static function handles(string $contentDir): array
    {
        $dir = self::dir($contentDir);
        $manifestFile = $dir . '/' . self::MANIFEST;
        if (!is_file($manifestFile)) {
            return [];
        }
        $manifest = @include $manifestFile;
        if (!is_array($manifest)) {
            return [];
        }
        $out = [];
        foreach (self::VENDOR as $handle => $deps) {
            $file = 'js/dist/vendor/' . $handle . '.min.js';
            if (is_file("{$dir}/{$file}")) {
                $out[$handle] = ['file' => $file, 'deps' => $deps, 'ver' => self::stamp("{$dir}/{$file}")];
            }
        }
        foreach ($manifest as $name => $row) {
            if (!is_string($name) || !str_ends_with($name, '.js') || !is_array($row)) {
                continue;
            }
            $file = 'js/dist/' . substr($name, 0, -3) . '.min.js';
            if (!is_file("{$dir}/{$file}")) {
                continue;
            }
            $out['wp-' . substr($name, 0, -3)] = [
                'file' => $file,
                'deps' => array_values(array_filter((array) ($row['dependencies'] ?? []), 'is_string')),
                'ver' => (string) ($row['version'] ?? self::stamp("{$dir}/{$file}")),
            ];
        }
        return $out;
    }

    /**
     * Copies the packages out of a WordPress tree (or an unpacked core
     * download) into the site's pack directory. Only the built JavaScript
     * and the manifest are taken, and only from the paths they live at, so
     * a wrong source folder copies nothing rather than something odd.
     *
     * @return array{files: int, dir: string}
     */
    public static function installFromTree(string $contentDir, string $tree): array
    {
        $tree = rtrim($tree, '/');
        $manifest = "{$tree}/wp-includes/assets/" . self::MANIFEST;
        $dist = "{$tree}/wp-includes/js/dist";
        if (!is_file($manifest) || !is_dir($dist)) {
            throw new \RuntimeException("No WordPress script packages under {$tree} (wants wp-includes/js/dist and the packages manifest).");
        }
        $target = self::dir($contentDir);
        foreach ([$target, "{$target}/js/dist", "{$target}/js/dist/vendor"] as $make) {
            if (!is_dir($make) && !@mkdir($make, 0755, true)) {
                throw new \RuntimeException("Cannot create {$make}.");
            }
        }
        $files = 0;
        if (@copy($manifest, "{$target}/" . self::MANIFEST)) {
            $files++;
        }
        foreach ([['', "{$dist}/*.min.js"], ['vendor/', "{$dist}/vendor/*.min.js"]] as [$prefix, $glob]) {
            foreach (glob($glob) ?: [] as $file) {
                if (@copy($file, "{$target}/js/dist/{$prefix}" . basename($file))) {
                    $files++;
                }
            }
        }
        return ['files' => $files, 'dir' => $target];
    }

    /** Removes the pack; the engine's own packages keep working without it. */
    public static function remove(string $contentDir): bool
    {
        $dir = self::dir($contentDir);
        if (!is_dir($dir)) {
            return false;
        }
        foreach (['js/dist/vendor', 'js/dist', ''] as $sub) {
            $path = $sub === '' ? $dir : "{$dir}/{$sub}";
            foreach (glob("{$path}/*.{js,php}", GLOB_BRACE) ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($path);
        }
        return !is_dir($dir);
    }

    private static function stamp(string $file): string
    {
        return (string) filemtime($file);
    }
}
