<?php
/**
 * Minn Engine login — password verification and session creation.
 *
 * Reproduces WordPress's password scheme so a user can sign in against the
 * same {prefix}users hashes, and creates a session row in the same
 * {prefix}usermeta session_tokens store the auth cookie validates against.
 * Implemented from the behavior matrix in contracts/rest/auth.md; no
 * WordPress source is used.
 *
 * Password scheme (modern "$wp$2y$..." installs): the stored value after the
 * "$wp$" prefix is a bcrypt hash of base64( HMAC-sha384( password,
 * "wp-sha384" ) ). Legacy phpass "$P$..." hashes are not verified here yet.
 */

function minn_check_password( string $password, string $hash ): bool {
	if ( str_starts_with( $hash, '$wp$' ) ) {
		$pre = base64_encode( hash_hmac( 'sha384', $password, 'wp-sha384', true ) );
		return password_verify( $pre, substr( $hash, 3 ) );
	}
	// Plain bcrypt (some tooling writes $2y$ directly).
	if ( str_starts_with( $hash, '$2y$' ) ) {
		return password_verify( $password, $hash );
	}
	return false;
}

/** A cryptographically random 43-char token, matching WP's alphabet width. */
function minn_generate_token(): string {
	// WordPress tokens are 43 base64-url-ish chars; any high-entropy string of
	// that width works because only sha256(token) is ever stored/compared.
	return substr( rtrim( strtr( base64_encode( random_bytes( 32 ) ), '+/', '-_' ), '=' ), 0, 43 );
}

/**
 * Create a session for the user and persist sha256(token) into the
 * session_tokens usermeta map, preserving existing live sessions. The
 * stored value carries expiration, ip, ua, and login, matching what the
 * cookie validator and WordPress both read. Returns the raw token.
 */
function minn_create_session( int $uid, int $expiration ): string {
	global $table_prefix;
	$token = minn_generate_token();
	$key   = hash( 'sha256', $token );

	$entry = array(
		'expiration' => $expiration,
		'ip'         => $_SERVER['REMOTE_ADDR'] ?? '',
		'ua'         => $_SERVER['HTTP_USER_AGENT'] ?? '',
		'login'      => time(),
	);

	$sessions          = minn_read_sessions( $uid );
	$sessions          = minn_prune_sessions( $sessions );
	$sessions[ $key ]  = $entry;
	minn_write_sessions( $uid, $sessions );

	return $token;
}

/** Remove one session (logout). Returns true when it existed. */
function minn_destroy_session( int $uid, string $token ): bool {
	$key      = hash( 'sha256', $token );
	$sessions = minn_read_sessions( $uid );
	if ( ! isset( $sessions[ $key ] ) ) {
		return false;
	}
	unset( $sessions[ $key ] );
	minn_write_sessions( $uid, $sessions );
	return true;
}

/** Read session_tokens as key => {expiration, ip, ua, login} without unserialize(). */
function minn_read_sessions( int $uid ): array {
	$blob = minn_user_meta( $uid, 'session_tokens' );
	if ( null === $blob || '' === $blob ) {
		return array();
	}
	$out = array();
	if ( preg_match_all(
		'/s:64:"([0-9a-f]{64})";a:\d+:\{(.*?)\}(?=s:64:|\}$)/s',
		$blob,
		$m,
		PREG_SET_ORDER
	) ) {
		foreach ( $m as $entry ) {
			$out[ $entry[1] ] = minn_parse_session_entry( $entry[2] );
		}
	}
	return $out;
}

