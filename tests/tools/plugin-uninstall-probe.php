<?php
/**
 * What deleting plugins does, as the reference's delete_plugins does it
 * (probe plugin-uninstall): three throwaway plugins the probe writes into
 * the plugins folder (one with an uninstall.php, one that registered an
 * uninstall hook, one bare file) are deleted; heard are the actions around
 * it (pre_uninstall_plugin, uninstall_<file>, delete_plugin,
 * deleted_plugin) with what they were handed, what each uninstall routine
 * saw, the registered uninstall hooks before and after, the answer, the
 * files left, and what the update check still lists. (Translations go
 * too, but the reference lists them once a request, before the probe
 * could write any.) Deleting a plugin that is not there is asked too. The
 * probe's plugins and options are removed at the end, whatever happens.
 * Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
foreach (['wp-admin/includes/plugin.php', 'wp-admin/includes/file.php'] as $admin) {
    if (is_file(ABSPATH . $admin)) {
        require_once ABSPATH . $admin;
    }
}
$dir = WP_PLUGIN_DIR;
$updates = get_site_transient('update_plugins');
$plugins = ['zz-uninstall-file/zz-uninstall-file.php', 'zz-uninstall-hook/zz-uninstall-hook.php', 'zz-uninstall-single.php'];
$sweep = static function () use ($dir, $updates): void {
    $updates === false ? delete_site_transient('update_plugins') : set_site_transient('update_plugins', $updates);
    foreach (['zz-uninstall-file', 'zz-uninstall-hook'] as $folder) {
        foreach (glob("{$dir}/{$folder}/*") ?: [] as $file) {
            unlink($file);
        }
        if (is_dir("{$dir}/{$folder}")) {
            rmdir("{$dir}/{$folder}");
        }
    }
    if (is_file("{$dir}/zz-uninstall-single.php")) {
        unlink("{$dir}/zz-uninstall-single.php");
    }
    $registered = get_option('uninstall_plugins');
    if (is_array($registered)) {
        unset($registered['zz-uninstall-hook/zz-uninstall-hook.php']);
        update_option('uninstall_plugins', $registered);
    }
    delete_option('zz_uninstall_seen');
};
register_shutdown_function($sweep);
$sweep();

$header = static fn (string $name): string => "<?php\n/*\n * Plugin Name: {$name}\n * Version: 1.0\n */\n";
mkdir("{$dir}/zz-uninstall-file");
file_put_contents("{$dir}/zz-uninstall-file/zz-uninstall-file.php", $header('Zz Uninstall File'));
file_put_contents("{$dir}/zz-uninstall-file/uninstall.php", "<?php\n\$seen = (array) get_option('zz_uninstall_seen', []);\n\$seen[] = ['uninstall.php', defined('WP_UNINSTALL_PLUGIN') ? WP_UNINSTALL_PLUGIN : null, basename(__DIR__)];\nupdate_option('zz_uninstall_seen', \$seen);\n");
mkdir("{$dir}/zz-uninstall-hook");
file_put_contents("{$dir}/zz-uninstall-hook/zz-uninstall-hook.php", $header('Zz Uninstall Hook') . "if (!function_exists('zz_uninstall_hook_cb')) {\n    function zz_uninstall_hook_cb() {\n        \$seen = (array) get_option('zz_uninstall_seen', []);\n        \$seen[] = ['callback', defined('WP_UNINSTALL_PLUGIN'), current_action()];\n        update_option('zz_uninstall_seen', \$seen);\n    }\n}\n");
file_put_contents("{$dir}/zz-uninstall-single.php", $header('Zz Uninstall Single'));
require_once "{$dir}/zz-uninstall-hook/zz-uninstall-hook.php";
$check = (object) ['last_checked' => 1, 'checked' => [], 'response' => [], 'translations' => [], 'no_update' => []];
foreach (['zz-uninstall-file/zz-uninstall-file.php', 'zz-uninstall-single.php', 'zz-uninstall-other/zz-uninstall-other.php'] as $plugin) {
    $check->response[$plugin] = (object) ['slug' => dirname($plugin), 'new_version' => '2.0'];
    $check->no_update[$plugin] = (object) ['slug' => dirname($plugin)];
    $check->checked[$plugin] = '1.0';
}
set_site_transient('update_plugins', $check);
register_uninstall_hook('zz-uninstall-hook/zz-uninstall-hook.php', 'zz_uninstall_hook_cb');
$mine = static fn ($registered): array => array_intersect_key((array) $registered, array_flip(['zz-uninstall-file/zz-uninstall-file.php', 'zz-uninstall-hook/zz-uninstall-hook.php', 'zz-uninstall-single.php']));
$say('the uninstall hooks registered', $mine(get_option('uninstall_plugins')));
$say('is_uninstallable_plugin', array_map('is_uninstallable_plugin', $plugins));

$heard = [];
add_action('pre_uninstall_plugin', static function ($plugin, $registered) use (&$heard, $mine): void {
    $heard[] = ['pre_uninstall_plugin', $plugin, $mine($registered)];
}, 10, 2);
foreach ($plugins as $plugin) {
    add_action("uninstall_{$plugin}", static function () use (&$heard, $plugin): void {
        $heard[] = ["uninstall_{$plugin}", did_action("uninstall_{$plugin}")];
    }, 5);
}
add_action('delete_plugin', static function ($plugin) use (&$heard, $dir): void {
    $heard[] = ['delete_plugin', $plugin, file_exists("{$dir}/{$plugin}")];
});
add_action('deleted_plugin', static function ($plugin, $deleted) use (&$heard, $dir): void {
    $heard[] = ['deleted_plugin', $plugin, $deleted, file_exists("{$dir}/{$plugin}")];
}, 10, 2);
$answer = delete_plugins($plugins);
$say('deleting the three', [$answer, $heard]);
$say('what the uninstall routines saw', get_option('zz_uninstall_seen'));
$say('the uninstall hooks registered after', $mine(get_option('uninstall_plugins')));
$say('the files left', array_values(array_filter(['zz-uninstall-file', 'zz-uninstall-hook', 'zz-uninstall-single.php'], static fn ($name) => file_exists("{$dir}/{$name}"))));
$left = get_site_transient('update_plugins');
$say('what the update check still lists', is_object($left) ? ['response' => array_keys((array) $left->response), 'no_update' => array_keys((array) ($left->no_update ?? [])), 'checked' => array_keys((array) ($left->checked ?? []))] : $left);
$heard = [];
$missing = delete_plugins(['zz-uninstall-nowhere/zz-uninstall-nowhere.php']);
$say('deleting a plugin that is not there', [is_wp_error($missing) ? [$missing->get_error_code(), $missing->get_error_message()] : $missing, $heard]);
$say('deleting nothing', delete_plugins([]));

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
