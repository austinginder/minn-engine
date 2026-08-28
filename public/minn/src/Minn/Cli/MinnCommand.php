<?php

declare(strict_types=1);

namespace Minn\Cli;

use WP_CLI;

/**
 * Identifies the engine.
 *
 * ## EXAMPLES
 *
 *     wp minn version
 *     wp minn info
 */
final class MinnCommand
{
    /**
     * Prints the engine version.
     *
     * @when before_wp_load
     */
    public function version(array $args, array $assocArgs): void
    {
        WP_CLI::log(self::engineVersion());
    }

    /**
     * Prints the engine's version and where it runs from.
     *
     * @when before_wp_load
     */
    public function info(array $args, array $assocArgs): void
    {
        $runtime = Runtime::boot();
        WP_CLI::log('Minn Engine ' . self::engineVersion());
        WP_CLI::log('Engine dir: ' . MINN_ENGINE_DIR);
        WP_CLI::log('Site root: ' . ABSPATH);
        WP_CLI::log('Table prefix: ' . $runtime->db->prefix());
        WP_CLI::log('Home: ' . ($runtime->site->option('home') ?? ''));
    }

    private static function engineVersion(): string
    {
        return defined('MINN_ENGINE_VERSION') ? MINN_ENGINE_VERSION : '0.0.1';
    }
}
