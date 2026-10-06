<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Http\Request;
use Minn\Http\Response;
use Minn\RestError;
use Minn\Runtime\Runtime;

/**
 * Routes plugin code registered with register_rest_route(), answered
 * through the runtime's server after the engine's own routes have had
 * their turn; the runtime's say before the engine answers at all (an
 * authentication refusal, a pre-dispatch answer, a removed endpoint);
 * and the runtime's namespaces folded into the index.
 */
final class RuntimeRoutes
{
    /** rest_post_dispatch's defaults the engine does itself: every answer is cut to its _fields before it is served. */
    private const DISPATCH_DONE = ['rest_filter_response_fields' => 10];

    /**
     * What plugin code decides before any route runs, engine routes
     * included, in the reference's order: rest_authentication_errors may
     * refuse the request, rest_pre_dispatch may answer it outright, and a
     * route a rest_endpoints filter removed is no route at all. Null lets
     * the engine's router proceed.
     */
    public static function gate(Request $request): ?Response
    {
        $error = \apply_filters('rest_authentication_errors', null);
        if ($error instanceof \WP_Error) {
            return self::toResponse(\rest_convert_error_to_response($error));
        }
        $server = \rest_get_server();
        $wpRequest = self::wpRequest($request);
        $early = \apply_filters('rest_pre_dispatch', null, $server, $wpRequest);
        if ($early !== null) {
            return self::toResponse(self::ensure($early));
        }
        if ($server->route_removed_by_filter($wpRequest)) {
            return Reply::error(\Minn\RestError::noRoute());
        }
        return null;
    }

    /** Null when the runtime has no route for the request either. */
    public static function dispatch(Request $request): ?Response
    {
        $server = \rest_get_server();
        $wpRequest = self::wpRequest($request);
        $matched = $server->match_request_to_handler($wpRequest);
        if ($matched instanceof \WP_Error) {
            return null;
        }
        self::matched($request, (string) $matched[0], (array) $matched[1]);
        $result = self::ensure($server->dispatch($wpRequest));
        // A plugin's answer keeps only its _fields, as the engine's own do (serve() then skips the filter).
        if (\has_filter('rest_post_dispatch', 'rest_filter_response_fields') === self::DISPATCH_DONE['rest_filter_response_fields']) {
            $result = \rest_filter_response_fields($result, $server, $wpRequest);
        }
        return self::toResponse(self::ensure($result));
    }

    /** The engine's index plus the namespaces and routes the runtime holds. */
    public static function mergeIndex(Response $response): Response
    {
        $data = json_decode($response->body, true);
        if (!is_array($data) || !isset($data['routes'])) {
            return $response;
        }
        $server = \rest_get_server();
        $runtime = $server->get_data_for_routes($server->get_routes(), 'view');
        unset($runtime['/']);
        // A namespace index carries only its own namespace's routes and no namespace list.
        $scope = isset($data['namespace']) ? (string) $data['namespace'] : null;
        if ($scope === null) {
            foreach ($server->get_namespaces() as $namespace) {
                if (!in_array($namespace, $data['namespaces'] ?? [], true)) {
                    $data['namespaces'][] = $namespace;
                }
            }
        }
        foreach ($runtime as $route => $description) {
            if ($scope !== null && (string) ($description['namespace'] ?? '') !== $scope) {
                continue;
            }
            if (!isset($data['routes'][$route])) {
                $data['routes'][$route] = $description;
            }
        }
        return new Response($response->status, $response->headers, (string) json_encode($data));
    }

    /** @var \WeakMap<Request, \WP_REST_Request>|null the one request object plugins see for one request, filters and actions alike */
    private static ?\WeakMap $requests = null;

    /** @var \WeakMap<Request, array{0: string, 1: array<string, mixed>}>|null the route and handler a request matched */
    private static ?\WeakMap $matches = null;

    /** @var \WeakMap<object, array{0: mixed, 1: Response|RestError}>|null what each object handed to plugins was made from, and how it looked */
    private static ?\WeakMap $origins = null;

