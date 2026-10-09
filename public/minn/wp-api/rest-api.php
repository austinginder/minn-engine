<?php
/** The REST route API over WP_REST_Server. Behaviour from contracts/fixtures/api/rest.json. */

use Minn\Rest\Fields;
use Minn\Rest\Schema;
use Minn\Rest\SchemaValues;
use Minn\Runtime\Refusal;
use Minn\Runtime\Runtime;
use Minn\Rest\RouteArgs;

function rest_get_server()
{
    $server = $GLOBALS['wp_rest_server'] ?? null;
    if (!$server instanceof WP_REST_Server) {
        $class = apply_filters('wp_rest_server_class', 'WP_REST_Server');
        $server = new $class();
        $GLOBALS['wp_rest_server'] = $server;
        do_action('rest_api_init', $server);
    }
    return $server;
}

function rest_api_init()
{
    rest_api_register_rewrites();
    do_action('rest_api_init', rest_get_server());
}

function rest_api_register_rewrites()
{
}

/**
 * The REST API's own filters, added when the server starts, after every
 * plugin has hooked in: a plugin's rest_pre_dispatch callback at the same
 * priority runs first (ACF's returns nothing there, which would otherwise
 * swallow the OPTIONS answer).
 */
function rest_api_default_filters()
{
    add_filter('rest_pre_serve_request', 'rest_send_cors_headers');
    add_filter('rest_post_dispatch', 'rest_send_allow_header', 10, 3);
    add_filter('rest_post_dispatch', 'rest_filter_response_fields', 10, 3);
    add_filter('rest_pre_dispatch', 'rest_handle_options_request', 10, 3);
    add_filter('rest_index', 'rest_add_application_passwords_to_index');
}

function register_rest_route($route_namespace, $route, $args = [], $override = false)
{
    if (empty($route_namespace)) {
        _doing_it_wrong('register_rest_route', 'Routes must be namespaced with plugin or theme name and version.', '4.4.0');
        return false;
    }
    if (empty($route)) {
        _doing_it_wrong('register_rest_route', 'Route must be specified.', '4.4.0');
        return false;
    }
    $clean_namespace = trim((string) $route_namespace, '/');
    if ($clean_namespace !== $route_namespace) {
        _doing_it_wrong('register_rest_route', 'Namespace must not start or end with a slash.', '5.4.2');
    }
    [$args, $missingPermission] = RouteArgs::normalise((array) $args);
    if ($missingPermission) {
        _doing_it_wrong('register_rest_route', 'The REST API route definition is missing the required permission_callback argument.', '5.5.0');
    }
    rest_get_server()->register_route($clean_namespace, '/' . $clean_namespace . '/' . trim((string) $route, '/'), $args, $override);
    return true;
}

function register_rest_field($object_type, $attribute, $args = [])
{
    $defaults = ['get_callback' => null, 'update_callback' => null, 'schema' => null];
    $args = wp_parse_args($args, $defaults);
    foreach ((array) $object_type as $type) {
        $GLOBALS['wp_rest_additional_fields'][$type][$attribute] = $args;
    }
}

function rest_do_request($request)
{
    $request = rest_ensure_request($request);
    return rest_get_server()->dispatch($request);
}

function rest_ensure_request($request)
{
    if ($request instanceof WP_REST_Request) {
        return $request;
    }
    if (is_string($request)) {
        return new WP_REST_Request('GET', $request);
    }
    return new WP_REST_Request('GET', '', $request);
}

function rest_ensure_response($response)
{
    if (is_wp_error($response)) {
        return $response;
    }
    if ($response instanceof WP_REST_Response) {
        return $response;
    }
    if ($response instanceof WP_HTTP_Response) {
        return new WP_REST_Response($response->get_data(), $response->get_status(), $response->get_headers());
    }
    return new WP_REST_Response($response);
}

function rest_convert_error_to_response($error)
{
    $status = array_reduce($error->get_all_error_data(), static fn ($status, $error_data) => is_array($error_data) && isset($error_data['status']) ? $error_data['status'] : $status, 500);
    $errors = _minn_rest_error_entries($error);
    $data = $errors[0];
    if (count($errors) > 1) {
        array_shift($errors);
        $data['additional_errors'] = $errors;
    }
    return new WP_REST_Response($data, $status);
}

