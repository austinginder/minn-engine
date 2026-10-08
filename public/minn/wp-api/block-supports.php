<?php
/**
 * Block supports (wp-includes/block-supports): each support's register
 * function (the attributes it adds to a block type that opts in), its apply
 * function (the wrapper classes and styles it earns from a block's
 * attributes, built with the style engine), and its render filters (what it
 * does to a rendered block's HTML on render_block and render_block_data).
 * The supports register with WP_Block_Supports in the reference's order,
 * which is the order their classes and styles appear. The engine's own
 * renderer applies the same effects to the core blocks it renders, so it
 * runs render_block without these filters there (Runtime\BlockFilters).
 * Behaviour from contracts/fixtures/api/block-supports.json.
 */

use Minn\Blocks\LayoutStyle;
use Minn\Blocks\Layout;
use Minn\Blocks\RenderState;

foreach ([
    'align' => ['register_attribute' => 'wp_register_alignment_support', 'apply' => 'wp_apply_alignment_support'],
    'custom-classname' => ['register_attribute' => 'wp_register_custom_classname_support', 'apply' => 'wp_apply_custom_classname_support'],
    'generated-classname' => ['apply' => 'wp_apply_generated_classname_support'],
    'colors' => ['register_attribute' => 'wp_register_colors_support', 'apply' => 'wp_apply_colors_support'],
    'typography' => ['register_attribute' => 'wp_register_typography_support', 'apply' => 'wp_apply_typography_support'],
    'border' => ['register_attribute' => 'wp_register_border_support', 'apply' => 'wp_apply_border_support'],
    'layout' => ['register_attribute' => 'wp_register_layout_support'],
    'position' => ['register_attribute' => 'wp_register_position_support'],
    'spacing' => ['register_attribute' => 'wp_register_spacing_support', 'apply' => 'wp_apply_spacing_support'],
    'dimensions' => ['register_attribute' => 'wp_register_dimensions_support', 'apply' => 'wp_apply_dimensions_support'],
    'duotone' => ['register_attribute' => ['WP_Duotone', 'register_duotone_support']],
    'shadow' => ['register_attribute' => 'wp_register_shadow_support', 'apply' => 'wp_apply_shadow_support'],
    'background' => ['register_attribute' => 'wp_register_background_support'],
    'block-style-variation' => [],
    'aria-label' => ['register_attribute' => 'wp_register_aria_label_support', 'apply' => 'wp_apply_aria_label_support'],
    'anchor' => ['register_attribute' => 'wp_register_anchor_support', 'apply' => 'wp_apply_anchor_support'],
    'custom-css' => ['register_attribute' => 'wp_register_custom_css_support'],
] as $minn_support => $minn_config) {
    WP_Block_Supports::get_instance()->register($minn_support, $minn_config);
}
unset($minn_support, $minn_config);

/** @internal adds attributes a block type lacks; a block type with no attributes yet starts with an empty list */
function _minn_support_attributes($block_type, array $attributes): void
{
    if (!is_array($block_type->attributes)) {
        $block_type->attributes = [];
    }
    foreach ($attributes as $name => $definition) {
        if (!array_key_exists($name, $block_type->attributes)) {
            $block_type->attributes[$name] = $definition;
        }
    }
}

/**
 * @internal what the style engine makes of some styles, as wrapper attributes: class names and inline styles.
 * Colors and typography turn their presets into class names alone; spacing, shadow and border keep theirs as custom properties.
 */
function _minn_support_styles(array $styles, bool $preset_classes = false): array
{
    $styles = array_filter($styles, static fn ($v) => $v !== null && $v !== [] && $v !== '');
    if ($styles === []) {
        return [];
    }
    $out = wp_style_engine_get_styles($styles, ['convert_vars_to_classnames' => $preset_classes]);
    return array_filter(['class' => $out['classnames'] ?? '', 'style' => $out['css'] ?? ''], static fn ($v) => $v !== '');
}

// Register functions.

function wp_register_alignment_support($block_type)
{
    if (block_has_support($block_type, 'align', false)) {
        _minn_support_attributes($block_type, ['align' => ['type' => 'string', 'enum' => ['left', 'center', 'right', 'wide', 'full', '']]]);
    }
}

function wp_register_anchor_support($block_type)
{
    if (block_has_support($block_type, 'anchor', false)) {
        _minn_support_attributes($block_type, ['anchor' => ['type' => 'string']]);
    }
}

function wp_register_aria_label_support($block_type)
{
    if (block_has_support($block_type, 'ariaLabel', false)) {
        _minn_support_attributes($block_type, ['ariaLabel' => ['type' => 'string']]);
    }
}

function wp_register_custom_classname_support($block_type)
{
    if (block_has_support($block_type, 'customClassName', true)) {
        _minn_support_attributes($block_type, ['className' => ['type' => 'string']]);
    }
}

function wp_register_custom_css_support($block_type)
{
    if (block_has_support($block_type, 'customCSS', true)) {
        _minn_support_attributes($block_type, ['style' => ['type' => 'object']]);
    }
}

