<?php

use Minn\Runtime\Abilities;

/**
 * The ability categories registry (probe abilities-classes): one instance,
 * whose first request fires the categories init, handed this registry. It
 * takes a registration at any time, refusing one with the reference's
 * notice. The registrations live in Minn\Runtime\Abilities; it hands them
 * out as WP_Ability_Category objects. It refuses to be serialized or
 * unserialized.
 */
final class WP_Ability_Categories_Registry
{
    private static $instance = null;

    public function register(string $slug, array $args): ?WP_Ability_Category
    {
        return self::category($slug, Abilities::registerCategory($slug, $args));
    }

    public function unregister(string $slug): ?WP_Ability_Category
    {
        return self::category($slug, Abilities::unregisterCategory($slug));
    }

    public function get_all_registered(): array
    {
        return array_map(static fn (array $row) => new WP_Ability_Category((string) $row['slug'], $row), Abilities::allCategories());
    }

    public function is_registered(string $slug): bool
    {
        return Abilities::category($slug) !== null;
    }

    public function get_registered(string $slug): ?WP_Ability_Category
    {
        return self::category($slug, Abilities::findCategory($slug));
    }

    public static function get_instance(): ?self
    {
        if (self::$instance === null) {
            self::$instance = new self();
            Abilities::initializeCategories();
        }
        return self::$instance;
    }

    public function __wakeup(): void
    {
        throw new LogicException(__CLASS__ . ' should never be unserialized.');
    }

    public function __sleep(): array
    {
        throw new LogicException(__CLASS__ . ' should never be serialized.');
    }

    private static function category(string $slug, ?array $row): ?WP_Ability_Category
    {
        return $row === null ? null : new WP_Ability_Category($slug, $row);
    }
}