    /** The request as the runtime's server reads it, and as a REST filter or action hands it to plugins: the same object each time. */
    public static function wpRequest(Request $request): \WP_REST_Request
    {
        self::$requests ??= new \WeakMap();
        return self::$requests[$request] ??= self::newWpRequest($request);
    }

    /**
     * Keeps the route and handler a request matched, for the response plugins see at serving.
     *
     * @param array<string, mixed> $handler
     */
    public static function matched(Request $request, string $route, array $handler): void
    {
        self::$matches ??= new \WeakMap();
        self::$matches[$request] = [$route, $handler];
    }

    /** An engine refusal as plugins handle one. */
    public static function toWpError(RestError $error): \WP_Error
    {
        $payload = $error->payload();
        $wpError = new \WP_Error($error->errorCode, $error->getMessage(), $payload['data']);
        self::remember($wpError, $error, [$wpError->errors, $wpError->error_data]);
        return $wpError;
    }

    /**
     * An engine answer as plugins handle one: its data decoded (an empty
     * object stays one), its links on the response rather than in the data,
     * its status and headers.
     */
    public static function toWp(Response $response): \WP_REST_Response
    {
        $data = self::decode($response->body);
        $links = [];
        if (is_array($data) && isset($data['_links']) && is_array($data['_links'])) {
            $links = self::expand($data['_links']);
            unset($data['_links']);
        }
        $headers = $response->headers;
        foreach (array_keys(Reply::HEADERS) as $name) {
            unset($headers[$name]);
        }
        $wpResponse = new \WP_REST_Response($data, $response->status, $headers);
        $wpResponse->add_links($links);
        self::remember($wpResponse, $response, self::look($wpResponse));
        return $wpResponse;
    }

    /**
     * One item a response carries, as a response object: its data without
     * _links, the links on the response.
     *
     * @param array<string, mixed> $item
     */
    public static function itemResponse(array $item): \WP_REST_Response
    {
        $links = isset($item['_links']) && is_array($item['_links']) ? self::expand($item['_links']) : [];
        unset($item['_links']);
        $response = new \WP_REST_Response($item, 200);
        $response->add_links($links);
        return $response;
    }

    /** Whatever the filters left, as the engine sends it; what they handed back untouched is the engine's own answer, byte for byte. */
    public static function fromWp(mixed $result): Response
    {
        if (is_object($result) && isset(self::$origins[$result])) {
            [$look, $origin] = self::$origins[$result];
            $now = $result instanceof \WP_Error ? [$result->errors, $result->error_data] : self::look($result);
            if ($look === $now) {
                return $origin instanceof RestError ? Reply::error($origin) : $origin;
            }
        }
        return self::toResponse(self::ensure($result));
    }

    /**
     * The server's last word on any REST answer, engine or plugin route,
     * as the reference serves one: rest_post_dispatch may change it,
     * rest_pre_serve_request may serve it itself (what it prints is the
     * body), and rest_pre_echo_response may rewrite the data echoed. With
     * nothing hooked, the answer goes out as it is.
     */
    public static function serve(Request $request, Response $response): Response
    {
        if (!Runtime::hooks()->hasBeyond('rest_post_dispatch', self::DISPATCH_DONE) && !\has_filter('rest_pre_serve_request') && !\has_filter('rest_pre_echo_response')) {
            return $response;
        }
        $data = self::decode($response->body);
        if ($response->body === '' || ($data === null && trim($response->body) !== 'null')) {
            return $response;
        }
        $embedded = is_array($data) && array_key_exists('_embedded', $data) ? $data['_embedded'] : null;
        $wpRequest = self::wpRequest($request);
        $server = \rest_get_server();
        $result = self::toWp($response);
        if ($embedded !== null) {
            $plain = $result->get_data();
            unset($plain['_embedded']);
            $result->set_data($plain);
        }
        [$route, $handler] = self::$matches[$request] ?? [null, null];
        $result->set_matched_route($route);
        $result->set_matched_handler($handler);
        $result = \rest_ensure_response(Runtime::hooks()->filterWithout('rest_post_dispatch', [\rest_ensure_response($result), $server, $wpRequest], self::DISPATCH_DONE));
        $result = $result instanceof \WP_Error ? \rest_convert_error_to_response($result) : $result;
        $headers = Reply::HEADERS;
        foreach ((array) $result->get_headers() as $name => $value) {
            $headers[$name] = (string) $value;
        }
        ob_start();
        $served = \apply_filters('rest_pre_serve_request', false, $result, $wpRequest, $server);
        $printed = (string) ob_get_clean();
        if ($served) {
            return new Response($result->get_status(), $headers, $printed, $response->cookies, $response->afterSend);
        }
        $out = $server->response_to_data($result, false);
        if ($embedded !== null && is_array($out)) {
            $out['_embedded'] = $embedded;
        }
        $out = \apply_filters('rest_pre_echo_response', $out, $server, $wpRequest);
        return new Response($result->get_status(), $headers, (string) json_encode($out), $response->cookies, $response->afterSend);
    }

