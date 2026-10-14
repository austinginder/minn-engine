<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Http\Args;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Json;
use Minn\Objects\Errors;
use Minn\Objects\RestRequest;
use Minn\Objects\RestResponse;
use Minn\Objects\RestServer;
use Minn\RestError;
use Minn\Runtime\Handoff;
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
    private const DISPATCH_DONE = ['rest_filter_response_fields' => 10, 'rest_send_allow_header' => 10];

    /** rest_post_dispatch's defaults that touch one route only: elsewhere they hand the answer back as it came, so they are left out there. */
    private const ROUTE_SCOPED = ['_wp_connectors_rest_settings_dispatch' => [10, '/wp/v2/settings']];

    /** rest_pre_serve_request's defaults the engine does itself: oEmbed's XML (see oembedXml()). */
    private const SERVE_DONE = ['_oembed_rest_pre_serve_request' => 10, 'rest_send_cors_headers' => 10];

    /**
     * What plugin code decides before any route runs, engine routes
     * included, in the reference's order: rest_authentication_errors may
     * refuse the request, rest_pre_dispatch may answer it outright, and a
     * route a rest_endpoints filter removed is no route at all. Null lets
     * the engine's router proceed.
     */
    public static function gate(Request $request): ?Response
    {
        $error = Runtime::hooks()->filter('rest_authentication_errors', [null]);
        if ($error instanceof Errors) {
            return self::toResponse(Responses::fromErrors($error));
        }
        return self::predispatch($request);
    }

    /**
     * What plugin code decides before a route runs, for a request inside
     * another (an embedded link) as for the first: rest_pre_dispatch may
     * answer it outright, and a route a rest_endpoints filter removed is no
     * route at all. Null lets the router proceed.
     */
    public static function predispatch(Request $request): ?Response
    {
        $server = self::server();
        $wpRequest = self::wpRequest($request);
        $early = Runtime::hooks()->filter('rest_pre_dispatch', [null, $server, $wpRequest]);
        if ($early !== null) {
            // An early answer that names its route (an OPTIONS description) gets that route's Allow header.
            $early = self::ensure($early);
            if ((string) $early->get_matched_route() !== '') {
                self::allow($early, (string) $early->get_matched_route(), $wpRequest);
            }
            return self::toResponse($early);
        }
        if ($server->route_removed_by_filter($wpRequest)) {
            return Reply::error(\Minn\RestError::noRoute());
        }
        return null;
    }

    /** Null when the runtime has no route for the request either. */
    public static function dispatch(Request $request): ?Response
    {
        $server = self::server();
        $wpRequest = self::wpRequest($request);
        $matched = $server->match_request_to_handler($wpRequest);
        if ($matched instanceof Errors) {
            return null;
        }
        self::matched($request, (string) $matched[0], (array) $matched[1]);
        $result = self::ensure($server->dispatch($wpRequest));
        self::allow($result, (string) $matched[0], $wpRequest);
        // A plugin's answer keeps only its _fields, as the engine's own do (serve() then skips the filter).
        if (Runtime::hooks()->has('rest_post_dispatch', 'rest_filter_response_fields') === self::DISPATCH_DONE['rest_filter_response_fields']) {
            $result = Responses::filterFields($result, $server, $wpRequest);
        }
        return self::toResponse(self::ensure($result));
    }

    /**
     * The Allow header a plugin's route answers with, as the reference's
     * rest_send_allow_header sets it: every method of the route whose
     * handler's permission callback lets this request through (a handler
     * with none counts). Left alone when the handler set one itself.
     */
    private static function allow(RestResponse $result, string $route, RestRequest $wpRequest): void
    {
        $allowed = self::allowedMethods($route, $wpRequest);
        if (!isset($result->get_headers()['Allow']) && $allowed !== []) {
            $result->header('Allow', implode(', ', $allowed));
        }
    }

    /** The methods of a plugin's route whose handler lets this request through (a handler with no permission callback does). @return list<string> */
    public static function allowedMethods(string $route, RestRequest $wpRequest): array
    {
        $allowed = [];
        foreach ((array) (self::server()->get_routes()[$route] ?? []) as $handler) {
            $permitted = empty($handler['permission_callback']) || call_user_func($handler['permission_callback'], $wpRequest) === true;
            foreach (array_keys((array) ($handler['methods'] ?? [])) as $method) {
                $allowed[strtoupper((string) $method)] = $permitted;
            }
        }
        return array_keys(array_filter($allowed));
    }

    /**
     * The methods a self link's caller may use there, as the reference
     * hints them on every self link it serves (targetHints.allow): the
     * route the link names, each of its methods whose handler lets this
     * caller through; null when no route of the runtime's takes the link.
     * A route the engine answers itself is judged by its policies, as its
     * Allow header is (probe rest-registry-controllers).
     *
     * @return list<string>|null
     */
    public static function targetHints(string $href): ?array
    {
        $wpRequest = Handoff::restRequestFromUrl($href);
        if (!$wpRequest instanceof RestRequest) {
            return null;
        }
        $server = self::server();
        $matched = $server->match_request_to_handler($wpRequest);
        if ($matched instanceof Errors) {
            return null;
        }
        return $server->engine_route((string) $matched[0]) ? $server->engine_allowed($wpRequest) : self::allowedMethods((string) $matched[0], $wpRequest);
    }

    /** The engine's index plus the namespaces and routes the runtime holds. */
    public static function mergeIndex(Response $response): Response
    {
        $data = Json::decode($response->body, or: null);
        if (!is_array($data) || !isset($data['routes'])) {
            return $response;
        }
        $server = self::server();
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
        return new Response($response->status, $response->headers, Json::encode($data, Json::ESCAPED));
    }

    /** @var \WeakMap<Request, RestRequest>|null the one request object plugins see for one request, filters and actions alike */
    private static ?\WeakMap $requests = null;

    /** @var \WeakMap<Request, array{0: string, 1: array<string, mixed>}>|null the route and handler a request matched */
    private static ?\WeakMap $matches = null;

    /** @var \WeakMap<object, array{0: mixed, 1: Response|RestError}>|null what each object handed to plugins was made from, and how it looked */
    private static ?\WeakMap $origins = null;

    /** An in-process call's own request object stands for the engine's request: the server sets its route's parameters on it, as the reference's dispatch does. */
    public static function adopt(Request $request, RestRequest $wpRequest): void
    {
        self::$requests ??= new \WeakMap();
        self::$requests[$request] = $wpRequest;
    }

    /**
     * The request as the list's handler reads it: its parameters with their
     * defaults, sanitized by their schemas, as plugins' filters see it too.
     *
     * @param array<string, array<string, mixed>> $registered
     */
    public static function sanitized(Request $request, array $registered): RestRequest
    {
        $wp = self::wpRequest($request);
        $args = array_map(static fn (array $arg): array => array_diff_key($arg, [Args::HANDLER_VALIDATES => true]) + ['validate_callback' => 'rest_validate_request_arg', 'sanitize_callback' => 'rest_sanitize_request_arg'], $registered);
        $wp->set_attributes(['args' => $args] + (array) $wp->get_attributes());
        $wp->set_default_params(array_map(static fn (array $arg) => $arg['default'], array_filter($args, static fn (array $arg) => array_key_exists('default', $arg))));
        $wp->sanitize_params();
        return $wp;
    }

    /** The request as the runtime's server reads it, and as a REST filter or action hands it to plugins: the same object each time. */
    public static function wpRequest(Request $request): RestRequest
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
    public static function toWpError(RestError $error): Errors
    {
        $payload = $error->payload();
        $wpError = Handoff::newError($error->errorCode, $error->getMessage(), $payload['data']);
        self::remember($wpError, $error, [$wpError->errors, $wpError->error_data]);
        return $wpError;
    }

    /**
     * An engine answer as plugins handle one: its data decoded (an empty
     * object stays one), its links on the response rather than in the data,
     * its status and headers.
     */
    public static function toWp(Response $response): RestResponse
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
        $wpResponse = Handoff::newRestResponse($data, $response->status, self::wpHeaders($headers));
        $wpResponse->add_links($links);
        self::remember($wpResponse, $response, self::look($wpResponse));
        return $wpResponse;
    }

    /**
     * A route's own headers as the reference's response object holds them:
     * a list's totals are numbers, but the comments list's stay text.
     *
     * @param array<string, string> $headers
     * @return array<string, string|int>
     */
    public static function wpHeaders(array $headers, string $route = ''): array
    {
        if ($route === '/wp/v2/comments') {
            return $headers;
        }
        foreach (['X-WP-Total', 'X-WP-TotalPages'] as $name) {
            if (isset($headers[$name]) && ctype_digit((string) $headers[$name])) {
                $headers[$name] = (int) $headers[$name];
            }
        }
        return $headers;
    }

    /**
     * One item a response carries, as a response object: its data without
     * _links, the links on the response.
     *
     * @param array<string, mixed> $item
     */
    public static function itemResponse(array $item): RestResponse
    {
        $links = isset($item['_links']) && is_array($item['_links']) ? self::expand($item['_links']) : [];
        unset($item['_links']);
        $response = Handoff::newRestResponse($item, 200);
        $response->add_links($links);
        return $response;
    }

    /** Whatever the filters left, as the engine sends it; what they handed back untouched is the engine's own answer, byte for byte. */
    public static function fromWp(mixed $result): Response
    {
        if (is_object($result) && isset(self::$origins[$result])) {
            [$look, $origin] = self::$origins[$result];
            $now = $result instanceof Errors ? [$result->errors, $result->error_data] : self::look($result);
            if ($look === $now) {
                return $origin instanceof RestError ? Reply::error($origin) : $origin;
            }
        }
        return self::toResponse(self::ensure($result));
    }

    /** @return array<string, int> the rest_post_dispatch callbacks this request leaves out: the engine's own, and the route-scoped ones off their route */
    private static function dispatchDone(Request $request): array
    {
        $done = self::DISPATCH_DONE;
        foreach (self::ROUTE_SCOPED as $callback => [$priority, $route]) {
            if (self::wpRequest($request)->get_route() !== $route) {
                $done[$callback] = $priority;
            }
        }
        return $done;
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
        $xml = self::oembedXml($request, $response);
        if ($xml !== null) {
            return $xml;
        }
        $done = self::dispatchDone($request);
        if (!Runtime::hooks()->hasBeyond('rest_post_dispatch', $done) && !Runtime::hooks()->hasBeyond('rest_pre_serve_request', self::SERVE_DONE) && !Runtime::hooks()->has('rest_pre_echo_response')) {
            return $response;
        }
        $data = self::decode($response->body);
        if ($response->body === '' || ($data === null && trim($response->body) !== 'null')) {
            return $response;
        }
        $embedded = is_array($data) && array_key_exists('_embedded', $data) ? $data['_embedded'] : null;
        $wpRequest = self::wpRequest($request);
        $server = self::server();
        $result = self::toWp($response);
        if ($embedded !== null) {
            $plain = $result->get_data();
            unset($plain['_embedded']);
            $result->set_data($plain);
        }
        [$route, $handler] = self::$matches[$request] ?? [null, null];
        $result->set_matched_route($route);
        $result->set_matched_handler($handler);
        $result = Responses::ensure(Runtime::hooks()->filterWithout('rest_post_dispatch', [Responses::ensure($result), $server, $wpRequest], $done));
        $result = $result instanceof Errors ? Responses::fromErrors($result) : $result;
        $headers = Reply::HEADERS;
        foreach ((array) $result->get_headers() as $name => $value) {
            $headers[$name] = (string) $value;
        }
        ob_start();
        $served = Runtime::hooks()->filterWithout('rest_pre_serve_request', [false, $result, $wpRequest, $server], self::SERVE_DONE);
        $printed = (string) ob_get_clean();
        if ($served) {
            return new Response($result->get_status(), $headers, $printed, $response->cookies, $response->afterSend);
        }
        $out = $server->response_to_data($result, false);
        if ($embedded !== null && is_array($out)) {
            $out['_embedded'] = $embedded;
        }
        $out = Runtime::hooks()->filter('rest_pre_echo_response', [$out, $server, $wpRequest]);
        return new Response($result->get_status(), $headers, Json::encode($out, Json::ESCAPED), $response->cookies, $response->afterSend);
    }

    /**
     * Whether the answer went out as oEmbed's XML. The reference prints it
     * in rest_pre_serve_request ahead of rest_send_cors_headers, so none of
     * the CORS headers follow it, Vary: Origin among them.
     */
    public static function servedAsXml(Request $request, Response $response): bool
    {
        return self::asksForXml($request) && str_starts_with((string) ($response->headers['Content-Type'] ?? ''), 'text/xml');
    }

    /** The embed route only: the proxy answers JSON whatever its format. */
    private static function asksForXml(Request $request): bool
    {
        return ($request->query['format'] ?? '') === 'xml' && $request->path === '/oembed/1.0/embed';
    }

    /**
     * An embed asked for as XML (format=xml), as the reference's
     * _oembed_rest_pre_serve_request serves it over HTTP: the data, or the
     * error (its code, message and data), as an oembed document, text/xml,
     * with the status it had. Null for anything else.
     */
    private static function oembedXml(Request $request, Response $response): ?Response
    {
        if (!self::asksForXml($request)) {
            return null;
        }
        $data = Json::decode($response->body, or: null);
        if (!is_array($data)) {
            return null;
        }
        $headers = ['Content-Type' => 'text/xml; charset=' . Runtime::options()->filtered('blog_charset')] + $response->headers;
        return new Response($response->status, $headers, (string) \_oembed_create_xml($data), $response->cookies, $response->afterSend);
    }

    /** What a response looks like to the code that may change it. @return array<int, mixed> */
    private static function look(RestResponse $response): array
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
                // As a plugin hands add_links() one: the href and the link's attributes beside it.
                $item = (array) $item;
                $out[$rel][] = ['href' => (string) ($item['href'] ?? '')] + $item;
            }
        }
        return $out;
    }

    private static function newWpRequest(Request $request): RestRequest
    {
        $wpRequest = Handoff::newRestRequest($request->method->value, $request->path);
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
    public static function ensure(mixed $result): RestResponse
    {
        $response = Responses::ensure($result);
        return $response instanceof Errors ? Responses::fromErrors($response) : $response;
    }

    /** The runtime's response as the engine sends it, with the reference's header set. */
    /** The runtime's REST server, the one door to it (built, with rest_api_init, on first use). */
    public static function server(): RestServer
    {
        return Responses::server();
    }

    private static function toResponse(RestResponse $response): Response
    {
        $data = self::server()->response_to_data($response, false);
        $headers = Reply::HEADERS;
        foreach ((array) $response->get_headers() as $name => $value) {
            $headers[$name] = (string) $value;
        }
        return new Response($response->get_status(), $headers, Json::encode($data, Json::ESCAPED));
    }
}
