<?php
/**
 * Minn Engine auth — WordPress cookie and REST-nonce compatibility.
 *
 * The crypto here reproduces WordPress's documented auth scheme so that a
 * session minted by WordPress is accepted by the engine and a cookie minted
 * by the engine is accepted by WordPress. Salts arrive as constants from the
 * site's own wp-config.php (LOGGED_IN_KEY/SALT, NONCE_KEY/SALT), so the
 * engine keys off exactly what the WordPress install used. Implemented from
 * the behavior matrix in contracts/rest/auth.md and cross-checked against a
 * live reference; no WordPress source is used.
 *
 * Scheme (logged_in cookie): value is
 *   username|expiration|token|hmac
 * with
 *   pass_frag = the last 4 chars of a modern "$wp$"-prefixed (bcrypt) hash,
 *               or substr(user_pass, 8, 4) for a legacy phpass "$P$" hash
 *   key  = HMAC-md5(username|pass_frag|expiration|token, salt('logged_in'))
 *   hmac = HMAC-sha256(username|expiration|token, key)
 * The session is valid when sha256(token) is a live entry in the user's
 * session_tokens meta. The wp_rest nonce is
 *   substr(HMAC-md5(tick|wp_rest|uid|token, salt('nonce')), -12, 10)
 * accepting the current tick and the one before it.
 */

const MINN_DAY  = 86400;
const MINN_NONCE_LIFE = MINN_DAY;

function minn_salt( string $scheme ): string {
	$map = array(
		'logged_in' => array( 'LOGGED_IN_KEY', 'LOGGED_IN_SALT' ),
		'nonce'     => array( 'NONCE_KEY', 'NONCE_SALT' ),
		'auth'      => array( 'AUTH_KEY', 'AUTH_SALT' ),
	);
	[ $k, $s ] = $map[ $scheme ];
	return ( defined( $k ) ? constant( $k ) : '' ) . ( defined( $s ) ? constant( $s ) : '' );
}

function minn_hash( string $data, string $scheme ): string {
	return hash_hmac( 'md5', $data, minn_salt( $scheme ) );
}

/** Constant-time compare, mirroring hash_equals usage in the reference. */
function minn_hash_equals( string $a, string $b ): bool {
	return hash_equals( $a, $b );
}

/**
 * The 4-character password fragment the cookie key is derived from.
 *
 * Modern installs store "$wp$2y$..." (bcrypt behind a wrapper) and key off
 * the LAST four characters; the historical phpass format ("$P$…") keys off
 * offset 8. Getting this wrong yields a cookie that verifies nowhere, so
 * both branches are pinned by tests/auth-crypto.test.php.
 */
function minn_pass_fragment( string $user_pass ): string {
	return str_starts_with( $user_pass, '$wp$' )
		? substr( $user_pass, -4 )
		: substr( $user_pass, 8, 4 );
}

function minn_get_user_by_login( string $login ): ?array {
	global $table_prefix;
	$stmt = minn_db()->prepare(
		"SELECT * FROM {$table_prefix}users WHERE user_login = ? LIMIT 1"
	);
	$stmt->bind_param( 's', $login );
	$stmt->execute();
	return $stmt->get_result()->fetch_assoc() ?: null;
}

function minn_get_user_by_id( int $id ): ?array {
	global $table_prefix;
	$stmt = minn_db()->prepare( "SELECT * FROM {$table_prefix}users WHERE ID = ? LIMIT 1" );
	$stmt->bind_param( 'i', $id );
	$stmt->execute();
	return $stmt->get_result()->fetch_assoc() ?: null;
}

/**
 * Is sha256(token) a live session for this user? Reads the serialized
 * session_tokens meta without unserialize(): the map is
 * a:N:{s:64:"<sha256>";a:4:{...s:10:"expiration";i:<ts>;...}}, so a bounded
 * scan pulls each token key and its expiration.
 */
function minn_session_is_live( int $uid, string $token ): bool {
	$want = hash( 'sha256', $token );
	$blob = minn_user_meta( $uid, 'session_tokens' );
	if ( null === $blob || '' === $blob ) {
		return false;
	}
	// Each entry begins s:64:"<key>"; then an array with an integer
	// "expiration". Find the key, then the first expiration after it.
	if ( ! preg_match_all( '/s:64:"([0-9a-f]{64})";a:\d+:\{(.*?)\}(?=s:64:|$|\}$)/s', $blob, $m, PREG_SET_ORDER ) ) {
		// Fallback: locate the key then the next expiration int anywhere after.
		$pos = strpos( $blob, 's:64:"' . $want . '"' );
		if ( false === $pos ) {
			return false;
		}
		if ( preg_match( '/s:10:"expiration";i:(\d+);/', substr( $blob, $pos ), $mm ) ) {
			return (int) $mm[1] >= time();
		}
		return true;
	}
	foreach ( $m as $entry ) {
		if ( ! minn_hash_equals( $entry[1], $want ) ) {
			continue;
		}
		if ( preg_match( '/s:10:"expiration";i:(\d+);/', $entry[2], $mm ) ) {
			return (int) $mm[1] >= time();
		}
		return true;
	}
	return false;
}

