<?php
/**
 * What switching a plugin on and off does, as the reference's
 * activate_plugin and deactivate_plugins do it (probe plugin-activation):
 * throwaway plugins the probe writes into the plugins folder are activated
 * and deactivated. Heard are the actions around each call with what they
 * were handed; seen is whether the plugin's file was loaded and its
 * activation and deactivation hooks ran (and with what); then the answer
 * and the probe's plugins in active_plugins. Refusals asked: a file that is
 * not there, a plugin that prints on activation, a PHP or WordPress version
 * the plugin needs and the site lacks, a required plugin that is missing.
 * Also: silent mode, activating twice, a list with one bad entry, a full
 * path, a single-file plugin, and a readme.txt asking for more than the
 * header (only the header counts). The probe's plugins and options are
 * removed at the end, whatever happens. Same protocol as api-probe.php.
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
$hook = 'zz-activate-hook/zz-activate-hook.php';
$folders = ['zz-activate-hook', 'zz-activate-output', 'zz-activate-php', 'zz-activate-wp', 'zz-activate-deps', 'zz-activate-both', 'zz-activate-needs-hook', 'zz-activate-silent', 'zz-activate-two-deps', 'zz-activate-readme', 'zz-activate-slugs'];
$mine = static fn (array $list): array => array_values(array_filter($list, static fn ($file): bool => is_string($file) && str_starts_with($file, 'zz-activate')));
$sweep = static function () use ($dir, $folders, $mine): void {
    update_option('active_plugins', array_values(array_diff((array) get_option('active_plugins', []), $mine((array) get_option('active_plugins', [])))));
    foreach ($folders as $folder) {
        foreach (glob("{$dir}/{$folder}/*") ?: [] as $file) {
            unlink($file);
        }
        if (is_dir("{$dir}/{$folder}")) {
            rmdir("{$dir}/{$folder}");
        }
    }
    if (is_file("{$dir}/zz-activate-single.php")) {
        unlink("{$dir}/zz-activate-single.php");
    }
    delete_option('zz_activate_ran');
};
$sweep();
register_shutdown_function($sweep);

$plugin = static fn (string $name, string $headers, string $body = ''): string => "<?php\n/**\n * Plugin Name: {$name}\n{$headers} */\n{$body}\n";
foreach ($folders as $folder) {
    mkdir("{$dir}/{$folder}", 0755, true);
}
file_put_contents("{$dir}/{$hook}", $plugin('ZZ Activate Hook', '', implode("\n", [
    "\$GLOBALS['zz_activate_seen'][] = 'loaded ' . (did_action('activate_plugin') > (\$GLOBALS['zz_activate_before'] ?? 0) ? 'after' : 'before') . ' activate_plugin';",
    "register_activation_hook(__FILE__, static function (\$network_wide = null) { \$GLOBALS['zz_activate_seen'][] = 'activation hook: ' . var_export(\$network_wide, true); update_option('zz_activate_ran', 'yes'); });",
    "register_deactivation_hook(__FILE__, static function (\$network_wide = null) { \$GLOBALS['zz_activate_seen'][] = 'deactivation hook: ' . var_export(\$network_wide, true); });",
])));
file_put_contents("{$dir}/zz-activate-output/zz-activate-output.php", $plugin('ZZ Activate Output', '', "echo 'Hello from activation';"));
file_put_contents("{$dir}/zz-activate-php/zz-activate-php.php", $plugin('ZZ Activate PHP', " * Requires PHP: 99.0\n"));
file_put_contents("{$dir}/zz-activate-wp/zz-activate-wp.php", $plugin('ZZ Activate WP', " * Requires at least: 99.0\n"));
file_put_contents("{$dir}/zz-activate-deps/zz-activate-deps.php", $plugin('ZZ Activate Deps', " * Requires Plugins: zz-activate-not-installed\n"));
file_put_contents("{$dir}/zz-activate-single.php", $plugin('ZZ Activate Single', ''));
file_put_contents("{$dir}/zz-activate-both/zz-activate-both.php", $plugin('ZZ Activate Both', " * Requires at least: 99.0\n * Requires PHP: 99.0\n"));
file_put_contents("{$dir}/zz-activate-needs-hook/zz-activate-needs-hook.php", $plugin('ZZ Activate Needs Hook', " * Requires Plugins: zz-activate-hook\n"));
file_put_contents("{$dir}/zz-activate-two-deps/zz-activate-two-deps.php", $plugin('ZZ Activate Two Deps', " * Requires Plugins: zz-activate-not-installed, zz-activate-hook\n"));
file_put_contents("{$dir}/zz-activate-silent/zz-activate-silent.php", $plugin('ZZ Activate Silent', '', "\$GLOBALS['zz_activate_seen'][] = 'silent plugin loaded';"));
file_put_contents("{$dir}/zz-activate-readme/zz-activate-readme.php", $plugin('ZZ Activate Readme', ''));
file_put_contents("{$dir}/zz-activate-slugs/zz-activate-slugs.php", $plugin('ZZ Activate Slugs', " * Requires Plugins: zz-activate-bbb, ZZ-Upper, not a slug, ../zz-up, zz-activate-aaa,, zz_under, zz-two--dash, -zz-lead, zz-activate-hook, zz-activate-aaa\n"));
file_put_contents("{$dir}/zz-activate-readme/readme.txt", "=== ZZ Activate Readme ===\nRequires at least: 99.0\nRequires PHP: 99.0\nRequires Plugins: zz-activate-not-installed\nStable tag: 1.0\n\nA readme asking for more than the header does.\n");
wp_clean_plugins_cache(false);

