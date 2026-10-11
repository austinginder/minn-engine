<?php
/**
 * What the boot sets up, run again (probe boot-defaults): the cookie and
 * plugin-directory constants; the connector registry refused outside init;
 * the default icon collections and the core blocks registered from their
 * metadata, each refused as already registered; and the cron spawn, its
 * loopback request caught by pre_http_request and answered here. The cron
 * option and the cron lock are put back as they were. wp_cron() is left out:
 * the engine runs due events its own way (see contracts/runtime.md).
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$notes = [];
add_action('doing_it_wrong_run', static function ($function, $message) use (&$notes) {
    $notes[] = [$function, strip_tags((string) $message)];
}, 10, 2);
add_filter('doing_it_wrong_trigger_error', '__return_false');
$heard = static function (callable $run) use (&$notes): array {
    $notes = [];
    $returned = $run();
    return ['returned' => $returned, 'notices' => $notes];
};
// WP-CLI forces site_url()'s scheme on one stack and not the other: the scheme is masked with the host.
$root = static fn ($value) => is_string($value) ? (string) preg_replace('#^https?://#', '{scheme}://', str_replace([rtrim(ABSPATH, '/'), (string) parse_url(home_url(), PHP_URL_HOST)], ['{root}', '{host}'], $value)) : $value;

$cookies = ['COOKIEHASH', 'USER_COOKIE', 'PASS_COOKIE', 'AUTH_COOKIE', 'SECURE_AUTH_COOKIE', 'LOGGED_IN_COOKIE', 'TEST_COOKIE', 'COOKIEPATH', 'SITECOOKIEPATH', 'ADMIN_COOKIE_PATH', 'PLUGINS_COOKIE_PATH', 'COOKIE_DOMAIN', 'RECOVERY_MODE_COOKIE'];
$plugins = ['WP_PLUGIN_URL', 'PLUGINDIR', 'WPMU_PLUGIN_URL', 'MUPLUGINDIR'];
$constants = static fn (array $names) => array_combine($names, array_map(static fn ($name) => defined($name) ? $root(constant($name)) : 'undefined', $names));
$say('the cookie constants', ['call' => $heard(static fn () => wp_cookie_constants()), 'values' => $constants($cookies)]);
$say('the plugin directory constants', ['call' => $heard(static fn () => wp_plugin_directory_constants()), 'values' => $constants($plugins), 'plugin folder' => basename(WP_PLUGIN_DIR), 'must-use folder' => basename(WPMU_PLUGIN_DIR)]);

$fired = [];
$listen = static function ($registry) use (&$fired) {
    $fired[] = [get_class($registry), WP_Connector_Registry::get_instance() === $registry ? 'the instance' : 'another registry'];
};
add_action('wp_connectors_init', $listen);
$instance = WP_Connector_Registry::get_instance();
$say('the connectors set up again outside init', $heard(static fn () => _wp_connectors_init()) + ['fired' => $fired, 'instance kept' => WP_Connector_Registry::get_instance() === $instance]);
remove_action('wp_connectors_init', $listen);
$say('the default icon collections again', $heard(static fn () => _wp_register_default_icon_collections()));
$blocks = $heard(static fn () => register_core_block_types_from_metadata());
$say('the core blocks registered from their metadata again', ['returned' => $blocks['returned'], 'refused' => array_map(static fn ($note) => preg_match('/"([^"]+)"/', $note[1], $m) ? [$note[0], $m[1]] : $note, $blocks['notices'])]);

// The cron spawn.
global $wpdb;
$row = static fn (string $name) => $wpdb->get_row($wpdb->prepare("SELECT option_value, autoload FROM {$wpdb->options} WHERE option_name = %s", $name), ARRAY_A);
$kept = array_combine($names = ['cron', '_transient_doing_cron', '_transient_timeout_doing_cron'], array_map($row, $names));
register_shutdown_function(static function () use ($kept, $wpdb): void {
    foreach ($kept as $name => $saved) {
        if ($saved === null) {
            $wpdb->delete($wpdb->options, ['option_name' => $name]);
        } else {
            $wpdb->replace($wpdb->options, ['option_name' => $name, 'option_value' => $saved['option_value'], 'autoload' => $saved['autoload']]);
        }
    }
    wp_cache_delete('alloptions', 'options');
    wp_cache_delete('notoptions', 'options');
    foreach (array_keys($kept) as $name) {
        wp_cache_delete($name, 'options');
    }
});
$sent = [];
$catch = static function ($pre, $args, $url) use (&$sent, $root) {
    $keep = array_intersect_key($args, array_flip(['method', 'timeout', 'redirection', 'blocking', 'body', 'sslverify']));
    ksort($keep);
    $sent[] = [$root((string) preg_replace('/doing_wp_cron=[0-9.]+/', 'doing_wp_cron={time}', $url)), $keep];
    return ['headers' => [], 'body' => '', 'response' => ['code' => 200, 'message' => 'OK'], 'cookies' => [], 'filename' => null];
};
$requested = [];
$request = static function ($cron_request, $doing_wp_cron) use (&$requested, $root) {
    $requested[] = ['keys' => array_keys($cron_request), 'url' => $root((string) preg_replace('/doing_wp_cron=[0-9.]+/', 'doing_wp_cron={time}', $cron_request['url'])), 'key' => is_numeric($cron_request['key']) && $cron_request['key'] == $doing_wp_cron, 'args' => $cron_request['args'], 'time' => is_numeric($doing_wp_cron)];
    return $cron_request;
};
add_filter('pre_http_request', $catch, 10, 3);
add_filter('cron_request', $request, 10, 2);
$spawn = static function () use (&$sent, &$requested) {
    [$sent, $requested] = [[], []];
    $returned = spawn_cron();
    return ['returned' => $returned, 'request filter' => $requested, 'sent' => $sent, 'locked' => get_transient('doing_cron') !== false];
};
// Nothing due: the stored events all lie ahead.
$events = _get_cron_array();
_set_cron_array(array_filter((array) $events, static fn ($time) => $time > time() + HOUR_IN_SECONDS, ARRAY_FILTER_USE_KEY));
delete_transient('doing_cron');
$cron = ['nothing due' => $spawn()];
wp_schedule_single_event(time() - 60, 'zz_cron_probe');
$cron['an event due'] = $spawn();
$cron['while locked'] = $spawn();
remove_filter('pre_http_request', $catch, 10);
remove_filter('cron_request', $request, 10);
$say('the cron spawn', $cron);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