/** @internal each message of a WP_Error as a REST error entry: code, message, the code's last data, and its earlier data when there is any */
function _minn_rest_error_entries(WP_Error $error): array
{
    $entries = [];
    foreach ((array) $error->errors as $code => $messages) {
        $all_data = $error->get_all_error_data($code);
        $last_data = array_pop($all_data);
        foreach ((array) $messages as $message) {
            $entries[] = ['code' => $code, 'message' => $message, 'data' => $last_data] + ($all_data ? ['additional_data' => $all_data] : []);
        }
    }
    return $entries;
}

/**
 * An OPTIONS request to a route a plugin registered is answered with the
 * route's description in the help context (namespace, methods, endpoints with
 * their arguments, the schema, the self link), and one no route matches with
 * an empty description. The engine answers its own routes itself, the index
 * and the namespace indexes among them, so those pass through.
 */
function rest_handle_options_request($response, $handler, $request)
{
    if (!empty($response) || !$request instanceof WP_REST_Request || $request->get_method() !== 'OPTIONS' || !$handler instanceof WP_REST_Server) {
        return $response;
    }
    $found = Minn\Rest\RouteMatch::route($handler->get_namespaces(), static fn (string $namespace) => $handler->get_routes($namespace), (string) $request->get_route());
    $path = (string) $request->get_route();
    if (($found !== null && $handler->engine_route($found['route'])) || ($found === null && ($path === '/' || in_array(trim($path, '/'), $handler->get_namespaces(), true)))) {
        return $response;
    }
    // A path no route matches is still answered, with nothing to describe.
    if ($found === null) {
        return new WP_REST_Response([]);
    }
    $answer = new WP_REST_Response($handler->get_data_for_route($found['route'], $found['handlers'], 'help'));
    $answer->set_matched_route($found['route']);
    return $answer;
}

function rest_send_cors_headers($value)
{
    return $value;
}

/**
 * The cookie sign-in judged for a REST request (probe rest-api-helpers): a
 * result already reached stands; a signed-in user who did not sign in by
 * cookie is left alone; otherwise no nonce means no cookie sign-in (the
 * user becomes 0 and the request goes on), a bad one is refused, a good
 * one lets it through.
 */
function rest_cookie_check_errors($result)
{
    if (!empty($result)) {
        return $result;
    }
    if ($GLOBALS['wp_rest_auth_cookie'] !== true && is_user_logged_in()) {
        return $result;
    }
    $nonce = $_REQUEST['_wpnonce'] ?? $_SERVER['HTTP_X_WP_NONCE'] ?? null;
    if ($nonce === null) {
        wp_set_current_user(0);
        return true;
    }
    if (!wp_verify_nonce($nonce, 'wp_rest')) {
        return new WP_Error('rest_cookie_invalid_nonce', __('Cookie check failed'), ['status' => 403]);
    }
    return true;
}

function rest_get_avatar_urls($id_or_email)
{
    $out = [];
    foreach ([24, 48, 96] as $size) {
        $out[$size] = get_avatar_url($id_or_email, ['size' => $size]);
    }
    return $out;
}

function rest_get_avatar_sizes()
{
    return apply_filters('rest_avatar_sizes', [24, 48, 96]);
}

function rest_parse_date($date, $force_utc = false)
{
    return SchemaValues::parseDate($date, (bool) $force_utc);
}

function rest_parse_hex_color($color)
{
    return SchemaValues::parseHexColor($color);
}

function rest_get_date_with_gmt($date, $is_utc = false)
{
    $has_timezone = preg_match('#(Z|[+-]\d{2}(:\d{2})?)$#', (string) $date);
    if ($is_utc || $has_timezone) {
        $date = rest_parse_date($date, true);
        if ($date === false) {
            return null;
        }
        $utc = gmdate('Y-m-d H:i:s', $date);
        $local = get_date_from_gmt($utc);
        return [$local, $utc];
    }
    $date = rest_parse_date($date);
    if ($date === false) {
        return null;
    }
    $local = gmdate('Y-m-d H:i:s', $date);
    $utc = get_gmt_from_date($local);
    return [$local, $utc];
}

