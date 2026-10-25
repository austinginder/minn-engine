<?php

/**
 * The font library's helpers (probe fonts): a family list sanitized (names
 * of more than letters and hyphens quoted), the slug that identifies a face, data
 * kept to a schema of sanitizers, and the font types an upload may carry.
 */
class WP_Font_Utils
{
    private static function maybe_add_quotes($item)
    {
        return Minn\Theme\FontNames::quoted($item);
    }

    public static function sanitize_font_family($font_family)
    {
        return Minn\Theme\FontNames::family($font_family);
    }

    public static function get_font_face_slug($settings)
    {
        return Minn\Theme\FontNames::faceSlug($settings);
    }

    /** Only the keys the schema names, each through its rule, as the reference sanitizes a font tree (Minn\Theme\FontSchema). */
    public static function sanitize_from_schema($tree, $schema)
    {
        return Minn\Theme\FontSchema::sanitize($tree, $schema);
    }

    public static function get_allowed_font_mime_types()
    {
        return Minn\Theme\FontNames::MIME_TYPES;
    }
}
