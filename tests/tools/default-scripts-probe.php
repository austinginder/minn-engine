<?php
/**
 * The scripts and styles registries as plugins edit them in place (probe
 * default-scripts), the way wp_default_scripts callbacks do: jQuery's
 * migrate dependency dropped, a source swapped, an entry unset, one added
 * as a _WP_Dependency of its own; then what printing them amounts to (the
 * tags' ids and sources, in order); and whether the registries told
 * wp_default_scripts and wp_default_styles. Read only. Same protocol as
 * api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$scripts = wp_scripts();
wp_styles();
$say('the defaults actions were told', [did_action('wp_default_scripts') > 0, did_action('wp_default_styles') > 0]);
$say('jquery as registered', [$scripts->registered['jquery']->deps ?? null, isset($scripts->registered['jquery-migrate'])]);
$scripts->registered['jquery']->deps = array_values(array_diff($scripts->registered['jquery']->deps, ['jquery-migrate']));
$scripts->registered['jquery-core']->src = 'https://zz.example/jquery.js';
$scripts->registered['zz-direct'] = new _WP_Dependency('zz-direct', 'https://zz.example/direct.js', ['jquery'], '1.2', null);
unset($scripts->registered['zz-gone']);
wp_register_script('zz-later', 'https://zz.example/later.js', [], '3');
$say('jquery after the edits and a later registration', [$scripts->registered['jquery']->deps, $scripts->registered['jquery-core']->src, isset($scripts->registered['zz-direct'])]);
wp_enqueue_script('zz-direct');
ob_start();
wp_print_scripts();
$printed = (string) ob_get_clean();
preg_match_all('/<script[^>]*\bsrc="([^"]+)"[^>]*\bid="([^"]+)"|<script[^>]*\bid="([^"]+)"[^>]*\bsrc="([^"]+)"/', $printed, $m, PREG_SET_ORDER);
$say('printed', array_map(static fn ($t) => [($t[2] ?? '') !== '' ? $t[2] : $t[3], (string) preg_replace('/\?ver=.*$/', '', ($t[1] ?? '') !== '' ? $t[1] : $t[4])], $m));
$styles = wp_styles();
$styles->registered['zz-style'] = new _WP_Dependency('zz-style', 'https://zz.example/zz.css', [], '1', 'print');
wp_enqueue_style('zz-style');
ob_start();
wp_print_styles(['zz-style']);
$say('a style added in place prints', trim((string) preg_replace('/\?ver=[^\']*/', '', (string) ob_get_clean())));
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
