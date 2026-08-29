<?php

declare(strict_types=1);

namespace Minn\Admin;

use Minn\Content\Site;
use Minn\Support\FileHeaders;
use Minn\Support\Serialized;

/**
 * The Minn Admin app on disk: the symlinked dev copy the engine serves the
 * shell and assets from. Minn Admin is MIT, so reading its files is fine.
 */
final readonly class App
{
    private const ASSET_TYPES = [
        'css' => 'text/css',
        'js' => 'application/javascript',
        'woff2' => 'font/woff2',
        'woff' => 'font/woff',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'json' => 'application/json',
    ];

    public function __construct(private string $dir)
    {
    }

    /**
     * Whether the site has switched Minn Admin off: its plugin folder is in
     * wp-content/plugins (the record the reference keeps) and active_plugins
     * does not name it. A site that carries only the engine's bundle has no
     * such record and keeps its admin.
     */
    public static function switchedOff(string $contentDir, Site $site): bool
    {
        if (!is_dir("{$contentDir}/plugins/minn-admin")) {
            return false;
        }
        return !in_array('minn-admin/minn-admin.php', Serialized::stringList($site->option('active_plugins')), true);
    }

    public function dir(): string
    {
        return $this->dir;
    }

    public function installed(): bool
    {
        return is_dir($this->dir);
    }

    public function version(): string
    {
        $main = "{$this->dir}/minn-admin.php";
        if ($this->installed() && is_readable($main)) {
            $head = (string) file_get_contents($main, false, null, 0, 8192);
            if (preg_match("/MINN_ADMIN_VERSION',\s*'([^']+)'/", $head, $m)) {
                return $m[1];
            }
            $fromHeader = FileHeaders::values($main, ['Version'])['Version'];
            if ($fromHeader !== '') {
                return $fromHeader;
            }
        }
        return '0.0.0';
    }

    /** The absolute path of a readable file inside the app, or null. */
    public function file(string $relative): ?string
    {
        if (!$this->installed()) {
            return null;
        }
        $full = realpath($this->dir . '/' . ltrim($relative, '/'));
        if ($full === false || !str_starts_with($full, realpath($this->dir) . '/') || !is_file($full) || !is_readable($full)) {
            return null;
        }
        return $full;
    }

    /** A self-busting asset version: app version plus file mtime. */
    public function assetVersion(string $relative): string
    {
        $mtime = $this->installed() ? @filemtime("{$this->dir}/{$relative}") : 0;
        return $this->version() . ($mtime ? '.' . $mtime : '');
    }

    /**
     * The absolute path and content type of an asset inside the app, or
     * null for anything missing or outside it.
     *
     * @return array{0: string, 1: string}|null
     */
    public function asset(string $relative): ?array
    {
        if (!$this->installed()) {
            return null;
        }
        $full = realpath($this->dir . '/' . ltrim($relative, '/'));
        if ($full === false || !str_starts_with($full, realpath($this->dir) . '/') || !is_file($full)) {
            return null;
        }
        $ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
        return [$full, self::ASSET_TYPES[$ext] ?? 'application/octet-stream'];
    }
}
