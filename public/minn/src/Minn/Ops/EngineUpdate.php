<?php

declare(strict_types=1);

namespace Minn\Ops;

use Minn\Http\Download;
use Minn\Support\Files;
use RuntimeException;

/**
 * Replaces the running engine with a published release. The release's
 * minn.zip comes from the engine repository's own GitHub release downloads
 * and nowhere else (every redirect hop is judged), must match the sha256
 * GitHub publishes for it, and is unpacked beside the engine into a folder
 * of its own, where it is checked again: its bootstrap names the version on
 * offer and bin/minn is there. Then the
 * swap, two renames in the folder the engine lives in: the running engine
 * moves aside, the new one moves in, and the old copy is removed; when the
 * second rename fails the first is undone. The legacy install record
 * (.install.json, from installs that kept it inside the engine) comes
 * along. One update runs at a time. A development checkout (a symbolic
 * link, or a folder under git) is refused: git owns those. The site keeps
 * answering throughout.
 */
final readonly class EngineUpdate
{
    /** Where a release asset may come from: the release page's link and the hosts it redirects to. */
    private const HOSTS = ['https://github.com/', 'https://objects.githubusercontent.com/', 'https://release-assets.githubusercontent.com/'];

    private const MAX_DOWNLOAD = 64 * 1048576;

    public function __construct(private string $engineDir)
    {
    }

    /** Downloads, checks and installs a release; the version now in place. */
    public function apply(Release $release): string
    {
        $this->refuseCheckout();
        if ($release->sha256 === '') {
            throw new RuntimeException("GitHub publishes no checksum for Minn {$release->version}'s minn.zip, so it is not installed.");
        }
        if (!str_starts_with($release->package, Releases::DOWNLOADS)) {
            throw new RuntimeException("Minn {$release->version}'s minn.zip is not one of Minn's own GitHub releases, so it is not installed.");
        }
        $zip = Download::https($release->package, self::MAX_DOWNLOAD, self::HOSTS, 'Minn Engine/' . self::versionOf($this->engineDir));
        return $this->install($zip, $release->sha256, $release->version);
    }

    /** Installs an archive in hand once it matches its checksum and holds the version named; the version now in place. */
    public function install(string $zip, string $sha256, string $version): string
    {
        $this->refuseCheckout();
        if (!hash_equals(strtolower($sha256), hash('sha256', $zip))) {
            throw new RuntimeException('The minn.zip downloaded does not match the checksum its release publishes, so it is not installed.');
        }
        $parent = dirname($this->engineDir);
        if (!is_writable($parent)) {
            throw new RuntimeException("Minn cannot write to {$parent}, the folder it lives in. Update it from the command line instead: php " . basename($this->engineDir) . '/bin/minn update');
        }
        $lock = fopen(sys_get_temp_dir() . '/minn-update-' . md5($this->engineDir) . '.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            throw new RuntimeException('Another update of this Minn is running; nothing changed.');
        }
        try {
            return $this->swap($zip, $version, $parent);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** Unpacks, checks and swaps in the archive, one update at a time (install() holds the lock). */
    private function swap(string $zip, string $version, string $parent): string
    {
        $suffix = bin2hex(random_bytes(6));
        $file = "{$parent}/.minn-incoming-{$suffix}.zip";
        $stage = "{$parent}/.minn-incoming-{$suffix}";
        $aside = "{$parent}/.minn-outgoing-{$suffix}";
        try {
            file_put_contents($file, $zip);
            $tree = Archive::unpackFolder($file, $stage);
            if (!is_file("{$tree}/bootstrap.php") || !is_file("{$tree}/bin/minn")) {
                throw new RuntimeException('The archive is not a Minn engine: it has no bootstrap.php and bin/minn at the top of its folder.');
            }
            $found = self::versionOf($tree);
            if ($found !== $version) {
                throw new RuntimeException("The archive holds Minn {$found}, not the {$version} its release names.");
            }
            if (is_file("{$this->engineDir}/.install.json")) {
                copy("{$this->engineDir}/.install.json", "{$tree}/.install.json");
            }
            if (!rename($this->engineDir, $aside)) {
                throw new RuntimeException('The running engine could not be moved aside, so nothing changed.');
            }
            if (!rename($tree, $this->engineDir)) {
                rename($aside, $this->engineDir);
                throw new RuntimeException('The new engine could not be moved into place; the running one was put back.');
            }
        } finally {
            @unlink($file);
            Files::deleteTree($stage);
        }
        Files::deleteTree($aside);
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
        return $version;
    }

    /** The version a bootstrap.php names; "0.0.0" when it names none. */
    public static function versionOf(string $engineDir): string
    {
        $bootstrap = (string) @file_get_contents("{$engineDir}/bootstrap.php");
        return preg_match("/MINN_ENGINE_VERSION', '([^']+)'/", $bootstrap, $m) === 1 ? $m[1] : '0.0.0';
    }

    /** A link, or a folder inside a git working copy at any depth (the engine's own repository keeps it two levels down). */
    private function refuseCheckout(): void
    {
        $checkout = is_link($this->engineDir);
        for ($dir = $this->engineDir; !$checkout && dirname($dir) !== $dir; $dir = dirname($dir)) {
            $checkout = file_exists("{$dir}/.git");
        }
        if ($checkout) {
            throw new RuntimeException('This Minn is a development checkout (a link, or a folder under git), so it is updated with git, not from a release.');
        }
    }
}
