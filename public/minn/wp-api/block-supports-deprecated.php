<?php
/**
 * The block supports' deprecated functions (wp-includes/deprecated.php,
 * probe block-supports): each reports its deprecation as the reference
 * names it, then does what it still does there. The tinycolor helpers keep
 * the reference's arithmetic, quirks included: a channel is read as a whole
 * number (a percentage is its number, a fraction is 0), and colour names
 * other than transparent are not read.
 */

use Minn\Theme\GlobalStyles;

// Colours, the tinycolor way.

/** A number as a fraction of its maximum, read as a whole number and clamped to it. */
function wp_tinycolor_bound01($n, $max)
{
    _deprecated_function(__FUNCTION__, '6.3.0');
    $n = min($max, max(0, (int) $n));
    if (abs($n - $max) < 0.000001) {
        return 1.0;
    }
    return fmod((float) $n, (float) $max) / (float) $max;
}

/** An alpha between 0 and 1; anything else (or not a number) is 1. */
function _wp_tinycolor_bound_alpha($n)
{
    _deprecated_function(__FUNCTION__, '6.3.0');
    if (!is_numeric($n)) {
        return 1;
    }
    $n = (float) $n;
    return $n < 0 || $n > 1 ? 1 : $n;
}

/** Red, green and blue each bounded to 0..255. */
function wp_tinycolor_rgb_to_rgb($rgb_color)
{
    _deprecated_function(__FUNCTION__, '6.3.0');
    return [
        'r' => wp_tinycolor_bound01($rgb_color['r'], 255) * 255,
        'g' => wp_tinycolor_bound01($rgb_color['g'], 255) * 255,
        'b' => wp_tinycolor_bound01($rgb_color['b'], 255) * 255,
    ];
}

/** One channel of a hue between two levels. */
function wp_tinycolor_hue_to_rgb($p, $q, $t)
{
    _deprecated_function(__FUNCTION__, '6.3.0');
    if ($t < 0) {
        ++$t;
    }
    if ($t > 1) {
        --$t;
    }
    return match (true) {
        $t < 1 / 6 => $p + ($q - $p) * 6 * $t,
        $t < 1 / 2 => $q,
        $t < 2 / 3 => $p + ($q - $p) * (2 / 3 - $t) * 6,
        default => $p,
    };
}

/** A hue (of 360), saturation and lightness (of 100) as red, green and blue. */
function wp_tinycolor_hsl_to_rgb($hsl_color)
{
    _deprecated_function(__FUNCTION__, '6.3.0');
    $h = wp_tinycolor_bound01($hsl_color['h'], 360);
    $s = wp_tinycolor_bound01($hsl_color['s'], 100);
    $l = wp_tinycolor_bound01($hsl_color['l'], 100);
    // A grey (no saturation) goes through the channels too, each coming out at the lightness.
    $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
    $p = 2 * $l - $q;
    return ['r' => wp_tinycolor_hue_to_rgb($p, $q, $h + 1 / 3) * 255, 'g' => wp_tinycolor_hue_to_rgb($p, $q, $h) * 255, 'b' => wp_tinycolor_hue_to_rgb($p, $q, $h - 1 / 3) * 255];
}

