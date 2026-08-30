<?php
/** The REST route API over WP_REST_Server. Behaviour from contracts/fixtures/api/rest.json. */

use Minn\Rest\Schema;
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

function rest_api_default_filters()
{
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
    $errors = [];
    foreach ((array) $error->errors as $code => $messages) {
        $all_data = $error->get_all_error_data($code);
        $last_data = array_pop($all_data);
        foreach ((array) $messages as $message) {
            $formatted = ['code' => $code, 'message' => $message, 'data' => $last_data];
            if ($all_data) {
                $formatted['additional_data'] = $all_data;
            }
            $errors[] = $formatted;
        }
    }
    $data = $errors[0];
    if (count($errors) > 1) {
        array_shift($errors);
        $data['additional_errors'] = $errors;
    }
    return new WP_REST_Response($data, $status);
}

function rest_handle_options_request($response, $handler, $request)
{
    return $response;
}

function rest_send_cors_headers($value)
{
    return $value;
}

function rest_cookie_check_errors($result)
{
    return $result;
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
    return Schema::parseDate($date, (bool) $force_utc);
}

function rest_parse_hex_color($color)
{
    return Schema::parseHexColor($color);
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
    return Schema::isBoolean($maybe_bool);
}

function rest_is_integer($maybe_integer)
{
    return Schema::isInteger($maybe_integer);
}

function rest_is_array($maybe_array)
{
    return Schema::isArray($maybe_array);
}

function rest_sanitize_array($maybe_array)
{
    return Schema::toArray($maybe_array);
}

function rest_is_object($maybe_object)
{
    return Schema::isObject($maybe_object);
}

function rest_sanitize_object($maybe_object)
{
    return Schema::toObject($maybe_object);
}

function rest_stabilize_value($value)
{
    return Schema::stabilize($value);
}

function rest_get_best_type_for_value($value, $types)
{
    return Schema::bestType($value, $types);
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
    return Schema::valuesEqual($value1, $value2);
}

function rest_find_matching_pattern_property_schema($property, $args)
{
    return Schema::patternProperty((string) $property, (array) $args);
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

function rest_get_endpoint_args_for_schema($schema, $method = WP_REST_Server::CREATABLE)
{
    return Schema::endpointArgs((array) $schema, $method === WP_REST_Server::CREATABLE);
}

function rest_filter_response_by_context($response_data, $schema, $context)
{
    return _minn_schema()->filterByContext($response_data, (array) $schema, (string) $context);
}

function rest_find_any_matching_schema($value, $args, $param)
{
    $schema = _minn_schema()->anyMatching($value, (array) $args, (string) $param);
    return $schema ?? new WP_Error('rest_no_matching_schema', sprintf('%s does not match any of the expected formats.', $param));
}

function rest_is_field_included($field, $fields)
{
    if (in_array($field, $fields, true)) {
        return true;
    }
    foreach ($fields as $accepted_field) {
        if (str_starts_with($accepted_field, "$field.")) {
            return true;
        }
    }
    return false;
}

function rest_default_additional_properties_to_false($schema)
{
    return Schema::closeObjects((array) $schema);
}

function rest_filter_response_fields($response, $server, $request)
{
    return $response;
}

function rest_preload_api_request($memo, $path)
{
    return $memo;
}

function rest_output_link_header()
{
}

function rest_output_link_wp_head()
{
}

function rest_add_application_passwords_to_index($response)
{
    return $response;
}

function rest_sanitize_boolean($value)
{
    return Schema::toBoolean($value);
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
    $found = Schema::combining($value, (array) $args, (bool) $stop_after_first_match, static function ($value, array $schema) use ($param) {
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

/** One refusal for a value that matched none of the combined schemas. */
function rest_get_combining_operation_error($value, $param, $errors)
{
    if (count($errors) === 1) {
        return $errors[0]['error_object'];
    }
    $message = sprintf('%s does not match any of the expected formats.', $param);
    $details = [];
    foreach ($errors as $error) {
        $details[] = ['index' => $error['index'], 'message' => $error['error_object']->get_error_message()];
    }
    return new WP_Error('rest_no_matching_schema', $message, ['details' => $details]);
}
