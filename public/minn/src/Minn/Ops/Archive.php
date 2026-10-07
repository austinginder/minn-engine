<?php

declare(strict_types=1);

namespace Minn\Ops;

use Minn\RestError;

/**
 * A zip unpacked the safe way, for the plugin and theme installer and for
 * the engine's own update alike: every entry stays inside its folder (no
 * absolute path, no "..", no backslash, no NUL), none is a symbolic link,
 * there are no more than 20,000 of them, they unpack to no more than
 * 512 MB, and they all sit in exactly one top folder (macOS's __MACOSX
 * noise aside). Nothing is written until every entry has passed.
 */
final class Archive
{
    public const MAX_BYTES = 512 * 1048576;
    private const MAX_ENTRIES = 20000;

    /** Unpacks a zip file into a new folder $stage; the path of the one folder the archive held. */
    public static function unpackFolder(string $file, string $stage): string
    {
        $archive = new \ZipArchive();
        if ($archive->open($file) !== true) {
            throw new RestError('not_zip', 'The archive could not be opened.', 400);
        }
        try {
            if ($archive->numFiles > self::MAX_ENTRIES) {
                throw new RestError('bad_archive', 'The archive holds more than ' . self::MAX_ENTRIES . ' entries.', 400);
            }
            $top = null;
            $bytes = 0;
            for ($i = 0; $i < $archive->numFiles; $i++) {
                $name = (string) $archive->getNameIndex($i);
                if (str_starts_with($name, '__MACOSX/')) {
                    continue;
                }
                if ($name === '' || str_starts_with($name, '/') || str_contains($name, '..') || str_contains($name, "\0") || str_contains($name, '\\')) {
                    throw new RestError('bad_archive', 'The archive holds a path that leaves its folder.', 400);
                }
                if (self::isSymlinkEntry($archive, $i)) {
                    throw new RestError('bad_archive', 'The archive holds a symbolic link.', 400);
                }
                $bytes += (int) (($archive->statIndex($i) ?: [])['size'] ?? 0);
                if ($bytes > self::MAX_BYTES) {
                    throw new RestError('bad_archive', 'The archive unpacks to more than ' . (int) (self::MAX_BYTES / 1048576) . ' MB.', 400);
                }
                $first = explode('/', $name, 2)[0];
                if ($top !== null && $first !== $top) {
                    throw new RestError('bad_archive', 'The archive must hold exactly one folder.', 400);
                }
                $top = $first;
            }
            if ($top === null || !self::isFolderName($top)) {
                throw new RestError('bad_archive', 'The archive must hold exactly one folder.', 400);
            }
            mkdir($stage, 0755, true);
            if (!$archive->extractTo($stage)) {
                throw new RestError('extract_failed', 'The archive could not be unpacked.', 500);
            }
            return "{$stage}/{$top}";
        } finally {
            $archive->close();
        }
    }

    /** A plain folder name: no separators, never "." or "..". */
    public static function isFolderName(string $name): bool
    {
        return $name !== '.' && $name !== '..' && preg_match('/^[A-Za-z0-9._-]+$/', $name) === 1;
    }

    /** Whether a zip entry is recorded as a symbolic link (a Unix entry whose mode says so). */
    private static function isSymlinkEntry(\ZipArchive $archive, int $index): bool
    {
        $opsys = 0;
        $attributes = 0;
        if (!$archive->getExternalAttributesIndex($index, $opsys, $attributes)) {
            return false;
        }
        return $opsys === \ZipArchive::OPSYS_UNIX && (($attributes >> 16) & 0170000) === 0120000;
    }
}
