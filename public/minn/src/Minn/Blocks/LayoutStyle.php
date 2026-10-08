<?php

declare(strict_types=1);

namespace Minn\Blocks;

/**
 * The CSS rules a block's layout writes (wp_get_layout_style, probe
 * block-supports), as selector => declarations pairs the style engine turns
 * into a stylesheet:
 *
 * - flow: only the gap between children (none, then the gap, on each);
 * - constrained: the content width on the children that are not aligned
 *   left, right or full, the wide width on wide ones, no limit on full ones
 *   (justifyContent pushes the children to a side: both margins with a
 *   width, the one side alone without), negative margins on full ones for a
 *   block with side padding, then the children's gap;
 * - flex: nowrap, the gap, then the direction and alignments the
 *   orientation reads from justifyContent and verticalAlignment;
 * - grid: the column template (a count, a minimum width, or both folded
 *   together with the gap, or the fallback gap without one), then the gap.
 *
 * Widths arrive checked (one dropped as unsafe is empty) and gaps with
 * presets turned into custom properties.
 */
final class LayoutStyle
{
    private const JUSTIFY = ['left' => 'flex-start', 'right' => 'flex-end', 'center' => 'center', 'stretch' => 'stretch', 'space-between' => 'space-between'];
    private const ALIGN = ['top' => 'flex-start', 'center' => 'center', 'bottom' => 'flex-end', 'stretch' => 'stretch', 'space-between' => 'space-between'];
    private const CHILD_KEYS = ['selfStretch', 'flexSize', 'columnSpan', 'rowSpan', 'columnStart', 'rowStart'];

    /**
     * The rules for a container.
     *
     * @param array<string, mixed> $layout the layout, its widths checked
     * @param string|array<string, string>|null $gap the block gap (a value, or top and left), null when it is not written
     * @param array<string, string> $padding the block's own padding (left, right), for full-width children
     * @param string $fallbackGap the gap a grid with a minimum column width counts when the block writes none
     * @return list<array{selector: string, declarations: array<string, string>}>
     */
    public static function rules(string $selector, array $layout, string|array|null $gap, array $padding = [], string $fallbackGap = '0.5em'): array
    {
        return match ((string) ($layout['type'] ?? 'default')) {
            'default' => self::flowGap($selector, $gap),
            'constrained' => [...self::constrained($selector, $layout, $padding), ...self::flowGap($selector, $gap)],
            'flex' => self::single($selector, self::flex($layout, $gap)),
            'grid' => self::single($selector, self::grid($layout, $gap, $fallbackGap)),
            default => [],
        };
    }

    /** @return list<array{selector: string, declarations: array<string, string>}> */
    private static function flowGap(string $selector, string|array|null $gap): array
    {
        $value = is_array($gap) ? ($gap['top'] ?? null) : $gap;
        if ($value === null || $value === '') {
            return [];
        }
        return [
            ['selector' => "{$selector} > *", 'declarations' => ['margin-block-start' => '0', 'margin-block-end' => '0']],
            ['selector' => "{$selector} > * + *", 'declarations' => ['margin-block-start' => (string) $value, 'margin-block-end' => '0']],
        ];
    }

    /** @return list<array{selector: string, declarations: array<string, string>}> */
    private static function constrained(string $selector, array $layout, array $padding): array
    {
        $content = isset($layout['contentSize']) ? (string) $layout['contentSize'] : null;
        $wide = isset($layout['wideSize']) ? (string) $layout['wideSize'] : null;
        $children = "{$selector} > :where(:not(.alignleft):not(.alignright):not(.alignfull))";
        $justify = (string) ($layout['justifyContent'] ?? '');
        $rules = [];
        if (isset($layout['contentSize']) || isset($layout['wideSize'])) {
            [$left, $right] = match ($justify) {
                'left' => ['0 !important', 'auto !important'],
                'right' => ['auto !important', '0 !important'],
                default => ['auto !important', 'auto !important'],
            };
            // A width given but dropped as unsafe ('') stays dropped; only a width not given falls back to the other.
            $rules[] = ['selector' => $children, 'declarations' => self::filled(['max-width' => $content ?? $wide ?? '', 'margin-left' => $left, 'margin-right' => $right])];
            $rules[] = ['selector' => "{$selector} > .alignwide", 'declarations' => self::filled(['max-width' => $wide ?? $content ?? ''])];
            $rules[] = ['selector' => "{$selector} .alignfull", 'declarations' => ['max-width' => 'none']];
        } elseif ($justify === 'left' || $justify === 'right') {
            $rules[] = ['selector' => $children, 'declarations' => ["margin-{$justify}" => '0 !important']];
        }
        $margins = [];
        foreach (['right', 'left'] as $side) {
            if (isset($padding[$side]) && $padding[$side] !== '') {
                $margins["margin-{$side}"] = 'calc(' . ($padding[$side] === '0' ? '0px' : $padding[$side]) . ' * -1)';
            }
        }
        if ($margins !== []) {
            $rules[] = ['selector' => "{$selector} > .alignfull", 'declarations' => $margins];
        }
        return array_values(array_filter($rules, static fn (array $rule) => $rule['declarations'] !== []));
    }