function minn_parse_session_entry( string $inner ): array {
	$e = array( 'expiration' => 0, 'ip' => '', 'ua' => '', 'login' => 0 );
	if ( preg_match( '/s:10:"expiration";i:(\d+);/', $inner, $m ) ) {
		$e['expiration'] = (int) $m[1];
	}
	if ( preg_match( '/s:2:"ip";s:\d+:"(.*?)";/s', $inner, $m ) ) {
		$e['ip'] = $m[1];
	}
	if ( preg_match( '/s:2:"ua";s:\d+:"(.*?)";/s', $inner, $m ) ) {
		$e['ua'] = $m[1];
	}
	if ( preg_match( '/s:5:"login";i:(\d+);/', $inner, $m ) ) {
		$e['login'] = (int) $m[1];
	}
	return $e;
}

function minn_prune_sessions( array $sessions ): array {
	$now = time();
	foreach ( $sessions as $k => $e ) {
		if ( ( $e['expiration'] ?? 0 ) < $now ) {
			unset( $sessions[ $k ] );
		}
	}
	return $sessions;
}

/** Serialize the session map ourselves and store it (single UPDATE/INSERT). */
function minn_write_sessions( int $uid, array $sessions ): void {
	global $table_prefix;
	$serialized = minn_php_serialize_sessions( $sessions );
	// wp_slash is not needed: values are numeric, ip/ua are stored as-is by
	// core, and a prepared statement handles the bytes.
	$stmt = minn_db()->prepare(
		"INSERT INTO {$table_prefix}usermeta (user_id, meta_key, meta_value)
		 VALUES (?, 'session_tokens', ?)
		 ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value)"
	);
	// The unique key on (user_id, meta_key) may not exist on core schemas, so
	// fall back to a delete+insert when the upsert cannot match.
	if ( ! minn_usermeta_has_unique_key() ) {
		$del = minn_db()->prepare(
			"DELETE FROM {$table_prefix}usermeta WHERE user_id = ? AND meta_key = 'session_tokens'"
		);
		$del->bind_param( 'i', $uid );
		$del->execute();
		$ins = minn_db()->prepare(
			"INSERT INTO {$table_prefix}usermeta (user_id, meta_key, meta_value) VALUES (?, 'session_tokens', ?)"
		);
		$ins->bind_param( 'is', $uid, $serialized );
		$ins->execute();
		return;
	}
	$stmt->bind_param( 'is', $uid, $serialized );
	$stmt->execute();
}

function minn_usermeta_has_unique_key(): bool {
	static $has = null;
	if ( null === $has ) {
		global $table_prefix;
		$res = minn_db()->query( "SHOW INDEX FROM {$table_prefix}usermeta WHERE Non_unique = 0 AND Column_name = 'user_id'" );
		$has = $res && $res->num_rows > 0;
	}
	return $has;
}

/** Serialize the {key: {expiration,ip,ua,login}} session map as PHP does. */
function minn_php_serialize_sessions( array $sessions ): string {
	$out = 'a:' . count( $sessions ) . ':{';
	foreach ( $sessions as $key => $e ) {
		$out .= minn_php_serialize_string( (string) $key );
		$out .= 'a:4:{';
		$out .= minn_php_serialize_string( 'expiration' ) . 'i:' . (int) $e['expiration'] . ';';
		$out .= minn_php_serialize_string( 'ip' ) . minn_php_serialize_string( (string) $e['ip'] );
		$out .= minn_php_serialize_string( 'ua' ) . minn_php_serialize_string( (string) $e['ua'] );
		$out .= minn_php_serialize_string( 'login' ) . 'i:' . (int) $e['login'] . ';';
		$out .= '}';
	}
	return $out . '}';
}

function minn_php_serialize_string( string $s ): string {
	return 's:' . strlen( $s ) . ':"' . $s . '";';
}

/**
 * Authenticate a username/password. Returns the user row on success, null
 * otherwise. Does not create a session or set a cookie (callers decide).
 */
function minn_login( string $username, string $password ): ?array {
	$user = minn_get_user_by_login( $username );
	if ( ! $user ) {
		return null;
	}
	if ( ! minn_check_password( $password, $user['user_pass'] ) ) {
		return null;
	}
	return $user;
}