    /** What a response looks like to the code that may change it. @return array<int, mixed> */
    private static function look(\WP_REST_Response $response): array
    {
        return [$response->get_data(), $response->get_status(), $response->get_headers(), $response->get_links()];
    }

    private static function remember(object $made, Response|RestError $origin, array $look): void
    {
        self::$origins ??= new \WeakMap();
        self::$origins[$made] = [$look, $origin];
    }

    /** JSON as arrays, as plugins read REST data, with an empty object kept an object so it is sent back as one. */
    private static function decode(string $json): mixed
    {
        $convert = static function (mixed $value) use (&$convert): mixed {
            if ($value instanceof \stdClass) {
                $vars = get_object_vars($value);
                return $vars === [] ? $value : array_map($convert, $vars);
            }
            return is_array($value) ? array_map($convert, $value) : $value;
        };
        return $convert(json_decode($json, false));
    }

    /**
     * Compacted link relations ("wp:term") back to the relations a response
     * holds ("https://api.w.org/term"); the CURIE list itself is rebuilt
     * when the response is compacted again.
     *
     * @param array<string, mixed> $links
     * @return array<string, list<array<string, mixed>>>
     */
    private static function expand(array $links): array
    {
        $curies = [];
        foreach ((array) ($links['curies'] ?? []) as $curie) {
            if (is_array($curie) && isset($curie['name'], $curie['href'])) {
                $curies[(string) $curie['name']] = (string) $curie['href'];
            }
        }
        unset($links['curies']);
        $out = [];
        foreach ($links as $rel => $items) {
            $rel = (string) $rel;
            $colon = strpos($rel, ':');
            if ($colon !== false && isset($curies[substr($rel, 0, $colon)])) {
                $rel = str_replace('{rel}', substr($rel, $colon + 1), $curies[substr($rel, 0, $colon)]);
            }
            foreach ((array) $items as $item) {
                $item = (array) $item;
                $href = (string) ($item['href'] ?? '');
                unset($item['href']);
                $out[$rel][] = ['href' => $href, 'attributes' => $item];
            }
        }
        return $out;
    }

    private static function newWpRequest(Request $request): \WP_REST_Request
    {
        $wpRequest = new \WP_REST_Request($request->method->value, $request->path);
        $wpRequest->set_query_params($request->query);
        $wpRequest->set_body_params($request->form);
        $wpRequest->set_file_params($request->files);
        $headers = [];
        foreach ($request->headers as $name => $value) {
            $headers[$name] = $value;
        }
        $wpRequest->set_headers($headers);
        $wpRequest->set_body($request->body);
        return $wpRequest;
    }

    /** A callback's return as a response object, an error converted. */
    public static function ensure(mixed $result): \WP_REST_Response
    {
        $response = \rest_ensure_response($result);
        return $response instanceof \WP_Error ? \rest_convert_error_to_response($response) : $response;
    }

    /** The runtime's response as the engine sends it, with the reference's header set. */
    private static function toResponse(\WP_REST_Response $response): Response
    {
        $data = \rest_get_server()->response_to_data($response, false);
        $headers = Reply::HEADERS;
        foreach ((array) $response->get_headers() as $name => $value) {
            $headers[$name] = (string) $value;
        }
        return new Response($response->get_status(), $headers, (string) json_encode($data));
    }
}
