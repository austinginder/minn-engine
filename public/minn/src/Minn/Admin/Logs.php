<?php

declare(strict_types=1);

namespace Minn\Admin;

use Minn\RestError;

/**
 * The log files the System view can read and clear: the debug log the
 * engine's failure handler writes, and PHP's own error log when it is a
 * separate file inside the site. Anything outside the site is named but
 * never read; it may be another tenant's.
 */
final readonly class Logs
{
    private const TAIL_BYTES = 262144;

    public function __construct(private string $webroot)
    {
    }

    /** @return array<string, array{label: string, group: string, path: string}> */
    public function sources(): array
    {
        $sources = ['debug' => ['label' => 'Debug log', 'group' => 'Minn Engine', 'path' => $this->debugLogPath()]];
        $ini = (string) ini_get('error_log');
        if ($ini !== '' && $ini !== 'syslog' && str_starts_with($ini, '/') && $ini !== $sources['debug']['path'] && is_file($ini) && $this->owned($ini)) {
            $sources['php-error'] = ['label' => 'PHP error log', 'group' => 'Server', 'path' => $ini];
        }
        return $sources;
    }

    /** @return list<array> the sources that exist, debug first */
    public function listPayload(): array
    {
        $out = [];
        foreach ($this->sources() as $id => $source) {
            if (!is_file($source['path'])) {
                continue;
            }
            $size = (int) filesize($source['path']);
            $out[] = [
                'id' => $id,
                'label' => $source['label'],
                'group' => $source['group'],
                'size' => $size,
                'size_human' => self::human($size),
                'modified' => gmdate('c', (int) filemtime($source['path'])),
                'clearable' => is_writable($source['path']),
            ];
        }
        return $out;
    }

    public function read(string $id): array
    {
        $source = $this->sources()[$id] ?? null;
        if ($source === null) {
            throw new RestError('unknown_log', 'Unknown log source.', 404);
        }
        return $this->tail($source['path']) + [
            'id' => $id,
            'label' => $source['label'],
            'group' => $source['group'],
            'clearable' => is_writable($source['path']),
        ];
    }

    public function clear(string $id): void
    {
        $source = $this->sources()[$id] ?? null;
        if ($source === null) {
            throw new RestError('unknown_log', 'Unknown log source.', 404);
        }
        $path = $source['path'];
        if (!$this->owned($path)) {
            throw new RestError('not_clearable', 'That log lives outside this site, so Minn will not clear it.', 400);
        }
        if (!is_file($path)) {
            return;
        }
        if (!is_writable($path) || @file_put_contents($path, '') === false) {
            throw new RestError('not_writable', 'The log is not writable.', 400);
        }
    }

    public function debugLogPath(): string
    {
        $configured = defined('WP_DEBUG_LOG') && is_string(WP_DEBUG_LOG) ? WP_DEBUG_LOG : '';
        return $configured !== '' ? $configured : "{$this->webroot}/wp-content/debug.log";
    }

    /** The last TAIL_BYTES of a file, the partial first line dropped. */
    public function tail(string $path): array
    {
        $owned = $this->owned($path);
        $relative = $owned ? ltrim(str_replace($this->webroot, '', $path), '/') : basename($path);
        if (!$owned) {
            return ['exists' => false, 'path' => $relative, 'content' => '', 'size' => 0, 'note' => 'This log lives outside the site, so Minn does not read it.'];
        }
        if (!is_file($path)) {
            return ['exists' => false, 'path' => $relative, 'content' => '', 'size' => 0];
        }
        $size = (int) filesize($path);
        $truncated = $size > self::TAIL_BYTES;
        $content = '';
        $handle = @fopen($path, 'rb');
        if ($handle !== false) {
            if ($truncated) {
                fseek($handle, -self::TAIL_BYTES, SEEK_END);
            }
            $content = (string) stream_get_contents($handle);
            fclose($handle);
            if ($truncated) {
                $newline = strpos($content, "\n");
                $content = $newline === false ? $content : substr($content, $newline + 1);
            }
        }
        return [
            'exists' => true,
            'path' => $relative,
            'size' => $size,
            'size_human' => self::human($size),
            'truncated' => $truncated,
            'content' => $content,
            'writable' => is_writable($path),
        ];
    }

    /** True when the path resolves inside the webroot. */
    public function owned(string $path): bool
    {
        $real = realpath($path) ?: realpath(dirname($path));
        $root = realpath($this->webroot);
        return $real !== false && $root !== false && str_starts_with($real, rtrim($root, '/') . '/');
    }

    public static function human(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 1) . ' GB';
        }
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' B';
    }
}
