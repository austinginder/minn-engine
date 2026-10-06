<?php
/**
 * Plugin Name: Minn test trace
 * Description: Fixture for the hook-trace suite, loaded by the engine and the reference alike. A request that carries X-Minn-Trace naming a run the suite opened (wp-content/minn-trace/<run>.open exists) has every action it fires appended, in order, with a summary of its arguments, to wp-content/minn-trace/<run>.ndjson.
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
        // Statuses, keys and slugs are the same on both stacks; free text is not compared.
        return preg_match('/^[a-z0-9_-]{1,40}$/', $value) === 1 ? "'{$value}'" : 'string';
    }
    if (is_array($value)) {
        return 'array';
    }
    return $value === null ? 'null' : gettype($value);
}

add_action('all', static function (string $hook) use ($minnTraceDir, $minnTraceRun): void {
    // Only actions: an action is counted before 'all' runs, a filter is not.
    if (did_action($hook) === 0) {
        return;
    }
    $args = array_map('minn_test_trace_describe', array_slice(func_get_args(), 1));
    file_put_contents("{$minnTraceDir}/{$minnTraceRun}.ndjson", json_encode([$hook, $args], JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND);
}, PHP_INT_MIN);
