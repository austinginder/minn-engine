<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Objects\Errors;
use Minn\Objects\HttpResponse;
use Minn\Objects\RestRequest;
use Minn\Objects\RestResponse;
use Minn\Objects\RestServer;
use Minn\Runtime\Handoff;
use Minn\Runtime\Runtime;

/**
 * REST answers as the WordPress layer shapes them: any value made a
 * response, a list of errors made one (its status from the errors' data),
 * the REST server (made, and rest_api_init told, the first time it is
 * asked), the fields a request asked for kept, and the status that tells
 * a signed-out caller from one who may not.
 */
final class Responses
{
    /** A value as a REST response (rest_ensure_response): errors and responses as they are; an HTTP response's parts carried over. */
    public static function ensure(mixed $response): mixed
    {
        if ($response instanceof Errors || $response instanceof RestResponse) {
            return $response;
        }
        if ($response instanceof HttpResponse) {
            return Handoff::newRestResponse($response->get_data(), $response->get_status(), $response->get_headers());
        }
        return Handoff::newRestResponse($response);
    }

    /**
     * A list of errors as a REST response (rest_convert_error_to_response):
     * the first error the body, the rest as additional_errors; the status
     * the last one the errors' data name, 500 when none does.
     */
    public static function fromErrors(mixed $error): RestResponse
    {
        $status = array_reduce($error->get_all_error_data(), static fn (mixed $status, mixed $data): mixed => is_array($data) && isset($data['status']) ? $data['status'] : $status, 500);
        $errors = self::entries($error);
        $data = $errors[0];
        if (count($errors) > 1) {
            array_shift($errors);
            $data['additional_errors'] = $errors;
        }
        return Handoff::newRestResponse($data, $status);
    }

    /** The REST server (rest_get_server): made the first time, of the class wp_rest_server_class names, and rest_api_init told. */
    public static function server(): mixed
    {
        $server = $GLOBALS['wp_rest_server'] ?? null;
        if (!$server instanceof RestServer) {
            $class = Runtime::hooks()->filter('wp_rest_server_class', ['WP_REST_Server']);
            $server = new $class();
            $GLOBALS['wp_rest_server'] = $server;
            Runtime::hooks()->action('rest_api_init', [$server]);
        }
        return $server;
    }

    /** Whether a field is among those asked for (rest_is_field_included), a nested one by its parent or child. */
    public static function fieldIncluded(mixed $field, mixed $fields): bool
    {
        if (in_array($field, $fields, true)) {
            return true;
        }
        foreach ($fields as $accepted) {
            if (str_starts_with((string) $accepted, "{$field}.") || str_starts_with((string) $field, "{$accepted}.")) {
                return true;
            }
        }
        return false;
    }

    /** A response with only the fields the request's _fields asks for (rest_filter_response_fields), a list item by item. */
    public static function filterFields(mixed $response, mixed $server, mixed $request): mixed
    {
        $fields = $request instanceof RestRequest ? Fields::fromQuery(['_fields' => $request->get_param('_fields')]) : null;
        if (!$response instanceof RestResponse || $response->is_error() || $fields === null || !is_array($data = $response->get_data())) {
            return $response;
        }
        $isList = array_filter(array_keys($data), static fn (int|string $key): bool => !is_numeric($key)) === [];
        $response->set_data($isList ? array_map(static fn (mixed $item): mixed => is_array($item) ? $fields->apply($item) : $item, $data) : $fields->apply($data));
        return $response;
    }

    /** The status refusing a caller (rest_authorization_required_code): 403 for one signed in, 401 for one who is not. */
    public static function authorizationRequiredCode(): int
    {
        return Handoff::signedIn() ? 403 : 401;
    }

    /**
     * Each error as the REST body writes it: code, message, its code's last
     * data, and the earlier data as additional_data.
     *
     * @return list<array<string, mixed>>
     */
    private static function entries(mixed $error): array
    {
        $entries = [];
        foreach ((array) $error->errors as $code => $messages) {
            $all = $error->get_all_error_data($code);
            $last = array_pop($all);
            foreach ((array) $messages as $message) {
                $entries[] = ['code' => $code, 'message' => $message, 'data' => $last] + ($all ? ['additional_data' => $all] : []);
            }
        }
        return $entries;
    }
}
