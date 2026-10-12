<?php
// OPTIONS on core routes: what the description carries, schema included.
$out = [];
foreach (['/wp/v2/posts', '/wp/v2/posts/1', '/wp/v2/pages', '/wp/v2/users', '/wp/v2/categories', '/wp/v2/comments', '/wp/v2/media', '/wp/v2/settings', '/wp/v2/types', '/wp/v2/block-types', '/wp/v2/menu-items', '/wp/v2/templates', '/wp/v2/global-styles/themes/twentytwentyfive', '/wp/v2/search', '/wp/v2'] as $route) {
    wp_set_current_user(0);
    $res = rest_do_request(new WP_REST_Request('OPTIONS', $route));
    $d = (array) $res->get_data();
    $schema = $d['schema'] ?? null;
    $out[] = [$route, $res->get_status(), array_keys($d), is_array($schema) ? ['title' => $schema['title'] ?? null, 'properties' => count((array) ($schema['properties'] ?? [])), 'md5' => md5(json_encode($schema))] : null];
}
$server = rest_get_server();
$routes = $server->get_routes();
$out[] = ['facade routes has /wp/v2/posts', isset($routes['/wp/v2/posts']), isset($routes['/wp/v2/posts']['schema'])];
echo json_encode($out, JSON_UNESCAPED_SLASHES), "\n";
