<?php

declare(strict_types=1);

namespace Minn\Cli;

use Minn\Ops\Packages;
use Minn\RestError;
use WP_CLI;

/**
 * Puts a theme or a plugin on disk for `wp theme install` and `wp plugin
 * install`: a wordpress.org slug, a local zip, or a zip URL, saying along
 * the way what WP-CLI says. The two kinds differ only in their words, in
 * where they live, and in a plugin being allowed to be a single file.
 */
final readonly class PackageInstaller
{
    private function __construct(
        private Packages $packages,
        private string $kind,
        private string $invalidSlug,
        private string $notFound,
    ) {
    }

    /** The installer `wp theme install` uses. */
    public static function themes(Packages $packages): self
    {
        return new self($packages, 'theme', '%s: Invalid slug provided', '%s: Theme not found');
    }

    /** The installer `wp plugin install` uses. */
    public static function plugins(Packages $packages): self
    {
        return new self($packages, 'plugin', '%s: Invalid plugin slug.', '%s: Plugin not found.');
    }

    /**
     * One source onto disk: the folder when it is there afterwards (put
     * there now, or already there), null when it could not be installed,
     * and whether this call put it there.
     *
     * @return array{0: ?string, 1: bool}
     */
    public function install(string $source, bool $force, string $version): array
    {
        if (preg_match('#^https?://#i', $source)) {
            return $this->archive($source, $force);
        }
        if (is_file($source) || str_ends_with(strtolower($source), '.zip')) {
            return is_file($source) ? $this->archive($source, $force) : $this->refuse($source, sprintf($this->invalidSlug, $source));
        }
        if (!preg_match('/^[a-z0-9-]+$/', $source)) {
            return $this->refuse($source, sprintf($this->invalidSlug, $source));
        }
        $dest = rtrim(ABSPATH, '/') . "/wp-content/{$this->kind}s/{$source}";
        if ($this->present($dest) && !$force) {
            WP_CLI::warning("{$source}: " . ucfirst($this->kind) . ' already installed.');
            return [$source, false];
        }
        try {
            $info = $this->kind === 'theme' ? $this->packages->directoryTheme($source) : $this->packages->directoryPlugin($source);
        } catch (RestError) {
            $info = null;
        }
        if ($info === null) {
            return $this->refuse($source, sprintf($this->notFound, $source));
        }
        return $this->fromDirectory($source, $info, $force, $version, is_dir($dest));
    }

    /**
     * A wordpress.org release onto disk, replacing an installed copy when forced.
     *
     * @param array<string, mixed> $info the directory's answer for the slug
     * @return array{0: ?string, 1: bool}
     */
    private function fromDirectory(string $slug, array $info, bool $force, string $version, bool $existed): array
    {
        $label = ucfirst($this->kind);
        $name = html_entity_decode(strip_tags((string) ($info['name'] ?? $slug)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $link = $version !== ''
            ? "https://downloads.wordpress.org/{$this->kind}/{$slug}.{$version}.zip"
            : (string) ($info['download_link'] ?? '');
        WP_CLI::log("Installing {$name} (" . ($version !== '' ? $version : (string) ($info['version'] ?? '')) . ')');
        WP_CLI::log("Downloading installation package from {$link}...");
        try {
            WP_CLI::log('Unpacking the package...');
            WP_CLI::log("Installing the {$this->kind}...");
            if ($existed) {
                WP_CLI::log("Removing the old version of the {$this->kind}...");
            }
            $folder = match (true) {
                $this->kind === 'theme' && $force => $this->packages->replaceTheme($slug, $version),
                $this->kind === 'theme' => $this->packages->installTheme($slug, $version),
                $force => $this->packages->replacePlugin($slug, $version),
                default => $this->packages->installPlugin($slug, $version),
            };
        } catch (RestError $error) {
            return $this->refuse($slug, $slug . ': ' . $error->getMessage());
        }
        WP_CLI::log($existed ? "{$label} updated successfully." : "{$label} installed successfully.");
        return [$folder, true];
    }

    /**
     * A zip from a URL or a local path, unpacked into place.
     *
     * @return array{0: ?string, 1: bool}
     */
    private function archive(string $source, bool $force): array
    {
        $label = ucfirst($this->kind);
        try {
            if (preg_match('#^https?://#i', $source)) {
                WP_CLI::log("Downloading installation package from {$source}...");
                $bytes = $this->packages->fetch(preg_replace('#^http://#i', 'https://', $source) ?? $source);
            } else {
                $bytes = (string) file_get_contents($source);
            }
            WP_CLI::log('Unpacking the package...');
            WP_CLI::log("Installing the {$this->kind}...");
            $result = $force ? $this->packages->unpackReplacing($bytes, $this->kind) : $this->packages->unpack($bytes, $this->kind);
        } catch (RestError $error) {
            if ($error->status === 409) {
                $folder = basename((string) ($error->extra['destination'] ?? ''));
                WP_CLI::warning(($folder !== '' ? $folder : $source) . ": {$label} already installed.");
                return [$folder !== '' ? $folder : null, false];
            }
            return $this->refuse($source, $source . ': ' . $error->getMessage());
        }
        WP_CLI::log("{$label} installed successfully.");
        return [$result['folder'], true];
    }

    /** Whether something of this kind is already at the path (a plugin may be a single file beside it). */
    private function present(string $dest): bool
    {
        return is_dir($dest) || ($this->kind === 'plugin' && is_file($dest . '.php'));
    }

    /**
     * The two warnings WP-CLI gives for a source it could not install.
     *
     * @return array{0: null, 1: false}
     */
    private function refuse(string $source, string $reason): array
    {
        WP_CLI::warning($reason);
        WP_CLI::warning("The '{$source}' {$this->kind} could not be found.");
        return [null, false];
    }
}
