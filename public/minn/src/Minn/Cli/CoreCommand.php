<?php

declare(strict_types=1);

namespace Minn\Cli;

use Minn\Ops\EngineChecksums;
use RuntimeException;
use WP_CLI;

/**
 * WP-CLI's core verbs where a host's automation meets a Minn site. A Minn
 * site has none of WordPress's core files, so a WordPress core update
 * neither applies nor may pretend to: `core update` says the site is up to
 * date (Minn reports the latest release it speaks) and refuses outright
 * when asked for a version or a package, and `core download` is held back
 * over a Minn install, since it would put WordPress's files over the
 * engine. `core verify-checksums` checks the engine's own files against
 * its signed release. Minn's own updater is `wp minn update`.
 */
final class CoreCommand
{
    private const REFUSAL = "This site runs Minn Engine, which keeps WordPress's API but none of its core files, so a WordPress core update does not apply. Update Minn instead: wp minn update.";

    /**
     * Says whether WordPress core is up to date; on Minn it always is, and an asked-for version or package is refused.
     *
     * ## OPTIONS
     *
     * [<zip>]
     * : A WordPress package; refused on Minn.
     *
     * [--minor]
     * : Only a minor release.
     *
     * [--version=<version>]
     * : A WordPress version; refused on Minn.
     *
     * [--force]
     * : Refused on Minn.
     *
     * [--locale=<locale>]
     * : Ignored.
     *
     * [--insecure]
     * : Ignored.
     *
     * @when before_wp_load
     *
     * @param list<string> $args
     * @param array<string, string|bool> $assocArgs
     */
    public static function update(array $args, array $assocArgs): void
    {
        if (isset($args[0]) || isset($assocArgs['version']) || !empty($assocArgs['force'])) {
            WP_CLI::error(self::REFUSAL);
        }
        WP_CLI::success(empty($assocArgs['minor']) ? 'WordPress is up to date.' : 'WordPress is at the latest minor release.');
    }

    /**
     * Checks the install's files against its signed Minn release, in WP-CLI's own words for the result.
     *
     * ## OPTIONS
     *
     * [--include-root]
     * : Ignored; the webroot's shape files are always checked.
     *
     * [--version=<version>]
     * : Ignored; the running Minn's release is the one checked.
     *
     * [--locale=<locale>]
     * : Ignored.
     *
     * [--insecure]
     * : Ignored.
     *
     * [--exclude=<files>]
     * : Comma-separated paths to leave out of the result.
     *
     * @when before_wp_load
     *
     * @param list<string> $args
     * @param array<string, string|bool> $assocArgs
     */
    public static function verifyChecksums(array $args, array $assocArgs): void
    {
        $engineDir = defined('MINN_ENGINE_DIR') ? (string) MINN_ENGINE_DIR : dirname(__DIR__, 3);
        try {
            $result = (new EngineChecksums($engineDir))->check(self::root());
        } catch (RuntimeException $e) {
            WP_CLI::error($e->getMessage());
        }
        $excluded = array_filter(array_map('trim', explode(',', (string) ($assocArgs['exclude'] ?? ''))));
        $said = 0;
        foreach (['changed' => "File doesn't verify against checksum: %s", 'missing' => "File doesn't exist: %s", 'extra' => 'File should not exist: %s'] as $kind => $words) {
            foreach (array_diff($result[$kind], $excluded) as $path) {
                WP_CLI::warning(sprintf($words, $path));
                $said++;
            }
        }
        if ($said > 0) {
            WP_CLI::error("WordPress installation doesn't verify against checksums.");
        }
        WP_CLI::success('WordPress installation verifies against checksums.');
    }

    /** Holds back `core download` over a Minn install unless MINN_ALLOW_CORE_DOWNLOAD is set; `wp minn eject` is the way back to WordPress. */
    public static function guardDownload(): void
    {
        if (getenv('MINN_ALLOW_CORE_DOWNLOAD')) {
            return;
        }
        if (self::runsMinn(self::root())) {
            WP_CLI::error("This site runs Minn Engine. wp core download would put WordPress's files over it. To go back to WordPress, run wp minn eject. To download anyway, set MINN_ALLOW_CORE_DOWNLOAD=1.");
        }
    }

    /** The webroot WP-CLI works in: its --path, else the current directory. */
    private static function root(): string
    {
        $config = WP_CLI::get_runner()->config;
        return rtrim((string) (is_string($config['path'] ?? null) && $config['path'] !== '' ? $config['path'] : getcwd()), '/');
    }

    /** Whether the webroot boots the engine: its wp-settings.php hands off to minn/bootstrap.php. */
    private static function runsMinn(string $root): bool
    {
        $settings = $root . '/wp-settings.php';
        return is_file($root . '/minn/bootstrap.php') && is_file($settings) && str_contains((string) file_get_contents($settings), 'minn/bootstrap.php');
    }
}
