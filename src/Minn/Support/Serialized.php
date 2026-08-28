<?php

declare(strict_types=1);

namespace Minn\Support;

/**
 * Tolerant readers for the serialized-PHP blobs WordPress stores. Nothing
 * here executes the blob; each reader scans for the one shape it needs.
 */
final class Serialized
{
    /** The integer values of a serialized list such as sticky_posts. */
    public static function intList(?string $blob): array
    {
        if ($blob === null || $blob === '' || !preg_match_all('/i:\d+;i:(\d+);/', $blob, $m)) {
            return [];
        }
        return array_map(intval(...), $m[1]);
    }
}
