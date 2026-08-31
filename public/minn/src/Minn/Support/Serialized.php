<?php

declare(strict_types=1);

namespace Minn\Support;

/**
 * Tolerant readers for the serialized-PHP blobs WordPress stores. Nothing
 * here executes the blob; each reader scans for the one shape it needs.
 */
final class Serialized
{
    /** The string values of a serialized string list. */
    public static function stringList(?string $blob): array
    {
        if ($blob === null || !str_starts_with($blob, 'a:')) {
            return [];
        }
        preg_match_all('/;s:\d+:"([^"]*)";/', $blob, $m);
        return $m[1];
    }

    /** A flat string list in the stored form. */
    public static function serializeStringList(array $values): string
    {
        $out = 'a:' . count($values) . ':{';
        $index = 0;
        foreach ($values as $value) {
            $out .= 'i:' . $index++ . ';s:' . strlen($value) . ':"' . $value . '";';
        }
        return $out . '}';
    }

    /** The first "field";value pair inside a blob: string, int, or float value as a string. */
    public static function field(?string $blob, string $field): ?string
    {
        if ($blob === null) {
            return null;
        }
        $quoted = preg_quote($field, '/');
        if (preg_match('/s:' . strlen($field) . ':"' . $quoted . '";(?:s:\d+:"([^"]*)"|i:(-?\d+)|d:([0-9.Ee+-]+))/', $blob, $m)) {
            return ($m[1] ?? '') !== '' ? $m[1] : ($m[2] ?? $m[3] ?? '');
        }
        return null;
    }

    /** Returned by decode() when the blob is not a serialized value the reader accepts. */
    public const INVALID = "\0minn:invalid\0";

    /**
     * A serialized scalar or array as PHP data, parsed by this reader:
     * strings, integers, floats, booleans, null, and arrays of those.
     * Objects are refused (nothing here instantiates anything), as is any
     * blob with trailing bytes or a malformed shape, with INVALID.
     */
    public static function decode(string $blob): mixed
    {
        if ($blob === '' || !preg_match('/^[sidbNaO]:|^N;/', $blob)) {
            return self::INVALID;
        }
        $offset = 0;
        try {
            $value = self::read($blob, $offset);
        } catch (\ValueError) {
            return self::INVALID;
        }
        return $offset === strlen($blob) ? $value : self::INVALID;
    }

    private static function read(string $blob, int &$offset): mixed
    {
        $type = $blob[$offset] ?? '';
        $offset++;
        switch ($type) {
            case 'N':
                self::expect($blob, $offset, ';');
                return null;
            case 'b':
                self::expect($blob, $offset, ':');
                $digit = self::until($blob, $offset, ';');
                return $digit === '1';
            case 'i':
                self::expect($blob, $offset, ':');
                return (int) self::until($blob, $offset, ';');
            case 'd':
                self::expect($blob, $offset, ':');
                return (float) self::until($blob, $offset, ';');
            case 's':
                self::expect($blob, $offset, ':');
                $length = (int) self::until($blob, $offset, ':');
                self::expect($blob, $offset, '"');
                $string = substr($blob, $offset, $length);
                $offset += $length;
                self::expect($blob, $offset, '"');
                self::expect($blob, $offset, ';');
                return $string;
            case 'O':
                // An object becomes a plain stdClass carrying its properties; no
                // class is ever instantiated, which is what keeps this safe.
                self::expect($blob, $offset, ':');
                $nameLength = (int) self::until($blob, $offset, ':');
                self::expect($blob, $offset, '"');
                $offset += $nameLength;
                self::expect($blob, $offset, '"');
                self::expect($blob, $offset, ':');
                $count = (int) self::until($blob, $offset, ':');
                self::expect($blob, $offset, '{');
                $object = new \stdClass();
                for ($i = 0; $i < $count; $i++) {
                    $key = self::read($blob, $offset);
                    if (!is_string($key)) {
                        throw new \ValueError('key');
                    }
                    if (str_starts_with($key, "\0")) {
                        $key = (string) substr($key, (int) strrpos($key, "\0") + 1);
                    }
                    $object->{$key} = self::read($blob, $offset);
                }
                self::expect($blob, $offset, '}');
                return $object;
            case 'a':
                self::expect($blob, $offset, ':');
                $count = (int) self::until($blob, $offset, ':');
                self::expect($blob, $offset, '{');
                $array = [];
                for ($i = 0; $i < $count; $i++) {
                    $key = self::read($blob, $offset);
                    if (!is_int($key) && !is_string($key)) {
                        throw new \ValueError('key');
                    }
                    $array[$key] = self::read($blob, $offset);
                }
                self::expect($blob, $offset, '}');
                return $array;
            default:
                throw new \ValueError('type');
        }
    }

    private static function expect(string $blob, int &$offset, string $char): void
    {
        if (($blob[$offset] ?? '') !== $char) {
            throw new \ValueError('shape');
        }
        $offset++;
    }

    private static function until(string $blob, int &$offset, string $char): string
    {
        $end = strpos($blob, $char, $offset);
        if ($end === false) {
            throw new \ValueError('shape');
        }
        $value = substr($blob, $offset, $end - $offset);
        $offset = $end + 1;
        return $value;
    }

    /**
     * PHP's serialize() for the values decode() accepts: null, bool, int,
     * float, string, and arrays of those. Objects are refused.
     */
    public static function encode(mixed $value): string
    {
        if ($value === null) {
            return 'N;';
        }
        if (is_bool($value)) {
            return $value ? 'b:1;' : 'b:0;';
        }
        if (is_int($value)) {
            return 'i:' . $value . ';';
        }
        if (is_float($value)) {
            return 'd:' . $value . ';';
        }
        if (is_string($value)) {
            return 's:' . strlen($value) . ':"' . $value . '";';
        }
        if (is_array($value)) {
            $body = '';
            foreach ($value as $key => $item) {
                $body .= self::encode(is_int($key) ? $key : (string) $key) . self::encode($item);
            }
            return 'a:' . count($value) . ':{' . $body . '}';
        }
        if (is_object($value)) {
            // Plugin code stores objects (maybe_serialize's contract); WRITING
            // them with PHP's own serializer runs nothing and matches the
            // reference byte for byte. Only the reverse direction is the
            // hazard, and reads stay on the tolerant decoders.
            return serialize($value);
        }
        throw new \ValueError('encode');
    }

    /** The integer values of a serialized list such as sticky_posts. */
    public static function intList(?string $blob): array
    {
        if ($blob === null || $blob === '' || !preg_match_all('/i:\d+;i:(\d+);/', $blob, $m)) {
            return [];
        }
        return array_map(intval(...), $m[1]);
    }
}
