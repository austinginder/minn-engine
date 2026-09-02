<?php
/**
 * Plugin Name: Minn Test Gate
 * Description: A fixture for tests/rest-gate.test.php: what plugin code may decide about REST requests before and beside the engine's own routes.
 * Version: 1.0.0
 */

// A plugin route under the wp/v2 namespace: the engine's declared-types catch-all must not shadow it.
add_action('rest_api_init', static function (): void {
    register_rest_route('wp/v2', '/gate-probe', [
        'methods' => 'GET',
        'callback' => static fn () => ['probe' => 'wp/v2 route from a plugin'],
        'permission_callback' => '__return_true',
    ]);
    // An in-process call to a core route runs as the outer request's user.
    register_rest_route('wp/v2', '/gate-probe/me', [
        'methods' => 'GET',
        'callback' => static function () {
            $response = rest_do_request(new WP_REST_Request('GET', '/wp/v2/users/me'));
            $data = $response->get_data();
            return ['status' => $response->get_status(), 'id' => is_array($data) ? ($data['id'] ?? null) : null];
        },
        'permission_callback' => '__return_true',
    ]);
});

// "Disable the REST API for anonymous visitors" plugins do exactly this, on every route.
add_filter('rest_authentication_errors', static function ($result) {
    if (!empty($_GET['minn_gate_refuse'])) {
        return new WP_Error('minn_gate_refused', 'Refused by the gate fixture.', ['status' => 401]);
    }
    return $result;
});

// A pre-dispatch answer replaces the route's own, on every route.
add_filter('rest_pre_dispatch', static function ($result, $server, $request) {
    if (!empty($_GET['minn_gate_pre'])) {
        return new WP_REST_Response(['pre' => true, 'route' => $request->get_route()], 200);
    }
    return $result;
}, 10, 3);

// A removed endpoint is no route, on the engine's own routes too.
add_filter('rest_endpoints', static function (array $endpoints): array {
    unset($endpoints['/wp/v2/tags']);
    return $endpoints;
});
