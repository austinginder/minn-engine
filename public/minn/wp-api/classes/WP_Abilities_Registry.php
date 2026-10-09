<?php

use Minn\Runtime\Abilities;

/**
 * The abilities registry (probe abilities-classes): one instance, whose
 * first request fires the categories init and then the abilities init, each
 * handed its registry. It takes a registration at any time, refusing one
 * with the reference's notice. The registrations live in
 * Minn\Runtime\Abilities; it hands them out as WP_Ability objects. It
 * refuses to be serialized or unserialized.
 */
final class WP_Abilities_Registry
{
    private static $instance = null;

    public function register(string $name, array $args): ?WP_Ability
    {
        return self::ability($name, Abilities::register($name, $args));
    }

    public function unregister(string $name): ?WP_Ability
    {
        return self::ability($name, Abilities::unregister($name));
    }

    public function get_all_registered(): array
    {
        return array_map(static fn (array $row) => new WP_Ability((string) $row['name'], $row), Abilities::all());
    }

    public function is_registered(string $name): bool
    {
        return Abilities::ability($name) !== null;
    }

    public function get_registered(string $name): ?WP_Ability
    {
        return self::ability($name, Abilities::find($name));
    }

    public static function get_instance(): ?self
    {
        if (self::$instance === null) {
            self::$instance = new self();
            Abilities::initialize();
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

    private static function ability(string $name, ?array $row): ?WP_Ability
    {
        return $row === null ? null : new WP_Ability($name, $row);
    }
}
