<?php

declare(strict_types=1);

namespace Minn\Theme;

use Closure;

/**
 * @font-face rules as the reference prints them (probe fonts), and the
 * fonts a theme.json declares in the shape they are printed from.
 *
 * A face needs a family and at least one source it can name a format for.
 * Its rule lists family (quoted unless it is one word or already carries
 * quotes), style (normal), weight (400), display (fallback unless it is
 * one the reference knows), the sources, then any other face property in
 * the order given. Sources go data URIs first, then woff2, woff,
 * truetype, embedded-opentype and opentype; anything else is dropped.
 */
final class FontFaces
{
    private const DISPLAYS = ['auto', 'block', 'fallback', 'swap', 'optional'];
    private const PROPERTIES = ['ascent-override', 'descent-override', 'font-display', 'font-family', 'font-stretch', 'font-style', 'font-weight', 'font-variant', 'font-feature-settings', 'font-variation-settings', 'line-gap-override', 'size-adjust', 'src', 'unicode-range'];
    private const FORMATS = ['woff2' => 'woff2', 'woff' => 'woff', 'ttf' => 'truetype', 'eot' => 'embedded-opentype', 'otf' => 'opentype'];

    /**
     * The rules for fonts given as families of faces, one rule a line; ''
     * when no face can be printed.
     *
     * @param array<array-key, mixed> $fonts
     */
    public static function css(array $fonts): string
    {
        $css = '';
        foreach ($fonts as $faces) {
            foreach (is_array($faces) ? $faces : [] as $face) {
                $rule = is_array($face) ? self::rule($face) : '';
                $css .= $rule === '' ? '' : $rule . "\n";
            }
        }
        return $css;
    }

    /**
     * The fonts a theme.json's settings declare, one list of faces per
     * family that has any: each face's sources first (file:./ ones through
     * $fileUri), then its family (the first name of its own, or of the
     * family's, unquoted), then its other properties in kebab case.
     *
     * @param Closure(string): string $fileUri a theme-relative path to its URL
     * @return list<list<array<string, mixed>>>
     */
    public static function fromSettings(array $settings, Closure $fileUri): array
    {
        $fonts = [];
        foreach (StylePresets::fontFamilies($settings) as $family) {
            $faces = [];
            foreach ((array) ($family['fontFace'] ?? []) as $definition) {
                if (!is_array($definition)) {
                    continue;
                }
                $src = array_map(static fn ($s) => str_starts_with((string) $s, 'file:./') ? $fileUri(substr((string) $s, 7)) : (string) $s, (array) ($definition['src'] ?? []));
                $name = self::firstName((string) ($definition['fontFamily'] ?? '') !== '' ? (string) $definition['fontFamily'] : (string) ($family['fontFamily'] ?? ''));
                $rest = [];
                foreach (array_diff_key($definition, ['src' => true, 'fontFamily' => true]) as $key => $value) {
                    $rest[strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', (string) $key))] = $value;
                }
                $faces[] = ['src' => $src, 'font-family' => $name] + $rest;
            }
            if ($faces !== []) {
                $fonts[] = $faces;
            }
        }
        return $fonts;
    }

    /** One face's rule, or '' when it has no family or no source it can print. */
    private static function rule(array $face): string
    {
        $family = $face['font-family'] ?? '';
        $src = self::sources($face['src'] ?? []);
        if (!is_string($family) || $family === '' || $src === '') {
            return '';
        }
        $display = (string) ($face['font-display'] ?? 'fallback');
        $properties = [
            'font-family' => self::family($family),
            'font-style' => (string) ($face['font-style'] ?? 'normal'),
            'font-weight' => (string) ($face['font-weight'] ?? '400'),
            'font-display' => in_array($display, self::DISPLAYS, true) ? $display : 'fallback',
            'src' => $src,
        ];
        foreach ($face as $key => $value) {
            if (in_array($key, self::PROPERTIES, true) && !isset($properties[$key])) {
                $properties[$key] = $key === 'font-variation-settings' && is_array($value) ? self::variations($value) : (string) $value;
            }
        }
        return '@font-face{' . implode(';', array_map(static fn ($key, $value) => "{$key}:{$value}", array_keys($properties), $properties)) . ';}';
    }

    /** The sources in the order the reference prints them: data URIs, then one each by format. */
    private static function sources(mixed $src): string
    {
        $data = [];
        $byFormat = [];
        foreach (is_array($src) ? $src : [$src] as $url) {
            $url = (string) $url;
            if (str_starts_with($url, 'data:')) {
                $data[] = "url({$url})";
                continue;
            }
            $extension = strtolower(pathinfo($url, PATHINFO_EXTENSION));
            if (isset(self::FORMATS[$extension])) {
                $byFormat[$extension] = "url('{$url}') format('" . self::FORMATS[$extension] . "')";
            }
        }
        $ordered = array_values(array_filter(array_map(static fn (string $extension) => $byFormat[$extension] ?? null, array_keys(self::FORMATS))));
        return implode(', ', [...$data, ...$ordered]);
    }

    /** A family name as printed: as given when it carries quotes or is one word, quoted otherwise. */
    private static function family(string $family): string
    {
        return str_contains($family, '"') || str_contains($family, "'") || preg_match('/^[\w-]+$/', $family) === 1 ? $family : '"' . $family . '"';
    }

    /** font-variation-settings given as axis => value, run together as the reference prints them. @param array<string, mixed> $settings */
    private static function variations(array $settings): string
    {
        return implode('', array_map(static fn ($axis, $value) => "{$axis} {$value}", array_keys($settings), $settings));
    }

    /** The first name in a comma-separated family list, without its quotes. */
    private static function firstName(string $family): string
    {
        return trim(explode(',', $family)[0], " \t\n\r\"'");
    }
}
