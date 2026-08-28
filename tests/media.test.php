<?php
/**
 * wp/v2/media: reads in live parity with the reference, uploads over both
 * transports with GD sub-sizes, the serialized metadata blob proven
 * cross-stack (WordPress reads engine uploads byte-identically and vice
 * versa), field edits, and force delete including the files.
 *
 * Ref: (cd wp-reference && php -S 127.0.0.1:8123) — SKIPs cleanly when down.
 * The uploads root is SHARED (wp-reference/wp-content/uploads is a symlink
 * into public/wp-content/uploads), mirroring the shared database.
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

function md_mint( int $uid ): array {
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

function md_headers( ?array $mint, array $extra = array() ): array {
	global $REF;
	$headers = $extra;
	if ( $mint ) {
		$alt       = 'wordpress_logged_in_' . md5( $REF );
		$headers[] = 'Cookie: ' . $mint['cookie_name'] . '=' . $mint['cookie'] . '; ' . $alt . '=' . $mint['cookie'];
		$headers[] = 'X-WP-Nonce: ' . $mint['nonce'];
	}
	return $headers;
}

function md_fetch( string $base, string $query, ?array $mint, string $method = 'GET', ?string $body = null, array $extra_headers = array() ): array {
	if ( null !== $body && ! preg_grep( '/^Content-Type:/i', $extra_headers ) ) {
		$extra_headers[] = 'Content-Type: application/json';
	}
	$headers = md_headers( $mint, $extra_headers );
	$ctx     = stream_context_create(
		array(
			'ssl'  => array( 'verify_peer' => false, 'verify_peer_name' => false ),
			'http' => array(
				'ignore_errors' => true,
				'timeout'       => 30,
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

/** Raw-binary upload (Content-Disposition transport). */
function md_upload( string $base, array $mint, string $path, string $filename ): array {
	return md_fetch(
		$base,
		'rest_route=' . rawurlencode( '/wp/v2/media' ),
		$mint,
		'POST',
		(string) file_get_contents( $path ),
		array( 'Content-Type: image/png', 'Content-Disposition: attachment; filename="' . $filename . '"' )
	);
}

function md_norm( $x ) {
	global $ENGINE, $REF;
	if ( is_array( $x ) ) {
		return array_map( 'md_norm', $x );
	}
	if ( is_string( $x ) ) {
		return str_replace( array( str_replace( '/', '\\/', $REF ), $REF ), $ENGINE, $x );
	}
	return $x;
}

function md_parity( string $label, string $query, ?array $mint, string $method = 'GET', ?string $body = null ): void {
	global $ENGINE, $REF;
	[ $rs, $rb ] = md_fetch( $REF, $query, $mint, $method, $body );
	[ $es, $eb ] = md_fetch( $ENGINE, $query, $mint, $method, $body );
	if ( $rs !== $es ) {
		check( false, $label, "status $rs vs $es" );
		return;
	}
	$d = minn_test_diff( md_norm( $rb ), md_norm( $eb ) );
	check( null === $d, $label, (string) $d );
}

/** Every attachment on this fixture database is a suite artifact. */
function md_cleanup(): void {
$battery = (array) json_decode((string) @file_get_contents($GLOBALS['ROOT'] . "/contracts/fixtures/blocks/manifest.json"), true);
$keep    = implode(',', array_merge(array_values((array) ($battery['posts'] ?? [])), [(int) ($battery['image'] ?? 0)]));
	global $ROOT;
	shell_exec(
		'cd ' . escapeshellarg( "$ROOT/wp-reference" ) .
		' && ids=$(wp post list --post_type=attachment --format=ids --post__not_in=' . $keep . ' 2>/dev/null); [ -n "$ids" ] && wp post delete $ids --force 2>/dev/null; true'
	);
}
register_shutdown_function( 'md_cleanup' );
md_cleanup();

echo "media suite: $ENGINE (engine) / $REF (oracle)\n";

$admin  = md_mint( 1 );
$author = md_mint( 3 );
$Q      = 'rest_route=' . rawurlencode( '/wp/v2/media' );

// A deterministic probe image, larger than every default sub-size.
$probe = sys_get_temp_dir() . '/minn-media-probe.png';
$im    = imagecreatetruecolor( 1200, 800 );
imagefilledrectangle( $im, 0, 0, 1199, 799, imagecolorallocate( $im, 40, 80, 200 ) );
imagefilledellipse( $im, 600, 400, 500, 300, imagecolorallocate( $im, 240, 200, 40 ) );
imagepng( $im, $probe );
imagedestroy( $im );

// 1. The oracle uploads one; every read surface must agree on it.
[ $st, $wp_up ] = md_upload( $REF, $admin, $probe, 'oracle-probe.png' );
check( 201 === $st && ! empty( $wp_up['id'] ), 'WordPress uploads the probe' );
$oid = (int) ( $wp_up['id'] ?? 0 );

md_parity( 'single view (anonymous)', $Q . '%2F' . $oid, null );
md_parity( 'single edit context (admin)', $Q . '%2F' . $oid . '&context=edit', $admin );
md_parity( 'list (admin)', $Q, $admin );
md_parity( 'list with _fields', $Q . '&_fields=id,title,mime_type,source_url,media_details,alt_text', $admin );
md_parity( 'single invalid id', $Q . '%2F999999', $admin );
md_parity( 'edit context refused (anonymous)', $Q . '&context=edit', null );

