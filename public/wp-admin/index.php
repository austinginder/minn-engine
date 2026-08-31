<?php
/**
 * Minn Engine admin-path shape file.
 *
 * Hosts such as Kinsta 403 a directory that exists without an index, and
 * plugins `require` files under wp-admin/includes/, so this directory has
 * to exist on disk. Direct request boots like index.php; the engine then
 * 302s /wp-admin/ to /minn-admin/. Required mid-request, the engine is
 * already up and this file does nothing. Original, MIT-licensed work.
 */

if (defined('MINN_ENGINE_DIR')) {
    return;
}

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

require_once dirname(__DIR__) . '/wp-config.php';
