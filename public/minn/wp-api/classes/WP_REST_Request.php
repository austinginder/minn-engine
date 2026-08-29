<?php

/** The request object a route callback receives. Shapes from contracts/fixtures/api/rest.json. */
class WP_REST_Request implements ArrayAccess
{
    protected $method = '';
    protected $params;
    protected $headers = [];
    protected $body = null;
    protected $route;
    protected $attributes = [];
    protected $parsed_json = false;
    protected $parsed_body = false;

    public function __construct($method = '', $route = '', $attributes = [])
    {
        $this->params = ['URL' => [], 'GET' => [], 'POST' => [], 'FILES' => [], 'JSON' => null, 'defaults' => []];
        $this->set_method($method);
        $this->set_route($route);
        $this->set_attributes($attributes);
    }

    public function get_method()
    {
        return $this->method;
    }

    public function set_method($method)
    {
        $this->method = strtoupper((string) $method);
    }

    public function get_headers()
    {
        return $this->headers;
    }

    public function is_method($method)
    {
        return $this->get_method() === strtoupper((string) $method);
    }

    public static function canonicalize_header_name($key)
    {
        return str_replace('-', '_', strtolower((string) $key));
    }

    public function get_header($key)
    {
        $key = $this->canonicalize_header_name($key);
        if (!isset($this->headers[$key])) {
            return null;
        }
        return implode(',', $this->headers[$key]);
    }

    public function get_header_as_array($key)
    {
        $key = $this->canonicalize_header_name($key);
        return $this->headers[$key] ?? null;
    }

    public function set_header($key, $value)
    {
        $key = $this->canonicalize_header_name($key);
        $this->headers[$key] = (array) $value;
    }

    public function add_header($key, $value)
    {
        $key = $this->canonicalize_header_name($key);
        if (!isset($this->headers[$key])) {
            $this->headers[$key] = [];
        }
        $this->headers[$key] = array_merge($this->headers[$key], (array) $value);
    }

    public function remove_header($key)
    {
        unset($this->headers[$this->canonicalize_header_name($key)]);
    }

    public function set_headers($headers, $override = true)
    {
        if ($override) {
            $this->headers = [];
        }
        foreach ((array) $headers as $key => $value) {
            $this->set_header($key, $value);
        }
    }

    public function get_content_type()
    {
        $value = $this->get_header('Content-Type');
        if ($value === null || $value === '') {
            return null;
        }
        $parameters = '';
        if (str_contains($value, ';')) {
            [$value, $parameters] = explode(';', $value, 2);
        }
        $value = strtolower(trim($value));
        if (!str_contains($value, '/')) {
            return null;
        }
        [$type, $subtype] = explode('/', $value, 2);
        return ['value' => $value, 'type' => $type, 'subtype' => $subtype, 'parameters' => trim($parameters)];
    }

    public function is_json_content_type()
    {
        $content_type = $this->get_content_type();
        return $content_type !== null && preg_match('#^application/([\w!\#$&-\^\.\+]+\+)?json(\+oembed)?$#i', $content_type['value']) === 1;
    }

    protected function get_parameter_order()
    {
        $order = [];
        if ($this->is_json_content_type()) {
            $order[] = 'JSON';
        }
        $this->parse_json_params();
        $body = $this->get_body();
        if ($this->method !== 'POST' && !empty($body)) {
            $this->parse_body_params();
        }
        $accepts_body_data = ['POST', 'PUT', 'PATCH', 'DELETE'];
        if (in_array($this->method, $accepts_body_data, true)) {
            $order[] = 'POST';
        }
        $order[] = 'GET';
        $order[] = 'URL';
        $order[] = 'defaults';
        return apply_filters('rest_request_parameter_order', $order, $this);
    }

    public function get_param($key)
    {
        $order = $this->get_parameter_order();
        foreach ($order as $type) {
            if (isset($this->params[$type][$key])) {
                return $this->params[$type][$key];
            }
        }
        return null;
    }

    public function has_param($key)
    {
        $order = $this->get_parameter_order();
        foreach ($order as $type) {
            if (is_array($this->params[$type]) && array_key_exists($key, $this->params[$type])) {
                return true;
            }
        }
        return false;
    }

