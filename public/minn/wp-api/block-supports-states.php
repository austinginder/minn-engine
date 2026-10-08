<?php
/**
 * Block state styles (wp-includes/block-supports/states.php, probe
 * block-supports): a block whose type takes pseudo-class states
 * (WP_Theme_JSON::VALID_BLOCK_PSEUDO_SELECTORS) and whose style names some
 * (style[":hover"]) gets a wp-states-* class, and each state's styles as
 * !important rules under it, split by the selectors the block type gives
 * its features, in the block-supports store. The selector arithmetic and
 * the fallbacks are Minn\Blocks\States.
 */

use Minn\Blocks\States;

/** A selector list split at its top-level commas, each part as written. */
function wp_split_selector_list($selector)
{
    return States::split((string) $selector);
}

/** A state on each selector of a block's list, its leading compound selector swapped for the base. */
function wp_build_state_selector($base_selector, $block_selector, $state)
{
    return States::selector((string) $base_selector, (string) $block_selector, (string) $state);
}

/** Each element's selectors under a root (theme.json's element selectors, a part at a time). */
function wp_get_block_state_element_selectors($root_selector)
{
    $selectors = [];
    foreach (WP_Theme_JSON::ELEMENTS as $element => $list) {
        $selectors[$element] = implode(',', array_map(static fn (string $part) => "{$root_selector} {$part}", States::split($list)));
    }
    return $selectors;
}

/** A preset value (var:preset|{kind}|{slug}) as its custom property, through arrays. */
function wp_normalize_state_preset_vars($value)
{
    return States::presetVars($value);
}

/** A state's style with its presets as custom properties. */
function wp_normalize_state_style_for_css_output($style)
{
    return States::presetVars($style);
}

/** Declarations with the background image unset under a lone background colour. */
function wp_get_state_declarations_with_background_resets($declarations)
{
    return States::backgroundResets((array) $declarations);
}

/** Declarations with a solid border style where a width or colour has none. */
function wp_get_state_declarations_with_fallback_border_styles($declarations)
{
    return States::borderFallbacks((array) $declarations);
}

/** A state's style with an aspect ratio and the heights unsetting each other. */
function wp_get_state_style_with_fallback_dimension_styles($state_style)
{
    return States::dimensionFallbacks((array) $state_style);
}

/** A state's style without its nested keys (elements, blocks). */
function wp_get_root_state_style($state_style, $nested_keys)
{
    return array_diff_key((array) $state_style, array_flip((array) $nested_keys));
}

/** A state's style split into groups by the selectors a block gives its features. */
function wp_get_state_style_groups($state_style, $block_selectors)
{
    return States::groups((array) $state_style, (array) $block_selectors);
}

/** Adds a style to a selector's group, merged into what it holds. */
function wp_add_state_style_group(&$groups, $selector, $style)
{
    States::addGroup($groups, $selector, (array) $style);
}

/** Adds a state's rule for a selector (its declarations through the style engine), when the style makes any. */
function wp_add_block_state_style_rule(&$css_rules, $state, $selector, $style, $rules_group = null)
{
    $declarations = (array) (wp_style_engine_get_styles((array) $style)['declarations'] ?? []);
    if ($declarations === []) {
        return;
    }
    $rule = ['state' => $state, 'selector' => $selector, 'declarations' => $declarations];
    if ($rules_group !== null) {
        $rule['rules_group'] = $rules_group;
    }
    $css_rules[] = $rule;
}

/**
 * The rules a block's state styles make: each state's style, presets as
 * custom properties and elements left out, split by the block type's
 * selectors (its root selector, null without one).
 */
function wp_get_block_state_style_rules($state_styles, $block_type, $rules_group = null)
{
    $selectors = $block_type instanceof WP_Block_Type ? (array) $block_type->selectors : [];
    $rules = [];
    foreach ((array) $state_styles as $state => $style) {
        $root = wp_get_root_state_style(States::presetVars((array) $style), ['elements']);
        foreach (States::groups($root, $selectors) as $group) {
            wp_add_block_state_style_rule($rules, $state, $group['selector'], $group['style'], $rules_group);
        }
    }
    return $rules;
}

/** The class a block's state rules hang on: wp-states- and a digest of the block and its rules. */
function wp_get_block_state_unique_class($block_name, $css_rules)
{
    return 'wp-states-' . substr(md5((string) $block_name . serialize($css_rules)), 0, 8);
}

/**
 * The block's state styles as !important rules under its wp-states-* class
 * (a lone background colour unsetting the image, a border width or colour
 * styled solid), kept in the block-supports store; the class on its first tag.
 */
function wp_render_block_states_support($block_content, $block)
{
    $name = (string) ($block['blockName'] ?? '');
    $states = array_intersect_key((array) ($block['attrs']['style'] ?? []), array_flip(WP_Theme_JSON::VALID_BLOCK_PSEUDO_SELECTORS[$name] ?? []));
    if ($block_content === '' || $states === []) {
        return $block_content;
    }
    $rules = wp_get_block_state_style_rules($states, WP_Block_Type_Registry::get_instance()->get_registered($name));
    if ($rules === []) {
        return $block_content;
    }
    $class = wp_get_block_state_unique_class($name, $rules);
    $css = [];
    foreach ($rules as $rule) {
        $declarations = array_map(static fn ($value) => "{$value} !important", States::backgroundResets((array) $rule['declarations']));
        $css[] = ['selector' => States::selector(".{$class}", (string) $rule['selector'], (string) $rule['state']), 'declarations' => States::borderFallbacks($declarations)];
    }
    wp_style_engine_get_stylesheet_from_css_rules($css, ['context' => 'block-supports']);
    return _minn_support_first_tag((string) $block_content, [$class]);
}
