<?php
/**
 * The abilities REST controllers as classes (probe abilities-rest-controllers):
 * WP_REST_Abilities_V1_List_Controller, WP_REST_Abilities_V1_Categories_Controller
 * and WP_REST_Abilities_V1_Run_Controller called directly, as a plugin
 * would: their schemas and parameters, the permission checks for a
 * signed-in caller and for nobody, lists and single items with their
 * links, items prepared from ability objects, and a run (the method an
 * ability takes, its input sanitized, its permission, its answer).
 * Registrations live for the request only; it writes nothing.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
add_filter('doing_it_wrong_trigger_error', '__return_false');
$wrong = [];
add_action('doing_it_wrong_run', static function ($function, $message) use (&$wrong): void {
    $wrong[] = [$function, strip_tags((string) $message)];
}, 10, 2);
$schema = ['type' => 'object', 'properties' => ['text' => ['type' => 'string'], 'times' => ['type' => 'integer', 'default' => 1, 'minimum' => 1]], 'additionalProperties' => false];
add_action('wp_abilities_api_categories_init', static function (): void {
    wp_register_ability_category('zz-rest', ['label' => 'Zz Rest', 'description' => 'A probe category.', 'meta' => ['zz' => 1]]);
});
add_action('wp_abilities_api_init', static function () use ($schema): void {
    wp_register_ability('zz/rest-echo', [
        'label' => 'Zz Echo', 'description' => 'Echoes text.', 'category' => 'zz-rest',
        'input_schema' => $schema, 'output_schema' => ['type' => 'string'],
        'execute_callback' => static fn ($input) => str_repeat((string) ($input['text'] ?? ''), (int) ($input['times'] ?? 1)),
        'permission_callback' => static fn () => current_user_can('manage_options'),
        'meta' => ['show_in_rest' => true],
    ]);
    wp_register_ability('zz/rest-read', [
        'label' => 'Zz Read', 'description' => 'Reads.', 'category' => 'zz-rest',
        'execute_callback' => static fn () => 'read', 'permission_callback' => '__return_true',
        'meta' => ['show_in_rest' => true, 'annotations' => ['readonly' => true]],
    ]);
    wp_register_ability('zz/rest-erring', [
        'label' => 'Zz Erring', 'description' => 'Refuses with an error.', 'category' => 'zz-rest',
        'execute_callback' => static fn () => 'never', 'permission_callback' => static fn () => new WP_Error('zz_nope', 'Nope.', ['zz' => 1]),
        'meta' => ['show_in_rest' => true],
    ]);
    wp_register_ability('zz/rest-hidden', [
        'label' => 'Zz Hidden', 'description' => 'Not shown.', 'category' => 'zz-rest',
        'execute_callback' => static fn () => 'hidden', 'permission_callback' => '__return_true',
    ]);
});
$host = (string) parse_url(home_url(), PHP_URL_HOST);
$mask = static function ($value) use (&$mask, $host) {
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    return is_string($value) ? str_replace($host, '{host}', $value) : $value;
};
$answer = static function ($response) use ($mask) {
    if (is_wp_error($response)) {
        return ['error', $response->get_error_code(), $response->get_error_message(), $response->get_error_data()];
    }
    if (!$response instanceof WP_REST_Response) {
        return ['not a response', $response];
    }
    $headers = $response->get_headers();
    ksort($headers);
    return [$response->get_status(), $mask($response->get_data()), $mask($response->get_links()), $mask($headers)];
};
$request = static function (string $method, string $route, array $params = []) {
    $request = new WP_REST_Request($method, $route);
    foreach ($params as $key => $value) {
        $request->set_param($key, $value);
    }
    return $request;
};
$posted = static function (string $route, string $name, $input) {
    $out = new WP_REST_Request('POST', $route);
    $out->set_url_params(['name' => $name]);
    $out->set_header('content-type', 'application/json');
    $out->set_body(wp_json_encode(['input' => $input]));
    return $out;
};
// Called directly, a list needs the defaults dispatch would have given it from the collection parameters.
$listing = static function ($controller, string $route, array $params = []) use ($request) {
    $out = $request('GET', $route, $params);
    $out->set_default_params(array_filter(array_map(static fn ($arg) => $arg['default'] ?? null, $controller->get_collection_params()), static fn ($value) => $value !== null));
    return $out;
};
$ours = static fn (array $data, string $key) => array_values(array_filter($data, static fn ($item) => str_starts_with((string) ($item[$key] ?? ''), 'zz')));

$list = new WP_REST_Abilities_V1_List_Controller();
$categories = new WP_REST_Abilities_V1_Categories_Controller();
$run = new WP_REST_Abilities_V1_Run_Controller();
wp_get_abilities();

$say('schemas', [
    get_parent_class($list), get_parent_class($categories), get_parent_class($run),
    $list->get_item_schema(), $list->get_collection_params(),
    $categories->get_item_schema(), $categories->get_collection_params(),
    $run->get_run_args(), $run->get_run_schema(),
]);

wp_set_current_user(0);
$say('permission, nobody', [
    $answer($list->get_items_permissions_check($request('GET', '/wp-abilities/v1/abilities'))),
    $answer($list->get_item_permissions_check($request('GET', '/wp-abilities/v1/abilities/zz/rest-echo', ['name' => 'zz/rest-echo']))),
    $answer($categories->get_items_permissions_check($request('GET', '/wp-abilities/v1/categories'))),
    $answer($categories->get_item_permissions_check($request('GET', '/wp-abilities/v1/categories/zz-rest', ['slug' => 'zz-rest']))),
    $answer($run->check_ability_permissions($request('POST', '/wp-abilities/v1/abilities/zz/rest-echo/run', ['name' => 'zz/rest-echo', 'input' => ['text' => 'a']]))),
    $answer($run->check_ability_permissions($request('GET', '/wp-abilities/v1/abilities/zz/rest-read/run', ['name' => 'zz/rest-read']))),
    $answer($run->check_ability_permissions($posted('/wp-abilities/v1/abilities/zz/rest-echo/run', 'zz/rest-echo', ['text' => 'a']))),
    $answer($run->check_ability_permissions($posted('/wp-abilities/v1/abilities/zz/rest-echo/run', 'zz/rest-echo', ['times' => 0]))),
    $answer($run->check_ability_permissions($posted('/wp-abilities/v1/abilities/zz/rest-erring/run', 'zz/rest-erring', null))),
]);

wp_set_current_user(1);
$say('permission, an administrator', [
    $answer($list->get_items_permissions_check($request('GET', '/wp-abilities/v1/abilities'))),
    $answer($list->get_item_permissions_check($request('GET', '/wp-abilities/v1/abilities/zz/rest-echo', ['name' => 'zz/rest-echo']))),
    $answer($categories->get_items_permissions_check($request('GET', '/wp-abilities/v1/categories'))),
    $answer($run->check_ability_permissions($request('POST', '/wp-abilities/v1/abilities/zz/rest-echo/run', ['name' => 'zz/rest-echo', 'input' => ['text' => 'a']]))),
    $answer($run->check_ability_permissions($request('POST', '/wp-abilities/v1/abilities/zz/never/run', ['name' => 'zz/never']))),
    $answer($run->check_ability_permissions($request('POST', '/wp-abilities/v1/abilities/zz/rest-hidden/run', ['name' => 'zz/rest-hidden']))),
    $answer($run->check_ability_permissions($posted('/wp-abilities/v1/abilities/zz/rest-echo/run', 'zz/rest-echo', ['text' => 'a']))),
    $answer($run->check_ability_permissions($request('POST', '/wp-abilities/v1/abilities/zz/rest-read/run', ['name' => 'zz/rest-read']))),
    $answer($run->check_ability_permissions($request('GET', '/wp-abilities/v1/abilities/zz/rest-echo/run', ['name' => 'zz/rest-echo', 'input' => ['text' => 'a']]))),
    $answer($run->check_ability_permissions($request('GET', '/wp-abilities/v1/abilities/zz/rest-read/run', ['name' => 'zz/rest-read', 'input' => ['x' => 1]]))),
    $answer($run->check_ability_permissions($posted('/wp-abilities/v1/abilities/zz/rest-erring/run', 'zz/rest-erring', null))),
    $answer($run->execute_ability($posted('/wp-abilities/v1/abilities/zz/rest-erring/run', 'zz/rest-erring', null))),
]);

$items = static function ($response) use ($answer, $ours, $mask) {
    if (!$response instanceof WP_REST_Response) {
        return $answer($response);
    }
    $headers = $response->get_headers();
    ksort($headers);
    $data = $response->get_data();
    return [$response->get_status(), $mask(is_array($data) ? $ours($data, isset($data[0]['slug']) ? 'slug' : 'name') : $data), $mask($headers)];
};
$say('the abilities list', [
    $items($list->get_items($listing($list, '/wp-abilities/v1/abilities'))),
    $items($list->get_items($listing($list, '/wp-abilities/v1/abilities', ['category' => 'zz-rest']))),
    $items($list->get_items($listing($list, '/wp-abilities/v1/abilities', ['category' => 'zz-none']))),
    $items($list->get_items($listing($list, '/wp-abilities/v1/abilities', ['category' => 'zz-rest', 'per_page' => 1, 'page' => 2]))),
    $items($list->get_items($listing($list, '/wp-abilities/v1/abilities', ['category' => 'zz-rest', 'context' => 'embed']))),
    $items($list->get_items($listing($list, '/wp-abilities/v1/abilities', ['namespace' => 'zz']))),
    $items($list->get_items($listing($list, '/wp-abilities/v1/abilities', ['namespace' => 'core']))),
    $items($list->get_items($listing($list, '/wp-abilities/v1/abilities', ['meta' => ['annotations' => ['readonly' => true]]]))),
    $items($list->get_items($listing($list, '/wp-abilities/v1/abilities', ['meta' => ['show_in_rest' => true, 'public' => false], 'category' => 'zz-rest']))),
    $items($list->get_items($listing($list, '/wp-abilities/v1/abilities', ['category' => 'zz-rest', 'page' => 5]))),
    $items($list->get_items($listing($list, '/wp-abilities/v1/abilities', ['category' => 'zz-rest', 'meta' => ['annotations' => ['readonly' => 'true']]]))),
    $items($list->get_items($listing($list, '/wp-abilities/v1/abilities', ['category' => 'zz-rest', 'meta' => ['public' => 0]]))),
    $items($list->get_items($listing($list, '/wp-abilities/v1/abilities', ['category' => 'zz-rest', 'meta' => ['zz-unknown' => false]]))),
]);
$say('one ability', [
    $answer($list->get_item($request('GET', '/wp-abilities/v1/abilities/zz/rest-echo', ['name' => 'zz/rest-echo']))),
    $answer($list->get_item($request('GET', '/wp-abilities/v1/abilities/zz/rest-hidden', ['name' => 'zz/rest-hidden']))),
    $answer($list->get_item($request('GET', '/wp-abilities/v1/abilities/zz/never', ['name' => 'zz/never']))),
    $answer($list->prepare_item_for_response(wp_get_ability('zz/rest-read'), $request('GET', '/wp-abilities/v1/abilities/zz/rest-read'))),
    $answer($list->prepare_item_for_response(wp_get_ability('zz/rest-read'), $request('GET', '/wp-abilities/v1/abilities/zz/rest-read', ['_fields' => 'name,label']))),
    $answer($list->prepare_item_for_response(wp_get_ability('zz/rest-hidden'), $request('GET', '/wp-abilities/v1/abilities/zz/rest-hidden', ['context' => 'embed']))),
]);
$say('the categories', [
    $items($categories->get_items($listing($categories, '/wp-abilities/v1/categories'))),
    $items($categories->get_items($listing($categories, '/wp-abilities/v1/categories', ['per_page' => 1]))),
    $items($categories->get_items($listing($categories, '/wp-abilities/v1/categories', ['per_page' => 1, 'page' => 2]))),
    $answer($categories->get_item($request('GET', '/wp-abilities/v1/categories/zz-rest', ['slug' => 'zz-rest']))),
    $answer($categories->get_item($request('GET', '/wp-abilities/v1/categories/zz-none', ['slug' => 'zz-none']))),
    $answer($categories->prepare_item_for_response(wp_get_ability_category('zz-rest'), $request('GET', '/wp-abilities/v1/categories/zz-rest'))),
    $answer($categories->prepare_item_for_response(wp_get_ability_category('zz-rest'), $request('GET', '/wp-abilities/v1/categories/zz-rest', ['context' => 'embed']))),
]);

$say('the method a run takes', [
    $answer($run->validate_request_method('GET', ['readonly' => true])),
    $answer($run->validate_request_method('POST', ['readonly' => true])),
    $answer($run->validate_request_method('POST', ['readonly' => false])),
    $answer($run->validate_request_method('GET', [])),
    $answer($run->validate_request_method('DELETE', ['readonly' => false, 'destructive' => true])),
    $answer($run->validate_request_method('DELETE', ['readonly' => false, 'destructive' => true, 'idempotent' => true])),
    $answer($run->validate_request_method('DELETE', [])),
    $answer($run->validate_request_method('POST', ['destructive' => true, 'idempotent' => true])),
    $answer($run->validate_request_method('GET', ['destructive' => true, 'idempotent' => true])),
    $answer($run->validate_request_method('DELETE', ['readonly' => true])),
    $answer($run->validate_request_method('PUT', ['readonly' => false])),
]);
$say('input sanitized', [
    $run->sanitize_input_for_ability(['text' => 'a', 'times' => '2'], $request('POST', '/wp-abilities/v1/abilities/zz/rest-echo/run', ['name' => 'zz/rest-echo'])),
    $run->sanitize_input_for_ability(['text' => 'a', 'times' => 'x'], $request('POST', '/wp-abilities/v1/abilities/zz/rest-echo/run', ['name' => 'zz/rest-echo'])),
    $run->sanitize_input_for_ability(null, $request('POST', '/wp-abilities/v1/abilities/zz/rest-echo/run', ['name' => 'zz/rest-echo'])),
    $run->sanitize_input_for_ability('raw', $request('POST', '/wp-abilities/v1/abilities/zz/never/run', ['name' => 'zz/never'])),
    $run->sanitize_input_for_ability(['a' => 1], $request('GET', '/wp-abilities/v1/abilities/zz/rest-read/run', ['name' => 'zz/rest-read'])),
    $run->sanitize_input_for_ability(['text' => 5, 'times' => '2'], $request('POST', '/wp-abilities/v1/abilities/zz/rest-echo/run', ['name' => 'zz/rest-echo'])),
    $run->sanitize_input_for_ability(['text' => 'a', 'extra' => 1], $request('POST', '/wp-abilities/v1/abilities/zz/rest-echo/run', ['name' => 'zz/rest-echo'])),
    $run->sanitize_input_for_ability(['text' => 'a'], $request('POST', '/wp-abilities/v1/abilities/zz/rest-echo/run', ['name' => 'zz/rest-echo'])),
]);
$say('a run', [
    $answer($run->execute_ability($request('POST', '/wp-abilities/v1/abilities/zz/rest-echo/run', ['name' => 'zz/rest-echo', 'input' => ['text' => 'ab', 'times' => 2]]))),
    $answer($run->execute_ability($request('POST', '/wp-abilities/v1/abilities/zz/rest-echo/run', ['name' => 'zz/rest-echo', 'input' => ['times' => 0]]))),
    $answer($run->execute_ability($request('GET', '/wp-abilities/v1/abilities/zz/rest-echo/run', ['name' => 'zz/rest-echo', 'input' => ['text' => 'ab']]))),
    $answer($run->execute_ability($request('GET', '/wp-abilities/v1/abilities/zz/rest-read/run', ['name' => 'zz/rest-read']))),
    $answer($run->execute_ability($request('POST', '/wp-abilities/v1/abilities/zz/rest-read/run', ['name' => 'zz/rest-read']))),
    $answer($run->execute_ability($request('POST', '/wp-abilities/v1/abilities/zz/never/run', ['name' => 'zz/never']))),
    $answer($run->execute_ability($request('POST', '/wp-abilities/v1/abilities/zz/rest-hidden/run', ['name' => 'zz/rest-hidden']))),
    $answer($run->execute_ability($posted('/wp-abilities/v1/abilities/zz/rest-echo/run', 'zz/rest-echo', ['text' => 'cd', 'times' => 2]))),
    $answer($run->execute_ability($posted('/wp-abilities/v1/abilities/zz/rest-echo/run', 'zz/rest-echo', ['times' => 0]))),
]);
$describe = static function ($value) use (&$describe) {
    if ($value instanceof Closure) {
        return 'closure';
    }
    if (is_array($value) && count($value) === 2 && is_object($value[0] ?? null) && is_string($value[1] ?? null)) {
        return [get_class($value[0]), $value[1]];
    }
    return is_array($value) ? array_map($describe, $value) : $value;
};
$server = rest_get_server();
$routes = $server->get_routes('wp-abilities/v1');
ksort($routes);
$say('the routes registered', [
    array_map($describe, $routes),
    array_map(static fn ($route) => $describe($server->get_route_options($route)), array_combine(array_keys($routes), array_keys($routes))),
]);
wp_set_current_user(0);
$say('notices', $wrong);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
