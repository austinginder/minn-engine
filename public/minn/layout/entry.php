<?php
/**
 * Minn Engine entry-point shape file.
 *
 * Laid at the webroot under the names of WordPress's request entry points
 * (wp-cron.php, wp-comments-post.php, xmlrpc.php and the rest in
 * data/entry-files.json). Hosts such as Kinsta (nginx) pass a request for a
 * .php file to PHP only when the file exists, so without these the host's
 * cron, comment posting and the other entry points answer 404 before the
 * engine sees them. Requested directly, it boots like index.php and the
 * router answers the path. Required mid-request, the engine is already up
 * and it does nothing. Original, MIT-licensed work.
 */

if (defined('MINN_ENGINE_DIR')) {
    return;
}

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

require_once __DIR__ . '/wp-config.php';
