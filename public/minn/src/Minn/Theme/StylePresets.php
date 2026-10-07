<?php

declare(strict_types=1);

namespace Minn\Theme;

use Minn\Blocks\StyleEngine;

/**
 * The preset side of theme.json: the colour, gradient, font-size,
 * font-family, spacing, and shadow lists a theme declares over core's
 * own, as the custom properties on :root and the has-* utility classes
 * the reference prints for them. Pure: settings in, CSS fragments out.
 */
final class StylePresets
{
    /** @return array<string, list<array{slug: string, value: string}>> */
    /** Every preset list the theme declares, over core's own, keyed by kind. */
    public static function presets(array $settings): array
    {
        $core = (array) json_decode((string) file_get_contents(MINN_ENGINE_DIR . '/data/presets.json'), true);
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

    /** The has-* utility classes the presets give every colour, gradient, font size, and font family. */
    public static function presetClasses(array $presets): string
    {
        $out = '';
        foreach ($presets['color'] as $c) {
            $out .= ".has-{$c['slug']}-color{color: var(--wp--preset--color--{$c['slug']}) !important;}";
        }
        foreach ($presets['color'] as $c) {
            $out .= ".has-{$c['slug']}-background-color{background-color: var(--wp--preset--color--{$c['slug']}) !important;}";
        }
        foreach ($presets['color'] as $c) {
            $out .= ".has-{$c['slug']}-border-color{border-color: var(--wp--preset--color--{$c['slug']}) !important;}";
        }
        foreach ($presets['gradient'] as $g) {
            $out .= ".has-{$g['slug']}-gradient-background{background: var(--wp--preset--gradient--{$g['slug']}) !important;}";
        }
        foreach ($presets['font-size'] as $f) {
            $out .= ".has-{$f['slug']}-font-size{font-size: var(--wp--preset--font-size--{$f['slug']}) !important;}";
        }
        foreach ($presets['font-family'] as $f) {
            $out .= ".has-{$f['slug']}-font-family{font-family: var(--wp--preset--font-family--{$f['slug']}) !important;}";
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

    /**
     * A font size with fluid bounds becomes clamp(min, min + ((1vw - v) * f),
     * max) scaled between a 320px viewport and the theme's wide size, which
     * is how the reference arrives at 0.196 for a 1rem to 1.125rem size on a
     * 1340px wide layout. A plain size stays as written.
     */
    public static function fluidFontSize(array $preset, array $settings): string
    {
        $size = (string) $preset['size'];
        $fluid = $preset['fluid'] ?? ($settings['typography']['fluid'] ?? false);
        if ($fluid === false || !is_array($fluid) || !isset($fluid['min'], $fluid['max'])) {
            return $size;
        }
        $min = (string) $fluid['min'];
        $max = (string) $fluid['max'];
        $unit = preg_replace('/[0-9.]/', '', $min) ?: 'rem';
        $perRem = $unit === 'rem' ? 1 : 16;
        $minViewport = 320 / 16;
        $maxViewport = ((float) ($settings['layout']['wideSize'] ?? '1600px')) / 16;
        $slope = ((float) $max - (float) $min) / $perRem / ($maxViewport - $minViewport);
        $factor = rtrim(rtrim(number_format($slope * 100, 3, '.', ''), '0'), '.');
        $offset = $unit === 'rem' ? ($minViewport / 100) . 'rem' : (320 / 100) . 'px';
        // The additive term is always in rem: a px minimum is converted (20px becomes 1.25rem, 35px 2.188rem).
        $base = $unit === 'rem' ? $min : rtrim(rtrim(number_format((float) $min / 16, 3, '.', ''), '0'), '.') . 'rem';
        return sprintf('clamp(%s, %s + ((1vw - %s) * %s), %s)', $min, $base, $offset, $factor, $max);
    }

    /** The format() a font source is declared with, from its file extension. */
    public static function fontFormat(string $url): string
    {
        return match (strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION))) {
            'woff' => 'woff',
            'ttf' => 'truetype',
            'otf' => 'opentype',
            'eot' => 'embedded-opentype',
            'svg' => 'svg',
            default => 'woff2',
        };
    }
}
