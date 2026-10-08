<?php

declare(strict_types=1);

namespace Minn\Blocks;

use Minn\Support\Html;

/**
 * The opening tag of a dynamic block's wrapper, in the reference's class
 * order: text alignment, the link-colour marker (for the blocks that
 * carry it), the block's own extra classes (a taxonomy, an alignment),
 * the custom class and its numbered style companion, the element-style
 * class, the block's class, colour presets, font size and family, and
 * the layout classes last; the style attribute inline.
 */
final class Wrapper
{
    /** A dynamic block's opening tag with its classes in the reference's order. */
    public static function open(
        string $tag,
        string $blockClass,
        Block $block,
        bool $styleFirst = false,
        bool $linkColorClass = false,
        array $extraClasses = [],
        array $trailingClasses = [],
    ): string {
        $style = Styles::inline((array) $block->attr('style', []));
        $classes = [];
        if (!empty($block->attrs['textAlign'])) {
            $classes[] = 'has-text-align-' . $block->attrs['textAlign'];
        }
        if ($linkColorClass && isset($block->attrs['style']['elements']['link']['color']['text'])) {
            $classes[] = 'has-link-color';
        }
        array_push($classes, ...$extraClasses);
        $className = $block->className();
        if ($className !== '') {
            // A custom class and its numbered style companion lead, before the block's own class.
            $classes[] = $className;
            $numbered = RenderState::current()->takePendingVariation();
            if ($numbered !== null) {
                $classes[] = $numbered;
            }
        }
        $elements = RenderState::current()->takePendingElements();
        if ($elements !== null) {
            $classes[] = $elements;
        }
        $classes[] = $blockClass;
        if (!empty($block->attrs['textColor']) || isset($block->attrs['style']['color']['text'])) {
            $classes[] = 'has-text-color';
        }
        if (!empty($block->attrs['textColor'])) {
            $classes[] = 'has-' . Styles::slug((string) $block->attrs['textColor']) . '-color';
        }
        if (!empty($block->attrs['backgroundColor']) || isset($block->attrs['style']['color']['background'])) {
            $classes[] = 'has-background';
        }
        if (!empty($block->attrs['backgroundColor'])) {
            $classes[] = 'has-' . Styles::slug((string) $block->attrs['backgroundColor']) . '-background-color';
        }
        if (!empty($block->attrs['fontSize'])) {
            $classes[] = 'has-' . Styles::slug((string) $block->attrs['fontSize']) . '-font-size';
        }
        if (!empty($block->attrs['fontFamily'])) {
            $classes[] = 'has-' . Styles::slug((string) $block->attrs['fontFamily']) . '-font-family';
        }
        array_push($classes, ...$trailingClasses);
        $classAttr = ' class="' . Html::attr(implode(' ', $classes)) . '"';
        $styleAttr = $style === '' ? '' : ' style="' . Html::attr($style) . '"';
        return '<' . $tag . ($styleFirst ? $styleAttr . $classAttr : $classAttr . $styleAttr) . '>';
    }
}
