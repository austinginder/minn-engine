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
    /** PHP's array wrappers a stored record may name, by lower-cased class name. */
    private const WRAPPERS = ['arrayobject' => \ArrayObject::class, 'arrayiterator' => \ArrayIterator::class, 'recursivearrayiterator' => \RecursiveArrayIterator::class];

    /** @var array<string, \Closure(\stdClass): object> keyed by lower-cased class name */
    private static array $factories = [];

    /** Registers what a record naming this class becomes. */
    public static function register(string $class, \Closure $factory): void
    {
        self::$factories[strtolower($class)] = $factory;
    }

    /** The reviver the serialized reader takes: PHP's array wrappers rebuilt, a factory's object, or the properties as they are. */
    public static function reviver(): \Closure
    {
        return static function (string $class, \stdClass $properties): object {
            $name = strtolower($class);
            $wrapper = isset(self::WRAPPERS[$name]) ? self::arrayWrapper(self::WRAPPERS[$name], $properties) : null;
            if ($wrapper !== null) {
                return $wrapper;
            }
            $factory = self::$factories[$name] ?? null;
            return $factory === null ? $properties : $factory($properties);
        };
    }

    /**
     * PHP's own array wrappers, stored as their four numbered parts (flags,
     * storage, member properties, iterator class), rebuilt through their
     * constructors so nothing else runs. Plugins keep settings in them
     * (CleanTalk does), and the reference reads them back as the real class;
     * written back, they serialize to the same bytes. One wrapping an object,
     * or carrying member properties, stays a record of its parts.
     *
     * @param class-string<\ArrayObject|\ArrayIterator> $class
     */
    private static function arrayWrapper(string $class, \stdClass $properties): ?object
    {
        $parts = get_object_vars($properties);
        if (!is_int($parts[0] ?? null) || !is_array($parts[1] ?? null) || ($parts[2] ?? []) !== []) {
            return null;
        }
        $wrapper = new $class($parts[1], $parts[0]);
        $iterator = $parts[3] ?? null;
        if ($wrapper instanceof \ArrayObject && is_string($iterator) && in_array(strtolower($iterator), ['arrayiterator', 'recursivearrayiterator'], true)) {
            $wrapper->setIteratorClass($iterator);
        }
        return $wrapper;
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
