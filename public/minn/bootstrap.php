<?php

declare(strict_types=1);

/**
 * Minn Engine boot. wp-settings.php requires this file; everything the
 * engine needs lives beside it, so the minn/ folder is the whole deploy.
 * Under WP-CLI the file only makes the engine's classes available: the
 * command (see cli.php) drives the engine itself.
 */

if (!defined('MINN_ENGINE_VERSION')) {
    define('MINN_ENGINE_VERSION', '0.0.1');
    define('MINN_ENGINE_DIR', __DIR__);
}

require_once __DIR__ . '/src/Minn/Autoloader.php';

if (defined('WP_CLI') && WP_CLI) {
    // The engine's own commands boot through Minn\Cli\Runtime and set this
    // constant first. Reaching here without it means WP-CLI is loading
    // WordPress for a command the engine does not answer.
    if (!defined('MINN_CLI_RUNTIME')) {
        WP_CLI::error(
            "This command needs WordPress itself, which Minn Engine does not contain.\n"
            . 'The engine answers: option get/add/update/delete/set, user list/get/login/create/update/delete, plugin list, theme list, cache flush, search-replace, minn version/info/probe, '
            . "and WP-CLI's own config and db commands."
        );
    }
    return;
}

Minn\Autoloader::register();
(new Minn\Engine(MINN_ENGINE_VERSION, __DIR__))->serve();
