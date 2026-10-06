<?php

declare(strict_types=1);

namespace Minn\Admin;

use Minn\Auth\Capabilities;
use Minn\Content\Site;
use Minn\Support\Serialized;

/**
 * How much the uploads folder holds, as Minn Admin 0.43 works it out and
 * shares it through the minn_admin_uploads_size transient: the cached
 * figure in either shape it has had ({bytes, partial}, or a bare count from
 * before the budget), else a walk only an administrator may start, one at a
 * time behind a two-minute lock, stopped after four seconds and kept as
 * partial for an hour instead of twelve.
 */
final readonly class UploadsSize
{
    private const CACHE = '_transient_minn_admin_uploads_size';
    private const EXPIRES = '_transient_timeout_minn_admin_uploads_size';
    private const LOCK = 'minn_admin_uploads_size_lock';
    private const BUDGET = 4.0;

    public function __construct(private Site $site, private Capabilities $capabilities, private string $dir)
    {
    }

    /** The Media card's "N used" line, "over N used" for a walk cut short, or "" when the size is not known. */
    public function label(int $userId): string
    {
        $size = $this->measure($userId);
        if ($size === null) {
            return '';
        }
        return ($size['partial'] ? 'over ' : '') . Format::size($size['bytes']) . ' used';
    }

    /**
     * The uploads size, cached or walked now.
     *
     * @return array{bytes: int, partial: bool}|null null when nothing is known and this caller may not find out
     */
    public function measure(int $userId): ?array
    {
        $cached = $this->cached();
        if ($cached !== null) {
            return $cached;
        }
        if (!$this->capabilities->can($userId, 'manage_options') || !$this->lock()) {
            return null;
        }
        $out = $this->walk();
        $this->site->setOption(self::EXPIRES, (string) (time() + ($out['partial'] ? 1 : 12) * 3600));
        $this->site->setOption(self::CACHE, Serialized::encode($out));
        $this->site->deleteOption(self::LOCK);
        return $out;
    }

    /** @return array{bytes: int, partial: bool}|null */
    private function cached(): ?array
    {
        $raw = $this->site->option(self::CACHE);
        $expires = $this->site->option(self::EXPIRES);
        if ($raw === null || ($expires !== null && (int) $expires < time())) {
            return null;
        }
        $value = Serialized::decode($raw);
        if (is_array($value) && isset($value['bytes'])) {
            return ['bytes' => (int) $value['bytes'], 'partial' => (bool) ($value['partial'] ?? false)];
        }
        return ['bytes' => (int) $value, 'partial' => false];
    }

    /** Whether this request may walk: no other has started one in the last two minutes. */
    private function lock(): bool
    {
        $held = $this->site->option(self::LOCK);
        if ($held !== null && (int) $held > time() - 120) {
            return false;
        }
        $this->site->setOption(self::LOCK, (string) time());
        return true;
    }

    /** @return array{bytes: int, partial: bool} */
    private function walk(): array
    {
        $bytes = 0;
        $partial = false;
        $budget = microtime(true) + self::BUDGET;
        if (!is_dir($this->dir)) {
            return ['bytes' => 0, 'partial' => false];
        }
        try {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::LEAVES_ONLY, \RecursiveIteratorIterator::CATCH_GET_CHILD);
            $n = 0;
            foreach ($files as $file) {
                $bytes += (int) $file->getSize();
                if (++$n % 200 === 0 && microtime(true) > $budget) {
                    $partial = true;
                    break;
                }
            }
        } catch (\Throwable) {
            $partial = true;
        }
        return ['bytes' => $bytes, 'partial' => $partial];
    }
}
