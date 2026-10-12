<?php

declare(strict_types=1);

namespace Minn;

use Exception;
use JsonException;
use stdClass;

/**
 * JSON in and out, for the engine and for code written for Minn:
 *
 *     use Minn\Json;
 *
 *     Json::encode($rows);              // {"url":"https://example.com/a","name":"Café"}
 *     Json::decode($text);              // objects as arrays; JsonException when it is not JSON
 *     Json::decode($stored, or: []);    // the fallback, for text that may be empty or broken
 *     Json::file($path);                // the same, read from a file; the error names the file
 *
 * encode() writes slashes and characters as themselves unless flags say
 * otherwise (Json::ESCAPED is the form WordPress writes, which REST bodies
 * keep). A string cut mid-character still encodes: each invalid byte becomes
 * "?", as wp_json_encode() does it (probe functions-utils). What cannot be
 * encoded even then (NAN, nesting past the depth) throws a JsonException.
 */
final class Json
{
    /** Slashes and non-ASCII characters written as themselves: encode()'s default. */
    public const READABLE = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    /** Slashes and non-ASCII characters escaped, as wp_json_encode() and the REST API write them. */
    public const ESCAPED = 0;

    /**
     * A value as JSON; invalid UTF-8 is repaired, never a reason to fail.
     *
     * @param int $flags json_encode() flags; JSON_THROW_ON_ERROR here skips the repair, as it does for wp_json_encode()
     * @throws JsonException when the value cannot be encoded even after the repair
     */
    public static function encode(mixed $value, int $flags = self::READABLE, int $depth = 512): string
    {
        $json = json_encode($value, $flags, $depth);
        if ($json !== false) {
            return $json;
        }
        try {
            $clean = self::repair($value, $depth);
        } catch (Exception $e) {
            throw new JsonException($e->getMessage(), JSON_ERROR_DEPTH, $e);
        }
        $json = json_encode($clean, $flags, $depth);
        if ($json === false) {
            throw new JsonException(json_last_error_msg(), json_last_error());
        }
        return $json;
    }

    /**
     * The value the text holds, objects as arrays; with `or:` given, that comes back instead of an exception.
     *
     * @throws JsonException when the text is not JSON and no fallback was given
     */
    public static function decode(string $text, mixed $or = null): mixed
    {
        try {
            return json_decode($text, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            if (func_num_args() > 1) {
                return $or;
            }
            throw $e;
        }
    }

    /**
     * The value a JSON file holds, objects as arrays; with `or:` given, a missing or broken file gives that instead.
     *
     * @throws JsonException naming the file when it cannot be read or is not JSON and no fallback was given
     */
    public static function file(string $path, mixed $or = null): mixed
    {
        $text = is_file($path) && is_readable($path) ? file_get_contents($path) : false;
        if ($text === false) {
            if (func_num_args() > 1) {
                return $or;
            }
            throw new JsonException("{$path} could not be read.");
        }
        try {
            return json_decode($text, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            if (func_num_args() > 1) {
                return $or;
            }
            throw new JsonException("{$path} is not JSON: {$e->getMessage()}", $e->getCode(), $e);
        }
    }

    /**
     * A value with every string and key made valid UTF-8, each invalid byte becoming "?", objects read as plain objects of their public properties.
     *
     * @throws Exception "Reached depth limit" when arrays and objects nest deeper than $depth, as the reference's sanity check throws it
     */
    public static function repair(mixed $value, int $depth = 512): mixed
    {
        if ($depth < 0) {
            throw new Exception('Reached depth limit');
        }
        if (is_array($value)) {
            $clean = [];
            foreach ($value as $key => $item) {
                $clean[is_string($key) ? self::text($key) : $key] = self::member($item, $depth);
            }
            return $clean;
        }
        if (is_object($value)) {
            $clean = new stdClass();
            foreach (get_object_vars($value) as $key => $item) {
                $clean->{self::text((string) $key)} = self::member($item, $depth);
            }
            return $clean;
        }
        return is_string($value) ? self::text($value) : $value;
    }

    /** One member of an array or object: a nested one goes a level deeper, a string is repaired. */
    private static function member(mixed $item, int $depth): mixed
    {
        if (is_array($item) || is_object($item)) {
            return self::repair($item, $depth - 1);
        }
        return is_string($item) ? self::text($item) : $item;
    }

    /** A string made valid UTF-8: each invalid byte becomes "?", the valid characters stay. */
    private static function text(string $text): string
    {
        return $text === '' || preg_match('//u', $text) === 1 ? $text : (string) mb_convert_encoding($text, 'UTF-8', 'UTF-8');
    }

    /**
     * JSON as arrays, but an empty object stays an object: an in-process
     * REST answer keeps `{}` where the reference's data holds an empty
     * object (a global style's emptied settings), which decoding to arrays
     * would turn into `[]`.
     */
    public static function decodeKeepingEmptyObjects(string $json): mixed
    {
        $decoded = json_decode($json);
        $arrays = static function (mixed $value) use (&$arrays): mixed {
            if ($value instanceof \stdClass) {
                $properties = get_object_vars($value);
                return $properties === [] ? $value : array_map($arrays, $properties);
            }
            return is_array($value) ? array_map($arrays, $value) : $value;
        };
        return $arrays($decoded);
    }
}