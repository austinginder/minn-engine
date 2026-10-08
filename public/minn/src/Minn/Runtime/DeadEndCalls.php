<?php

declare(strict_types=1);

namespace Minn\Runtime;

/**
 * What a dead-end class (the Customizer, an admin screen) answers for any
 * method it does not define: the call is logged (PlaceholderTrace) and comes
 * back null, so a plugin or theme calling one does not fatal. The class's
 * own methods still do what they do.
 */
trait DeadEndCalls
{
    /** An instance method the class does not define, logged and answered with null. @param list<mixed> $arguments */
    public function __call(string $name, array $arguments): mixed
    {
        PlaceholderTrace::hit(static::class . '::' . $name);
        return null;
    }

    /** A static method the class does not define, logged and answered with null. @param list<mixed> $arguments */
    public static function __callStatic(string $name, array $arguments): mixed
    {
        PlaceholderTrace::hit(static::class . '::' . $name);
        return null;
    }
}