    /** @return array<string, string> */
    private static function flex(array $layout, string|array|null $gap): array
    {
        $declarations = [];
        if (($layout['flexWrap'] ?? '') === 'nowrap') {
            $declarations['flex-wrap'] = 'nowrap';
        }
        $gapValue = self::gapValue($gap);
        if ($gapValue !== null) {
            $declarations['gap'] = $gapValue;
        }
        $justify = (string) ($layout['justifyContent'] ?? '');
        $align = (string) ($layout['verticalAlignment'] ?? '');
        if (($layout['orientation'] ?? 'horizontal') === 'vertical') {
            $declarations['flex-direction'] = 'column';
            // Down a column, items line up at the start unless justified otherwise.
            return $declarations + self::filled(['align-items' => self::JUSTIFY[$justify] ?? 'flex-start', 'justify-content' => self::ALIGN[$align] ?? '']);
        }
        // Across a row, items stretch by default and cannot be spaced between.
        return $declarations + self::filled(['justify-content' => $justify === 'stretch' ? '' : (self::JUSTIFY[$justify] ?? ''), 'align-items' => $align === 'space-between' ? '' : (self::ALIGN[$align] ?? '')]);
    }

    /** @return array<string, string> */
    private static function grid(array $layout, string|array|null $gap, string $fallbackGap): array
    {
        $count = $layout['columnCount'] ?? null;
        $minimum = (string) ($layout['minimumColumnWidth'] ?? '');
        $gapValue = self::gapValue($gap);
        if (!empty($count) && $minimum === '') {
            $declarations = ['grid-template-columns' => "repeat({$count}, minmax(0, 1fr))"];
        } elseif (!empty($count)) {
            $between = $gapValue ?? $fallbackGap;
            $declarations = ['grid-template-columns' => "repeat(auto-fill, minmax(max(min({$minimum}, 100%), (100% - ({$between} * ({$count} - 1))) /{$count}), 1fr))", 'container-type' => 'inline-size'];
        } else {
            $declarations = ['grid-template-columns' => 'repeat(auto-fill, minmax(min(' . ($minimum !== '' ? $minimum : '12rem') . ', 100%), 1fr))', 'container-type' => 'inline-size'];
        }
        if ($gapValue !== null) {
            $declarations['gap'] = $gapValue;
        }
        return $declarations;
    }

    /** A flex or grid gap: one value, or the row and column gaps together. */
    private static function gapValue(string|array|null $gap): ?string
    {
        if (is_array($gap)) {
            $parts = array_values(array_filter([$gap['top'] ?? null, $gap['left'] ?? null], static fn ($v) => $v !== null && $v !== ''));
            return $parts === [] ? null : implode(' ', $parts);
        }
        return $gap === null || $gap === '' ? null : $gap;
    }

    /**
     * @param array<string, string> $declarations
     * @return array<string, string> the declarations that have a value
     */
    private static function filled(array $declarations): array
    {
        return array_filter($declarations, static fn (string $value) => $value !== '');
    }

    /**
     * @param array<string, string> $declarations
     * @return list<array{selector: string, declarations: array<string, string>}>
     */
    private static function single(string $selector, array $declarations): array
    {
        return $declarations === [] ? [] : [['selector' => $selector, 'declarations' => $declarations]];
    }

    /**
     * The rules a child writes from its own layout (wp_get_child_layout_style_rules):
     * a fixed or filling flex size, a grid span, a grid position, released
     * again (the full row for a span) in a container narrower than the
     * columns it needs: the columns at the parent's minimum width (12rem by
     * default) and the gaps between them (1.5rem, or 24px for a width in px).
     *
     * @param array<string, mixed> $child the child's layout (selfStretch, flexSize, columnSpan, rowSpan, columnStart, rowStart)
     * @param array<string, mixed> $parent the parent's layout
     * @return list<array<string, mixed>>
     */
    public static function childRules(string $selector, array $child, array $parent): array
    {
        $declarations = [];
        if (($child['selfStretch'] ?? '') === 'fixed' && !empty($child['flexSize'])) {
            $declarations = ['flex-basis' => (string) $child['flexSize'], 'box-sizing' => 'border-box'];
        } elseif (($child['selfStretch'] ?? '') === 'fill') {
            $declarations = ['flex-grow' => '1'];
        }
        foreach (['column', 'row'] as $axis) {
            $start = $child["{$axis}Start"] ?? null;
            $span = $child["{$axis}Span"] ?? null;
            if (!empty($start)) {
                $declarations["grid-{$axis}"] = $start . (!empty($span) ? " / span {$span}" : '');
            } elseif (!empty($span)) {
                $declarations["grid-{$axis}"] = "span {$span}";
            }
        }
        $rules = $declarations === [] ? [] : [['selector' => $selector, 'declarations' => $declarations]];
        if (!empty($child['columnStart']) || !empty($child['rowStart'])) {
            $columns = (int) ($child['columnStart'] ?? 1) + max(0, (int) ($child['columnSpan'] ?? 1) - 1);
            preg_match('/^([\d.]+)\s*([a-z%]*)$/', (string) ($parent['minimumColumnWidth'] ?? ''), $width);
            $unit = ($width[2] ?? '') !== '' ? $width[2] : 'rem';
            $size = $columns * (float) ($width[1] ?? 12) + ($columns - 1) * ($unit === 'px' ? 24 : 1.5);
            $rules[] = ['rules_group' => "@container (max-width: {$size}{$unit} )", 'selector' => $selector, 'declarations' => ['grid-column' => empty($child['columnSpan']) ? 'auto' : '1/-1', 'grid-row' => 'auto']];
        }
        return $rules;
    }

    /** A layout's container values: everything but the child keys. */
    public static function containerValues(array $layout): array
    {
        return array_diff_key($layout, array_flip(self::CHILD_KEYS));
    }

    /** A layout's child values: the child keys alone. */
    public static function childValues(array $layout): array
    {
        return array_intersect_key($layout, array_flip(self::CHILD_KEYS));
    }
}
