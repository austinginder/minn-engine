<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Http\Request;
use Minn\Http\Response;

/**
 * Routes plugin code registered with register_rest_route(), answered
 * through the runtime's server after the engine's own routes have had
 * their turn; the runtime's say before the engine answers at all (an
 * authentication refusal, a pre-dispatch answer, a removed endpoint);
 * and the runtime's namespaces folded into the index.
 */
final class RuntimeRoutes
{
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
        return self::toResponse(self::ensure($server->dispatch($wpRequest)));
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

    /** The request as the runtime's server reads it. */
    private static function wpRequest(Request $request): \WP_REST_Request
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
    private static function ensure(mixed $result): \WP_REST_Response
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