/** @internal the schema handler; the closures carry the reference's filters into it */
function _minn_schema(): Schema
{
    static $schema = null;
    return $schema ??= new Schema(
        static fn (string $email): bool => (bool) is_email($email),
        static fn (int|float $n): string => (string) number_format_i18n($n),
        static fn (string $format, mixed $value): mixed => match ($format) {
            'hex-color' => (string) sanitize_hex_color($value),
            'email' => sanitize_email($value),
            'uri' => sanitize_url($value),
            'textarea-field' => sanitize_textarea_field($value),
            default => sanitize_text_field($value),
        },
    );
}

/** @internal true, or the WP_Error a refusal reads as */
function _minn_schema_result(true|Refusal $result): bool|WP_Error
{
    return $result === true ? true : new WP_Error($result->code, $result->message, $result->data);
}

function rest_is_boolean($maybe_bool)
{
    return SchemaValues::isBoolean($maybe_bool);
}

function rest_is_integer($maybe_integer)
{
    return SchemaValues::isInteger($maybe_integer);
}

function rest_is_array($maybe_array)
{
    return SchemaValues::isArray($maybe_array);
}

function rest_sanitize_array($maybe_array)
{
    return SchemaValues::toArray($maybe_array);
}

function rest_is_object($maybe_object)
{
    return SchemaValues::isObject($maybe_object);
}

function rest_sanitize_object($maybe_object)
{
    return SchemaValues::toObject($maybe_object);
}

function rest_stabilize_value($value)
{
    return SchemaValues::stabilize($value);
}

function rest_get_best_type_for_value($value, $types)
{
    return SchemaValues::bestType($value, $types);
}

function rest_handle_multi_type_schema($value, $args, $param = '')
{
    return rest_get_best_type_for_value($value, $args['type']);
}

function rest_validate_value_from_schema($value, $args, $param = '')
{
    return _minn_schema_result(_minn_schema()->validate($value, (array) $args, (string) $param));
}

function rest_are_values_equal($value1, $value2)
{
    return SchemaValues::valuesEqual($value1, $value2);
}

function rest_find_matching_pattern_property_schema($property, $args)
{
    return SchemaValues::patternProperty((string) $property, (array) $args);
}

function rest_sanitize_value_from_schema($value, $args, $param = '')
{
    return _minn_schema()->sanitize($value, (array) $args, (string) $param);
}

function rest_validate_request_arg($value, $request, $param)
{
    $attributes = $request->get_attributes();
    if (!isset($attributes['args'][$param]) || !is_array($attributes['args'][$param])) {
        return true;
    }
    $args = $attributes['args'][$param];
    if (!isset($args['type'])) {
        // A route argument without a schema type is accepted as given (observed on the reference).
        return true;
    }
    return rest_validate_value_from_schema($value, $args, $param);
}

function rest_sanitize_request_arg($value, $request, $param)
{
    $attributes = $request->get_attributes();
    if (!isset($attributes['args'][$param]) || !is_array($attributes['args'][$param])) {
        return $value;
    }
    $args = $attributes['args'][$param];
    return rest_sanitize_value_from_schema($value, $args, $param);
}

function rest_parse_request_arg($value, $request, $param)
{
    $is_valid = rest_validate_request_arg($value, $request, $param);
    if (is_wp_error($is_valid)) {
        return $is_valid;
    }
    return rest_sanitize_request_arg($value, $request, $param);
}

function rest_get_route_for_post($post)
{
    $post = get_post($post);
    if (!$post instanceof WP_Post) {
        return '';
    }
    $post_type_route = rest_get_route_for_post_type_items($post->post_type);
    if (!$post_type_route) {
        return '';
    }
    $route = sprintf('%s/%d', $post_type_route, $post->ID);
    return apply_filters('rest_route_for_post', $route, $post);
}

function rest_get_route_for_post_type_items($post_type)
{
    $post_type = get_post_type_object($post_type);
    if (!$post_type || !$post_type->show_in_rest) {
        return '';
    }
    $namespace = !empty($post_type->rest_namespace) ? $post_type->rest_namespace : 'wp/v2';
    $rest_base = !empty($post_type->rest_base) ? $post_type->rest_base : $post_type->name;
    $route = sprintf('/%s/%s', $namespace, $rest_base);
    return apply_filters('rest_route_for_post_type_items', $route, $post_type);
}

