<?php
/**
 * wp/v2/types in the edit context, as the reference answers it (probe
 * rest-types-edit): the capabilities, visibility, viewability, labels and
 * supports each type adds there, for core types and a plugin's type with
 * its own labels, capabilities and supports; the list as an administrator
 * and an author see it; who may ask (a visitor, an author for a type they
 * cannot edit). Same protocol as api-probe.php; dispatched in process.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
foreach (['home_url', 'site_url', 'option_home', 'option_siteurl'] as $devHook) {
    remove_all_filters($devHook);
}
register_post_type('zz_book', [
    'public' => true,
    'show_in_rest' => true,
    'show_in_nav_menus' => false,
    'labels' => ['name' => 'Zz Books', 'singular_name' => 'Zz Book', 'add_new_item' => 'Add Zz Book'],
    'capability_type' => ['zz_book', 'zz_books'],
    'map_meta_cap' => true,
    'supports' => ['title', 'editor', 'thumbnail', 'custom-fields', 'zz-feature'],
    'menu_icon' => 'dashicons-book',
]);
register_post_type('zz_note', [
    'public' => false,
    'show_ui' => true,
    'show_in_rest' => true,
    'hierarchical' => true,
    'labels' => ['name' => 'Zz Notes', 'singular_name' => 'Zz Note', 'menu_name' => 'Zz Menu'],
    'supports' => ['title', 'page-attributes', 'zz-feature' => ['a' => 1]],
    'rest_base' => 'zz-notes',
    'menu_icon' => 'data:image/svg+xml;base64,PHN2Zy8+',
]);
$get = static function (string $route, array $query = []): array {
    $request = new WP_REST_Request('GET', $route);
    $request->set_query_params($query);
    $response = rest_do_request($request);
    return ['status' => $response->get_status(), 'data' => rest_get_server()->response_to_data($response, false)];
};
$home = home_url();
$mask = static fn ($value) => json_decode(str_replace([$home, addcslashes($home, '/')], '{home}', (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)), true);
wp_set_current_user(1);
foreach (['post', 'page', 'attachment', 'wp_block', 'wp_template', 'nav_menu_item', 'zz_book', 'zz_note'] as $type) {
    $say("edit {$type}", $mask($get("/wp/v2/types/{$type}", ['context' => 'edit'])));
}
$say('edit list as admin', array_keys((array) $get('/wp/v2/types', ['context' => 'edit'])['data']));
$say('view post', $mask($get('/wp/v2/types/post')));
$say('view zz_note', $mask($get('/wp/v2/types/zz_note')));
$say('view list keys', array_keys((array) $get('/wp/v2/types')['data']));
$say('embed post', $mask($get('/wp/v2/types/post', ['context' => 'embed'])));
$author = (int) (get_users(['role' => 'author', 'number' => 1, 'fields' => 'ID'])[0] ?? 3);
wp_set_current_user($author);
$say('edit list as an author', array_keys((array) $get('/wp/v2/types', ['context' => 'edit'])['data']));
$say('author asks for page in edit', $get('/wp/v2/types/page', ['context' => 'edit']));
$say('author asks for post in edit', array_keys((array) $get('/wp/v2/types/post', ['context' => 'edit'])['data']));
wp_set_current_user(0);
$say('visitor asks for post in edit', $get('/wp/v2/types/post', ['context' => 'edit']));
$say('visitor list in edit', $get('/wp/v2/types', ['context' => 'edit']));
unregister_post_type('zz_book');
unregister_post_type('zz_note');
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
