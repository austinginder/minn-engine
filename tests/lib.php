<?php
/** Shared helpers for the engine suites. */

/**
 * The site the suites drive. It is NOT the marketing site: that one lives at
 * minn-engine.localhost, keeps the Minn site theme, and is never pinned or
 * written to by a run. The test site is a copy of it that exists to be pushed
 * around, so a crashed run can no longer strand the marketing page on
 * twentytwentyfive.
 */
function minn_test_site_root(): string {
	return rtrim( getenv( 'MINN_TEST_ROOT' ) ?: '~/Cove/Sites/minn.localhost', '/' );
}

/** The test site over HTTPS. */
function minn_test_url(): string {
	return rtrim( getenv( 'MINN_TEST_URL' ) ?: 'https://minn.localhost', '/' );
}

/** The parked WordPress the test site is diffed against. */
function minn_test_reference_url(): string {
	return rtrim( getenv( 'MINN_TEST_REF' ) ?: 'http://127.0.0.1:8123', '/' );
}

function minn_test_fetch( string $url, int $timeout = 10 ): array {
	$ctx  = stream_context_create(
		array(
			'ssl'  => array( 'verify_peer' => false, 'verify_peer_name' => false ),
			'http' => array( 'ignore_errors' => true, 'timeout' => $timeout ),
		)
	);
	$body    = @file_get_contents( $url, false, $ctx );
	$headers = array( 'status' => 0 );
	foreach ( $http_response_header ?? array() as $h ) {
		if ( preg_match( '#^HTTP/\S+\s+(\d+)#', $h, $m ) ) {
			$headers['status'] = (int) $m[1];
		} elseif ( str_contains( $h, ':' ) ) {
			[ $k, $v ] = explode( ':', $h, 2 );
			$headers[ strtolower( trim( $k ) ) ] = trim( $v );
		}
	}
	return array( $headers, minn_test_neutralise( (string) $body ) );
}

/** The wp-container-core-*-is-layout suffix is engine-defined (contracts/blocks.md); neutralise it before diffing. */
function minn_test_neutralise( string $body ): string {
	return (string) preg_replace( '/(wp-container-core-[a-z-]+-is-layout-)[0-9a-f]{8}/', '$1HASH', $body );
}

/** Structural diff: first differing path, or null when equal. Object key
 *  order is ignored; array order is enforced. */
function minn_test_diff( $a, $b, string $path = '$' ): ?string {
	if ( is_array( $a ) && is_array( $b ) ) {
		$a_list = array_is_list( $a );
		if ( $a_list !== array_is_list( $b ) ) {
			return "$path (list vs object)";
		}
		if ( $a_list && count( $a ) !== count( $b ) ) {
			return "$path (length " . count( $a ) . ' vs ' . count( $b ) . ')';
		}
		if ( ! $a_list ) {
			$missing = array_diff( array_keys( $a ), array_keys( $b ) );
			$extra   = array_diff( array_keys( $b ), array_keys( $a ) );
			if ( $missing || $extra ) {
				return "$path (keys missing: " . implode( ',', $missing ) . ' extra: ' . implode( ',', $extra ) . ')';
			}
		}
		foreach ( $a as $k => $v ) {
			$d = minn_test_diff( $v, $b[ $k ], "$path.$k" );
			if ( null !== $d ) {
				return $d;
			}
		}
		return null;
	}
	if ( $a !== $b ) {
		return "$path (" . var_export( $a, true ) . ' vs ' . var_export( $b, true ) . ')';
	}
	return null;
}

/**
 * The dev site's own theme is the Minn site theme (own git repo at
 * site/minn-site, the engine's front page); the parity fixtures were
 * captured under twentytwentyfive. Every suite pins the reference theme while it runs and
 * restores the site's own theme on shutdown. run-all.sh pins once for the
 * whole run and sets MINN_TEST_KEEP_THEME so the suites skip their own pin;
 * a suite that needs a different theme calls this with its slug.
 */
