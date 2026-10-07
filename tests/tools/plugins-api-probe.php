<?php
/**
 * plugins_api() as plugins call it (probe plugins-api): a self-hosted
 * plugin answering plugin_information for its own slug through the
 * plugins_api filter (what the filter and plugins_api_args are handed, the
 * answer as it comes back, plugins_api_result after it), an answer that is
 * an error, an action nobody answers and the directory refuses, and the
 * directory's own answer for a real slug (the fields that do not move).
 * Same protocol as api-probe.php.
 */

if (!function_exists('plugins_api')) {
    require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
}
$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$heard = [];
add_filter('plugins_api_args', static function ($args, $action) use (&$heard) {
    $heard[] = ['plugins_api_args', get_debug_type($args), $action, (array) $args];
    return $args;
}, 10, 2);
add_filter('plugins_api', static function ($result, $action, $args) use (&$heard) {
    $heard[] = ['plugins_api', $result, $action, get_debug_type($args), $args->slug ?? null];
    if (($args->slug ?? '') === 'zz-self-hosted') {
        return (object) ['name' => 'ZZ Self Hosted', 'slug' => 'zz-self-hosted', 'version' => '2.0.0', 'download_link' => 'https://example.test/zz.zip', 'sections' => ['description' => 'Mine.']];
    }
    if (($args->slug ?? '') === 'zz-broken') {
        return new WP_Error('zz_down', 'The vendor is down.');
    }
    return $result;
}, 10, 3);
add_filter('plugins_api_result', static function ($res, $action, $args) use (&$heard) {
    $heard[] = ['plugins_api_result', get_debug_type($res), $action, $args->slug ?? null];
    return $res;
}, 10, 3);
$shape = static fn ($r) => $r instanceof WP_Error ? ['error', $r->get_error_code(), $r->get_error_message()] : (is_object($r) ? ['object', get_debug_type($r), array_intersect_key((array) $r, array_flip(['name', 'slug', 'version', 'download_link', 'sections', 'author', 'requires']))] : $r);
$say('self-hosted', $shape(plugins_api('plugin_information', ['slug' => 'zz-self-hosted'])));
$say('self-hosted, args as an object', $shape(plugins_api('plugin_information', (object) ['slug' => 'zz-self-hosted', 'fields' => ['sections' => false]])));
$say('an error answer', $shape(plugins_api('plugin_information', ['slug' => 'zz-broken'])));
$say('what the filters heard', $heard);
$heard = [];
$real = plugins_api('plugin_information', ['slug' => 'hello-dolly', 'fields' => ['sections' => false]]);
$say('the directory', $real instanceof WP_Error ? ['error', $real->get_error_code()] : [get_debug_type($real), $real->name ?? null, $real->slug ?? null, $real->author ?? null, isset($real->sections)]);
$say('the directory, heard', array_map(static fn ($h) => array_slice($h, 0, 3), $heard));
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
