<?php

declare(strict_types=1);

/**
 * WP-CLI entry point for Minn Engine, loaded through wp-cli.yml before any
 * bundled command. Each verb runs on the engine in the before_wp_load
 * phase, so WP-CLI never tries to load WordPress.
 */

if (!defined('WP_CLI') || !WP_CLI) {
    return;
}

require_once __DIR__ . '/src/Minn/Autoloader.php';
Minn\Autoloader::register();

Minn\Cli\Commands::register();
