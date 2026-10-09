<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Http\Refusal;
use Minn\Http\Request;
use Minn\RestError;

/**
 * A post created with an id names one that exists: refused with
 * rest_post_exists before the caller is judged, even signed out (probe
 * patch-security). An id of 0 passes here and is refused later, as an
 * invalid post id.
 */
final class ExistingPost implements Refusal
{
    /** Throws rest_post_exists for a non-empty id in the body or the query. */
    public static function refuse(Request $request): void
    {
        if (!empty($request->json()['id'] ?? $request->query['id'] ?? null)) {
            throw new RestError('rest_post_exists', 'Cannot create existing post.', 400);
        }
    }
}
