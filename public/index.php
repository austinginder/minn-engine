<?php
/**
 * Minn Engine front controller.
 *
 * Mirrors the WordPress boot chain shape: index.php loads wp-config.php,
 * and wp-config.php ends by requiring wp-settings.php, which here is the
 * engine's own boot file. The file layout and config format are part of
 * the Tier 1 operational contract; every line of implementation is
 * original, MIT-licensed work.
 *
 * WordPress defines ABSPATH before wp-config.php runs (wp-load.php).
 * Custom configs use it (this is the contract, not an invitation to
 * edit wp-config). Define it here so those files boot unchanged.
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

require_once __DIR__ . '/wp-config.php';
