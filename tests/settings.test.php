<?php
/**
 * wp/v2/settings: payload parity, the manage_options gate, and write
 * round trips proven cross-stack (engine writes read back through
 * WordPress and vice versa). Restores every touched option.
 *
 * Ref: (cd wp-reference && php -S 127.0.0.1:8123) — SKIPs cleanly when down.
 */

$ENGINE = 'https://minn-engine.localhost';
$REF    = 'http://127.0.0.1:8123';
$ROOT   = dirname( __DIR__ );

require_once __DIR__ . '/lib.php';

[ $ph ] = minn_test_fetch( "$REF/?rest_route=/wp/v2/posts", 3 );
if ( 200 !== $ph['status'] ) {
	echo "SKIP: reference WordPress not running at $REF (start: cd wp-reference && php -S 127.0.0.1:8123)\n";
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

function st_mint( int $uid ): array {
	global $ROOT;
	$mint = json_decode( (string) shell_exec(
		'wp --path=' . escapeshellarg( "$ROOT/wp-reference" ) . ' eval-file ' . escapeshellarg( "$ROOT/tests/tools/mint-session.php" ) . " $uid 2>/dev/null"
	), true );
	if ( ! $mint || empty( $mint['cookie'] ) ) {
		echo "SKIP: could not mint a reference session (wp-cli unavailable?)\n";
		exit( 0 );
	}
	return $mint;
}

function st_fetch( string $base, ?array $mint, string $method = 'GET', ?string $body = null ): array {
	global $REF;
	$headers = array();
	if ( $mint ) {
		$alt       = 'wordpress_logged_in_' . md5( $REF );
		$headers[] = 'Cookie: ' . $mint['cookie_name'] . '=' . $mint['cookie'] . '; ' . $alt . '=' . $mint['cookie'];
		$headers[] = 'X-WP-Nonce: ' . $mint['nonce'];
	}
	if ( null !== $body ) {
		$headers[] = 'Content-Type: application/json';
	}
	$ctx = stream_context_create(
		array(
			'ssl'  => array( 'verify_peer' => false, 'verify_peer_name' => false ),
			'http' => array(
				'ignore_errors' => true,
				'timeout'       => 10,
				'method'        => $method,
				'header'        => implode( "\r\n", $headers ),
				'content'       => $body ?? '',
			),
		)
	);
	$raw    = @file_get_contents( $base . '/?rest_route=' . rawurlencode( '/wp/v2/settings' ), false, $ctx );
	$status = 0;
	foreach ( $http_response_header ?? array() as $h ) {
		if ( preg_match( '#^HTTP/\S+\s+(\d+)#', $h, $m ) ) {
			$status = (int) $m[1];
		}
	}
	return array( $status, json_decode( (string) $raw, true ) );
}

function st_norm( $x ) {
	global $ENGINE, $REF;
	if ( is_array( $x ) ) {
		return array_map( 'st_norm', $x );
	}
	return is_string( $x ) ? str_replace( $REF, $ENGINE, $x ) : $x;
}

/** Restore the options this suite touches. */
function st_cleanup(): void {
	global $ROOT;
	shell_exec(
		'cd ' . escapeshellarg( "$ROOT/wp-reference" ) .
		' && wp option update blogname "Minn Engine" 2>/dev/null'
		. ' && wp option update posts_per_page 10 2>/dev/null'
		. ' && wp option update use_smilies 1 2>/dev/null'
		. ' && wp option update blogdescription "Indistinguishable at the seams. Radically simpler inside." 2>/dev/null; true'
	);
}
register_shutdown_function( 'st_cleanup' );
st_cleanup();

echo "settings suite: $ENGINE (engine) / $REF (oracle)\n";

$admin  = st_mint( 1 );
$editor = st_mint( 2 );

// 1. Payload and gate parity.
[ $rs, $rb ] = st_fetch( $REF, $admin );
[ $es, $eb ] = st_fetch( $ENGINE, $admin );
$d = minn_test_diff( st_norm( $rb ), st_norm( $eb ) );
check( 200 === $rs && $rs === $es && null === $d, 'settings payload matches', (string) $d );

foreach ( array( 'editor' => $editor, 'anonymous' => null ) as $who => $mint ) {
	[ $rs, $rb ] = st_fetch( $REF, $mint );
	[ $es, $eb ] = st_fetch( $ENGINE, $mint );
	$d = minn_test_diff( $rb, $eb );
	check( $rs === $es && null === $d, "refused identically ($who)", "status $rs vs $es; " . (string) $d );
}

// 2. Engine writes; WordPress reads them back.
[ $st, $b ] = st_fetch( $ENGINE, $admin, 'POST', json_encode( array(
	'title'          => 'Engine-titled site',
	'posts_per_page' => 7,
	'use_smilies'    => false,
	'description'    => 'Set by the engine',
) ) );
// Boolean false is stored as '' and then served as null — core's own
// round-trip quirk, matched exactly.
check( 200 === $st && 'Engine-titled site' === ( $b['title'] ?? '' ) && 7 === ( $b['posts_per_page'] ?? 0 ) && array_key_exists( 'use_smilies', $b ) && null === $b['use_smilies'], 'engine write returns the new payload' );
[ , $b ] = st_fetch( $REF, $admin );
check(
	'Engine-titled site' === ( $b['title'] ?? '' ) && 7 === ( $b['posts_per_page'] ?? 0 )
	&& array_key_exists( 'use_smilies', $b ) && null === $b['use_smilies'] && 'Set by the engine' === ( $b['description'] ?? '' ),
	'WordPress reads the engine-written options'
);

// 3. WordPress writes; the engine reads them back.
[ $st ] = st_fetch( $REF, $admin, 'PUT', '{"title":"Oracle-titled site","use_smilies":true}' );
check( 200 === $st, 'WordPress writes settings' );
[ , $b ] = st_fetch( $ENGINE, $admin );
check( 'Oracle-titled site' === ( $b['title'] ?? '' ) && true === ( $b['use_smilies'] ?? false ), 'engine reads the WordPress-written options' );

// 4. Unregistered keys are ignored on both stacks.
[ $rs, $rb ] = st_fetch( $REF, $admin, 'POST', '{"not_a_setting":"x"}' );
[ $es, $eb ] = st_fetch( $ENGINE, $admin, 'POST', '{"not_a_setting":"x"}' );
$d = minn_test_diff( st_norm( $rb ), st_norm( $eb ) );
check( $rs === $es && null === $d, 'unregistered keys ignored identically', (string) $d );

// 5. Writers below manage_options are refused identically.
[ $rs, $rb ] = st_fetch( $REF, $editor, 'POST', '{"title":"nope"}' );
[ $es, $eb ] = st_fetch( $ENGINE, $editor, 'POST', '{"title":"nope"}' );
$d = minn_test_diff( $rb, $eb );
check( $rs === $es && null === $d, 'editor write refused identically', (string) $d );

// 6. Restore and confirm the fixture state.
st_cleanup();
[ , $b ] = st_fetch( $ENGINE, $admin );
check( 'Minn Engine' === ( $b['title'] ?? '' ) && 10 === ( $b['posts_per_page'] ?? 0 ), 'fixture options restored' );

echo "\n$pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
