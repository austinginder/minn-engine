<?php
/**
 * Category and nav menu helpers (probe category-menu-helpers): categories
 * found by path, sanitized and cleaned from the cache, the tag cloud's
 * sort callbacks and count scale; menu items checked, found for the object
 * they point to, cached, removed with their term, and the menu template's id and has-children filters.
 * The probe's categories, post and menu are removed.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = ['terms' => [], 'posts' => [], 'menus' => []];
register_shutdown_function(static function () use (&$made): void {
    foreach ($made['menus'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_nav_menu($id);
        }
    }
    foreach ($made['posts'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_post($id, true);
        }
    }
    foreach (array_reverse($made['terms']) as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_term($id, 'category');
        }
    }
});
$ids = [];
$term = static function (string $name, int $parent = 0) use (&$made) {
    $made['terms'][] = $id = (int) (wp_insert_term($name, 'category', ['parent' => $parent])['term_id'] ?? 0);
    return $id;
};
$parent = $term('Zz Cat Parent');
$child = $term('Zz Cat Child', $parent);
$other = $term('Zz Cat Child Other');
$shape = static fn ($value) => $value instanceof WP_Term ? ['term', $value->slug, $value->parent > 0] : (is_array($value) ? array_intersect_key($value, array_flip(['slug', 'name', 'taxonomy'])) : $value);

$say('categories by path', [
    $shape(get_category_by_path('zz-cat-parent')),
    $shape(get_category_by_path('zz-cat-parent/zz-cat-child')),
    $shape(get_category_by_path('/zz-cat-parent/zz-cat-child/')),
    $shape(get_category_by_path('zz-cat-child')),
    $shape(get_category_by_path('zz-cat-child', false)),
    $shape(get_category_by_path('zz-wrong/zz-cat-child')),
    $shape(get_category_by_path('zz-wrong/zz-cat-child', false)),
    $shape(get_category_by_path('zz-cat-parent/zz-cat-child', true, ARRAY_A)),
    $shape(get_category_by_path('zz-cat-never')),
    $shape(get_category_by_path('ZZ-CAT-PARENT/Zz-Cat-Child')),
    $shape(get_category_by_path('zz-cat-parent/zz-cat-child?x=1')),
]);

$category = get_category($child);
$say('categories sanitized', [
    (static function () use ($category) {
        $copy = clone $category;
        $copy->name = '<b>Zz & "Name"</b>';
        $copy->description = "Line one\nline <script>two</script>";
        $out = sanitize_category($copy);
        return [$out->name, $out->description, $out->filter];
    })(),
    (static function () use ($category) {
        $copy = (array) $category;
        $copy['name'] = 'Zz <i>raw</i>';
        $out = sanitize_category($copy, 'raw');
        return [$out['name'], $out['filter'] ?? null];
    })(),
    sanitize_category_field('name', 'Zz <b>& name</b>', $child, 'display'),
    sanitize_category_field('slug', 'Zz Slug!', $child, 'db'),
    sanitize_category_field('parent', '7abc', $child, 'raw'),
    sanitize_category_field('description', "Zz\n<p>desc</p>", $child, 'edit'),
    (static function () use ($child) {
        wp_cache_set($child, 'zz-cached', 'category');
        $cleaned = clean_category_cache($child);
        return [$cleaned, wp_cache_get($child, 'category')];
    })(),
]);

$objects = [(object) ['name' => 'beta', 'count' => 5], (object) ['name' => 'Alpha', 'count' => 9], (object) ['name' => 'alpha2', 'count' => 5], (object) ['name' => 'alpha10', 'count' => 0]];
$say('tag cloud helpers', [
    _wp_object_count_sort_cb($objects[0], $objects[1]), _wp_object_count_sort_cb($objects[1], $objects[0]), _wp_object_count_sort_cb($objects[0], $objects[2]),
    _wp_object_name_sort_cb($objects[0], $objects[1]), _wp_object_name_sort_cb($objects[2], $objects[3]), _wp_object_name_sort_cb($objects[1], $objects[1]),
    (static function () use ($objects) {
        $list = $objects;
        uasort($list, '_wp_object_name_sort_cb');
        return array_column(array_values($list), 'name');
    })(),
    array_map(static fn ($value) => default_topic_count_scale($value), [0, 1, 2, 9, 10, 99, 1000]),
]);

$made['posts'][] = $post = (int) wp_insert_post(['post_title' => 'zz menu post', 'post_status' => 'publish', 'post_content' => 'x']);
$made['menus'][] = $menu = (int) wp_create_nav_menu('Zz Probe Menu ' . getmypid());
$item = static fn (array $args) => (int) wp_update_nav_menu_item($menu, 0, $args + ['menu-item-status' => 'publish']);
$top = $item(['menu-item-type' => 'post_type', 'menu-item-object' => 'post', 'menu-item-object-id' => $post, 'menu-item-title' => 'Zz Post Item']);
$cat_item = $item(['menu-item-type' => 'taxonomy', 'menu-item-object' => 'category', 'menu-item-object-id' => $other, 'menu-item-parent-id' => $top, 'menu-item-title' => 'Zz Cat Item']);
$custom = $item(['menu-item-type' => 'custom', 'menu-item-url' => 'https://zz.example/', 'menu-item-title' => 'Zz Custom', 'menu-item-parent-id' => $cat_item]);
$second_cat = $item(['menu-item-type' => 'taxonomy', 'menu-item-object' => 'category', 'menu-item-object-id' => $other, 'menu-item-title' => 'Zz Cat Again']);
$position = static fn (array $list, array $all) => array_map(static fn ($id) => array_search($id, $all, true), $list);
$all = [$top, $cat_item, $custom, $second_cat];
$say('menu items', [
    $menu > 0 && $top > 0 && $cat_item > 0,
    $position(wp_get_associated_nav_menu_items($post, 'post_type'), $all),
    $position(wp_get_associated_nav_menu_items($post, 'post_type', 'page'), $all),
    $position(wp_get_associated_nav_menu_items($other, 'taxonomy', 'category'), $all),
    $position(wp_get_associated_nav_menu_items($other, 'taxonomy', 'post_tag'), $all),
    $position(wp_get_associated_nav_menu_items(999999999, 'post_type'), $all),
    _is_valid_nav_menu_item((object) ['_invalid' => false]), _is_valid_nav_menu_item((object) ['_invalid' => true]), _is_valid_nav_menu_item((object) []),
    _is_valid_nav_menu_item(wp_setup_nav_menu_item(get_post($top))),
    (static function () use ($menu) {
        $items = wp_get_nav_menu_items($menu, ['update_menu_item_cache' => false]);
        update_menu_item_cache($items);
        return [count($items), wp_cache_get((int) $items[0]->object_id, 'posts') !== false];
    })(),
]);
$say('removed with its term', [
    has_action('delete_term', '_wp_delete_tax_menu_item'),
    (static function () use ($other, $cat_item, $second_cat, $custom) {
        _wp_delete_tax_menu_item($other, (int) get_term($other)->term_taxonomy_id, 'category');
        return [get_post($cat_item) === null, get_post($second_cat) === null, get_post($custom) !== null, (int) get_post_meta($custom, '_menu_item_menu_item_parent', true) === $cat_item];
    })(),
]);

$say('menu template filters', [
    has_filter('nav_menu_item_id', '_nav_menu_item_id_use_once'),
    (static function () {
        $item = (object) ['ID' => 77];
        $args = (object) ['menu_id' => 'zz'];
        return [_nav_menu_item_id_use_once('menu-item-77', $item), _nav_menu_item_id_use_once('menu-item-77', $item), _nav_menu_item_id_use_once('menu-item-78', (object) ['ID' => 78])];
    })(),
    has_filter('nav_menu_css_class', 'wp_nav_menu_remove_menu_item_has_children_class'),
    wp_nav_menu_remove_menu_item_has_children_class(['menu-item', 'menu-item-has-children'], (object) [], (object) ['depth' => 1], 0),
    wp_nav_menu_remove_menu_item_has_children_class(['menu-item', 'menu-item-has-children'], (object) [], (object) ['depth' => 2], 0),
    wp_nav_menu_remove_menu_item_has_children_class(['menu-item', 'menu-item-has-children'], (object) [], (object) ['depth' => 2], 1),
    wp_nav_menu_remove_menu_item_has_children_class(['menu-item', 'menu-item-has-children'], (object) [], (object) ['depth' => 0], 5),
    wp_nav_menu_remove_menu_item_has_children_class(['menu-item-has-children', 'x'], (object) [], (object) ['depth' => 1], null),
]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
