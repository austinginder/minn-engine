<?php
/**
 * Plugin Name: Minn test request
 * Description: Fixture for the request-vars suite, loaded by the engine and the reference alike. For a request carrying X-Minn-Request naming a run the suite opened (wp-content/minn-request/<run>.open exists), it notes at template_redirect (first) what the request parse amounted to, as plugins read it: $wp->query_vars, $wp->request and $wp->query_string, the main query's own query array, and a few get_query_var answers (appended to <run>.log). Without such a run the header does nothing.
 * License: MIT
 */

$minnRequestRun = (string) ($_SERVER['HTTP_X_MINN_REQUEST'] ?? '');
$minnRequestDir = (defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : dirname(__DIR__)) . '/minn-request';
if (preg_match('/^[a-z0-9-]{1,64}$/', $minnRequestRun) !== 1 || !is_file("{$minnRequestDir}/{$minnRequestRun}.open")) {
    return;
}
add_action('template_redirect', static function () use ($minnRequestDir, $minnRequestRun): void {
    global $wp, $wp_query;
    $vars = array_map(static fn ($v) => is_scalar($v) ? (string) $v : $v, (array) $wp->query_vars);
    ksort($vars);
    $query = array_map(static fn ($v) => is_scalar($v) ? (string) $v : $v, (array) $wp_query->query);
    ksort($query);
    file_put_contents("{$minnRequestDir}/{$minnRequestRun}.log", json_encode([
        'query_vars' => $vars,
        'request' => $wp->request,
        'query_string' => $wp->query_string,
        'wp_query->query' => $query,
        'get_query_var' => ['p' => get_query_var('p'), 'page_id' => get_query_var('page_id'), 'name' => get_query_var('name'), 'pagename' => get_query_var('pagename'), 'paged' => get_query_var('paged'), 'page' => get_query_var('page')],
    ], JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND);
}, 0);
