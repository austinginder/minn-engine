<?php

declare(strict_types=1);

/**
 * Minn Engine boot. wp-settings.php requires this file; everything the
 * engine needs lives beside it, so the minn/ folder is the whole deploy.
 */

define('MINN_ENGINE_VERSION', '0.0.1');
define('MINN_ENGINE_DIR', __DIR__);

require_once __DIR__ . '/src/Minn/Autoloader.php';

Minn\Autoloader::register();
(new Minn\Engine(MINN_ENGINE_VERSION, __DIR__))->serve();
