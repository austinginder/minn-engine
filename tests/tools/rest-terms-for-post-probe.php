<?php
/**
 * A post's terms over REST (probe rest-terms-for-post; security sweep
 * 2026-10-13): GET /wp/v2/tags and /wp/v2/categories with ?post= answer
 * only for a post of the taxonomy's types that is publicly viewable (a
 * password or not) or that the caller may read, as the reference answers;
 * signed out, as a subscriber and as an administrator, by the post's state.
 * Same protocol as api-probe.php; everything it makes is removed at the end.
 */

$made = [];
$sub = wp_insert_user(['user_login' => 'zz_secterm_sub', 'user_pass' => wp_generate_password(), 'user_email' => 'zz_secterm_sub@example.com', 'role' => 'subscriber']);
register_shutdown_function(static function () use (&$made, $sub): void {
    foreach ($made as $id) { if (is_int($id) && $id > 1) { wp_delete_post($id, true); } }
    if (!function_exists('wp_delete_user')) { require_once ABSPATH . 'wp-admin/includes/user.php'; }
    if (is_int($sub) && $sub > 1) { wp_delete_user($sub); }
});
$posts = [
    'published' => ['post_status' => 'publish'],
    'draft' => ['post_status' => 'draft'],
    'private' => ['post_status' => 'private'],
    'protected' => ['post_status' => 'publish', 'post_password' => 'pw'],
    'page' => ['post_status' => 'publish', 'post_type' => 'page'],
    'pending' => ['post_status' => 'pending'],
];
$ids = [];
foreach ($posts as $label => $args) {
    $id = wp_insert_post($args + ['post_title' => "zz secterm {$label}", 'post_author' => 1]);
    if (!is_int($id) || $id <= 1) { echo json_encode(['setup failed']); exit; }
    $made[] = $id;
    $ids[$label] = $id;
}
$out = [];
foreach (['nobody' => 0, 'subscriber' => $sub, 'admin' => 1] as $who => $user) {
    wp_set_current_user($user);
    foreach ($ids as $label => $id) {
        foreach (['tags', 'categories'] as $base) {
            $request = new WP_REST_Request('GET', "/wp/v2/{$base}");
            $request->set_query_params(['post' => $id]);
            $response = rest_do_request($request);
            $data = $response->get_data();
            $out["{$who} {$base} {$label}"] = $response->get_status() . ' ' . (is_array($data) && isset($data['code']) ? $data['code'] . ': ' . $data['message'] : 'ok');
        }
    }
}
echo json_encode(array_map(static fn ($label, $value) => [$label, $value], array_keys($out), array_values($out)), JSON_UNESCAPED_SLASHES);
