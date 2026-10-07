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
    // WordPress for a command the engine does not answer itself (a plugin's
    // command, wp eval, a bundled command): the engine's WordPress runtime
    // stands in, plugins loaded, and WP-CLI runs the command against it.
    if (!defined('MINN_CLI_RUNTIME')) {
        Minn\Autoloader::register();
        Minn\Cli\Runtime::standIn();
    }
    return;
}

// Requested straight over the web (/minn/bootstrap.php) instead of through
// wp-settings.php: nothing is served from here, and nothing says what is here.
if (!defined('ABSPATH') && PHP_SAPI !== 'cli') {
    http_response_code(404);
    return;
}

Minn\Autoloader::register();

// Before anything else, as the reference does: a fresh .maintenance answers every request.
$minn_content_dir = defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : ABSPATH . 'wp-content';
$minn_maintenance = Minn\Front\Maintenance::answer(ABSPATH, $minn_content_dir, time());
if ($minn_maintenance !== null) {
    $minn_maintenance->send();
    return;
}

// A page cache's early file, in the global scope it expects, when WP_CACHE asks for it.
// The hook API comes first, as the reference's does: page caches register callbacks from that file.
if (defined('WP_CACHE') && WP_CACHE && Minn\Runtime\EarlyFilters::apply('enable_loading_advanced_cache_dropin', true) && is_file($minn_content_dir . '/advanced-cache.php')) {
    require_once __DIR__ . '/wp-api/hooks.php';
    include $minn_content_dir . '/advanced-cache.php';
}

(new Minn\Engine(MINN_ENGINE_VERSION, __DIR__))->serve();
