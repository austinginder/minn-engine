<?php
/**
 * Minn Engine front controller.
 *
 * Mirrors the WordPress boot chain shape: index.php loads wp-config.php,
 * and wp-config.php ends by requiring wp-settings.php, which here is the
 * engine's own boot file. The file layout and config format are part of
 * the Tier 1 operational contract; every line of implementation is
 * original, MIT-licensed work.
 */

require_once __DIR__ . '/wp-config.php';
