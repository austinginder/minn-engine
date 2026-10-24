<?php

declare(strict_types=1);

namespace Minn\Theme;

use Minn\Json;

/**
 * The theme files a request reads, each read once: a theme's JSON documents
 * (theme.json and the partials under styles/), the JSON files under a
 * folder, and a patterns folder's files by slug. Several parts of a page
 * build a Theme over the same folders (the block renderer, global styles,
 * the template index, the facade's theme.json data), and each read every
 * file again; under open_basedir, which checks each path a file call names,
 * those reads were the larger part of a page's time. A process answers one
 * request, so the next request reads what is on disk then.
 */
final class ThemeFiles
{
    /** @var array<string, mixed> */
    private static array $documents = [];

    /** @var array<string, list<string>> */
    private static array $folders = [];

    /** @var array<string, array<string, string>> */
    private static array $patterns = [];

    /** A JSON file's contents, null when it is missing or not JSON. */
    public static function json(string $file): mixed
    {
        if (!array_key_exists($file, self::$documents)) {
            self::$documents[$file] = Json::file($file, or: null);
        }
        return self::$documents[$file];
    }

    /**
     * Every JSON file under a folder and its subfolders, in path order; none
     * when the folder is not there.
     *
     * @return list<string>
     */
    public static function jsonUnder(string $dir): array
    {
        if (!isset(self::$folders[$dir])) {
            $files = [];
            if (is_dir($dir)) {
                foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $file) {
                    if ($file->getExtension() === 'json') {
                        $files[] = $file->getPathname();
                    }
                }
                sort($files);
            }
            self::$folders[$dir] = $files;
        }
        return self::$folders[$dir];
    }

    /**
     * The PHP files directly in a patterns folder, by the slug each one's
     * header declares (a file without one is left out).
     *
     * @return array<string, string>
     */
    public static function patterns(string $dir): array
    {
        if (!isset(self::$patterns[$dir])) {
            $patterns = [];
            foreach (glob("{$dir}/*.php") ?: [] as $file) {
                $head = (string) file_get_contents($file, false, null, 0, 2000);
                if (preg_match('/^\s*\*\s*Slug:\s*(\S+)/m', $head, $m)) {
                    $patterns[$m[1]] = $file;
                }
            }
            self::$patterns[$dir] = $patterns;
        }
        return self::$patterns[$dir];
    }
}