function rest_get_route_for_term($term)
{
    $term = get_term($term);
    if (!$term instanceof WP_Term) {
        return '';
    }
    $taxonomy_route = rest_get_route_for_taxonomy_items($term->taxonomy);
    if (!$taxonomy_route) {
        return '';
    }
    $route = sprintf('%s/%d', $taxonomy_route, $term->term_id);
    return apply_filters('rest_route_for_term', $route, $term);
}

function rest_get_route_for_taxonomy_items($taxonomy)
{
    $taxonomy = get_taxonomy($taxonomy);
    if (!$taxonomy || !$taxonomy->show_in_rest) {
        return '';
    }
    $namespace = !empty($taxonomy->rest_namespace) ? $taxonomy->rest_namespace : 'wp/v2';
    $rest_base = !empty($taxonomy->rest_base) ? $taxonomy->rest_base : $taxonomy->name;
    $route = sprintf('/%s/%s', $namespace, $rest_base);
    return apply_filters('rest_route_for_taxonomy_items', $route, $taxonomy);
}

function rest_get_queried_resource_route()
{
    if (is_singular()) {
        $route = rest_get_route_for_post(get_queried_object());
    } elseif (is_category() || is_tag() || is_tax()) {
        $route = rest_get_route_for_term(get_queried_object());
    } elseif (is_author()) {
        $route = '/wp/v2/users/' . get_queried_object_id();
    } else {
        $route = '';
    }
    return apply_filters('rest_queried_resource_route', $route);
}

function rest_get_allowed_schema_keywords()
{
    return Schema::KEYWORDS;
}

/** The JSON schema keywords a profile keeps for a client: draft-04's whole set, the REST API's for any other (probe json-schema). */
function wp_get_json_schema_allowed_keywords(string $schema_profile = 'rest-api'): array
{
    return Minn\Rest\ClientSchema::keywords($schema_profile);
}

/** A schema trimmed to a profile's keywords for a client (Rest\ClientSchema). */
function wp_prepare_json_schema_for_client(array $schema, string $schema_profile = 'draft-04'): array
{
    return _wp_prepare_json_schema_for_client_with_allowed_keywords($schema, array_flip(wp_get_json_schema_allowed_keywords($schema_profile)));
}

/** A schema trimmed to the keywords given as keys (Rest\ClientSchema). */
function _wp_prepare_json_schema_for_client_with_allowed_keywords(array $schema, array $allowed_keywords): array
{
    return Minn\Rest\ClientSchema::trim($schema, $allowed_keywords);
}

function rest_get_endpoint_args_for_schema($schema, $method = WP_REST_Server::CREATABLE)
{
    return Schema::endpointArgs((array) $schema, $method === WP_REST_Server::CREATABLE);
}

function rest_filter_response_by_context($response_data, $schema, $context)
{
    return _minn_schema()->filterByContext($response_data, (array) $schema, (string) $context);
}

/** The first anyOf schema the value satisfies, else one refusal made of what each said (probe rest-api-helpers). */
function rest_find_any_matching_schema($value, $args, $param)
{
    $errors = [];
    foreach ((array) ($args['anyOf'] ?? []) as $index => $schema) {
        if (!isset($schema['type']) && isset($args['type'])) {
            $schema['type'] = $args['type'];
        }
        $result = rest_validate_value_from_schema($value, $schema, $param);
        if (!is_wp_error($result)) {
            return $schema;
        }
        $errors[] = ['error_object' => $result, 'schema' => $schema, 'index' => $index];
    }
    return rest_get_combining_operation_error($value, $param, $errors);
}

/** Whether a field is asked for: named, or a parent or a child of it named (probe rest-api-helpers). */
function rest_is_field_included($field, $fields)
{
    if (in_array($field, $fields, true)) {
        return true;
    }
    foreach ($fields as $accepted_field) {
        if (str_starts_with($accepted_field, "$field.") || str_starts_with((string) $field, "$accepted_field.")) {
            return true;
        }
    }
    return false;
}

function rest_default_additional_properties_to_false($schema)
{
    return Schema::closeObjects((array) $schema);
}

