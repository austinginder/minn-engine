<?php

use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Rest\Api;
use Minn\Runtime\Runtime;

/**
 * The route registry and dispatcher plugin code registers into. Routes it
 * does not hold are answered by the engine's own REST layer.
 */
class WP_REST_Server
{
    const READABLE = 'GET';
    const CREATABLE = 'POST';
    const EDITABLE = 'POST, PUT, PATCH';
    const DELETABLE = 'DELETE';
    const ALLMETHODS = 'GET, POST, PUT, PATCH, DELETE';

    protected $namespaces = [];
    protected $endpoints = [];
    protected $route_options = [];
    protected $embed_cache = [];
    protected $dispatching_requests = [];

    public function __construct()
    {
        $this->endpoints = [
            '/' => [
                'callback' => [$this, 'get_index'],
                'methods' => 'GET',
                'args' => ['context' => ['default' => 'view']],
            ],
        ];
    }

    public function check_authentication()
    {
        return apply_filters('rest_authentication_errors', null);
    }

    protected function error_to_response($error)
    {
        return rest_convert_error_to_response($error);
    }

    protected function json_error($code, $message, $status = null)
    {
        $status = $status ? (int) $status : 500;
        return wp_json_encode(['code' => $code, 'message' => $message, 'data' => ['status' => $status]]);
    }

    public function get_json_encode_options(WP_REST_Request $request)
    {
        $options = 0;
        return apply_filters('rest_json_encode_options', $options, $request);
    }

    public function register_route($route_namespace, $route, $route_args, $override = false)
    {
        if (!isset($this->namespaces[$route_namespace])) {
            $this->namespaces[$route_namespace] = [];
            $this->register_route($route_namespace, '/' . $route_namespace, [
                [
                    'methods' => self::READABLE,
                    'callback' => [$this, 'get_namespace_index'],
                    'args' => ['namespace' => ['default' => $route_namespace], 'context' => ['default' => 'view']],
                ],
            ]);
        }
        $this->namespaces[$route_namespace][$route] = true;
        $route_args['namespace'] = $route_namespace;
        if ($override || empty($this->endpoints[$route])) {
            $this->endpoints[$route] = $route_args;
        } else {
            $this->endpoints[$route] = array_merge($this->endpoints[$route], $route_args);
        }
    }

    public function get_routes($route_namespace = '')
    {
        $endpoints = $this->endpoints;
        if ($route_namespace) {
            $endpoints = wp_list_filter($endpoints, ['namespace' => $route_namespace]);
        }
        $endpoints = apply_filters('rest_endpoints', $endpoints);
        foreach ($endpoints as $route => &$handlers) {
            if (isset($handlers['callback'])) {
                $handlers = [$handlers];
            }
            if (!isset($this->route_options[$route])) {
                $this->route_options[$route] = [];
            }
            foreach ($handlers as $key => &$handler) {
                if (!is_numeric($key)) {
                    $this->route_options[$route][$key] = $handler;
                    unset($handlers[$key]);
                    continue;
                }
                $handler = wp_parse_args($handler, ['methods' => [], 'accept_json' => false, 'accept_raw' => false, 'show_in_index' => true, 'args' => []]);
                if (is_string($handler['methods'])) {
                    $methods = explode(',', $handler['methods']);
                } elseif (is_array($handler['methods'])) {
                    $methods = $handler['methods'];
                } else {
                    $methods = [];
                }
                $handler['methods'] = [];
                foreach ($methods as $method) {
                    $method = strtoupper(trim($method));
                    $handler['methods'][$method] = true;
                }
            }
        }
        return $endpoints;
    }

    public function get_namespaces()
    {
        return array_keys($this->namespaces);
    }

    public function get_route_options($route)
    {
        return $this->route_options[$route] ?? null;
    }

    /** The routes' methods and regexes, for the engine's fallback and the index. */
    public function match_request_to_handler($request)
    {
        $method = $request->get_method();
        $path = $request->get_route();
        $with_namespace = [];
        foreach ($this->get_namespaces() as $namespace) {
            if (str_starts_with(trim($path, '/'), $namespace)) {
                $with_namespace[] = $this->get_routes($namespace);
            }
        }
        if ($with_namespace) {
            $routes = array_merge(...$with_namespace);
        } else {
            $routes = $this->get_routes();
        }
        foreach ($routes as $route => $handlers) {
            $match = preg_match('@^' . $route . '$@i', $path, $matches);
            if (!$match) {
                continue;
            }
            $args = [];
            foreach ($matches as $param => $value) {
                if (!is_int($param)) {
                    $args[$param] = $value;
                }
            }
            foreach ($handlers as $handler) {
                $callback = $handler['callback'];
                if (empty($handler['methods'][$method])) {
                    continue;
                }
                if (!is_callable($callback)) {
                    return new WP_Error('rest_invalid_handler', 'The handler for the route is invalid.', ['status' => 500]);
                }
                $request->set_url_params($args);
                $request->set_attributes($handler);
                $defaults = [];
                foreach ($handler['args'] as $arg => $options) {
                    if (isset($options['default'])) {
                        $defaults[$arg] = $options['default'];
                    }
                }
                $request->set_default_params($defaults);
                return [$route, $handler];
            }
        }
        return new WP_Error('rest_no_route', 'No route was found matching the URL and request method.', ['status' => 404]);
    }

