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
     * Every file under a directory, as list_files gives them: names starting
     * with a dot left out, the names in $skip left out at the top level only,
     * an empty folder named with a trailing slash. Depth 0 means no limit;
     * at the depth given a folder is named with its slash instead of entered.
     *
     * @param list<string> $skip
     * @return list<string>|false false when the directory cannot be read
     */
    public static function files(string $dir, int $depth = 0, array $skip = []): array|false
    {
        return self::walk($dir, $depth, $skip, '.', 1);
    }

    /**
     * As files(), dot files and folders included.
     *
     * @param list<string> $skip
     * @return list<string>|false
     */
    public static function filesWithHidden(string $dir, int $depth = 0, array $skip = []): array|false
    {
        return self::walk($dir, $depth, $skip, null, 1);
    }

    /**
     * @param list<string> $skip
     * @return list<string>|false
     */
    private static function walk(string $dir, int $depth, array $skip, ?string $hiddenPrefix, int $level): array|false
    {
        $entries = @scandir($dir);
        if ($entries === false) {
            return false;
        }
        $dir = rtrim($dir, '/') . '/';
        $out = [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || ($hiddenPrefix !== null && str_starts_with($entry, $hiddenPrefix)) || ($level === 1 && in_array($entry, $skip, true))) {
                continue;
            }
            if (!is_dir($dir . $entry)) {
                $out[] = $dir . $entry;
                continue;
            }
            $nested = $depth > 0 && $level >= $depth ? [] : self::walk($dir . $entry, $depth, $skip, $hiddenPrefix, $level + 1);
            if ($nested !== false) {
                $out = $nested === [] ? [...$out, $dir . $entry . '/'] : array_merge($out, $nested);
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

    /**
     * A directory copied through a WordPress filesystem object, as copy_dir
     * copies it: the source listed, the destination made when it is missing,
     * each file copied (once more after a chmod when the first copy fails),
     * each missing folder made and copied in turn. A name in $skip is left
     * out at its own level, "folder/name" one level down. Null when it all
     * copied, else the refusal's code and data.
     *
     * @param list<string> $skip
     * @return array{0: string, 1: string}|null
     */
    public static function copyThrough(object $fs, string $from, string $to, array $skip, int $fileMode, int $dirMode): ?array
    {
        $listing = $fs->dirlist($from);
        if (!is_array($listing)) {
            return ['dirlist_failed_copy_dir', basename($from)];
        }
        [$from, $to] = [rtrim($from, '/') . '/', rtrim($to, '/') . '/'];
        if (!$fs->exists($to) && !$fs->mkdir($to)) {
            return ['mkdir_destination_failed_copy_dir', basename($to)];
        }
        foreach ($listing as $name => $entry) {
            if (in_array((string) $name, $skip, true)) {
                continue;
            }
            if (($entry['type'] ?? '') === 'f') {
                if (!$fs->copy($from . $name, $to . $name, true, $fileMode) && !($fs->chmod($to . $name, $fileMode) && $fs->copy($from . $name, $to . $name, true, $fileMode))) {
                    return ['copy_failed_copy_dir', $to . $name];
                }
                continue;
            }
            if (!$fs->is_dir($to . $name) && !$fs->mkdir($to . $name, $dirMode)) {
                return ['mkdir_failed_copy_dir', $to . $name];
            }
            $inner = array_values(array_map(static fn (string $item): string => substr($item, strlen($name . '/')), array_filter($skip, static fn (string $item): bool => str_starts_with($item, $name . '/'))));
            $refused = self::copyThrough($fs, $from . $name, $to . $name, $inner, $fileMode, $dirMode);
            if ($refused !== null) {
                return $refused;
            }
        }
        return null;
    }

    /**
     * The files of a recursive filesystem listing (dirlist), each as a path
     * under $dir, in listing order, folders' files where the folder stands.
     *
     * @return list<string>
     */
    public static function listedFiles(mixed $listing, string $dir): array
    {
        $out = [];
        foreach (is_array($listing) ? $listing : [] as $name => $entry) {
            $path = rtrim($dir, '/') . '/' . $name;
            $out = ($entry['type'] ?? '') === 'f' ? [...$out, $path] : [...$out, ...self::listedFiles($entry['files'] ?? [], $path)];
        }
        return $out;
    }
}
