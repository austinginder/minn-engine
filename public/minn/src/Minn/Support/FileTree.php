<?php

declare(strict_types=1);

namespace Minn\Support;

/**
 * Whole-directory reads and copies. The engine's own installer, the update
 * unpacker and the plugins that reach for WordPress's copy_dir all want the
 * same two operations: list a tree, and duplicate one.
 */
final class FileTree
{
    /**
     * Every file under a directory, and every directory itself with a trailing
     * slash, to the given depth. Depth 0 means no limit; depth 1 stops at the
     * directory's own entries and names its subdirectories without descending.
     *
     * @param list<string> $skip names to leave out at every level
     * @return list<string>|false false when the directory cannot be read
     */
    public static function files(string $dir, int $depth = 0, array $skip = [], int $level = 1): array|false
    {
        $entries = @scandir($dir);
        if ($entries === false) {
            return false;
        }
        $dir = rtrim($dir, '/') . '/';
        $out = [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || in_array($entry, $skip, true)) {
                continue;
            }
            if (!is_dir($dir . $entry)) {
                $out[] = $dir . $entry;
                continue;
            }
            if ($depth > 0 && $level >= $depth) {
                $out[] = $dir . $entry . '/';
                continue;
            }
            $nested = self::files($dir . $entry, $depth, $skip, $level + 1);
            if ($nested !== false) {
                $out = array_merge($out, $nested);
            }
        }
        return $out;
    }

    /**
     * Copies a directory's contents into another, creating it if it is not
     * there. Names in $skip are left behind at the top level only, which is
     * how the update unpacker keeps a directory it means to preserve.
     *
     * @param list<string> $skip
     */
    public static function copy(string $from, string $to, array $skip = []): bool
    {
        $entries = @scandir($from);
        if ($entries === false) {
            return false;
        }
        if (!is_dir($to) && !@mkdir($to, 0755, true) && !is_dir($to)) {
            return false;
        }
        $from = rtrim($from, '/') . '/';
        $to = rtrim($to, '/') . '/';
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || in_array($entry, $skip, true)) {
                continue;
            }
            $ok = is_dir($from . $entry)
                ? self::copy($from . $entry, $to . $entry)
                : @copy($from . $entry, $to . $entry);
            if (!$ok) {
                return false;
            }
        }
        return true;
    }
}
