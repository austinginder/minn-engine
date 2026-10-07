<?php

declare(strict_types=1);

namespace Minn\Support;

/**
 * Recursive filesystem work behind WP_Filesystem_Direct: best-effort tree
 * delete and chmod (suppressed errors, keep-going semantics, the AND of
 * every step as the result), and the symbolic-to-octal permission string
 * conversion. The facade class keeps the reference's argument handling and
 * maps each operation here.
 */
final class Files
{
    /** drwxr-xr-x (or any rwx string) to its octal digits, the reference's tallying shape. */
    public static function octalFromSymbolic(string $mode): string
    {
        $kept = (string) preg_replace('/[^rwx-]/', '', $mode);
        $mode = str_pad($kept, 10, '-', STR_PAD_LEFT);
        $mode = strtr($mode, ['-' => '0', 'r' => '4', 'w' => '2', 'x' => '1']);
        return $mode[0]
            . (string) ((int) $mode[1] + (int) $mode[2] + (int) $mode[3])
            . (string) ((int) $mode[4] + (int) $mode[5] + (int) $mode[6])
            . (string) ((int) $mode[7] + (int) $mode[8] + (int) $mode[9]);
    }

    /**
     * Delete a directory tree, files first, best effort; true only when
     * everything went. A symlinked directory is unlinked, never followed:
     * the dev sites symlink plugin folders into the tree, and a delete that
     * reached through one would empty the real source.
     */
    public static function deleteTree(string $path): bool
    {
        // A link or a file named directly is removed itself: a trailing slash on a link would walk into its target.
        $named = rtrim($path, '/');
        if (is_link($named) || is_file($named)) {
            return @unlink($named);
        }
        $path = $named . '/';
        $ok = true;
        $entries = @scandir($path);
        foreach (is_array($entries) ? $entries : [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $target = $path . $entry;
            if (is_dir($target) && !is_link($target)) {
                $ok = self::deleteTree($target) && $ok;
            } elseif (!@unlink($target)) {
                $ok = false;
            }
        }
        if (file_exists($path) && !@rmdir($path)) {
            $ok = false;
        }
        return $ok;
    }

    /** Apply a mode to every FILE under a directory (the directories themselves keep theirs); always reports true. */
    public static function chmodTree(string $path, int $mode): bool
    {
        $path = rtrim($path, '/') . '/';
        $entries = @scandir($path);
        foreach (is_array($entries) ? $entries : [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $target = $path . $entry;
            if (is_dir($target) && !is_link($target)) {
                self::chmodTree($target, $mode);
            } else {
                @chmod($target, $mode);
            }
        }
        return true;
    }
}