// The actions a call fires (an action is counted before 'all' runs; a filter is not), its arguments in words.
$heard = [];
$listening = false;
$describe = static function ($value) use ($mine) {
    if (is_array($value)) {
        return ['zz' => $mine($value)];
    }
    if (is_object($value)) {
        return 'object:' . get_class($value);
    }
    return $value;
};
add_action('all', static function (string $name) use (&$heard, &$listening, $describe): void {
    if (!$listening || did_action($name) === 0 || preg_match('/^(activate_|activated_plugin|deactivate_|deactivated_plugin|update_option_active_plugins|plugin_loaded)/', $name) !== 1) {
        return;
    }
    $heard[] = [$name, array_map($describe, array_slice(func_get_args(), 1))];
}, PHP_INT_MIN);
$answer = static fn ($result) => is_wp_error($result)
    ? ['error' => $result->get_error_code(), 'message' => $result->get_error_message(), 'data' => is_array($result->get_error_data()) && array_filter($result->get_error_data(), 'is_object') !== [] ? array_keys($result->get_error_data()) : $result->get_error_data()]
    : $result;
$ask = static function (string $label, callable $call) use ($say, &$heard, &$listening, $answer, $mine): void {
    $heard = [];
    $GLOBALS['zz_activate_seen'] = [];
    $GLOBALS['zz_activate_before'] = did_action('activate_plugin');
    $listening = true;
    ob_start();
    try {
        $result = $call();
    } finally {
        $printed = (string) ob_get_clean();
        $listening = false;
    }
    $say($label, [
        'answer' => $answer($result),
        'heard' => $heard,
        'seen' => $GLOBALS['zz_activate_seen'],
        'printed' => $printed,
        'active' => $mine((array) get_option('active_plugins', [])),
        'activation hook stored' => get_option('zz_activate_ran', null),
    ]);
};

