<?php

declare(strict_types=1);

namespace Minn\Support;

use Exception;
use stdClass;

/**
 * Makes a value encodable the way the JSON encoder's fallback does it
 * (probe functions-utils): every string and every key made valid UTF-8,
 * each invalid byte becoming "?" while the valid characters stay, objects
 * read as plain objects of their public properties, no deeper than the
 * depth asked for.
 */
final class Json
{
    /** A string made valid UTF-8: each invalid byte becomes "?", the valid characters stay. */
    public static function convert(string $text): string
    {
        return $text === '' || preg_match('//u', $text) === 1 ? $text : (string) mb_convert_encoding($text, 'UTF-8', 'UTF-8');
    }

    /**
     * A value with every string and key made valid UTF-8, ready to encode.
     *
     * @throws Exception "Reached depth limit" when arrays and objects nest deeper than $depth
     */
    public static function sanitize(mixed $value, int $depth = 512): mixed
    {
        if ($depth < 0) {
            throw new Exception('Reached depth limit');
        }
        if (is_array($value)) {
            $clean = [];
            foreach ($value as $key => $item) {
                $clean[is_string($key) ? self::convert($key) : $key] = self::item($item, $depth);
            }
            return $clean;
        }
        if (is_object($value)) {
            $clean = new stdClass();
            foreach (get_object_vars($value) as $key => $item) {
                $clean->{self::convert((string) $key)} = self::item($item, $depth);
            }
            return $clean;
        }
        return is_string($value) ? self::convert($value) : $value;
    }

    /** One member of an array or object: a nested one goes a level deeper, a string is converted. */
    private static function item(mixed $item, int $depth): mixed
    {
        if (is_array($item) || is_object($item)) {
            return self::sanitize($item, $depth - 1);
        }
        return is_string($item) ? self::convert($item) : $item;
    }
}
