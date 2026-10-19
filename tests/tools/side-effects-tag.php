<?php
/**
 * Loaded by WP-CLI (--require) when tests/tools/side-effects.php runs a
 * probe on the reference: marks the run's database connection as the
 * probe's once WordPress has connected, so the tool's triggers record its
 * writes and no one else's.
 */

WP_CLI::add_wp_hook('muplugins_loaded', static function (): void {
    $GLOBALS['wpdb']->query('SET @zz_side_effects_probe = 1');
}, -PHP_INT_MAX);