/** An answer cut to the request's _fields (dot paths descend), each item of a list on its own; an error is left whole. */
function rest_filter_response_fields($response, $server, $request)
{
    $fields = $request instanceof WP_REST_Request ? Fields::fromQuery(['_fields' => $request->get_param('_fields')]) : null;
    if (!$response instanceof WP_REST_Response || $response->is_error() || $fields === null || !is_array($data = $response->get_data())) {
        return $response;
    }
    $response->set_data(wp_is_numeric_array($data) ? array_map(static fn ($item) => is_array($item) ? $fields->apply($item) : $item, $data) : $fields->apply($data));
    return $response;
}

/**
 * A REST answer kept for the editor to read without asking again: a GET
 * (or OPTIONS, given as [path, method]) dispatched in process; only a 200
 * is kept, its data through rest_post_dispatch with its headers, OPTIONS
 * answers under their own key.
 */
function rest_preload_api_request($memo, $path)
{
    $memo = is_array($memo) ? $memo : [];
    // An empty path asks for nothing (probe rest-api-helpers).
    if ($path === '' || $path === null) {
        return $memo;
    }
    $method = 'GET';
    if (is_array($path) && count($path) === 2) {
        $method = in_array(end($path), ['GET', 'OPTIONS'], true) ? (string) end($path) : 'GET';
        $path = reset($path);
    }
    $path = untrailingslashit((string) $path) ?: '/';
    $parts = parse_url($path);
    if ($parts === false || $path === '') {
        return $memo;
    }
    $request = new WP_REST_Request($method, (string) ($parts['path'] ?? '/'));
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $query);
        $request->set_query_params($query);
    }
    $response = rest_do_request($request);
    if ($response->get_status() !== 200) {
        return $memo;
    }
    $server = rest_get_server();
    $response = apply_filters('rest_post_dispatch', rest_ensure_response($response), $server, $request);
    $entry = ['body' => (array) $server->response_to_data($response, $request->has_param('_embed') ? rest_parse_embed_param($request['_embed']) : false), 'headers' => $response->get_headers()];
    if ($method === 'OPTIONS') {
        $memo[$method][$path] = $entry;
    } else {
        $memo[$path] = $entry;
    }
    return $memo;
}

function rest_output_link_header()
{
    $root = get_rest_url();
    if (headers_sent() || empty($root) || PHP_SAPI === 'cli') {
        return;
    }
    header(sprintf('Link: <%s>; rel="https://api.w.org/"', sanitize_url($root)), false);
    $resource = rest_get_queried_resource_route();
    if ($resource) {
        header(sprintf('Link: <%s>; rel="alternate"; title="%s"; type="application/json"', sanitize_url(rest_url($resource)), _x('JSON', 'REST API resource link name')), false);
    }
}

/**
 * The REST API link, and on a classic page the queried object's JSON
 * alternate. Outside a page there is no queried object, so only the root.
 * No trailing newline: the reference continues the line with the RSD link.
 */
function rest_output_link_wp_head()
{
    $head = Runtime::current()->get('classic_head');
    $resolution = Runtime::current()->get('classic_resolution');
    if ($head instanceof \Minn\Theme\HeadLinks && $resolution instanceof \Minn\Front\Resolution) {
        echo $head->restLink() . $head->jsonAlternate($resolution);
        return;
    }
    echo '<link rel="https://api.w.org/" href="' . esc_url(get_rest_url()) . '" />';
}

/** The index's application-passwords sign-in, where they are available: the authorization screen's address (probe rest-api-helpers). */
function rest_add_application_passwords_to_index($response)
{
    if (!$response instanceof WP_REST_Response || !wp_is_application_passwords_available()) {
        return $response;
    }
    $response->data['authentication']['application-passwords'] = ['endpoints' => ['authorization' => admin_url('authorize-application.php')]];
    return $response;
}

function rest_sanitize_boolean($value)
{
    return SchemaValues::toBoolean($value);
}

function rest_authorization_required_code()
{
    return is_user_logged_in() ? 403 : 401;
}

function rest_is_ip_address($ip)
{
    return filter_var((string) $ip, FILTER_VALIDATE_IP) === false ? false : (string) $ip;
}

