<?php

declare(strict_types=1);

namespace Minn\Blocks;

/**
 * The wrapper attributes a block's supports declaration earns from its
 * attributes: align and class-name classes, color and gradient presets or
 * inline values, the font-size preset, the anchor id.
 */
final class Supports
{
    /**
     * The classes and inline styles a block's supports amount to.
     *
     * @param array<string, mixed> $attributes the block's prepared attributes
     * @param array<string, mixed> $supports the block type's supports
     * @param callable(string): string $kebab the slug form of a preset name (filtered on the reference)
     * @return array<string, string> class, style, id, only those that apply
     */
    public static function attributes(array $attributes, array $supports, string $defaultClass, callable $kebab): array
    {
        $classes = [];
        $styles = [];
        if (!empty($supports['align']) && !empty($attributes['align']) && is_string($attributes['align'])) {
            $classes[] = 'align' . $attributes['align'];
        }
        $customClass = !empty($attributes['className']) && ($supports['customClassName'] ?? true) !== false ? (string) $attributes['className'] : null;
        if ($customClass !== null) {
            $classes[] = $customClass;
        }
        if (($supports['className'] ?? true) !== false) {
            $classes[] = $defaultClass;
        }
        self::color($attributes, $supports['color'] ?? null, $kebab, $classes, $styles);
        $typography = $supports['typography'] ?? null;
        if ($typography && (!empty($typography['fontSize']) || $typography === true)) {
            if (!empty($attributes['fontSize'])) {
                $classes[] = 'has-' . $kebab((string) $attributes['fontSize']) . '-font-size';
            } elseif (!empty($attributes['style']['typography']['fontSize'])) {
                $styles[] = 'font-size:' . $attributes['style']['typography']['fontSize'];
            }
        }
        $output = [];
        if ($classes !== []) {
            $output['class'] = implode(' ', array_unique($classes));
        }
        if ($styles !== []) {
            $output['style'] = implode(';', $styles) . ';';
        }
        if (!empty($supports['anchor']) && !empty($attributes['anchor'])) {
            $output['id'] = (string) $attributes['anchor'];
        }
        return $output;
    }

    /** @param list<string> $classes @param list<string> $styles */
    private static function color(array $attributes, mixed $color, callable $kebab, array &$classes, array &$styles): void
    {
        if (!$color) {
            return;
        }
        $custom = $attributes['style']['color'] ?? [];
        $slots = [
            ['on' => !empty($color['text']) || $color === true, 'preset' => 'textColor', 'custom' => 'text', 'flag' => 'has-text-color', 'suffix' => '-color', 'property' => 'color'],
            ['on' => !empty($color['background']) || $color === true, 'preset' => 'backgroundColor', 'custom' => 'background', 'flag' => 'has-background', 'suffix' => '-background-color', 'property' => 'background-color'],
            ['on' => !empty($color['gradients']), 'preset' => 'gradient', 'custom' => 'gradient', 'flag' => 'has-background', 'suffix' => '-gradient-background', 'property' => 'background'],
        ];
        foreach ($slots as $slot) {
            if (!$slot['on']) {
                continue;
            }
            if (!empty($attributes[$slot['preset']])) {
                $classes[] = $slot['flag'];
                $classes[] = 'has-' . $kebab((string) $attributes[$slot['preset']]) . $slot['suffix'];
            } elseif (!empty($custom[$slot['custom']])) {
                $classes[] = $slot['flag'];
                $styles[] = $slot['property'] . ':' . $custom[$slot['custom']];
            }
        }
    }
}
