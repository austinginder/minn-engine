<?php

/**
 * The reference's theme.json object as plugin code reads it: the merged
 * data the engine builds (get_raw_data, get_data, get_settings) and the
 * custom CSS are real; every other method keeps its inert placeholder, so a
 * call still traces as a gap instead of failing.
 */
#[AllowDynamicProperties]
class WP_Theme_JSON
{
    const ROOT_CSS_PROPERTIES_SELECTOR = ':root';
    const ROOT_BLOCK_SELECTOR = 'body';
    const VALID_ORIGINS = ['default', 'blocks', 'theme', 'custom'];
    const PRESETS_METADATA = [['path' => ['dimensions', 'aspectRatios'], 'prevent_override' => ['dimensions', 'defaultAspectRatios'], 'use_default_names' => false, 'value_key' => 'ratio', 'css_vars' => '--wp--preset--aspect-ratio--$slug', 'classes' => [], 'properties' => ['aspect-ratio']], ['path' => ['color', 'palette'], 'prevent_override' => ['color', 'defaultPalette'], 'use_default_names' => false, 'value_key' => 'color', 'css_vars' => '--wp--preset--color--$slug', 'classes' => ['.has-$slug-color' => 'color', '.has-$slug-background-color' => 'background-color', '.has-$slug-border-color' => 'border-color'], 'properties' => ['color', 'background-color', 'border-color']], ['path' => ['color', 'gradients'], 'prevent_override' => ['color', 'defaultGradients'], 'use_default_names' => false, 'value_key' => 'gradient', 'css_vars' => '--wp--preset--gradient--$slug', 'classes' => ['.has-$slug-gradient-background' => 'background'], 'properties' => ['background']], ['path' => ['color', 'duotone'], 'prevent_override' => ['color', 'defaultDuotone'], 'use_default_names' => false, 'value_func' => NULL, 'css_vars' => NULL, 'classes' => [], 'properties' => ['filter']], ['path' => ['typography', 'fontSizes'], 'prevent_override' => ['typography', 'defaultFontSizes'], 'use_default_names' => true, 'value_func' => 'wp_get_typography_font_size_value', 'css_vars' => '--wp--preset--font-size--$slug', 'classes' => ['.has-$slug-font-size' => 'font-size'], 'properties' => ['font-size']], ['path' => ['typography', 'fontFamilies'], 'prevent_override' => false, 'use_default_names' => false, 'value_key' => 'fontFamily', 'css_vars' => '--wp--preset--font-family--$slug', 'classes' => ['.has-$slug-font-family' => 'font-family'], 'properties' => ['font-family']], ['path' => ['spacing', 'spacingSizes'], 'prevent_override' => ['spacing', 'defaultSpacingSizes'], 'use_default_names' => true, 'value_key' => 'size', 'css_vars' => '--wp--preset--spacing--$slug', 'classes' => [], 'properties' => ['padding', 'margin']], ['path' => ['shadow', 'presets'], 'prevent_override' => ['shadow', 'defaultPresets'], 'use_default_names' => false, 'value_key' => 'shadow', 'css_vars' => '--wp--preset--shadow--$slug', 'classes' => [], 'properties' => ['box-shadow']], ['path' => ['border', 'radiusSizes'], 'prevent_override' => false, 'use_default_names' => false, 'value_key' => 'size', 'css_vars' => '--wp--preset--border-radius--$slug', 'classes' => [], 'properties' => ['border-radius']], ['path' => ['dimensions', 'dimensionSizes'], 'prevent_override' => false, 'use_default_names' => false, 'value_key' => 'size', 'css_vars' => '--wp--preset--dimension--$slug', 'classes' => [], 'properties' => ['width', 'height', 'min-height']]];
    const PROPERTIES_METADATA = ['aspect-ratio' => ['dimensions', 'aspectRatio'], 'background' => ['color', 'gradient'], 'background-color' => ['color', 'background'], 'background-image' => ['background', 'backgroundImage'], 'background-position' => ['background', 'backgroundPosition'], 'background-repeat' => ['background', 'backgroundRepeat'], 'background-size' => ['background', 'backgroundSize'], 'background-attachment' => ['background', 'backgroundAttachment'], 'border-radius' => ['border', 'radius'], 'border-top-left-radius' => ['border', 'radius', 'topLeft'], 'border-top-right-radius' => ['border', 'radius', 'topRight'], 'border-bottom-left-radius' => ['border', 'radius', 'bottomLeft'], 'border-bottom-right-radius' => ['border', 'radius', 'bottomRight'], 'border-color' => ['border', 'color'], 'border-width' => ['border', 'width'], 'border-style' => ['border', 'style'], 'border-top-color' => ['border', 'top', 'color'], 'border-top-width' => ['border', 'top', 'width'], 'border-top-style' => ['border', 'top', 'style'], 'border-right-color' => ['border', 'right', 'color'], 'border-right-width' => ['border', 'right', 'width'], 'border-right-style' => ['border', 'right', 'style'], 'border-bottom-color' => ['border', 'bottom', 'color'], 'border-bottom-width' => ['border', 'bottom', 'width'], 'border-bottom-style' => ['border', 'bottom', 'style'], 'border-left-color' => ['border', 'left', 'color'], 'border-left-width' => ['border', 'left', 'width'], 'border-left-style' => ['border', 'left', 'style'], 'color' => ['color', 'text'], 'text-align' => ['typography', 'textAlign'], 'column-count' => ['typography', 'textColumns'], 'font-family' => ['typography', 'fontFamily'], 'font-size' => ['typography', 'fontSize'], 'font-style' => ['typography', 'fontStyle'], 'font-weight' => ['typography', 'fontWeight'], 'letter-spacing' => ['typography', 'letterSpacing'], 'line-height' => ['typography', 'lineHeight'], 'margin' => ['spacing', 'margin'], 'margin-top' => ['spacing', 'margin', 'top'], 'margin-right' => ['spacing', 'margin', 'right'], 'margin-bottom' => ['spacing', 'margin', 'bottom'], 'margin-left' => ['spacing', 'margin', 'left'], 'min-height' => ['dimensions', 'minHeight'], 'min-width' => ['dimensions', 'minWidth'], 'outline-color' => ['outline', 'color'], 'outline-offset' => ['outline', 'offset'], 'outline-style' => ['outline', 'style'], 'outline-width' => ['outline', 'width'], 'padding' => ['spacing', 'padding'], 'padding-top' => ['spacing', 'padding', 'top'], 'padding-right' => ['spacing', 'padding', 'right'], 'padding-bottom' => ['spacing', 'padding', 'bottom'], 'padding-left' => ['spacing', 'padding', 'left'], '--wp--style--root--padding' => ['spacing', 'padding'], '--wp--style--root--padding-top' => ['spacing', 'padding', 'top'], '--wp--style--root--padding-right' => ['spacing', 'padding', 'right'], '--wp--style--root--padding-bottom' => ['spacing', 'padding', 'bottom'], '--wp--style--root--padding-left' => ['spacing', 'padding', 'left'], 'text-decoration' => ['typography', 'textDecoration'], 'text-shadow' => ['typography', 'textShadow'], 'text-transform' => ['typography', 'textTransform'], 'text-indent' => ['typography', 'textIndent'], 'filter' => ['filter', 'duotone'], 'box-shadow' => ['shadow'], 'height' => ['dimensions', 'height'], 'width' => ['dimensions', 'width'], 'writing-mode' => ['typography', 'writingMode']];
    const INDIRECT_PROPERTIES_METADATA = ['gap' => [['spacing', 'blockGap']], 'column-gap' => [['spacing', 'blockGap', 'left']], 'row-gap' => [['spacing', 'blockGap', 'top']], 'max-width' => [['layout', 'contentSize'], ['layout', 'wideSize']], 'background-image' => [['background', 'backgroundImage', 'url'], ['background', 'gradient']]];
    const PROTECTED_PROPERTIES = ['spacing.blockGap' => ['spacing', 'blockGap']];
    const VALID_TOP_LEVEL_KEYS = ['blockTypes', 'customTemplates', 'description', 'patterns', 'settings', 'slug', 'styles', 'templateParts', 'title', 'version'];
    const VALID_SETTINGS = ['appearanceTools' => NULL, 'useRootPaddingAwareAlignments' => NULL, 'background' => ['backgroundImage' => NULL, 'backgroundSize' => NULL, 'gradient' => NULL], 'border' => ['color' => NULL, 'radius' => NULL, 'radiusSizes' => NULL, 'style' => NULL, 'width' => NULL], 'color' => ['background' => NULL, 'custom' => NULL, 'customDuotone' => NULL, 'customGradient' => NULL, 'defaultDuotone' => NULL, 'defaultGradients' => NULL, 'defaultPalette' => NULL, 'duotone' => NULL, 'gradients' => NULL, 'link' => NULL, 'heading' => NULL, 'button' => NULL, 'caption' => NULL, 'palette' => NULL, 'text' => NULL], 'custom' => NULL, 'dimensions' => ['aspectRatio' => NULL, 'aspectRatios' => NULL, 'defaultAspectRatios' => NULL, 'dimensionSizes' => NULL, 'height' => NULL, 'minHeight' => NULL, 'minWidth' => NULL, 'width' => NULL], 'layout' => ['contentSize' => NULL, 'wideSize' => NULL, 'allowEditing' => NULL, 'allowCustomContentAndWideSize' => NULL], 'lightbox' => ['enabled' => true, 'allowEditing' => true], 'position' => ['fixed' => NULL, 'sticky' => NULL], 'blockVisibility' => ['allowEditing' => true], 'spacing' => ['customSpacingSize' => NULL, 'defaultSpacingSizes' => NULL, 'spacingSizes' => NULL, 'spacingScale' => NULL, 'blockGap' => NULL, 'margin' => NULL, 'padding' => NULL, 'units' => NULL], 'shadow' => ['presets' => NULL, 'defaultPresets' => NULL], 'typography' => ['fluid' => NULL, 'customFontSize' => NULL, 'defaultFontSizes' => NULL, 'dropCap' => NULL, 'fontFamilies' => NULL, 'fontSizes' => NULL, 'fontStyle' => NULL, 'fontWeight' => NULL, 'letterSpacing' => NULL, 'lineHeight' => NULL, 'textAlign' => NULL, 'textColumns' => NULL, 'textDecoration' => NULL, 'textIndent' => NULL, 'textTransform' => NULL, 'writingMode' => NULL], 'viewport' => ['mobile' => NULL, 'tablet' => NULL]];
    const FONT_FAMILY_SCHEMA = [['fontFamily' => NULL, 'name' => NULL, 'slug' => NULL, 'fontFace' => [['ascentOverride' => NULL, 'descentOverride' => NULL, 'fontDisplay' => NULL, 'fontFamily' => NULL, 'fontFeatureSettings' => NULL, 'fontStyle' => NULL, 'fontStretch' => NULL, 'fontVariationSettings' => NULL, 'fontWeight' => NULL, 'lineGapOverride' => NULL, 'sizeAdjust' => NULL, 'src' => NULL, 'unicodeRange' => NULL]]]];
    const VALID_STYLES = ['background' => ['backgroundImage' => NULL, 'backgroundPosition' => NULL, 'backgroundRepeat' => NULL, 'backgroundSize' => NULL, 'backgroundAttachment' => NULL, 'gradient' => NULL], 'border' => ['color' => NULL, 'radius' => NULL, 'style' => NULL, 'width' => NULL, 'top' => NULL, 'right' => NULL, 'bottom' => NULL, 'left' => NULL], 'color' => ['background' => NULL, 'gradient' => NULL, 'text' => NULL], 'dimensions' => ['aspectRatio' => NULL, 'height' => NULL, 'minHeight' => NULL, 'minWidth' => NULL, 'width' => NULL], 'filter' => ['duotone' => NULL], 'outline' => ['color' => NULL, 'offset' => NULL, 'style' => NULL, 'width' => NULL], 'shadow' => NULL, 'spacing' => ['margin' => NULL, 'padding' => NULL, 'blockGap' => NULL], 'typography' => ['fontFamily' => NULL, 'fontSize' => NULL, 'fontStyle' => NULL, 'fontWeight' => NULL, 'letterSpacing' => NULL, 'lineHeight' => NULL, 'textAlign' => NULL, 'textColumns' => NULL, 'textDecoration' => NULL, 'textIndent' => NULL, 'textShadow' => NULL, 'textTransform' => NULL, 'writingMode' => NULL], 'css' => NULL];
    const VALID_ELEMENT_PSEUDO_SELECTORS = ['link' => [':link', ':any-link', ':visited', ':hover', ':focus', ':focus-visible', ':active'], 'button' => [':link', ':any-link', ':visited', ':hover', ':focus', ':focus-visible', ':active']];
    const VALID_BLOCK_PSEUDO_SELECTORS = ['core/button' => [':hover', ':focus', ':focus-visible', ':active'], 'core/navigation-link' => [':hover', ':focus', ':focus-visible', ':active']];
    const VALID_BLOCK_CUSTOM_STATES = ['core/navigation-link' => ['-current']];
    const DEFAULT_VIEWPORT_BREAKPOINTS = ['mobile' => '480px', 'tablet' => '782px'];
    const ELEMENTS = ['link' => 'a:where(:not(.wp-element-button))', 'heading' => 'h1, h2, h3, h4, h5, h6', 'h1' => 'h1', 'h2' => 'h2', 'h3' => 'h3', 'h4' => 'h4', 'h5' => 'h5', 'h6' => 'h6', 'button' => '.wp-element-button, .wp-block-button__link', 'caption' => '.wp-element-caption, .wp-block-audio figcaption, .wp-block-embed figcaption, .wp-block-gallery figcaption, .wp-block-image figcaption, .wp-block-table figcaption, .wp-block-video figcaption', 'cite' => 'cite', 'textInput' => 'textarea, input:where([type=email],[type=number],[type=password],[type=search],[type=text],[type=tel],[type=url])', 'select' => 'select'];
    const __EXPERIMENTAL_ELEMENT_CLASS_NAMES = ['button' => 'wp-element-button', 'caption' => 'wp-element-caption'];
    const BLOCK_SUPPORT_FEATURE_LEVEL_SELECTORS = ['__experimentalBorder' => 'border', 'color' => 'color', 'dimensions' => 'dimensions', 'spacing' => 'spacing', 'typography' => 'typography'];
    const APPEARANCE_TOOLS_OPT_INS = [['background', 'backgroundImage'], ['background', 'backgroundSize'], ['background', 'gradient'], ['border', 'color'], ['border', 'radius'], ['border', 'style'], ['border', 'width'], ['color', 'link'], ['color', 'heading'], ['color', 'button'], ['color', 'caption'], ['dimensions', 'aspectRatio'], ['dimensions', 'height'], ['dimensions', 'minHeight'], ['dimensions', 'minWidth'], ['dimensions', 'width'], ['position', 'sticky'], ['spacing', 'blockGap'], ['spacing', 'margin'], ['spacing', 'padding'], ['typography', 'lineHeight'], ['typography', 'textColumns']];
    const LATEST_SCHEMA = 3;
    protected $theme_json = NULL;
    protected static $blocks_metadata = [];

