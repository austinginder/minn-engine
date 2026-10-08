<?php
/**
 * The layout block support (wp-includes/block-supports/layout.php, probe
 * block-supports): the layout definitions, a layout's stylesheet, the
 * container and child values of a layout, and the render filter that gives
 * a block its layout classes (Minn\Blocks\Layout, which the engine's own
 * renderer shares) on the element holding its inner blocks. Each layout's
 * rules go to the style engine's block-supports store, which the page
 * prints. The classic-theme restores put back the wrappers a theme without
 * theme.json styles.
 */

use Minn\Blocks\Layout;
use Minn\Blocks\LayoutStyle;
use Minn\Content\Blocks as MinnBlocks;
use Minn\Support\Html;

/** The layout types the reference defines: class names, base styles, spacing styles (data/layout-definitions.json). */
function wp_get_layout_definitions()
{
    static $definitions = null;
    return $definitions ??= (array) json_decode((string) file_get_contents(MINN_ENGINE_DIR . '/data/layout-definitions.json'), true);
}

/** A block gap with any value (or side) carrying a character that could break out of a declaration dropped to null. */
function wp_sanitize_block_gap_value($gap_value)
{
    return Layout::sanitizeGap($gap_value);
}

/**
 * The stylesheet a layout writes for a selector, kept in the block-supports
 * store: the gap (its presets as custom properties) only with gap support
 * and serialization, the side padding for full-width children of a
 * constrained layout.
 */
function wp_get_layout_style($selector, $layout, $has_block_gap_support = false, $gap_value = null, $should_skip_gap_serialization = false, $fallback_gap_value = '0.5em', $block_spacing = null, $options = [])
{
    $gap = $has_block_gap_support && !$should_skip_gap_serialization ? Layout::gapCss($gap_value) : null;
    $padding = Layout::paddingCss((array) ($block_spacing['padding'] ?? []));
    $rules = LayoutStyle::rules((string) $selector, Layout::widths(LayoutStyle::containerValues((array) $layout)), $gap, $padding, (string) $fallback_gap_value);
    return $rules === [] ? '' : wp_style_engine_get_stylesheet_from_css_rules($rules, ['context' => 'block-supports']);
}

/** A layout's container values: all but the child's own size and place. */
function wp_get_layout_container_values($layout)
{
    return LayoutStyle::containerValues((array) $layout);
}

/** A layout's child values: the child's own size and place in its parent. */
function wp_get_layout_child_values($layout)
{
    return LayoutStyle::childValues((array) $layout);
}

/** The rules a child's layout writes in its parent's layout (a flex size, a grid span or place). */
function wp_get_child_layout_style_rules($selector, $child_layout, $parent_layout = [], $viewport_overrides = null)
{
    return LayoutStyle::childRules((string) $selector, (array) $child_layout, (array) $parent_layout);
}

/**
 * A block's layout classes (the layout support) and a child's own layout
 * class, added to the element holding its inner blocks; the rules they
 * name kept in the block-supports store.
 */
function wp_render_layout_support_flag($block_content, $block)
{
    $block_type = WP_Block_Type_Registry::get_instance()->get_registered((string) ($block['blockName'] ?? ''));
    $supported = block_has_support($block_type, 'layout', false) || block_has_support($block_type, '__experimentalLayout', false);
    $child = (array) ($block['attrs']['style']['layout'] ?? []);
    if (!$supported && $child === []) {
        return $block_content;
    }
    $classes = $child === [] ? [] : array_filter([Layout::childClass($child, (array) ($block['parentLayout'] ?? []))]);
    if ($supported) {
        // The engine's renderer holds the theme's layout settings (root padding, block gaps) in the render state it adopts.
        MinnBlocks::renderer();
        $classes = [...$classes, ...Layout::forBlock((string) $block['blockName'], (array) ($block['attrs'] ?? []), (array) $block_type->supports)];
    }
    return _minn_add_layout_classes((string) $block_content, $block, $classes);
}

