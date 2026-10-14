<?php

declare(strict_types=1);

use Minn\Admin\AdminPlugin;
use Minn\Support\Files;

/**
 * Minn Admin's plugin copy: the engine's copy laid into the plugins folder
 * (into an empty place, over an older copy, never over a newer one, a link,
 * or from a copy that is not the whole plugin), and the copy a request
 * serves (the plugin when it is whole, at least the engine's own and asks
 * for no newer engine; the engine's copy otherwise). Scratch folders only,
 * outside any git checkout.
 */
$scratch = static function (): string {
    $dir = sys_get_temp_dir() . '/minn-admin-plugin-' . bin2hex(random_bytes(4));
    mkdir($dir, 0755, true);
    return $dir;
};
$plugin = static function (string $dir, string $version, string $extra = '', bool $whole = true): string {
    mkdir("{$dir}/assets/js", 0755, true);
    file_put_contents("{$dir}/minn-admin.php", "<?php\n/**\n * Plugin Name: Minn Admin\n * Version: {$version}\n{$extra} */\ndefined( 'ABSPATH' ) || exit;\ndefine( 'MINN_ADMIN_VERSION', '{$version}' );\n");
    file_put_contents("{$dir}/assets/js/app.js", "/* {$version} */");
    if ($whole) {
        mkdir("{$dir}/includes", 0755, true);
        file_put_contents("{$dir}/includes/class-minn-admin.php", "<?php // {$version}\n");
    }
    return $dir;
};
$engine = static function (string $root, string $version, string $adminVersion) use ($plugin): string {
    mkdir("{$root}/minn", 0755, true);
    file_put_contents("{$root}/minn/bootstrap.php", "<?php\ndefine('MINN_ENGINE_VERSION', '{$version}');\n");
    $plugin("{$root}/minn/admin", $adminVersion);
    return "{$root}/minn";
};
$version = static fn (string $dir): string => preg_match("/MINN_ADMIN_VERSION', '([^']+)'/", (string) @file_get_contents("{$dir}/minn-admin.php"), $m) === 1 ? $m[1] : '';

