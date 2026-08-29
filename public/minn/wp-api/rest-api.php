<?php
/** The REST route API over WP_REST_Server. Behaviour from contracts/fixtures/api/rest.json. */

use Minn\Runtime\Runtime;

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
    if (!did_action('rest_api_init')) {
        // Registering before rest_api_init is fine: the server records it and fires the action once created.
    }
    if (isset($args['args'])) {
        $common_args = $args['args'];
        unset($args['args']);
    } else {
        $common_args = [];
    }
    if (isset($args['callback'])) {
        $args = [$args];
    }
    $defaults = ['methods' => 'GET', 'callback' => null, 'args' => []];
    foreach ($args as $key => &$arg_group) {
        if (!is_numeric($key)) {
            continue;
        }
        $arg_group = array_merge($defaults, $arg_group);
        $arg_group['args'] = array_merge($common_args, $arg_group['args']);
        if (!isset($arg_group['permission_callback'])) {
            _doing_it_wrong('register_rest_route', 'The REST API route definition is missing the required permission_callback argument.', '5.5.0');
        }
        foreach ($arg_group['args'] as $arg => &$options) {
            if (!is_array($options)) {
                $options = [];
            }
            if (!isset($options['validate_callback'])) {
                $options['validate_callback'] = 'rest_validate_request_arg';
            }
            if (!isset($options['sanitize_callback'])) {
                $options['sanitize_callback'] = 'rest_sanitize_request_arg';
            }
        }
        unset($options);
    }
    unset($arg_group);
    $full_route = '/' . $clean_namespace . '/' . trim((string) $route, '/');
    rest_get_server()->register_route($clean_namespace, $full_route, $args, $override);
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
    if ($force_utc) {
        $date = preg_replace('#^(.*?)[+-]\d{2}:?\d{2}$#', '$1Z', (string) $date);
    }
    $regex = '#^\d{4}-\d{2}-\d{2}[Tt ]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}(?::\d{2})?)?$#';
    if (!preg_match($regex, (string) $date, $matches)) {
        return false;
    }
    return strtotime((string) $date);
}

