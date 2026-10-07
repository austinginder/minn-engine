<?php
/**
 * Plugin Name: Minn test endpoint
 * Description: Fixture for the rewrite-endpoints suite, loaded by the engine and the reference alike. While a run the suite opened is open (wp-content/minn-endpoint/<run>.open exists), it adds two rewrite endpoints as shop and app plugins do (zzend on pages, posts and the root; zzpage on pages only), and for a request carrying X-Minn-Endpoint naming the run it notes at template_redirect what the request amounted to: the endpoint vars the parse set, what get_query_var answers, the conditionals and the queried object (appended to <run>.log). The endpoints stand for every request of the run, so the reference's stored rules can be rebuilt with them.
 * License: MIT
 */

$minnEndpointDir = (defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : dirname(__DIR__)) . '/minn-endpoint';
$minnEndpointOpen = glob("{$minnEndpointDir}/*.open") ?: [];
if ($minnEndpointOpen === []) {
    return;
}
add_action('init', static function (): void {
    add_rewrite_endpoint('zzend', EP_PAGES | EP_PERMALINK | EP_ROOT);
    add_rewrite_endpoint('zzpage', EP_PAGES);
});
$minnEndpointRun = (string) ($_SERVER['HTTP_X_MINN_ENDPOINT'] ?? '');
if (preg_match('/^[a-z0-9-]{1,64}$/', $minnEndpointRun) !== 1 || !is_file("{$minnEndpointDir}/{$minnEndpointRun}.open")) {
    return;
}
add_action('template_redirect', static function () use ($minnEndpointDir, $minnEndpointRun): void {
    global $wp;
    $vars = array_intersect_key((array) $wp->query_vars, array_flip(['zzend', 'zzpage']));
    ksort($vars);
    file_put_contents("{$minnEndpointDir}/{$minnEndpointRun}.log", json_encode([
        'vars' => array_map('strval', $vars),
        'get_query_var' => [get_query_var('zzend', '(unset)'), get_query_var('zzpage', '(unset)')],
        'is' => array_keys(array_filter(['page' => is_page(), 'single' => is_single(), 'home' => is_home(), '404' => is_404()])),
        'queried' => get_queried_object_id(),
    ]) . "\n", FILE_APPEND);
}, 1);
