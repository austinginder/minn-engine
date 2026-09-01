<?php

declare(strict_types=1);

namespace Minn\Support;

/** Walks a directory the way the filesystem API lists it: named entries, dot entries skipped, hidden ones optional, recursion optional. */
final class DirectoryListing
{
    /**
     * A directory's entries described one by one, or false when unreadable.
     *
     * @param callable(string): array<string, mixed> $describe the entry's own fields for an absolute path
     * @return array<string, array<string, mixed>>|false false when the path is not a readable directory
     */
    public static function read(string $path, bool $includeHidden, bool $recursive, ?string $onlyName, callable $describe): array|false
    {
        if (!is_dir($path) || !is_readable($path)) {
            return false;
        }
        $handle = dir($path);
        if (!$handle) {
            return false;
        }
        $path = rtrim($path, '/\\') . '/';
        $entries = [];
        while (($name = $handle->read()) !== false) {
            if ($name === '.' || $name === '..' || (!$includeHidden && $name[0] === '.') || ($onlyName !== null && $name !== $onlyName)) {
                continue;
            }
            $entry = ['name' => $name] + $describe($path . $name);
            $entry['type'] = is_dir($path . $name) ? 'd' : 'f';
            if ($entry['type'] === 'd') {
                $entry['files'] = $recursive ? (self::read($path . $name, $includeHidden, true, null, $describe) ?: []) : [];
            }
            $entries[$name] = $entry;
        }
        $handle->close();
        return $entries;
    }
}
