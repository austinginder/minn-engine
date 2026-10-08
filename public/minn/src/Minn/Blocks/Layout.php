<?php

declare(strict_types=1);

namespace Minn\Blocks;

use Minn\Runtime\Runtime;
use Minn\Support\Kses;

/**
 * The layout support's classes and rules (wp_render_layout_support_flag,
 * probe block-supports), for core and plugin blocks alike:
 *
 * - is-layout-{type} and {block}-is-layout-{type} for the layout the block
 *   uses (its own over its type's default), with has-global-padding for a
 *   constrained layout under a theme with root-padding-aware alignments;
 * - is-vertical or is-horizontal, is-content-justification-* and is-nowrap
 *   from the layout the block itself stores;
 * - a wp-container-{block}-is-layout-* class when the layout writes CSS
 *   (LayoutStyle), and wp-container-content-* for a child's own size or
 *   place in its parent's layout.
 *
 * The rules go to the page's block-support styles through the private
 * minn_block_support_rules action. A container class's suffix is a digest
 * the engine cannot reproduce (its inputs are not observable), so the engine
 * derives its own from the layout: same shape, different value, which the
 * parity suites normalise.
 */
final class Layout
{
    /**
     * The layout classes a core block's wrapper carries, under its type's
     * supports (data/blocks.json).
     *
     * @return list<string>
     */
    public static function classes(string $blockName, array $attrs): array
    {
        return self::forBlock($blockName, $attrs, CoreBlocks::supports($blockName));
    }

    /**
     * The layout classes a block's wrapper carries, its container's rules
     * recorded, under the theme's layout settings the render state holds
     * (root-padding-aware alignments, block gaps).
     *
     * @param array<string, mixed> $supports the block type's supports (layout and its default, spacing)
     * @return list<string>
     */
    public static function forBlock(string $blockName, array $attrs, array $supports): array
    {
        $state = RenderState::current();
        $own = (array) ($attrs['layout'] ?? []);
        $used = self::used($own, $supports);
        $type = (string) ($used['type'] ?? 'default');
        $slug = str_starts_with($blockName, 'core/') ? substr($blockName, 5) : str_replace('/', '-', $blockName);
        $name = $type === 'default' ? 'flow' : Styles::slug($type);
        $classes = [...self::typeClasses($type, $own), "is-layout-{$name}"];
        $container = 'wp-container-' . str_replace('/', '-', $blockName) . '-is-layout-' . substr(md5(serialize([$used, $attrs['style']['spacing'] ?? null])), 0, 8);
        $gap = $state->blockGap() && !self::skipsGap($supports) ? self::gapCss(self::sanitizeGap($attrs['style']['spacing']['blockGap'] ?? null)) : null;
        $padding = self::paddingCss((array) ($attrs['style']['spacing']['padding'] ?? []));
        $fallback = (string) ($supports['spacing']['blockGap']['__experimentalDefault'] ?? '0.5em');
        $rules = LayoutStyle::rules(".{$container}", self::widths(LayoutStyle::containerValues($used)), $gap, $padding, $fallback);
        if ($rules !== []) {
            $classes[] = $container;
            self::record($rules);
        }
        $classes[] = "wp-block-{$slug}-is-layout-{$name}";
        return $classes;
    }

    /**
     * The class a child's own layout earns (its size or place in the parent's
     * layout), its rules recorded; null when it writes none.
     *
     * @param array<string, mixed> $child the block's style.layout
     * @param array<string, mixed> $parent the parent's layout
     */
    public static function childClass(array $child, array $parent): ?string
    {
        $class = 'wp-container-content-' . substr(md5(serialize([$child, $parent])), 0, 8);
        $rules = LayoutStyle::childRules(".{$class}", LayoutStyle::childValues($child), $parent);
        if ($rules === []) {
            return null;
        }
        self::record($rules);
        return $class;
    }

    /**
     * A block gap as the layout reads it: a value with a character that could
     * end a declaration or open a function (\ ( & = } or a comment) is
     * dropped, a side of one too; anything not a string is left as it is.
     */
    public static function sanitizeGap(mixed $gap): mixed
    {
        if (is_array($gap)) {
            return array_map(static fn ($side) => is_string($side) ? self::safeGap($side) : null, $gap);
        }
        return is_string($gap) ? self::safeGap($gap) : $gap;
    }

    private static function safeGap(string $value): ?string
    {
        return preg_match('%[\\\\(&=}]|/\*%', $value) === 1 ? null : $value;
    }

    /**
     * A gap with its presets as custom properties, a side at a time.
     *
     * @return string|array<string, string>|null
     */
    public static function gapCss(mixed $gap): string|array|null
    {
        if (is_array($gap)) {
            return array_map(static fn ($side) => Styles::value((string) $side), array_filter($gap, 'is_scalar'));
        }
        return is_scalar($gap) ? Styles::value((string) $gap) : null;
    }

    /** @param array<string, mixed> $supports */
    private static function skipsGap(array $supports): bool
    {
        $skip = $supports['spacing']['__experimentalSkipSerialization'] ?? false;
        return is_array($skip) ? in_array('blockGap', $skip, true) : (bool) $skip;
    }

    /**
     * The layout a block uses: its own over its type's default; the legacy
     * inherit flag means constrained.
     *
     * @param array<string, mixed> $supports
     * @return array<string, mixed>
     */
    private static function used(array $own, array $supports): array
    {
        $support = $supports['layout'] ?? $supports['__experimentalLayout'] ?? [];
        $used = array_merge(is_array($support) ? (array) ($support['default'] ?? []) : [], $own);
        if (!empty($used['inherit']) && !isset($used['type'])) {
            $used['type'] = 'constrained';
        }
        return $used;
    }

    /** @return list<string> */
    private static function typeClasses(string $type, array $own): array
    {
        $classes = [];
        if ($type === 'constrained' && RenderState::current()->rootPaddingAware()) {
            $classes[] = 'has-global-padding';
        }
        if ($type === 'flex' && in_array($own['orientation'] ?? null, ['vertical', 'horizontal'], true)) {
            $classes[] = 'is-' . $own['orientation'];
        }
        if (($type === 'flex' || $type === 'constrained') && !empty($own['justifyContent'])) {
            $classes[] = 'is-content-justification-' . Styles::slug((string) $own['justifyContent']);
        }
        if ($type === 'flex' && ($own['flexWrap'] ?? '') === 'nowrap') {
            $classes[] = 'is-nowrap';
        }
        return $classes;
    }

    /**
     * A block's side padding with its presets as custom properties.
     *
     * @return array<string, string>
     */
    public static function paddingCss(array $padding): array
    {
        return array_map(static fn ($side) => Styles::value((string) $side), array_filter($padding, 'is_scalar'));
    }

    /**
     * A layout with its widths checked: the first declaration of each, through
     * the style attribute filter, empty when that drops it.
     *
     * @return array<string, mixed>
     */
    public static function widths(array $layout): array
    {
        foreach (['contentSize', 'wideSize'] as $key) {
            if (isset($layout[$key])) {
                $layout[$key] = Kses::style(explode(';', (string) $layout[$key])[0]);
            }
        }
        return $layout;
    }

    /** @param list<array<string, mixed>> $rules */
    private static function record(array $rules): void
    {
        Runtime::hooks()->action('minn_block_support_rules', [$rules]);
    }
}