// 2. The engine uploads over BOTH transports; WordPress reads each back.
[ $st, $bin_up ] = md_upload( $ENGINE, $admin, $probe, 'engine-binary.png' );
check( 201 === $st && ! empty( $bin_up['id'] ), 'engine accepts the raw-binary transport' );
$bid = (int) ( $bin_up['id'] ?? 0 );
[ $st, $back ] = md_fetch( $REF, $Q . '%2F' . $bid . '&context=edit', $admin );
$d = minn_test_diff( md_norm( $back ), md_norm( $bin_up ) );
check( 200 === $st && null === $d, 'WordPress reads the engine upload byte-identically', (string) $d );

$sizes = $bin_up['media_details']['sizes'] ?? array();
check(
	array( 300, 200 ) === array( $sizes['medium']['width'] ?? 0, $sizes['medium']['height'] ?? 0 )
	&& array( 1024, 683 ) === array( $sizes['large']['width'] ?? 0, $sizes['large']['height'] ?? 0 )
	&& array( 150, 150 ) === array( $sizes['thumbnail']['width'] ?? 0, $sizes['thumbnail']['height'] ?? 0 )
	&& array( 768, 512 ) === array( $sizes['medium_large']['width'] ?? 0, $sizes['medium_large']['height'] ?? 0 ),
	'sub-size ladder matches core (constrain math included)',
	json_encode( array_map( static fn( $s ) => array( $s['width'], $s['height'] ), $sizes ) )
);
$updir = dirname( __DIR__ ) . '/public/wp-content/uploads/' . ( $bin_up['media_details']['file'] ?? '' );
check( is_file( dirname( $updir ) . '/' . ( $sizes['medium']['file'] ?? '' ) ), 'generated files land in the shared uploads root' );

// A multipart upload rides the same path the Minn Admin app uses.
$boundary = 'minnBoundary' . bin2hex( random_bytes( 6 ) );
$payload  = "--$boundary\r\nContent-Disposition: form-data; name=\"file\"; filename=\"engine-multipart.png\"\r\nContent-Type: image/png\r\n\r\n"
	. file_get_contents( $probe ) . "\r\n--$boundary--\r\n";
[ $st, $mp_up ] = md_fetch( $ENGINE, $Q, $admin, 'POST', $payload, array( "Content-Type: multipart/form-data; boundary=$boundary" ) );
check( 201 === $st && ! empty( $mp_up['id'] ), 'engine accepts the multipart transport (the app\'s path)' );
$mid = (int) ( $mp_up['id'] ?? 0 );
[ $st, $back ] = md_fetch( $REF, $Q . '%2F' . $mid . '&context=edit', $admin );
$d = minn_test_diff( md_norm( $back ), md_norm( $mp_up ) );
check( 200 === $st && null === $d, 'multipart upload reads back identically too', (string) $d );

// 3. Field edits round-trip across stacks.
[ $st, $b ] = md_fetch( $ENGINE, $Q . '%2F' . $bid, $admin, 'POST', json_encode( array( 'alt_text' => 'Engine alt', 'title' => 'Engine title', 'caption' => 'A caption' ) ) );
check( 200 === $st && 'Engine alt' === ( $b['alt_text'] ?? '' ), 'engine edits alt/title/caption' );
[ , $b ] = md_fetch( $REF, $Q . '%2F' . $bid . '&context=edit', $admin );
check(
	'Engine alt' === ( $b['alt_text'] ?? '' ) && 'Engine title' === ( $b['title']['raw'] ?? '' ) && 'A caption' === ( $b['caption']['raw'] ?? '' ),
	'WordPress sees the engine edits'
);
[ $st, $b ] = md_fetch( $REF, $Q . '%2F' . $bid, $admin, 'POST', '{"alt_text":"Oracle alt"}' );
check( 200 === $st, 'WordPress edits the alt text' );
[ , $b ] = md_fetch( $ENGINE, $Q . '%2F' . $bid . '&context=edit', $admin );
check( 'Oracle alt' === ( $b['alt_text'] ?? '' ), 'engine sees the WordPress edit' );
md_parity( 'edited object stays at parity', $Q . '%2F' . $bid . '&context=edit', $admin );

// 4. Deletes: trash unsupported, force removes row AND files.
md_parity( 'delete without force refused (501)', $Q . '%2F' . $mid, $admin, 'DELETE' );
$files = array_map(
	static fn( $s ) => dirname( $updir ) . '/' . $s['file'],
	$bin_up['media_details']['sizes'] ?? array()
);
[ $st, $b ] = md_fetch( $ENGINE, $Q . '%2F' . $bid . '&force=true', $admin, 'DELETE' );
check( 200 === $st && true === ( $b['deleted'] ?? null ) && 'Engine title' === ( $b['previous']['title']['raw'] ?? '' ), 'engine force-deletes with previous' );
[ $st ] = md_fetch( $REF, $Q . '%2F' . $bid, $admin );
check( 404 === $st, 'WordPress confirms the attachment is gone' );
$gone = true;
foreach ( $files as $f ) {
	if ( 'full' !== basename( $f ) && is_file( $f ) ) {
		$gone = false;
	}
}
check( $gone && ! is_file( $updir ), 'force delete removed the files on disk' );

// 5. Refusals.
md_parity( 'anonymous cannot upload', $Q, null, 'POST', '{}' );
[ $st, $b ] = md_upload( $ENGINE, $author, $probe, 'author-upload.png' );
check( 201 === $st && 3 === (int) ( $b['author'] ?? 0 ), 'author may upload (upload_files)' );

echo "\n$pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