function minn_test_pin_theme( string $slug = 'twentytwentyfive' ): void {
	// Only the FIRST pin in a process saves the original and registers the
	// restore: shutdown functions run in registration order, so a second
	// pin's restore would otherwise win and strand the site on the first
	// pin's theme (a classic-suite run used to leave twentytwentyfive up).
	static $pinned = false;
	$public = minn_test_site_root() . '/public';
	$wp     = static function ( string $args ) use ( $public ): string {
		return trim( (string) shell_exec( 'cd ' . escapeshellarg( $public ) . ' && /opt/homebrew/bin/wp ' . $args . ' 2>/dev/null' ) );
	};
	$saved = array( 'template' => $wp( 'option get template' ), 'stylesheet' => $wp( 'option get stylesheet' ) );
	if ( $saved['stylesheet'] === $slug && $saved['template'] === $slug ) {
		return;
	}
	foreach ( array_keys( $saved ) as $name ) {
		$wp( 'option update ' . $name . ' ' . escapeshellarg( $slug ) . ' >/dev/null' );
	}
	if ( $pinned ) {
		return;
	}
	$pinned = true;
	register_shutdown_function(
		static function () use ( $wp, $saved ): void {
			foreach ( $saved as $name => $value ) {
				if ( $value !== '' ) {
					$wp( 'option update ' . $name . ' ' . escapeshellarg( $value ) . ' >/dev/null' );
				}
			}
		}
	);
}
/**
 * The fixtures and the prose checks were captured in English. A language
 * switched on for testing (WPLANG, or a user's locale meta) would change
 * what the reference renders and what the app loads, so every suite pins
 * en_US while it runs and puts the setting back on shutdown. run-all.sh
 * pins once and sets MINN_TEST_KEEP_LOCALE so the suites skip their own.
 */
function minn_test_pin_locale(): void {
	$public = minn_test_site_root() . '/public';
	$wp     = static function ( string $args ) use ( $public ): string {
		return trim( (string) shell_exec( 'cd ' . escapeshellarg( $public ) . ' && /opt/homebrew/bin/wp ' . $args . ' 2>/dev/null' ) );
	};
	$prefix = $wp( 'config get table_prefix' ) ?: 'wp_';
	$row    = $wp( 'db query ' . escapeshellarg( "SELECT CONCAT('=', option_value) FROM {$prefix}options WHERE option_name = 'WPLANG'" ) . ' --skip-column-names' );
	$exists = str_starts_with( $row, '=' );
	$site   = $exists ? substr( $row, 1 ) : null;
	$users  = array();
	foreach ( array_filter( explode( "\n", $wp( 'db query ' . escapeshellarg( "SELECT user_id, meta_value FROM {$prefix}usermeta WHERE meta_key = 'locale' AND meta_value <> ''" ) . ' --skip-column-names' ) ) ) as $row ) {
		[ $id, $locale ] = array_pad( preg_split( '/\t/', $row ), 2, '' );
		$users[ (int) $id ] = $locale;
	}
	if ( ( $site ?? '' ) === '' && $users === array() ) {
		return;
	}
	$wp( 'db query ' . escapeshellarg( "UPDATE {$prefix}usermeta SET meta_value = '' WHERE meta_key = 'locale'" ) );
	if ( $exists ) {
		$wp( 'option update WPLANG "" >/dev/null' );
	}
	register_shutdown_function(
		static function () use ( $wp, $prefix, $exists, $site, $users ): void {
			if ( $exists && $site !== null && $site !== '' ) {
				$wp( 'option update WPLANG ' . escapeshellarg( $site ) . ' >/dev/null' );
			}
			foreach ( $users as $id => $locale ) {
				$wp( 'db query ' . escapeshellarg( "UPDATE {$prefix}usermeta SET meta_value = '" . addslashes( $locale ) . "' WHERE meta_key = 'locale' AND user_id = " . (int) $id ) );
			}
		}
	);
}
if ( getenv( 'MINN_TEST_KEEP_THEME' ) === false ) {
	minn_test_pin_theme();
}
if ( getenv( 'MINN_TEST_KEEP_LOCALE' ) === false ) {
	minn_test_pin_locale();
}
