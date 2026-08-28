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

    /**
     * Says what a WordPress webroot will and will not get from the engine.
     *
     * ## OPTIONS
     *
     * [<webroot>]
     * : The webroot to inspect. Defaults to the current directory.
     *
     * @when before_wp_load
     */
    public function preflight(array $args, array $assocArgs): void
    {
        self::installer('preflight', $args, $assocArgs);
    }

    /**
     * Parks WordPress and installs the engine into a webroot.
     *
     * ## OPTIONS
     *
     * [<webroot>]
     * : The webroot. Defaults to the current directory.
     *
     * [--park=<dir>]
     * : Where WordPress's own files go. Defaults to wp-parked beside the webroot.
     *
     * [--force]
     * : Install even when preflight is RED.
     *
     * @when before_wp_load
     */
    public function install(array $args, array $assocArgs): void
    {
        self::installer('install', $args, $assocArgs);
    }

    /**
     * Removes the engine and puts WordPress's files back.
     *
     * ## OPTIONS
     *
     * [<webroot>]
     * : The webroot. Defaults to the current directory.
     *
     * @when before_wp_load
     */
    public function eject(array $args, array $assocArgs): void
    {
        self::installer('eject', $args, $assocArgs);
    }

    /**
     * Says whether a webroot runs WordPress or the engine.
     *
     * ## OPTIONS
     *
     * [<webroot>]
     * : The webroot. Defaults to the current directory.
     *
     * @when before_wp_load
     */
    public function status(array $args, array $assocArgs): void
    {
        self::installer('status', $args, $assocArgs);
    }

    private static function installer(string $command, array $args, array $assocArgs): void
    {
        $argv = [$command, $args[0] ?? (string) getcwd()];
        foreach ($assocArgs as $key => $value) {
            $argv[] = '--' . $key . ($value === true ? '' : '=' . $value);
        }
        $code = Installer::main($argv, defined('MINN_ENGINE_DIR') ? MINN_ENGINE_DIR : dirname(__DIR__, 3));
        if ($code !== 0) {
            WP_CLI::halt($code);
        }
    }

    private static function engineVersion(): string
    {
        return defined('MINN_ENGINE_VERSION') ? MINN_ENGINE_VERSION : '0.0.1';
    }
}