/** @internal a child's own layout class alone: what the engine's renderer leaves of the layout support for a core block it renders itself */
function _minn_render_child_layout_support($block_content, $block)
{
    $child = (array) ($block['attrs']['style']['layout'] ?? []);
    $class = $child === [] ? null : Layout::childClass($child, (array) ($block['parentLayout'] ?? []));
    return $class === null ? $block_content : _minn_add_layout_classes((string) $block_content, $block, [$class]);
}

/** @internal adds classes to the element holding a block's inner blocks (found by its class), else to the first tag */
function _minn_add_layout_classes(string $content, array $block, array $classes): string
{
    $wrapper = _minn_inner_wrapper_class($block);
    $found = $wrapper !== null && preg_match('/\bclass\s*=\s*"[^"]*(?<![\w-])' . preg_quote($wrapper, '/') . '(?![\w-])/', $content) === 1;
    return Html::addClasses($content, $classes, $found ? $wrapper : null);
}

/** @internal the first class of the tag a block's markup last opens before its inner blocks, when that is not its first tag */
function _minn_inner_wrapper_class(array $block): ?string
{
    $first = $block['innerContent'][0] ?? null;
    if (empty($block['innerBlocks']) || !is_string($first) || preg_match_all('/<[a-zA-Z][^>]*>/', $first, $tags) < 2) {
        return null;
    }
    preg_match('/\bclass\s*=\s*(["\'])\s*([^\s"\']+)/', (string) end($tags[0]), $class);
    return $class[2] ?? null;
}

/** The parent's layout recorded on a block about to render inside it (parentLayout), for its own layout's rules. */
function wp_add_parent_layout_to_parsed_block($parsed_block, $source_block, $parent_block)
{
    $layout = $parent_block instanceof WP_Block ? ($parent_block->parsed_block['attrs']['layout'] ?? null) : null;
    if (!empty($layout)) {
        $parsed_block['parentLayout'] = $layout;
    }
    return $parsed_block;
}

/** The registered style variation a class string names (is-style-{name}), or null. */
function wp_get_block_style_variation_name_from_registered_style(string $class_name, array $registered_styles = []): ?string
{
    preg_match_all('/\bis-style-(?!default)(\S+)\b/', $class_name, $matches);
    $names = array_map(static fn ($style) => (string) ($style['name'] ?? ''), $registered_styles);
    foreach ($matches[1] as $name) {
        if (in_array($name, $names, true)) {
            return $name;
        }
    }
    return null;
}

/**
 * A group's old inner container, put back for a theme without theme.json:
 * the content inside the group's tag wrapped in
 * .wp-block-group__inner-container, unless it has one or lays out as flex
 * or grid.
 */
function wp_restore_group_inner_container($block_content, $block)
{
    if (wp_theme_has_theme_json() || in_array($block['attrs']['layout']['type'] ?? '', ['flex', 'grid'], true) || str_contains((string) $block_content, 'wp-block-group__inner-container')) {
        return $block_content;
    }
    $restored = preg_replace('/^(\s*<[a-zA-Z][^>]*>)(.*)(<\/[a-zA-Z][\w-]*>\s*)$/s', '$1<div class="wp-block-group__inner-container">$2</div>$3', (string) $block_content, 1);
    return $restored ?? $block_content;
}

/**
 * An image's old outer container, put back for a theme without theme.json:
 * a figure aligned left, center or right goes inside a div.wp-block-image
 * that takes the block's own class names.
 */
function wp_restore_image_outer_container($block_content, $block)
{
    if (wp_theme_has_theme_json() || preg_match('/^\s*<figure\b[^>]*\bclass="([^"]*)"/', (string) $block_content, $figure) !== 1 || preg_match('/\balign(?:left|center|right)\b/', $figure[1]) !== 1) {
        return $block_content;
    }
    $own = array_filter(explode(' ', (string) ($block['attrs']['className'] ?? '')));
    $kept = array_diff(array_filter(explode(' ', $figure[1])), ['wp-block-image', ...$own]);
    $outer = implode(' ', ['wp-block-image', ...$own]);
    $inner = (string) preg_replace('/\bclass="[^"]*"/', 'class="' . implode(' ', $kept) . '"', (string) $block_content, 1);
    return '<div class="' . esc_attr($outer) . '">' . $inner . '</div>';
}
