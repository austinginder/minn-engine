<?php

/**
 * The font library's helpers (probe fonts): a family list sanitized (names
 * of more than one word quoted), the slug that identifies a face, data
 * kept to a schema of sanitizers, and the font types an upload may carry.
 */
class WP_Font_Utils
{
    private static function maybe_add_quotes($item)
    {
        $item = trim((string) $item, " \t\n\r\0\x0B\"'");
        return preg_match('/^[\w-]+$/', $item) === 1 ? $item : '"' . $item . '"';
    }

    public static function sanitize_font_family($font_family)
    {
        $font_family = sanitize_text_field((string) $font_family);
        if ($font_family === '') {
            return '';
        }
        $names = array_filter(array_map(static fn ($name) => trim((string) $name, " \t\n\r\0\x0B\"'"), explode(',', $font_family)), static fn ($name) => $name !== '');
        return implode(', ', array_map([self::class, 'maybe_add_quotes'], $names));
    }

    /** family list (lower case, unquoted); style; weight (keywords as numbers); stretch; unicode range. */
    public static function get_font_face_slug($settings)
    {
        $settings = wp_parse_args((array) $settings, ['fontFamily' => '', 'fontStyle' => 'normal', 'fontWeight' => '400', 'fontStretch' => '100%', 'unicodeRange' => 'U+0-10FFFF']);
        $families = array_map(static fn ($name) => strtolower(trim((string) $name, " \t\n\r\0\x0B\"'")), explode(',', (string) $settings['fontFamily']));
        $weight = str_replace(['normal', 'bold'], ['400', '700'], (string) $settings['fontWeight']);
        return implode(';', [implode(',', $families), (string) $settings['fontStyle'], $weight, (string) $settings['fontStretch'], (string) $settings['unicodeRange']]);
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
        return ['otf' => 'application/vnd.ms-opentype', 'ttf' => 'font/sfnt', 'woff' => 'font/woff', 'woff2' => 'font/woff2'];
    }
}
