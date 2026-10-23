<?php
/**
 * Minn Engine admin-path shape file.
 *
 * Hosts such as Kinsta 403 a directory that exists without an index, and
 * plugins `require` files under wp-admin/includes/, so this directory has
 * to exist on disk. The same file is laid under every other wp-admin/*.php
 * name in data/entry-files.json (admin-ajax.php, admin-post.php,
 * async-upload.php, post.php, ...), because nginx passes a request for a
 * .php file to PHP only when the file exists. Direct request boots like
 * index.php and the router answers the path (/wp-admin/ itself 302s to
 * /minn-admin/). Required mid-request, the engine is already up and this
 * file does nothing. Original, MIT-licensed work.
 */

if (defined('MINN_ENGINE_DIR')) {
    return;
}

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

require_once dirname(__DIR__) . '/wp-config.php';