    public function dispatch($request)
    {
        $this->dispatching_requests[] = $request;
        $result = apply_filters('rest_pre_dispatch', null, $this, $request);
        if ($result !== null) {
            array_pop($this->dispatching_requests);
            return $result;
        }
        $error = null;
        $matched = $this->match_request_to_handler($request);
        if (is_wp_error($matched)) {
            if ($matched->get_error_code() === 'rest_no_route' && Runtime::booted()) {
                $engine = $this->engine_response($request);
                if ($engine !== null) {
                    array_pop($this->dispatching_requests);
                    return $engine;
                }
            }
            array_pop($this->dispatching_requests);
            return $this->error_to_response($matched);
        }
        [$route, $handler] = $matched;
        if (!is_callable($handler['callback'])) {
            $error = new WP_Error('rest_invalid_handler', 'The handler for the route is invalid.', ['status' => 500]);
        }
        if (!is_wp_error($error)) {
            $check_required = $request->has_valid_params();
            if (is_wp_error($check_required)) {
                $error = $check_required;
            } else {
                $check_sanitized = $request->sanitize_params();
                if (is_wp_error($check_sanitized)) {
                    $error = $check_sanitized;
                }
            }
        }
        $response = $this->respond_to_request($request, $route, $handler, $error);
        array_pop($this->dispatching_requests);
        return $response;
    }

    public function is_dispatching()
    {
        return (bool) $this->dispatching_requests;
    }

    protected function respond_to_request($request, $route, $handler, $response)
    {
        $response = apply_filters('rest_request_before_callbacks', $response, $handler, $request);
        if (!is_wp_error($response)) {
            if (!empty($handler['permission_callback'])) {
                $permission = call_user_func($handler['permission_callback'], $request);
                if (is_wp_error($permission)) {
                    $response = $permission;
                } elseif ($permission === false || $permission === null) {
                    $response = new WP_Error('rest_forbidden', 'Sorry, you are not allowed to do that.', ['status' => rest_authorization_required_code()]);
                }
            }
        }
        if (!is_wp_error($response)) {
            $dispatch_result = apply_filters('rest_dispatch_request', null, $request, $route, $handler);
            if ($dispatch_result !== null) {
                $response = $dispatch_result;
            } else {
                $response = call_user_func($handler['callback'], $request);
            }
        }
        $response = apply_filters('rest_request_after_callbacks', $response, $handler, $request);
        if (is_wp_error($response)) {
            $response = $this->error_to_response($response);
        } else {
            $response = rest_ensure_response($response);
        }
        $response->set_matched_route($route);
        $response->set_matched_handler($handler);
        return $response;
    }

    /** A core route answered by the engine's own REST layer, as the response object plugin code expects. */
    protected function engine_response(WP_REST_Request $request): ?WP_REST_Response
    {
        $runtime = Runtime::current();
        $route = '/' . ltrim((string) $request->get_route(), '/');
        $query = $request->get_query_params();
        $headers = [];
        foreach ($request->get_headers() as $name => $values) {
            $headers[str_replace('_', '-', $name)] = implode(',', $values);
        }
        $method = Method::tryFrom($request->get_method()) ?? Method::Get;
        $body = (string) ($request->get_body() ?? '');
        $form = $request->get_body_params();
        if ($body === '' && $form !== [] && !$request->is_json_content_type()) {
            $body = http_build_query($form);
            $headers['content-type'] = 'application/x-www-form-urlencoded';
        }
        $minnRequest = new Request($method, '/wp-json' . $route, $query, $headers, $runtime->request?->cookies ?? [], $body, $runtime->isSecure(), $runtime->request?->host ?? (string) parse_url(home_url(), PHP_URL_HOST), $form);
        $response = Api::forRequest($runtime->db, $minnRequest)->handle($route);
        $data = json_decode($response->body, true);
        if ($response->status === 404 && is_array($data) && ($data['code'] ?? '') === 'rest_no_route') {
            return null;
        }
        $data = self::attach_additional_fields($route, $data);
        $out = new WP_REST_Response($data, $response->status, $response->headers);
        $out->set_matched_route($route);
        return $out;
    }

