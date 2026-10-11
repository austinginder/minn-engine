<?php
/**
 * The REST API's own filters (probe rest-default-filters): every callback
 * rest_api_default_filters() adds, by hook, priority and accepted
 * arguments, under the site's WP_DEBUG, and what serving a REST request
 * adds to them. The headers the deprecation
 * handlers send are compared over HTTP by the runtime suite
 * (contracts/api/http-proofs.json). Nothing is written.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$hooks = static function (): array {
    global $wp_filter;
    $out = [];
    foreach ((array) $wp_filter as $hook => $holder) {
        foreach ((array) ($holder->callbacks ?? []) as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                if (is_string($callback['function'])) {
                    $out["{$hook} {$priority} {$callback['function']} {$callback['accepted_args']}"] = true;
                }
            }
        }
    }
    return $out;
};
$before = $hooks();
$returned = rest_api_default_filters();
$added = array_keys(array_diff_key($hooks(), $before));
sort($added);
$say('rest default filters', ['debug' => defined('WP_DEBUG') && WP_DEBUG, 'returned' => $returned, 'added' => $added]);

// Again while a REST request is being served: what only a REST request adds.
$before = $hooks();
if (!defined('REST_REQUEST')) {
    define('REST_REQUEST', true);
}
rest_api_default_filters();
$added = array_keys(array_diff_key($hooks(), $before));
sort($added);
$say('rest default filters, serving a REST request', ['serving' => wp_is_serving_rest_request(), 'added' => $added]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
