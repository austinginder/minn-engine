<?php
/**
 * rest-api.php's remaining helpers (probe rest-api-helpers): schema
 * comparison and combining (oneOf, anyOf, pattern properties, multiple
 * types), sanitizing arrays, objects and hex colours, a route's request
 * arguments validated, sanitized and parsed, a response filtered by
 * context, fields included, the URL prefix and requests made from strings,
 * avatar sizes and URLs, the route a queried object answers to, preloading,
 * the cookie check, CORS and OPTIONS answers, the index's application
 * password note, and the init helpers. Nothing is stored.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$host = (string) parse_url(home_url(), PHP_URL_HOST);
$mask = static function ($value) use ($host, &$mask) {
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    return is_string($value) ? (string) preg_replace('#https?://' . preg_quote($host, '#') . '#', '//{host}', $value) : $value;
};
$error = static fn ($value) => is_wp_error($value) ? ['WP_Error', $value->get_error_code(), $value->get_error_message(), $value->get_error_data()] : $value;

$say('values compared', [
    rest_are_values_equal(1, 1), rest_are_values_equal(1, '1'), rest_are_values_equal(1, 1.0), rest_are_values_equal('a', 'a'), rest_are_values_equal(true, 1),
    rest_are_values_equal(['a' => 1, 'b' => 2], ['b' => 2, 'a' => 1]), rest_are_values_equal([1, 2], [2, 1]), rest_are_values_equal(['a' => [1, 2]], ['a' => [1, 2]]),
    rest_are_values_equal(['a' => 1], ['a' => 1, 'b' => 2]), rest_are_values_equal(null, null), rest_are_values_equal(null, false), rest_are_values_equal([], []), rest_are_values_equal(0.1 + 0.2, 0.3),
]);

$say('additional properties turned off', [
    rest_default_additional_properties_to_false(['type' => 'object', 'properties' => ['a' => ['type' => 'object', 'properties' => ['b' => ['type' => 'string']]], 'c' => ['type' => 'array', 'items' => ['type' => 'object']]]]),
    rest_default_additional_properties_to_false(['type' => 'object', 'additionalProperties' => true]),
    rest_default_additional_properties_to_false(['type' => ['object', 'null'], 'properties' => []]),
    rest_default_additional_properties_to_false(['type' => 'string']),
    rest_default_additional_properties_to_false(['anyOf' => [['type' => 'object'], ['type' => 'string']]]),
]);

$say('pattern properties found', [
    rest_find_matching_pattern_property_schema('zz_one', ['patternProperties' => ['^zz_' => ['type' => 'integer'], '^yy' => ['type' => 'string']]]),
    rest_find_matching_pattern_property_schema('nope', ['patternProperties' => ['^zz_' => ['type' => 'integer']]]),
    rest_find_matching_pattern_property_schema('abc', ['patternProperties' => ['b' => ['type' => 'boolean']]]),
    rest_find_matching_pattern_property_schema('x', []),
]);

$oneOf = ['oneOf' => [['type' => 'integer', 'minimum' => 5, 'title' => 'Big'], ['type' => 'string', 'enum' => ['a', 'b']]]];
$anyOf = ['anyOf' => [['type' => 'integer'], ['type' => 'string', 'format' => 'email'], ['type' => 'object', 'properties' => ['x' => ['type' => 'boolean']], 'required' => ['x']]]];
$say('oneOf and anyOf matched', array_map($error, [
    rest_find_one_matching_schema(7, $oneOf, 'zz'), rest_find_one_matching_schema('a', $oneOf, 'zz'), rest_find_one_matching_schema(2, $oneOf, 'zz'), rest_find_one_matching_schema(true, $oneOf, 'zz'),
    rest_find_one_matching_schema(5, ['oneOf' => [['type' => 'integer'], ['type' => 'number']]], 'zz'),
    rest_find_one_matching_schema(5, ['oneOf' => [['type' => 'integer'], ['type' => 'number']]], 'zz', true),
    rest_find_any_matching_schema(3, $anyOf, 'zz'), rest_find_any_matching_schema('a@b.example', $anyOf, 'zz'), rest_find_any_matching_schema('nope', $anyOf, 'zz'), rest_find_any_matching_schema(['x' => 'no'], $anyOf, 'zz'),
    rest_get_combining_operation_error('v', 'zz', [['schema' => ['type' => 'integer'], 'error_object' => new WP_Error('rest_invalid_type', 'zz is not of type integer.', ['param' => 'zz']), 'index' => 0]]),
    rest_get_combining_operation_error('v', 'zz', [['schema' => ['type' => 'integer', 'title' => 'Number'], 'error_object' => new WP_Error('a', 'A'), 'index' => 0]]),
    rest_get_combining_operation_error('v', 'zz', [['schema' => ['type' => 'integer', 'title' => 'Number'], 'error_object' => new WP_Error('a', 'A'), 'index' => 0], ['schema' => ['type' => 'string'], 'error_object' => new WP_Error('b', 'B'), 'index' => 1]]),
    rest_get_combining_operation_error('v', 'zz', [['schema' => ['type' => 'integer'], 'error_object' => new WP_Error('a', 'A'), 'index' => 0], ['schema' => ['type' => 'integer'], 'error_object' => new WP_Error('b', 'B'), 'index' => 1]]),
]));

$say('multiple types', [
    rest_handle_multi_type_schema('5', ['type' => ['integer', 'string']], 'zz'), rest_handle_multi_type_schema(5, ['type' => ['string', 'integer']], 'zz'),
    rest_handle_multi_type_schema('true', ['type' => ['boolean', 'string']], 'zz'), rest_handle_multi_type_schema([1], ['type' => ['object', 'array']], 'zz'),
    rest_handle_multi_type_schema('x', ['type' => ['integer', 'boolean']], 'zz'), rest_handle_multi_type_schema(null, ['type' => ['null', 'string']], 'zz'),
    rest_handle_multi_type_schema(1.5, ['type' => ['integer', 'number']], 'zz'), rest_handle_multi_type_schema('x', ['type' => ['zz-unknown', 'string']], 'zz'),
]);

$say('arrays, objects and colours sanitized', [
    array_map(static fn ($value) => rest_sanitize_array($value), ['a,b, c', ['x', 'y'], ['k' => 'v'], '', null, 5, (object) ['a' => 1], [0 => 'a', 2 => 'b']]),
    array_map(static fn ($value) => $error(rest_sanitize_object($value)), ['', null, ['a' => 1], (object) ['b' => 2], 'string', 5, [1, 2], new ArrayObject(['c' => 3])]),
    array_map(static fn ($value) => rest_parse_hex_color($value), ['#fff', '#ffffff', 'fff', '#FFF', '#ggg', '#ffff', '#12345', ' #fff', '#fff ', '', '#0a0B0c']),
]);

register_rest_route('zz-probe/v1', '/args', ['methods' => 'POST', 'callback' => '__return_null', 'permission_callback' => '__return_true', 'args' => [
    'count' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 10],
    'tags' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => ['a', 'b']]],
    'color' => ['type' => 'string', 'format' => 'hex-color'],
    'flag' => ['type' => 'boolean'],
    'mixed' => ['type' => ['integer', 'string']],
    'plain' => ['description' => 'No type'],
]]);
$say('request arguments', (static function () use ($error) {
    $request = new WP_REST_Request('POST', '/zz-probe/v1/args');
    $route = rest_get_server()->get_routes()['/zz-probe/v1/args'][0] ?? [];
    $request->set_attributes(['args' => $route['args'] ?? []]);
    $out = [];
    foreach ([['count', '5'], ['count', '50'], ['count', 'x'], ['tags', 'a,b'], ['tags', ['a', 'z']], ['color', '#abc'], ['color', 'blue'], ['flag', 'false'], ['flag', 'maybe'], ['mixed', '7'], ['plain', ['any']], ['missing', 'x']] as [$param, $value]) {
        $out[] = [$param, $value, $error(rest_validate_request_arg($value, $request, $param)), $error(rest_sanitize_request_arg($value, $request, $param)), $error(rest_parse_request_arg($value, $request, $param))];
    }
    return $out;
})());

$schema = ['type' => 'object', 'properties' => [
    'id' => ['type' => 'integer', 'context' => ['view', 'edit', 'embed']],
    'secret' => ['type' => 'string', 'context' => ['edit']],
    'meta' => ['type' => 'object', 'context' => ['view', 'edit'], 'properties' => ['a' => ['type' => 'string', 'context' => ['edit']], 'b' => ['type' => 'string', 'context' => ['view', 'edit']]]],
    'list' => ['type' => 'array', 'context' => ['view', 'edit'], 'items' => ['type' => 'object', 'properties' => ['x' => ['type' => 'string', 'context' => ['edit']], 'y' => ['type' => 'string', 'context' => ['view']]]]],
    'open' => ['type' => 'object', 'context' => ['view'], 'additionalProperties' => ['type' => 'string', 'context' => ['edit']]],
]];
$data = ['id' => 1, 'secret' => 's', 'meta' => ['a' => 'A', 'b' => 'B'], 'list' => [['x' => 1, 'y' => 2]], 'open' => ['k' => 'v'], 'extra' => 'e'];
$say('responses filtered by context', [rest_filter_response_by_context($data, $schema, 'view'), rest_filter_response_by_context($data, $schema, 'embed'), rest_filter_response_by_context($data, $schema, 'edit'), rest_filter_response_by_context([$data], ['type' => 'array', 'items' => $schema], 'embed')]);

$say('endpoint arguments from a schema', [
    rest_get_endpoint_args_for_schema(['type' => 'object', 'properties' => ['title' => ['type' => 'string', 'required' => true, 'description' => 'T', 'default' => 'x', 'arg_options' => ['sanitize_callback' => 'zz_cb']], 'id' => ['type' => 'integer', 'readonly' => true], 'meta' => ['type' => 'object', 'properties' => []]]], WP_REST_Server::CREATABLE),
    rest_get_endpoint_args_for_schema(['type' => 'object', 'properties' => ['title' => ['type' => 'string', 'required' => true, 'default' => 'x']]], WP_REST_Server::EDITABLE),
    rest_get_endpoint_args_for_schema([]),
]);

$say('fields included', [
    rest_is_field_included('id', ['id', 'title']), rest_is_field_included('title.raw', ['title']), rest_is_field_included('title', ['title.raw']), rest_is_field_included('meta.a.b', ['meta.a']),
    rest_is_field_included('meta', ['meta.a.b']), rest_is_field_included('content', ['id']), rest_is_field_included('a', []), rest_is_field_included('title.raw', ['title.rendered']),
]);

$say('the prefix, requests and avatars', $mask((static function () {
    $out = [rest_get_url_prefix()];
    add_filter('rest_url_prefix', $prefix = static fn () => 'zz-api');
    $out[] = rest_get_url_prefix();
    remove_filter('rest_url_prefix', $prefix);
    foreach (['/wp/v2/posts', '/wp/v2/posts?per_page=2&_fields=id', ['method' => 'GET'], 'https://zz.example/wp-json/wp/v2/posts', home_url('/wp-json/wp/v2/types?context=edit')] as $given) {
        $request = rest_ensure_request($given);
        $out[] = [get_class($request), $request->get_method(), $request->get_route(), $request->get_query_params()];
    }
    $existing = new WP_REST_Request('PUT', '/zz');
    $out[] = rest_ensure_request($existing) === $existing;
    $out[] = rest_get_avatar_sizes();
    add_filter('rest_avatar_sizes', $sizes = static fn () => [32]);
    $out[] = rest_get_avatar_sizes();
    remove_filter('rest_avatar_sizes', $sizes);
    $out[] = array_keys(rest_get_avatar_urls(1));
    $out[] = array_map(static fn ($url) => (string) preg_replace('/[0-9a-f]{64}/', '{hash}', $url), rest_get_avatar_urls('zz@example.invalid'));
    $out[] = rest_get_avatar_urls(999999999);
    return $out;
})()));

$say('the route a queried object answers to', (static function () {
    $out = [];
    $post = get_posts(['numberposts' => 1, 'post_type' => 'post', 'fields' => 'ids'])[0] ?? 0;
    $page = get_posts(['numberposts' => 1, 'post_type' => 'page', 'fields' => 'ids'])[0] ?? 0;
    $routes = static fn () => (string) preg_replace('#/\d+$#', '/{id}', rest_get_queried_resource_route());
    foreach ([['p' => $post], ['page_id' => $page], ['cat' => 1], ['author' => 1], ['post_type' => 'post'], ['s' => 'zz'], ['year' => 2000]] as $vars) {
        query_posts($vars);
        $out[] = [array_keys($vars)[0], $routes()];
        wp_reset_query();
    }
    return $out;
})());

$say('preloading', $mask((static function () {
    $memo = array_reduce(['/wp/v2/types/post', '/wp/v2/posts?per_page=1&_fields=id', ['/zz-probe/v1/args', 'OPTIONS'], '/wp/v2/zz-none', '', 'zz'], static fn ($memo, $path) => rest_preload_api_request($memo, $path), []);
    return array_map(static fn ($entry) => is_array($entry) && isset($entry['body']) ? ['body keys' => is_array($entry['body']) ? array_keys($entry['body']) : gettype($entry['body']), 'headers' => isset($entry['headers']) ? array_keys($entry['headers']) : null] : (is_array($entry) ? array_map(static fn ($method) => array_keys((array) ($method['body'] ?? [])), $entry) : $entry), $memo);
})()));

$say('the cookie check, CORS and OPTIONS', $mask((static function () use ($error) {
    global $wp_rest_auth_cookie;
    $out = [];
    wp_set_current_user(0);
    $saved = $wp_rest_auth_cookie;
    $wp_rest_auth_cookie = null;
    $out[] = $error(rest_cookie_check_errors(null));
    $out[] = $error(rest_cookie_check_errors(true));
    $out[] = $error(rest_cookie_check_errors(new WP_Error('zz', 'Z')));
    rest_cookie_collect_status();
    $out[] = $wp_rest_auth_cookie;
    $wp_rest_auth_cookie = $saved;
    $out[] = [rest_send_cors_headers('passed through'), rest_send_cors_headers(null)];
    // A plugin's route: the engine describes its own routes itself (an OPTIONS answer with no schema yet, owed).
    $request = new WP_REST_Request('OPTIONS', '/zz-probe/v1/args');
    $answer = rest_handle_options_request(null, rest_get_server(), $request);
    $out[] = $answer instanceof WP_REST_Response ? [$answer->get_status(), array_keys((array) $answer->get_data()), $answer->get_matched_route()] : $answer;
    $out[] = rest_handle_options_request('kept', rest_get_server(), $request);
    $out[] = $error(rest_handle_options_request(null, rest_get_server(), new WP_REST_Request('OPTIONS', '/zz-none')));
    return $out;
})()));

$say('the index and init helpers', $mask((static function () {
    // Available here by a filter of the probe's own, as a test site turns them on over http.
    add_filter('wp_is_application_passwords_available', '__return_true');
    $index = new WP_REST_Response(['name' => 'Zz', 'authentication' => []]);
    $noted = rest_add_application_passwords_to_index($index);
    remove_filter('wp_is_application_passwords_available', '__return_true');
    $out = [$noted->get_data()['authentication'] ?? null];
    add_filter('wp_is_application_passwords_available', '__return_false');
    $out[] = rest_add_application_passwords_to_index(new WP_REST_Response(['authentication' => []]))->get_data();
    remove_filter('wp_is_application_passwords_available', '__return_false');
    $out[] = [wp_is_rest_endpoint(), has_filter('rest_pre_serve_request', 'rest_send_cors_headers'), has_filter('rest_post_dispatch', 'rest_send_allow_header'), has_filter('rest_pre_dispatch', 'rest_handle_options_request'), has_filter('rest_authentication_errors', 'rest_cookie_check_errors')];
    global $wp_rewrite, $wp;
    $out[] = in_array('rest_route', $wp->public_query_vars, true);
    return $out;
})()));

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