    /** register_rest_field() additions for the object type behind a core route. */
    public static function attach_additional_fields(string $route, mixed $data): mixed
    {
        $fields = $GLOBALS['wp_rest_additional_fields'] ?? [];
        if ($fields === [] || !is_array($data) || !preg_match('#^/wp/v2/([a-z_-]+)(/\d+)?$#', $route, $m)) {
            return $data;
        }
        $base = $m[1];
        $type = null;
        foreach (Runtime::registry()->postTypes() as $name => $row) {
            if (($row['rest_base'] ?: $name) === $base) {
                $type = $name;
                break;
            }
        }
        if ($type === null) {
            foreach (Runtime::registry()->taxonomies() as $name => $row) {
                if (($row['rest_base'] ?: $name) === $base) {
                    $type = $name;
                    break;
                }
            }
        }
        if ($type === null) {
            $type = match ($base) { 'users' => 'user', 'comments' => 'comment', 'media' => 'attachment', default => null };
        }
        if ($type === null || empty($fields[$type])) {
            return $data;
        }
        $apply = static function (array $item) use ($fields, $type): array {
            foreach ($fields[$type] as $name => $options) {
                if (!empty($options['get_callback'])) {
                    $item[$name] = call_user_func($options['get_callback'], $item, $name, null, $type);
                }
            }
            return $item;
        };
        if (isset($m[2]) || isset($data['id'])) {
            return $apply($data);
        }
        return array_map(static fn ($item) => is_array($item) ? $apply($item) : $item, $data);
    }

    public function response_to_data($response, $embed)
    {
        $data = $response->get_data();
        $links = self::get_compact_response_links($response);
        if (!empty($links)) {
            $data['_links'] = $links;
        }
        return $data;
    }

    public static function get_response_links($response)
    {
        $links = $response->get_links();
        if (empty($links)) {
            return [];
        }
        $data = [];
        foreach ($links as $rel => $items) {
            $data[$rel] = [];
            foreach ($items as $item) {
                $attributes = $item['attributes'];
                $attributes['href'] = $item['href'];
                $data[$rel][] = $attributes;
            }
        }
        return $data;
    }

    public static function get_compact_response_links($response)
    {
        $links = self::get_response_links($response);
        if (empty($links)) {
            return [];
        }
        $curies = $response->get_curies();
        $used_curies = [];
        foreach ($links as $rel => $items) {
            foreach ($curies as $curie) {
                $href_prefix = substr($curie['href'], 0, strpos($curie['href'], '{rel}'));
                if (!str_starts_with($rel, $href_prefix)) {
                    continue;
                }
                $used_curies[$curie['name']] = $curie;
                $rel_regex = str_replace('\{rel\}', '(.+)', preg_quote($curie['href'], '!'));
                preg_match('!' . $rel_regex . '!', $rel, $matches);
                if ($matches) {
                    $new_rel = $curie['name'] . ':' . $matches[1];
                    $used_curies[$curie['name']] = $curie;
                    $links[$new_rel] = $items;
                    unset($links[$rel]);
                    break;
                }
            }
        }
        if (!empty($used_curies)) {
            $links['curies'] = array_values($used_curies);
        }
        return $links;
    }

    public function envelope_response($response, $embed)
    {
        $envelope = ['body' => $this->response_to_data($response, $embed), 'status' => $response->get_status(), 'headers' => $response->get_headers()];
        return rest_ensure_response(apply_filters('rest_envelope_response', $envelope, $response));
    }

    public function get_index($request)
    {
        $available = [
            'name' => get_option('blogname'),
            'description' => get_option('blogdescription'),
            'url' => get_option('siteurl'),
            'home' => home_url(),
            'gmt_offset' => get_option('gmt_offset'),
            'timezone_string' => get_option('timezone_string'),
            'namespaces' => array_keys($this->namespaces),
            'authentication' => [],
            'routes' => $this->get_data_for_routes($this->get_routes(), $request['context']),
        ];
        $response = new WP_REST_Response($available);
        $response->add_link('help', 'https://developer.wordpress.org/rest-api/');
        return apply_filters('rest_index', $response, $request);
    }

    public function get_namespace_index($request)
    {
        $namespace = $request['namespace'];
        if (!isset($this->namespaces[$namespace])) {
            return new WP_Error('rest_invalid_namespace', 'The specified namespace could not be found.', ['status' => 404]);
        }
        $routes = $this->namespaces[$namespace];
        $endpoints = array_intersect_key($this->get_routes(), $routes);
        $data = ['namespace' => $namespace, 'routes' => $this->get_data_for_routes($endpoints, $request['context'])];
        $response = rest_ensure_response($data);
        $response->add_link('up', rest_url('/'));
        return apply_filters('rest_namespace_index', $response, $request);
    }

