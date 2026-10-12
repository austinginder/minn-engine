<?php

declare(strict_types=1);

namespace Minn\Theme;

use Minn\Blocks\StyleEngine;
use Minn\Json;

/**
 * The preset side of theme.json: the colour, gradient, font-size,
 * font-family, spacing, and shadow lists a theme declares over core's
 * own, as the custom properties on :root and the has-* utility classes
 * the reference prints for them. Pure: settings in, CSS fragments out.
 */
final class StylePresets
{
    /** @return array<string, list<array{slug: string, value: string}>> */
    /** Every preset list the theme declares, over core's own (as given, else data/presets.json), keyed by kind. */
    public static function presets(array $settings, ?array $core = null): array
    {
        $core ??= (array) Json::file(MINN_ENGINE_DIR . '/data/presets.json');
        $merge = static fn (array $defaults, array $own, string $key) => [...$defaults, ...array_map(static fn (array $p) => ['slug' => (string) $p['slug'], 'value' => (string) $p[$key]], $own)];
        $fontSizes = self::presetList($settings['typography']['fontSizes'] ?? []);
        $spacing = self::presetList($settings['spacing']['spacingSizes'] ?? []);
        return [
            'aspect-ratio' => $core['aspect-ratio'] ?? [],
            'color' => $merge($core['color'] ?? [], self::presetList($settings['color']['palette'] ?? []), 'color'),
            'gradient' => $merge($core['gradient'] ?? [], self::presetList($settings['color']['gradients'] ?? []), 'gradient'),
            'font-size' => array_map(static fn (array $p) => ['slug' => (string) $p['slug'], 'value' => self::fluidFontSize($p, $settings)], self::defaultSlugsFirst($fontSizes, ['small', 'medium', 'large', 'x-large'])),
            'font-family' => array_map(static fn (array $p) => ['slug' => (string) $p['slug'], 'value' => (string) $p['fontFamily']], self::fontFamilies($settings)),
            'spacing' => self::spacingPresets($core['spacing'] ?? [], $spacing),
            'shadow' => $merge($core['shadow'] ?? [], self::presetList($settings['shadow']['presets'] ?? []), 'shadow'),
        ];
    }

    /**
     * A preset list as theme.json writes it is a plain list; as the site
     * editor saves it, it is keyed by origin (default, theme, custom). The
     * reference prints the origins in that order, so the two shapes flatten
     * to one list here.
     *
     * @return list<array>
     */
    public static function presetList(mixed $presets): array
    {
        if (!is_array($presets)) {
            return [];
        }
        if (array_is_list($presets)) {
            return array_values(array_filter($presets, 'is_array'));
        }
        $out = [];
        foreach (['default', 'theme', 'custom'] as $origin) {
            foreach ((array) ($presets[$origin] ?? []) as $preset) {
                if (is_array($preset)) {
                    $out[] = $preset;
                }
            }
        }
        return $out;
    }

    /**
     * The font family presets in the settings.
     *
     * @return list<array>
     */
    public static function fontFamilies(array $settings): array
    {
        return self::presetList($settings['typography']['fontFamilies'] ?? []);
    }

    /** The presets as custom properties for :root. */
    public static function presetProperties(array $presets): string
    {
        $out = '';
        foreach ($presets as $kind => $entries) {
            foreach ($entries as $entry) {
                $out .= "--wp--preset--{$kind}--{$entry['slug']}: {$entry['value']};";
            }
        }
        return $out;
    }

    /**
     * A settings.custom tree as custom properties: --wp--custom-- and the path
     * to each value, every key in kebab case, lists by index; empty values
     * are left out.
     *
     * @param array<array-key, mixed> $custom
     */
    public static function customProperties(array $custom, string $prefix = '--wp--custom--'): string
    {
        $out = '';
        foreach ($custom as $key => $value) {
            $name = $prefix . StyleEngine::kebab((string) $key);
            if (is_array($value)) {
                $out .= self::customProperties($value, $name . '--');
            } elseif (is_scalar($value)) {
                $out .= $name . ': ' . (is_bool($value) ? ($value ? '1' : '') : (string) $value) . ';';
            }
        }
        return $out;
    }

    /** The has-* utility classes the presets give every colour, gradient, font size, and font family; each under the prefix given (a block's :where(selector)). */
    public static function presetClasses(array $presets, string $prefix = ''): string
    {
        $out = '';
        foreach ([['color', 'color', 'color'], ['color', 'background-color', 'background-color'], ['color', 'border-color', 'border-color'], ['gradient', 'gradient-background', 'background'], ['font-size', 'font-size', 'font-size'], ['font-family', 'font-family', 'font-family']] as [$kind, $suffix, $property]) {
            foreach ((array) ($presets[$kind] ?? []) as $preset) {
                $out .= "{$prefix}.has-{$preset['slug']}-{$suffix}{{$property}: var(--wp--preset--{$kind}--{$preset['slug']}) !important;}";
            }
        }
        return $out;
    }

    /**
     * A theme size that reuses one of core's slugs prints in core's position;
     * the theme's own slugs follow.
     *
     * @param list<array> $presets
     * @param list<string> $defaults
     * @return list<array>
     */
    public static function defaultSlugsFirst(array $presets, array $defaults): array
    {
        $rank = array_flip($defaults);
        $keyed = [];
        foreach ($presets as $i => $preset) {
            $slug = (string) ($preset['slug'] ?? '');
            $keyed[] = [isset($rank[$slug]) ? $rank[$slug] : count($defaults) + $i, $preset];
        }
        usort($keyed, static fn (array $a, array $b) => $a[0] <=> $b[0]);
        return array_column($keyed, 1);
    }

    /**
     * The default spacing scale (20 to 80) is always present, whatever
     * defaultSpacingSizes says; a theme size with the same slug replaces the
     * default in place, and the theme's other sizes follow the scale.
     *
     * @return list<array{slug: string, value: string}>
     */
    public static function spacingPresets(array $defaults, array $own): array
    {
        $out = [];
        foreach ($defaults as $preset) {
            $out[(string) $preset['slug']] = ['slug' => (string) $preset['slug'], 'value' => (string) $preset['value']];
        }
        foreach ($own as $preset) {
            $out[(string) $preset['slug']] = ['slug' => (string) $preset['slug'], 'value' => (string) $preset['size']];
        }
        return array_values($out);
    }

    /** A font size preset as the global stylesheet writes it: fluid by the theme's settings (Typography::fontSize). */
    public static function fluidFontSize(array $preset, array $settings): string
    {
        return (string) Typography::fontSize($preset, $settings);
    }
}
