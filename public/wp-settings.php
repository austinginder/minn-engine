<?php
/**
 * Minn Engine boot.
 *
 * WordPress's stock wp-config.php ends with `require_once ABSPATH .
 * 'wp-settings.php'`. Shipping the engine's boot under this filename means
 * an unmodified wp-config.php (the file backup and migration tooling reads
 * with regexes) boots Minn Engine instead of WordPress, with zero edits.
 */

require_once __DIR__ . '/minn/bootstrap.php';
