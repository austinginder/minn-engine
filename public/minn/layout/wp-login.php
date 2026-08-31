<?php
/**
 * Minn Engine sign-in shape file.
 *
 * Two ways in. Requested directly, it boots exactly like index.php and the
 * engine routes /wp-login.php to its sign-in surface. Require'd
 * mid-request by plugin code (a hide-login plugin serving its own sign-in
 * URL requires ABSPATH . 'wp-login.php'), the engine is already up, so it
 * signals Engine::respond() to answer the current request with the sign-in
 * surface instead. Original, MIT-licensed work.
 */

if (defined('MINN_ENGINE_DIR') && class_exists(\Minn\Login\ServeLogin::class)) {
    throw new \Minn\Login\ServeLogin();
}

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

require_once __DIR__ . '/wp-config.php';
