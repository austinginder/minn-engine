<?php

declare(strict_types=1);

namespace Minn\Ops;

use Minn\Json;

/**
 * The files an install lays at the webroot for tooling to find: the shape
 * files from layout/ (index.php, wp-settings.php, version.php and the rest),
 * a shape file under each of WordPress's request entry points
 * (data/entry-files.json: wp-cron.php, wp-admin/admin-ajax.php, ...), so a
 * host that runs only PHP files present on disk (nginx) still reaches the
 * engine, and one file for each of the reference's wp-includes and
 * wp-admin/includes files, an empty placeholder or, where layout/ ships one,
 * the engine's loader. An engine update lays them again from the new engine:
 * a file still as the replaced engine wrote it is replaced, a missing one is
 * written, and one the site changed is left alone.
 */
final class ShapeFiles
{
    /** The shape files copied from layout/ as they are. */
    public const LAYOUT = ['index.php', 'wp-login.php', 'wp-settings.php', 'wp-cli.yml', 'wp-includes/version.php', 'wp-admin/index.php'];

    /**
     * An install: every layout file copied, and each entry point and each of
     * the reference's files the site lacks written (an empty placeholder found
     * there becomes the loader layout/ ships for it).
     *
     * @return array{layout: int, entries: int, placeholders: int} how many were written
     */
    public static function lay(string $engineDir, string $root): array
    {
        $layout = 0;
        foreach (self::LAYOUT as $file) {
            $layout += (int) self::write("{$root}/{$file}", (string) file_get_contents("{$engineDir}/layout/{$file}"));
        }
        return [
            'layout' => $layout,
            'entries' => self::layMissing(self::entryFiles($engineDir), $root),
            'placeholders' => self::layMissing(self::referenceFiles($engineDir), $root),
        ];
    }

    /**
     * An update, once the new engine is in place: each file the new engine
     * lays that is missing, or still as the replaced engine ($previous, its
     * folder) laid it, is written from the new one.
     */
    public static function refresh(string $engineDir, string $root, string $previous): int
    {
        if (!self::boots($root)) {
            return 0;
        }
        $before = self::wanted($previous);
        $written = 0;
        foreach (self::wanted($engineDir) as $file => $want) {
            $path = "{$root}/{$file}";
            $earlier = array_values(array_filter([self::placeholder($file), $before[$file] ?? null], 'is_string'));
            if (!file_exists($path) || self::stale($path, $want, $earlier)) {
                $written += (int) self::write($path, $want);
            }
        }
        return $written;
    }

    /**
     * The entry points at the webroot itself (not under wp-admin/, a tree eject
     * removes whole) whose file is still the one this engine lays: what eject
     * deletes before WordPress's own come back from the park.
     *
     * @return list<string>
     */
    public static function laidEntries(string $engineDir, string $root): array
    {
        $laid = [];
        foreach (self::entryFiles($engineDir) as $file => $want) {
            if (!str_contains($file, '/') && is_file("{$root}/{$file}") && file_get_contents("{$root}/{$file}") === $want) {
                $laid[] = $file;
            }
        }
        return $laid;
    }

    /** The empty placeholder for one of the reference's files. */
    public static function placeholder(string $file): string
    {
        return "<?php\n// Minn Engine placeholder for the reference's {$file}: the engine provides these symbols itself, so a plugin that requires this file gets nothing and continues.\n";
    }

    /** Whether the webroot boots the engine: its wp-settings.php requires minn/bootstrap.php. */
    private static function boots(string $root): bool
    {
        return is_file("{$root}/wp-settings.php") && str_contains((string) file_get_contents("{$root}/wp-settings.php"), 'minn/bootstrap.php');
    }

    /** @return array<string, string> every file the engine lays, by path from the webroot */
    private static function wanted(string $engineDir): array
    {
        $files = [];
        foreach (self::LAYOUT as $file) {
            if (is_file("{$engineDir}/layout/{$file}")) {
                $files[$file] = (string) file_get_contents("{$engineDir}/layout/{$file}");
            }
        }
        return $files + self::entryFiles($engineDir) + self::referenceFiles($engineDir);
    }

    /**
     * @return array<string, string> each entry point with its shape file: wp-admin's
     * index under wp-admin/, layout/entry.php at the webroot (none from an engine
     * that ships no entry list)
     */
    private static function entryFiles(string $engineDir): array
    {
        $list = Json::file("{$engineDir}/data/entry-files.json", or: []);
        $templates = [
            'wp-admin' => (string) @file_get_contents("{$engineDir}/layout/wp-admin/index.php"),
            '.' => (string) @file_get_contents("{$engineDir}/layout/entry.php"),
        ];
        $files = [];
        foreach (is_array($list) ? $list : [] as $file) {
            $template = is_string($file) && !str_contains($file, '..') ? ($templates[dirname($file)] ?? '') : '';
            if ($template !== '') {
                $files[$file] = $template;
            }
        }
        return $files;
    }

    /** @param array<string, string> $files each written where the site has nothing, or the stock placeholder; returns how many */
    private static function layMissing(array $files, string $root): int
    {
        $written = 0;
        foreach ($files as $file => $want) {
            $path = "{$root}/{$file}";
            if (!file_exists($path) || self::stale($path, $want, [self::placeholder($file)])) {
                $written += (int) self::write($path, $want);
            }
        }
        return $written;
    }

    /** @return array<string, string> the reference's files, each with the placeholder or loader the engine lays for it */
    private static function referenceFiles(string $engineDir): array
    {
        $list = Json::file("{$engineDir}/data/reference-files.json", or: []);
        $files = [];
        foreach (is_array($list) ? $list : [] as $file) {
            if (!is_string($file) || $file === '' || str_contains($file, '..')) {
                continue;
            }
            $loader = "{$engineDir}/layout/{$file}";
            $files[$file] = is_file($loader) ? (string) file_get_contents($loader) : self::placeholder($file);
        }
        return $files;
    }

    /** @param list<string> $earlier what an engine wrote there before */
    private static function stale(string $path, string $want, array $earlier): bool
    {
        $have = (string) @file_get_contents($path);
        return $have !== $want && in_array($have, $earlier, true);
    }

    private static function write(string $path, string $contents): bool
    {
        @mkdir(dirname($path), 0755, true);
        return @file_put_contents($path, $contents) !== false;
    }
}
