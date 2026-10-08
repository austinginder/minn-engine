<?php
/**
 * Local facts the suites need that stay off the public repository: where the
 * Cove sites live, the names of the dogfood sites, and the local admin
 * password. They come from tests/local.json (gitignored); without one, from
 * tests/local.example.json, which shows the keys:
 *
 *   sites          the Cove sites folder (default $HOME/Cove/Sites)
 *   dogfood        the dogfood site: a copy of a real site, child theme and all
 *   shop           the shop dogfood site: a copy of a production WooCommerce shop
 *   adminPassword  the admin password of the test and marketing sites
 *
 * No side effects, so a tool can require it without the suite helpers.
 */

/** One value from tests/local.json, or from the example file when there is none. */
function minn_test_local( string $key ): string {
	static $local = null;
	if ( $local === null ) {
		$file  = __DIR__ . '/local.json';
		$local = json_decode( (string) file_get_contents( is_file( $file ) ? $file : __DIR__ . '/local.example.json' ), true );
		$local = is_array( $local ) ? $local : array();
	}
	return (string) ( $local[ $key ] ?? '' );
}

/** The folder Cove keeps its sites in. */
function minn_test_sites_dir(): string {
	return rtrim( getenv( 'MINN_SITES_DIR' ) ?: ( minn_test_local( 'sites' ) ?: getenv( 'HOME' ) . '/Cove/Sites' ), '/' );
}

/** A Cove site's root folder by its name (the part before .localhost). */
function minn_test_site_dir( string $name ): string {
	return minn_test_sites_dir() . '/' . $name . '.localhost';
}
