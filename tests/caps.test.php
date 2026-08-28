<?php
/**
 * Capabilities + login suite.
 *
 * Two claims. First, the engine's current_user_can matches WordPress across
 * roles and the meta-cap mapping (edit_post/delete_post branch on ownership
 * and status), checked against the live reference as oracle. Second, the
 * engine can log a user in from a password and mint a session that
 * WordPress accepts, and it renders the edit-context user object
 * byte-for-byte.
 *
 * Run:  php tests/caps.test.php
 * Ref:  (cd wp-reference && php -S 127.0.0.1:8123) — SKIPs when down.
 */

$ENGINE = rtrim( getenv( 'MINN_TEST_URL' ) ?: 'https://minn-engine.localhost', '/' );
$REF    = 'http://127.0.0.1:8123';
$ROOT   = dirname( __DIR__ );

require_once __DIR__ . '/lib.php';

[ $ph ] = minn_test_fetch( "$REF/?rest_route=/wp/v2/posts", 3 );
if ( 200 !== $ph['status'] ) {
	echo "SKIP: reference WordPress not running at $REF\n";
	exit( 0 );
}

$pass = 0;
$fail = 0;
function check( bool $ok, string $label, string $detail = '' ): void {
	global $pass, $fail;
	if ( $ok ) { $pass++; echo "  ok  $label\n"; }
	else { $fail++; echo "FAIL  $label" . ( $detail ? "\n      $detail" : '' ) . "\n"; }
}

/**
 * The oracle's verdict for user_can, straight from the reference. Returns
 * 'Y'/'n'. post is optional (meta caps).
 */
function ref_can( int $uid, string $cap, ?int $post ): string {
	global $ROOT;
	$arg  = null === $post ? 'null' : (int) $post;
	$code = "echo user_can($uid,'" . addslashes( $cap ) . "'," . $arg . ")?'Y':'n';";
	return trim( (string) shell_exec(
		'wp --path=' . escapeshellarg( "$ROOT/wp-reference" ) . ' eval ' . escapeshellarg( $code ) . ' 2>/dev/null'
	) );
}

/** The engine's verdict, computed in-process via its own caps module. */
function eng_can( int $uid, string $cap, ?int $post ): string {
	static $loaded = false;
	global $ROOT;
	if ( ! $loaded ) {
		$config = file_get_contents( "$ROOT/public/wp-config.php" );
		$config = str_replace( "require_once ABSPATH . 'wp-settings.php';", '', $config );
		eval( '?>' . $config );
		// wp-config sets $table_prefix at file scope; here it is function-local,
		// so promote it to the global the engine reads.
		$GLOBALS['table_prefix'] = $table_prefix;
		require "$ROOT/src/bootstrap.php";
		require "$ROOT/src/compat.php";
		$loaded = true;
	}
	return minn_user_can( $uid, $cap, $post ) ? 'Y' : 'n';
}

echo "caps + login suite: $ENGINE / $REF (oracle)\n";

// 1. Capability parity across roles and the meta-cap branches. Users:
//    1 admin, 2 editor, 3 author (scribe). Posts: 9 editor-owned publish,
//    10 author draft, 11 author publish.
$matrix = array(
	array( 1, 'manage_options', null ),
	array( 2, 'manage_options', null ),
	array( 3, 'manage_options', null ),
	array( 1, 'edit_others_posts', null ),
	array( 2, 'edit_others_posts', null ),
	array( 3, 'edit_others_posts', null ),
	array( 2, 'manage_categories', null ),
	array( 3, 'publish_posts', null ),
	array( 3, 'upload_files', null ),
	array( 3, 'edit_post', 10 ),  // own draft
	array( 3, 'edit_post', 11 ),  // own published
	array( 3, 'edit_post', 9 ),   // others' post
	array( 2, 'edit_post', 10 ),  // editor on others'
	array( 3, 'delete_post', 11 ),// own published
	array( 3, 'delete_post', 9 ), // others'
	array( 2, 'delete_post', 9 ), // editor on others'
	array( 3, 'read_post', 9 ),   // published
	array( 1, 'promote_users', null ),
	array( 2, 'promote_users', null ),
);
$mismatch = 0;
foreach ( $matrix as $t ) {
	[ $uid, $cap, $post ] = $t;
	$ref = ref_can( $uid, $cap, $post );
	$eng = eng_can( $uid, $cap, $post );
	if ( $ref !== $eng ) {
		$mismatch++;
		echo "      user $uid $cap" . ( null === $post ? '' : "($post)" ) . ": ref=$ref eng=$eng\n";
	}
}
check( 0 === $mismatch, 'current_user_can matches the oracle across ' . count( $matrix ) . ' cases', "$mismatch mismatched" );

