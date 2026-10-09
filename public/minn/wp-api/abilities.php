<?php
/** The abilities API: categories and abilities registered on their own init actions, through the two registries (WP_Abilities_Registry, WP_Ability_Categories_Registry). */

function wp_register_ability_category(string $slug, array $args)
{
    if (!doing_action('wp_abilities_api_categories_init')) {
        _doing_it_wrong(__FUNCTION__, sprintf('Ability categories must be registered on the %1$s action. The ability category %2$s was not registered.', 'wp_abilities_api_categories_init', $slug), '6.9.0');
        return null;
    }
    return WP_Ability_Categories_Registry::get_instance()->register($slug, $args);
}

function wp_register_ability(string $name, array $args)
{
    if (!doing_action('wp_abilities_api_init')) {
        _doing_it_wrong(__FUNCTION__, sprintf('Abilities must be registered on the %1$s action. The ability %2$s was not registered.', 'wp_abilities_api_init', $name), '6.9.0');
        return null;
    }
    return WP_Abilities_Registry::get_instance()->register($name, $args);
}

function wp_unregister_ability_category(string $slug)
{
    return WP_Ability_Categories_Registry::get_instance()->unregister($slug);
}

function wp_unregister_ability(string $name)
{
    return WP_Abilities_Registry::get_instance()->unregister($name);
}

function wp_get_ability(string $name)
{
    return WP_Abilities_Registry::get_instance()->get_registered($name);
}

/**
 * The registered abilities, keyed by name, through one pipeline that always
 * runs: the category, namespace (a trailing slash ignored) and meta
 * conditions, then item_include_callback and wp_get_abilities_item_include
 * for each ability left, then result_callback (anything but an array is
 * none) and wp_get_abilities_result on the whole.
 */
function wp_get_abilities($args = [])
{
    $args = (array) $args;
    $item = isset($args['item_include_callback']) && is_callable($args['item_include_callback']) ? $args['item_include_callback'] : null;
    $matched = [];
    foreach (WP_Abilities_Registry::get_instance()->get_all_registered() as $name => $ability) {
        if (!_minn_ability_matches($ability, $args)) {
            continue;
        }
        $include = $item === null || (bool) $item($ability);
        if (apply_filters('wp_get_abilities_item_include', $include, $ability, $args)) {
            $matched[$name] = $ability;
        }
    }
    if (isset($args['result_callback']) && is_callable($args['result_callback'])) {
        $matched = $args['result_callback']($matched);
        $matched = is_array($matched) ? $matched : [];
    }
    return apply_filters('wp_get_abilities_result', $matched, $args);
}

/** @internal whether an ability meets wp_get_abilities()' category, namespace and meta conditions (each ignored when empty) */
function _minn_ability_matches(WP_Ability $ability, array $args): bool
{
    $category = (string) ($args['category'] ?? '');
    $namespace = rtrim((string) ($args['namespace'] ?? ''), '/');
    $meta = (array) ($args['meta'] ?? []);
    return ($category === '' || $ability->get_category() === $category)
        && ($namespace === '' || str_starts_with($ability->get_name(), $namespace . '/'))
        && ($meta === [] || _wp_get_abilities_match_meta($ability->get_meta(), $meta));
}

/**
 * Whether an ability's meta meets every condition: each key present, an
 * array condition met by an array value key by key (so a list matches
 * position by position), anything else equal strictly.
 */
function _wp_get_abilities_match_meta($meta, $conditions)
{
    foreach ($conditions as $key => $expected) {
        if (!is_array($meta) || !array_key_exists($key, $meta)) {
            return false;
        }
        $matches = is_array($expected) ? is_array($meta[$key]) && _wp_get_abilities_match_meta($meta[$key], $expected) : $meta[$key] === $expected;
        if (!$matches) {
            return false;
        }
    }
    return true;
}

function wp_has_ability(string $name)
{
    return WP_Abilities_Registry::get_instance()->is_registered($name);
}

function wp_get_ability_category(string $slug)
{
    return WP_Ability_Categories_Registry::get_instance()->get_registered($slug);
}

function wp_get_ability_categories()
{
    return WP_Ability_Categories_Registry::get_instance()->get_all_registered();
}

function wp_has_ability_category(string $slug)
{
    return WP_Ability_Categories_Registry::get_instance()->is_registered($slug);
}
