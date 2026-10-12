<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Closure;

/**
 * wp_cache's options group, where option reads are kept as the reference
 * keeps them: the autoloaded list under alloptions, the names a read found
 * unset under notoptions, and one key per option not autoloaded. The
 * engine's own object cache holds it, or an object-cache.php drop-in once
 * the facade hands over its functions.
 *
 * A drop-in's cache outlives the request, and the engine writes some
 * options behind the store (its own state, the CLI), so a key left by an
 * earlier request is never trusted: each is read from the database the
 * first time a request asks for it, then kept in step for the rest of it.
 */
final class OptionsGroup
{
    public const GROUP = 'options';

    /** @var array{get: Closure, set: Closure, delete: Closure}|null a drop-in's cache functions */
    private static ?array $external = null;

    /** @var array<string, true> keys this request read or wrote, while a drop-in holds the group */
    private array $fresh = [];

    /** Hands the group to an object-cache.php drop-in's functions. */
    public static function useExternal(Closure $get, Closure $set, Closure $delete): void
    {
        self::$external = ['get' => $get, 'set' => $set, 'delete' => $delete];
    }

    /** A key's value, found only when this request may trust it. */
    public function get(string $key, ?bool &$found = null): mixed
    {
        if (self::$external === null) {
            return Runtime::cache()->get($key, self::GROUP, $found);
        }
        $found = false;
        if (!isset($this->fresh[$key])) {
            return false;
        }
        $value = (self::$external['get'])($key, $found);
        return $found ? $value : false;
    }

    /** Stores a key; a drop-in is written only when it holds something else. */
    public function set(string $key, mixed $value): void
    {
        if (self::$external === null) {
            Runtime::cache()->set($key, $value, self::GROUP);
            return;
        }
        if (!isset($this->fresh[$key])) {
            $this->fresh[$key] = true;
            $held = (self::$external['get'])($key, $found);
            if ($found && $held === $value) {
                return;
            }
        }
        (self::$external['set'])($key, $value);
    }

    /** Removes a key. */
    public function delete(string $key): void
    {
        if (self::$external === null) {
            Runtime::cache()->delete($key, self::GROUP);
            return;
        }
        (self::$external['delete'])($key);
    }
}