function rest_parse_hex_color($color)
{
    return preg_match('|^#([A-Fa-f0-9]{3}){1,2}$|', (string) $color) ? $color : false;
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

function rest_is_boolean($maybe_bool)
{
    if (is_bool($maybe_bool)) {
        return true;
    }
    if (is_string($maybe_bool)) {
        $maybe_bool = strtolower($maybe_bool);
        return in_array($maybe_bool, ['false', 'true', '0', '1'], true);
    }
    if (is_int($maybe_bool)) {
        return in_array($maybe_bool, [0, 1], true);
    }
    return false;
}

function rest_is_integer($maybe_integer)
{
    return is_numeric($maybe_integer) && round((float) $maybe_integer) === (float) $maybe_integer;
}

function rest_is_array($maybe_array)
{
    if (is_scalar($maybe_array)) {
        $maybe_array = wp_parse_list($maybe_array);
    }
    return wp_is_numeric_array($maybe_array);
}

function rest_sanitize_array($maybe_array)
{
    if (is_scalar($maybe_array)) {
        return wp_parse_list($maybe_array);
    }
    if (!is_array($maybe_array)) {
        return [];
    }
    return array_values($maybe_array);
}

function rest_is_object($maybe_object)
{
    if ($maybe_object === '') {
        return true;
    }
    if ($maybe_object instanceof stdClass) {
        return true;
    }
    if ($maybe_object instanceof JsonSerializable) {
        $maybe_object = $maybe_object->jsonSerialize();
    }
    return is_array($maybe_object);
}

function rest_sanitize_object($maybe_object)
{
    if ($maybe_object === '') {
        return [];
    }
    if ($maybe_object instanceof stdClass) {
        return (array) $maybe_object;
    }
    if ($maybe_object instanceof JsonSerializable) {
        $maybe_object = $maybe_object->jsonSerialize();
    }
    return is_array($maybe_object) ? $maybe_object : [];
}

function rest_stabilize_value($value)
{
    if (is_scalar($value) || $value === null) {
        return $value;
    }
    if (is_object($value)) {
        return $value;
    }
    ksort($value);
    foreach ($value as $k => $v) {
        $value[$k] = rest_stabilize_value($v);
    }
    return $value;
}

function rest_get_best_type_for_value($value, $types)
{
    static $checks = ['array' => 'rest_is_array', 'object' => 'rest_is_object', 'null' => 'is_null', 'boolean' => 'rest_is_boolean', 'integer' => 'rest_is_integer', 'number' => 'is_numeric', 'string' => 'is_string'];
    $types = array_values(array_intersect(array_keys($checks), (array) $types));
    if (count($types) === 1) {
        return $types[0];
    }
    foreach ($checks as $type => $check) {
        if (in_array($type, $types, true) && $check($value)) {
            return $type;
        }
    }
    return '';
}

function rest_handle_multi_type_schema($value, $args, $param = '')
{
    return rest_get_best_type_for_value($value, $args['type']);
}

function rest_validate_value_from_schema($value, $args, $param = '')
{
    if (isset($args['anyOf'])) {
        foreach ($args['anyOf'] as $schema) {
            if (!is_wp_error(rest_validate_value_from_schema($value, $schema, $param))) {
                return true;
            }
        }
        return new WP_Error('rest_no_matching_schema', sprintf('%s does not match any of the expected formats.', $param));
    }
    if (isset($args['oneOf'])) {
        $matches = 0;
        foreach ($args['oneOf'] as $schema) {
            if (!is_wp_error(rest_validate_value_from_schema($value, $schema, $param))) {
                $matches++;
            }
        }
        if ($matches !== 1) {
            return new WP_Error('rest_one_of_multiple_matches', sprintf('%s matches more than one of the expected formats.', $param));
        }
        return true;
    }
    $allowed_types = ['array', 'object', 'string', 'number', 'integer', 'boolean', 'null'];
    if (!isset($args['type'])) {
        // An enum alone is still checked; nothing else is without a type.
        if (!empty($args['enum'])) {
            $found = false;
            foreach ($args['enum'] as $enum) {
                if (rest_are_values_equal($value, $enum)) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $encoded = array_map(static fn ($v) => is_scalar($v) ? (string) $v : wp_json_encode($v), $args['enum']);
                if (count($encoded) === 1) {
                    return new WP_Error('rest_not_in_enum', sprintf('%1$s is not %2$s.', $param, $encoded[0]));
                }
                $last = array_pop($encoded);
                return new WP_Error('rest_not_in_enum', sprintf('%1$s is not one of %2$s and %3$s.', $param, implode(', ', $encoded), $last));
            }
        }
        return true;
    }
    if (is_array($args['type'])) {
        $best_type = rest_handle_multi_type_schema($value, $args, $param);
        if (!$best_type) {
            return new WP_Error('rest_invalid_type', sprintf('%1$s is not of type %2$s.', $param, implode(',', $args['type'])), ['param' => $param]);
        }
        $args['type'] = $best_type;
    }
    if ($args['type'] === 'array') {
        if (!rest_is_array($value)) {
            return new WP_Error('rest_invalid_type', sprintf('%1$s is not of type %2$s.', $param, 'array'), ['param' => $param]);
        }
        $value = rest_sanitize_array($value);
        if (isset($args['items'])) {
            foreach ($value as $index => $v) {
                $is_valid = rest_validate_value_from_schema($v, $args['items'], $param . '[' . $index . ']');
                if (is_wp_error($is_valid)) {
                    return $is_valid;
                }
            }
        }
        if (isset($args['minItems']) && count($value) < $args['minItems']) {
            return new WP_Error('rest_too_few_items', sprintf('%1$s must contain at least %2$s items.', $param, number_format_i18n($args['minItems'])));
        }
        if (isset($args['maxItems']) && count($value) > $args['maxItems']) {
            return new WP_Error('rest_too_many_items', sprintf('%1$s must contain at most %2$s items.', $param, number_format_i18n($args['maxItems'])));
        }
        if (!empty($args['uniqueItems']) && count(array_unique(array_map('serialize', $value))) !== count($value)) {
            return new WP_Error('rest_duplicate_items', sprintf('%s has duplicate items.', $param));
        }
    }
    if ($args['type'] === 'object') {
        if (!rest_is_object($value)) {
            return new WP_Error('rest_invalid_type', sprintf('%1$s is not of type %2$s.', $param, 'object'), ['param' => $param]);
        }
        $value = rest_sanitize_object($value);
        if (isset($args['required']) && is_array($args['required'])) {
            foreach ($args['required'] as $name) {
                if (!array_key_exists($name, $value)) {
                    return new WP_Error('rest_property_required', sprintf('%1$s is a required property of %2$s.', $name, $param));
                }
            }
        } elseif (isset($args['properties'])) {
            foreach ($args['properties'] as $name => $property) {
                if (isset($property['required']) && $property['required'] === true && !array_key_exists($name, $value)) {
                    return new WP_Error('rest_property_required', sprintf('%1$s is a required property of %2$s.', $name, $param));
                }
            }
        }
        foreach ($value as $property => $v) {
            if (isset($args['properties'][$property])) {
                $is_valid = rest_validate_value_from_schema($v, $args['properties'][$property], $param . '[' . $property . ']');
                if (is_wp_error($is_valid)) {
                    return $is_valid;
                }
                continue;
            }
            $pattern_property_schema = rest_find_matching_pattern_property_schema($property, $args);
            if ($pattern_property_schema !== null) {
                $is_valid = rest_validate_value_from_schema($v, $pattern_property_schema, $param . '[' . $property . ']');
                if (is_wp_error($is_valid)) {
                    return $is_valid;
                }
                continue;
            }
            if (isset($args['additionalProperties'])) {
                if ($args['additionalProperties'] === false) {
                    return new WP_Error('rest_additional_properties_forbidden', sprintf('%1$s is not a valid property of Object.', $property));
                }
                if (is_array($args['additionalProperties'])) {
                    $is_valid = rest_validate_value_from_schema($v, $args['additionalProperties'], $param . '[' . $property . ']');
                    if (is_wp_error($is_valid)) {
                        return $is_valid;
                    }
                }
            }
        }
    }
    if ($args['type'] === 'null') {
        if ($value !== null) {
            return new WP_Error('rest_invalid_type', sprintf('%1$s is not of type %2$s.', $param, 'null'), ['param' => $param]);
        }
        return true;
    }
    if (!empty($args['enum'])) {
        $found = false;
        foreach ($args['enum'] as $enum) {
            if (rest_are_values_equal($value, $enum)) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            $encoded = array_map(static fn ($v) => is_scalar($v) ? (string) $v : wp_json_encode($v), $args['enum']);
            if (count($encoded) === 1) {
                return new WP_Error('rest_not_in_enum', sprintf('%1$s is not %2$s.', $param, $encoded[0]));
            }
            $last = array_pop($encoded);
            return new WP_Error('rest_not_in_enum', sprintf('%1$s is not one of %2$s and %3$s.', $param, implode(', ', $encoded), $last));
        }
    }
    if (in_array($args['type'], ['integer', 'number'], true) && !is_numeric($value)) {
        return new WP_Error('rest_invalid_type', sprintf('%1$s is not of type %2$s.', $param, $args['type']), ['param' => $param]);
    }
    if ($args['type'] === 'integer' && !rest_is_integer($value)) {
        return new WP_Error('rest_invalid_type', sprintf('%1$s is not of type %2$s.', $param, 'integer'), ['param' => $param]);
    }
    if ($args['type'] === 'boolean' && !rest_is_boolean($value)) {
        return new WP_Error('rest_invalid_type', sprintf('%1$s is not of type %2$s.', $param, 'boolean'), ['param' => $param]);
    }
    if ($args['type'] === 'string') {
        if (!is_string($value)) {
            return new WP_Error('rest_invalid_type', sprintf('%1$s is not of type %2$s.', $param, 'string'), ['param' => $param]);
        }
        if (isset($args['minLength']) && mb_strlen($value) < $args['minLength']) {
            return new WP_Error('rest_too_short', sprintf('%1$s must be at least %2$s characters long.', $param, number_format_i18n($args['minLength'])));
        }
        if (isset($args['maxLength']) && mb_strlen($value) > $args['maxLength']) {
            return new WP_Error('rest_too_long', sprintf('%1$s must be at most %2$s characters long.', $param, number_format_i18n($args['maxLength'])));
        }
        if (isset($args['pattern']) && !preg_match('#' . str_replace('#', '\\#', $args['pattern']) . '#u', $value)) {
            return new WP_Error('rest_invalid_pattern', sprintf('%1$s does not match pattern %2$s.', $param, $args['pattern']));
        }
    }
    if (isset($args['format']) && (!isset($args['type']) || $args['type'] === 'string' || !in_array($args['type'], $allowed_types, true))) {
        switch ($args['format']) {
            case 'hex-color':
                if (!rest_parse_hex_color($value)) {
                    return new WP_Error('rest_invalid_hex_color', 'Invalid hex color.');
                }
                break;
            case 'date-time':
                if (!rest_parse_date($value)) {
                    return new WP_Error('rest_invalid_date', 'Invalid date.');
                }
                break;
            case 'email':
                if (!is_email($value)) {
                    return new WP_Error('rest_invalid_email', 'Invalid email address.');
                }
                break;
            case 'ip':
                if (!filter_var($value, FILTER_VALIDATE_IP)) {
                    return new WP_Error('rest_invalid_ip', sprintf('%s is not a valid IP address.', $param));
                }
                break;
            case 'uuid':
                if (!wp_is_uuid($value)) {
                    return new WP_Error('rest_invalid_uuid', sprintf('%s is not a valid UUID.', $param));
                }
                break;
        }
    }
    if (in_array($args['type'], ['number', 'integer'], true) && (isset($args['minimum']) || isset($args['maximum']))) {
        if (isset($args['minimum']) && !isset($args['maximum'])) {
            if (!empty($args['exclusiveMinimum']) && $value <= $args['minimum']) {
                return new WP_Error('rest_out_of_bounds', sprintf('%1$s must be greater than %2$d', $param, $args['minimum']));
            }
            if (empty($args['exclusiveMinimum']) && $value < $args['minimum']) {
                return new WP_Error('rest_out_of_bounds', sprintf('%1$s must be greater than or equal to %2$d', $param, $args['minimum']));
            }
        } elseif (isset($args['maximum']) && !isset($args['minimum'])) {
            if (!empty($args['exclusiveMaximum']) && $value >= $args['maximum']) {
                return new WP_Error('rest_out_of_bounds', sprintf('%1$s must be less than %2$d', $param, $args['maximum']));
            }
            if (empty($args['exclusiveMaximum']) && $value > $args['maximum']) {
                return new WP_Error('rest_out_of_bounds', sprintf('%1$s must be less than or equal to %2$d', $param, $args['maximum']));
            }
        } elseif (isset($args['maximum'], $args['minimum'])) {
            if (!empty($args['exclusiveMinimum']) && !empty($args['exclusiveMaximum'])) {
                if ($value >= $args['maximum'] || $value <= $args['minimum']) {
                    return new WP_Error('rest_out_of_bounds', sprintf('%1$s must be between %2$d (exclusive) and %3$d (exclusive)', $param, $args['minimum'], $args['maximum']));
                }
            } elseif (empty($args['exclusiveMinimum']) && !empty($args['exclusiveMaximum'])) {
                if ($value >= $args['maximum'] || $value < $args['minimum']) {
                    return new WP_Error('rest_out_of_bounds', sprintf('%1$s must be between %2$d (inclusive) and %3$d (exclusive)', $param, $args['minimum'], $args['maximum']));
                }
            } elseif (!empty($args['exclusiveMinimum']) && empty($args['exclusiveMaximum'])) {
                if ($value > $args['maximum'] || $value <= $args['minimum']) {
                    return new WP_Error('rest_out_of_bounds', sprintf('%1$s must be between %2$d (exclusive) and %3$d (inclusive)', $param, $args['minimum'], $args['maximum']));
                }
            } elseif (empty($args['exclusiveMinimum']) && empty($args['exclusiveMaximum'])) {
                if ($value > $args['maximum'] || $value < $args['minimum']) {
                    return new WP_Error('rest_out_of_bounds', sprintf('%1$s must be between %2$d (inclusive) and %3$d (inclusive)', $param, $args['minimum'], $args['maximum']));
                }
            }
        }
    }
    if (isset($args['multipleOf']) && in_array($args['type'], ['number', 'integer'], true) && fmod((float) $value, (float) $args['multipleOf']) != 0) {
        return new WP_Error('rest_invalid_multiple', sprintf('%1$s must be a multiple of %2$s.', $param, $args['multipleOf']));
    }
    return true;
}

function rest_are_values_equal($value1, $value2)
{
    if (is_array($value1) && is_array($value2)) {
        if (count($value1) !== count($value2)) {
            return false;
        }
        foreach ($value1 as $index => $value) {
            if (!isset($value2[$index]) || !rest_are_values_equal($value, $value2[$index])) {
                return false;
            }
        }
        return true;
    }
    if (is_int($value1) && is_float($value2) || is_float($value1) && is_int($value2)) {
        return (float) $value1 === (float) $value2;
    }
    return $value1 === $value2;
}

function rest_find_matching_pattern_property_schema($property, $args)
{
    if (isset($args['patternProperties'])) {
        foreach ($args['patternProperties'] as $pattern => $child_schema) {
            if (preg_match('#' . str_replace('#', '\\#', $pattern) . '#u', (string) $property)) {
                return $child_schema;
            }
        }
    }
    return null;
}

function rest_sanitize_value_from_schema($value, $args, $param = '')
{
    if (isset($args['anyOf'])) {
        foreach ($args['anyOf'] as $schema) {
            if (!is_wp_error(rest_validate_value_from_schema($value, $schema, $param))) {
                return rest_sanitize_value_from_schema($value, $schema, $param);
            }
        }
        return $value;
    }
    if (isset($args['oneOf'])) {
        foreach ($args['oneOf'] as $schema) {
            if (!is_wp_error(rest_validate_value_from_schema($value, $schema, $param))) {
                return rest_sanitize_value_from_schema($value, $schema, $param);
            }
        }
        return $value;
    }
    if (!isset($args['type'])) {
        return $value;
    }
    if (is_array($args['type'])) {
        $best_type = rest_handle_multi_type_schema($value, $args, $param);
        if (!$best_type) {
            return null;
        }
        $args['type'] = $best_type;
    }
    if ($args['type'] === 'array') {
        $value = rest_sanitize_array($value);
        if (!empty($args['items'])) {
            foreach ($value as $index => $v) {
                $value[$index] = rest_sanitize_value_from_schema($v, $args['items'], $param . '[' . $index . ']');
            }
        }
        if (!empty($args['uniqueItems'])) {
            $value = array_values(array_unique(array_map('serialize', $value)));
            $value = array_map('unserialize', $value);
        }
        return $value;
    }
    if ($args['type'] === 'object') {
        $value = rest_sanitize_object($value);
        foreach ($value as $property => $v) {
            if (isset($args['properties'][$property])) {
                $value[$property] = rest_sanitize_value_from_schema($v, $args['properties'][$property], $param . '[' . $property . ']');
                continue;
            }
            $pattern_property_schema = rest_find_matching_pattern_property_schema($property, $args);
            if ($pattern_property_schema !== null) {
                $value[$property] = rest_sanitize_value_from_schema($v, $pattern_property_schema, $param . '[' . $property . ']');
                continue;
            }
            if (isset($args['additionalProperties'])) {
                if ($args['additionalProperties'] === false) {
                    unset($value[$property]);
                } elseif (is_array($args['additionalProperties'])) {
                    $value[$property] = rest_sanitize_value_from_schema($v, $args['additionalProperties'], $param . '[' . $property . ']');
                }
            }
        }
        return $value;
    }
    if ($args['type'] === 'null') {
        return null;
    }
    if ($args['type'] === 'integer') {
        return (int) $value;
    }
    if ($args['type'] === 'number') {
        return (float) $value;
    }
    if ($args['type'] === 'boolean') {
        return rest_sanitize_boolean($value);
    }
    if (isset($args['format']) && (!isset($args['type']) || $args['type'] === 'string')) {
        switch ($args['format']) {
            case 'hex-color':
                return (string) sanitize_hex_color($value);
            case 'date-time':
                return sanitize_text_field($value);
            case 'email':
                return sanitize_email($value);
            case 'uri':
                return sanitize_url($value);
            case 'ip':
                return sanitize_text_field($value);
            case 'uuid':
                return sanitize_text_field($value);
            case 'text-field':
                return sanitize_text_field($value);
            case 'textarea-field':
                return sanitize_textarea_field($value);
        }
    }
    if ($args['type'] === 'string') {
        return (string) $value;
    }
    return $value;
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
    $schema_properties = !empty($schema['properties']) ? $schema['properties'] : [];
    $endpoint_args = [];
    $valid_schema_properties = ['type', 'format', 'enum', 'items', 'properties', 'additionalProperties', 'patternProperties', 'minProperties', 'maxProperties', 'minimum', 'maximum', 'exclusiveMinimum', 'exclusiveMaximum', 'multipleOf', 'minLength', 'maxLength', 'pattern', 'minItems', 'maxItems', 'uniqueItems', 'anyOf', 'oneOf'];
    foreach ($schema_properties as $field_id => $params) {
        if (!empty($params['readonly'])) {
            continue;
        }
        $endpoint_args[$field_id] = ['validate_callback' => 'rest_validate_request_arg', 'sanitize_callback' => 'rest_sanitize_request_arg'];
        if (WP_REST_Server::CREATABLE === $method && isset($params['default'])) {
            $endpoint_args[$field_id]['default'] = $params['default'];
        }
        if (WP_REST_Server::CREATABLE === $method && !empty($params['required'])) {
            $endpoint_args[$field_id]['required'] = true;
        }
        foreach ($valid_schema_properties as $schema_prop) {
            if (isset($params[$schema_prop])) {
                $endpoint_args[$field_id][$schema_prop] = $params[$schema_prop];
            }
        }
        if (isset($params['arg_options'])) {
            if (WP_REST_Server::CREATABLE !== $method) {
                unset($params['arg_options']['required']);
            }
            $endpoint_args[$field_id] = array_merge($endpoint_args[$field_id], $params['arg_options']);
        }
    }
    return $endpoint_args;
}

function rest_filter_response_by_context($response_data, $schema, $context)
{
    if (isset($schema['anyOf'])) {
        $matching_schema = rest_find_any_matching_schema($response_data, $schema, '');
        if (!is_wp_error($matching_schema)) {
            if (!isset($schema['type'])) {
                $schema['type'] = $matching_schema['type'];
            }
            $schema['properties'] = $matching_schema['properties'] ?? [];
        }
    }
    if (!is_array($response_data) && !is_object($response_data)) {
        return $response_data;
    }
    $is_array_type = isset($schema['type']) && ($schema['type'] === 'array' || (is_array($schema['type']) && in_array('array', $schema['type'], true)));
    $is_object_type = isset($schema['type']) && ($schema['type'] === 'object' || (is_array($schema['type']) && in_array('object', $schema['type'], true)));
    if ($is_array_type && $is_object_type) {
        if (rest_is_array($response_data)) {
            $is_object_type = false;
        } else {
            $is_array_type = false;
        }
    }
    $has_additional_properties = $is_object_type && isset($schema['additionalProperties']) && is_array($schema['additionalProperties']);
    foreach ($response_data as $key => $value) {
        $check = [];
        if ($is_array_type) {
            $check = $schema['items'] ?? [];
        } elseif ($is_object_type) {
            if (isset($schema['properties'][$key])) {
                $check = $schema['properties'][$key];
            } else {
                $pattern_property_schema = rest_find_matching_pattern_property_schema($key, $schema);
                if ($pattern_property_schema !== null) {
                    $check = $pattern_property_schema;
                } elseif ($has_additional_properties) {
                    $check = $schema['additionalProperties'];
                }
            }
        }
        if (!isset($check['context'])) {
            continue;
        }
        if (!in_array($context, $check['context'], true)) {
            if ($is_array_type) {
                return [];
            }
            if (is_object($response_data)) {
                unset($response_data->{$key});
            } else {
                unset($response_data[$key]);
            }
            continue;
        }
        if (is_array($value) || is_object($value)) {
            $new_value = rest_filter_response_by_context($value, $check, $context);
            if (is_object($response_data)) {
                $response_data->{$key} = $new_value;
            } else {
                $response_data[$key] = $new_value;
            }
        }
    }
    return $response_data;
}

function rest_find_any_matching_schema($value, $args, $param)
{
    foreach ($args['anyOf'] as $schema) {
        if (!is_wp_error(rest_validate_value_from_schema($value, $schema, $param))) {
            return $schema;
        }
    }
    return new WP_Error('rest_no_matching_schema', sprintf('%s does not match any of the expected formats.', $param));
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
    $type = (array) ($schema['type'] ?? []);
    if (in_array('object', $type, true)) {
        if (isset($schema['properties'])) {
            foreach ($schema['properties'] as $key => $child_schema) {
                $schema['properties'][$key] = rest_default_additional_properties_to_false($child_schema);
            }
        }
        if (!isset($schema['additionalProperties'])) {
            $schema['additionalProperties'] = false;
        }
    }
    if (in_array('array', $type, true) && isset($schema['items'])) {
        $schema['items'] = rest_default_additional_properties_to_false($schema['items']);
    }
    return $schema;
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
    if (is_string($value)) {
        $value = strtolower($value);
        if (in_array($value, ['false', '0'], true)) {
            $value = false;
        }
    }
    return (bool) $value;
}

function rest_authorization_required_code()
{
    return is_user_logged_in() ? 403 : 401;
}
