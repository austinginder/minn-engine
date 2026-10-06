<?php
/**
 * What a REST comment save hands plugins and what it answers: a new comment
 * with markup, the same comment again, empty content, a plugin changing it
 * through rest_preprocess_comment and refusing one through
 * rest_pre_insert_comment, and an edit. Dispatched in process as an
 * administrator; the filters and actions each step runs are listed in order
 * with their arguments. Same protocol as api-probe.php; the post and its
 * comments are its own and go at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
wp_set_current_user(1);
$postId = (int) wp_insert_post(['post_title' => 'zz rest comment post', 'post_content' => 'Body', 'post_status' => 'publish', 'comment_status' => 'open']);
$watch = ['rest_preprocess_comment', 'allow_empty_comment', 'wp_get_comment_fields_max_lengths', 'duplicate_comment_id', 'comment_duplicate_trigger', 'check_comment_flood', 'wp_is_comment_flood', 'pre_comment_approved', 'rest_pre_insert_comment', 'pre_user_id', 'pre_comment_user_agent', 'pre_comment_author_name', 'pre_comment_content', 'pre_comment_user_ip', 'pre_comment_author_url', 'pre_comment_author_email', 'wp_insert_comment', 'comment_post', 'rest_insert_comment', 'rest_after_insert_comment', 'comment_save_pre', 'wp_update_comment_data', 'edit_comment', 'wp_update_comment_count', 'pre_wp_update_comment_count_now', 'preprocess_comment', 'wp_allow_comment', 'notify_moderator', 'notify_post_author'];
$made = [];
$steps = [];
$recorder = static function (string $hook) use (&$steps, $watch): void {
    if (!in_array($hook, $watch, true)) {
        return;
    }
    $args = array_map(static function ($a) {
        if (is_object($a)) {
            return 'object:' . get_class($a);
        }
        if (is_array($a)) {
            $keys = array_keys($a);
            sort($keys);
            return 'array[' . implode(',', $keys) . ']';
        }
        return $a;
    }, array_slice(func_get_args(), 1));
    $steps[] = $hook . ' ' . json_encode($args, JSON_UNESCAPED_SLASHES);
};
$mask = static function ($value) use (&$made, $postId, &$mask) {
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    if (!is_string($value) && !is_int($value)) {
        return $value;
    }
    $value = is_int($value) ? (string) $value : $value;
    $value = (string) preg_replace('/\b' . $postId . '\b/', '{post}', $value);
    foreach ($made as $n => $id) {
        $value = (string) preg_replace('/\b' . $id . '\b/', '{comment' . $n . '}', $value);
    }
    return (string) preg_replace('/\b20\d\d-\d\d-\d\d[T ]\d\d:\d\d:\d\d\b/', '{date}', $value);
};
$send = static function (string $label, string $method, string $route, array $body) use (&$steps, $recorder, $say, $mask, &$made): void {
    $steps = [];
    $request = new WP_REST_Request($method, $route);
    $request->set_body_params($body);
    $request->set_header('User-Agent', 'zz-probe-agent');
    add_action('all', $recorder);
    $response = rest_do_request($request);
    remove_action('all', $recorder);
    $data = rest_get_server()->response_to_data($response, false);
    if ($response->get_status() === 201 && isset($data['id'])) {
        $made[] = (int) $data['id'];
    }
    $answer = $response->is_error() ? ['code' => $data['code'] ?? null, 'message' => $data['message'] ?? null, 'data' => $data['data'] ?? null] : array_intersect_key($data, array_flip(['author_name', 'content', 'status', 'parent', 'author_url']));
    $say($label, $mask(['status' => $response->get_status(), 'answer' => $answer, 'steps' => $steps]));
};
$send('a new comment', 'POST', '/wp/v2/comments', ['post' => $postId, 'content' => 'Hello <b>there</b> <script>x()</script> & co']);
$send('the same again', 'POST', '/wp/v2/comments', ['post' => $postId, 'content' => 'Hello <b>there</b> <script>x()</script> & co']);
$send('no content', 'POST', '/wp/v2/comments', ['post' => $postId, 'content' => '']);
$change = static function ($prepared) {
    $prepared['comment_content'] .= ' (changed)';
    return $prepared;
};
add_filter('rest_preprocess_comment', $change);
$send('a plugin changes it', 'POST', '/wp/v2/comments', ['post' => $postId, 'content' => 'Second comment']);
remove_filter('rest_preprocess_comment', $change);
$refuse = static fn () => new WP_Error('zz_refused', 'Refused by a plugin.', ['status' => 418]);
add_filter('rest_pre_insert_comment', $refuse);
$send('a plugin refuses it', 'POST', '/wp/v2/comments', ['post' => $postId, 'content' => 'Third comment']);
remove_filter('rest_pre_insert_comment', $refuse);
if ($made !== []) {
    $send('an edit', 'POST', '/wp/v2/comments/' . $made[0], ['content' => 'Edited <em>words</em>', 'status' => 'hold']);
}
foreach ($made as $id) {
    wp_delete_comment($id, true);
}
wp_delete_post($postId, true);
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
