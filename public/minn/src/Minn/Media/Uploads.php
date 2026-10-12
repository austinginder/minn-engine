<?php

declare(strict_types=1);

namespace Minn\Media;

use Minn\Front\Permalinks;

/** The uploads directory: paths, URLs, the allowed types, and landing a file. */
final readonly class Uploads
{
    /** extension => canonical mime */
    public const MIMES = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'pdf' => 'application/pdf',
        'txt' => 'text/plain',
        'mp4' => 'video/mp4',
        'mp3' => 'audio/mpeg',
        'zip' => 'application/zip',
    ];

    public function __construct(
        private Permalinks $permalinks,
        private string $baseDir,
    ) {
    }

    /** The uploads directory on disk. */
    public function baseDir(): string
    {
        return $this->baseDir;
    }

    /** The uploads directory's URL. */
    public function baseUrl(): string
    {
        return $this->permalinks->url('/wp-content/uploads');
    }

    /** The URL of a file by its relative path. */
    public function urlFor(string $relativePath): string
    {
        return $this->baseUrl() . '/' . $relativePath;
    }

    /** The path of a file by its relative path. */
    public function pathFor(string $relativePath): string
    {
        return $this->baseDir . '/' . $relativePath;
    }

    /** A safe, lower-cased file name. */
    /**
     * A safe file name: one extension. Every dot-separated part between the
     * base and the last extension that is not itself an allowed extension
     * is joined with an underscore, so "shell.php.png" lands as
     * "shell_php.png" and no handler can be talked into running it.
     */
    public static function sanitizeName(string $filename): string
    {
        $name = strtolower((string) preg_replace('/[^A-Za-z0-9._-]+/', '-', basename($filename)));
        $parts = explode('.', $name);
        if (count($parts) <= 2) {
            return $name;
        }
        $extension = array_pop($parts);
        $base = array_shift($parts);
        foreach ($parts as $part) {
            $base .= (isset(self::MIMES[$part]) ? '.' : '_') . $part;
        }
        return $base . '.' . $extension;
    }

    /** A stored file's path relative to the uploads folder, or null for a file outside it. */
    public function relativeOf(string $absolutePath): ?string
    {
        $base = rtrim($this->baseDir, '/') . '/';
        return str_starts_with($absolutePath, $base) ? substr($absolutePath, strlen($base)) : null;
    }

    /**
     * Every file an attachment owns, in deletion order: the legacy thumb, each
     * sub-size, the original image, the backup sizes, then the file itself.
     *
     * @param array<string, mixed> $meta the attachment metadata
     * @param array<string, array<string, mixed>>|null $backupSizes
     * @return list<string> absolute paths, unique, only the main file checked for existence
     */
    public static function attachmentFiles(string $file, array $meta, ?array $backupSizes): array
    {
        $directory = dirname($file);
        $sibling = static fn (string $name): string => str_starts_with($name, '/') ? $name : $directory . '/' . $name;
        $paths = [];
        if (!empty($meta['thumb'])) {
            $paths[] = str_replace(basename($file), (string) $meta['thumb'], $file);
        }
        foreach ((array) ($meta['sizes'] ?? []) as $size) {
            if (is_array($size) && isset($size['file'])) {
                $paths[] = $sibling((string) $size['file']);
            }
        }
        if (!empty($meta['original_image'])) {
            $paths[] = $sibling((string) $meta['original_image']);
        }
        foreach ((array) $backupSizes as $size) {
            if (is_array($size) && isset($size['file'])) {
                $paths[] = $sibling((string) $size['file']);
            }
        }
        if ($file !== '') {
            $paths[] = $file;
        }
        return $paths;
    }

    /**
     * Where uploads live, from the site's options: the default folder under
     * wp-content, a relative upload_path under ABSPATH, or an absolute one;
     * the URL likewise, with year/month subfolders when the site asks.
     *
     * @return array{path: string, url: string, subdir: string, basedir: string, baseurl: string, error: false}
     */
    public static function layout(string $uploadPath, string $uploadUrlPath, string $siteUrl, string $contentDir, string $contentUrl, string $abspath, bool $yearMonth, string $time): array
    {
        $uploadPath = trim($uploadPath);
        if ($uploadPath === '' || $uploadPath === 'wp-content/uploads') {
            $dir = $contentDir . '/uploads';
        } elseif (!str_starts_with($uploadPath, $abspath)) {
            $dir = rtrim($abspath, '/') . '/' . $uploadPath;
        } else {
            $dir = $uploadPath;
        }
        $url = $uploadUrlPath;
        if ($url === '') {
            $url = $uploadPath === '' || $uploadPath === 'wp-content/uploads' || $uploadPath === $dir ? $contentUrl . '/uploads' : rtrim($siteUrl, '/') . '/' . $uploadPath;
        }
        $subdir = $yearMonth ? '/' . substr($time, 0, 4) . '/' . substr($time, 5, 2) : '';
        return ['path' => $dir . $subdir, 'url' => $url . $subdir, 'subdir' => $subdir, 'basedir' => $dir, 'baseurl' => $url, 'error' => false];
    }
}
