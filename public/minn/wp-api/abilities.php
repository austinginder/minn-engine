<?php
/** The abilities API: categories and abilities registered on their own init actions, as objects. Registry in Minn\Runtime\Abilities. */

use Minn\Runtime\Abilities;

function wp_register_ability_category(string $slug, array $args)
{
    $row = Abilities::registerCategory($slug, $args);
    return $row === null ? null : new WP_Ability_Category($slug, $row);
}

function wp_register_ability(string $name, array $args)
{
    $row = Abilities::register($name, $args);
    return $row === null ? null : new WP_Ability($name, $row);
}

function wp_unregister_ability_category(string $slug)
{
    $row = Abilities::unregisterCategory($slug);
    return $row === null ? null : new WP_Ability_Category($slug, $row);
}

function wp_unregister_ability(string $name)
{
    $row = Abilities::unregister($name);
    return $row === null ? null : new WP_Ability($name, $row);
}

function wp_get_ability(string $name)
{
    $row = Abilities::find($name);
    return $row === null ? null : new WP_Ability($name, $row);
}

function wp_get_abilities($args = [])
{
    $category = (string) (((array) $args)['category'] ?? '');
    $out = [];
    foreach (Abilities::all() as $name => $row) {
        if ($category === '' || (string) ($row['category'] ?? '') === $category) {
            $out[$name] = new WP_Ability($name, $row);
        }
    }
    return $out;
}

function wp_has_ability(string $name)
{
    return Abilities::ability($name) !== null;
}

function wp_get_ability_category(string $slug)
{
    $row = Abilities::findCategory($slug);
    return $row === null ? null : new WP_Ability_Category($slug, $row);
}

function wp_get_ability_categories()
{
    return array_map(static fn (array $row) => new WP_Ability_Category((string) $row['slug'], $row), Abilities::allCategories());
}

function wp_has_ability_category(string $slug)
{
    return Abilities::category($slug) !== null;
}
