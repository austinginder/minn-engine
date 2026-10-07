<?php
/**
 * What saving menus through the REST API tells plugins (probe
 * rest-menu-save): a menu made, renamed, given a custom link and a page,
 * the link edited and deleted, then the menu deleted; each request's
 * status and the hooks it fired, in order, with what identifies their
 * subject (the menu and items as {menuN} / {itemN}). Same protocol as
 * api-probe.php; dispatched in process, as an administrator; the menus
 * and items are its own and go at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
wp_set_current_user(1);
foreach (wp_get_nav_menus() as $old) {
    if (str_starts_with((string) $old->name, 'Zz Rest Menu')) {
        wp_delete_nav_menu($old->term_id);
    }
}
$made = ['menu' => [], 'item' => []];
$mask = static function ($value) use (&$made) {
    foreach (['menu', 'item'] as $kind) {
        foreach ($made[$kind] as $n => $id) {
            if ($value === $id || $value === (string) $id) {
                return '{' . $kind . $n . '}';
            }
        }
    }
    return $value;
};
$heard = [];
$note = static function (string $hook, ...$args) use (&$heard, $mask): void {
    $heard[] = [$hook, ...array_map(static fn ($a) => is_scalar($a) ? $mask($a) : (is_object($a) ? get_class($a) : gettype($a)), $args)];
    if (str_starts_with($hook, 'rest_pre_insert') && is_object($args[0] ?? null)) {
        $fields = array_map(static fn ($v) => is_scalar($v) ? $mask($v) : gettype($v), get_object_vars($args[0]));
        ksort($fields);
        $heard[] = ['prepared', $fields];
    }
};
$hooks = [
    'rest_pre_insert_nav_menu' => 2, 'rest_insert_nav_menu' => 3, 'rest_after_insert_nav_menu' => 3, 'rest_delete_nav_menu' => 3,
    'wp_create_nav_menu' => 2, 'wp_update_nav_menu' => 2, 'wp_delete_nav_menu' => 1, 'created_nav_menu' => 2, 'edited_nav_menu' => 2, 'delete_nav_menu' => 2,
    'rest_pre_insert_nav_menu_item' => 2, 'rest_insert_nav_menu_item' => 3, 'rest_after_insert_nav_menu_item' => 3, 'rest_delete_nav_menu_item' => 3,
    'wp_add_nav_menu_item' => 3, 'wp_update_nav_menu_item' => 3, 'save_post_nav_menu_item' => 3, 'before_delete_post' => 2, 'deleted_post' => 2,
];
foreach ($hooks as $hook => $count) {
    $callback = static function (...$args) use ($note, $hook) {
        $note($hook, ...array_slice($args, 0, 3));
        return $args[0] ?? null;
    };
    str_starts_with($hook, 'rest_pre_insert') ? add_filter($hook, $callback, 10, $count) : add_action($hook, $callback, 10, $count);
}
$send = static function (string $label, string $method, string $route, array $body, string $kind = '') use (&$heard, $say, $mask, &$made): array {
    $heard = [];
    [$path, $query] = array_pad(explode('?', $route, 2), 2, '');
    $request = new WP_REST_Request($method, $path);
    parse_str($query, $params);
    $request->set_query_params($params);
    $request->set_body_params($body);
    $response = rest_do_request($request);
    $data = (array) rest_get_server()->response_to_data($response, false);
    if ($kind !== '' && $response->get_status() === 201 && isset($data['id'])) {
        $made[$kind][] = (int) $data['id'];
    }
    $say($label, ['status' => $response->get_status(), 'heard' => array_map(static fn ($h) => array_map($mask, $h), $heard)]);
    return $data;
};
$menu = $send('a menu made', 'POST', '/wp/v2/menus', ['name' => 'Zz Rest Menu'], 'menu');
$menuId = (int) ($menu['id'] ?? 0);
$send('the menu renamed', 'POST', "/wp/v2/menus/{$menuId}", ['name' => 'Zz Rest Menu Two']);
$link = $send('a custom link added', 'POST', '/wp/v2/menu-items', ['title' => 'Zz Link', 'url' => 'https://zz.example/', 'menus' => $menuId, 'status' => 'publish'], 'item');
$send('a page added', 'POST', '/wp/v2/menu-items', ['type' => 'post_type', 'object' => 'page', 'object_id' => 2, 'menus' => $menuId, 'status' => 'publish'], 'item');
$linkId = (int) ($link['id'] ?? 0);
$send('the link edited', 'POST', "/wp/v2/menu-items/{$linkId}", ['title' => 'Zz Link Two']);
$stored = static function (int $id) use ($mask): array {
    $item = wp_setup_nav_menu_item(get_post($id));
    $menus = wp_get_object_terms($id, 'nav_menu', ['fields' => 'ids']);
    return array_map($mask, ['title' => get_post_field('post_title', $id, 'raw'), 'shown' => $item->title, 'url' => $item->url, 'type' => $item->type, 'object' => $item->object, 'object_id' => (int) $item->object_id,
        'parent' => (int) $item->menu_item_parent, 'order' => (int) $item->menu_order, 'status' => $item->post_status, 'target' => $item->target, 'attr_title' => $item->attr_title,
        'description' => $item->description, 'classes' => implode(' ', (array) $item->classes), 'xfn' => $item->xfn, 'menu' => (int) ($menus[0] ?? 0)]);
};
$say('the items as stored', array_map($stored, $made['item']));
$send('the link deleted', 'DELETE', "/wp/v2/menu-items/{$linkId}?force=true", []);
$send('the menu deleted', 'DELETE', "/wp/v2/menus/{$menuId}?force=true", []);

foreach (wp_get_nav_menus() as $old) {
    if (str_starts_with((string) $old->name, 'Zz Rest Menu')) {
        wp_delete_nav_menu($old->term_id);
    }
}
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