/** A CSS colour (rgb(a), hsl(a), hex with or without #, transparent) as red, green, blue and alpha; null for anything else. */
function wp_tinycolor_string_to_rgb($color_str)
{
    _deprecated_function(__FUNCTION__, '6.3.0');
    $color = strtolower(trim((string) $color_str));
    if ($color === 'transparent') {
        return ['r' => 0, 'g' => 0, 'b' => 0, 'a' => 0];
    }
    $unit = '((?:[-\+]?\d*\.\d+%?)|(?:[-\+]?\d+%?))';
    $three = '[\s|\(]+' . $unit . '[,|\s]+' . $unit . '[,|\s]+' . $unit . '\s*\)?';
    $four = '[\s|\(]+' . $unit . '[,|\s]+' . $unit . '[,|\s]+' . $unit . '[,|\s]+' . $unit . '\s*\)?';
    if (preg_match('/^rgb' . $three . '$/', $color, $m) === 1) {
        return wp_tinycolor_rgb_to_rgb(['r' => $m[1], 'g' => $m[2], 'b' => $m[3]]) + ['a' => 1];
    }
    if (preg_match('/^rgba' . $four . '$/', $color, $m) === 1) {
        return wp_tinycolor_rgb_to_rgb(['r' => $m[1], 'g' => $m[2], 'b' => $m[3]]) + ['a' => _wp_tinycolor_bound_alpha($m[4])];
    }
    if (preg_match('/^hsl' . $three . '$/', $color, $m) === 1) {
        return wp_tinycolor_hsl_to_rgb(['h' => $m[1], 's' => $m[2], 'l' => $m[3]]) + ['a' => 1];
    }
    if (preg_match('/^hsla' . $four . '$/', $color, $m) === 1) {
        return wp_tinycolor_hsl_to_rgb(['h' => $m[1], 's' => $m[2], 'l' => $m[3]]) + ['a' => _wp_tinycolor_bound_alpha($m[4])];
    }
    if (preg_match('/^#?([0-9a-f]{8}|[0-9a-f]{6}|[0-9a-f]{4}|[0-9a-f]{3})$/', $color, $m) === 1) {
        $hex = strlen($m[1]) <= 4 ? implode('', array_map(static fn (string $digit) => $digit . $digit, str_split($m[1]))) : $m[1];
        $rgb = wp_tinycolor_rgb_to_rgb(['r' => hexdec(substr($hex, 0, 2)), 'g' => hexdec(substr($hex, 2, 2)), 'b' => hexdec(substr($hex, 4, 2))]);
        return $rgb + ['a' => strlen($hex) === 8 ? _wp_tinycolor_bound_alpha(hexdec(substr($hex, 6, 2)) / 255) : 1];
    }
    return null;
}

// Duotone, before WP_Duotone.

function wp_get_duotone_filter_id($preset)
{
    _deprecated_function(__FUNCTION__, '6.3.0');
    return WP_Duotone::get_filter_id_from_preset($preset);
}

function wp_get_duotone_filter_property($preset)
{
    _deprecated_function(__FUNCTION__, '6.3.0');
    return WP_Duotone::get_filter_css_property_value_from_preset($preset);
}

function wp_get_duotone_filter_svg($preset)
{
    _deprecated_function(__FUNCTION__, '6.3.0', 'WP_Duotone::get_filter_svg_from_preset()');
    return WP_Duotone::get_filter_svg_from_preset($preset);
}

function wp_render_duotone_filter_preset($preset)
{
    _deprecated_function(__FUNCTION__, '5.9.1', 'wp_get_duotone_filter_property()');
    return wp_get_duotone_filter_property($preset);
}

function wp_register_duotone_support($block_type)
{
    _deprecated_function(__FUNCTION__, '6.3.0', 'WP_Duotone::register_duotone_support()');
    return WP_Duotone::register_duotone_support($block_type);
}

function wp_render_duotone_support($block_content, $block)
{
    _deprecated_function(__FUNCTION__, '6.3.0', 'WP_Duotone::render_duotone_support()');
    return WP_Duotone::render_duotone_support($block_content, $block, new WP_Block($block));
}

// Skipped serialization, before wp_should_skip_block_supports_serialization().

/** @internal whether a block type skips serializing a support set (as a whole, or any of its features) */
function _minn_skips_serialization($block_type, string $key): bool
{
    $skip = is_object($block_type) ? ($block_type->supports[$key]['__experimentalSkipSerialization'] ?? false) : false;
    return is_array($skip) || $skip === true;
}

function wp_skip_border_serialization($block_type)
{
    _deprecated_function(__FUNCTION__, '6.0.0', 'wp_should_skip_block_supports_serialization()');
    return _minn_skips_serialization($block_type, '__experimentalBorder');
}

function wp_skip_dimensions_serialization($block_type)
{
    _deprecated_function(__FUNCTION__, '6.0.0', 'wp_should_skip_block_supports_serialization()');
    return _minn_skips_serialization($block_type, 'dimensions');
}

function wp_skip_spacing_serialization($block_type)
{
    _deprecated_function(__FUNCTION__, '6.0.0', 'wp_should_skip_block_supports_serialization()');
    return _minn_skips_serialization($block_type, 'spacing');
}

