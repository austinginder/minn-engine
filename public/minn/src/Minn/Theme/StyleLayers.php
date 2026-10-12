<?php

declare(strict_types=1);

namespace Minn\Theme;

use Minn\Json;
use Minn\Runtime\Runtime;

/**
 * What plugins change about the global stylesheet's layers and nodes, for
 * GlobalStyles: core's preset lists when wp_theme_json_data_default
 * changed core's layer, and the style nodes wp_theme_json_get_style_nodes
 * keeps (probe hook-inputs-2). Without those filters the stylesheet is the
 * engine's own, byte for byte.
 */
final class StyleLayers
{
    /**
     * Core's preset lists: as stored, unless a plugin changed core's layer
     * (wp_theme_json_data_default), whose lists then stand in for them.
     *
     * @return array<string, list<array{slug: string, value: string}>>
     */
    public static function corePresets(): array
    {
        $stored = (array) Json::file(MINN_ENGINE_DIR . '/data/presets.json');
        $defaults = (array) Json::file(MINN_ENGINE_DIR . '/data/global-styles.json');
        $layer = ThemeJsonData::layer('default', $defaults);
        if ($layer === $defaults) {
            return $stored;
        }
        $settings = (array) ($layer['settings'] ?? []);
        $list = static function (mixed $presets, string $key): array {
            $presets = is_array($presets) && !array_is_list($presets) ? (array) ($presets['default'] ?? []) : (array) $presets;
            return array_values(array_map(static fn (array $p): array => ['slug' => (string) ($p['slug'] ?? ''), 'value' => (string) ($p[$key] ?? '')], array_filter($presets, 'is_array')));
        };
        return [
            'aspect-ratio' => $list($settings['dimensions']['aspectRatios'] ?? [], 'ratio'),
            'color' => $list($settings['color']['palette'] ?? [], 'color'),
            'gradient' => $list($settings['color']['gradients'] ?? [], 'gradient'),
            'shadow' => $list($settings['shadow']['presets'] ?? [], 'shadow'),
        ] + $stored;
    }

    /**
     * The blocks' styles as far as the kept nodes reach: a block's own
     * declarations with its node, each of its elements with that element's
     * node (the reference lists them apart); a block with neither goes.
     *
     * @param array<string, mixed> $blocks
     * @param list<string> $kept
     * @return array<string, mixed>
     */
    public static function keptBlocks(array $blocks, array $kept): array
    {
        $out = [];
        foreach ($blocks as $name => $node) {
            $node = (array) $node;
            $elements = array_filter((array) ($node['elements'] ?? []), static fn ($element): bool => in_array("styles/blocks/{$name}/elements/{$element}", $kept, true), ARRAY_FILTER_USE_KEY);
            $node = in_array("styles/blocks/{$name}", $kept, true) ? $node : [];
            if (isset($node['elements']) || $elements !== []) {
                $node['elements'] = $elements;
            }
            if ($node !== [] && $node !== ['elements' => []]) {
                $out[$name] = $node;
            }
        }
        return $out;
    }

    /**
     * The style nodes a plugin keeps (wp_theme_json_get_style_nodes) as
     * their paths, the root, element and block nodes printed only when
     * kept; null when no plugin filters them.
     *
     * @return list<string>|null
     */
    public static function keptNodes(array $styles): ?array
    {
        return Runtime::hooks()->has('wp_theme_json_get_style_nodes') && \function_exists('_minn_style_node_paths') ? \_minn_style_node_paths($styles) : null;
    }
}
