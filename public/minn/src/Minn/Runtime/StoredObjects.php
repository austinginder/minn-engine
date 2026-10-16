<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Support\Serialized;

/**
 * The classes a stored blob may name and come back as. The serialized
 * reader never instantiates by itself; the facade registers one factory per
 * value class it owns (WP_Post, WP_Term, WP_Comment), and any other class
 * comes back as itself only when it is plain data: a class that exists,
 * written in PHP, that runs nothing as it is revived or let go (no
 * __wakeup, __unserialize or __destruct, not Serializable), so no stored
 * blob can make code run (PHP object injection). Plugins keep settings in
 * such objects (WooCommerce PayPal Payments does) and read them back typed,
 * as the reference revives them. Anything else stays a stdClass of its
 * properties.
 */
final class StoredObjects
{
    /** PHP's array wrappers a stored record may name, by lower-cased class name. */
    private const WRAPPERS = ['arrayobject' => \ArrayObject::class, 'arrayiterator' => \ArrayIterator::class, 'recursivearrayiterator' => \RecursiveArrayIterator::class];

    /** @var array<string, \Closure(\stdClass): object> keyed by lower-cased class name */
    private static array $factories = [];

    /**
     * A stored value read back (maybe_unserialize): serialized data decoded,
     * its objects revived by the registered factories; anything else, or
     * data that does not decode, as given.
     */
    public static function maybeDecode(mixed $data): mixed
    {
        if (!is_string($data) || !Serialized::looksSerialized($data)) {
            return $data;
        }
        $decoded = Serialized::decode(trim($data), self::reviver());
        return $decoded === Serialized::INVALID ? $data : $decoded;
    }

    /** Registers what a record naming this class becomes. */
    public static function register(string $class, \Closure $factory): void
    {
        self::$factories[strtolower($class)] = $factory;
    }

    /** The reviver the serialized reader takes: PHP's array wrappers rebuilt, a factory's object, a plain data object, or the properties as they are. */
    public static function reviver(): \Closure
    {
        return static function (string $class, \stdClass $properties, array $pairs = []): object {
            $name = strtolower($class);
            $wrapper = isset(self::WRAPPERS[$name]) ? self::arrayWrapper(self::WRAPPERS[$name], $properties) : null;
            if ($wrapper !== null) {
                return $wrapper;
            }
            $factory = self::$factories[$name] ?? null;
            return $factory === null ? (self::plain($class, $pairs) ?? $properties) : $factory($properties);
        };
    }

    /**
     * A plain data object revived from its stored properties, as PHP's own reader
     * would, without its constructor: each property set in the scope of the
     * class that declares it (a parent's private one by its owner's name, a
     * readonly one where it may be initialized). Null for a class that is not
     * plain data, for the engine's own classes, or when a value does not fit
     * a typed property.
     *
     * @param list<array{0: int|string, 1: mixed}> $pairs the stored names and values
     */
    private static function plain(string $class, array $pairs): ?object
    {
        if (preg_match('/^\\\\?[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*(\\\\[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*)*$/', $class) !== 1 || str_starts_with(strtolower(ltrim($class, '\\')), 'minn\\') || !class_exists($class)) {
            return null;
        }
        $reflection = new \ReflectionClass($class);
        if ($reflection->isInternal() || $reflection->isAbstract() || $reflection->isEnum() || $reflection->implementsInterface(\Serializable::class)) {
            return null;
        }
        foreach (['__wakeup', '__unserialize', '__destruct'] as $magic) {
            if ($reflection->hasMethod($magic)) {
                return null;
            }
        }
        $object = $reflection->newInstanceWithoutConstructor();
        try {
            foreach ($pairs as [$raw, $value]) {
                [$owner, $property] = self::owner($reflection, (string) $raw);
                if (strcasecmp($owner, $reflection->getName()) !== 0 && !is_subclass_of($reflection->getName(), $owner)) {
                    return null;
                }
                \Closure::bind(static function (object $target) use ($property, $value): void {
                    $target->{$property} = $value;
                }, null, $owner)($object);
            }
        } catch (\Throwable) {
            return null;
        }
        return $object;
    }

    /**
     * The class whose scope sets a stored property, and the property's name:
     * "\0Owner\0name" is Owner's private one, "\0*\0name" a protected one,
     * any other name the class's own (or a parent's, where it is declared).
     *
     * @return array{0: string, 1: string}
     */
    private static function owner(\ReflectionClass $reflection, string $raw): array
    {
        if (str_starts_with($raw, "\0")) {
            $parts = explode("\0", $raw, 3);
            $name = $parts[2] ?? '';
            if (($parts[1] ?? '*') !== '*') {
                return [$parts[1], $name];
            }
            $raw = $name;
        }
        return [$reflection->hasProperty($raw) ? $reflection->getProperty($raw)->getDeclaringClass()->getName() : $reflection->getName(), $raw];
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