    public static function get_viewport_media_queries($viewport_settings = NULL, $options = [])
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::get_viewport_media_queries');
        return null;
    }

    private static function is_valid_viewport_breakpoint_size($value)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::is_valid_viewport_breakpoint_size');
        return null;
    }

    private static function get_viewport_breakpoint_value_in_pixels($value)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::get_viewport_breakpoint_value_in_pixels');
        return null;
    }

    private static function sanitize_viewport_settings($viewport_settings)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::sanitize_viewport_settings');
        return null;
    }

    protected static function schema_in_root_and_per_origin($schema)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::schema_in_root_and_per_origin');
        return null;
    }

    private function process_pseudo_selectors($node, $base_selector, $settings, $block_name, $block_metadata = NULL, $style_variation = NULL)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::process_pseudo_selectors');
        return null;
    }

    public static function get_element_class_name($element)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::get_element_class_name');
        return null;
    }

    public function __construct($theme_json = ['version' => 3], $origin = 'theme')
    {
        $this->theme_json = is_array($theme_json) ? $theme_json : [];
    }

    private static function unwrap_shared_block_style_variations($theme_json, $valid_variations)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::unwrap_shared_block_style_variations');
        return null;
    }

    protected static function maybe_opt_in_into_settings($theme_json)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::maybe_opt_in_into_settings');
        return null;
    }

    protected static function do_opt_in_into_settings(&$context)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::do_opt_in_into_settings');
    }

    protected static function sanitize($input, $valid_block_names, $valid_element_names, $valid_variations)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::sanitize');
        return null;
    }

    protected static function append_to_selector($selector, $to_append)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::append_to_selector');
        return null;
    }

    protected static function prepend_to_selector($selector, $to_prepend)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::prepend_to_selector');
        return null;
    }

    protected static function split_selector_list($selector)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::split_selector_list');
        return [];
    }

    protected static function get_blocks_metadata()
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::get_blocks_metadata');
        return null;
    }

    protected static function remove_keys_not_in_schema($tree, $schema)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::remove_keys_not_in_schema');
        return null;
    }

    public function get_settings()
    {
        return (array) ($this->theme_json['settings'] ?? []);
    }

    public function get_stylesheet($types = ['variables', 'styles', 'presets'], $origins = NULL, $options = [])
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::get_stylesheet');
        return null;
    }

    /** A block's own CSS under its selector, nested rules unfolded (Minn\Theme\CustomCss::scoped, probe block-supports). */
    public static function process_blocks_custom_css($css, $selector)
    {
        return \Minn\Theme\CustomCss::scoped((string) $css, (string) $selector);
    }

    /** The styles' own CSS: the top-level css, then each block's (deprecated since 6.7.0 for get_stylesheet). */
    public function get_custom_css()
    {
        _deprecated_function(__METHOD__, '6.7.0', 'get_stylesheet');
        return \Minn\Theme\CustomCss::ofStyles((array) ($this->theme_json['styles'] ?? []));
    }

    public function get_custom_templates()
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::get_custom_templates');
        return null;
    }

    public function get_template_parts()
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::get_template_parts');
        return null;
    }

    protected function get_block_classes($style_nodes)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::get_block_classes');
        return null;
    }

    protected function get_layout_styles($block_metadata, $options = [])
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::get_layout_styles');
        return null;
    }

    protected function get_preset_classes($setting_nodes, $origins)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::get_preset_classes');
        return null;
    }

    protected function get_css_variables($nodes, $origins)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::get_css_variables');
        return null;
    }

    private static function get_feature_selector($feature_selectors, $feature_key, $default_selector)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::get_feature_selector');
        return '';
    }

    protected static function to_ruleset($selector, $declarations)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::to_ruleset');
        return null;
    }

    protected static function compute_preset_classes($settings, $selector, $origins)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::compute_preset_classes');
        return null;
    }

    public static function scope_selector($scope, $selector)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::scope_selector');
        return null;
    }

    protected static function scope_style_node_selectors($scope, $node)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::scope_style_node_selectors');
        return null;
    }

    protected static function get_settings_values_by_slug($settings, $preset_metadata, $origins)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::get_settings_values_by_slug');
        return null;
    }

    protected static function get_settings_slugs($settings, $preset_metadata, $origins = NULL)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::get_settings_slugs');
        return null;
    }

    protected static function replace_slug_in_string($input, $slug)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::replace_slug_in_string');
        return null;
    }

    protected static function compute_preset_vars($settings, $origins)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::compute_preset_vars');
        return null;
    }

    protected static function compute_theme_vars($settings)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::compute_theme_vars');
        return null;
    }

    protected static function flatten_tree($tree, $prefix = '', $token = '--')
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::flatten_tree');
        return null;
    }

    protected static function compute_style_properties($styles, $settings = [], $properties = NULL, $theme_json = NULL, $selector = NULL, $use_root_padding = NULL)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::compute_style_properties');
        return null;
    }

    protected static function get_property_value($styles, $path, $theme_json = NULL)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::get_property_value');
        return null;
    }

    protected static function get_setting_nodes($theme_json, $selectors = [])
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::get_setting_nodes');
        return null;
    }

    protected static function get_style_nodes($theme_json, $selectors = [], $options = [])
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::get_style_nodes');
        return null;
    }

    public function get_styles_block_nodes()
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::get_styles_block_nodes');
        return null;
    }

    private static function update_separator_declarations($declarations)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::update_separator_declarations');
        return null;
    }

    private static function update_paragraph_text_indent_selector($feature_declarations, $settings, $block_name)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::update_paragraph_text_indent_selector');
        return null;
    }

    private static function update_button_width_declarations($feature_declarations, $settings)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::update_button_width_declarations');
        return null;
    }

    private static function get_block_nodes($theme_json, $selectors = [], $options = [])
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::get_block_nodes');
        return null;
    }

    public function get_styles_for_block($block_metadata)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::get_styles_for_block');
        return null;
    }

    public function get_root_layout_rules($selector, $block_metadata, $options = [])
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::get_root_layout_rules');
        return null;
    }

    protected static function get_metadata_boolean($data, $path, $default_value = false)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::get_metadata_boolean');
        return null;
    }

    /** Another layer over this one: maps merge key by key, lists (palettes, sizes) are replaced. */
    public function merge($incoming)
    {
        if ($incoming instanceof WP_Theme_JSON) {
            $this->theme_json = Minn\Theme\Theme::merge((array) $this->theme_json, (array) $incoming->get_raw_data());
        }
    }

    public function get_svg_filters($origins)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::get_svg_filters');
        return null;
    }

    protected static function should_override_preset($theme_json, $path, $override)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::should_override_preset');
        return null;
    }

    protected static function get_default_slugs($data, $node_path)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::get_default_slugs');
        return null;
    }

    protected function get_name_from_defaults($slug, $base_path)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::get_name_from_defaults');
        return null;
    }

    protected static function filter_slugs($node, $slugs)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::filter_slugs');
        return null;
    }

    public static function remove_insecure_properties($theme_json, $origin = 'theme')
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::remove_insecure_properties');
        return null;
    }

    protected static function remove_insecure_element_styles($elements, $responsive_media_queries = NULL)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::remove_insecure_element_styles');
        return null;
    }

    protected static function remove_insecure_inner_block_styles($blocks, $responsive_media_queries = NULL)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::remove_insecure_inner_block_styles');
        return null;
    }

    private static function preserve_valid_typed_settings($input, &$output, $schema, $path = [])
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::preserve_valid_typed_settings');
        return null;
    }

    protected static function remove_insecure_settings($input, $is_root = false)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::remove_insecure_settings');
        return null;
    }

    protected static function remove_insecure_styles($input)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::remove_insecure_styles');
        return null;
    }

    protected static function is_safe_css_declaration($property_name, $property_value)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::is_safe_css_declaration');
        return null;
    }

    private static function remove_indirect_properties($input, &$output)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::remove_indirect_properties');
        return null;
    }

    public function get_raw_data()
    {
        return $this->theme_json;
    }

    public static function get_from_editor_settings($settings)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::get_from_editor_settings');
        return null;
    }

    public function get_patterns()
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::get_patterns');
        return null;
    }

    public function get_data()
    {
        return $this->theme_json;
    }

    public function set_spacing_sizes()
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::set_spacing_sizes');
        return null;
    }

    private static function merge_spacing_sizes($base, $incoming)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::merge_spacing_sizes');
        return null;
    }

    private static function compute_spacing_sizes($spacing_scale)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::compute_spacing_sizes');
        return null;
    }

    private static function convert_custom_properties($value)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::convert_custom_properties');
        return null;
    }

    private static function resolve_custom_css_format($tree)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::resolve_custom_css_format');
        return null;
    }

    protected static function get_block_selectors($block_type, $root_selector)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::get_block_selectors');
        return null;
    }

    protected static function get_block_element_selectors($root_selector)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::get_block_element_selectors');
        return null;
    }

    protected function get_feature_declarations_for_node($metadata, &$node)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::get_feature_declarations_for_node');
        return null;
    }

    private static function convert_variables_to_value($styles, $values)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::convert_variables_to_value');
        return null;
    }

    public static function resolve_variables($theme_json)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::resolve_variables');
        return null;
    }

    protected static function get_block_style_variation_selector($variation_name, $block_selector)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::get_block_style_variation_selector');
        return null;
    }

    protected static function get_block_style_variation_feature_selector($style_variation, $feature_selector)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::get_block_style_variation_feature_selector');
        return null;
    }

    protected static function get_valid_block_style_variations($blocks_metadata = [])
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::get_valid_block_style_variations');
        return null;
    }

    private static function get_block_name_from_metadata_path($block_metadata)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Theme_JSON::get_block_name_from_metadata_path');
        return null;
    }
}
