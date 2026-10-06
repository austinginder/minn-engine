<?php

use Minn\Blocks\StyleEngine;

/**
 * The style engine's entry points (probe style-engine): a block's style
 * object parsed into declarations and class names (Blocks\StyleEngine),
 * declarations compiled to CSS, rules kept in named stores and compiled
 * into a stylesheet.
 */
#[AllowDynamicProperties]
final class WP_Style_Engine
{
    const BLOCK_STYLE_DEFINITIONS_METADATA = StyleEngine::DEFINITIONS;

    public static function store_css_rule($store_name, $css_selector, $css_declarations, $rules_group = '')
    {
        if (empty($store_name) || empty($css_selector) || empty($css_declarations)) {
            return;
        }
        static::get_store($store_name)->add_rule($css_selector, $rules_group)->add_declarations($css_declarations);
    }

    public static function get_store($store_name)
    {
        return WP_Style_Engine_CSS_Rules_Store::get_store($store_name);
    }

    public static function parse_block_styles($block_styles, $options)
    {
        return empty($block_styles) || !is_array($block_styles) ? ['declarations' => [], 'classnames' => []] : StyleEngine::parse($block_styles, (array) $options);
    }

    public static function compile_css($css_declarations, $css_selector)
    {
        if (empty($css_declarations) || !is_array($css_declarations)) {
            return '';
        }
        return $css_selector ? (new WP_Style_Engine_CSS_Rule($css_selector, $css_declarations))->get_css() : (new WP_Style_Engine_CSS_Declarations($css_declarations))->get_declarations_string();
    }

    public static function compile_stylesheet_from_css_rules($css_rules, $options = [])
    {
        $processor = new WP_Style_Engine_Processor();
        $processor->add_rules($css_rules);
        return $processor->get_css($options);
    }

    protected static function get_slug_from_preset_value($style_value, $property_key)
    {
        return StyleEngine::slug($style_value, (string) $property_key) ?? '';
    }

    protected static function get_css_var_value($style_value, $css_vars)
    {
        return StyleEngine::var($style_value, (array) $css_vars);
    }

    protected static function is_valid_style_value($style_value)
    {
        return $style_value === '0' || !empty($style_value);
    }
}
