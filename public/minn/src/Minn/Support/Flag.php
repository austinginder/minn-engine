<?php

declare(strict_types=1);

namespace Minn\Support;

/** A yes or no as WordPress reads one from loose input (wp_validate_boolean). */
final class Flag
{
    /** The string "false" in any case is no; anything else is PHP's truth. */
    public static function of(mixed $value): bool
    {
        if (is_string($value) && strtolower($value) === 'false') {
            return false;
        }
        return (bool) $value;
    }
}
