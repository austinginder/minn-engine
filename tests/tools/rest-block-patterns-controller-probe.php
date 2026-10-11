<?php
/**
 * The block patterns controller class (probe rest-block-patterns-controller):
 * its base, routes and schema; who may read the registered patterns; a
 * pattern's categories migrated to their current names; patterns shaped;
 * and the registered patterns listed (the remote ones stay off: they would
 * ask wordpress.org).
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
add_filter('doing_it_wrong_trigger_error', '__return_false');
add_filter('should_load_remote_block_patterns', '__return_false');
if (!did_action('init') && class_exists(Minn\Runtime\Plugins::class)) {
    Minn\Runtime\Plugins::load(Minn\Runtime\Runtime::current());
}
require_once ABSPATH . 'wp-admin/includes/user.php';
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
$shown = static fn ($response) => is_wp_error($response) ? ['error' => $response->get_error_code(), 'message' => $response->get_error_message(), 'data' => $response->get_error_data()] : ($response instanceof WP_REST_Response ? ['status' => $response->get_status(), 'data' => $response->get_data(), 'links' => $response->get_links()] : $response);
$request = static function (string $method, string $route, array $params = []) {
    $request = new WP_REST_Request($method, $route);
    foreach ($params as $key => $value) {
        $request->set_param($key, $value);
    }
    return $request;
};
wp_set_current_user(0);
rest_get_server();

$made = [];
register_shutdown_function(static function () use (&$made): void {
    foreach ($made as $id) {
        if (is_int($id) && $id > 1) {
            wp_delete_user($id);
        }
    }
    foreach (['zz-rbpc/full', 'zz-rbpc/plain'] as $pattern) {
        if (WP_Block_Patterns_Registry::get_instance()->is_registered($pattern)) {
            unregister_block_pattern($pattern);
        }
    }
});
foreach (['subscriber', 'contributor'] as $role) {
    $id = wp_insert_user(['user_login' => "zz_rbpc_{$role}", 'user_pass' => wp_generate_password(), 'user_email' => "zz-rbpc-{$role}@example.com", 'role' => $role]);
    if (!is_int($id) || $id <= 1) {
        fwrite(STDERR, "no fixture\n");
        exit(1);
    }
    $made[$role] = $id;
}
register_block_pattern('zz-rbpc/full', ['title' => 'ZZ full', 'content' => '<!-- wp:paragraph --><p>ZZ full</p><!-- /wp:paragraph -->', 'description' => 'A full pattern', 'viewportWidth' => 800, 'inserter' => false, 'categories' => ['buttons', 'call-to-action', 'columns', 'zz-own'], 'keywords' => ['zz', 'full'], 'blockTypes' => ['core/paragraph'], 'postTypes' => ['page'], 'templateTypes' => ['single'], 'source' => 'plugin']);
register_block_pattern('zz-rbpc/plain', ['title' => 'ZZ plain', 'content' => '<p>plain</p>']);

$controller = new WP_REST_Block_Patterns_Controller();
$say('the controller', Closure::bind(fn () => [$this->namespace, $this->rest_base, self::$categories_migration], $controller, WP_REST_Block_Patterns_Controller::class)());
$routes = (static function () use ($named): array {
    $server = $GLOBALS['wp_rest_server'];
    $GLOBALS['wp_rest_server'] = new WP_REST_Server();
    (new WP_REST_Block_Patterns_Controller())->register_routes();
    $found = [];
    foreach ($GLOBALS['wp_rest_server']->get_routes('wp/v2') as $route => $endpoints) {
        if (!str_starts_with($route, '/wp/v2/block-patterns')) {
            continue;
        }
        foreach ($endpoints as $endpoint) {
            $found[$route][] = ['methods' => array_keys($endpoint['methods']), 'callback' => $named($endpoint['callback']), 'permission' => $named($endpoint['permission_callback'] ?? null), 'args' => $named($endpoint['args'])];
        }
    }
    $GLOBALS['wp_rest_server'] = $server;
    return $found;
})();
$say('routes', $routes);
$say('schema', $named($controller->get_item_schema()));

$checks = static fn () => [$shown($controller->get_items_permissions_check($request('GET', '/wp/v2/block-patterns/patterns')))];
$visitor = $checks();
$asUsers = [];
foreach ($made as $role => $id) {
    wp_set_current_user($id);
    $asUsers[$role] = $checks();
}
wp_set_current_user(1);
$say('permissions', ['a visitor' => $visitor] + $asUsers + ['an administrator' => $checks()]);

$say('categories migrated', array_map(static fn ($pattern) => $call($controller, 'migrate_pattern_categories', $pattern), [
    'old names' => ['name' => 'a', 'categories' => ['buttons', 'columns', 'query']],
    'old and new' => ['name' => 'b', 'categories' => ['buttons', 'call-to-action', 'text']],
    'current names' => ['name' => 'c', 'categories' => ['featured', 'header']],
    'none' => ['name' => 'd'],
    'not a list' => ['name' => 'e', 'categories' => 'buttons'],
    'keyed' => ['name' => 'f', 'categories' => ['x' => 'buttons', 'y' => 'zz']],
]));

$registry = WP_Block_Patterns_Registry::get_instance();
$say('shaped', [
    'full' => $shown($controller->prepare_item_for_response($registry->get_registered('zz-rbpc/full'), $request('GET', '/wp/v2/block-patterns/patterns'))),
    'full, fields' => $shown($controller->prepare_item_for_response($registry->get_registered('zz-rbpc/full'), $request('GET', '/wp/v2/block-patterns/patterns', ['_fields' => 'name,title,categories']))),
    'full, embedded' => $shown($controller->prepare_item_for_response($registry->get_registered('zz-rbpc/full'), $request('GET', '/wp/v2/block-patterns/patterns', ['context' => 'embed']))),
    'plain' => $shown($controller->prepare_item_for_response($registry->get_registered('zz-rbpc/plain'), $request('GET', '/wp/v2/block-patterns/patterns'))),
    'migrated' => $shown($controller->prepare_item_for_response($call($controller, 'migrate_pattern_categories', $registry->get_registered('zz-rbpc/full')), $request('GET', '/wp/v2/block-patterns/patterns'))),
]);

$listed = $controller->get_items($request('GET', '/wp/v2/block-patterns/patterns'));
$items = $listed instanceof WP_REST_Response ? (array) $listed->get_data() : [];
$say('listed', [
    'status' => $listed instanceof WP_REST_Response ? $listed->get_status() : $shown($listed),
    'names' => array_map(static fn ($item) => [$item['name'] ?? null, $item['categories'] ?? null, $item['source'] ?? null, isset($item['_links'])], $items),
    'ours' => array_values(array_filter($items, static fn ($item) => str_starts_with((string) ($item['name'] ?? ''), 'zz-rbpc/'))),
    'registered names' => array_map(static fn ($pattern) => $pattern['name'], $registry->get_all_registered()),
]);
wp_set_current_user(0);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
