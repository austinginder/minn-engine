<?php

declare(strict_types=1);

namespace Minn\Runtime;

/**
 * The abilities registry behind the wp_*_ability facade: categories and
 * abilities recorded per request. The reference initialises the API
 * lazily, firing wp_abilities_api_init once on first access so plugin
 * registrations land before any lookup.
 */
final class Abilities
{
    private const STATE = 'abilities';

    /** @return array{fired: bool, categories: array<string, array>, abilities: array<string, array>} */
    private static function state(): array
    {
        $state = Runtime::current()->get(self::STATE);
        return is_array($state) ? $state : ['fired' => false, 'categories' => [], 'abilities' => []];
    }

    private static function save(array $state): void
    {
        Runtime::current()->set(self::STATE, $state);
    }

    /** Fires the init action once, then answers every later call from the recorded state. */
    public static function initialize(): void
    {
        $state = self::state();
        if ($state['fired']) {
            return;
        }
        $state['fired'] = true;
        self::save($state);
        \do_action('wp_abilities_api_init');
    }

    public static function registerCategory(string $slug, array $args): bool
    {
        $state = self::state();
        if (isset($state['categories'][$slug])) {
            return false;
        }
        $state['categories'][$slug] = $args + ['slug' => $slug];
        self::save($state);
        return true;
    }

    public static function register(string $name, array $args): ?array
    {
        $state = self::state();
        if (isset($state['abilities'][$name]) || !str_contains($name, '/')) {
            return null;
        }
        $state['abilities'][$name] = $args + ['name' => $name];
        self::save($state);
        return $state['abilities'][$name];
    }

    public static function unregister(string $name, bool $category = false): bool
    {
        $state = self::state();
        $key = $category ? 'categories' : 'abilities';
        if (!isset($state[$key][$name])) {
            return false;
        }
        unset($state[$key][$name]);
        self::save($state);
        return true;
    }

    /** @return array<string, mixed>|null */
    public static function find(string $name, bool $category = false): ?array
    {
        self::initialize();
        $state = self::state();
        return $state[$category ? 'categories' : 'abilities'][$name] ?? null;
    }

    /** @return array<string, array> */
    public static function all(bool $categories = false): array
    {
        self::initialize();
        return self::state()[$categories ? 'categories' : 'abilities'];
    }
}