    public function set_param($key, $value)
    {
        $order = $this->get_parameter_order();
        $found_key = false;
        foreach ($order as $type) {
            if ($type !== 'defaults' && is_array($this->params[$type]) && array_key_exists($key, $this->params[$type])) {
                $this->params[$type][$key] = $value;
                $found_key = true;
            }
        }
        if (!$found_key) {
            $this->params[$order[0]][$key] = $value;
        }
    }

    public function get_params()
    {
        $order = $this->get_parameter_order();
        $order = array_reverse($order, true);
        $params = [];
        foreach ($order as $type) {
            if (is_array($this->params[$type])) {
                $params = array_merge($params, $this->params[$type]);
            }
        }
        return $params;
    }

    public function get_url_params()
    {
        return $this->params['URL'];
    }

    public function set_url_params($params)
    {
        $this->params['URL'] = $params;
    }

    public function get_query_params()
    {
        return $this->params['GET'];
    }

    public function set_query_params($params)
    {
        $this->params['GET'] = $params;
    }

    public function get_body_params()
    {
        return $this->params['POST'];
    }

    public function set_body_params($params)
    {
        $this->params['POST'] = $params;
    }

    public function get_file_params()
    {
        return $this->params['FILES'];
    }

    public function set_file_params($params)
    {
        $this->params['FILES'] = $params;
    }

    public function get_default_params()
    {
        return $this->params['defaults'];
    }

    public function set_default_params($params)
    {
        $this->params['defaults'] = $params;
    }

    public function get_body()
    {
        return $this->body;
    }

    public function set_body($data)
    {
        $this->body = $data;
        $this->parsed_json = false;
        $this->parsed_body = false;
    }

    public function get_json_params()
    {
        $this->parse_json_params();
        return $this->params['JSON'];
    }

    protected function parse_json_params()
    {
        if ($this->parsed_json) {
            return true;
        }
        $this->parsed_json = true;
        if (!$this->is_json_content_type()) {
            return true;
        }
        $body = $this->get_body();
        if (empty($body)) {
            return true;
        }
        $params = json_decode($body, true);
        if ($params === null && json_last_error() !== JSON_ERROR_NONE) {
            $this->parsed_json = false;
            return new WP_Error('rest_invalid_json', 'Invalid JSON body passed.', ['status' => 400, 'json_error_code' => json_last_error(), 'json_error_message' => json_last_error_msg()]);
        }
        $this->params['JSON'] = $params;
        return true;
    }

    protected function parse_body_params()
    {
        if ($this->parsed_body) {
            return;
        }
        $this->parsed_body = true;
        $content_type = $this->get_content_type();
        if (!empty($content_type) && $content_type['value'] !== 'application/x-www-form-urlencoded') {
            return;
        }
        parse_str((string) $this->get_body(), $params);
        $this->params['POST'] = array_merge($params, $this->params['POST']);
    }

    public function get_route()
    {
        return $this->route;
    }

    public function set_route($route)
    {
        $this->route = $route;
    }

    public function get_attributes()
    {
        return $this->attributes;
    }

    public function set_attributes($attributes)
    {
        $this->attributes = $attributes;
    }

    public function sanitize_params()
    {
        $attributes = $this->get_attributes();
        if (empty($attributes['args'])) {
            return true;
        }
        $order = $this->get_parameter_order();
        $invalid_params = [];
        $invalid_details = [];
        foreach ($order as $type) {
            if (empty($this->params[$type])) {
                continue;
            }
            foreach ($this->params[$type] as $key => $value) {
                if (!isset($attributes['args'][$key])) {
                    continue;
                }
                $param_args = $attributes['args'][$key];
                if (!isset($param_args['sanitize_callback'])) {
                    continue;
                }
                if ($param_args['sanitize_callback'] === false || $param_args['sanitize_callback'] === null) {
                    continue;
                }
                $sanitized_value = call_user_func($param_args['sanitize_callback'], $value, $this, $key);
                if (is_wp_error($sanitized_value)) {
                    $invalid_params[$key] = implode(' ', $sanitized_value->get_error_messages());
                    $invalid_details[$key] = rest_convert_error_to_response($sanitized_value)->get_data();
                } else {
                    $this->params[$type][$key] = $sanitized_value;
                }
            }
        }
        if ($invalid_params) {
            return new WP_Error('rest_invalid_param', sprintf('Invalid parameter(s): %s', implode(', ', array_keys($invalid_params))), ['status' => 400, 'params' => $invalid_params, 'details' => $invalid_details]);
        }
        return true;
    }

