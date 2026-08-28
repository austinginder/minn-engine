<?php

declare(strict_types=1);

namespace Minn\Blocks;

/**
 * The inline style and class names a block's "style" and preset
 * attributes produce at render time, for the dynamic blocks that build
 * their own wrapper (static blocks already carry them in stored markup).
 */
final class Styles
{
    private const SIDES = ['top', 'right', 'bottom', 'left'];

    /** var:preset|spacing|40 becomes var(--wp--preset--spacing--40). */
    public static function value(string $value): string
    {
        if (str_starts_with($value, 'var:')) {
            return 'var(--wp--' . str_replace('|', '--', substr($value, 4)) . ')';
        }
        return $value;
    }

    /**
     * The style attribute's declarations. The reference emits the style
     * groups in a fixed order (border, colour, typography, spacing,
     * dimensions) and most properties in a fixed order within a group,
     * but a spacing box's sides come out as the block stored them.
     */
    public static function inline(array $style): string
    {
        $declarations = [];
        foreach (['border', 'color', 'typography', 'spacing', 'dimensions'] as $group) {
            $rules = $style[$group] ?? null;
            if (!is_array($rules)) {
                continue;
            }
            match ($group) {
                'border' => self::borderDeclarations($rules, $declarations),
                'color' => self::colorDeclarations($rules, $declarations),
                'spacing' => self::spacingDeclarations($rules, $declarations),
                'typography' => self::typographyDeclarations($rules, $declarations),
                'dimensions' => self::dimensionDeclarations($rules, $declarations),
            };
        }
        return implode(';', $declarations);
    }

    /** @param list<string> $declarations */
    private static function borderDeclarations(array $border, array &$declarations): void
    {
        foreach (self::SIDES as $side) {
            $value = $border[$side] ?? null;
            if (!is_array($value)) {
                continue;
            }
            foreach (['color', 'style', 'width'] as $property) {
                if (isset($value[$property])) {
                    $declarations[] = "border-{$side}-{$property}:" . self::value((string) $value[$property]);
                }
            }
        }
        foreach (['color', 'radius', 'style', 'width'] as $property) {
            if (isset($border[$property]) && !is_array($border[$property])) {
                $declarations[] = "border-{$property}:" . self::value((string) $border[$property]);
            }
        }
    }

    /** @param list<string> $declarations */
    private static function colorDeclarations(array $color, array &$declarations): void
    {
        foreach (['background' => 'background-color', 'text' => 'color', 'gradient' => 'background'] as $key => $property) {
            if (isset($color[$key]) && !is_array($color[$key])) {
                $declarations[] = $property . ':' . self::value((string) $color[$key]);
            }
        }
    }

    /** @param list<string> $declarations */
    private static function spacingDeclarations(array $spacing, array &$declarations): void
    {
        foreach (['margin', 'padding'] as $property) {
            $box = $spacing[$property] ?? null;
            if (!is_array($box)) {
                continue;
            }
            // Sides come out in the order the block stored them.
            foreach ($box as $side => $value) {
                if (in_array($side, self::SIDES, true) && $value !== null) {
                    $declarations[] = "{$property}-{$side}:" . self::value((string) $value);
                }
            }
        }
    }

    /** @param list<string> $declarations */
    private static function typographyDeclarations(array $typography, array &$declarations): void
    {
        $properties = [
            'fontSize' => 'font-size',
            'fontFamily' => 'font-family',
            'fontStyle' => 'font-style',
            'fontWeight' => 'font-weight',
            'lineHeight' => 'line-height',
            'textDecoration' => 'text-decoration',
            'textTransform' => 'text-transform',
            'letterSpacing' => 'letter-spacing',
            'writingMode' => 'writing-mode',
        ];
        foreach ($properties as $key => $property) {
            if (isset($typography[$key]) && !is_array($typography[$key])) {
                $declarations[] = $property . ':' . self::value((string) $typography[$key]);
            }
        }
    }

    /** @param list<string> $declarations */
    private static function dimensionDeclarations(array $dimensions, array &$declarations): void
    {
        foreach (['minHeight' => 'min-height', 'aspectRatio' => 'aspect-ratio'] as $key => $property) {
            if (isset($dimensions[$key]) && !is_array($dimensions[$key])) {
                $declarations[] = $property . ':' . self::value((string) $dimensions[$key]);
            }
        }
    }

    /** The preset classes (font size, text alignment, custom class names) a block's attributes declare. */
    public static function classes(array $attrs): array
    {
        $classes = [];
        if (!empty($attrs['className'])) {
            $classes[] = (string) $attrs['className'];
        }
        if (!empty($attrs['textAlign'])) {
            $classes[] = 'has-text-align-' . $attrs['textAlign'];
        }
        if (!empty($attrs['fontSize'])) {
            $classes[] = 'has-' . $attrs['fontSize'] . '-font-size';
        }
        if (!empty($attrs['fontFamily'])) {
            $classes[] = 'has-' . $attrs['fontFamily'] . '-font-family';
        }
        return $classes;
    }

    /** The align class an "align" attribute declares. */
    public static function align(array $attrs): ?string
    {
        return empty($attrs['align']) ? null : 'align' . $attrs['align'];
    }
}
