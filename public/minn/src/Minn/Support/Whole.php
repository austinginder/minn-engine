<?php

declare(strict_types=1);

namespace Minn\Support;

/**
 * Whole numbers read from outside: the reference's absint() turns the
 * smallest integer into a float (it has no whole-number opposite), which
 * a typed int, a LIMIT or a page number cannot hold. Here it reads as 0,
 * as asking for nothing (security sweep 2026-11-12, finding 3.7).
 */
final class Whole
{
    /** A value as a non-negative int: its integer reading without its sign, 0 for the smallest integer. */
    public static function abs(mixed $value): int
    {
        $number = is_scalar($value) || $value === null ? (int) $value : (int) (bool) $value;
        return $number === PHP_INT_MIN ? 0 : abs($number);
    }
}