    public function get_data_for_routes($routes, $context = 'view')
    {
        $available = [];
        foreach ($routes as $route => $callbacks) {
            $data = $this->get_data_for_route($route, $callbacks, $context);
            if (empty($data)) {
                continue;
            }
            $available[$route] = apply_filters('rest_endpoints_description', $data);
        }
        return apply_filters('rest_route_data', $available, $routes);
    }

    public function get_data_for_route($route, $callbacks, $context = 'view')
    {
        $data = ['namespace' => '', 'methods' => [], 'endpoints' => []];
        $route_options = $this->get_route_options($route);
        if ($route_options) {
            if (isset($route_options['namespace'])) {
                $data['namespace'] = $route_options['namespace'];
            }
            if (isset($route_options['schema']) && $context === 'help') {
                $data['schema'] = call_user_func($route_options['schema']);
            }
        }
        $allow_batch = false;
        foreach ($callbacks as $callback) {
            if (empty($callback['show_in_index'])) {
                continue;
            }
            $data['methods'] = array_merge($data['methods'], array_keys($callback['methods']));
            $endpoint_data = ['methods' => array_keys($callback['methods'])];
            $callback_batch = $callback['allow_batch'] ?? $allow_batch;
            if ($callback_batch) {
                $endpoint_data['allow_batch'] = $callback_batch;
            }
            if (isset($callback['args'])) {
                $endpoint_data['args'] = [];
                foreach ($callback['args'] as $key => $opts) {
                    if (is_string($opts)) {
                        $opts = [$opts => 0];
                    } elseif (!is_array($opts)) {
                        $opts = [];
                    }
                    $arg_data = ['required' => !empty($opts['required'])];
                    if (isset($opts['default'])) {
                        $arg_data['default'] = $opts['default'];
                    }
                    if (isset($opts['enum'])) {
                        $arg_data['enum'] = $opts['enum'];
                    }
                    if (isset($opts['description'])) {
                        $arg_data['description'] = $opts['description'];
                    }
                    if (isset($opts['type'])) {
                        $arg_data['type'] = $opts['type'];
                    }
                    if (isset($opts['items'])) {
                        $arg_data['items'] = $opts['items'];
                    }
                    foreach (['minimum', 'maximum', 'exclusiveMinimum', 'exclusiveMaximum', 'minLength', 'maxLength', 'pattern', 'format', 'properties', 'additionalProperties', 'oneOf', 'anyOf', 'minItems', 'maxItems', 'uniqueItems'] as $keyword) {
                        if (isset($opts[$keyword])) {
                            $arg_data[$keyword] = $opts[$keyword];
                        }
                    }
                    $required = $arg_data['required'];
                    unset($arg_data['required']);
                    $ordered = [];
                    foreach ($opts as $k => $v) {
                        if (array_key_exists($k, $arg_data)) {
                            $ordered[$k] = $arg_data[$k];
                        }
                    }
                    $ordered['required'] = $required;
                    $endpoint_data['args'][$key] = $ordered;
                }
            }
            $data['endpoints'][] = $endpoint_data;
            if (strpos($route, '(?P<') === false) {
                $data['_links'] = ['self' => [['href' => rest_url($route)]]];
            }
        }
        $data['methods'] = array_keys(array_flip($data['methods']));
        if (empty($data['methods'])) {
            return null;
        }
        return $data;
    }

    public function get_max_batch_size()
    {
        return apply_filters('rest_get_max_batch_size', 25);
    }

    public function serve_request($path = null)
    {
        return false;
    }

    public function set_status($code)
    {
        status_header($code);
    }

    public function send_header($key, $value)
    {
        header(sprintf('%s: %s', $key, str_replace(["\n", "\r"], '', (string) $value)));
    }

    public function send_headers($headers)
    {
        foreach ($headers as $key => $value) {
            $this->send_header($key, $value);
        }
    }

    public function remove_header($key)
    {
        header_remove($key);
    }

    public function get_raw_data()
    {
        return (string) (Runtime::current()->request?->body ?? '');
    }

    public function get_headers($server)
    {
        $headers = [];
        foreach ($server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headers[substr($key, 5)] = $value;
            }
        }
        return $headers;
    }

    public function get_json_last_error()
    {
        return json_last_error() === JSON_ERROR_NONE ? false : json_last_error_msg();
    }
}