$ask('activate a plugin with activation and deactivation hooks', static fn () => activate_plugin($hook));
$ask('activate it again', static fn () => activate_plugin($hook));
$ask('deactivate it', static fn () => deactivate_plugins($hook));
$ask('deactivate it again', static fn () => deactivate_plugins($hook));
delete_option('zz_activate_ran');
$ask('activate silently', static fn () => activate_plugin($hook, '', false, true));
$ask('deactivate silently', static fn () => deactivate_plugins($hook, true));
$ask('activate by its full path', static fn () => activate_plugin(WP_PLUGIN_DIR . '/' . $hook));
deactivate_plugins($hook, true);
$ask('activate a single-file plugin', static fn () => activate_plugin('zz-activate-single.php'));
deactivate_plugins('zz-activate-single.php', true);
$ask('activate a plugin that is not there', static fn () => activate_plugin('zz-activate-nowhere/zz-activate-nowhere.php'));
$ask('activate a plugin that prints', static fn () => activate_plugin('zz-activate-output/zz-activate-output.php'));
deactivate_plugins('zz-activate-output/zz-activate-output.php', true);
$ask('activate a plugin that needs a newer PHP', static fn () => activate_plugin('zz-activate-php/zz-activate-php.php'));
$ask('activate a plugin that needs a newer WordPress', static fn () => activate_plugin('zz-activate-wp/zz-activate-wp.php'));
$ask('activate a plugin whose required plugin is missing', static fn () => activate_plugin('zz-activate-deps/zz-activate-deps.php'));
$ask('activate a plugin that needs a newer PHP and WordPress', static fn () => activate_plugin('zz-activate-both/zz-activate-both.php'));
$ask('activate a plugin with two required plugins, one missing and one inactive', static fn () => activate_plugin('zz-activate-two-deps/zz-activate-two-deps.php'));
$ask('activate a plugin whose required plugin is installed but inactive', static fn () => activate_plugin('zz-activate-needs-hook/zz-activate-needs-hook.php'));
activate_plugin($hook, '', false, true);
$ask('activate a plugin whose required plugin is active', static fn () => activate_plugin('zz-activate-needs-hook/zz-activate-needs-hook.php'));
deactivate_plugins(['zz-activate-needs-hook/zz-activate-needs-hook.php', $hook], true);
$ask('activate a plugin never loaded, silently', static fn () => activate_plugin('zz-activate-silent/zz-activate-silent.php', '', false, true));
deactivate_plugins('zz-activate-silent/zz-activate-silent.php', true);
$ask('activate a list with one plugin that is not there', static fn () => activate_plugins([$hook, 'zz-activate-nowhere/zz-activate-nowhere.php']));
deactivate_plugins($hook, true);
$ask('activate a plugin whose required plugins are named in no order, some not slugs, one twice', static fn () => activate_plugin('zz-activate-slugs/zz-activate-slugs.php'));
$ask('activate a plugin whose readme, not its header, asks for newer versions and a plugin', static fn () => activate_plugin('zz-activate-readme/zz-activate-readme.php'));
deactivate_plugins('zz-activate-readme/zz-activate-readme.php', true);
$ask('validate a plugin that is not there', static fn () => validate_plugin('zz-activate-nowhere/zz-activate-nowhere.php'));
$ask('validate one that is', static fn () => validate_plugin($hook));
$ask('validate the requirements of one that needs a newer PHP', static fn () => validate_plugin_requirements('zz-activate-php/zz-activate-php.php'));
$ask('validate the requirements of one whose required plugin is missing', static fn () => validate_plugin_requirements('zz-activate-deps/zz-activate-deps.php'));
$ask('validate the requirements of one that asks for nothing', static fn () => validate_plugin_requirements($hook));

// The "Learn more about updating PHP" address in the PHP refusals.
$say('update-PHP address', wp_get_update_php_url());
$say('default update-PHP address', wp_get_default_update_php_url());
$elsewhere = static fn (): string => 'https://example.com/update-php/';
add_filter('wp_update_php_url', $elsewhere);
$say('update-PHP address, filtered', wp_get_update_php_url());
remove_filter('wp_update_php_url', $elsewhere);
add_filter('wp_update_php_url', '__return_empty_string');
$say('update-PHP address, filtered to nothing', wp_get_update_php_url());
remove_filter('wp_update_php_url', '__return_empty_string');

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
