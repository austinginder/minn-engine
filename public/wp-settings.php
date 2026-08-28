<?php
/**
 * Minn Engine boot.
 *
 * WordPress's stock wp-config.php ends with `require_once ABSPATH .
 * 'wp-settings.php'`. Shipping the engine's boot under this filename means
 * an unmodified wp-config.php (the file backup and migration tooling reads
 * with regexes) boots Minn Engine instead of WordPress, with zero edits.
 */

define( 'MINN_ENGINE_VERSION', '0.0.1' );

require_once dirname( __DIR__ ) . '/src/Minn/Autoloader.php';

Minn\Autoloader::register();
( new Minn\Engine( MINN_ENGINE_VERSION, dirname( __DIR__ ) ) )->serve();
