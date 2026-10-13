<?php
/**
 * The built-in types' capabilities by role (probe builtin-type-caps;
 * security sweep 2026-10-13): patterns and menu items answer with the
 * capability names they register (a pattern is read with edit_posts, a
 * private one with read_private_blocks), not the posts family, as on the
 * reference; meta capabilities and REST reads, subscriber to editor. Same
 * protocol as api-probe.php; everything it makes is removed at the end.
 */

$made = ['posts' => [], 'users' => []];
register_shutdown_function(static function () use (&$made): void {
    foreach ($made['posts'] as $id) { if (is_int($id) && $id > 1) { wp_delete_post($id, true); } }
    if (!function_exists('wp_delete_user')) { require_once ABSPATH . 'wp-admin/includes/user.php'; }
    foreach ($made['users'] as $id) { if (is_int($id) && $id > 1) { wp_delete_user($id); } }
});
$users = [];
foreach (['subscriber', 'contributor', 'author', 'editor'] as $role) {
    $id = wp_insert_user(['user_login' => "zz_sectc_{$role}", 'user_pass' => wp_generate_password(), 'user_email' => "zz_sectc_{$role}@example.com", 'role' => $role]);
    $made['users'][] = $id;
    $users[$role] = $id;
}
$published = wp_insert_post(['post_type' => 'wp_block', 'post_title' => 'zz sectc pattern', 'post_status' => 'publish', 'post_author' => 1, 'post_content' => 'x']);
$private = wp_insert_post(['post_type' => 'wp_block', 'post_title' => 'zz sectc private pattern', 'post_status' => 'private', 'post_author' => 1, 'post_content' => 'y']);
$menuItem = wp_insert_post(['post_type' => 'nav_menu_item', 'post_title' => 'zz sectc item', 'post_status' => 'publish', 'post_author' => 1]);
array_push($made['posts'], $published, $private, $menuItem);
$out = [];
foreach ($users as $role => $id) {
    wp_set_current_user($id);
    foreach (['read_post published pattern' => ['read_post', $published], 'read_post private pattern' => ['read_post', $private], 'edit_post private pattern' => ['edit_post', $private], 'edit_post menu item' => ['edit_post', $menuItem], 'delete_post published pattern' => ['delete_post', $published]] as $label => [$cap, $post]) {
        $out["{$role} {$label}"] = current_user_can($cap, $post);
    }
    // A pattern's own names asked directly: the posts family's answer, as the REST create and publish checks ask them.
    foreach (['edit_blocks', 'edit_others_blocks', 'publish_blocks', 'read_private_blocks', 'delete_private_blocks', 'edit_private_blocks', 'create_blocks', 'read_blocks'] as $cap) {
        $out["{$role} {$cap}"] = current_user_can($cap);
    }
    foreach (["/wp/v2/blocks/{$published}", "/wp/v2/blocks/{$private}", "/wp/v2/menu-items/{$menuItem}/autosaves"] as $route) {
        $r = rest_do_request(new WP_REST_Request('GET', $route));
        $d = $r->get_data();
        $out["{$role} GET " . preg_replace('/\d+/', 'N', $route)] = $r->get_status() . (is_array($d) && isset($d['code']) ? ' ' . $d['code'] : '');
    }
}
echo json_encode(array_map(static fn ($label, $value) => [$label, $value], array_keys($out), array_values($out)), JSON_UNESCAPED_SLASHES);
