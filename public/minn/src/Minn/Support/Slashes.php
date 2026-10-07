<?php

declare(strict_types=1);

namespace Minn\Support;

/**
 * Magic-quote slashes the way WordPress keeps them (wp_slash, wp_unslash):
 * added to or stripped from every string inside a value, arrays and
 * objects walked all the way down, anything else left as it is.
 */
final class Slashes
{
    /** Every string in a value with its quotes and backslashes slashed. */
    public static function add(mixed $value): mixed
    {
        return self::deep($value, static fn (string $text): string => addslashes($text));
    }

    /** Every string in a value with one level of slashes taken off. */
    public static function strip(mixed $value): mixed
    {
        return self::deep($value, static fn (string $text): string => stripslashes($text));
    }

    /** @param \Closure(string): string $change */
    private static function deep(mixed $value, \Closure $change): mixed
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = self::deep($item, $change);
            }
            return $value;
        }
        if (is_object($value)) {
            foreach (get_object_vars($value) as $key => $item) {
                $value->{$key} = self::deep($item, $change);
            }
            return $value;
        }
        return is_string($value) ? $change($value) : $value;
    }
}
