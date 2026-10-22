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

    /** Only the keys the schema names, each through its sanitizer; lists of objects take the schema's first entry. */
    public static function sanitize_from_schema($tree, $schema)
    {
        if (!is_array($tree) || !is_array($schema)) {
            return [];
        }
        $out = [];
        foreach ($tree as $key => $value) {
            // A list or object needs a schema of its own; one given a single sanitizer is dropped.
            if (array_key_exists($key, $schema) && (!is_array($value) || is_array($schema[$key]))) {
                $out[$key] = self::sanitize_value($value, $schema[$key]);
            }
        }
        return $out;
    }

    private static function sanitize_value($value, $rule)
    {
        if (!is_array($value)) {
            return self::apply_sanitizer($value, $rule);
        }
        if (!array_is_list($value) || !isset($rule[0])) {
            return self::sanitize_from_schema($value, $rule);
        }
        return array_map(static fn ($item) => is_array($item) ? self::sanitize_from_schema($item, $rule[0]) : self::apply_sanitizer($item, $rule[0]), $value);
    }

    private static function apply_sanitizer($value, $sanitizer)
    {
        return is_callable($sanitizer) ? call_user_func($sanitizer, $value) : $value;
    }

    public static function get_allowed_font_mime_types()
    {
        return Minn\Theme\FontNames::MIME_TYPES;
    }
}
