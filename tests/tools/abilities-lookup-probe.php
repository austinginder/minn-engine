<?php
/**
 * Looking abilities up, and connector keys kept out of the settings
 * answer (probe abilities-lookup): an ability's meta matched against
 * conditions, wp_get_abilities() filtered by category, namespace and meta,
 * its per-item and result callbacks and filters, and the settings route's
 * answer with its connector keys masked (and checked on a write, the check
 * answered without the network). The probe's abilities and categories go
 * at the end; nothing is stored.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
if (!did_action('init') && class_exists(Minn\Runtime\Plugins::class)) {
    // A CLI boot of the engine has not run the lifecycle yet (its plugins, then init, where connectors register); the reference has by the time eval-file runs.
    Minn\Runtime\Plugins::load(Minn\Runtime\Runtime::current());
}
// A write the settings check makes (a key it cannot validate is stored empty) is undone: each connector option goes back as it was.
global $wpdb;
$keys = ['connectors_ai_anthropic_api_key', 'connectors_ai_google_api_key', 'connectors_ai_openai_api_key', 'wordpress_api_key', 'connectors_spam_filtering_cleantalk_api_key'];
$stored = [];
foreach ($keys as $key) {
    $stored[$key] = $wpdb->get_row($wpdb->prepare("SELECT option_value, autoload FROM {$wpdb->options} WHERE option_name = %s", $key), ARRAY_A);
}
register_shutdown_function(static function () use ($stored, $wpdb): void {
    foreach ($stored as $key => $row) {
        if ($row === null) {
            $wpdb->delete($wpdb->options, ['option_name' => $key]);
        } else {
            $wpdb->update($wpdb->options, $row, ['option_name' => $key]);
        }
        wp_cache_delete($key, 'options');
    }
    wp_cache_delete('alloptions', 'options');
    wp_cache_delete('notoptions', 'options');
});

$say('meta matched', array_map(static fn ($pair) => _wp_get_abilities_match_meta($pair[0], $pair[1]), [
    [[], []], [['a' => 1], []], [['a' => 1], ['a' => 1]], [['a' => 1], ['a' => '1']], [['a' => true], ['a' => 1]], [['a' => 1, 'b' => 2], ['a' => 1, 'b' => 2]], [['a' => 1, 'b' => 3], ['a' => 1, 'b' => 2]],
    [['a' => 1], ['b' => 1]], [['a' => null], ['a' => null]], [[], ['a' => null]], [['a' => false], ['a' => false]], [['a' => 0], ['a' => false]],
    [['mcp' => ['public' => true, 'type' => 'tool']], ['mcp' => ['public' => true]]], [['mcp' => ['public' => true]], ['mcp' => ['public' => true, 'type' => 'tool']]], [['mcp' => ['public' => false]], ['mcp' => ['public' => true]]],
    [['mcp' => 'flat'], ['mcp' => ['public' => true]]], [['mcp' => ['public' => true]], ['mcp' => []]], [['mcp' => ['public' => true]], ['mcp' => 'x']], [['tags' => ['x', 'y']], ['tags' => ['x']]], [['tags' => ['x', 'y']], ['tags' => ['x', 'y']]],
    [['tags' => ['x', 'y']], ['tags' => ['y', 'x']]], [['a' => ['b' => ['c' => 1, 'd' => 2]]], ['a' => ['b' => ['c' => 1]]]], [['a' => ['b' => ['c' => 1]]], ['a' => ['b' => ['c' => 2]]]],
]));

add_action('wp_abilities_api_categories_init', static function (): void {
    wp_register_ability_category('zz-a', ['label' => 'Zz A', 'description' => 'A.']);
    wp_register_ability_category('zz-b', ['label' => 'Zz B', 'description' => 'B.']);
});
add_action('wp_abilities_api_init', static function (): void {
    $base = ['description' => 'Probe.', 'execute_callback' => static fn () => 'ok', 'permission_callback' => '__return_true'];
    wp_register_ability('zz/one', ['label' => 'Zz One', 'category' => 'zz-a', 'meta' => ['show_in_rest' => true, 'mcp' => ['public' => true, 'type' => 'tool']]] + $base);
    wp_register_ability('zz/two', ['label' => 'Zz Two', 'category' => 'zz-b', 'meta' => ['show_in_rest' => false]] + $base);
    wp_register_ability('zzx/three', ['label' => 'Zz Three', 'category' => 'zz-a'] + $base);
    wp_register_ability('zz-other/four', ['label' => 'Zz Four', 'category' => 'zz-b', 'meta' => ['mcp' => ['public' => false]]] + $base);
});
// Only the probe's own abilities are compared; the core ones each stack registers are left aside.
$names = static fn ($abilities) => is_array($abilities) ? array_values(array_filter(array_map(static fn ($key, $ability) => [$key, $ability instanceof WP_Ability ? $ability->get_name() : gettype($ability)], array_keys($abilities), $abilities), static fn ($pair) => str_starts_with((string) $pair[1], 'zz'))) : gettype($abilities);
$find = static fn (array $args) => $names(wp_get_abilities($args));
$say('abilities found', [
    $find([]),
    $find(['category' => 'zz-a']),
    $find(['category' => '']),
    $find(['namespace' => 'zz']),
    $find(['namespace' => 'zz/']),
    $find(['namespace' => 'zz-other']),
    $find(['namespace' => 'z']),
    $find(['meta' => ['show_in_rest' => true]]),
    $find(['meta' => ['show_in_rest' => false]]),
    $find(['meta' => ['mcp' => ['public' => true]]]),
    $find(['meta' => ['mcp' => ['public' => false]]]),
    $find(['meta' => []]),
    $find(['meta' => ['zz-missing' => 1]]),
    $find(['category' => 'zz-a', 'namespace' => 'zz', 'meta' => ['show_in_rest' => true]]),
    $find(['category' => 'zz-b', 'namespace' => 'zz']),
    $find(['zz-unknown' => 1]),
]);
$say('the per-item callback', [
    $find(['item_include_callback' => static fn (WP_Ability $ability) => $ability->get_name() !== 'zz/one']),
    $find(['item_include_callback' => static fn () => 1]),
    $find(['item_include_callback' => static fn () => 'yes']),
    $find(['item_include_callback' => static fn () => null]),
    $find(['item_include_callback' => static fn () => 0]),
    $find(['category' => 'zz-a', 'item_include_callback' => static fn (WP_Ability $ability) => $ability->get_name() === 'zzx/three']),
    $find(['item_include_callback' => 'zz_not_callable']),
]);
$say('the result callback', [
    $find(['result_callback' => static fn (array $abilities) => array_reverse($abilities, true)]),
    $find(['result_callback' => static fn (array $abilities) => array_slice($abilities, 0, 1)]),
    $find(['namespace' => 'zz', 'result_callback' => static fn (array $abilities) => array_values($abilities)]),
    $find(['result_callback' => static fn () => 'not an array']),
    $find(['result_callback' => 'zz_not_callable']),
]);
$say('the filters', (static function () use ($find, $names) {
    $heard = [];
    $item = static function ($include, ...$rest) use (&$heard) {
        if (str_starts_with($rest[0]->get_name(), 'zz')) {
            $heard[] = ['item', $include, count($rest), $rest[0]->get_name(), $rest[1] ?? null];
        }
        return $rest[0]->get_name() === 'zz/two' ? false : $include;
    };
    $result = static function ($abilities, ...$rest) use (&$heard, $names) {
        $heard[] = ['result', $names($abilities), $rest];
        return array_filter($abilities, static fn ($ability) => $ability->get_name() !== 'zzx/three');
    };
    add_filter('wp_get_abilities_item_include', $item, 10, 5);
    add_filter('wp_get_abilities_result', $result, 10, 5);
    $out = [$find([]), $find(['category' => 'zz-a', 'item_include_callback' => static fn () => true]), $find(['namespace' => 'zz', 'item_include_callback' => static fn (WP_Ability $ability) => $ability->get_name() !== 'zz/one'])];
    remove_filter('wp_get_abilities_item_include', $item, 10);
    remove_filter('wp_get_abilities_result', $result, 10);
    $out[] = array_map(static fn ($entry) => $entry[0] === 'result' ? [$entry[0], $entry[1], array_map(static fn ($arg) => is_array($arg) ? array_keys($arg) : $arg, $entry[2])] : $entry, $heard);
    return $out;
})());
foreach (['zz/one', 'zz/two', 'zzx/three', 'zz-other/four'] as $name) {
    wp_unregister_ability($name);
}
wp_unregister_ability_category('zz-a');
wp_unregister_ability_category('zz-b');

$requests = [];
add_filter('pre_http_request', static function ($pre, $args, $url) use (&$requests) {
    $requests[] = (string) parse_url($url, PHP_URL_HOST);
    return ['headers' => [], 'body' => '{"error":"zz"}', 'response' => ['code' => 401, 'message' => 'Unauthorized'], 'cookies' => [], 'filename' => null];
}, 10, 3);
$settings = ['title' => 'Zz', 'connectors_ai_openai_api_key' => 'sk-zz-1234567890abcdefXYZ', 'connectors_ai_anthropic_api_key' => '', 'connectors_ai_google_api_key' => 'short', 'wordpress_api_key' => 'zzakismetkey99', 'connectors_spam_filtering_cleantalk_api_key' => 'cleantalkzz12345', 'zz_other' => 'sk-not-a-connector'];
$dispatched = static function (string $method, string $route, $data, array $body = []) use (&$requests) {
    $requests = [];
    $request = new WP_REST_Request($method, $route);
    foreach ($body as $key => $value) {
        $request->set_param($key, $value);
    }
    $response = _wp_connectors_rest_settings_dispatch(new WP_REST_Response($data), rest_get_server(), $request);
    $written = [];
    foreach (['connectors_ai_google_api_key', 'connectors_ai_openai_api_key', 'connectors_ai_anthropic_api_key'] as $key) {
        $written[$key] = get_option($key, '(unset)');
    }
    return [$response instanceof WP_REST_Response ? $response->get_data() : gettype($response), $requests, $written];
};
$registry = WP_Connector_Registry::get_instance();
$registry->register('zz_probe_app', ['name' => 'Zz App', 'type' => 'zz_kind', 'authentication' => ['method' => 'application_password', 'setting_name' => 'zz_probe_app_creds']]);
$say('application password credentials in the answer', [
    $dispatched('GET', '/wp/v2/settings', ['zz_probe_app_creds' => ['username' => 'zz', 'password' => 'abcd efgh ijkl mnop']]),
    $dispatched('GET', '/wp/v2/settings', ['zz_probe_app_creds' => '{"username":"zz","password":"abcd efgh ijkl mnop"}']),
    $dispatched('GET', '/wp/v2/settings', ['zz_probe_app_creds' => ['username' => 'zz']]),
    $dispatched('POST', '/wp/v2/settings', ['zz_probe_app_creds' => ['username' => 'zz', 'password' => 'abcd efgh ijkl mnop']]),
]);
$registry->unregister('zz_probe_app');
$say('connector keys in the settings answer', [
    $dispatched('GET', '/wp/v2/settings', $settings),
    $dispatched('GET', '/wp/v2/posts', $settings),
    $dispatched('GET', '/wp/v2/settings', 'not an array'),
    $dispatched('GET', '/wp/v2/settings', ['title' => 'only']),
    $dispatched('POST', '/wp/v2/settings', $settings, ['connectors_ai_openai_api_key' => 'sk-zz-1234567890abcdefXYZ']),
    $dispatched('PUT', '/wp/v2/settings', $settings, ['wordpress_api_key' => 'zzakismetkey99']),
    $dispatched('POST', '/wp/v2/settings', $settings, ['title' => 'Zz']),
    $dispatched('GET', '/wp/v2/settings/', $settings),
    $dispatched('GET', '/WP/V2/SETTINGS', $settings),
]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
