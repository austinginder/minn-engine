<?php
/**
 * Plugin Name: Minn test trace
 * Description: Fixture for the hook-trace suite, loaded by the engine and the reference alike. A request that carries X-Minn-Trace naming a run the suite opened (wp-content/minn-trace/<run>.open exists) has every action it fires appended, in order, with a summary of its arguments, to wp-content/minn-trace/<run>.ndjson; with X-Minn-Trace-Filters too, the distinct filters it applies go to <run>.filters.json.
 * License: MIT
 */

$minnTraceRun = (string) ($_SERVER['HTTP_X_MINN_TRACE'] ?? '');
$minnTraceDir = (defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : dirname(__DIR__)) . '/minn-trace';
if (preg_match('/^[a-z0-9-]{1,64}$/', $minnTraceRun) !== 1 || !is_file("{$minnTraceDir}/{$minnTraceRun}.open")) {
    return;
}

/** What an argument is, in words that mean the same on both stacks (ids and times differ, kinds and states do not). */
function minn_test_trace_describe($value): string
{
    if ($value instanceof WP_Post) {
        return "post:{$value->post_type}:{$value->post_status}";
    }
    if ($value instanceof WP_User) {
        return 'user:' . implode(',', (array) $value->roles);
    }
    if ($value instanceof WP_Term) {
        return "term:{$value->taxonomy}";
    }
    if ($value instanceof WP_Comment) {
        return "comment:{$value->comment_approved}";
    }
    if ($value instanceof WP_REST_Request) {
        return 'request:' . $value->get_method();
    }
    if ($value instanceof WP_REST_Response) {
        return 'response:' . $value->get_status();
    }
    if ($value instanceof WP_Error) {
        return 'error:' . $value->get_error_code();
    }
    if (is_object($value)) {
        return 'object:' . get_class($value);
    }
    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }
    if (is_int($value) || (is_string($value) && ctype_digit($value) && $value !== '')) {
        return 'int';
    }
    if (is_string($value)) {
        // The theme caches' option names carry a hash; named by what they are.
        if (preg_match('/^(_site_transient_(timeout_)?)?wp_theme_files_patterns-[0-9a-f]{32}$/', $value) === 1) {
            return "'theme-cache'";
        }
        // Statuses, keys and slugs are the same on both stacks; free text is not compared.
        return preg_match('/^[a-z0-9_-]{1,40}$/', $value) === 1 ? "'{$value}'" : 'string';
    }
    if (is_array($value)) {
        // An upgrader's hook_extra: what it did, to which plugins or themes.
        if (is_string($value['action'] ?? null) && is_string($value['type'] ?? null)) {
            $items = $value['plugins'] ?? $value['themes'] ?? $value['plugin'] ?? $value['theme'] ?? [];
            return "extra:{$value['action']}:{$value['type']}:" . implode(',', array_map('strval', (array) $items));
        }
        return 'array';
    }
    return $value === null ? 'null' : gettype($value);
}

// With X-Minn-Trace-Filters as well, the distinct filters applied from the action it names (1: the REST server's start) to shutdown, written once at the end.
$minnTraceFrom = (string) ($_SERVER['HTTP_X_MINN_TRACE_FILTERS'] ?? '');
$minnTraceFilters = $minnTraceFrom !== '' ? ['open' => false, 'seen' => [], 'from' => preg_match('/^[a-z_]{2,40}$/', $minnTraceFrom) === 1 ? $minnTraceFrom : 'rest_api_init'] : null;
if ($minnTraceFilters !== null) {
    register_shutdown_function(static function () use (&$minnTraceFilters, $minnTraceDir, $minnTraceRun): void {
        file_put_contents("{$minnTraceDir}/{$minnTraceRun}.filters.json", json_encode(array_keys($minnTraceFilters['seen'])));
    });
}

add_action('all', static function (string $hook) use ($minnTraceDir, $minnTraceRun, &$minnTraceFilters): void {
    // An action is counted before 'all' runs, a filter is not.
    if (did_action($hook) === 0) {
        if ($minnTraceFilters !== null && $minnTraceFilters['open']) {
            $minnTraceFilters['seen'][$hook] = true;
        }
        return;
    }
    if ($minnTraceFilters !== null && ($hook === $minnTraceFilters['from'] || $hook === 'shutdown')) {
        $minnTraceFilters['open'] = $hook === $minnTraceFilters['from'];
    }
    $args = array_map('minn_test_trace_describe', array_slice(func_get_args(), 1));
    file_put_contents("{$minnTraceDir}/{$minnTraceRun}.ndjson", json_encode([$hook, $args], JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND);
    if ($hook === 'rest_api_init') {
        // What a plugin can tell about the request as the REST server starts.
        // (Ids in the route differ between the stacks' own objects.)
        $route = $GLOBALS['wp']->query_vars['rest_route'] ?? null;
        $state = [defined('REST_REQUEST') && REST_REQUEST, is_string($route) ? preg_replace('/\d+/', '{id}', $route) : $route, function_exists('wp_is_serving_rest_request') && wp_is_serving_rest_request()];
        file_put_contents("{$minnTraceDir}/{$minnTraceRun}.ndjson", json_encode(['minn-test:rest-request', $state], JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND);
    }
}, PHP_INT_MIN);
