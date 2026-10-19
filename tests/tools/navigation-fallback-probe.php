<?php
/**
 * A navigation that names no menu, on a site whose menus are out of sight
 * (probe navigation-fallback): the site's own navigation menus are kept
 * from the lookup (pre_get_posts), and its classic menus too where a case
 * says so (wp_get_nav_menus). Rendered, the block asks
 * wp_navigation_should_create_fallback; unless that says not, it makes a
 * published navigation menu during the render (from the classic menu,
 * titled and slugged after it; else "Navigation" with a page list) and
 * renders that, and a second render makes no other. Told not to, it makes
 * none. One naming a menu that is not there makes none either; one naming
 * a draft menu is recorded too. Recorded
 * for each render: the markup, the menu posts made (title, slug, status,
 * content) and the hooks a plugin would hear about them. The menus made
 * are deleted at the end. Same protocol as api-probe.php.
 */

global $wpdb;
$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$existing = array_map('intval', $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'wp_navigation'"));
$made = [];
register_shutdown_function(static function () use (&$made): void {
    foreach ($made as $id) {
        if (is_int($id) && $id > 1) {
            wp_delete_post($id, true);
        }
    }
});
$hide = static function ($query) use ($existing): void {
    if ($query->get('post_type') === 'wp_navigation' && $existing !== []) {
        $query->set('post__not_in', $existing);
    }
};
add_action('pre_get_posts', $hide);
$noClassic = static fn () => [];
$never = static fn () => false;

$heard = [];
$listeners = [
    'wp_navigation_should_create_fallback' => static function ($create) use (&$heard) {
        $heard[] = ['wp_navigation_should_create_fallback', $create];
        return $create;
    },
    'block_core_navigation_render_fallback' => static function ($blocks) use (&$heard) {
        $heard[] = ['block_core_navigation_render_fallback', array_map(static fn ($block) => $block['blockName'] ?? null, (array) $blocks)];
        return $blocks;
    },
    'save_post_wp_navigation' => static function ($id, $post, $update) use (&$heard) {
        $heard[] = ['save_post_wp_navigation', $post->post_title, $update];
    },
    'transition_post_status' => static function ($new, $old, $post) use (&$heard) {
        if ($post->post_type === 'wp_navigation') {
            $heard[] = ['transition_post_status', $new, $old];
        }
    },
];
foreach ($listeners as $hook => $listener) {
    add_filter($hook, $listener, 10, 3);
}
$render = static function (string $label, string $markup) use ($say, &$heard, &$made, $existing, $wpdb): void {
    $heard = [];
    $html = do_blocks($markup);
    $placeholders = $existing === [] ? '0' : implode(',', $existing);
    $new = [];
    foreach ((array) $wpdb->get_results("SELECT ID, post_title, post_name, post_status, post_content FROM {$wpdb->posts} WHERE post_type = 'wp_navigation' AND ID NOT IN ({$placeholders}) ORDER BY ID") as $row) {
        $id = (int) $row->ID;
        if (!in_array($id, $made, true) && $id > 1) {
            $made[] = $id;
            $new[] = ['title' => $row->post_title, 'name' => $row->post_name, 'status' => $row->post_status, 'content' => $row->post_content];
        }
    }
    $html = (string) preg_replace(['/\bnavigation-(\d+)\b/', '/\bpage-item-(\d+)\b/', '/\bmenu-item-(\d+)\b/'], ['navigation-N', 'page-item-N', 'menu-item-N'], $html);
    $say($label, ['made' => $new, 'heard' => $heard, 'html' => $html]);
};

$plain = '<!-- wp:navigation /-->';
add_filter('wp_navigation_should_create_fallback', $never, 5);
add_filter('wp_get_nav_menus', $noClassic);
$render('told not to make one, no classic menu', $plain);
remove_filter('wp_get_nav_menus', $noClassic);
$render('told not to make one, a classic menu', $plain);
remove_filter('wp_navigation_should_create_fallback', $never, 5);
add_filter('wp_get_nav_menus', $noClassic);
$render('no classic menu: a page list made', $plain);
$render('rendered again', $plain);
remove_filter('wp_get_nav_menus', $noClassic);
foreach ($made as $id) {
    if (is_int($id) && $id > 1) {
        wp_delete_post($id, true);
    }
}
$made = [];
$render('a classic menu: made from it', $plain);
$render('naming a menu that is not there', '<!-- wp:navigation {"ref":999999999} /-->');
foreach ($made as $id) {
    if (is_int($id) && $id > 1) {
        wp_delete_post($id, true);
    }
}
$made = [];
$draft = wp_insert_post(['post_type' => 'wp_navigation', 'post_status' => 'draft', 'post_title' => 'Zz Draft Menu', 'post_content' => '<!-- wp:navigation-link {"label":"Draft","url":"https://example.com/draft","kind":"custom"} /-->']);
if (!is_int($draft) || $draft <= 1) {
    fwrite(STDERR, "navigation-fallback: could not make the draft menu\n");
    exit(1);
}
$made[] = $draft;
add_filter('wp_get_nav_menus', $noClassic);
$render('naming a draft menu', '<!-- wp:navigation {"ref":' . $draft . '} /-->');
remove_filter('wp_get_nav_menus', $noClassic);
remove_action('pre_get_posts', $hide);
foreach ($listeners as $hook => $listener) {
    remove_filter($hook, $listener, 10);
}
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n";