return [
    'the engine\'s copy is laid into an empty place, whole, and nothing is left beside it' => static function () use ($scratch, $engine, $version) {
        $root = $scratch();
        try {
            $minn = $engine($root, '0.1.0', '0.45.0');
            $target = "{$root}/wp-content/plugins/minn-admin";
            $laid = AdminPlugin::lay("{$minn}/admin", $target);
            $beside = array_values(array_diff(scandir("{$root}/wp-content/plugins") ?: [], ['.', '..']));
            return $laid === 'laid' && $version($target) === '0.45.0' && is_file("{$target}/includes/class-minn-admin.php") && $beside === ['minn-admin'] ?: json_encode([$laid, $beside]);
        } finally {
            Files::deleteTree($root);
        }
    },
    'an older copy is upgraded in place, a newer or equal one is kept' => static function () use ($scratch, $engine, $plugin, $version) {
        $root = $scratch();
        try {
            $minn = $engine($root, '0.1.0', '0.45.0');
            $target = "{$root}/wp-content/plugins/minn-admin";
            $plugin($target, '0.44.0');
            file_put_contents("{$target}/left-behind.txt", 'old');
            $first = AdminPlugin::lay("{$minn}/admin", $target);
            $upgraded = $version($target) === '0.45.0' && !file_exists("{$target}/left-behind.txt");
            Files::deleteTree($target);
            $plugin($target, '0.46.0');
            $second = AdminPlugin::lay("{$minn}/admin", $target);
            $third = AdminPlugin::lay("{$minn}/admin", "{$root}/wp-content/plugins/minn-admin");
            return $first === 'upgraded' && $upgraded && $second === 'kept' && $version($target) === '0.46.0' && $third === 'kept' ?: json_encode([$first, $upgraded, $second, $version($target)]);
        } finally {
            Files::deleteTree($root);
        }
    },
    'a link in the way, or an engine copy that is not the whole plugin, is never laid' => static function () use ($scratch, $engine, $plugin) {
        $root = $scratch();
        try {
            $minn = $engine($root, '0.1.0', '0.45.0');
            mkdir("{$root}/wp-content/plugins", 0755, true);
            $elsewhere = $plugin("{$root}/dev-copy", '0.30.0');
            symlink($elsewhere, "{$root}/wp-content/plugins/minn-admin");
            $linked = AdminPlugin::lay("{$minn}/admin", "{$root}/wp-content/plugins/minn-admin");
            $stripped = $plugin("{$root}/stripped", '0.45.0', '', false);
            $partial = AdminPlugin::lay($stripped, "{$root}/wp-content/plugins/other-place");
            return $linked === 'skipped' && is_link("{$root}/wp-content/plugins/minn-admin") && $partial === 'skipped' && !file_exists("{$root}/wp-content/plugins/other-place") ?: json_encode([$linked, $partial]);
        } finally {
            @unlink("{$root}/wp-content/plugins/minn-admin");
            Files::deleteTree($root);
        }
    },
    'the plugin is served when whole and at least the engine\'s own; the engine\'s copy otherwise' => static function () use ($scratch, $engine, $plugin) {
        $root = $scratch();
        try {
            $minn = $engine($root, '0.1.0', '0.45.0');
            $content = "{$root}/wp-content";
            $none = AdminPlugin::app($minn, $content);
            $plugin("{$content}/plugins/minn-admin", '0.44.0');
            $older = AdminPlugin::app($minn, $content);
            Files::deleteTree("{$content}/plugins/minn-admin");
            $plugin("{$content}/plugins/minn-admin", '0.45.0');
            $equal = AdminPlugin::app($minn, $content);
            return $none->path() === '/minn/admin' && $older->dir() === "{$minn}/admin"
                && $equal->path() === '/wp-content/plugins/minn-admin' && $equal->dir() === "{$content}/plugins/minn-admin" && $equal->version() === '0.45.0'
                ?: json_encode([$none->path(), $older->dir(), $equal->path()]);
        } finally {
            Files::deleteTree($root);
        }
    },
    'a plugin asking for a newer engine (Requires Minn) is passed over for the engine\'s copy' => static function () use ($scratch, $engine, $plugin) {
        $root = $scratch();
        try {
            $minn = $engine($root, '0.1.0', '0.45.0');
            $content = "{$root}/wp-content";
            $plugin("{$content}/plugins/minn-admin", '0.47.0', " * Requires Minn: 0.2.0\n");
            $tooNew = AdminPlugin::app($minn, $content);
            Files::deleteTree("{$content}/plugins/minn-admin");
            $plugin("{$content}/plugins/minn-admin", '0.47.0', " * Requires Minn: 0.1.0\n");
            $fits = AdminPlugin::app($minn, $content);
            return $tooNew->path() === '/minn/admin' && $fits->path() === '/wp-content/plugins/minn-admin' ?: json_encode([$tooNew->path(), $fits->path()]);
        } finally {
            Files::deleteTree($root);
        }
    },
    'a development checkout always serves its own linked copy' => static function () use ($plugin, $scratch) {
        $root = $scratch();
        try {
            $plugin("{$root}/plugins/minn-admin", '99.0.0');
            $app = AdminPlugin::app(MINN_ENGINE_DIR, $root);
            return $app->path() === '/minn/admin' && $app->dir() === MINN_ENGINE_DIR . '/admin' ?: $app->dir();
        } finally {
            Files::deleteTree($root);
        }
    },
    'laying into an empty place activates the plugin once, sorted into the stored list' => static function () {
        $added = AdminPlugin::activated(serialize(['zz/zz.php', 'akismet/akismet.php']));
        $fresh = AdminPlugin::activated(null);
        $again = AdminPlugin::activated(serialize(['minn-admin/minn-admin.php']));
        return $added === ['akismet/akismet.php', 'minn-admin/minn-admin.php', 'zz/zz.php'] && $fresh === ['minn-admin/minn-admin.php'] && $again === null ?: json_encode([$added, $fresh, $again]);
    },
];
