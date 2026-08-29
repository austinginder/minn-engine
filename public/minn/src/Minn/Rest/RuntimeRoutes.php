<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Http\Request;
use Minn\Http\Response;

/**
 * Routes plugin code registered with register_rest_route(), answered
 * through the runtime's server after the engine's own routes have had
 * their turn; and the runtime's namespaces folded into the index.
 */
final class RuntimeRoutes
{
    /** Null when the runtime has no route for the request either. */
    public static function dispatch(Request $request): ?Response
    {
        $server = \rest_get_server();
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
        $matched = $server->match_request_to_handler($wpRequest);
        if ($matched instanceof \WP_Error) {
            return null;
        }
        $response = $server->dispatch($wpRequest);
        $response = \rest_ensure_response($response);
        if ($response instanceof \WP_Error) {
            $response = \rest_convert_error_to_response($response);
        }
        $data = $server->response_to_data($response, false);
        $headers = Reply::HEADERS;
        foreach ((array) $response->get_headers() as $name => $value) {
            $headers[$name] = (string) $value;
        }
        return new Response($response->get_status(), $headers, (string) json_encode($data));
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
        foreach ($server->get_namespaces() as $namespace) {
            if (!in_array($namespace, $data['namespaces'] ?? [], true)) {
                $data['namespaces'][] = $namespace;
            }
        }
        foreach ($runtime as $route => $description) {
            if (!isset($data['routes'][$route])) {
                $data['routes'][$route] = $description;
            }
        }
        return new Response($response->status, $response->headers, (string) json_encode($data));
    }
}
