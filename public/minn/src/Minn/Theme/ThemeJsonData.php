<?php

declare(strict_types=1);

namespace Minn\Theme;

use Minn\Runtime\Runtime;

/**
 * The theme's theme.json as plugins and the theme's own functions may
 * change it (wp_theme_json_data_theme, a WP_Theme_JSON_Data they update
 * with presets and settings of their own), as the reference's resolver
 * hands it to them. Worked out once a request for each set of callbacks,
 * so a filter added late still counts.
 */
final class ThemeJsonData
{
    /** The theme layer through wp_theme_json_data_theme; as written when nothing filters it. @param array<string, mixed> $json */
    public static function theme(array $json, string $dir): array
    {
        $hook = $GLOBALS['wp_filter']['wp_theme_json_data_theme'] ?? null;
        if (!\has_filter('wp_theme_json_data_theme') || !is_object($hook)) {
            return $json;
        }
        $key = 'theme_json_data_theme:' . $dir . ':' . md5(serialize(array_map('array_keys', (array) $hook->callbacks)));
        $cached = Runtime::current()->get($key);
        if (is_array($cached)) {
            return $cached;
        }
        $data = \apply_filters('wp_theme_json_data_theme', new \WP_Theme_JSON_Data($json, 'theme'));
        $filtered = $data instanceof \WP_Theme_JSON_Data ? (array) $data->get_data() : $json;
        Runtime::current()->set($key, $filtered);
        return $filtered;
    }
}