function minn_user_meta( int $uid, string $key ): ?string {
	global $table_prefix;
	$stmt = minn_db()->prepare(
		"SELECT meta_value FROM {$table_prefix}usermeta WHERE user_id = ? AND meta_key = ? LIMIT 1"
	);
	$stmt->bind_param( 'is', $uid, $key );
	$stmt->execute();
	$row = $stmt->get_result()->fetch_row();
	return $row ? $row[0] : null;
}

/**
 * Validate a logged_in cookie value. Returns [user_row, token] or null.
 */
function minn_validate_auth_cookie( string $cookie ): ?array {
	$parts = explode( '|', $cookie );
	if ( 4 !== count( $parts ) ) {
		return null;
	}
	[ $username, $expiration, $token, $hmac ] = $parts;
	if ( (int) $expiration < time() ) {
		return null;
	}
	$user = minn_get_user_by_login( $username );
	if ( ! $user ) {
		return null;
	}
	$pass_frag = minn_pass_fragment( $user['user_pass'] );
	$key       = minn_hash( $username . '|' . $pass_frag . '|' . $expiration . '|' . $token, 'logged_in' );
	$expected  = hash_hmac( 'sha256', $username . '|' . $expiration . '|' . $token, $key );
	if ( ! minn_hash_equals( $expected, $hmac ) ) {
		return null;
	}
	if ( ! minn_session_is_live( (int) $user['ID'], $token ) ) {
		return null;
	}
	return array( $user, $token );
}

/** The logged_in cookie name this site uses: md5 of the site URL. */
function minn_logged_in_cookie_name(): string {
	$siteurl = minn_option( 'siteurl' ) ?? '';
	return 'wordpress_logged_in_' . md5( $siteurl );
}

function minn_nonce_tick(): float {
	return ceil( time() / ( MINN_NONCE_LIFE / 2 ) );
}

/**
 * Verify a wp_rest nonce for a logged-in user. uid is the numeric user id;
 * WordPress feeds the id (not 0) into the nonce for logged-in users, plus
 * the session token. Accepts the current and previous tick.
 */
function minn_verify_rest_nonce( string $nonce, int $uid, string $token ): bool {
	$tick = minn_nonce_tick();
	foreach ( array( $tick, $tick - 1 ) as $t ) {
		$expected = substr( minn_hash( $t . '|wp_rest|' . $uid . '|' . $token, 'nonce' ), -12, 10 );
		if ( minn_hash_equals( $expected, $nonce ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Resolve the current request's authenticated user for a REST call.
 * Returns [user_row, token] on success. On failure sets $why to the
 * matching WordPress error code and returns null. A request with no auth
 * cookie at all is anonymous ($why stays 'rest_not_logged_in').
 */
function minn_authenticate_rest( ?string &$why ): ?array {
	$name = minn_logged_in_cookie_name();
	// Accept the canonical name; tooling may also send a host-derived name.
	$cookie = $_COOKIE[ $name ] ?? null;
	if ( null === $cookie ) {
		foreach ( $_COOKIE as $k => $v ) {
			if ( str_starts_with( $k, 'wordpress_logged_in_' ) ) {
				$cookie = $v;
				break;
			}
		}
	}
	if ( null === $cookie ) {
		$why = 'rest_not_logged_in';
		return null;
	}
	$valid = minn_validate_auth_cookie( $cookie );
	if ( null === $valid ) {
		$why = 'rest_not_logged_in';
		return null;
	}
	[ $user, $token ] = $valid;
	$nonce            = $_SERVER['HTTP_X_WP_NONCE'] ?? ( $_GET['_wpnonce'] ?? null );
	if ( null === $nonce || ! minn_verify_rest_nonce( (string) $nonce, (int) $user['ID'], $token ) ) {
		$why = 'rest_cookie_invalid_nonce';
		return null;
	}
	$why = '';
	return array( $user, $token );
}

/**
 * Mint a logged_in cookie value for a user and session token. Used by the
 * cross-acceptance suite to prove an engine-minted cookie is accepted by
 * WordPress itself.
 */
function minn_generate_auth_cookie( array $user, int $expiration, string $token ): string {
	$username  = $user['user_login'];
	$pass_frag = minn_pass_fragment( $user['user_pass'] );
	$key       = minn_hash( $username . '|' . $pass_frag . '|' . $expiration . '|' . $token, 'logged_in' );
	$hmac      = hash_hmac( 'sha256', $username . '|' . $expiration . '|' . $token, $key );
	return $username . '|' . $expiration . '|' . $token . '|' . $hmac;
}

/** Mint a wp_rest nonce for a user and session token. */
function minn_create_rest_nonce( int $uid, string $token ): string {
	return substr( minn_hash( minn_nonce_tick() . '|wp_rest|' . $uid . '|' . $token, 'nonce' ), -12, 10 );
}
