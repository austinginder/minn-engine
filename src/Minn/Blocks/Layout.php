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
            if (!empty($layout['justifyContent'])) {
                $classes[] = 'is-content-justification-' . $layout['justifyContent'];
            }
            if (($layout['flexWrap'] ?? '') === 'nowrap') {
                $classes[] = 'is-nowrap';
            }
        }
        if ($type === 'constrained') {
            $classes[] = 'has-global-padding';
        }
        $classes[] = 'is-layout-' . $type;
        if ($alwaysContainer || self::hasRules($type, $layout, $attrs)) {
            $container = 'wp-container-core-' . $blockSlug . '-is-layout-' . self::suffix($layout, $attrs);
            $classes[] = $container;
            RenderState::recordContainer($container, self::declarations($type, $layout, $attrs));
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
            'constrained' => isset($layout['justifyContent']) || isset($layout['contentSize']) || isset($layout['wideSize'])
                || isset($attrs['style']['spacing']['padding']['left']) || isset($attrs['style']['spacing']['padding']['right']),
            default => false,
        };
    }

    /** The declarations behind a container class, in the reference's order. */
    public static function declarations(string $type, array $layout, array $attrs): string
    {
        $rules = [];
        $gap = $attrs['style']['spacing']['blockGap'] ?? null;
        if (is_array($gap)) {
            $gap = $gap['left'] ?? $gap['top'] ?? null;
        }
        if ($type === 'flex') {
            $vertical = ($layout['orientation'] ?? '') === 'vertical';
            if ($vertical) {
                $rules[] = 'flex-direction:column';
            }
            if (($layout['flexWrap'] ?? '') === 'nowrap') {
                $rules[] = 'flex-wrap:nowrap';
            }
            if ($gap !== null) {
                $rules[] = 'gap:' . Styles::value((string) $gap);
            }
            $justify = (string) ($layout['justifyContent'] ?? '');
            $justifyMap = ['left' => 'flex-start', 'center' => 'center', 'right' => 'flex-end', 'space-between' => 'space-between', 'stretch' => 'stretch'];
            $alignMap = ['top' => 'flex-start', 'center' => 'center', 'bottom' => 'flex-end', 'stretch' => 'stretch', 'space-between' => 'space-between'];
            if ($vertical) {
                $rules[] = 'align-items:' . ($justifyMap[$justify] ?? 'flex-start');
                if (!empty($layout['verticalAlignment'])) {
                    $rules[] = 'justify-content:' . ($alignMap[$layout['verticalAlignment']] ?? 'flex-start');
                }
            } else {
                if ($justify !== '' && isset($justifyMap[$justify])) {
                    $rules[] = 'justify-content:' . $justifyMap[$justify];
                }
                if (!empty($layout['verticalAlignment'])) {
                    $rules[] = 'align-items:' . ($alignMap[$layout['verticalAlignment']] ?? 'center');
                }
            }
        } elseif ($type === 'grid') {
            if (!empty($layout['columnCount'])) {
                $rules[] = 'grid-template-columns:repeat(' . (int) $layout['columnCount'] . ', minmax(0, 1fr))';
            } else {
                $rules[] = 'grid-template-columns:repeat(auto-fill, minmax(min(' . ($layout['minimumColumnWidth'] ?? '12rem') . ', 100%), 1fr))';
                $rules[] = 'container-type:inline-size';
            }
            if ($gap !== null) {
                $rules[] = 'gap:' . Styles::value((string) $gap);
            }
        } elseif ($gap !== null) {
            // Flow and constrained layouts with their own gap.
            $rules[] = '--minn-block-gap:' . Styles::value((string) $gap);
        }
        return implode(';', $rules) . ($rules === [] ? '' : ';');
    }

    private static function suffix(array $layout, array $attrs): string
    {
        return substr(md5(serialize([$layout, $attrs['style']['spacing']['blockGap'] ?? null])), 0, 8);
    }
}
