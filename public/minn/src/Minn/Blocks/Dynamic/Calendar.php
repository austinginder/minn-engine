<?php

declare(strict_types=1);

namespace Minn\Blocks\Dynamic;

use Minn\Blocks\Block;
use Minn\Blocks\Styles;
use Minn\Html;

/**
 * The calendar block's colours: the reference puts them on the calendar's
 * table, not on the block's wrapper. A colour preset is a pair of classes;
 * a custom colour, only where no preset is named, is its marker class and
 * a declaration in the table's style attribute (probe
 * blocks-rest-widget-doubts). Gradients are not carried.
 */
final class Calendar
{
    private const COLOURS = [
        'textColor' => ['text', 'has-text-color', 'color', '-color'],
        'backgroundColor' => ['background', 'has-background', 'background-color', '-background-color'],
    ];

    /** The block with its colours taken off, for the wrapper. */
    public static function withoutColours(Block $block): Block
    {
        $attrs = $block->attrs;
        unset($attrs['textColor'], $attrs['backgroundColor'], $attrs['style']['color']);
        return $block->withAttrs($attrs);
    }

    /** The calendar's markup with the block's colours on its table. */
    public static function colourTable(Block $block, string $calendar): string
    {
        $classes = '';
        $style = '';
        foreach (self::COLOURS as $preset => [$custom, $marker, $property, $suffix]) {
            $slug = (string) ($block->attrs[$preset] ?? '');
            $value = Styles::value((string) ($block->attrs['style']['color'][$custom] ?? ''));
            if ($slug !== '') {
                $classes .= " {$marker} has-" . Styles::preset($slug) . $suffix;
            } elseif ($value !== '') {
                $classes .= " {$marker}";
                $style .= "{$property}:{$value};";
            }
        }
        $bare = '<table id="wp-calendar" class="wp-calendar-table"';
        $at = strpos($calendar, $bare);
        $table = '<table' . ($style === '' ? '' : ' style="' . Html::attr($style) . '"') . ' id="wp-calendar" class="wp-calendar-table' . Html::attr($classes) . '"';
        return $at === false ? $calendar : substr_replace($calendar, $table, $at, strlen($bare));
    }
}
