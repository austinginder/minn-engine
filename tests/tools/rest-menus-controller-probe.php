<?php
/**
 * The menus and menu items controller classes (probe rest-menus-controller):
 * each controller's base; their schemas and collection parameters; the
 * permission checks for a visitor, a contributor and an administrator
 * (menus readable to editors of posts, or to anyone through
 * rest_menu_read_access); a menu's locations and auto add; menus and their
 * items read, listed and prepared for the database; and a menu and an item
 * created, updated and deleted through them.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
add_filter('doing_it_wrong_trigger_error', '__return_false');
if (!did_action('init') && class_exists(Minn\Runtime\Plugins::class)) {
    Minn\Runtime\Plugins::load(Minn\Runtime\Runtime::current());
}
require_once ABSPATH . 'wp-admin/includes/user.php';
require_once ABSPATH . 'wp-admin/includes/nav-menu.php';
$try = static function (Closure $run) {
    try {
        return $run();
    } catch (Throwable $e) {
        return ['threw' => get_class($e)];
    }
};
$call = static fn (object $object, string $method, ...$args) => $try(static fn () => Closure::bind(fn (...$given) => $this->{$method}(...$given), $object, get_class($object))(...$args));
$named = static function ($value) use (&$named) {
    if ($value instanceof Closure) {
        return '@closure';
    }
    if (is_array($value) && count($value) === 2 && isset($value[0], $value[1]) && is_object($value[0]) && is_string($value[1])) {
        return '@method:' . get_class($value[0]) . '::' . $value[1];
    }
    return is_array($value) ? array_map($named, $value) : $value;
};
$shown = static fn ($response) => is_wp_error($response) ? ['error' => $response->get_error_code(), 'message' => $response->get_error_message(), 'data' => $response->get_error_data()] : ($response instanceof WP_REST_Response ? ['status' => $response->get_status(), 'data' => $response->get_data(), 'links' => $response->get_links(), 'headers' => $response->get_headers()] : $response);
$request = static function (string $method, string $route, array $params = [], array $defaults = []) {
    $request = new WP_REST_Request($method, $route);
    foreach ($params as $key => $value) {
        $request->set_param($key, $value);
    }
    $request->set_default_params($defaults);
    return $request;
};
wp_set_current_user(0);
rest_get_server();

// The menus, items, user, menu options and locations made here are put back on the way out.
$made = ['menus' => [], 'users' => []];
$options = get_option('nav_menu_options');
$locations = get_theme_mod('nav_menu_locations');
register_shutdown_function(static function () use (&$made, $options, $locations): void {
    wp_set_current_user(1);
    foreach ($made['menus'] as $id) {
        if (is_int($id) && $id > 1) {
            wp_delete_nav_menu($id);
        }
    }
    foreach ($made['users'] as $id) {
        if (is_int($id) && $id > 1) {
            wp_delete_user($id);
        }
    }
    $options === false ? delete_option('nav_menu_options') : update_option('nav_menu_options', $options);
    $locations === false ? remove_theme_mod('nav_menu_locations') : set_theme_mod('nav_menu_locations', $locations);
});
register_nav_menus(['zz-primary' => 'ZZ Primary', 'zz-footer' => 'ZZ Footer']);
$contributor = wp_insert_user(['user_login' => 'zz_rmc_contributor', 'user_pass' => wp_generate_password(), 'user_email' => 'zz-rmc@example.com', 'role' => 'contributor']);
$menu = wp_create_nav_menu('ZZ rmc menu');
foreach ([[$contributor, 'users'], [$menu, 'menus']] as [$id, $kind]) {
    if (!is_int($id) || $id <= 1) {
        fwrite(STDERR, "no fixture\n");
        exit(1);
    }
    $made[$kind][] = $id;
}
wp_set_current_user(1);
$custom = wp_update_nav_menu_item($menu, 0, ['menu-item-title' => 'ZZ custom', 'menu-item-url' => 'https://example.com/zz', 'menu-item-status' => 'publish', 'menu-item-type' => 'custom', 'menu-item-attr-title' => 'Attr', 'menu-item-target' => '_blank', 'menu-item-classes' => 'zz-a zz-b', 'menu-item-xfn' => 'friend', 'menu-item-description' => 'Desc']);
$postItem = wp_update_nav_menu_item($menu, 0, ['menu-item-object-id' => 1, 'menu-item-object' => 'post', 'menu-item-type' => 'post_type', 'menu-item-status' => 'publish', 'menu-item-parent-id' => $custom]);
$termItem = wp_update_nav_menu_item($menu, 0, ['menu-item-object-id' => 1, 'menu-item-object' => 'category', 'menu-item-type' => 'taxonomy', 'menu-item-status' => 'publish']);
set_theme_mod('nav_menu_locations', array_merge((array) $locations, ['zz-primary' => $menu]));
wp_set_current_user(0);
$ids = [$contributor, $menu, $custom, $postItem, $termItem];
$masked = static function ($value) use (&$ids) {
    rsort($ids);
    $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    foreach ($ids as $index => $id) {
        $json = preg_replace('/(?<=[:\[,])' . $id . '(?=[,\]}])/', '"{id' . $index . '}"', $json);
        $json = preg_replace('/(?<![0-9])' . $id . '(?![0-9])/', '{id' . $index . '}', $json);
    }
    return json_decode($json, true);
};

$menus = new WP_REST_Menus_Controller('nav_menu');
$items = new WP_REST_Menu_Items_Controller('nav_menu_item');
$say('schemas', ['menus' => $named($menus->get_item_schema()), 'menu items' => $named($items->get_item_schema()), 'menu item schema links' => $call($items, 'get_schema_links')]);
$say('collection parameters', ['menus' => $named($menus->get_collection_params()), 'menu items' => $named($items->get_collection_params())]);

$checks = static function () use ($menus, $items, $call, $shown, $request, $menu, $custom) {
    return [
        'menus' => $shown($menus->get_items_permissions_check($request('GET', '/wp/v2/menus'))),
        'a menu' => $shown($menus->get_item_permissions_check($request('GET', '/wp/v2/menus/' . $menu, ['id' => $menu]))),
        'a missing menu' => $shown($menus->get_item_permissions_check($request('GET', '/wp/v2/menus/999999', ['id' => 999999]))),
        'menus read only' => $call($menus, 'check_has_read_only_access', $request('GET', '/wp/v2/menus')),
        'items' => $shown($items->get_items_permissions_check($request('GET', '/wp/v2/menu-items'))),
        'an item' => $shown($items->get_item_permissions_check($request('GET', '/wp/v2/menu-items/' . $custom, ['id' => $custom]))),
        'a missing item' => $shown($items->get_item_permissions_check($request('GET', '/wp/v2/menu-items/999999', ['id' => 999999]))),
        'items read only' => $call($items, 'check_has_read_only_access', $request('GET', '/wp/v2/menu-items')),
        'create a menu' => $shown($menus->create_item_permissions_check($request('POST', '/wp/v2/menus', ['name' => 'x']))),
        'create an item' => $shown($items->create_item_permissions_check($request('POST', '/wp/v2/menu-items', ['title' => 'x']))),
    ];
};
$visitor = $checks();
add_filter('rest_menu_read_access', '__return_true');
$visitorOpened = $checks();
remove_filter('rest_menu_read_access', '__return_true');
wp_set_current_user($contributor);
$asContributor = $checks();
wp_set_current_user(1);
$administrator = $checks();
$say('permissions', $masked(['a visitor' => $visitor, 'a visitor, menus opened' => $visitorOpened, 'a contributor' => $asContributor, 'an administrator' => $administrator]));

$say('a menu\'s settings', $masked([
    'locations' => $call($menus, 'get_menu_locations', $menu),
    'no locations' => $call($menus, 'get_menu_locations', 999999),
    'auto add' => $call($menus, 'get_menu_auto_add', $menu),
    'got' => [get_class($call($menus, 'get_term', $menu)), $shown($call($menus, 'get_term', 999999)), $shown($call($menus, 'get_term', 1))],
    'item got' => [get_class($call($items, 'get_nav_menu_item', $custom)), $shown($call($items, 'get_nav_menu_item', 999999)), $shown($call($items, 'get_nav_menu_item', 1))],
    'menu of an item' => [$call($items, 'get_menu_id', $custom), $call($items, 'get_menu_id', 999999)],
]));
wp_set_current_user(1);
$say('a menu\'s settings changed', $masked([
    'auto add on' => [$call($menus, 'handle_auto_add', $menu, $request('PUT', '/wp/v2/menus/' . $menu, ['auto_add' => true])), $call($menus, 'get_menu_auto_add', $menu), get_option('nav_menu_options')],
    'auto add again' => $call($menus, 'handle_auto_add', $menu, $request('PUT', '/wp/v2/menus/' . $menu, ['auto_add' => true])),
    'auto add off' => [$call($menus, 'handle_auto_add', $menu, $request('PUT', '/wp/v2/menus/' . $menu, ['auto_add' => false])), $call($menus, 'get_menu_auto_add', $menu)],
    'auto add not asked' => $call($menus, 'handle_auto_add', $menu, $request('PUT', '/wp/v2/menus/' . $menu)),
    'locations' => [$shown($call($menus, 'handle_locations', $menu, $request('PUT', '/wp/v2/menus/' . $menu, ['locations' => ['zz-footer']]))), $call($menus, 'get_menu_locations', $menu)],
    'an unknown location' => $shown($call($menus, 'handle_locations', $menu, $request('PUT', '/wp/v2/menus/' . $menu, ['locations' => ['zz-none']]))),
    'locations not asked' => $call($menus, 'handle_locations', $menu, $request('PUT', '/wp/v2/menus/' . $menu)),
    'locations back' => [$call($menus, 'handle_locations', $menu, $request('PUT', '/wp/v2/menus/' . $menu, ['locations' => ['zz-primary']])), $call($menus, 'get_menu_locations', $menu)],
]));
wp_set_current_user(0);

// Reading, listing and writing as the administrator (the reference's methods do not check, the engine's routes do).
wp_set_current_user(1);
$readMenu = static fn (array $params) => $shown($menus->get_item($request('GET', '/wp/v2/menus/' . $menu, ['id' => $menu] + $params)));
$readItem = static fn (array $params, int $id = 0) => $shown($items->get_item($request('GET', '/wp/v2/menu-items/' . ($id ?: $custom), ['id' => $id ?: $custom] + $params)));
$say('reading', $masked([
    'a menu' => $readMenu([]),
    'a menu, edit' => $readMenu(['context' => 'edit']),
    'a menu, embedded' => $readMenu(['context' => 'embed']),
    'a menu, fields' => $readMenu(['_fields' => 'id,name,locations']),
    'menu links' => $call($menus, 'prepare_links', get_term($menu, 'nav_menu')),
    'a custom item' => $readItem([]),
    'a custom item, edit' => $readItem(['context' => 'edit']),
    'a post item' => $readItem([], $postItem),
    'a term item' => $readItem(['context' => 'embed'], $termItem),
    'item fields' => $readItem(['_fields' => 'id,title,url']),
    'item links' => $call($items, 'prepare_links', get_post($postItem)),
    'a prepared item' => $shown($items->prepare_item_for_response(get_post($custom), $request('GET', '/wp/v2/menu-items'))),
]));

$menuDefaults = array_filter(array_map(static fn ($arg) => $arg['default'] ?? null, $menus->get_collection_params()), static fn ($value) => $value !== null);
$itemDefaults = array_filter(array_map(static fn ($arg) => $arg['default'] ?? null, $items->get_collection_params()), static fn ($value) => $value !== null);
$say('listing', $masked([
    'menus' => $shown($menus->get_items($request('GET', '/wp/v2/menus', ['include' => [$menu]], $menuDefaults))),
    'the menu\'s items' => $shown($items->get_items($request('GET', '/wp/v2/menu-items', ['menus' => [$menu], 'page' => 1], $itemDefaults))),
    'by menu order' => $shown($items->get_items($request('GET', '/wp/v2/menu-items', ['menus' => [$menu], 'orderby' => 'menu_order', 'order' => 'asc', 'page' => 1], $itemDefaults))),
    'the query' => [$call($items, 'prepare_items_query', ['posts_per_page' => 5], $request('GET', '/wp/v2/menu-items', ['menus' => [$menu]])), $call($items, 'prepare_items_query', ['orderby' => 'menu_order'], $request('GET', '/wp/v2/menu-items', ['orderby' => 'menu_order']))],
]));

$preparedMenu = static fn (array $params, string $method = 'POST') => $try(static function () use ($params, $method, $menus, $call, $request, $menu) {
    $result = $call($menus, 'prepare_item_for_database', $request($method, '/wp/v2/menus' . ($method === 'POST' ? '' : '/' . $menu), $params + ($method === 'POST' ? [] : ['id' => $menu])));
    return is_wp_error($result) ? ['error' => $result->get_error_code()] : (array) $result;
});
$preparedItem = static fn (array $params, string $method = 'POST') => $try(static function () use ($params, $method, $items, $call, $request, $custom) {
    $result = $call($items, 'prepare_item_for_database', $request($method, '/wp/v2/menu-items' . ($method === 'POST' ? '' : '/' . $custom), $params + ($method === 'POST' ? [] : ['id' => $custom])));
    return is_wp_error($result) ? ['error' => $result->get_error_code(), 'message' => $result->get_error_message()] : (array) $result;
});
$say('prepared for the database', $masked([
    'a menu' => $preparedMenu(['name' => 'N', 'description' => 'D', 'slug' => 's']),
    'a menu update' => $preparedMenu(['name' => 'N2'], 'PUT'),
    'a menu, nothing' => $preparedMenu([]),
    'a custom item' => $preparedItem(['title' => 'T', 'url' => 'https://example.com/t', 'type' => 'custom', 'status' => 'publish', 'menus' => $menu, 'menu_order' => 3, 'target' => '_blank', 'classes' => ['a', 'b'], 'xfn' => ['friend'], 'description' => 'D', 'attr_title' => 'A']),
    'a post item' => $preparedItem(['type' => 'post_type', 'object' => 'post', 'object_id' => 1, 'menus' => $menu]),
    'an item update' => $preparedItem(['title' => 'T2'], 'PUT'),
    'an item, nothing' => $preparedItem([]),
    'an item, a missing post' => $preparedItem(['type' => 'post_type', 'object' => 'post', 'object_id' => 999999, 'menus' => $menu]),
    'an item, no url' => $preparedItem(['type' => 'custom', 'title' => 'x', 'menus' => $menu]),
    'an item, a missing parent' => $preparedItem(['type' => 'custom', 'title' => 'x', 'url' => 'https://example.com', 'parent' => 999999, 'menus' => $menu]),
]));

$life = [];
$createdMenu = $menus->create_item($request('POST', '/wp/v2/menus', ['name' => 'ZZ rmc created', 'description' => 'made', 'locations' => ['zz-footer'], 'auto_add' => true]));
$createdMenuId = $createdMenu instanceof WP_REST_Response ? (int) ($createdMenu->get_data()['id'] ?? 0) : 0;
if ($createdMenuId > 1) {
    $made['menus'][] = $createdMenuId;
    $ids[] = $createdMenuId;
}
$life['a menu created'] = $shown($createdMenu);
$life['a menu created again'] = $shown($menus->create_item($request('POST', '/wp/v2/menus', ['name' => 'ZZ rmc created'])));
$life['a menu updated'] = $shown($menus->update_item($request('PUT', '/wp/v2/menus/' . $createdMenuId, ['id' => $createdMenuId, 'name' => 'ZZ rmc renamed', 'locations' => [], 'auto_add' => false])));
$life['locations after'] = [$call($menus, 'get_menu_locations', $createdMenuId), $call($menus, 'get_menu_auto_add', $createdMenuId)];
$createdItem = $items->create_item($request('POST', '/wp/v2/menu-items', ['title' => 'ZZ rmc item', 'url' => 'https://example.com/i', 'menus' => $createdMenuId, 'status' => 'publish', 'menu_order' => 1]));
$createdItemId = $createdItem instanceof WP_REST_Response ? (int) ($createdItem->get_data()['id'] ?? 0) : 0;
if ($createdItemId > 1) {
    $ids[] = $createdItemId;
}
$life['an item created'] = $shown($createdItem);
$life['an item updated'] = $shown($items->update_item($request('PUT', '/wp/v2/menu-items/' . $createdItemId, ['id' => $createdItemId, 'title' => 'ZZ rmc item renamed', 'menus' => $createdMenuId])));
$life['an item deleted, not forced'] = $shown($items->delete_item($request('DELETE', '/wp/v2/menu-items/' . $createdItemId, ['id' => $createdItemId])));
$life['an item deleted'] = $shown($items->delete_item($request('DELETE', '/wp/v2/menu-items/' . $createdItemId, ['id' => $createdItemId, 'force' => true])));
$life['a menu deleted, not forced'] = $shown($menus->delete_item($request('DELETE', '/wp/v2/menus/' . $createdMenuId, ['id' => $createdMenuId])));
$life['a menu deleted'] = $shown($menus->delete_item($request('DELETE', '/wp/v2/menus/' . $createdMenuId, ['id' => $createdMenuId, 'force' => true])));
$life['gone'] = [get_term($createdMenuId, 'nav_menu') === null, get_post($createdItemId) === null];
wp_set_current_user(0);
$say('a menu through its life', $masked($life));

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