/** The first (anyOf) or only (oneOf) schema the value satisfies, else the reference's refusal. */
function rest_find_one_matching_schema($value, $args, $param, $stop_after_first_match = false)
{
    $found = SchemaValues::combining($value, (array) $args, (bool) $stop_after_first_match, static function ($value, array $schema) use ($param) {
        $result = rest_validate_value_from_schema($value, $schema, $param);
        return is_wp_error($result) ? new Refusal($result->get_error_code(), $result->get_error_message(), $result) : true;
    });
    if (isset($found['schema'])) {
        return $found['schema'];
    }
    if (isset($found['errors'])) {
        return rest_get_combining_operation_error($value, $param, array_map(static fn (array $e) => ['error_object' => $e['error']->data, 'schema' => $e['schema'], 'index' => $e['index']], $found['errors']));
    }
    $titles = array_filter(array_map(static fn ($s) => $s['title'] ?? '', $found['matches']));
    $message = $titles !== []
        ? sprintf('%s matches %s, but more than one of these is allowed.', $param, implode(', ', $titles))
        : sprintf('%s matches more than one of the expected formats.', $param);
    return new WP_Error('rest_one_of_multiple_matches', $message, ['positions' => array_keys($found['matches'])]);
}

/**
 * One refusal for a value that matched none of the combined schemas (probe
 * rest-api-helpers): with one schema, its refusal named; otherwise the
 * schemas whose refusal is only that the value is not their type are set
 * aside, and when one remains its refusal is named, else none is.
 */
function rest_get_combining_operation_error($value, $param, $errors)
{
    if (count($errors) === 1) {
        return _minn_combining_refusal((string) $param, $errors[0]);
    }
    $kept = array_values(array_filter($errors, static function (array $error) use ($param): bool {
        $data = $error['error_object']->get_error_data();
        return !($error['error_object']->get_error_code() === 'rest_invalid_type' && is_array($data) && ($data['param'] ?? null) === $param);
    }));
    if (count($kept) === 1) {
        return _minn_combining_refusal((string) $param, $kept[0]);
    }
    return new WP_Error('rest_no_matching_schema', sprintf(__('%s does not match any of the expected formats.'), $param));
}

/** @internal One schema's refusal as the combined one: by its title when it has one, with its position. */
function _minn_combining_refusal(string $param, array $error): WP_Error
{
    $reason = $error['error_object']->get_error_message();
    $message = isset($error['schema']['title'])
        ? sprintf(__('%1$s is not a valid %2$s. Reason: %3$s'), $param, $error['schema']['title'], $reason)
        : sprintf(__('%1$s does not match the expected format. Reason: %2$s'), $param, $reason);
    return new WP_Error('rest_no_matching_schema', $message, ['position' => $error['index']]);
}

/** What an _embed value asks for: every link (true), or the named relations. */
function rest_parse_embed_param($embed)
{
    if (!$embed || $embed === 'true' || $embed === '1') {
        return true;
    }
    $rels = wp_parse_list($embed);
    return $rels === [] ? true : $rels;
}

/** The Allow header for a matched route: the methods this request's caller may use there (the engine's routes by their policies, a plugin's by its handlers' permission callbacks); none, no header. */
function rest_send_allow_header($response, $server, $request)
{
    $route = (string) $response->get_matched_route();
    if ($route === '') {
        return $response;
    }
    $allowed = $server instanceof WP_REST_Server && $server->engine_route($route) ? $server->engine_allowed($request) : Minn\Rest\RuntimeRoutes::allowedMethods($route, $request);
    if ($allowed) {
        $response->header('Allow', implode(', ', $allowed));
    }
    return $response;
}

/** The engine answers REST requests before any page loads, so this has nothing to serve. */
function rest_api_loaded()
{
}

/** A value checked against the object rules alone: no enum or format (probe rest-validators). */
function rest_validate_object_value_from_schema($value, $args, $param)
{
    return _minn_schema_result(_minn_schema()->validateAs('object', $value, (array) $args, (string) $param));
}

/** A value checked against the array rules alone. */
function rest_validate_array_value_from_schema($value, $args, $param)
{
    return _minn_schema_result(_minn_schema()->validateAs('array', $value, (array) $args, (string) $param));
}

