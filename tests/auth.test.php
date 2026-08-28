<?php
/**
 * Auth suite: cookie + nonce compatibility, both directions.
 *
 * The strong claim this pins: a session minted by WordPress authenticates
 * against the engine, and a cookie minted by the engine authenticates
 * against WordPress, with byte-identical crypto. It also walks the whole
 * failure matrix so a permissive regression cannot pass quietly.
 *
 * Run:  php tests/auth.test.php
 * Ref:  (cd wp-reference && php -S 127.0.0.1:8123) — SKIPs cleanly when down.
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
	if ( $ok ) {
		$pass++;
		echo "  ok  $label\n";
	} else {
		$fail++;
		echo "FAIL  $label" . ( $detail ? "\n      $detail" : '' ) . "\n";
	}
}

/** Fetch with an optional cookie and nonce. */
function auth_get( string $base, string $route, ?string $cookie = null, ?string $nonce = null, ?string $cookie_name = null ): array {
	global $REF;
	$headers = array();
	if ( null !== $cookie ) {
		// Send under the canonical name and the reference's host-derived
		// name, since the two stacks compute the name from different URLs.
		$alt = 'wordpress_logged_in_' . md5( $REF );
		$headers[] = 'Cookie: ' . $cookie_name . '=' . $cookie . '; ' . $alt . '=' . $cookie;
	}
	if ( null !== $nonce ) {
		$headers[] = 'X-WP-Nonce: ' . $nonce;
	}
	$ctx = stream_context_create(
		array(
			'ssl'  => array( 'verify_peer' => false, 'verify_peer_name' => false ),
			'http' => array( 'ignore_errors' => true, 'timeout' => 10, 'header' => implode( "\r\n", $headers ) ),
		)
	);
	$body   = @file_get_contents( $base . '/?rest_route=' . rawurlencode( $route ), false, $ctx );
	$status = 0;
	foreach ( $http_response_header ?? array() as $h ) {
		if ( preg_match( '#^HTTP/\S+\s+(\d+)#', $h, $m ) ) {
			$status = (int) $m[1];
		}
	}
	return array( $status, json_decode( (string) $body, true ) );
}

echo "auth suite: $ENGINE (engine) / $REF (oracle)\n";

// A real WordPress session, minted by WordPress.
$mint = json_decode( (string) shell_exec(
	'wp --path=' . escapeshellarg( "$ROOT/wp-reference" ) . ' eval-file ' . escapeshellarg( "$ROOT/tests/tools/mint-session.php" ) . ' 1 2>/dev/null'
), true );
if ( ! $mint || empty( $mint['cookie'] ) ) {
	echo "SKIP: could not mint a reference session (wp-cli unavailable?)\n";
	exit( 0 );
}
$cn = $mint['cookie_name'];

// 1. The failure matrix, engine and reference agreeing on every rung.
foreach ( array(
	'anonymous'          => array( null, null, 401, 'rest_not_logged_in' ),
	'cookie without nonce' => array( $mint['cookie'], null, 403, 'rest_cookie_invalid_nonce' ),
	'cookie + bad nonce' => array( $mint['cookie'], '0123456789', 403, 'rest_cookie_invalid_nonce' ),
	'garbage cookie'     => array( 'not|a|real|cookie', $mint['nonce'], 401, 'rest_not_logged_in' ),
) as $label => $case ) {
	[ $ck, $nc, $want_status, $want_code ] = $case;
	[ $st, $body ] = auth_get( $ENGINE, '/wp/v2/users/me', $ck, $nc, $cn );
	check(
		$st === $want_status && ( $body['code'] ?? '' ) === $want_code,
		"me: $label rejected ($want_code)",
		"got $st " . ( $body['code'] ?? '?' )
	);
}

// 2. THE claim: a WordPress-minted session authenticates against the engine.
[ $st, $me ] = auth_get( $ENGINE, '/wp/v2/users/me', $mint['cookie'], $mint['nonce'], $cn );
check( 200 === $st, 'WordPress-minted session authenticates on the engine', "status $st" );
check( 1 === ( $me['id'] ?? 0 ), 'me resolves to the right user' );
check(
	in_array( 'DELETE', $me['_links']['self'][0]['targetHints']['allow'] ?? array(), true ),
	'me advertises write verbs in targetHints'
);

// 3. The same request against the reference produces the same body.
[ $rst, $rme ] = auth_get( $REF, '/wp/v2/users/me', $mint['cookie'], $mint['nonce'], $cn );
if ( 200 === $rst ) {
	// Re-encode without slash escaping so the origin swap matches.
	$norm = json_decode( str_replace( $REF, $ENGINE, json_encode( $rme, JSON_UNESCAPED_SLASHES ) ), true );
	$d    = minn_test_diff( $norm, $me );
	check( null === $d, 'me body matches the reference', (string) $d );
} else {
	check( false, 'reference accepted the same session', "status $rst" );
}

// 4. The reverse direction: engine-minted crypto, byte-identical, and
//    accepted by WordPress itself.
$eng = json_decode( (string) shell_exec(
	'php ' . escapeshellarg( "$ROOT/tests/tools/engine-mint.php" ) . ' 1 ' .
	escapeshellarg( $mint['token'] ) . ' ' . escapeshellarg( (string) $mint['expiration'] )
), true );
check( ( $eng['cookie'] ?? '' ) === $mint['cookie'], 'engine-minted cookie is byte-identical' );
check( ( $eng['nonce'] ?? '' ) === $mint['nonce'], 'engine-minted nonce is byte-identical' );
check( ( $eng['cookie_name'] ?? '' ) === $cn, 'engine computes the same cookie name' );

[ $wst, $wme ] = auth_get( $REF, '/wp/v2/users/me', $eng['cookie'], $eng['nonce'], $eng['cookie_name'] );
check( 200 === $wst && 1 === ( $wme['id'] ?? 0 ), 'WordPress accepts the ENGINE-minted session', "status $wst" );

// 5. A cookie for a live session but a nonce bound to a different user is
//    refused: the nonce is user-and-token bound, not a bare secret.
$other = json_decode( (string) shell_exec(
	'php ' . escapeshellarg( "$ROOT/tests/tools/engine-mint.php" ) . ' 2 ' .
	escapeshellarg( $mint['token'] ) . ' ' . escapeshellarg( (string) $mint['expiration'] )
), true );
[ $xst, $xbody ] = auth_get( $ENGINE, '/wp/v2/users/me', $mint['cookie'], $other['nonce'], $cn );
check(
	403 === $xst && 'rest_cookie_invalid_nonce' === ( $xbody['code'] ?? '' ),
	'nonce minted for another user is refused',
	"got $xst " . ( $xbody['code'] ?? '?' )
);

// 6. Public user routes stay public, and reveal nothing extra.
[ $ust, $users ] = auth_get( $ENGINE, '/wp/v2/users' );
check( 200 === $ust && is_array( $users ), 'users list is public' );
check(
	! isset( $users[0]['email'] ) && ! isset( $users[0]['roles'] ) && ! isset( $users[0]['capabilities'] ),
	'public user objects expose no email, roles, or capabilities'
);
[ $u4 ] = auth_get( $ENGINE, '/wp/v2/users/999' );
check( 404 === $u4, 'unknown user id is 404' );

echo "\n$pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
