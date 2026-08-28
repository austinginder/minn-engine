<?php

declare(strict_types=1);

namespace Minn\Blocks;

/**
 * The layout-support classes the reference adds at render time. Every
 * container carries is-layout-{type} and {block}-is-layout-{type}; flex and
 * grid layouts with rules of their own also carry a wp-container-* class
 * whose suffix names their generated stylesheet.
 *
 * That suffix is a digest the engine cannot reproduce (its inputs are not
 * observable), so the engine derives its own deterministic suffix from the
 * layout attributes. Same shape, different value: recorded in the contract,
 * and the parity suites normalise it.
 */
final class Layout
{
    /** @return list<string> */
    public static function classes(string $blockSlug, array $attrs, string $defaultType = 'flow', bool $alwaysContainer = false): array
    {
        $layout = (array) ($attrs['layout'] ?? []);
        $type = (string) ($layout['type'] ?? $defaultType);
        $type = $type === 'default' ? 'flow' : $type;
        $classes = [];

        if ($type === 'flex') {
            if (($layout['orientation'] ?? '') === 'vertical') {
                $classes[] = 'is-vertical';
            }
            if (($layout['flexWrap'] ?? '') === 'nowrap') {
                $classes[] = 'is-nowrap';
            }
            if (!empty($layout['justifyContent'])) {
                $classes[] = 'is-content-justification-' . $layout['justifyContent'];
            }
        }
        if ($type === 'constrained') {
            $classes[] = 'has-global-padding';
        }
        $classes[] = 'is-layout-' . $type;
        if ($alwaysContainer || self::hasRules($type, $layout, $attrs)) {
            $classes[] = 'wp-container-core-' . $blockSlug . '-is-layout-' . self::suffix($layout, $attrs);
        }
        $classes[] = 'wp-block-' . $blockSlug . '-is-layout-' . $type;
        return $classes;
    }

    /** A container stylesheet exists only when the layout declares something the defaults do not. */
    private static function hasRules(string $type, array $layout, array $attrs): bool
    {
        if (isset($attrs['style']['spacing']['blockGap'])) {
            return true;
        }
        return match ($type) {
            'flex' => isset($layout['flexWrap']) || isset($layout['justifyContent']) || isset($layout['orientation']) || isset($layout['verticalAlignment']),
            'grid' => isset($layout['minimumColumnWidth']) || isset($layout['columnCount']),
            'constrained' => isset($layout['justifyContent']) || isset($layout['contentSize']) || isset($layout['wideSize']),
            default => false,
        };
    }

    private static function suffix(array $layout, array $attrs): string
    {
        return substr(md5(serialize([$layout, $attrs['style']['spacing']['blockGap'] ?? null])), 0, 8);
    }
}
