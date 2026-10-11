<?php
/**
 * WP_REST_Terms_Controller (probe rest-terms-controller): a controller's
 * taxonomy, base and meta; the routes it registers; its schema and
 * collection parameters per taxonomy; the permission checks for a visitor,
 * a contributor and an administrator; terms read (view, edit, embed,
 * fields) and listed through it; the links and the database fields a
 * request makes; and a category and a tag created, updated and deleted.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
add_filter('doing_it_wrong_trigger_error', '__return_false');
if (!did_action('init') && class_exists(Minn\Runtime\Plugins::class)) {
    Minn\Runtime\Plugins::load(Minn\Runtime\Runtime::current());
}
$try = static function (Closure $run) {
    try {
        return $run();
    } catch (Throwable $e) {
        return ['threw' => get_class($e)];
    }
};
$call = static fn (object $object, string $method, ...$args) => $try(static fn () => Closure::bind(fn (...$given) => $this->{$method}(...$given), $object, get_class($object))(...$args));
$prop = static fn (object $object, string $name) => Closure::bind(fn () => $this->{$name} ?? null, $object, get_class($object))();
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

// Terms and users made here go on the way out.
$made = ['terms' => [], 'users' => []];
register_shutdown_function(static function () use (&$made): void {
    wp_set_current_user(1);
    foreach ($made['terms'] as [$id, $taxonomy]) {
        if (is_int($id) && $id > 1) {
            wp_delete_term($id, $taxonomy);
        }
    }
    foreach ($made['users'] as $id) {
        if (is_int($id) && $id > 1) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
            wp_delete_user($id);
        }
    }
});
$contributor = wp_insert_user(['user_login' => 'zz_rtc_contributor', 'user_pass' => wp_generate_password(), 'user_email' => 'zz-rtc@example.com', 'role' => 'contributor']);
if (!is_int($contributor) || $contributor <= 1) {
    fwrite(STDERR, "no contributor\n");
    exit(1);
}
$made['users'][] = $contributor;
$parent = wp_insert_term('ZZ rtc parent', 'category', ['slug' => 'zz-rtc-parent', 'description' => 'A <b>parent</b> category.']);
$child = is_array($parent) ? wp_insert_term('ZZ rtc child', 'category', ['slug' => 'zz-rtc-child', 'parent' => $parent['term_id']]) : null;
$tag = wp_insert_term('ZZ rtc tag', 'post_tag', ['slug' => 'zz-rtc-tag']);
foreach ([[$parent, 'category'], [$child, 'category'], [$tag, 'post_tag']] as [$made_term, $taxonomy]) {
    if (!is_array($made_term) || !is_int($made_term['term_id']) || $made_term['term_id'] <= 1) {
        fwrite(STDERR, "no fixture term\n");
        exit(1);
    }
    $made['terms'][] = [$made_term['term_id'], $taxonomy];
}
$parent = $parent['term_id'];
$child = $child['term_id'];
$tag = $tag['term_id'];
$ids = [$parent, $child, $tag];
$masked = static function ($value) use (&$ids) {
    rsort($ids);
    $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    foreach ($ids as $index => $id) {
        $json = preg_replace('/(?<=[:\[,])' . $id . '(?=[,\]}])/', '"{id' . $index . '}"', $json);
        $json = preg_replace('/(?<![0-9])' . $id . '(?![0-9])/', '{id' . $index . '}', $json);
    }
    return json_decode($json, true);
};

$controllers = [];
foreach (['category', 'post_tag', 'wp_pattern_category', 'nav_menu', 'post_format'] as $taxonomy) {
    $controllers[$taxonomy] = new WP_REST_Terms_Controller($taxonomy);
}
$say('controllers', array_map(static fn ($controller) => ['namespace' => $prop($controller, 'namespace'), 'rest_base' => $prop($controller, 'rest_base'), 'taxonomy' => $prop($controller, 'taxonomy'), 'meta' => is_object($prop($controller, 'meta')) ? get_class($prop($controller, 'meta')) : null, 'sort_column' => $prop($controller, 'sort_column'), 'total_terms' => $prop($controller, 'total_terms'), 'allow_batch' => $prop($controller, 'allow_batch')], $controllers));
$say('schemas', array_map(static fn ($controller) => $named($controller->get_item_schema()), $controllers));
$say('collection parameters', array_map(static fn ($controller) => $named($controller->get_collection_params()), $controllers));

register_taxonomy('zzrtcs', 'post', ['public' => true, 'show_in_rest' => true, 'hierarchical' => true, 'rest_base' => 'zz-rtcs']);
(new WP_REST_Terms_Controller('zzrtcs'))->register_routes();
$routes = rest_get_server()->get_routes();
$route = static fn (array $handlers) => array_map(static fn ($handler) => is_array($handler) ? $named(array_diff_key($handler, ['args' => true])) + ['args' => array_keys((array) ($handler['args'] ?? []))] : $handler, $handlers);
$say('routes registered', array_map(static fn ($pattern) => isset($routes[$pattern]) ? $route($routes[$pattern]) : null, ['terms' => '/wp/v2/zz-rtcs', 'a term' => '/wp/v2/zz-rtcs/(?P<id>[\d]+)']));

$categories = $controllers['category'];
$tags = $controllers['post_tag'];
$checks = static function () use ($categories, $tags, $controllers, $call, $shown, $request, $parent, $tag) {
    return [
        'list' => $shown($categories->get_items_permissions_check($request('GET', '/wp/v2/categories'))),
        'list, edit' => $shown($categories->get_items_permissions_check($request('GET', '/wp/v2/categories', ['context' => 'edit']))),
        'list, a post\'s' => $shown($categories->get_items_permissions_check($request('GET', '/wp/v2/categories', ['post' => 1]))),
        'list, a missing post\'s' => $shown($categories->get_items_permissions_check($request('GET', '/wp/v2/categories', ['post' => 999999]))),
        'one' => $shown($categories->get_item_permissions_check($request('GET', '/wp/v2/categories/' . $parent, ['id' => $parent]))),
        'one, edit' => $shown($categories->get_item_permissions_check($request('GET', '/wp/v2/categories/' . $parent, ['id' => $parent, 'context' => 'edit']))),
        'one, missing' => $shown($categories->get_item_permissions_check($request('GET', '/wp/v2/categories/999999', ['id' => 999999]))),
        'one, a tag as a category' => $shown($categories->get_item_permissions_check($request('GET', '/wp/v2/categories/' . $tag, ['id' => $tag]))),
        'create a category' => $shown($categories->create_item_permissions_check($request('POST', '/wp/v2/categories', ['name' => 'x']))),
        'create a tag' => $shown($tags->create_item_permissions_check($request('POST', '/wp/v2/tags', ['name' => 'x']))),
        'create a format' => $shown($controllers['post_format']->create_item_permissions_check($request('POST', '/wp/v2/post_format', ['name' => 'x']))),
        'update' => $shown($categories->update_item_permissions_check($request('PUT', '/wp/v2/categories/' . $parent, ['id' => $parent]))),
        'update, missing' => $shown($categories->update_item_permissions_check($request('PUT', '/wp/v2/categories/999999', ['id' => 999999]))),
        'delete' => $shown($categories->delete_item_permissions_check($request('DELETE', '/wp/v2/categories/' . $parent, ['id' => $parent]))),
        'delete the default category' => $shown($categories->delete_item_permissions_check($request('DELETE', '/wp/v2/categories/1', ['id' => 1]))),
        'a post\'s terms' => [$categories->check_read_terms_permission_for_post(get_post(1), $request('GET', '/wp/v2/categories', ['post' => 1]))],
        'taxonomies allowed' => [$call($categories, 'check_is_taxonomy_allowed', 'category'), $call($categories, 'check_is_taxonomy_allowed', 'nav_menu'), $call($categories, 'check_is_taxonomy_allowed', 'zz-none'), $call($categories, 'check_is_taxonomy_allowed', 'link_category')],
    ];
};
$visitor = $checks();
wp_set_current_user($contributor);
$asContributor = $checks();
wp_set_current_user(1);
$administrator = $checks();
wp_set_current_user(0);
$say('permissions', $masked(['a visitor' => $visitor, 'a contributor' => $asContributor, 'an administrator' => $administrator]));

$one = static fn (array $params, int $id = 0) => $shown($categories->get_item($request('GET', '/wp/v2/categories/' . ($id ?: $parent), ['id' => $id ?: $parent] + $params)));
$reads = [
    'a category' => $one([]),
    'embedded' => $one(['context' => 'embed']),
    'fields' => $one(['_fields' => 'id,name,_links']),
    'fields without links' => $one(['_fields' => 'id,parent']),
    'a child' => $one([], $child),
    'missing' => $one([], 999999),
    'a tag' => $shown($tags->get_item($request('GET', '/wp/v2/tags/' . $tag, ['id' => $tag]))),
    'prepared' => $shown($categories->prepare_item_for_response(get_term($parent, 'category'), $request('GET', '/wp/v2/categories'))),
    'links' => $call($categories, 'prepare_links', get_term($child, 'category')),
    'links of a tag' => $call($tags, 'prepare_links', get_term($tag, 'post_tag')),
    'got' => [get_class($call($categories, 'get_term', $parent)), $shown($call($categories, 'get_term', 999999)), $shown($call($categories, 'get_term', 0)), $shown($call($categories, 'get_term', $tag))],
];
wp_set_current_user(1);
$reads['edited'] = $one(['context' => 'edit']);
wp_set_current_user(0);
$say('reading', $masked($reads));

$defaults = static fn ($controller) => array_filter(array_map(static fn ($arg) => $arg['default'] ?? null, $controller->get_collection_params()), static fn ($value) => $value !== null);
$listed = static fn (array $params, $controller = null, string $route = '/wp/v2/categories') => $shown(($controller ?? $categories)->get_items($request('GET', $route, $params, $defaults($controller ?? $categories))));
$say('listing', $masked([
    'ours' => $listed(['include' => [$parent, $child], 'hide_empty' => false]),
    'children' => $listed(['parent' => $parent, 'hide_empty' => false]),
    'a page' => $listed(['include' => [$parent, $child], 'hide_empty' => false, 'per_page' => 1, 'page' => 2]),
    'by slug' => $listed(['slug' => ['zz-rtc-child'], 'hide_empty' => false]),
    'a search' => $listed(['search' => 'ZZ rtc', 'hide_empty' => false]),
    'by name, ascending' => $listed(['include' => [$parent, $child], 'hide_empty' => false, 'orderby' => 'name', 'order' => 'asc']),
    'a post\'s' => $listed(['post' => 1]),
    'empty ones hidden' => $listed(['include' => [$parent, $child], 'hide_empty' => true]),
    'tags' => $listed(['include' => [$tag], 'hide_empty' => false], $tags, '/wp/v2/tags'),
    'some fields' => $listed(['include' => [$parent], 'hide_empty' => false, '_fields' => 'id,name']),
]));

wp_set_current_user(1);
$prepared = static fn (array $params, string $method = 'POST', $controller = null) => $try(static function () use ($params, $method, $controller, $categories, $call, $request, $parent) {
    $result = $call($controller ?? $categories, 'prepare_item_for_database', $request($method, '/wp/v2/categories' . ($method === 'POST' ? '' : '/' . $parent), $params + ($method === 'POST' ? [] : ['id' => $parent])));
    return is_wp_error($result) ? ['error' => $result->get_error_code()] : (array) $result;
});
$say('prepared for the database', $masked([
    'nothing' => $prepared([]),
    'fields' => $prepared(['name' => 'N', 'description' => 'D', 'slug' => 's', 'parent' => $parent]),
    'fields, another order' => $prepared(['parent' => 0, 'slug' => 's', 'description' => 'D', 'name' => 'N']),
    'an update' => $prepared(['name' => 'N2'], 'PUT'),
    'a missing parent' => $prepared(['parent' => 999999]),
    'a parent for a tag' => $prepared(['parent' => $parent], 'POST', $tags),
]));

$life = [];
$created = $categories->create_item($request('POST', '/wp/v2/categories', ['name' => 'ZZ rtc created', 'description' => 'made', 'parent' => $parent]));
$createdId = $created instanceof WP_REST_Response ? (int) ($created->get_data()['id'] ?? 0) : 0;
if ($createdId > 1) {
    $made['terms'][] = [$createdId, 'category'];
    $ids[] = $createdId;
}
$life['created'] = $shown($created);
$life['created again'] = $shown($categories->create_item($request('POST', '/wp/v2/categories', ['name' => 'ZZ rtc created', 'parent' => $parent])));
$life['created, a missing parent'] = $shown($categories->create_item($request('POST', '/wp/v2/categories', ['name' => 'ZZ rtc orphan', 'parent' => 999999])));
$life['updated'] = $shown($categories->update_item($request('PUT', '/wp/v2/categories/' . $createdId, ['id' => $createdId, 'name' => 'ZZ rtc renamed', 'slug' => 'zz-rtc-renamed'])));
$life['updated, a missing one'] = $shown($categories->update_item($request('PUT', '/wp/v2/categories/999999', ['id' => 999999, 'name' => 'x'])));
$life['deleted, not forced'] = $shown($categories->delete_item($request('DELETE', '/wp/v2/categories/' . $createdId, ['id' => $createdId])));
$life['deleted'] = $shown($categories->delete_item($request('DELETE', '/wp/v2/categories/' . $createdId, ['id' => $createdId, 'force' => true])));
$life['gone'] = get_term($createdId, 'category') === null;
$madeTag = $tags->create_item($request('POST', '/wp/v2/tags', ['name' => 'ZZ rtc made tag']));
$madeTagId = $madeTag instanceof WP_REST_Response ? (int) ($madeTag->get_data()['id'] ?? 0) : 0;
if ($madeTagId > 1) {
    $made['terms'][] = [$madeTagId, 'post_tag'];
    $ids[] = $madeTagId;
}
$life['a tag created'] = $shown($madeTag);
$life['a tag deleted'] = $shown($tags->delete_item($request('DELETE', '/wp/v2/tags/' . $madeTagId, ['id' => $madeTagId, 'force' => true])));
wp_set_current_user(0);
$say('a term through its life', $masked($life));

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
