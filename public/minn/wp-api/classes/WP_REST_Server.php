<?php

use Minn\Rest\RuntimePrepare;
use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Rest\AdditionalFields;
use Minn\Rest\Api;
use Minn\Rest\BatchRequest;
use Minn\Rest\Links;
use Minn\Rest\RouteIndex;
use Minn\Rest\RouteMatch;
use Minn\Rest\RouteTable;
use Minn\Runtime\Refusal;
use Minn\Runtime\Runtime;

/**
 * The route registry and dispatcher plugin code registers into. Routes it
 * does not hold are answered by the engine's own REST layer.
 */
#[AllowDynamicProperties]
class WP_REST_Server
{
    const READABLE = 'GET';
    const CREATABLE = 'POST';
    const EDITABLE = 'POST, PUT, PATCH';
    const DELETABLE = 'DELETE';
    const ALLMETHODS = 'GET, POST, PUT, PATCH, DELETE';

    protected $namespaces = [];
    protected $endpoints = [];
    protected ?array $engine_endpoints = null;
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
        $endpoints = $this->endpoints + $this->engine_endpoints();
        if ($route_namespace) {
            $endpoints = wp_list_filter($endpoints, ['namespace' => $route_namespace]);
        }
        [$routes, $options] = RouteTable::normalise((array) apply_filters('rest_endpoints', $endpoints));
        foreach ($options as $route => $found) {
            $this->route_options[$route] = $found + ($this->route_options[$route] ?? []);
        }
        return $routes;
    }

    public function get_namespaces()
    {
        $namespaces = array_keys($this->namespaces);
        foreach ($this->engine_endpoints() as $handlers) {
            if (($handlers['namespace'] ?? '') !== '' && !in_array($handlers['namespace'], $namespaces, true)) {
                $namespaces[] = $handlers['namespace'];
            }
        }
        return $namespaces;
    }

    /** Whether a route pattern is one of the engine's own, which the engine answers itself. */
    public function engine_route(string $route): bool
    {
        return isset($this->engine_endpoints()[$route]) && !isset($this->endpoints[$route]);
    }

    /** The engine's own routes as table entries, so plugin code that reads the table sees the whole site; each answers through the engine. */
    protected function engine_endpoints(): array
    {
        if ($this->engine_endpoints !== null) {
            return $this->engine_endpoints;
        }
        $this->engine_endpoints = [];
        $map = Runtime::booted() ? Runtime::current()->get('engine_routes') : null;
        // Outside a web request (WP-CLI, a probe) nothing handed the table over: it is read off the API directly.
        $map ??= Runtime::booted() ? Api::forRequest(Runtime::current()->db, new Request(Method::Get, '/wp-json/', [], [], [], '', Runtime::current()->isSecure(), (string) parse_url(home_url(), PHP_URL_HOST)))->routes() : null;
        $map = $map instanceof Closure ? $map() : $map;
        foreach (is_array($map) ? $map : [] as $route => $methods) {
            if ($route === '/' || isset($this->endpoints[$route])) {
                continue;
            }
            $methods = in_array('*', $methods, true) ? ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] : $methods;
            $this->engine_endpoints[$route] = [
                ['methods' => implode(',', $methods), 'callback' => [$this, 'engine_callback'], 'permission_callback' => '__return_true', 'args' => []],
                'namespace' => preg_match('#^/([^/]+/v\d+)#', $route, $m) ? $m[1] : '',
            ];
        }
        return $this->engine_endpoints;
    }

    /** Whether a rest_endpoints filter dropped the route that would answer this request, so the engine must not answer it either. */
    public function route_removed_by_filter($request): bool
    {
        $all = array_keys($this->endpoints + $this->engine_endpoints());
        $kept = array_keys($this->get_routes());
        $path = (string) $request->get_route();
        foreach (array_diff($all, $kept) as $route) {
            if (@preg_match('@^' . $route . '$@i', $path) === 1) {
                return true;
            }
        }
        return false;
    }

    public function engine_callback($request)
    {
        return $this->engine_response($request) ?? new WP_Error('rest_no_route', 'No route was found matching the URL and request method.', ['status' => 404]);
    }

    public function get_route_options($route)
    {
        return $this->route_options[$route] ?? null;
    }

    /** The routes' methods and regexes, for the engine's fallback and the index. */
    public function match_request_to_handler($request)
    {
        $match = RouteMatch::find($this->get_namespaces(), fn (string $namespace) => $this->get_routes($namespace), $request->get_method(), $request->get_route());
        if ($match instanceof Refusal) {
            return new WP_Error($match->code, $match->message, $match->data);
        }
        $request->set_url_params($match['params']);
        $request->set_attributes($match['handler']);
        $request->set_default_params($match['defaults']);
        return [$match['route'], $match['handler']];
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
        $api = Api::forRequest($runtime->db, $minnRequest);
        // An in-process call carries no nonce; it runs as whoever is current now
        // (a plugin may have switched with wp_set_current_user), with the outer
        // request's session when that is the same user.
        $userId = get_current_user_id();
        if ($userId > 0) {
            $api->actingAs($userId, $userId === ($runtime->reader?->userId ?? 0) ? (string) $runtime->reader->sessionToken : '');
        }
        $response = RuntimePrepare::during($request, static fn () => $api->handleEngineOnly($route, $request));
        if ($response === null) {
            return null;
        }
        $data = json_decode($response->body, true);
        if ($response->status === 404 && is_array($data) && ($data['code'] ?? '') === 'rest_no_route') {
            return null;
        }
        $data = self::attach_additional_fields($route, $data);
        // The route's own headers (Allow, X-WP-Total, Location...), not the transport's, which serving adds.
        $out = new WP_REST_Response($data, $response->status, array_diff_key($response->headers, Minn\Rest\Reply::HEADERS, ['Vary' => true, 'X-Robots-Tag' => true]));
        $out->set_matched_route($route);
        return $out;
    }

    /** register_rest_field() additions for the object type behind a core route. */
    public static function attach_additional_fields(string $route, mixed $data): mixed
    {
        $fields = $GLOBALS['wp_rest_additional_fields'] ?? [];
        $target = $fields === [] || !is_array($data) ? null : AdditionalFields::typeForRoute($route, Runtime::registry());
        if ($target === null || empty($fields[$target[0]])) {
            return $data;
        }
        return AdditionalFields::apply($fields[$target[0]], $target[0], $target[1], $data, static fn ($callback, array $item, string $name, string $type) => call_user_func($callback, $item, $name, null, $type));
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
                $data[$rel][] = Links::item((string) $rel, (string) $item['href'], (array) $item['attributes']);
            }
        }
        return $data;
    }

    public static function get_compact_response_links($response)
    {
        return Links::compact((array) self::get_response_links($response), (array) $response->get_curies());
    }

    public function envelope_response($response, $embed)
    {
        $envelope = ['body' => $this->response_to_data($response, $embed), 'status' => $response->get_status(), 'headers' => $response->get_headers()];
        return rest_ensure_response(apply_filters('rest_envelope_response', $envelope, $response));
    }

    /**
     * Runs several requests in one round trip. Captured shape: HTTP 207
     * with `responses`, one enveloped {body, status, headers} per entry in
     * request order. Which methods a batch accepts is the route's own
     * schema (the Store API allows POST, PUT, PATCH and DELETE), so a bad
     * method is refused by parameter validation before this runs.
     */
    public function serve_batch_request_v1($batch_request)
    {
        $responses = [];
        foreach (BatchRequest::describe($batch_request['requests'] ?? []) as $described) {
            $single = new WP_REST_Request($described['method'], $described['path']);
            if ($described['query'] !== []) {
                $single->set_query_params($described['query']);
            }
            if ($described['body'] !== null) {
                $single->set_body_params($described['body']);
            }
            if ($described['headers'] !== null) {
                $single->set_headers($described['headers']);
            }
            $responses[] = $this->envelope_response($this->dispatch($single), false)->get_data();
        }
        return new WP_REST_Response(['responses' => $responses], 207);
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
        return RouteIndex::describe((string) $route, $callbacks, $this->get_route_options($route) ?: [], (string) $context, static fn (string $route) => rest_url($route));
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
