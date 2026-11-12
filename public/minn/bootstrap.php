<?php

declare(strict_types=1);

/**
 * Minn Engine boot. wp-settings.php requires this file; everything the
 * engine needs lives beside it, so the minn/ folder is the whole deploy.
 * Under WP-CLI the file only makes the engine's classes available: the
 * command (see cli.php) drives the engine itself.
 */

if (!defined('MINN_ENGINE_VERSION')) {
    define('MINN_ENGINE_VERSION', '0.1.0');
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

// The server variables are made whole first, as the reference makes them before its maintenance check and its
// page cache's early file, so both read the path the engine will route (security sweep 2026-11-12, finding 2.5).
Minn\Http\Request::fixServerVars();

// Before anything else, as the reference does: a fresh .maintenance answers every request.
$minn_content_dir = defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : ABSPATH . 'wp-content';
$minn_maintenance = Minn\Front\Maintenance::answer(ABSPATH, $minn_content_dir, time());
if ($minn_maintenance !== null) {
    $minn_maintenance->send();
    return;
}

// A page cache's early file, in the global scope it expects, when WP_CACHE asks for it. What the
// reference has loaded by then comes first: the request's kind, as its entry file declares it, and
// its earliest functions (the hook API, load.php, plugin.php, the constants' setup and the
// compatibility shims), which page caches call from that file (WP Rocket asks is_admin() and is_ssl()).
// The rest of the facade, the object cache drop-in among it, still loads at boot.
if (defined('WP_CACHE') && WP_CACHE && Minn\Runtime\EarlyFilters::apply('enable_loading_advanced_cache_dropin', true) && is_file($minn_content_dir . '/advanced-cache.php')) {
    Minn\Runtime\Constants::initial(ABSPATH, __DIR__);
    Minn\Runtime\EarlyRequest::declare(Minn\Http\Request::fromGlobals());
    foreach (['hooks', 'load', 'plugin', 'misc', 'compat', 'functions', 'formatting'] as $minn_early) {
        require_once __DIR__ . '/wp-api/' . $minn_early . '.php';
    }
    include $minn_content_dir . '/advanced-cache.php';
}

(new Minn\Engine(MINN_ENGINE_VERSION, __DIR__))->serve();
