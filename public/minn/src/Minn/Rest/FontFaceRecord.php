<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Content\Posts;
use Minn\Http\Refusal;
use Minn\Http\Request;
use Minn\RestError;
use Minn\Runtime\Runtime;

/**
 * A font face route names a font face: refused as an invalid post id
 * before the caller is judged, signed out or not, when the id (the
 * query's, else the path's, as the reference reads a parameter) is not a
 * wp_font_face post. Its family is judged once the caller has been.
 */
final class FontFaceRecord implements Refusal
{
    /** Throws rest_post_invalid_id unless the face exists. */
    public static function refuse(Request $request): void
    {
        self::require($request, '#/font-faces/(\d+)$#', 'wp_font_face');
    }

    /** Throws rest_post_invalid_id unless the id the path ends with (or the query's id) is a post of the type. */
    public static function require(Request $request, string $pattern, string $type): void
    {
        $given = $request->query['id'] ?? null;
        $id = is_scalar($given) && is_numeric(trim((string) $given))
            ? (int) trim((string) $given)
            : (preg_match($pattern, $request->path, $match) === 1 ? (int) $match[1] : 0);
        $post = $id > 0 ? (new Posts(Runtime::current()->db))->find($id) : null;
        if ($post === null || $post->type !== $type) {
            throw new RestError('rest_post_invalid_id', 'Invalid post ID.', 404);
        }
    }
}