/** The style attribute, with backgroundColor, textColor and gradient for the colors the block supports (text and background unless declined). */
function wp_register_colors_support($block_type)
{
    $color = $block_type->supports['color'] ?? false;
    $text = $color === true || (is_array($color) && ($color['text'] ?? true));
    $background = $color === true || (is_array($color) && ($color['background'] ?? true));
    $gradients = is_array($color) && !empty($color['gradients']);
    $link = is_array($color) && !empty($color['link']);
    if (!$text && !$background && !$gradients && !$link) {
        return;
    }
    _minn_support_attributes($block_type, array_filter(['style' => ['type' => 'object'], 'backgroundColor' => $background ? ['type' => 'string'] : null, 'textColor' => $text ? ['type' => 'string'] : null, 'gradient' => $gradients ? ['type' => 'string'] : null]));
}

/** The style attribute (and fontSize, fontFamily) for a block that supports some typography. */
function wp_register_typography_support($block_type)
{
    $typography = $block_type->supports['typography'] ?? false;
    if (!is_array($typography) || array_filter($typography) === []) {
        return;
    }
    _minn_support_attributes($block_type, array_filter(['style' => ['type' => 'object'], 'fontSize' => !empty($typography['fontSize']) ? ['type' => 'string'] : null, 'fontFamily' => !empty($typography['__experimentalFontFamily']) ? ['type' => 'string'] : null]));
}

/** The style attribute (and borderColor) for a block that supports borders. */
function wp_register_border_support($block_type)
{
    $border = $block_type->supports['__experimentalBorder'] ?? false;
    if (!$border) {
        return;
    }
    _minn_support_attributes($block_type, array_filter(['style' => ['type' => 'object'], 'borderColor' => wp_has_border_feature_support($block_type, 'color') ? ['type' => 'string'] : null]));
}

function wp_register_layout_support($block_type)
{
    if (block_has_support($block_type, 'layout', false) || block_has_support($block_type, '__experimentalLayout', false)) {
        _minn_support_attributes($block_type, ['layout' => ['type' => 'object']]);
    }
}

/** @internal registers the style attribute for a block type that supports a feature */
function _minn_register_style_support($block_type, string $feature): void
{
    $support = $block_type->supports[$feature] ?? false;
    if ($support === true || (is_array($support) && array_filter($support) !== [])) {
        _minn_support_attributes($block_type, ['style' => ['type' => 'object']]);
    }
}

function wp_register_position_support($block_type)
{
    _minn_register_style_support($block_type, 'position');
}

function wp_register_spacing_support($block_type)
{
    _minn_register_style_support($block_type, 'spacing');
}

function wp_register_dimensions_support($block_type)
{
    _minn_register_style_support($block_type, 'dimensions');
}

function wp_register_background_support($block_type)
{
    _minn_register_style_support($block_type, 'background');
}

/** Nothing: a box shadow rides on the style attribute other supports register. */
function wp_register_shadow_support($block_type)
{
}

// Apply functions: the wrapper attributes each support earns.

function wp_apply_alignment_support($block_type, $block_attributes)
{
    return block_has_support($block_type, 'align', false) && array_key_exists('align', (array) $block_attributes) ? ['class' => 'align' . $block_attributes['align']] : [];
}

function wp_apply_anchor_support($block_type, $block_attributes)
{
    return block_has_support($block_type, 'anchor', false) && !empty($block_attributes['anchor']) ? ['id' => (string) $block_attributes['anchor']] : [];
}

function wp_apply_aria_label_support($block_type, $block_attributes)
{
    return block_has_support($block_type, 'ariaLabel', false) && !empty($block_attributes['ariaLabel']) ? ['aria-label' => (string) $block_attributes['ariaLabel']] : [];
}

function wp_apply_custom_classname_support($block_type, $block_attributes)
{
    return block_has_support($block_type, 'customClassName', true) && !empty($block_attributes['className']) ? ['class' => (string) $block_attributes['className']] : [];
}

/** Text, background and gradient: a preset as its classes, else the custom value inline, unless serialization is skipped. */
function wp_apply_colors_support($block_type, $block_attributes)
{
    $color = $block_type->supports['color'] ?? false;
    if (!$color || wp_should_skip_block_supports_serialization($block_type, 'color')) {
        return [];
    }
    $custom = (array) ($block_attributes['style']['color'] ?? []);
    $styles = [];
    foreach (['text' => ['textColor', 'color', true], 'background' => ['backgroundColor', 'color', true], 'gradient' => ['gradient', 'gradient', false]] as $slot => [$preset, $kind, $default]) {
        $supported = $color === true ? $slot !== 'gradient' : ($color[$slot === 'gradient' ? 'gradients' : $slot] ?? $default);
        if ($supported && !wp_should_skip_block_supports_serialization($block_type, 'color', $slot)) {
            $styles[$slot] = !empty($block_attributes[$preset]) ? "var:preset|{$kind}|{$block_attributes[$preset]}" : ($custom[$slot] ?? null);
        }
    }
    return _minn_support_styles(['color' => array_filter($styles)], true);
}

