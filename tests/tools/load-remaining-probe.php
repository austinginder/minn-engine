<?php
/**
 * The rest of load.php (probe load-remaining): which admin the request is in
 * (by the screen and by the constants' absence), protected ajax actions, the
 * active and valid plugins, the server protocol, development
 * modes, file modifications, changeable ini values, JSON and XML requests,
 * basic auth (detected, and read from an Authorization header), and whether
 * the request uses themes. $_SERVER and $_REQUEST are put back at the end.
 * wp_debug_mode is left out: under WP-CLI the reference's error settings are
 * WP-CLI's own.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$server = $_SERVER;
$request = $_REQUEST;
$reporting = error_reporting();
$display = ini_get('display_errors');
register_shutdown_function(static function () use ($server, $request, $reporting, $display): void {
    $_SERVER = $server;
    $_REQUEST = $request;
    error_reporting($reporting);
    ini_set('display_errors', (string) $display);
});

$screen = static fn (string $admin) => new class ($admin) {
    public function __construct(private string $admin)
    {
    }

    public function in_admin($admin = null)
    {
        return $admin === null ? $this->admin !== '' : $this->admin === $admin;
    }
};
$admins = static fn () => [is_blog_admin(), is_network_admin(), is_user_admin()];
$which = [$admins()];
foreach (['site', 'network', 'user', ''] as $admin) {
    $GLOBALS['current_screen'] = $screen($admin);
    $which[] = [$admin, $admins()];
}
unset($GLOBALS['current_screen']);
$say('which admin', $which);

$protected = [];
foreach (['heartbeat', 'customize_save', 'zz-other', '', 'send-password-reset', 'wp-compression-test', 'toggle-auto-updates'] as $action) {
    $_REQUEST['action'] = $action;
    $protected[] = [$action, is_protected_ajax_action()];
}
unset($_REQUEST['action']);
$protected[] = ['no action', is_protected_ajax_action()];
add_filter('wp_protected_ajax_actions', $extra = static fn ($actions) => array_merge($actions, ['zz-protected']));
$_REQUEST['action'] = 'zz-protected';
$protected[] = ['filtered', is_protected_ajax_action()];
remove_filter('wp_protected_ajax_actions', $extra);
unset($_REQUEST['action']);
$say('protected ajax actions', $protected);

$say('active and valid plugins', array_map(static fn ($path) => str_replace(wp_normalize_path(WP_PLUGIN_DIR), '{plugins}', wp_normalize_path($path)), wp_get_active_and_valid_plugins()));

$protocols = [];
foreach (['HTTP/1.1', 'HTTP/2', 'HTTP/2.0', 'HTTP/3', 'HTTP/1.0', 'HTTP/9', 'zz', ''] as $protocol) {
    $_SERVER['SERVER_PROTOCOL'] = $protocol;
    $protocols[] = [$protocol, wp_get_server_protocol()];
}
unset($_SERVER['SERVER_PROTOCOL']);
$protocols[] = ['unset', wp_get_server_protocol()];
$say('server protocol', $protocols);

$mode = wp_get_development_mode();
$say('development modes', [$mode, array_map(static fn ($given) => wp_is_development_mode($given), ['core', 'plugin', 'theme', 'all', 'zz', ''])]);

add_filter('file_mod_allowed', $mods = static fn ($allowed, $context) => $context === 'zz_blocked' ? false : $allowed, 10, 2);
$say('file modifications', [wp_is_file_mod_allowed('automatic_updater'), wp_is_file_mod_allowed('capability_update_core'), wp_is_file_mod_allowed('zz_blocked'), wp_is_file_mod_allowed('')]);
remove_filter('file_mod_allowed', $mods, 10);

$say('changeable ini values', array_map(static fn ($setting) => [$setting, wp_is_ini_value_changeable($setting)], ['memory_limit', 'display_errors', 'error_reporting', 'upload_max_filesize', 'post_max_size', 'max_execution_time', 'zz.unknown', 'allow_url_fopen', 'disable_functions']));

$kinds = [];
foreach ([[[], 'none'], [['HTTP_ACCEPT' => 'application/json'], 'accept json'], [['HTTP_ACCEPT' => 'text/html, application/json;q=0.9'], 'accept mixed'], [['CONTENT_TYPE' => 'application/json; charset=utf-8'], 'content json'], [['HTTP_ACCEPT' => 'application/xml'], 'accept xml'], [['HTTP_ACCEPT' => 'text/xml'], 'accept text xml'], [['CONTENT_TYPE' => 'application/xml'], 'content xml'], [['CONTENT_TYPE' => 'text/xml'], 'content text xml'], [['HTTP_ACCEPT' => 'application/rss+xml'], 'accept rss'], [['HTTP_ACCEPT' => 'application/ld+json'], 'accept ld json'], [['HTTP_ACCEPT' => 'APPLICATION/JSON'], 'accept upper'], [['HTTP_ACCEPT' => 'application/atom+xml'], 'accept atom'], [['HTTP_ACCEPT' => 'application/rdf+xml'], 'accept rdf'], [['HTTP_ACCEPT' => 'text/xml+oembed'], 'accept oembed text'], [['HTTP_ACCEPT' => 'application/xml+oembed'], 'accept oembed application'], [['CONTENT_TYPE' => 'application/rss+xml'], 'content rss'], [['HTTP_ACCEPT' => 'TEXT/XML'], 'accept upper xml']] as [$headers, $label]) {
    unset($_SERVER['HTTP_ACCEPT'], $_SERVER['CONTENT_TYPE']);
    foreach ($headers as $key => $value) {
        $_SERVER[$key] = $value;
    }
    $kinds[] = [$label, wp_is_json_request(), wp_is_xml_request()];
}
unset($_SERVER['HTTP_ACCEPT'], $_SERVER['CONTENT_TYPE']);
$say('JSON and XML requests', $kinds);

$basic = [];
foreach ([[[], 'none'], [['PHP_AUTH_USER' => 'zz'], 'auth user'], [['PHP_AUTH_PW' => 'pw'], 'auth password only'], [['REMOTE_USER' => 'zz'], 'remote user'], [['REDIRECT_REMOTE_USER' => 'zz'], 'redirect remote user']] as [$values, $label]) {
    unset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'], $_SERVER['REMOTE_USER'], $_SERVER['REDIRECT_REMOTE_USER']);
    foreach ($values as $key => $value) {
        $_SERVER[$key] = $value;
    }
    $basic[] = [$label, wp_is_site_protected_by_basic_auth(), wp_is_site_protected_by_basic_auth('login'), wp_is_site_protected_by_basic_auth('admin'), wp_is_site_protected_by_basic_auth('front'), wp_is_site_protected_by_basic_auth('zz')];
}
unset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'], $_SERVER['REMOTE_USER'], $_SERVER['REDIRECT_REMOTE_USER']);
add_filter('wp_is_site_protected_by_basic_auth', $basicFilter = static fn ($is, $context) => $context === 'front' ? true : $is, 10, 2);
$basic[] = ['filtered', wp_is_site_protected_by_basic_auth('front'), wp_is_site_protected_by_basic_auth('login')];
remove_filter('wp_is_site_protected_by_basic_auth', $basicFilter, 10);
$say('basic auth detected', $basic);

$populated = [];
foreach ([['HTTP_AUTHORIZATION' => 'Basic ' . base64_encode('zz-user:zz:pass')], ['HTTP_AUTHORIZATION' => 'basic ' . base64_encode('lower:case')], ['REDIRECT_HTTP_AUTHORIZATION' => 'Basic ' . base64_encode('redirected:pw')], ['HTTP_AUTHORIZATION' => 'Bearer abc'], ['HTTP_AUTHORIZATION' => 'Basic !!!notbase64'], ['HTTP_AUTHORIZATION' => 'Basic ' . base64_encode('nocolon')], ['HTTP_AUTHORIZATION' => 'Basic ' . base64_encode('given:pw'), 'PHP_AUTH_USER' => 'already'], []] as $values) {
    unset($_SERVER['HTTP_AUTHORIZATION'], $_SERVER['REDIRECT_HTTP_AUTHORIZATION'], $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);
    foreach ($values as $key => $value) {
        $_SERVER[$key] = $value;
    }
    wp_populate_basic_auth_from_authorization_header();
    $populated[] = [array_keys($values), $_SERVER['PHP_AUTH_USER'] ?? null, $_SERVER['PHP_AUTH_PW'] ?? null];
}
unset($_SERVER['HTTP_AUTHORIZATION'], $_SERVER['REDIRECT_HTTP_AUTHORIZATION'], $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);
$say('basic auth from the authorization header', $populated);

add_filter('wp_using_themes', $themes = static fn ($using) => !$using);
$flipped = wp_using_themes();
remove_filter('wp_using_themes', $themes);
$say('using themes', [wp_using_themes(), $flipped, defined('WP_USE_THEMES')]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