    public function has_valid_params()
    {
        if ($this->is_json_content_type() && !empty($this->get_body())) {
            $json_error = $this->parse_json_params();
            if (is_wp_error($json_error)) {
                return $json_error;
            }
        }
        $attributes = $this->get_attributes();
        $required = [];
        $args = empty($attributes['args']) ? [] : $attributes['args'];
        foreach ($args as $key => $arg) {
            $param = $this->get_param($key);
            if (isset($arg['required']) && $arg['required'] === true && $param === null) {
                $required[] = $key;
            }
        }
        if (!empty($required)) {
            return new WP_Error('rest_missing_callback_param', sprintf('Missing parameter(s): %s', implode(', ', $required)), ['status' => 400, 'params' => $required]);
        }
        $invalid_params = [];
        $invalid_details = [];
        foreach ($args as $key => $arg) {
            $param = $this->get_param($key);
            if ($param !== null && !empty($arg['validate_callback'])) {
                $valid_check = call_user_func($arg['validate_callback'], $param, $this, $key);
                if ($valid_check === false) {
                    $invalid_params[$key] = 'Invalid parameter.';
                    $invalid_details[$key] = ['code' => 'rest_invalid_param', 'message' => 'Invalid parameter.', 'data' => null];
                }
                if (is_wp_error($valid_check)) {
                    $invalid_params[$key] = implode(' ', $valid_check->get_error_messages());
                    $invalid_details[$key] = rest_convert_error_to_response($valid_check)->get_data();
                }
            }
        }
        if ($invalid_params) {
            return new WP_Error('rest_invalid_param', sprintf('Invalid parameter(s): %s', implode(', ', array_keys($invalid_params))), ['status' => 400, 'params' => $invalid_params, 'details' => $invalid_details]);
        }
        if (isset($attributes['validate_callback'])) {
            $valid_check = call_user_func($attributes['validate_callback'], $this);
            if (is_wp_error($valid_check)) {
                return $valid_check;
            }
            if ($valid_check === false) {
                return new WP_Error('rest_invalid_params', 'Invalid parameters.', ['status' => 400]);
            }
        }
        return true;
    }

    public function offsetExists($offset): bool
    {
        $order = $this->get_parameter_order();
        foreach ($order as $type) {
            if (isset($this->params[$type][$offset])) {
                return true;
            }
        }
        return false;
    }

    public function offsetGet($offset): mixed
    {
        return $this->get_param($offset);
    }

    public function offsetSet($offset, $value): void
    {
        $this->set_param($offset, $value);
    }

    public function offsetUnset($offset): void
    {
        $order = $this->get_parameter_order();
        foreach ($order as $type) {
            unset($this->params[$type][$offset]);
        }
    }

    public static function from_url($url)
    {
        $bits = parse_url((string) $url);
        $query_params = [];
        if (!empty($bits['query'])) {
            wp_parse_str($bits['query'], $query_params);
        }
        $api_root = rest_url();
        if (get_option('permalink_structure') && str_starts_with((string) $url, $api_root)) {
            $api_url_part = substr((string) $url, strlen(untrailingslashit($api_root)));
            $route = parse_url($api_url_part, PHP_URL_PATH);
        } elseif (!empty($query_params['rest_route'])) {
            $route = $query_params['rest_route'];
            unset($query_params['rest_route']);
        }
        $request = false;
        if (!empty($route)) {
            $request = new WP_REST_Request('GET', $route);
            $request->set_query_params($query_params);
        }
        return apply_filters('rest_request_from_url', $request, $url);
    }
}
