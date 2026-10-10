<?php
/**
 * The rest of admin-bar.php (probe admin-bar-remaining): every menu builder
 * run on a fresh toolbar for the front views a signed-in administrator sees
 * (a post, a page, a category, an author, search, the home page), the nodes
 * each one adds read back in order. A subscriber's toolbar, and a signed-out
 * one, show what the builders leave out. The probe's posts, category and
 * user are removed at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = ['posts' => [], 'terms' => [], 'users' => []];
$pings = wp_next_scheduled('do_pings');
register_shutdown_function(static function () use (&$made, $pings): void {
    foreach ($made['posts'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_post($id, true);
        }
    }
    foreach ($made['terms'] as [$id, $taxonomy]) {
        if (is_int($id) && $id > 0) {
            wp_delete_term($id, $taxonomy);
        }
    }
    foreach ($made['users'] as $id) {
        if (is_int($id) && $id > 1) {
            wp_delete_user($id);
        }
    }
    if ($pings === false) {
        wp_clear_scheduled_hook('do_pings');
    }
});
$ids = [];
$host = (string) parse_url(home_url(), PHP_URL_HOST);
$mask = static function ($value) use (&$mask, &$ids, $host) {
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    if (is_int($value)) {
        return isset($ids[$value]) ? '{' . $ids[$value] . '}' : $value;
    }
    if (!is_string($value)) {
        return $value;
    }
    $value = (string) preg_replace('#https?://' . preg_quote($host, '#') . '#', '{site}', $value);
    $value = (string) preg_replace('/_wpnonce=[0-9a-f]{10}/', '_wpnonce={nonce}', $value);
    // The moderation count is the site's own; it shows as {held} when it is the count wp_count_comments gives.
    $held = (int) wp_count_comments()->moderated;
    $value = str_replace(["count-{$held}\"", ">{$held}<", ">{$held} Comment"], ['count-{held}"', '>{held}<', '>{held} Comment'], $value);
    foreach ($ids as $id => $label) {
        $value = (string) preg_replace('/(?<![0-9a-z])' . $id . '(?![0-9a-z])/i', '{' . $label . '}', $value);
    }
    return $value;
};
$post = static function (string $title, array $extra = []) use (&$made, &$ids): int {
    $made['posts'][] = $id = (int) wp_insert_post($extra + ['post_title' => $title, 'post_status' => 'publish', 'post_content' => 'x', 'post_author' => 1]);
    $ids[$id] = str_replace(' ', '-', $title);
    return $id;
};
$query = static function (array $args) {
    global $wp_query, $wp_the_query, $post;
    $wp_the_query = $wp_query = new WP_Query();
    $wp_query->query($args);
    $post = $wp_query->post ?? null;
};
$builders = [
    'my account item' => static fn ($bar) => wp_admin_bar_my_account_item($bar), 'my account menu' => static fn ($bar) => wp_admin_bar_my_account_menu($bar), 'sidebar toggle' => static fn ($bar) => wp_admin_bar_sidebar_toggle($bar),
    'wp menu' => static fn ($bar) => wp_admin_bar_wp_menu($bar), 'site menu' => static fn ($bar) => wp_admin_bar_site_menu($bar), 'edit site menu' => static fn ($bar) => wp_admin_bar_edit_site_menu($bar),
    'my sites menu' => static fn ($bar) => wp_admin_bar_my_sites_menu($bar), 'updates menu' => static fn ($bar) => wp_admin_bar_updates_menu($bar), 'comments menu' => static fn ($bar) => wp_admin_bar_comments_menu($bar), 'appearance menu' => static fn ($bar) => wp_admin_bar_appearance_menu($bar),
    'new content menu' => static fn ($bar) => wp_admin_bar_new_content_menu($bar), 'edit menu' => static fn ($bar) => wp_admin_bar_edit_menu($bar), 'secondary groups' => static fn ($bar) => wp_admin_bar_add_secondary_groups($bar),
    'recovery mode menu' => static fn ($bar) => wp_admin_bar_recovery_mode_menu($bar), 'search menu' => static fn ($bar) => wp_admin_bar_search_menu($bar), 'command palette menu' => static fn ($bar) => wp_admin_bar_command_palette_menu($bar),
];
// Each builder alone on a fresh toolbar: the nodes it adds, in order.
$toolbar = static function (array $only = []) use ($builders, $mask): array {
    $out = [];
    foreach ($builders as $label => $build) {
        if ($only !== [] && !in_array($label, $only, true)) {
            continue;
        }
        $bar = new WP_Admin_Bar();
        $build($bar);
        $nodes = [];
        foreach ((array) $bar->get_nodes() as $node) {
            $node = (array) $node;
            ksort($node);
            $nodes[] = $node;
        }
        $out[$label] = $mask($nodes);
    }
    return $out;
};

// WP-CLI never loads the toolbar class on the reference; the engine autoloads it.
if (!class_exists('WP_Admin_Bar')) {
    require_once ABSPATH . WPINC . '/class-wp-admin-bar.php';
}
$made['terms'][] = [$category = (int) wp_insert_term('zz ab cat', 'category')['term_id'], 'category'];
$ids[$category] = 'zz-ab-cat';
$single = $post('zz ab single', ['post_category' => [$category]]);
$page = $post('zz ab page', ['post_type' => 'page']);
$draft = $post('zz ab draft', ['post_status' => 'draft']);
$made['users'][] = $reader = (int) wp_insert_user(['user_login' => 'zz_ab_reader', 'user_pass' => wp_generate_password(), 'user_email' => 'zz-ab-reader@example.com', 'role' => 'subscriber', 'display_name' => 'Zz Reader']);
$ids[$reader] = 'reader';

wp_set_current_user(1);
$views = [];
foreach (['a post' => ['p' => $single], 'a page' => ['page_id' => $page], 'a category' => ['cat' => $category], 'an author' => ['author' => 1], 'search' => ['s' => 'zz ab'], 'the home page' => []] as $label => $args) {
    $query($args);
    $views[$label] = $toolbar($label === 'a post' ? [] : ['edit menu', 'edit site menu', 'search menu', 'new content menu']);
}
$say('an administrator', $views);

wp_set_current_user($reader);
$query(['p' => $single]);
$say('a subscriber', $toolbar(['my account item', 'my account menu', 'site menu', 'edit menu', 'new content menu', 'comments menu', 'appearance menu', 'edit site menu', 'search menu']));

wp_set_current_user(0);
$say('signed out', $toolbar(['my account item', 'site menu', 'edit menu', 'new content menu', 'search menu', 'wp menu']));

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
