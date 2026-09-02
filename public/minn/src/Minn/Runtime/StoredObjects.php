<?php

declare(strict_types=1);

namespace Minn\Runtime;

/**
 * The classes a stored blob may name and come back as. The serialized
 * reader never instantiates; the facade registers one factory per value
 * class it owns (WP_Post, WP_Term, WP_Comment) and a record naming any
 * other class stays a stdClass of its properties. That is what lets a
 * transient the reference wrote, holding post objects, read back typed.
 */
final class StoredObjects
{
    /** @var array<string, \Closure(\stdClass): object> keyed by lower-cased class name */
    private static array $factories = [];

    /** Registers what a record naming this class becomes. */
    public static function register(string $class, \Closure $factory): void
    {
        self::$factories[strtolower($class)] = $factory;
    }

    /** The reviver the serialized reader takes: a factory's object, or the properties as they are. */
    public static function reviver(): \Closure
    {
        return static function (string $class, \stdClass $properties): object {
            $factory = self::$factories[strtolower($class)] ?? null;
            return $factory === null ? $properties : $factory($properties);
        };
    }

    /** Whether a factory is registered for the class. */
    public static function knows(string $class): bool
    {
        return isset(self::$factories[strtolower($class)]);
    }

    /** Forgets every factory, for suites. */
    public static function reset(): void
    {
        self::$factories = [];
    }
}
