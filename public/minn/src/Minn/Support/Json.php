<?php

declare(strict_types=1);

namespace Minn\Support;

/** Makes a value encodable: strings that are not valid UTF-8 get their high bytes replaced, recursively. */
final class Json
{
    public static function sanitize(mixed $value): mixed
    {
        if (is_string($value)) {
            return $value === '' || preg_match('/^./us', $value) === 1 ? $value : (string) preg_replace('/[\x80-\xff]/', '?', $value);
        }
        if (is_array($value)) {
            return array_map(self::sanitize(...), $value);
        }
        if (is_object($value)) {
            foreach (get_object_vars($value) as $key => $item) {
                $value->{$key} = self::sanitize($item);
            }
        }
        return $value;
    }
}