/** The typography a block supports: presets as classes, custom values inline (a custom font size made fluid), the text alignment as a class. */
function wp_apply_typography_support($block_type, $block_attributes)
{
    $typography = $block_type->supports['typography'] ?? false;
    if (!is_array($typography) || wp_should_skip_block_supports_serialization($block_type, 'typography')) {
        return [];
    }
    $style = (array) ($block_attributes['style']['typography'] ?? []);
    $features = ['fontSize' => 'fontSize', 'fontFamily' => '__experimentalFontFamily', 'fontStyle' => '__experimentalFontStyle', 'fontWeight' => '__experimentalFontWeight', 'lineHeight' => 'lineHeight', 'textDecoration' => '__experimentalTextDecoration', 'textTransform' => '__experimentalTextTransform', 'letterSpacing' => '__experimentalLetterSpacing', 'writingMode' => '__experimentalWritingMode'];
    $styles = [];
    foreach ($features as $feature => $support) {
        if (empty($typography[$support]) || wp_should_skip_block_supports_serialization($block_type, 'typography', $feature)) {
            continue;
        }
        $styles[$feature] = match ($feature) {
            'fontSize' => !empty($block_attributes['fontSize']) ? "var:preset|font-size|{$block_attributes['fontSize']}" : (isset($style['fontSize']) ? wp_get_typography_font_size_value(['size' => $style['fontSize']]) : null),
            'fontFamily' => !empty($block_attributes['fontFamily']) ? "var:preset|font-family|{$block_attributes['fontFamily']}" : ($style['fontFamily'] ?? null),
            default => $style[$feature] ?? null,
        };
    }
    $out = _minn_support_styles(['typography' => array_filter($styles, static fn ($v) => $v !== null && $v !== '')], true);
    $align = !empty($typography['textAlign']) && !wp_should_skip_block_supports_serialization($block_type, 'typography', 'textAlign') ? ($style['textAlign'] ?? '') : '';
    if ($align !== '') {
        $out['class'] = trim(($out['class'] ?? '') . ' has-text-align-' . $align);
    }
    return $out;
}

/** Border color, radius, style and width, all round or side by side, a preset color as its classes. */
function wp_apply_border_support($block_type, $block_attributes)
{
    if (!($block_type->supports['__experimentalBorder'] ?? false) || wp_should_skip_block_supports_serialization($block_type, 'border')) {
        return [];
    }
    $border = (array) ($block_attributes['style']['border'] ?? []);
    $allowed = static fn (string $feature): bool => wp_has_border_feature_support($block_type, $feature) && !wp_should_skip_block_supports_serialization($block_type, 'border', $feature);
    $styles = [];
    if ($allowed('color')) {
        $styles['color'] = !empty($block_attributes['borderColor']) ? "var:preset|color|{$block_attributes['borderColor']}" : ($border['color'] ?? null);
    }
    foreach (['radius', 'style', 'width'] as $feature) {
        if ($allowed($feature) && isset($border[$feature])) {
            $styles[$feature] = $border[$feature];
        }
    }
    foreach (['top', 'right', 'bottom', 'left'] as $side) {
        $sideStyles = [];
        foreach (['width', 'color', 'style'] as $feature) {
            if ($allowed($feature) && isset($border[$side][$feature])) {
                $sideStyles[$feature] = $border[$side][$feature];
            }
        }
        $styles[$side] = $sideStyles === [] ? null : $sideStyles;
    }
    return _minn_support_styles(['border' => array_filter($styles, static fn ($v) => $v !== null && $v !== '')]);
}

/** Padding and margin the block supports (the gap is the layout's). */
function wp_apply_spacing_support($block_type, $block_attributes)
{
    $spacing = $block_type->supports['spacing'] ?? false;
    if (!$spacing || wp_should_skip_block_supports_serialization($block_type, 'spacing')) {
        return [];
    }
    $style = (array) ($block_attributes['style']['spacing'] ?? []);
    $styles = [];
    foreach (['padding', 'margin'] as $feature) {
        if (($spacing === true || !empty($spacing[$feature])) && !wp_should_skip_block_supports_serialization($block_type, 'spacing', $feature)) {
            $styles[$feature] = $style[$feature] ?? null;
        }
    }
    return _minn_support_styles(['spacing' => array_filter($styles)]);
}

/** A minimum height the block supports (an aspect ratio is the render filter's). */
function wp_apply_dimensions_support($block_type, $block_attributes)
{
    if (!block_has_support($block_type, ['dimensions', 'minHeight'], false) || wp_should_skip_block_supports_serialization($block_type, 'dimensions', 'minHeight')) {
        return [];
    }
    return _minn_support_styles(['dimensions' => ['minHeight' => $block_attributes['style']['dimensions']['minHeight'] ?? null]]);
}

function wp_apply_shadow_support($block_type, $block_attributes)
{
    if (!block_has_support($block_type, 'shadow', false) || wp_should_skip_block_supports_serialization($block_type, 'shadow')) {
        return [];
    }
    return _minn_support_styles(['shadow' => $block_attributes['style']['shadow'] ?? null]);
}
