<?php

declare(strict_types=1);

namespace Minn\Ops;

use Minn\Http;
use Minn\Http\Download;
use Minn\Support\Files;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use ZipArchive;

/**
 * Checks a Minn install's files against the release they claim to be, the
 * way `wp core verify-checksums` checks WordPress's against wordpress.org:
 * the update service describes the running version's release, its minn.zip
 * is downloaded from the service alone and must match its published sha256
 * and carry a signature from a key this engine trusts (EngineUpdate), and
 * then every file of the engine is compared with the archive's, as are the
 * shape files at the webroot with the archive's layout templates. A file
 * that differs, one the archive has and the install lacks, and one the
 * install has beyond the archive are all named. The install record
 * (.install.json) is the site's own and is not compared.
 */
final readonly class EngineChecksums
{
    /** Where a version's release record is asked for. */
    public const SOURCE = Directory::BASE . 'minn/releases/';

    private const MAX_DOWNLOAD = 64 * 1048576;

    private const OWN = ['.install.json'];

    /** @param list<string> $keys the release keys to trust (tests pass their own) */
    public function __construct(private string $engineDir, private array $keys = EngineUpdate::KEYS, private Http $http = new Http())
    {
    }

    /**
     * The comparison: the version checked, and the paths (under minn/, or at
     * the webroot for shape files) that differ, are missing, or are extra.
     *
     * @return array{version: string, changed: list<string>, missing: list<string>, extra: list<string>}
     */
    public function check(string $webroot): array
    {
        $version = EngineUpdate::versionOf($this->engineDir);
        $release = $this->release($version);
        $zip = (new Download($this->http))->https($release->package, self::MAX_DOWNLOAD, [Directory::ORIGIN], Directory::userAgent());
        (new EngineUpdate($this->engineDir, $this->keys, $this->http))->verify($zip, $release->sha256, $release->signature, $version, 'trusted');
        $released = self::archiveHashes($zip);
        $result = self::compare($released, self::treeHashes($this->engineDir), 'minn/');
        $layout = self::compare(self::layoutOf($released), self::shapeHashes(rtrim($webroot, '/'), array_keys(self::layoutOf($released))), '');
        return ['version' => $version, 'changed' => [...$result['changed'], ...$layout['changed']], 'missing' => [...$result['missing'], ...$layout['missing']], 'extra' => $result['extra']];
    }

    /** The release the service describes for a version; it must be one Minn offers, of that version. */
    private function release(string $version): Release
    {
        $reply = $this->http->get(self::SOURCE . rawurlencode($version), headers: ['Accept: application/json'], timeout: 10.0, hosts: [Directory::ORIGIN], maxBytes: 1048576);
        if ($reply->code === 404) {
            throw new RuntimeException("No Minn {$version} release is published to check this install against.");
        }
        if (!$reply->ok()) {
            throw new RuntimeException('The Minn update service did not answer, so the files could not be checked. Try again later.');
        }
        $answer = $reply->json();
        $release = is_array($answer) ? Release::fromArray($answer) : null;
        if ($release === null || $release->version !== $version) {
            throw new RuntimeException("The Minn update service did not describe Minn {$version} as a release it offers.");
        }
        return $release;
    }

    /**
     * The sha256 of every file in the archive's engine folder, by its path inside that folder.
     *
     * @return array<string, string>
     */
    private static function archiveHashes(string $zip): array
    {
        $file = tempnam(sys_get_temp_dir(), 'minn-check-');
        file_put_contents($file, $zip);
        $archive = new ZipArchive();
        $hashes = [];
        try {
            if ($archive->open($file) !== true) {
                throw new RuntimeException('The release archive could not be opened.');
            }
            for ($i = 0; $i < $archive->numFiles; $i++) {
                $name = (string) $archive->getNameIndex($i);
                if (!str_ends_with($name, '/') && str_starts_with($name, 'minn/')) {
                    $hashes[substr($name, 5)] = hash('sha256', (string) $archive->getFromIndex($i));
                }
            }
        } finally {
            $archive->close();
            @unlink($file);
        }
        return $hashes;
    }

    /**
     * The sha256 of every file under the engine folder, by its path inside it; the install record left out.
     *
     * @return array<string, string>
     */
    private static function treeHashes(string $dir): array
    {
        $hashes = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS | RecursiveDirectoryIterator::FOLLOW_SYMLINKS));
        foreach ($files as $file) {
            $path = substr($file->getPathname(), strlen($dir) + 1);
            if ($file->isFile() && !in_array($path, self::OWN, true)) {
                $hashes[str_replace('\\', '/', $path)] = (string) hash_file('sha256', $file->getPathname());
            }
        }
        return $hashes;
    }

    /**
     * The archive's layout templates by the webroot path each shapes.
     *
     * @param array<string, string> $released
     * @return array<string, string>
     */
    private static function layoutOf(array $released): array
    {
        $layout = [];
        foreach ($released as $path => $hash) {
            if (str_starts_with($path, 'layout/')) {
                $layout[substr($path, 7)] = $hash;
            }
        }
        return $layout;
    }

    /**
     * The sha256 of the webroot's shape files that exist.
     *
     * @param list<string> $paths
     * @return array<string, string>
     */
    private static function shapeHashes(string $webroot, array $paths): array
    {
        $hashes = [];
        foreach ($paths as $path) {
            if (is_file("{$webroot}/{$path}")) {
                $hashes[$path] = (string) hash_file('sha256', "{$webroot}/{$path}");
            }
        }
        return $hashes;
    }

    /**
     * Expected against found: the paths that differ, are missing, and are extra, prefixed.
     *
     * @param array<string, string> $expected
     * @param array<string, string> $found
     * @return array{changed: list<string>, missing: list<string>, extra: list<string>}
     */
    private static function compare(array $expected, array $found, string $prefix): array
    {
        $out = ['changed' => [], 'missing' => [], 'extra' => []];
        foreach ($expected as $path => $hash) {
            if (!isset($found[$path])) {
                $out['missing'][] = $prefix . $path;
            } elseif (!hash_equals($hash, $found[$path])) {
                $out['changed'][] = $prefix . $path;
            }
        }
        foreach (array_keys(array_diff_key($found, $expected)) as $path) {
            $out['extra'][] = $prefix . $path;
        }
        foreach ($out as $kind => $paths) {
            sort($paths);
            $out[$kind] = $paths;
        }
        return $out;
    }
}