/** A typography feature of a block's style as one declaration, a preset as its custom property; null when the block sets none. */
function wp_typography_get_css_variable_inline_style($attributes, $feature, $css_property)
{
    _deprecated_function(__FUNCTION__, '6.1.0', 'wp_style_engine_get_styles()');
    $value = $attributes['style']['typography'][$feature] ?? null;
    if (empty($value)) {
        return null;
    }
    if (!is_string($value) || !str_contains($value, 'var:preset|')) {
        return sprintf('%s:%s;', $css_property, $value);
    }
    return sprintf('%s:var(--wp--preset--%s--%s);', $css_property, $css_property, substr($value, (int) strrpos($value, '|') + 1));
}

// The rest.

/** A block.json's metadata, unchanged. */
function _wp_multiple_block_styles($metadata)
{
    _deprecated_function(__FUNCTION__, '6.1.0');
    return $metadata;
}

/** A block's content as it is: the element class is wp_render_elements_class_name's now. */
function wp_render_elements_support($block_content, $block)
{
    _deprecated_function(__FUNCTION__, '6.6.0', 'wp_render_elements_class_name');
    return $block_content;
}

/** A variation's instance class: its name and the block's digest. */
function wp_create_block_style_variation_instance_name($block, $variation)
{
    _deprecated_function(__FUNCTION__, '6.7.0', 'wp_unique_id');
    return $variation . '--' . md5(serialize($block));
}

/** The global styles' own CSS: the top-level css and each block's, kept for the request until the theme.json data is cleaned. */
function wp_get_global_styles_custom_css()
{
    _deprecated_function(__FUNCTION__, '6.7.0', 'wp_get_global_stylesheet');
    $runtime = Minn\Runtime\Runtime::current();
    $css = $runtime->get('minn_global_styles_custom_css');
    if (!is_string($css)) {
        $css = (string) WP_Theme_JSON_Resolver::get_merged_data()->get_custom_css();
        $runtime->set('minn_global_styles_custom_css', $css);
    }
    return $css;
}

/** Under a block theme, the Customizer's CSS and the global styles' own go with the global styles instead of their own <style>. */
function wp_enqueue_global_styles_custom_css()
{
    _deprecated_function(__FUNCTION__, '6.7.0', 'wp_enqueue_global_styles');
    if (!wp_is_block_theme()) {
        return;
    }
    remove_action('wp_head', 'wp_custom_css_cb', 101);
    $css = wp_get_custom_css() . wp_get_global_styles_custom_css();
    if ($css !== '') {
        wp_add_inline_style('global-styles', $css);
    }
}

/**
 * The old web fonts handler's hooks: registering and printing the theme's
 * fonts, which the engine does itself (wp_print_font_faces), so they hold no
 * work of their own.
 */
function _wp_theme_json_webfonts_handler()
{
    _deprecated_function(__FUNCTION__, '6.4.0', 'wp_print_font_faces');
    foreach (['wp_loaded', 'wp_enqueue_scripts', 'admin_init'] as $hook) {
        add_action($hook, static function (): void {
        });
    }
}

/**
 * A navigation submenu's colour classes and styles from its navigation's
 * context: the overlay colours for a submenu inside one, a named colour as
 * its class, a custom one inline.
 */
function block_core_navigation_submenu_build_css_colors($context, $attributes, $is_sub_menu = false)
{
    _deprecated_function(__FUNCTION__, '6.3.0');
    $classes = [];
    $styles = '';
    [$text, $background] = $is_sub_menu ? ['overlayTextColor', 'overlayBackgroundColor'] : ['textColor', 'backgroundColor'];
    $customText = 'custom' . ucfirst($text);
    $customBackground = 'custom' . ucfirst($background);
    if (isset($context[$text]) || isset($context[$customText])) {
        $classes[] = 'has-text-color';
    }
    if (isset($context[$text])) {
        $classes[] = sprintf('has-%s-color', $context[$text]);
    } elseif (isset($context[$customText])) {
        $styles .= sprintf('color: %s;', $context[$customText]);
    }
    if (isset($context[$background]) || isset($context[$customBackground])) {
        $classes[] = 'has-background';
    }
    if (isset($context[$background])) {
        $classes[] = sprintf('has-%s-background-color', $context[$background]);
    } elseif (isset($context[$customBackground])) {
        $styles .= sprintf('background-color: %s;', $context[$customBackground]);
    }
    return ['css_classes' => $classes, 'inline_styles' => $styles];
}
