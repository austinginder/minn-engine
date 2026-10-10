<?php
/**
 * The rest of option.php (probe option-remaining): network options on a
 * single site, added, read, updated and deleted through their hooks (the
 * network id given and not, a pre filter answering, defaults, the value as
 * stored with its autoload), an option's autoload set alone, a registered
 * setting's default given back when none is passed, and the core settings
 * registered. The probe's options are removed at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$names = ['zz_or_net', 'zz_or_net_list', 'zz_or_auto', 'zz_or_auto_two', 'zz_or_default'];
register_shutdown_function(static function () use ($names): void {
    foreach ($names as $name) {
        delete_option($name);
    }
});
foreach ($names as $name) {
    delete_option($name);
}
global $wpdb;
$row = static fn (string $name) => $wpdb->get_row($wpdb->prepare("SELECT option_value, autoload FROM {$wpdb->options} WHERE option_name = %s", $name), ARRAY_A);

$heard = [];
$hooks = ['pre_add_site_option_zz_or_net' => 3, 'add_site_option_zz_or_net' => 3, 'add_site_option' => 3, 'pre_site_option_zz_or_net' => 4, 'pre_site_option' => 4, 'default_site_option_zz_or_net' => 3, 'site_option_zz_or_net' => 3,
    'pre_update_site_option_zz_or_net' => 4, 'update_site_option_zz_or_net' => 4, 'update_site_option' => 4, 'pre_delete_site_option_zz_or_net' => 2, 'delete_site_option_zz_or_net' => 2, 'delete_site_option' => 3];
foreach ($hooks as $hook => $count) {
    add_filter($hook, static function (...$args) use ($hook, &$heard) {
        if (str_contains($hook, 'zz_or_net') || ($args[0] ?? null) === 'zz_or_net') {
            $heard[] = [$hook, ...$args];
        }
        return $args[0];
    }, 10, $count);
}
$network = [];
$network[] = get_network_option(null, 'zz_or_net', 'fallback');
$network[] = add_network_option(null, 'zz_or_net', 'first');
$network[] = $row('zz_or_net');
$network[] = add_network_option(1, 'zz_or_net', 'again');
$network[] = get_network_option(1, 'zz_or_net');
$network[] = get_network_option(7, 'zz_or_net');
$network[] = update_network_option(null, 'zz_or_net', 'second');
$network[] = update_network_option(null, 'zz_or_net', 'second');
$network[] = [get_network_option(null, 'zz_or_net'), get_option('zz_or_net'), get_site_option('zz_or_net'), $row('zz_or_net')];
$network[] = update_network_option(null, 'zz_or_net_list', ['a' => 1]);
$network[] = [$row('zz_or_net_list'), get_network_option(null, 'zz_or_net_list')];
$network[] = delete_network_option(null, 'zz_or_net');
$network[] = delete_network_option(null, 'zz_or_net');
$network[] = get_network_option(null, 'zz_or_net', 'gone');
$network[] = get_network_option(null, '', 'empty name');
$network[] = $heard;
$heard = [];
add_filter('pre_site_option_zz_or_net', $answer = static fn () => 'from the pre filter');
$network[] = [get_network_option(null, 'zz_or_net', 'unused'), $heard];
remove_filter('pre_site_option_zz_or_net', $answer, 10);
$say('network options on a single site', $network);

add_option('zz_or_auto', 'value', '', true);
add_option('zz_or_auto_two', 'value', '', false);
$autoload = [$row('zz_or_auto')['autoload'], $row('zz_or_auto_two')['autoload']];
foreach ([[false, 'zz_or_auto'], [false, 'zz_or_auto'], [true, 'zz_or_auto'], ['yes', 'zz_or_auto_two'], ['no', 'zz_or_auto_two'], ['on', 'zz_or_auto_two'], ['off', 'zz_or_auto_two'], [null, 'zz_or_auto_two'], ['auto', 'zz_or_auto_two'], [true, 'zz_or_missing']] as [$value, $name]) {
    $autoload[] = [wp_set_option_autoload($name, $value), $row($name)['autoload'] ?? null];
}
$say('an option autoload set alone', $autoload);

register_setting('zz_or_group', 'zz_or_default', ['type' => 'string', 'default' => 'registered default']);
$defaults = [
    filter_default_option(false, 'zz_or_default', false), filter_default_option('given', 'zz_or_default', true), filter_default_option('given', 'zz_or_default', false), filter_default_option(false, 'zz_or_unregistered', false),
    get_option('zz_or_default'), get_option('zz_or_default', 'passed'), has_filter('default_option_zz_or_default', 'filter_default_option'),
];
unregister_setting('zz_or_group', 'zz_or_default');
$defaults[] = get_option('zz_or_default');
$say('a registered default', $defaults);

register_initial_settings();
$settings = get_registered_settings();
$core = [];
foreach (['blogname', 'blogdescription', 'siteurl', 'home', 'admin_email', 'timezone_string', 'date_format', 'time_format', 'start_of_week', 'WPLANG', 'use_smilies', 'default_category', 'default_post_format', 'posts_per_page', 'show_on_front', 'page_on_front', 'page_for_posts', 'default_ping_status', 'default_comment_status', 'site_logo', 'site_icon'] as $name) {
    $setting = $settings[$name] ?? null;
    if (is_array($setting)) {
        unset($setting['sanitize_callback']);
        $setting['show_in_rest'] = is_array($setting['show_in_rest']) ? array_keys($setting['show_in_rest']) : $setting['show_in_rest'];
        ksort($setting);
    }
    $core[$name] = $setting;
}
$say('the core settings registered', $core);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
