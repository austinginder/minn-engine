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

function wp_get_abilities($args = [])
{
    $category = (string) (((array) $args)['category'] ?? '');
    $all = WP_Abilities_Registry::get_instance()->get_all_registered();
    return $category === '' ? $all : array_filter($all, static fn (WP_Ability $ability) => $ability->get_category() === $category);
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
