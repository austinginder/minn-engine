<?php
/**
 * Load helpers (probe load-helpers): JSON media types recognised, the timer
 * since the request started, the internal encoding taken from the site's
 * charset, the language folder constants, the active themes' folders, and
 * the shutdown action fired. Same protocol as api-probe.php; it writes
 * nothing.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};

$say('JSON media types', array_map(static fn ($type) => [$type, wp_is_json_media_type($type)], [
    'application/json', 'application/ld+json', 'application/vnd.api+json', 'text/json', 'application/json; charset=utf-8', 'APPLICATION/JSON',
    'application/javascript', 'json', '', 'application/x-json', 'application/jsonp', 'application/problem+json; x=y', ' application/json',
    'application/+json', 'application/a+json+b', 'application/json, text/html', 'text/html, application/json', 'application/json;',
    'application/json ; charset=utf-8', 'Application/Vnd.Api+JSON', 'application/json5', 'application/xjson', 'xapplication/json',
    "application/json\n", 'application/ json', 'application/json+xml', 'image/svg+xml', 'application//json',
]));

$timer = timer_float();
$say('the timer', [is_float($timer), $timer > 0, $timer < 600, is_float($GLOBALS['timestart'] ?? null)]);

$say('the internal encoding', (static function () {
    $was = mb_internal_encoding();
    $out = [wp_set_internal_encoding(), mb_internal_encoding()];
    $latin = static fn () => 'ISO-8859-1';
    add_filter('pre_option_blog_charset', $latin);
    wp_set_internal_encoding();
    $out[] = mb_internal_encoding();
    remove_filter('pre_option_blog_charset', $latin);
    mb_internal_encoding($was);
    return $out;
})());

$say('the language folder', [defined('WP_LANG_DIR'), defined('LANGDIR'), wp_set_lang_dir(), str_replace(ABSPATH, '{abs}', WP_LANG_DIR), LANGDIR]);
$say('the active themes', array_map(static fn ($path) => str_replace(get_theme_root(), '{themes}', $path), wp_get_active_and_valid_themes()));

$say('the shutdown action', (static function () {
    $heard = [];
    $listen = static function () use (&$heard): void {
        $heard[] = [current_filter(), func_get_args()];
    };
    add_action('shutdown', $listen, -999);
    $out = [shutdown_action_hook(), $heard];
    remove_action('shutdown', $listen, -999);
    return $out;
})());

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
