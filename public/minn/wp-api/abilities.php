<?php
/** The abilities API: a recording registry, initialised lazily the reference's way. Registry in Minn\Runtime\Abilities. */

use Minn\Runtime\Abilities;

function wp_register_ability_category(string $slug, array $args)
{
    return Abilities::registerCategory($slug, $args);
}

function wp_register_ability(string $name, array $args)
{
    $row = Abilities::register($name, $args);
    return $row === null ? null : new WP_Ability($name, $row);
}

function wp_unregister_ability_category(string $slug)
{
    return Abilities::unregister($slug, category: true);
}

function wp_unregister_ability(string $name)
{
    return Abilities::unregister($name);
}

function wp_get_ability(string $name)
{
    $row = Abilities::find($name);
    return $row === null ? null : new WP_Ability($name, $row);
}

function wp_get_abilities($args = [])
{
    $rows = Abilities::all();
    $category = (string) (((array) $args)['category'] ?? '');
    if ($category !== '') {
        $rows = array_filter($rows, static fn (array $row) => (string) ($row['category'] ?? '') === $category);
    }
    return array_map(static fn (array $row) => new WP_Ability((string) $row['name'], $row), array_values($rows));
}

function wp_has_ability(string $name)
{
    return Abilities::find($name) !== null;
}

function wp_get_ability_category(string $slug)
{
    return Abilities::find($slug, category: true);
}

function wp_get_ability_categories()
{
    return Abilities::all(categories: true);
}

function wp_has_ability_category(string $slug)
{
    return Abilities::find($slug, category: true) !== null;
}