/** A value checked against the string rules alone: length and pattern, not format. */
function rest_validate_string_value_from_schema($value, $args, $param)
{
    return _minn_schema_result(_minn_schema()->validateAs('string', $value, (array) $args, (string) $param));
}

/** A value checked against the number rules alone: numeric, bounds, multipleOf. */
function rest_validate_number_value_from_schema($value, $args, $param)
{
    return _minn_schema_result(_minn_schema()->validateAs('number', $value, (array) $args, (string) $param));
}

/** A value checked against the integer rules alone: the number rules, then wholeness. */
function rest_validate_integer_value_from_schema($value, $args, $param)
{
    return _minn_schema_result(_minn_schema()->validateAs('integer', $value, (array) $args, (string) $param));
}

function rest_validate_boolean_value_from_schema($value, $param)
{
    return _minn_schema_result(_minn_schema()->validateAs('boolean', $value, [], (string) $param));
}

function rest_validate_null_value_from_schema($value, $param)
{
    return _minn_schema_result(_minn_schema()->validateAs('null', $value, [], (string) $param));
}

function rest_validate_enum($value, $args, $param)
{
    return _minn_schema_result(_minn_schema()->validateEnum($value, (array) $args, (string) $param));
}

function rest_validate_json_schema_pattern($pattern, $value)
{
    return SchemaValues::matchesPattern((string) $pattern, (string) $value);
}

function rest_validate_array_contains_unique_items($input_array)
{
    return SchemaValues::hasUniqueItems((array) $input_array);
}

function _rest_array_intersect_key_recursive($array1, $array2)
{
    return SchemaValues::intersectKeys((array) $array1, (array) $array2);
}

/** The refusal for a combinator none of whose schemas matched, from the closest one's error ($error: schema, error_object, index). */
function rest_format_combining_operation_error($param, $error)
{
    $title = isset($error['schema']['title']) ? (string) $error['schema']['title'] : null;
    $reason = ($error['error_object'] ?? null) instanceof WP_Error ? $error['error_object']->get_error_message() : '';
    return _minn_schema_result(_minn_schema()->combiningFailure((string) $param, $title, $reason, $error['index'] ?? null));
}

/** Notes how an application password sign-in went (hooked on its success and failure actions), for rest_authentication_errors. */
function rest_application_password_collect_status($user_or_error, $app_password = [])
{
    $GLOBALS['wp_rest_application_password_status'] = $user_or_error;
    $GLOBALS['wp_rest_application_password_uuid'] = is_wp_error($user_or_error) ? null : ($app_password['uuid'] ?? null);
}

/** The uuid of the application password this request signed in with, or null. */
function rest_get_authenticated_app_password()
{
    return $GLOBALS['wp_rest_application_password_uuid'] ?? null;
}

/** An earlier answer stands; otherwise a failed application password is a 401, and a successful one is true. */
function rest_application_password_check_errors($result)
{
    if (!empty($result)) {
        return $result;
    }
    $status = $GLOBALS['wp_rest_application_password_status'] ?? null;
    if (is_wp_error($status)) {
        $status->add_data(['status' => 401]);
        return $status;
    }
    return $status instanceof WP_User ? true : $result;
}

/** Notes the cookie check's verdict as the auth_cookie_* action fires: true when valid, else the hook's name after auth_cookie_. */
function rest_cookie_collect_status()
{
    $hook = (string) current_filter();
    $GLOBALS['wp_rest_auth_cookie'] = $hook === 'auth_cookie_valid' ? true : substr($hook, 12);
}

/** The REST API's line in the XML-RPC service description. */
function rest_output_rsd()
{
    echo "\t" . '<api name="WP-API" blogID="1" preferred="false" apiLink="' . esc_url(get_rest_url()) . '" />' . "\n\t";
}

/** Nothing to register: the engine's core routes answer on every request without being added to the server. */
function create_initial_rest_routes()
{
    // The engine answers most of wp/v2 itself; these routes are served by their controller classes (probes abilities-rest-controllers, icons).
    foreach ([new WP_REST_Icons_Controller(), new WP_REST_Icon_Collections_Controller(), new WP_REST_Abilities_V1_Categories_Controller(), new WP_REST_Abilities_V1_Run_Controller(), new WP_REST_Abilities_V1_List_Controller()] as $controller) {
        $controller->register_routes();
    }
}
