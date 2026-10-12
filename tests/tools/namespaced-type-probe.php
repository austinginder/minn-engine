<?php
/**
 * A post type under a REST namespace of its own (probe namespaced-type):
 * created, listed, read, read for editing, updated and deleted at its own
 * routes, its wp/v2 twin absent, and its type object naming the namespace.
 * The post's id is shown as whether it has one. What it makes it deletes.
 */
register_post_type('zz_ns_type', ['public' => true, 'show_in_rest' => true, 'rest_namespace' => 'zz-ns/v1', 'rest_base' => 'things', 'supports' => ['title', 'editor', 'author']]);
$ask = static function (string $method, string $route, array $params = [], int $user = 1) {
    wp_set_current_user($user);
    $r = new WP_REST_Request($method, $route);
    foreach ($params as $k => $v) { $r->set_param($k, $v); }
    $res = rest_do_request($r);
    $d = $res->get_data();
    $keep = static fn ($x) => is_array($x) ? ['id' => isset($x['id']) ? is_int($x['id']) && $x['id'] > 1 : null] + array_intersect_key($x, array_flip(['type', 'status', 'title', 'code', 'message', 'slug', 'link'])) : $x;
    return [$res->get_status(), is_array($d) && array_is_list($d) ? array_map($keep, $d) : $keep($d), array_intersect_key($res->get_headers(), array_flip(['X-WP-Total', 'X-WP-TotalPages']))];
};
$out = [];
$created = $ask('POST', '/zz-ns/v1/things', ['title' => 'zz-ns-one', 'status' => 'publish', 'content' => 'x']);
$out[] = ['create', $created];
$id = is_array($created[1]) ? (int) ($created[1]['id'] ?? 0) : 0;
$out[] = ['list', $ask('GET', '/zz-ns/v1/things', [], 0)];
$out[] = ['one', $id > 0 ? $ask('GET', "/zz-ns/v1/things/{$id}", [], 0) : null];
$out[] = ['edit context', $id > 0 ? $ask('GET', "/zz-ns/v1/things/{$id}", ['context' => 'edit']) : null];
$out[] = ['update', $id > 0 ? $ask('POST', "/zz-ns/v1/things/{$id}", ['title' => 'zz-ns-two']) : null];
$out[] = ['wp/v2 route', $ask('GET', '/wp/v2/things', [], 0)];
$out[] = ['type object', (static function () { $r = rest_do_request(new WP_REST_Request('GET', '/wp/v2/types/zz_ns_type')); return array_intersect_key((array) $r->get_data(), array_flip(['rest_base', 'rest_namespace', '_links'])); })()];
$out[] = ['delete', $id > 0 ? $ask('DELETE', "/zz-ns/v1/things/{$id}", ['force' => true]) : null];
foreach (get_posts(['post_type' => 'zz_ns_type', 'post_status' => 'any', 'numberposts' => 10]) as $p) {
    if ($p->ID > 1) {
        wp_delete_post($p->ID, true);
    }
}
$home = (string) parse_url(home_url(), PHP_URL_HOST);
echo str_replace($home, '{host}', json_encode($out, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)), "\n";
