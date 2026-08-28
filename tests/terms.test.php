<?php
/**
 * Term management: list parameters in parity, the editor's
 * tag-on-the-fly create flow, category CRUD with the hierarchy links,
 * and the refusal ladder — cross-stack in both directions.
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

function tm_mint( int $uid ): array {
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

function tm_fetch( string $base, string $query, ?array $mint, string $method = 'GET', ?string $body = null ): array {
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
	$raw    = @file_get_contents( "$base/?$query", false, $ctx );
	$status = 0;
	foreach ( $http_response_header ?? array() as $h ) {
		if ( preg_match( '#^HTTP/\S+\s+(\d+)#', $h, $m ) ) {
			$status = (int) $m[1];
		}
	}
	return array( $status, json_decode( (string) $raw, true ) );
}

function tm_norm( $x ) {
	global $ENGINE, $REF;
	if ( is_array( $x ) ) {
		return array_map( 'tm_norm', $x );
	}
	if ( is_string( $x ) ) {
		return str_replace( array( str_replace( '/', '\\/', $REF ), $REF ), $ENGINE, $x );
	}
	return $x;
}

function tm_parity( string $label, string $query, ?array $mint, string $method = 'GET', ?string $body = null ): void {
	global $ENGINE, $REF;
	[ $rs, $rb ] = tm_fetch( $REF, $query, $mint, $method, $body );
	[ $es, $eb ] = tm_fetch( $ENGINE, $query, $mint, $method, $body );
	if ( $rs !== $es ) {
		check( false, $label, "status $rs vs $es" );
		return;
	}
	$d = minn_test_diff( tm_norm( $rb ), tm_norm( $eb ) );
	check( null === $d, $label, (string) $d );
}

/** Remove suite terms (fixture ids are 1-3). */
function tm_cleanup(): void {
	global $ROOT;
	shell_exec(
		'cd ' . escapeshellarg( "$ROOT/wp-reference" ) .
		' && for tax in post_tag category; do for id in $(wp term list "$tax" --field=term_id 2>/dev/null); do [ "$id" -gt 3 ] && wp term delete "$tax" "$id" 2>/dev/null; done; done; true'
	);
}
register_shutdown_function( 'tm_cleanup' );
tm_cleanup();

echo "terms suite: $ENGINE (engine) / $REF (oracle)\n";

$admin  = tm_mint( 1 );
$author = tm_mint( 3 );
$QC     = 'rest_route=' . rawurlencode( '/wp/v2/categories' );
$QT     = 'rest_route=' . rawurlencode( '/wp/v2/tags' );

// 1. The app's list queries and capability-driven hints.
tm_parity( 'categories by count desc', $QC . '&per_page=100&orderby=count&order=desc&_fields=id,name,count', $admin );
tm_parity( 'tags search', $QT . '&search=eng', $admin );
tm_parity( 'tags include', $QT . '&include=2,3&_fields=id,name', $author );
tm_parity( 'category single (admin hints)', $QC . '%2F1', $admin );
tm_parity( 'category single (anonymous hints)', $QC . '%2F1', null );

// 2. The editor flow: an author creates a tag on the engine.
[ $st, $created ] = tm_fetch( $ENGINE, $QT, $author, 'POST', '{"name":"engine-made"}' );
check( 201 === $st && ! empty( $created['id'] ), 'author creates a tag through the engine' );
$tid = (int) ( $created['id'] ?? 0 );
[ $st, $back ] = tm_fetch( $REF, $QT . '%2F' . $tid, $author );
$d = minn_test_diff( tm_norm( $back ), tm_norm( $created ) );
check( 200 === $st && null === $d, 'WordPress reads the engine-made tag identically', (string) $d );
tm_parity( 'duplicate tag name refused (term_exists)', $QT, $admin, 'POST', '{"name":"engine-made"}' );
tm_parity( 'author cannot create categories', $QC, $author, 'POST', '{"name":"nope"}' );

// 3. Category hierarchy: engine create with a parent, then update.
[ $st, $cat ] = tm_fetch( $ENGINE, $QC, $admin, 'POST', '{"name":"Engine Child","parent":1,"description":"made by the engine"}' );
check( 201 === $st && 1 === ( $cat['parent'] ?? 0 ), 'engine creates a child category' );
$cid = (int) ( $cat['id'] ?? 0 );
[ $st, $back ] = tm_fetch( $REF, $QC . '%2F' . $cid, $admin );
$d = minn_test_diff( tm_norm( $back ), tm_norm( $cat ) );
check( 200 === $st && null === $d, 'WordPress reads it identically (up link included)', (string) $d );
[ $st, $b ] = tm_fetch( $REF, $QC . '%2F' . $cid, $admin, 'POST', '{"name":"Oracle Renamed"}' );
check( 200 === $st && 'Oracle Renamed' === ( $b['name'] ?? '' ), 'WordPress renames it' );
[ , $b ] = tm_fetch( $ENGINE, $QC . '%2F' . $cid, $admin );
check( 'Oracle Renamed' === ( $b['name'] ?? '' ), 'engine sees the rename' );

// 4. The delete ladder.
tm_parity( 'delete without force refused', $QC . '%2F' . $cid, $admin, 'DELETE' );
tm_parity( 'author cannot delete', $QT . '%2F' . $tid, $author, 'DELETE' );
tm_parity( 'default category cannot be deleted', $QC . '%2F1&force=true', $admin, 'DELETE' );
[ $st, $b ] = tm_fetch( $ENGINE, $QC . '%2F' . $cid . '&force=true', $admin, 'DELETE' );
check( 200 === $st && true === ( $b['deleted'] ?? null ) && 'Oracle Renamed' === ( $b['previous']['name'] ?? '' ), 'engine force-deletes the category' );
[ $st, $b ] = tm_fetch( $ENGINE, $QT . '%2F' . $tid . '&force=true', $admin, 'DELETE' );
check( 200 === $st && true === ( $b['deleted'] ?? null ), 'engine force-deletes the tag' );
[ $st ] = tm_fetch( $REF, $QT . '%2F' . $tid, $admin );
check( 404 === $st, 'WordPress confirms the tag is gone' );

echo "\n$pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
