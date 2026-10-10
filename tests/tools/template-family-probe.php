<?php
/**
 * Template tags left across small files (probe template-family): category
 * lookups by name and slug, a tag fetched, old category fields added;
 * registered meta keys and their defaults; post formats (the label, the
 * check, the query var); post thumbnails (id, check, URL and tag printed);
 * a classic menu printed and its tree walked; shortcodes in HTML attributes,
 * escaped ones unescaped, one stripped, all removed; block templates enabled
 * and template slugs kept unique in a theme. The probe's posts, templates,
 * terms, menu and attachment are removed at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = ['posts' => [], 'terms' => [], 'menus' => []];
$hadAside = term_exists('post-format-aside', 'post_format');
$pings = wp_next_scheduled('do_pings');
register_shutdown_function(static function () use (&$made, $hadAside, $pings): void {
    foreach ($made['menus'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_nav_menu($id);
        }
    }
    foreach ($made['posts'] as $id) {
        if (is_int($id) && $id > 1) {
            wp_delete_post($id, true);
        }
    }
    foreach ($made['terms'] as [$id, $taxonomy]) {
        if (is_int($id) && $id > 1) {
            wp_delete_term($id, $taxonomy);
        }
    }
    if (!$hadAside && ($aside = term_exists('post-format-aside', 'post_format'))) {
        wp_delete_term((int) $aside['term_id'], 'post_format');
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
    if (is_object($value)) {
        return $mask(get_object_vars($value));
    }
    if (is_int($value)) {
        return isset($ids[$value]) ? '{' . $ids[$value] . '}' : $value;
    }
    if (!is_string($value)) {
        return $value;
    }
    $value = (string) preg_replace('#https?://' . preg_quote($host, '#') . '#', '{site}', $value);
    foreach ($ids as $id => $label) {
        $value = (string) preg_replace('/(?<![0-9a-z])' . $id . '(?![0-9a-z])/i', '{' . $label . '}', $value);
    }
    return $value;
};
$echoed = static function (Closure $call): string {
    ob_start();
    $call();
    return (string) ob_get_clean();
};
$post = static function (string $title, array $extra = []) use (&$made, &$ids): int {
    $id = wp_insert_post($extra + ['post_title' => $title, 'post_status' => 'publish', 'post_content' => 'x', 'post_author' => 1]);
    if (!is_int($id) || $id < 2) {
        fwrite(STDERR, "template-family: {$title} could not be made\n");
        exit(1);
    }
    $made['posts'][] = $id;
    $ids[$id] = str_replace(' ', '-', $title);
    return $id;
};
$term = static function (string $name, string $taxonomy) use (&$made, &$ids): int {
    $result = wp_insert_term($name, $taxonomy);
    if (!is_array($result)) {
        fwrite(STDERR, "template-family: the term {$name} could not be made\n");
        exit(1);
    }
    $id = (int) $result['term_id'];
    $made['terms'][] = [$id, $taxonomy];
    $ids[$id] = str_replace(' ', '-', $name);
    return $id;
};
$query = static function (array $args) {
    global $wp_query, $wp_the_query;
    $wp_the_query = $wp_query = new WP_Query();
    $wp_query->query($args);
    if ($wp_query->have_posts()) {
        $wp_query->the_post();
    }
};
$shape = static fn ($term) => is_object($term) ? ['term_id' => $term->term_id, 'name' => $term->name, 'slug' => $term->slug, 'taxonomy' => $term->taxonomy] : $term;

$cat = $term('zz tf cat', 'category');
$tag = $term('zz tf tag', 'post_tag');
$say('categories and tags', $mask([
    get_cat_ID('Uncategorized'), get_cat_ID('zz tf cat'), get_cat_ID('ZZ TF CAT'), get_cat_ID('zz nowhere'), get_cat_ID(''),
    $shape(get_category_by_slug('zz-tf-cat')), $shape(get_category_by_slug('ZZ-TF-CAT')), get_category_by_slug('zz-nowhere'), get_category_by_slug(''),
    $shape(get_tag($tag)), array_intersect_key((array) get_tag($tag, ARRAY_A), array_flip(['term_id', 'name', 'slug', 'taxonomy'])), get_tag(999999), $shape(get_tag((string) $tag)),
    (static function () use ($cat) {
        $category = get_term($cat, 'category');
        _make_cat_compat($category);
        $row = get_term($cat, 'category', ARRAY_A);
        _make_cat_compat($row);
        $keys = ['cat_ID', 'category_count', 'category_description', 'cat_name', 'category_nicename', 'category_parent'];
        return [array_intersect_key(get_object_vars($category), array_flip($keys)), array_intersect_key($row, array_flip($keys))];
    })(),
]));

register_post_meta('post', 'zz_tf_meta', ['type' => 'string', 'single' => true, 'default' => 'meta default']);
register_meta('post', 'zz_tf_list', ['type' => 'string', 'single' => false, 'default' => 'list default', 'object_subtype' => 'page']);
$plain = $post('zz tf plain');
$say('registered meta', $mask([
    registered_meta_key_exists('post', 'zz_tf_meta'), registered_meta_key_exists('post', 'zz_tf_meta', 'post'), registered_meta_key_exists('post', 'zz_tf_list'), registered_meta_key_exists('post', 'zz_tf_list', 'page'),
    registered_meta_key_exists('post', 'zz_tf_nope'), registered_meta_key_exists('user', 'zz_tf_meta'),
    filter_default_metadata(null, $plain, 'zz_tf_meta', true, 'post'), filter_default_metadata(null, $plain, 'zz_tf_meta', false, 'post'), filter_default_metadata('kept', $plain, 'zz_tf_meta', true, 'post'),
    filter_default_metadata(null, $plain, 'zz_tf_nope', true, 'post'), filter_default_metadata(null, 999999, 'zz_tf_meta', true, 'post'), get_post_meta($plain, 'zz_tf_meta', true),
]));
unregister_post_meta('post', 'zz_tf_meta');
unregister_meta_key('post', 'zz_tf_list', 'page');

$aside = $post('zz tf aside');
set_post_format($aside, 'aside');
$query(['p' => $aside]);
$say('post formats', $mask([
    get_post_format_string('aside'), get_post_format_string('standard'), get_post_format_string('zz'), get_post_format_string(''),
    has_post_format('aside', $aside), has_post_format(['gallery', 'aside'], $aside), has_post_format('gallery', $aside), has_post_format([], $aside), has_post_format(), has_post_format('aside', $plain), has_post_format([], $plain),
    _post_format_request(['post_format' => 'aside']), _post_format_request(['post_format' => 'post-format-aside']), _post_format_request(['post_format' => 'zz']), _post_format_request(['post_format' => 'standard']), _post_format_request(['p' => 5]),
]));

$upload = wp_get_upload_dir();
$made['posts'][] = $image = (int) wp_insert_attachment(['post_title' => 'zz tf image', 'post_mime_type' => 'image/jpeg', 'post_status' => 'inherit'], $upload['basedir'] . '/2026/10/zz-tf-image.jpg');
$ids[$image] = 'image';
wp_update_attachment_metadata($image, ['width' => 1200, 'height' => 800, 'file' => '2026/10/zz-tf-image.jpg', 'sizes' => ['medium' => ['file' => 'zz-tf-image-300x200.jpg', 'width' => 300, 'height' => 200, 'mime-type' => 'image/jpeg'], 'post-thumbnail' => ['file' => 'zz-tf-image-640x427.jpg', 'width' => 640, 'height' => 427, 'mime-type' => 'image/jpeg']]]);
$featured = $post('zz tf featured');
set_post_thumbnail($featured, $image);
$query(['p' => $featured]);
$say('post thumbnails', $mask([
    get_post_thumbnail_id($featured), get_post_thumbnail_id(get_post($featured)), get_post_thumbnail_id(), get_post_thumbnail_id($plain), get_post_thumbnail_id(999999),
    has_post_thumbnail($featured), has_post_thumbnail(), has_post_thumbnail($plain), has_post_thumbnail(999999),
    $echoed(static fn () => the_post_thumbnail_url()), $echoed(static fn () => the_post_thumbnail_url('medium')), $echoed(static fn () => the_post_thumbnail_url('full')),
    $echoed(static fn () => the_post_thumbnail()), $echoed(static fn () => the_post_thumbnail('medium', ['class' => 'zz-thumb', 'alt' => 'ZZ alt'])), $echoed(static fn () => the_post_thumbnail('full', 'loading=eager')),
]));

register_nav_menu('zz-tf-loc', 'ZZ Template Family');
$made['menus'][] = $menu = (int) wp_create_nav_menu('zz tf menu');
$ids[$menu] = 'menu';
$parentItem = (int) wp_update_nav_menu_item($menu, 0, ['menu-item-type' => 'post_type', 'menu-item-object' => 'post', 'menu-item-object-id' => $plain, 'menu-item-status' => 'publish']);
$ids[$parentItem] = 'item-a';
$childItem = (int) wp_update_nav_menu_item($menu, 0, ['menu-item-type' => 'custom', 'menu-item-title' => 'ZZ Child', 'menu-item-url' => 'https://zz.example/child', 'menu-item-parent-id' => $parentItem, 'menu-item-status' => 'publish']);
$ids[$childItem] = 'item-b';
$catItem = (int) wp_update_nav_menu_item($menu, 0, ['menu-item-type' => 'taxonomy', 'menu-item-object' => 'category', 'menu-item-object-id' => $cat, 'menu-item-status' => 'publish']);
$ids[$catItem] = 'item-c';
$mods = get_theme_mod('nav_menu_locations');
set_theme_mod('nav_menu_locations', ['zz-tf-loc' => $menu]);
$query(['p' => $plain]);
$menus = [
    wp_nav_menu(['theme_location' => 'zz-tf-loc', 'echo' => false]),
    wp_nav_menu(['menu' => $menu, 'echo' => false, 'container' => 'nav', 'container_class' => 'zz-nav', 'menu_class' => 'zz-list', 'depth' => 1]),
    wp_nav_menu(['menu' => 'zz tf menu', 'echo' => false, 'container' => false, 'items_wrap' => '<ol id="%1$s" class="%2$s">%3$s</ol>', 'before' => '<b>', 'after' => '</b>', 'link_before' => '[', 'link_after' => ']']),
    wp_nav_menu(['menu' => 999999, 'echo' => false, 'fallback_cb' => false]),
    $echoed(static fn () => wp_nav_menu(['menu' => $menu, 'menu_id' => 'zz-given-id'])),
];
$items = wp_get_nav_menu_items($menu);
_wp_menu_item_classes_by_context($items);
$menus[] = walk_nav_menu_tree($items, 0, (object) ['before' => '', 'after' => '', 'link_before' => '', 'link_after' => '', 'item_spacing' => 'preserve', 'walker' => '']);
$menus[] = walk_nav_menu_tree($items, 1, (object) ['before' => '', 'after' => '', 'link_before' => '', 'link_after' => '', 'item_spacing' => 'discard', 'walker' => '']);
$mods === false ? remove_theme_mod('nav_menu_locations') : set_theme_mod('nav_menu_locations', $mods);
$say('a classic menu', $mask($menus));

add_shortcode('zz_tf', static fn ($atts, $content = '') => 'ZZ[' . ($atts['a'] ?? '') . '|' . $content . ']');
$say('shortcodes', [
    do_shortcodes_in_html_tags('<a title="[zz_tf a=1]" href="x">[zz_tf]</a>', false, ['zz_tf']), do_shortcodes_in_html_tags('<a title="[zz_tf a=1]">x</a>', true, ['zz_tf']),
    do_shortcodes_in_html_tags('<!-- [zz_tf] --><p data-x="[[zz_tf]]">y</p>', false, ['zz_tf']), do_shortcodes_in_html_tags('<p>[zz_tf]</p>', false, []),
    unescape_invalid_shortcodes('&#91;zz_tf&#93; and &#91;nope&#93;'), unescape_invalid_shortcodes('plain'),
    strip_shortcode_tag(['[zz_tf a=1]', '', 'zz_tf', ' a=1', '', '', '', '']), strip_shortcode_tag(['[[zz_tf]]', '[', 'zz_tf', '', '', '', '', ']']),
    _filter_do_shortcode_context(), (static function () {
        remove_all_shortcodes();
        return [shortcode_exists('zz_tf'), shortcode_exists('caption'), do_shortcode('[zz_tf]')];
    })(),
]);

$theme = get_stylesheet();
$templates = [];
foreach (['zz-tf-one', 'zz-tf-two'] as $slug) {
    $id = wp_insert_post(['post_type' => 'wp_template', 'post_name' => $slug, 'post_title' => $slug, 'post_status' => 'publish']);
    if (!is_int($id) || $id < 2) {
        fwrite(STDERR, "template-family: the template {$slug} could not be made\n");
        exit(1);
    }
    $made['posts'][] = $id;
    wp_set_object_terms($id, $theme, 'wp_theme');
    $templates[] = $id;
}
[$first, $second] = $templates;
$say('template slugs kept unique in a theme', [
    wp_filter_wp_template_unique_post_slug(null, 'zz-tf-one', $second, 'publish', 'wp_template'), wp_filter_wp_template_unique_post_slug(null, 'zz-tf-one', $first, 'publish', 'wp_template'),
    wp_filter_wp_template_unique_post_slug(null, 'zz-tf-one', 0, 'publish', 'wp_template'), wp_filter_wp_template_unique_post_slug(null, 'zz-tf-one', $second, 'publish', 'wp_template_part'),
    wp_filter_wp_template_unique_post_slug('given', 'zz-tf-one', $second, 'publish', 'wp_template'), wp_filter_wp_template_unique_post_slug(null, 'zz-tf-one', $second, 'auto-draft', 'wp_template'),
]);

$say('block templates', [
    current_theme_supports('block-templates'), wp_enable_block_templates(), current_theme_supports('block-templates'),
    wp_filter_wp_template_unique_post_slug('given-slug', 'given-slug', $plain, 'publish', 'post'), wp_filter_wp_template_unique_post_slug('given-slug', 'given-slug', 0, 'publish', 'wp_template'),
    wp_filter_wp_template_unique_post_slug(null, 'index', 0, 'publish', 'wp_template'), wp_filter_wp_template_unique_post_slug('override', 'single', 0, 'auto-draft', 'wp_template_part'),
]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
