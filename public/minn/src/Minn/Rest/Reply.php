<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Http\Request;
use Minn\Http\Response;
use Minn\RestError;

/**
 * JSON responses in the reference's shape: its header set, its json_encode
 * flags (slashes escaped), the _fields filter, and the pagination headers
 * on lists.
 */
final class Reply
{
    public const HEADERS = [
        'Content-Type' => 'application/json; charset=UTF-8',
        'X-Content-Type-Options' => 'nosniff',
        'Access-Control-Expose-Headers' => 'X-WP-Total, X-WP-TotalPages, Link',
        'Access-Control-Allow-Headers' => 'Authorization, X-WP-Nonce, Content-Disposition, Content-MD5, Content-Type',
        'Allow' => 'GET',
    ];

    /** One item, shaped by the request's own _fields: what nearly every handler ends with. */
    public static function answer(Request $request, mixed $data, int $status = 200): Response
    {
        return self::item($data, Fields::fromQuery($request->query), $status);
    }

    /** One object as a response, the selected fields applied. */
    public static function item(mixed $data, ?Fields $fields, int $status = 200): Response
    {
        if ($fields !== null && !$fields->deferred && is_array($data) && $status < 400) {
            $data = $fields->apply($data);
        }
        return new Response($status, self::HEADERS, (string) json_encode($data));
    }

    /**
     * A list response with the total and page-count headers.
     *
     * @param list<array> $rows
     */
    public static function list(array $rows, int $total, int $totalPages, ?Fields $fields): Response
    {
        if ($fields !== null) {
            $rows = array_map(static fn (array $row) => $fields->apply($row), $rows);
        }
        return new Response(
            200,
            self::HEADERS + ['X-WP-Total' => (string) $total, 'X-WP-TotalPages' => (string) $totalPages],
            (string) json_encode($rows),
        );
    }

    /** A REST error as the reference's error body. */
    public static function error(RestError $error): Response
    {
        return new Response($error->status, self::HEADERS, (string) json_encode($error->payload()));
    }
}