// 2. Login: the engine authenticates a password and mints a session.
$login = json_decode( (string) shell_exec(
	'php ' . escapeshellarg( "$ROOT/tests/tools/engine-login.php" ) . ' admin ' . escapeshellarg( 'password' )
), true );
check( ! empty( $login['ok'] ), 'engine logs in with a correct password' );

$bad = json_decode( (string) shell_exec(
	'php ' . escapeshellarg( "$ROOT/tests/tools/engine-login.php" ) . ' admin ' . escapeshellarg( 'wrong-password' )
), true );
check( empty( $bad['ok'] ), 'engine rejects a wrong password' );

// 3. WordPress accepts the session the engine just created.
if ( ! empty( $login['ok'] ) ) {
	$alt     = 'wordpress_logged_in_' . md5( $REF );
	$headers = 'Cookie: ' . $login['cookie_name'] . '=' . $login['cookie'] . '; ' . $alt . '=' . $login['cookie'] . "\r\n"
			. 'X-WP-Nonce: ' . $login['nonce'];
	$ctx  = stream_context_create( array( 'http' => array( 'header' => $headers, 'ignore_errors' => true, 'timeout' => 10 ) ) );
	$body = json_decode( (string) @file_get_contents( "$REF/?rest_route=/wp/v2/users/me", false, $ctx ), true );
	check( 1 === ( $body['id'] ?? 0 ), 'WordPress accepts the engine-created session', json_encode( $body ) );
}

// 4. Edit-context user object matches the reference byte for byte.
$mint = json_decode( (string) shell_exec(
	'wp --path=' . escapeshellarg( "$ROOT/wp-reference" ) . ' eval-file ' . escapeshellarg( "$ROOT/tests/tools/mint-session.php" ) . ' 1 2>/dev/null'
), true );
if ( ! empty( $mint['cookie'] ) ) {
	$cn      = $mint['cookie_name'];
	$alt     = 'wordpress_logged_in_' . md5( $REF );
	$headers = 'Cookie: ' . $cn . '=' . $mint['cookie'] . '; ' . $alt . '=' . $mint['cookie'] . "\r\n" . 'X-WP-Nonce: ' . $mint['nonce'];

	$mk = function ( string $base ) use ( $headers ) {
		$ctx = stream_context_create( array(
			'ssl'  => array( 'verify_peer' => false, 'verify_peer_name' => false ),
			'http' => array( 'header' => $headers, 'ignore_errors' => true, 'timeout' => 10 ),
		) );
		return json_decode( (string) @file_get_contents( "$base/?rest_route=/wp/v2/users/me&context=edit", false, $ctx ), true );
	};
	$ref_me = $mk( $REF );
	$eng_me = $mk( $ENGINE );
	check( isset( $eng_me['email'], $eng_me['roles'], $eng_me['capabilities'] ), 'edit context exposes email, roles, capabilities' );
	$norm = json_decode( str_replace( $REF, $ENGINE, json_encode( $ref_me, JSON_UNESCAPED_SLASHES ) ), true );
	$d    = minn_test_diff( $norm, $eng_me );
	check( null === $d, 'edit-context user object matches the reference', (string) $d );
	check(
		( $eng_me['capabilities']['administrator'] ?? false ) === true
			&& ( $eng_me['capabilities']['manage_options'] ?? false ) === true,
		'capabilities map carries role name and primitives'
	);
}

echo "\n$pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
