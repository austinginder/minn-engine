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

    /** The style attribute's declarations, in the reference's order. */
    public static function inline(array $style): string
    {
        $declarations = [];
        foreach (self::SIDES as $side) {
            $border = $style['border'][$side] ?? null;
            if (is_array($border)) {
                if (isset($border['color'])) {
                    $declarations[] = "border-{$side}-color:" . self::value((string) $border['color']);
                }
                if (isset($border['width'])) {
                    $declarations[] = "border-{$side}-width:" . self::value((string) $border['width']);
                }
            }
        }
        if (isset($style['color']['background'])) {
            $declarations[] = 'background-color:' . self::value((string) $style['color']['background']);
        }
        if (isset($style['color']['text'])) {
            $declarations[] = 'color:' . self::value((string) $style['color']['text']);
        }
        foreach (['margin', 'padding'] as $property) {
            $box = $style['spacing'][$property] ?? null;
            if (!is_array($box)) {
                continue;
            }
            foreach (self::SIDES as $side) {
                if (isset($box[$side])) {
                    $declarations[] = "{$property}-{$side}:" . self::value((string) $box[$side]);
                }
            }
        }
        $typography = (array) ($style['typography'] ?? []);
        foreach ([
            'fontSize' => 'font-size',
            'fontStyle' => 'font-style',
            'fontWeight' => 'font-weight',
            'letterSpacing' => 'letter-spacing',
            'lineHeight' => 'line-height',
            'textDecoration' => 'text-decoration',
            'textTransform' => 'text-transform',
        ] as $key => $property) {
            if (isset($typography[$key])) {
                $declarations[] = $property . ':' . self::value((string) $typography[$key]);
            }
        }
        return implode(';', $declarations);
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
        return $classes;
    }

    /** The align class an "align" attribute declares. */
    public static function align(array $attrs): ?string
    {
        return empty($attrs['align']) ? null : 'align' . $attrs['align'];
    }
}
