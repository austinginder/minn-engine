<?php
/**
 * WP_REST_Posts_Controller, read side (probe rest-posts-controller): a
 * controller's base, namespace and meta per type; the routes it registers;
 * its item schema per type (which fields a type's supports, visibility,
 * hierarchy and taxonomies bring; callbacks named) and its schema links;
 * its collection parameters; the permission checks and available actions
 * for a visitor and an administrator; the small helpers (dates, the
 * protected title, the query, statuses, passwords); and a post read (view,
 * edit, embed, fields) and listed through it.
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
// Callbacks named rather than encoded: a method by its name, a closure as a closure.
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
$request = static function (string $route, array $params = []) {
    $request = new WP_REST_Request('GET', $route);
    foreach ($params as $key => $value) {
        $request->set_param($key, $value);
    }
    return $request;
};
wp_set_current_user(0);
// The server is up before the probe's types exist, so only the controller registers their routes.
rest_get_server();

register_post_type('zzrpc', ['public' => true, 'show_in_rest' => true, 'hierarchical' => true, 'rest_base' => 'zz-rpcs', 'supports' => ['title', 'editor', 'author', 'excerpt', 'thumbnail', 'comments', 'custom-fields', 'page-attributes', 'post-formats'], 'taxonomies' => ['category', 'post_tag']]);
register_post_type('zzrpcns', ['public' => false, 'publicly_queryable' => true, 'show_in_rest' => true, 'rest_namespace' => 'zz/v1', 'supports' => ['title']]);
register_post_type('zzrpcbare', ['public' => true, 'show_in_rest' => true, 'supports' => false, 'taxonomies' => ['category']]);
register_post_meta('zzrpc', 'zz_rpc_key', ['show_in_rest' => true, 'single' => true, 'type' => 'string', 'description' => 'A probe key.']);

$controllers = [];
foreach (['post', 'page', 'wp_block', 'wp_navigation', 'zzrpc', 'zzrpcns', 'zzrpcbare'] as $type) {
    $controllers[$type] = new WP_REST_Posts_Controller($type);
}
$say('controllers', array_map(static fn ($controller) => ['namespace' => $prop($controller, 'namespace'), 'rest_base' => $prop($controller, 'rest_base'), 'post_type' => $prop($controller, 'post_type'), 'meta' => is_object($prop($controller, 'meta')) ? get_class($prop($controller, 'meta')) : null, 'allow_batch' => $prop($controller, 'allow_batch'), 'password_check_passed' => $prop($controller, 'password_check_passed')], $controllers));

$controllers['zzrpc']->register_routes();
$controllers['zzrpcns']->register_routes();
$routes = rest_get_server()->get_routes();
$route = static fn (array $handlers) => array_map(static fn ($handler) => is_array($handler) ? $named(array_diff_key($handler, ['args' => true])) + ['args' => array_keys((array) ($handler['args'] ?? []))] : $handler, $handlers);
$say('routes registered', [
    'collection' => isset($routes['/wp/v2/zz-rpcs']) ? $route($routes['/wp/v2/zz-rpcs']) : null,
    'item' => isset($routes['/wp/v2/zz-rpcs/(?P<id>[\d]+)']) ? $route($routes['/wp/v2/zz-rpcs/(?P<id>[\d]+)']) : null,
    'item args' => isset($routes['/wp/v2/zz-rpcs/(?P<id>[\d]+)']) ? $named(array_map(static fn ($handler) => $handler['args'] ?? null, $routes['/wp/v2/zz-rpcs/(?P<id>[\d]+)'])) : null,
    'another namespace' => isset($routes['/zz/v1/zzrpcns']) ? $route($routes['/zz/v1/zzrpcns']) : null,
]);

$say('item schemas', array_map(static fn ($controller) => $named($controller->get_item_schema()), $controllers));
// The filter reads a whole post schema (a partial one fatals on the reference).
$say('a navigation schema opened to embedding', $named(WP_Navigation_Fallback::update_wp_navigation_post_schema($controllers['post']->get_item_schema())));
$say('schema links', array_map(static fn ($controller) => $call($controller, 'get_schema_links'), $controllers));
$say('collection parameters', array_map(static fn ($controller) => $named($controller->get_collection_params()), $controllers));

$post = $controllers['post'];
$hello = get_post(1);
$checks = static function () use ($post, $call, $hello, $request, $shown, $controllers) {
    return [
        'items' => $shown($post->get_items_permissions_check($request('/wp/v2/posts'))),
        'items, edit' => $shown($post->get_items_permissions_check($request('/wp/v2/posts', ['context' => 'edit']))),
        'item' => $shown($post->get_item_permissions_check($request('/wp/v2/posts/1', ['id' => 1]))),
        'item, edit' => $shown($post->get_item_permissions_check($request('/wp/v2/posts/1', ['id' => 1, 'context' => 'edit']))),
        'item, missing' => $shown($post->get_item_permissions_check($request('/wp/v2/posts/999999', ['id' => 999999]))),
        'read' => $call($post, 'check_read_permission', $hello),
        'update' => $call($post, 'check_update_permission', $hello),
        'create' => $call($post, 'check_create_permission', $hello),
        'delete' => $call($post, 'check_delete_permission', $hello),
        'actions' => $call($post, 'get_available_actions', $hello, $request('/wp/v2/posts/1', ['context' => 'edit'])),
        'actions, view' => $call($post, 'get_available_actions', $hello, $request('/wp/v2/posts/1')),
        'actions on a page' => $call($controllers['page'], 'get_available_actions', $hello, $request('/wp/v2/pages/1', ['context' => 'edit'])),
    ];
};
$visitor = $checks();
wp_set_current_user(1);
$administrator = $checks();
wp_set_current_user(0);
$say('permissions', ['a visitor' => $visitor, 'an administrator' => $administrator]);

$statuses = $request('/wp/v2/posts');
$statuses->set_attributes(['args' => $post->get_collection_params()]);
$editStatuses = $request('/wp/v2/posts', ['context' => 'edit']);
$editStatuses->set_attributes(['args' => $post->get_collection_params()]);
$say('helpers', [
    'dates' => [$call($post, 'prepare_date_response', '2024-01-02 03:04:05'), $call($post, 'prepare_date_response', '0000-00-00 00:00:00'), $call($post, 'prepare_date_response', '2024-01-02 03:04:05', '2024-01-05 06:07:08'), $call($post, 'prepare_date_response', '0000-00-00 00:00:00', '2024-01-05 06:07:08'), $call($post, 'prepare_date_response', null)],
    'protected title' => $post->protected_title_format(),
    'query' => [$call($post, 'prepare_items_query', ['posts_per_page' => 5, 'zz' => 1, 's' => 'x'], $request('/wp/v2/posts')), $call($post, 'prepare_items_query', ['orderby' => 'include_slugs', 'post_name__in' => ['a']], $request('/wp/v2/posts', ['orderby' => 'include_slugs'])), $call($post, 'prepare_items_query')],
    'statuses' => [$shown($post->sanitize_post_statuses('publish', $statuses, 'status')), $shown($post->sanitize_post_statuses('publish,draft', $statuses, 'status')), $shown($post->sanitize_post_statuses(['private'], $editStatuses, 'status')), $shown($post->sanitize_post_statuses(['zz'], $statuses, 'status'))],
    'type allowed' => [$call($post, 'check_is_post_type_allowed', get_post_type_object('post')), $call($post, 'check_is_post_type_allowed', get_post_type_object('zzrpcns')), $call($post, 'check_is_post_type_allowed', get_post_type_object('nav_menu_item')), $call($post, 'check_is_post_type_allowed', 'post'), $call($post, 'check_is_post_type_allowed', null)],
    'posts' => [get_class($call($post, 'get_post', 1)), $shown($call($post, 'get_post', 999999)), $shown($call($post, 'get_post', 0)), $shown($call($post, 'get_post', -3)), $shown($call($controllers['page'], 'get_post', 1))],
    'passwords' => [$post->check_password_required(true, $hello), $post->check_password_required(false, $hello), $post->can_access_password_content($hello, $request('/wp/v2/posts/1'))],
]);

$item = static fn (array $params) => $shown($post->get_item($request('/wp/v2/posts/1', ['id' => 1] + $params)));
$prepared = static fn (array $params) => $shown($post->prepare_item_for_response($hello, $request('/wp/v2/posts/1', $params)));
$reads = [
    'a post' => $item([]),
    'a post, embedded' => $item(['context' => 'embed']),
    'some fields' => $item(['_fields' => 'id,title,_links']),
    'fields, nested' => $item(['_fields' => 'title.rendered,content']),
    'a missing post' => $shown($post->get_item($request('/wp/v2/posts/999999', ['id' => 999999]))),
    'prepared' => $prepared([]),
    'prepared, embedded' => $prepared(['context' => 'embed']),
    'prepared, fields without links' => $prepared(['_fields' => 'id,slug']),
    'links' => $call($post, 'prepare_links', $hello),
];
wp_set_current_user(1);
$reads['a post, edited'] = $item(['context' => 'edit']);
$reads['prepared, edited'] = $prepared(['context' => 'edit']);
wp_set_current_user(0);
$say('reading', $reads);

// The list's defaults set as dispatching would set them.
$defaults = array_filter(array_map(static fn ($arg) => $arg['default'] ?? null, $post->get_collection_params()), static fn ($value) => $value !== null);
$listed = static function (array $params) use ($post, $request, $shown, $defaults) {
    $list = $request('/wp/v2/posts', $params + ['per_page' => 2]);
    $list->set_default_params($defaults);
    return $shown($post->get_items($list));
};
$say('listing', [
    'a page of posts' => $listed([]),
    'the second page' => $listed(['page' => 2]),
    'some fields' => $listed(['_fields' => 'id,slug']),
    'a search' => $listed(['search' => 'hello']),
    'a page too far' => $listed(['page' => 999]),
]);

// The container class suffix is engine-defined (contracts/blocks.md), neutralised as the suites do.
echo preg_replace('/(wp-container-core-[a-z-]+-is-layout-)[0-9a-f]{8}/', '$1HASH', json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)), "\n";
