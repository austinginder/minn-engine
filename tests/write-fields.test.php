<?php
/**
 * Extended write fields: scheduling, sticky, format, author, password
 * (including its conflicts and protected reads), featured media,
 * footnotes meta, page attributes — every write proven by reading it
 * back through real WordPress.
 *
 * Ref: (cd wp-reference && php -S 127.0.0.1:8123) — SKIPs cleanly when down.
 */

$ENGINE = 'https://minn.localhost';
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

function wf_mint( int $uid ): array {
	global $ROOT;
	$mint = json_decode( (string) shell_exec(
		'wp --path=' . escapeshellarg( minn_test_site_root() . '/wp-reference' ) . ' eval-file ' . escapeshellarg( "$ROOT/tests/tools/mint-session.php" ) . " $uid 2>/dev/null"
	), true );
	if ( ! $mint || empty( $mint['cookie'] ) ) {
		echo "SKIP: could not mint a reference session (wp-cli unavailable?)\n";
		exit( 0 );
	}
	return $mint;
}

function wf_fetch( string $base, string $query, ?array $mint, string $method = 'GET', ?string $body = null, array $extra = array() ): array {
	global $REF;
	$headers = $extra;
	if ( $mint ) {
		$alt       = 'wordpress_logged_in_' . md5( $REF );
		$headers[] = 'Cookie: ' . $mint['cookie_name'] . '=' . $mint['cookie'] . '; ' . $alt . '=' . $mint['cookie'];
		$headers[] = 'X-WP-Nonce: ' . $mint['nonce'];
	}
	if ( null !== $body && ! preg_grep( '/^Content-Type:/i', $headers ) ) {
		$headers[] = 'Content-Type: application/json';
	}
	$ctx = stream_context_create(
		array(
			'ssl'  => array( 'verify_peer' => false, 'verify_peer_name' => false ),
			'http' => array(
				'ignore_errors' => true,
				'timeout'       => 15,
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

function wf_norm( $x ) {
	global $ENGINE, $REF;
	if ( is_array( $x ) ) {
		return array_map( 'wf_norm', $x );
	}
	if ( is_string( $x ) ) {
		return str_replace( array( str_replace( '/', '\\/', $REF ), $REF ), $ENGINE, $x );
	}
	return $x;
}

function wf_parity( string $label, string $query, ?array $mint, string $method = 'GET', ?string $body = null ): void {
	global $ENGINE, $REF;
	[ $rs, $rb ] = wf_fetch( $REF, $query, $mint, $method, $body );
	[ $es, $eb ] = wf_fetch( $ENGINE, $query, $mint, $method, $body );
	if ( $rs !== $es ) {
		check( false, $label, "status $rs vs $es" );
		return;
	}
	$d = minn_test_diff( wf_norm( $rb ), wf_norm( $eb ) );
	check( null === $d, $label, (string) $d );
}

/** Suite artifacts: posts beyond the fixture set, attachments, sticky state. */
function wf_cleanup(): void {
$battery = (array) json_decode((string) @file_get_contents($GLOBALS['ROOT'] . "/contracts/fixtures/blocks/manifest.json"), true);
$keep    = implode(',', array_merge(array_values((array) ($battery['posts'] ?? [])), [(int) ($battery['image'] ?? 0)]));
	global $ROOT;
	shell_exec(
		'cd ' . escapeshellarg( minn_test_site_root() . '/wp-reference' ) .
		' && for id in $(wp post list --post_type=post,page,attachment --post_status=any --field=ID --post__not_in=' . $keep . ' 2>/dev/null); do [ "$id" -gt 29 ] && wp post delete "$id" --force 2>/dev/null; done'
		. ' && wp option update sticky_posts \'a:1:{i:0;i:5;}\' --format=plaintext 2>/dev/null; true'
	);
	shell_exec( 'cd ' . escapeshellarg( minn_test_site_root() . '/wp-reference' ) . " && wp db query \"UPDATE wp_options SET option_value = 'a:1:{i:0;i:5;}' WHERE option_name = 'sticky_posts'\" 2>/dev/null" );
}
register_shutdown_function( 'wf_cleanup' );
wf_cleanup();

echo "write-fields suite: $ENGINE (engine) / $REF (oracle)\n";

$admin  = wf_mint( 1 );
$author = wf_mint( 3 );
$QP     = 'rest_route=' . rawurlencode( '/wp/v2/posts' );
$QG     = 'rest_route=' . rawurlencode( '/wp/v2/pages' );
$QM     = 'rest_route=' . rawurlencode( '/wp/v2/media' );

$ids = array();

// 1. Scheduling: publish with a future date becomes 'future'.
[ $st, $b ] = wf_fetch( $ENGINE, $QP, $admin, 'POST', '{"title":"Scheduled by engine","status":"publish","date":"2027-01-15T09:30:00","content":"future"}' );
check( 201 === $st && 'future' === ( $b['status'] ?? '' ) && '2027-01-15T09:30:00' === ( $b['date'] ?? '' ), 'engine schedules a future publish' );
$sid = $ids[] = (int) ( $b['id'] ?? 0 );
[ $st, $back ] = wf_fetch( $REF, $QP . '%2F' . $sid . '&context=edit', $admin );
$d = minn_test_diff( wf_norm( $back ), wf_norm( $b ) );
check( 200 === $st && null === $d, 'WordPress reads the scheduled post identically', (string) $d );

// 2. Sticky rides the serialized option, both directions.
[ $st, $b ] = wf_fetch( $ENGINE, $QP, $admin, 'POST', '{"title":"Sticky probe","status":"publish","sticky":true,"content":"s"}' );
check( 201 === $st && true === ( $b['sticky'] ?? false ), 'engine creates a sticky post' );
$kid = $ids[] = (int) ( $b['id'] ?? 0 );
[ , $back ] = wf_fetch( $REF, $QP . '%2F' . $kid . '&context=edit', $admin );
check( true === ( $back['sticky'] ?? false ), 'WordPress sees the engine-written sticky option' );
[ $st, $b ] = wf_fetch( $REF, $QP . '%2F' . $kid, $admin, 'POST', '{"sticky":false}' );
check( 200 === $st && false === ( $b['sticky'] ?? true ), 'WordPress unsticks it' );
[ , $b ] = wf_fetch( $ENGINE, $QP . '%2F' . $kid . '&context=edit', $admin );
check( false === ( $b['sticky'] ?? true ), 'engine sees the unstick' );

// 3. Format, author, footnotes in one create.
[ $st, $b ] = wf_fetch( $ENGINE, $QP, $admin, 'POST', '{"title":"Sink by engine","status":"publish","format":"aside","author":2,"meta":{"footnotes":"[]"},"comment_status":"closed","content":"sink"}' );
check( 201 === $st && 'aside' === ( $b['format'] ?? '' ) && 2 === ( $b['author'] ?? 0 ) && 'closed' === ( $b['comment_status'] ?? '' ), 'engine writes format, author, comment_status' );
$xid = $ids[] = (int) ( $b['id'] ?? 0 );
[ $st, $back ] = wf_fetch( $REF, $QP . '%2F' . $xid . '&context=edit', $admin );
$d = minn_test_diff( wf_norm( $back ), wf_norm( $b ) );
check( 200 === $st && null === $d, 'WordPress reads the sink post identically (footnotes included)', (string) $d );
wf_parity( 'author cannot reassign authorship', $QP . '%2F' . $xid, $author, 'POST', '{"author":3}' );

// 4. Password: protected reads and the sticky conflict.
[ $st, $b ] = wf_fetch( $ENGINE, $QP, $admin, 'POST', '{"title":"PW by engine","status":"publish","password":"pw-1","content":"<p>secret</p>"}' );
check( 201 === $st && 'pw-1' === ( $b['password'] ?? '' ) && true === ( $b['content']['protected'] ?? false ), 'engine creates a password post' );
$pid = $ids[] = (int) ( $b['id'] ?? 0 );
wf_parity( 'anonymous view is protected identically', $QP . '%2F' . $pid, null );
wf_parity( 'sticky+password refused identically', $QP, $admin, 'POST', '{"title":"x","sticky":true,"password":"y","content":"z"}' );

// 5. Featured media: an engine upload becomes the thumbnail.
$img = sys_get_temp_dir() . '/wf-thumb.png';
$im  = imagecreatetruecolor( 640, 480 );
imagefilledrectangle( $im, 0, 0, 639, 479, imagecolorallocate( $im, 20, 160, 90 ) );
imagepng( $im, $img );
imagedestroy( $im );
[ $st, $media ] = wf_fetch( $ENGINE, $QM, $admin, 'POST', (string) file_get_contents( $img ), array( 'Content-Type: image/png', 'Content-Disposition: attachment; filename="wf-thumb.png"' ) );
check( 201 === $st && ! empty( $media['id'] ), 'engine uploads the thumbnail' );
$mid = (int) ( $media['id'] ?? 0 );
[ $st, $b ] = wf_fetch( $ENGINE, $QP . '%2F' . $xid, $admin, 'POST', json_encode( array( 'featured_media' => $mid ) ) );
check( 200 === $st && $mid === ( $b['featured_media'] ?? 0 ), 'engine sets featured media' );
[ $st, $back ] = wf_fetch( $REF, $QP . '%2F' . $xid . '&context=edit', $admin );
$d = minn_test_diff( wf_norm( $back ), wf_norm( $b ) );
check( 200 === $st && null === $d, 'WordPress reads the featured link identically', (string) $d );

// 6. Page attributes.
[ $st, $b ] = wf_fetch( $ENGINE, $QG, $admin, 'POST', '{"title":"Child by engine","status":"publish","parent":2,"menu_order":7,"content":"child"}' );
check( 201 === $st && 2 === ( $b['parent'] ?? 0 ) && 7 === ( $b['menu_order'] ?? 0 ), 'engine creates a child page with menu order' );
$gid = $ids[] = (int) ( $b['id'] ?? 0 );
[ $st, $back ] = wf_fetch( $REF, $QG . '%2F' . $gid . '&context=edit', $admin );
$d = minn_test_diff( wf_norm( $back ), wf_norm( $b ) );
check( 200 === $st && null === $d, 'WordPress reads the child page identically', (string) $d );

// 7. Clean up through the engine, fixture state confirmed by the suites that follow.
foreach ( array_merge( $ids, array( $mid ) ) as $cleanup_id ) {
	$route = $cleanup_id === $mid ? $QM : ( $cleanup_id === $gid ? $QG : $QP );
	wf_fetch( $ENGINE, $route . '%2F' . $cleanup_id . '&force=true', $admin, 'DELETE' );
}
[ , $b ] = wf_fetch( $ENGINE, $QP . '%2F5', $admin );
check( true === ( $b['sticky'] ?? false ), 'fixture sticky state intact (post 5)' );

echo "\n$pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
