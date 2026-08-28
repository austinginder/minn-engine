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

    /** The integer values of a serialized list such as sticky_posts. */
    public static function intList(?string $blob): array
    {
        if ($blob === null || $blob === '' || !preg_match_all('/i:\d+;i:(\d+);/', $blob, $m)) {
            return [];
        }
        return array_map(intval(...), $m[1]);
    }
}
