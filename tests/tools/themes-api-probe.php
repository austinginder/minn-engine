<?php
/**
 * themes_api() as plugins call it (probe themes-api): a theme seller
 * answering theme_information for its own slug through the themes_api
 * filter (what the filter and themes_api_args are handed, the answer as it
 * comes back, themes_api_result after it), an answer that is an error, an
 * action nobody answers and the directory refuses, and the directory's own
 * answers for a real slug and a search (the fields that do not move).
 * Needs the network on both stacks. Same protocol as api-probe.php.
 */

if (!function_exists('themes_api')) {
    require_once ABSPATH . 'wp-admin/includes/theme.php';
}
$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$heard = [];
add_filter('themes_api_args', static function ($args, $action) use (&$heard) {
    $heard[] = ['themes_api_args', get_debug_type($args), $action, (array) $args];
    return $args;
}, 10, 2);
add_filter('themes_api', static function ($result, $action, $args) use (&$heard) {
    $heard[] = ['themes_api', $result, $action, get_debug_type($args), $args->slug ?? null];
    if (($args->slug ?? '') === 'zz-sold-theme') {
        return (object) ['name' => 'ZZ Sold Theme', 'slug' => 'zz-sold-theme', 'version' => '2.0.0', 'download_link' => 'https://example.test/zz-theme.zip', 'sections' => ['description' => 'Mine.']];
    }
    if (($args->slug ?? '') === 'zz-broken-theme') {
        return new WP_Error('zz_down', 'The vendor is down.');
    }
    return $result;
}, 10, 3);
add_filter('themes_api_result', static function ($res, $action, $args) use (&$heard) {
    $heard[] = ['themes_api_result', get_debug_type($res), $action, $args->slug ?? null];
    return $res;
}, 10, 3);
$shape = static fn ($r) => $r instanceof WP_Error ? ['error', $r->get_error_code(), $r->get_error_message()] : (is_object($r) ? ['object', get_debug_type($r), array_intersect_key((array) $r, array_flip(['name', 'slug', 'version', 'download_link', 'sections', 'requires', 'requires_php']))] : $r);
$ask = static function (string $label, string $action, $args, ?callable $shapeIt = null) use ($say, $shape, &$heard): void {
    $heard = [];
    $answer = themes_api($action, $args);
    $say($label, ['answer' => ($shapeIt ?? $shape)($answer), 'heard' => $heard]);
};
$ask('sold by its maker', 'theme_information', ['slug' => 'zz-sold-theme']);
$ask('sold by its maker, args as an object', 'theme_information', (object) ['slug' => 'zz-sold-theme', 'fields' => ['sections' => false]]);
$ask('its maker is down', 'theme_information', ['slug' => 'zz-broken-theme']);
$ask('a directory theme', 'theme_information', ['slug' => 'twentytwentyone', 'fields' => ['sections' => false]], static function ($r) {
    if (!is_object($r)) {
        return $r instanceof WP_Error ? ['error', $r->get_error_code()] : $r;
    }
    return ['object', get_debug_type($r), 'name' => $r->name ?? null, 'slug' => $r->slug ?? null, 'download link host' => parse_url((string) ($r->download_link ?? ''), PHP_URL_HOST) !== null, 'has version' => is_string($r->version ?? null), 'sections' => isset($r->sections)];
});
$ask('a theme the directory does not know', 'theme_information', ['slug' => 'zz-no-such-theme-anywhere']);
$ask('a search', 'query_themes', ['search' => 'twentytwentyone', 'per_page' => 2], static function ($r) {
    if (!is_object($r)) {
        return $r instanceof WP_Error ? ['error', $r->get_error_code()] : $r;
    }
    $first = (array) (($r->themes ?? [])[0] ?? []);
    return ['object', get_debug_type($r), 'info keys' => array_keys((array) ($r->info ?? [])), 'themes is a list' => is_array($r->themes ?? null) && array_is_list($r->themes), 'a theme is' => get_debug_type(($r->themes ?? [])[0] ?? null), 'first slug' => $first['slug'] ?? null];
});
$ask('an action nobody answers', 'zz_no_such_action', ['slug' => 'twentytwentyone']);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
