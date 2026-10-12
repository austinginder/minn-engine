<?php

declare(strict_types=1);

namespace Minn\Theme;

use Minn\Runtime\Runtime;

/**
 * The theme's theme.json as plugins and the theme's own functions may
 * change it (wp_theme_json_data_theme, a WP_Theme_JSON_Data they update
 * with presets and settings of their own), as the reference's resolver
 * hands it to them, and the core and site editor layers likewise
 * (wp_theme_json_data_default, wp_theme_json_data_user). Worked out once
 * a request for each set of callbacks, so a filter added late still counts.
 */
final class ThemeJsonData
{
    /** The theme layer through wp_theme_json_data_theme; as written when nothing filters it. @param array<string, mixed> $json */
    public static function theme(array $json, string $dir): array
    {
        $hook = $GLOBALS['wp_filter']['wp_theme_json_data_theme'] ?? null;
        if (!Runtime::hooks()->has('wp_theme_json_data_theme') || !is_object($hook)) {
            return $json;
        }
        $key = 'theme_json_data_theme:' . $dir . ':' . md5(serialize(array_map('array_keys', (array) $hook->callbacks)));
        $cached = Runtime::current()->get($key);
        if (is_array($cached)) {
            return $cached;
        }
        $data = \apply_filters('wp_theme_json_data_theme', new \WP_Theme_JSON_Data($json, 'theme'));
        $filtered = $data instanceof \WP_Theme_JSON_Data ? self::asWritten((array) $data->get_theme_json()->get_raw_data()) : $json;
        Runtime::current()->set($key, $filtered);
        return $filtered;
    }

    /**
     * The core layer (wp_theme_json_data_default, origin default) or the
     * site editor's (wp_theme_json_data_user, origin custom) as plugins
     * leave it, each a WP_Theme_JSON_Data they update; as given when
     * nothing filters it. Worked out once a request for each set of
     * callbacks and layer.
     *
     * @param array<string, mixed> $json
     * @return array<string, mixed>
     */
    public static function layer(string $origin, array $json): array
    {
        $filter = $origin === 'default' ? 'wp_theme_json_data_default' : 'wp_theme_json_data_user';
        $hook = $GLOBALS['wp_filter'][$filter] ?? null;
        if (!Runtime::hooks()->has($filter) || !is_object($hook)) {
            return $json;
        }
        $key = "theme_json_data_{$origin}:" . md5(serialize($json)) . ':' . md5(serialize(array_map('array_keys', (array) $hook->callbacks)));
        $cached = Runtime::current()->get($key);
        if (is_array($cached)) {
            return $cached;
        }
        $data = \apply_filters($filter, new \WP_Theme_JSON_Data(['version' => 3] + $json, $origin));
        $filtered = $data instanceof \WP_Theme_JSON_Data ? array_diff_key((array) $data->get_data(), ['version' => true]) : $json;
        Runtime::current()->set($key, $filtered);
        return $filtered;
    }

    /**
     * The filtered layer back in theme.json's own shape: each preset (and
     * the spacing scale) the theme's list again, everything else in the
     * order it came, as the reference's resolver keeps the layer's object.
     *
     * @param array<string, mixed> $raw
     * @return array<string, mixed>
     */
    private static function asWritten(array $raw): array
    {
        $paths = array_merge(array_column(\WP_Theme_JSON::PRESETS_METADATA, 'path'), [['spacing', 'spacingScale']]);
        $nodes = [['settings']];
        foreach (array_keys((array) ($raw['settings']['blocks'] ?? [])) as $block) {
            $nodes[] = ['settings', 'blocks', (string) $block];
        }
        foreach ($nodes as $node) {
            foreach ($paths as $path) {
                $ref = &$raw;
                foreach (array_merge($node, $path) as $key) {
                    if (!is_array($ref) || !array_key_exists($key, $ref)) {
                        unset($ref);
                        continue 2;
                    }
                    $ref = &$ref[$key];
                }
                if (is_array($ref) && array_key_exists('theme', $ref)) {
                    $ref = $ref['theme'];
                }
                unset($ref);
            }
        }
        return $raw;
    }
}
