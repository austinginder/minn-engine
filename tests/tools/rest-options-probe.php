<?php
/**
 * OPTIONS requests to routes a plugin registered: the route's description in
 * the help context (namespace, methods, endpoints and arguments, the schema
 * when the route declares one, the self link for a route without
 * parameters), answered by rest_handle_options_request on rest_pre_dispatch.
 * A HEAD request is served by a route's GET handler. The engine's own routes
 * are not probed here. Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
foreach (['home_url', 'site_url', 'option_home', 'option_siteurl'] as $devHook) {
    remove_all_filters($devHook);
}
$register = static function (): void {
    register_rest_route('minn-probe/v1', '/thing', [
        ['methods' => 'GET', 'callback' => static fn () => ['ok' => 1], 'permission_callback' => '__return_true', 'args' => ['x' => ['type' => 'integer', 'description' => 'X', 'default' => 3], 'y' => ['type' => 'string', 'enum' => ['a', 'b'], 'required' => true]]],
        ['methods' => 'POST', 'callback' => static fn () => ['ok' => 2], 'permission_callback' => '__return_true'],
        'schema' => static fn () => ['$schema' => 'http://json-schema.org/draft-04/schema#', 'title' => 'thing', 'type' => 'object', 'properties' => ['ok' => ['type' => 'integer']]],
    ]);
    register_rest_route('minn-probe/v1', '/plain', ['methods' => 'GET', 'callback' => static fn () => ['plain' => true], 'permission_callback' => '__return_true']);
    register_rest_route('minn-probe/v1', '/post-only', ['methods' => 'POST', 'callback' => static fn () => ['posted' => true], 'permission_callback' => '__return_true']);
    register_rest_route('minn-probe/v1', '/thing/(?P<id>\d+)', ['methods' => 'GET, DELETE', 'callback' => static fn ($r) => ['id' => (int) $r['id']], 'permission_callback' => '__return_true', 'args' => ['id' => ['type' => 'integer']]]);
};
if (did_action('rest_api_init')) {
    $register();
} else {
    add_action('rest_api_init', $register);
}
$server = rest_get_server();
if (!did_action('rest_api_init')) {
    do_action('rest_api_init', $server);
}
$answer = static function (string $method, string $route) use ($server): array {
    $response = $server->dispatch(new WP_REST_Request($method, $route));
    $data = $response->get_data();
    if (isset($data['_links']['self'][0]['href'])) {
        $data['_links']['self'][0]['href'] = (string) preg_replace('#^https?://[^/]+#', '{home}', $data['_links']['self'][0]['href']);
    }
    return [$response->get_status(), $data, $response->get_matched_route()];
};
$say('options with a schema', $answer('OPTIONS', '/minn-probe/v1/thing'));
$say('options without a schema', $answer('OPTIONS', '/minn-probe/v1/plain'));
$say('options with a parameter', $answer('OPTIONS', '/minn-probe/v1/thing/7'));
$say('options on no route', $answer('OPTIONS', '/minn-probe/v1/none'));
$say('get passes through', $answer('GET', '/minn-probe/v1/plain'));
$headers = static function (string $method, string $route) use ($server): array {
    $response = $server->dispatch(new WP_REST_Request($method, $route));
    return [$response->get_status(), $response->get_data(), $response->get_matched_route()];
};
$say('head on a get route', $headers('HEAD', '/minn-probe/v1/plain'));
$say('head on a get and delete route', $headers('HEAD', '/minn-probe/v1/thing/7'));
$say('head on a post-only route', $headers('HEAD', '/minn-probe/v1/post-only'));
remove_filter('rest_pre_dispatch', 'rest_handle_options_request', 10);
$say('options with the handler removed', $answer('OPTIONS', '/minn-probe/v1/plain')[0]);
add_filter('rest_pre_dispatch', 'rest_handle_options_request', 10, 3);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
