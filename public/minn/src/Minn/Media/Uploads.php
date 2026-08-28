<?php

declare(strict_types=1);

namespace Minn\Media;

use Minn\Content\Site;
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
        'svg' => 'image/svg+xml',
        'pdf' => 'application/pdf',
        'txt' => 'text/plain',
        'mp4' => 'video/mp4',
        'mp3' => 'audio/mpeg',
        'zip' => 'application/zip',
    ];

    public function __construct(
        private Site $site,
        private Permalinks $permalinks,
        private string $baseDir,
    ) {
    }

    public function baseDir(): string
    {
        return $this->baseDir;
    }

    public function baseUrl(): string
    {
        return $this->permalinks->url('/wp-content/uploads');
    }

    public function urlFor(string $relativePath): string
    {
        return $this->baseUrl() . '/' . $relativePath;
    }

    public function pathFor(string $relativePath): string
    {
        return $this->baseDir . '/' . $relativePath;
    }

    /** A safe, lower-cased file name. */
    public static function sanitizeName(string $filename): string
    {
        return strtolower(preg_replace('/[^A-Za-z0-9._-]+/', '-', basename($filename)));
    }

    /**
     * Lands content in the dated directory under a unique name and returns
     * the relative path ("2026/08/name.png").
     *
     * @param string|null $movedFrom a temporary upload to move; null writes $raw
     */
    public function store(string $filename, ?string $movedFrom, ?string $raw): string
    {
        $subdir = gmdate('Y/m', time() + $this->site->gmtOffset());
        $dir = $this->baseDir . '/' . $subdir;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $stem = preg_replace('/\.[^.]+$/', '', $filename);
        $try = $filename;
        $n = 0;
        while (file_exists("{$dir}/{$try}")) {
            $n++;
            $try = "{$stem}-{$n}.{$ext}";
        }
        $path = "{$dir}/{$try}";
        if ($movedFrom !== null) {
            rename($movedFrom, $path);
        } else {
            file_put_contents($path, (string) $raw);
        }
        return "{$subdir}/{$try}";
    }

    /** Removes the original and every generated size. */
    public function remove(string $relativePath, array $sizes): void
    {
        $dir = dirname($this->pathFor($relativePath));
        foreach ($sizes as $size) {
            @unlink($dir . '/' . $size['file']);
        }
        @unlink($this->pathFor($relativePath));
    }
}
