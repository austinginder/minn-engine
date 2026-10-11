<?php
/**
 * Three helpers left (probe tail-helpers): the core taxonomies registered
 * again, and what they are after; the current block template's markup, run
 * through the content filters; and the navigation menu a theme falls back
 * to: the newest published one, else one made from a classic menu, else a
 * default one. The posts, menu and items the probe makes are removed again.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
if (!did_action('init')) {
    do_action('init');
}
$host = (string) parse_url(home_url(), PHP_URL_HOST);
$unhost = static fn ($value) => json_decode(str_replace($host, '{host}', (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)), true);

// The core taxonomies, registered again in the reference's order.
$core = ['category', 'post_tag', 'nav_menu', 'link_category', 'post_format', 'wp_theme', 'wp_template_part_area', 'wp_pattern_category'];
$describe = static function () use ($core, $unhost) {
    $out = [];
    foreach ($core as $name) {
        $taxonomy = get_taxonomy($name);
        $out[$name] = $taxonomy ? $unhost([
            'label' => $taxonomy->label, 'name' => $taxonomy->labels->name, 'singular' => $taxonomy->labels->singular_name,
            'public' => $taxonomy->public, 'queryable' => $taxonomy->publicly_queryable, 'hierarchical' => $taxonomy->hierarchical,
            'show_ui' => $taxonomy->show_ui, 'rest' => [$taxonomy->show_in_rest, $taxonomy->rest_base, $taxonomy->rest_namespace, $taxonomy->rest_controller_class],
            'query_var' => $taxonomy->query_var, 'rewrite' => $taxonomy->rewrite, 'object_type' => $taxonomy->object_type,
            'caps' => (array) $taxonomy->cap, 'builtin' => $taxonomy->_builtin, 'admin_column' => $taxonomy->show_admin_column,
        ]) : null;
    }
    return $out;
};
$before = $describe();
$heard = [];
$listener = static function ($taxonomy, $object_type) use (&$heard) {
    $heard[] = [$taxonomy, $object_type];
};
add_action('registered_taxonomy', $listener, 10, 2);
$returned = create_initial_taxonomies();
remove_action('registered_taxonomy', $listener, 10);
$after = $describe();
$say('core taxonomies registered again', ['returned' => $returned, 'heard' => $heard, 'unchanged' => $after === $before, 'after' => $after]);

// The block template's markup.
global $_wp_current_template_content;
$template = static function (string $content, int $user = 0) {
    global $_wp_current_template_content;
    $_wp_current_template_content = $content;
    wp_set_current_user($user);
    $out = get_the_block_template_html();
    wp_set_current_user(0);
    return $out;
};
add_shortcode('zz_tail', static fn () => '<b>ZZ shortcode</b>');
add_shortcode('zz_tail_quote', static fn () => 'ZZ "said" -- it :)');
$say('block template html', [
    'none, a visitor' => $template(''),
    'none, signed in' => $template('', 1),
    'filtered' => $template('<!-- wp:paragraph --><p>ZZ "quoted" -- dash ]]> :) [zz_tail] https://example.com/x</p><!-- /wp:paragraph --><!-- wp:html --><img src="https://example.com/zz.jpg" width="10" height="10"><!-- /wp:html -->'),
    'a group' => $template('<!-- wp:group {"layout":{"type":"constrained"}} --><div class="wp-block-group"><!-- wp:paragraph --><p>ZZ in a group</p><!-- /wp:paragraph --></div><!-- /wp:group -->'),
    'plain text' => $template('ZZ plain <em>text</em>'),
    'an attachment\'s image' => (static function () use ($template, $host) {
        $ids = get_posts(['post_type' => 'attachment', 'post_mime_type' => 'image', 'post_status' => 'inherit', 'numberposts' => 1, 'orderby' => 'ID', 'order' => 'ASC', 'fields' => 'ids']);
        $source = $ids ? wp_get_attachment_image_src($ids[0], 'full') : false;
        if (!$source) {
            return 'no image here';
        }
        $html = $template('<!-- wp:html --><img class="wp-image-' . $ids[0] . '" src="' . $source[0] . '" width="' . $source[1] . '" height="' . $source[2] . '"><!-- /wp:html --><!-- wp:html --><iframe src="https://example.com/zz" width="10" height="10"></iframe><!-- /wp:html -->');
        return str_replace([$host, 'wp-image-' . $ids[0]], ['{host}', 'wp-image-{id}'], $html);
    })(),
    'a shortcode\'s own quotes' => $template('<!-- wp:paragraph --><p>[zz_tail_quote]</p><!-- /wp:paragraph -->[zz_tail_quote]'),
]);
$_wp_current_template_content = '';
remove_shortcode('zz_tail');
remove_shortcode('zz_tail_quote');

// The fallback navigation menu.
global $wpdb;
$baseline = (int) $wpdb->get_var("SELECT MAX(ID) FROM {$wpdb->posts}");
$made = ['posts' => [], 'menu' => 0];
register_shutdown_function(static function () use (&$made, $baseline): void {
    global $wpdb;
    foreach ((array) $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'wp_navigation' AND ID > %d", $baseline)) as $id) {
        $made['posts'][] = (int) $id;
    }
    foreach (array_unique($made['posts']) as $id) {
        if (is_int($id) && $id > 1) {
            wp_delete_post($id, true);
        }
    }
    if (is_int($made['menu']) && $made['menu'] > 1) {
        wp_delete_nav_menu($made['menu']);
    }
});
// What a fallback made: navigation posts newer than the probe's start, the probe's own two left out (and removed at once).
$created = static function () use ($baseline, $unhost, &$made) {
    global $wpdb;
    $rows = array_filter((array) $wpdb->get_results($wpdb->prepare("SELECT ID, post_title, post_name, post_status, post_content FROM {$wpdb->posts} WHERE post_type = 'wp_navigation' AND ID > %d ORDER BY ID", $baseline), ARRAY_A), static fn ($row) => !in_array((int) $row['ID'], $made['posts'], true));
    foreach ($rows as $row) {
        if (is_numeric($row['ID']) && (int) $row['ID'] > 1) {
            wp_delete_post((int) $row['ID'], true);
        }
    }
    return array_values(array_map(static fn ($row) => $unhost(['title' => $row['post_title'], 'name' => $row['post_name'], 'status' => $row['post_status'], 'content' => $row['post_content']]), $rows));
};
// No published navigation: the navigation queries answer nothing.
$none = static fn ($posts, $query) => $query->get('post_type') === 'wp_navigation' ? [] : $posts;
wp_set_current_user(1);
$newest = wp_insert_post(['post_type' => 'wp_navigation', 'post_title' => 'ZZ newest nav', 'post_status' => 'publish', 'post_content' => '<!-- wp:page-list /-->', 'post_date' => '2026-09-15 00:00:00', 'post_date_gmt' => '2026-09-15 00:00:00']);
$draft = wp_insert_post(['post_type' => 'wp_navigation', 'post_title' => 'ZZ draft nav', 'post_status' => 'draft', 'post_content' => '', 'post_date' => '2026-09-20 00:00:00', 'post_date_gmt' => '2026-09-20 00:00:00']);
$menu = wp_create_nav_menu('ZZ tail menu');
wp_set_current_user(0);
if (!is_int($newest) || $newest <= 1 || !is_int($draft) || $draft <= 1 || !is_int($menu) || $menu <= 1) {
    fwrite(STDERR, "no fixture\n");
    exit(1);
}
$made['posts'] = [$newest, $draft];
$made['menu'] = $menu;
$parent = wp_update_nav_menu_item($menu, 0, ['menu-item-title' => 'ZZ Parent', 'menu-item-url' => 'https://example.com/zz-parent', 'menu-item-status' => 'publish', 'menu-item-type' => 'custom']);
wp_update_nav_menu_item($menu, 0, ['menu-item-title' => 'ZZ Child', 'menu-item-url' => 'https://example.com/zz-child', 'menu-item-status' => 'publish', 'menu-item-type' => 'custom', 'menu-item-parent-id' => $parent]);
$onlyOurs = static fn () => [wp_get_nav_menu_object($menu)];
$noMenus = static fn () => [];
$fallback = static function (?Closure $menus = null, bool $create = true) use ($none, $created) {
    add_filter('posts_pre_query', $none, 10, 2);
    if ($menus !== null) {
        add_filter('wp_get_nav_menus', $menus);
    }
    $refuse = static fn () => false;
    if (!$create) {
        add_filter('wp_navigation_should_create_fallback', $refuse);
    }
    $returned = WP_Navigation_Fallback::get_fallback();
    remove_filter('posts_pre_query', $none, 10);
    if ($menus !== null) {
        remove_filter('wp_get_nav_menus', $menus);
    }
    remove_filter('wp_navigation_should_create_fallback', $refuse);
    return ['returned' => $returned, 'created' => $created()];
};
$navigation = WP_Navigation_Fallback::get_fallback();
$say('fallback navigation', [
    'the newest published' => $navigation instanceof WP_Post ? [$navigation->ID === $newest, $navigation->post_title, $navigation->post_status] : $navigation,
    'none, creating refused' => $fallback($onlyOurs, false),
    'none, a classic menu' => $fallback($onlyOurs),
    'none, no classic menu' => $fallback($noMenus),
]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
