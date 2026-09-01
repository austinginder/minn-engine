<?php
/**
 * Extra post types declared in minn.json: they join wp/v2/types and get a
 * collection at their rest_base, at live parity with a WordPress plugin
 * that register_post_type's the same slug. Ref: php -S 127.0.0.1:8123.
 */

$ENGINE = 'https://minn.localhost';
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

function dt_wp( string $command ): string {
	global $ROOT;
	return trim( (string) shell_exec( 'wp --path=' . escapeshellarg( minn_test_site_root() . '/wp-reference' ) . " $command 2>/dev/null" ) );
}

function dt_mint( int $uid ): array {
	global $ROOT;
	$mint = json_decode( (string) shell_exec(
		'wp --path=' . escapeshellarg( minn_test_site_root() . '/wp-reference' ) . ' eval-file ' . escapeshellarg( "$ROOT/tests/tools/mint-session.php" ) . " $uid 2>/dev/null"
	), true );
	if ( ! $mint || empty( $mint['cookie'] ) ) {
		echo "SKIP: could not mint a session\n";
		exit( 0 );
	}
	return $mint;
}

function dt_fetch( string $base, string $query, ?array $mint, string $method = 'GET', ?string $body = null ): array {
	$headers = array();
	if ( $mint ) {
		global $REF;
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
	$raw    = @file_get_contents( "$base/?$query", false, $ctx );
	$status = 0;
	foreach ( $http_response_header ?? array() as $h ) {
		if ( preg_match( '#^HTTP/\S+\s+(\d+)#', $h, $m ) ) {
			$status = (int) $m[1];
		}
	}
	return array( $status, json_decode( (string) $raw, true ) );
}

function dt_norm( $x ) {
	global $ENGINE, $REF;
	if ( is_array( $x ) ) {
		return array_map( 'dt_norm', $x );
	}
	if ( is_string( $x ) ) {
		return str_replace( array( str_replace( '/', '\\/', $REF ), $REF ), $ENGINE, $x );
	}
	return $x;
}

function dt_parity( string $label, string $query, ?array $mint = null, string $method = 'GET', ?string $body = null ): void {
	global $ENGINE, $REF;
	[ $rs, $rb ] = dt_fetch( $REF, $query, $mint, $method, $body );
	[ $es, $eb ] = dt_fetch( $ENGINE, $query, $mint, $method, $body );
	if ( $rs !== $es ) {
		check( false, $label, "status $rs vs $es " . json_encode( $eb ) );
		return;
	}
	$d = minn_test_diff( dt_norm( $rb ), dt_norm( $eb ) );
	check( null === $d, $label, (string) $d );
}

$previous = dt_wp( 'option get minn_active_extensions' );
register_shutdown_function( static function () use ( $previous ): void {
	dt_wp( 'plugin deactivate minn-test-types' );
	dt_wp( 'option update minn_active_extensions ' . escapeshellarg( $previous !== '' ? $previous : '[]' ) );
	foreach ( preg_split( '/\s+/', dt_wp( 'post list --post_type=zz_note --format=ids' ) ) ?: array() as $id ) {
		if ( (int) $id > 0 ) {
			dt_wp( 'post delete ' . (int) $id . ' --force' );
		}
	}
} );

echo "declared-types suite: $ENGINE (engine) / $REF (oracle)\n";

dt_wp( 'plugin activate minn-test-types' );
dt_wp( 'option update minn_active_extensions \'["minn-block-visibility","minn-test-types"]\'' );
$admin = dt_mint( 1 );

dt_parity( 'types list includes zz_note', 'rest_route=' . rawurlencode( '/wp/v2/types' ) );
dt_parity( 'types single zz_note', 'rest_route=' . rawurlencode( '/wp/v2/types/zz_note' ) );
dt_parity( 'empty zz-note collection', 'rest_route=' . rawurlencode( '/wp/v2/zz-note' ) );

[ $st, $created ] = dt_fetch( $ENGINE, 'rest_route=' . rawurlencode( '/wp/v2/zz-note' ), $admin, 'POST', json_encode( array( 'title' => 'A note', 'status' => 'publish', 'content' => 'Hello note' ) ) );
check( 201 === $st && ( $created['type'] ?? '' ) === 'zz_note', 'engine creates a zz_note (201)', json_encode( $created ) );
$nid = (int) ( $created['id'] ?? 0 );
[ $st, $oracle_read ] = dt_fetch( $REF, 'rest_route=' . rawurlencode( '/wp/v2/zz-note/' . $nid ) . '&context=edit', $admin );
check( 200 === $st, 'WordPress can read the engine-written note' );
$d = minn_test_diff( dt_norm( $oracle_read ), dt_norm( $created ) );
check( null === $d, 'create note equals the WordPress edit read-back', (string) $d );

dt_parity( 'public note view', 'rest_route=' . rawurlencode( '/wp/v2/zz-note/' . $nid ) );
check( str_contains( (string) ( $created['link'] ?? '' ), '/zz_note/' ), 'note permalink uses the type slug', (string) ( $created['link'] ?? '' ) );

[ $st, $wp_note ] = dt_fetch( $REF, 'rest_route=' . rawurlencode( '/wp/v2/zz-note' ), $admin, 'POST', json_encode( array( 'title' => 'Oracle note', 'status' => 'publish' ) ) );
check( 201 === $st && ! empty( $wp_note['id'] ), 'WordPress creates a zz_note' );
$wid = (int) ( $wp_note['id'] ?? 0 );
[ $st, $engine_read ] = dt_fetch( $ENGINE, 'rest_route=' . rawurlencode( '/wp/v2/zz-note/' . $wid ) . '&context=edit', $admin );
check( 200 === $st, 'engine reads the WordPress-written note' );
$d = minn_test_diff( dt_norm( $wp_note ), dt_norm( $engine_read ) );
check( null === $d, 'WordPress create note equals the engine edit read-back', (string) $d );

dt_fetch( $ENGINE, 'rest_route=' . rawurlencode( '/wp/v2/zz-note/' . $nid ) . '&force=true', $admin, 'DELETE' );
dt_fetch( $ENGINE, 'rest_route=' . rawurlencode( '/wp/v2/zz-note/' . $wid ) . '&force=true', $admin, 'DELETE' );
[ $st ] = dt_fetch( $REF, 'rest_route=' . rawurlencode( '/wp/v2/zz-note/' . $nid ), $admin );
check( 404 === $st, 'force-delete removes the note on WordPress too' );

echo "\n$pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
