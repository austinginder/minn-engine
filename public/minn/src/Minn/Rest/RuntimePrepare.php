<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Runtime\Runtime;

/**
 * An item a REST response carries, through the filter the reference runs
 * as it prepares one (rest_prepare_{type}, rest_prepare_attachment,
 * rest_prepare_user, rest_prepare_comment, rest_prepare_{taxonomy}): the
 * item as a response object, its links on the response rather than in its
 * data, handed with the object it describes and the request; what the
 * filter leaves is the item. With nothing hooked, or a response handed back
 * untouched, the item is Minn's own, byte for byte.
 */
final class RuntimePrepare
{
    /** Remembers the request a REST call is answering, for the filters its items pass through (on the request's own runtime). */
    public static function answering(\WP_REST_Request $request): void
    {
        Runtime::current()->set('rest_prepare_request', $request);
    }

    /**
     * One item through a rest_prepare filter.
     *
     * @param array<string, mixed> $item
     * @param \Closure(): mixed $described the object the item describes, fetched only when a plugin listens
     * @return array<string, mixed>
     */
    public static function item(string $filter, array $item, \Closure $described): array
    {
        if (!Runtime::booted() || !\has_filter($filter)) {
            return $item;
        }
        $response = RuntimeRoutes::itemResponse($item);
        $before = [$response->get_data(), $response->get_links()];
        $request = Runtime::current()->get('rest_prepare_request');
        $request = $request instanceof \WP_REST_Request ? $request : new \WP_REST_Request('GET', '/');
        $filtered = \rest_ensure_response(\apply_filters($filter, $response, $described(), $request));
        if ($filtered instanceof \WP_Error) {
            return $item;
        }
        if ($filtered === $response && [$response->get_data(), $response->get_links()] === $before) {
            return $item;
        }
        $data = \rest_get_server()->response_to_data($filtered, false);
        return is_array($data) ? $data : $item;
    }
}
