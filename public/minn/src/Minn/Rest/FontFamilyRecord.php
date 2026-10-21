<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Http\Refusal;
use Minn\Http\Request;

/**
 * A font family route names a font family: refused as an invalid post id
 * before the caller is judged, signed out or not, when the id is not a
 * wp_font_family post (a post of any other type, a face included).
 */
final class FontFamilyRecord implements Refusal
{
    /** Throws rest_post_invalid_id unless the family exists. */
    public static function refuse(Request $request): void
    {
        FontFaceRecord::require($request, '#/font-families/(\d+)$#', 'wp_font_family');
    }
}
