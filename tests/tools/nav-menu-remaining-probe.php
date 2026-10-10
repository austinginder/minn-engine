<?php
/**
 * The rest of nav-menu.php (probe nav-menu-remaining): a menu location
 * registered alone, the locations assigned and checked (registered or not,
 * empty, a missing menu, through the has_nav_menu filter), menus recognised
 * by id, slug, name and object, pages added to menus that take new pages
 * (top-level pages only, published from another status), menu items taken
 * away with the post they point at, and the locations carried across a theme
 * switch. The probe's menus and pages are removed, and the theme's mods and
 * the menu options put back, at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$mods = 'theme_mods_' . get_stylesheet();
$saved = [];
foreach ([$mods, 'nav_menu_options', 'theme_switch_menu_locations'] as $name) {
    $saved[$name] = get_option($name, null);
}
$made = ['menus' => [], 'posts' => []];
$pings = wp_next_scheduled('do_pings');
register_shutdown_function(static function () use (&$made, $saved, $pings): void {
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
    foreach ($saved as $name => $value) {
        $value === null ? delete_option($name) : update_option($name, $value);
    }
    if ($pings === false) {
        wp_clear_scheduled_hook('do_pings');
    }
});
$ids = [];
$mask = static function ($value) use (&$mask, &$ids) {
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    if (is_int($value) || (is_string($value) && ctype_digit($value))) {
        return isset($ids[(int) $value]) ? '{' . $ids[(int) $value] . '}' : $value;
    }
    return $value;
};
$menu = static function (string $name) use (&$made, &$ids): int {
    $id = wp_create_nav_menu($name);
    if (!is_int($id) || $id < 1) {
        fwrite(STDERR, "nav-menu-remaining: the menu {$name} could not be made\n");
        exit(1);
    }
    $made['menus'][] = $id;
    $ids[$id] = str_replace(' ', '-', $name);
    return $id;
};
$page = static function (string $title, array $extra = []) use (&$made, &$ids): int {
    $id = wp_insert_post($extra + ['post_title' => $title, 'post_type' => 'page', 'post_status' => 'publish', 'post_content' => 'x']);
    if (!is_int($id) || $id < 2) {
        fwrite(STDERR, "nav-menu-remaining: the page {$title} could not be made\n");
        exit(1);
    }
    $made['posts'][] = $id;
    $ids[$id] = str_replace(' ', '-', $title);
    return $id;
};
$items = static function (int $menu) use ($mask): array {
    $out = [];
    foreach ((array) wp_get_nav_menu_items($menu) as $item) {
        // A custom link points at itself; its id changes from run to run.
        $out[] = $mask([(int) $item->object_id === (int) $item->ID ? 'self' : (int) $item->object_id, $item->object, $item->type, $item->title, (int) $item->menu_order, (int) $item->menu_item_parent]);
    }
    return $out;
};

register_nav_menu('zz-main', 'ZZ Main Menu');
register_nav_menu('zz-footer', 'ZZ Footer');
$registered = get_registered_nav_menus();
$say('a location registered alone', [$registered['zz-main'] ?? null, $registered['zz-footer'] ?? null]);

$main = $menu('zz nm main');
$other = $menu('zz nm other');
set_theme_mod('nav_menu_locations', ['zz-main' => $main, 'zz-footer' => 0, 'zz-unregistered' => $other, 'zz-missing-menu' => 999999]);
$checks = [$mask(get_nav_menu_locations()), has_nav_menu('zz-main'), has_nav_menu('zz-footer'), has_nav_menu('zz-unregistered'), has_nav_menu('zz-missing-menu'), has_nav_menu('zz-nowhere')];
add_filter('has_nav_menu', $filter = static function ($has, $location) use (&$checks) {
    $checks[] = ['heard', $has, $location];
    return $location === 'zz-footer' ? true : $has;
}, 10, 2);
$checks[] = has_nav_menu('zz-main');
$checks[] = has_nav_menu('zz-footer');
remove_filter('has_nav_menu', $filter, 10);
set_theme_mod('nav_menu_locations', 'not a list');
$checks[] = get_nav_menu_locations();
remove_theme_mod('nav_menu_locations');
$checks[] = [get_nav_menu_locations(), has_nav_menu('zz-main')];
$say('locations assigned and checked', $checks);

$say('menus recognised', [is_nav_menu($main), is_nav_menu((string) $main), is_nav_menu('zz-nm-main'), is_nav_menu('zz nm main'), is_nav_menu(wp_get_nav_menu_object($main)), is_nav_menu(999999), is_nav_menu(''), is_nav_menu(0), is_nav_menu('uncategorized')]);

update_option('nav_menu_options', ['auto_add' => [$main]]);
$top = $page('zz nm top');
$child = $page('zz nm child', ['post_parent' => $top]);
$drafted = $page('zz nm drafted', ['post_status' => 'draft']);
$hooked = $items($main);
_wp_auto_add_pages_to_menu('publish', 'draft', get_post($drafted));
_wp_auto_add_pages_to_menu('publish', 'publish', get_post($top));
_wp_auto_add_pages_to_menu('draft', 'publish', get_post($top));
_wp_auto_add_pages_to_menu('publish', 'future', get_post($child));
$made['posts'][] = $post = (int) wp_insert_post(['post_title' => 'zz nm post', 'post_status' => 'publish']);
$ids[$post] = 'zz-nm-post';
_wp_auto_add_pages_to_menu('publish', 'draft', get_post($post));
$say('pages added to menus that take them', [$hooked, $items($main), $items($other)]);

wp_update_nav_menu_item($other, 0, ['menu-item-type' => 'post_type', 'menu-item-object' => 'page', 'menu-item-object-id' => $top, 'menu-item-status' => 'publish']);
wp_update_nav_menu_item($other, 0, ['menu-item-type' => 'custom', 'menu-item-title' => 'ZZ Custom', 'menu-item-url' => 'https://zz.example/', 'menu-item-status' => 'publish']);
wp_update_nav_menu_item($other, 0, ['menu-item-type' => 'taxonomy', 'menu-item-object' => 'category', 'menu-item-object-id' => 1, 'menu-item-status' => 'publish']);
$before = [$items($main), $items($other)];
_wp_delete_post_menu_item($top);
_wp_delete_post_menu_item(999999);
$say('menu items taken away with their post', [$before, $items($main), $items($other)]);

register_nav_menu('primary', 'ZZ Primary');
register_nav_menu('zz-header', 'ZZ Header');
$switched = [];
foreach ([
    'same names' => [['zz-main' => $main, 'zz-footer' => $other], ['zz-main' => 0]],
    'common names' => [['main' => $main, 'footer' => $other, 'zz-gone' => $main], []],
    'nothing kept' => [false, ['zz-main' => $other]],
] as $label => [$old, $current]) {
    $old === false ? delete_option('theme_switch_menu_locations') : update_option('theme_switch_menu_locations', $old);
    set_theme_mod('nav_menu_locations', $current);
    _wp_menus_changed();
    $switched[$label] = [$mask(get_nav_menu_locations()), get_option('theme_switch_menu_locations', 'gone')];
}
$say('locations across a theme switch', $switched);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
