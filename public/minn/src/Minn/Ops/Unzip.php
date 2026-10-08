<?php

declare(strict_types=1);

namespace Minn\Ops;

use Closure;
use Minn\Runtime\Refusal;
use ZipArchive;

/**
 * An archive unpacked as unzip_file() unpacks it (probe unzip-file): into
 * the destination through the filesystem the site set up, every folder
 * made first (the destination and its missing parents included), resource-fork entries
 * (__MACOSX/) and entries whose names climb out or are absolute left out;
 * refused when the disk cannot take twice the unpacked size and a little
 * more. pre_unzip_file may answer first, and unzip_file is handed the
 * result, each with the folders to make and the space needed. An archive
 * the reader cannot open is refused in the words of the library the
 * reference falls back to.
 */
final readonly class Unzip
{
    /**
     * @param object $filesystem the site's WP_Filesystem (mkdir, is_dir, put_contents)
     * @param Closure(string, mixed...): mixed $filter applies a filter, as apply_filters does
     */
    public function __construct(private object $filesystem, private Closure $filter, private int $dirMode, private int $fileMode)
    {
    }

    /** Whether a file is a zip archive that opens and passes its consistency check (wp_zip_file_is_valid). */
    public static function valid(string $file): bool
    {
        $archive = new ZipArchive();
        if (!is_file($file) || $archive->open($file, ZipArchive::CHECKCONS) !== true) {
            return false;
        }
        $archive->close();
        return true;
    }

    /** The archive's files under the destination: true, a refusal, or what pre_unzip_file answered instead. */
    public function into(string $file, string $to): mixed
    {
        $to = rtrim($to, '/') . '/';
        $archive = new ZipArchive();
        $opened = is_file($file) ? $archive->open($file) : ZipArchive::ER_NOENT;
        if ($opened !== true) {
            return new Refusal('incompatible_archive', 'Incompatible Archive.', $opened === ZipArchive::ER_NOENT
                ? "PCLZIP_ERR_MISSING_FILE (-4) : Missing archive file '{$file}'"
                : 'PCLZIP_ERR_BAD_FORMAT (-10) : Unable to find End of Central Dir Record signature');
        }
        [$entries, $folders, $size] = self::read($archive);
        // The destination and whichever of its parents do not exist yet come first.
        $missing = [];
        for ($dir = rtrim($to, '/'); $dir !== '' && $dir !== '.' && $dir !== '/' && !$this->filesystem->is_dir($dir); $dir = dirname($dir)) {
            $missing[] = $dir;
        }
        $needed = array_values(array_unique([...$missing, ...array_map(static fn (string $folder) => $to . $folder, $folders)]));
        $required = $size * 2.1;
        $free = function_exists('disk_free_space') ? @disk_free_space(dirname(rtrim($to, '/'))) : false;
        if ($free !== false && $required > $free) {
            return new Refusal('disk_full_unzip_file', 'Could not copy files. You may have run out of disk space.', ['uncompressed_size' => $required, 'available_space' => $free]);
        }
        $pre = ($this->filter)('pre_unzip_file', null, $file, $to, $needed, $required);
        if ($pre !== null) {
            return $pre;
        }
        $result = $this->write($archive, $entries, $to, $needed);
        $archive->close();
        return ($this->filter)('unzip_file', $result, $file, $to, $needed, $required);
    }

    /**
     * The entries to write, the folders they need (each archive folder and
     * every parent of a file), and their unpacked size.
     *
     * @return array{0: array<int, string>, 1: list<string>, 2: int} index => name, folders, bytes
     */
    private static function read(ZipArchive $archive): array
    {
        $entries = [];
        $folders = [];
        $size = 0;
        for ($i = 0; $i < $archive->numFiles; $i++) {
            $stat = $archive->statIndex($i);
            $name = is_array($stat) ? (string) $stat['name'] : '';
            if ($name === '' || str_starts_with($name, '__MACOSX/') || !self::safe($name)) {
                continue;
            }
            $size += (int) $stat['size'];
            $entries[$i] = $name;
            $parts = explode('/', rtrim($name, '/'));
            $depth = str_ends_with($name, '/') ? count($parts) : count($parts) - 1;
            for ($at = 1; $at <= $depth; $at++) {
                $folders[] = implode('/', array_slice($parts, 0, $at));
            }
        }
        return [$entries, array_values(array_unique($folders)), $size];
    }

    /** Whether an entry's name stays inside the destination: no climbing segment, not absolute, no drive. */
    private static function safe(string $name): bool
    {
        return !str_starts_with($name, '/') && !str_contains($name, ':') && !in_array('..', explode('/', $name), true) && !in_array('.', array_slice(explode('/', $name), 0, -1), true);
    }

    /** @param array<int, string> $entries @param list<string> $needed */
    private function write(ZipArchive $archive, array $entries, string $to, array $needed): true|Refusal
    {
        $ordered = $needed;
        usort($ordered, static fn (string $a, string $b) => strlen($a) <=> strlen($b));
        foreach ($ordered as $folder) {
            if (!$this->filesystem->is_dir($folder) && !$this->filesystem->mkdir($folder, $this->dirMode)) {
                return new Refusal('mkdir_failed_ziparchive', 'Could not create directory.', $folder);
            }
        }
        foreach ($entries as $index => $name) {
            if (str_ends_with($name, '/')) {
                continue;
            }
            $contents = $archive->getFromIndex($index);
            if ($contents === false) {
                return new Refusal('stat_failed_ziparchive', 'Could not retrieve file from archive.', $name);
            }
            if (!$this->filesystem->put_contents($to . $name, $contents, $this->fileMode)) {
                return new Refusal('copy_failed_ziparchive', 'Could not copy file.', $name);
            }
        }
        return true;
    }
}
