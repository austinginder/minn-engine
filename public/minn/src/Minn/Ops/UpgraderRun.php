<?php

declare(strict_types=1);

namespace Minn\Ops;

use Minn\RestError;
use Minn\Runtime\Runtime;

/**
 * The engine's own installs and updates, run through the reference's
 * upgraders (Plugin_Upgrader, Theme_Upgrader) so plugins hear every hook a
 * WordPress install or update tells them: the REST plugin route, Minn
 * Admin's install, upload and update routes, the CLI verbs and the
 * automatic updates all come here. The Minn update service's packages are
 * fetched over the engine's own client (Packages::fetch: https, the
 * service's host, a size cap) and handed to the upgrader as a file through
 * upgrader_pre_download, so where Minn gets code is never the
 * plugin-filterable HTTP API's to change. A package from anywhere else is
 * installed only when its publisher answered upgrader_pre_download with a
 * copy it checked (or refused it): nobody's word, no install. A package
 * from the service passes Archive's checks before the upgrader unpacks it;
 * a local file (an upload, checked by Packages) is the upgrader's to read. The upgrader's refusals come back
 * as the engine's REST errors, in the words its routes already use.
 */
final readonly class UpgraderRun
{
    public function __construct(private Packages $packages)
    {
    }

    /**
     * Installs a package (or, with overwrite_package, replaces the folder it
     * lands on): its folder and the sha256 of a package fetched from the
     * service, '' otherwise.
     *
     * @param array<string, mixed> $args the upgrader's install arguments
     * @return array{folder: string, sha256: string}
     */
    public function install(string $kind, string $package, array $args = []): array
    {
        $skin = new \WP_Ajax_Upgrader_Skin();
        $upgrader = $kind === 'theme' ? new \Theme_Upgrader($skin) : new \Plugin_Upgrader($skin);
        $sha256 = '';
        $answer = $this->guarded($package, $sha256, static fn () => $upgrader->install($package, $args));
        if ($answer !== true) {
            throw $this->refusal($kind, $skin, $answer, $upgrader);
        }
        return ['folder' => (string) ($upgrader->result['destination_name'] ?? ''), 'sha256' => $sha256];
    }

    /**
     * Applies the offer the update transient holds for one plugin (file) or
     * theme (folder), as Minn Admin does on WordPress: a plugin through
     * bulk_upgrade, a theme through upgrade. The sha256 of the package, ''
     * when it did not come from the service.
     */
    public function update(string $kind, string $item, string $package): string
    {
        $skin = new \WP_Ajax_Upgrader_Skin();
        $upgrader = $kind === 'theme' ? new \Theme_Upgrader($skin) : new \Plugin_Upgrader($skin);
        $sha256 = '';
        $answer = $this->guarded($package, $sha256, static fn () => $kind === 'theme' ? $upgrader->upgrade($item) : ($upgrader->bulk_upgrade([$item])[$item] ?? false));
        if ($answer === false || $answer === null || $answer instanceof \WP_Error) {
            throw $this->refusal($kind, $skin, $answer, $upgrader);
        }
        return $sha256;
    }

    /**
     * Runs $call with the engine's say over the download: the service's
     * package fetched here, any other remote one only on its publisher's
     * answer. $sha256 is set when the service's package was fetched.
     */
    private function guarded(string $package, string &$sha256, \Closure $call): mixed
    {
        $fetch = function (mixed $reply, mixed $asked) use ($package, &$sha256): mixed {
            if ($reply !== false || $asked !== $package || !preg_match('#^https?://#i', $package)) {
                return $reply;
            }
            if (!str_starts_with($package, Directory::PACKAGES)) {
                return new \WP_Error('update_failed', "The package is not from the Minn update service and its publisher did not verify the download.");
            }
            $zip = $this->packages->fetch($package, Directory::ORIGIN);
            $file = (string) tempnam(sys_get_temp_dir(), 'minn-pkg-');
            file_put_contents($file, $zip);
            try {
                Archive::inspect($file);
            } catch (RestError $refused) {
                @unlink($file);
                return new \WP_Error($refused->errorCode, $refused->getMessage());
            }
            $sha256 = hash('sha256', $zip);
            return $file;
        };
        $hooks = Runtime::hooks();
        $hooks->add('upgrader_pre_download', $fetch, PHP_INT_MAX, 2);
        try {
            return $call();
        } finally {
            $hooks->remove('upgrader_pre_download', $fetch, PHP_INT_MAX);
        }
    }

    /** The upgrader's refusal in the engine's words: a taken folder says what is there and what came. */
    private function refusal(string $kind, \WP_Ajax_Upgrader_Skin $skin, mixed $answer, \WP_Upgrader $upgrader): RestError
    {
        $error = $answer instanceof \WP_Error ? $answer : ($skin->result instanceof \WP_Error ? $skin->result : $skin->get_errors());
        $code = $error instanceof \WP_Error ? (string) $error->get_error_code() : '';
        $data = $error instanceof \WP_Error ? $error->get_error_data($code) : null;
        $message = $error instanceof \WP_Error && $code !== '' ? (string) $error->get_error_message($code) . (is_string($data) && $data !== '' && !str_starts_with($data, '/') ? ' ' . strip_tags($data) : '') : 'The package could not be installed.';
        return match (true) {
            $code === 'folder_exists' => $this->taken($kind, (string) $data, $upgrader),
            $code === 'incompatible_archive_no_plugins' => new RestError('not_plugin', 'The archive is not a plugin: no minn.json and no file with a Plugin Name header in its folder.', 400),
            str_starts_with($code, 'incompatible_archive_theme') => new RestError('not_theme', $code === 'incompatible_archive_theme_no_index' ? $message : 'The archive is not a theme: no style.css with a Theme Name.', 400),
            $code === 'incompatible_archive' => new RestError('not_zip', 'The archive could not be opened.', 400),
            str_starts_with($code, 'incompatible_') => new RestError($code, $message, 400),
            $code === 'download_failed', $code === 'update_failed' => new RestError('update_failed', $message, 502),
            in_array($code, ['not_zip', 'bad_archive'], true) => new RestError($code, $message, 400),
            $code === '' => new RestError('install_failed', $message, 500),
            default => new RestError($code, $message, 500),
        };
    }

    /** The refusal for a folder already taken, naming what is there and what the package holds. */
    private function taken(string $kind, string $destination, \WP_Upgrader $upgrader): RestError
    {
        $new = $kind === 'theme' ? ($upgrader->new_theme_data ?? []) : ($upgrader->new_plugin_data ?? []);
        $current = $this->packages->identity(rtrim($destination, '/'), $kind);
        return new RestError('folder_exists', 'Destination folder already exists.', 409, [
            'destination' => rtrim($destination, '/'),
            'current_name' => $current['name'],
            'current_version' => $current['version'],
            'new_name' => (string) ($new['Name'] ?? ''),
            'new_version' => (string) ($new['Version'] ?? ''),
        ]);
    }
}
