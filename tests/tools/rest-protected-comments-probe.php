<?php
/**
 * Comments on a password-protected post over REST (probe
 * rest-protected-comments; security sweep 2026-10-13): listing and creating
 * need the post's password, or the right to edit the post, as on the
 * reference; by role, without a password, with a wrong one and the right
 * one. Same protocol as api-probe.php; everything it makes is removed.
 */

$made = ['posts' => [], 'users' => [], 'comments' => []];
register_shutdown_function(static function () use (&$made): void {
    foreach ($made['comments'] as $id) { if (is_int($id) && $id > 1) { wp_delete_comment($id, true); } }
    foreach ($made['posts'] as $id) { if (is_int($id) && $id > 1) { wp_delete_post($id, true); } }
    if (!function_exists('wp_delete_user')) { require_once ABSPATH . 'wp-admin/includes/user.php'; }
    foreach ($made['users'] as $id) { if (is_int($id) && $id > 1) { wp_delete_user($id); } }
});
add_filter('comment_flood_filter', '__return_false');
add_filter('duplicate_comment_id', '__return_false');
$users = ['nobody' => 0];
foreach (['subscriber', 'author', 'editor'] as $role) {
    $id = wp_insert_user(['user_login' => "zz_seccm_{$role}", 'user_pass' => wp_generate_password(), 'user_email' => "zz_seccm_{$role}@example.com", 'role' => $role]);
    if (!is_int($id) || $id <= 1) { echo json_encode(['setup failed']); exit; }
    $made['users'][] = $id;
    $users[$role] = $id;
}
$post = wp_insert_post(['post_title' => 'zz seccm protected', 'post_status' => 'publish', 'post_password' => 'pw', 'comment_status' => 'open', 'post_author' => 1]);
$made['posts'][] = $post;
$made['comments'][] = wp_insert_comment(['comment_post_ID' => $post, 'comment_content' => 'zz seccm seed', 'comment_approved' => 1]);
$out = [];
foreach ($users as $who => $id) {
    wp_set_current_user($id);
    foreach (['no password' => null, 'wrong' => 'nope', 'right' => 'pw'] as $label => $password) {
        $request = new WP_REST_Request('GET', '/wp/v2/comments');
        $request->set_query_params(['post' => $post] + ($password === null ? [] : ['password' => $password]));
        $response = rest_do_request($request);
        $data = $response->get_data();
        $out["{$who} list, {$label}"] = $response->get_status() . ' ' . (is_array($data) && isset($data['code']) ? $data['code'] : count((array) $data) . ' comments');
        $request = new WP_REST_Request('POST', '/wp/v2/comments');
        $request->set_body_params(['post' => $post, 'content' => "zz seccm {$who} {$label} " . wp_generate_password(6, false)] + ($password === null ? [] : ['password' => $password]));
        $response = rest_do_request($request);
        $data = $response->get_data();
        if (isset($data['id']) && is_int($data['id'])) { $made['comments'][] = $data['id']; }
        $out["{$who} create, {$label}"] = $response->get_status() . ' ' . (is_array($data) && isset($data['code']) ? $data['code'] : 'created');
    }
}
echo json_encode(array_map(static fn ($label, $value) => [$label, $value], array_keys($out), array_values($out)), JSON_UNESCAPED_SLASHES);
