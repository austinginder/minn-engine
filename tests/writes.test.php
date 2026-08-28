<?php
/**
 * Writes suite: create, update, delete for posts, gated by capabilities.
 *
 * The claim: a write issued to the engine changes the shared database, is
 * visible to WordPress immediately, and returns the edit-context object
 * WordPress returns. The engine and the reference are diffed on the same
 * operations, and the capability refusals match code for code.
 *
 * Run:  php tests/writes.test.php
 * Ref:  (cd wp-reference && php -S 127.0.0.1:8123) — SKIPs when down.
 *
 * Every post this suite creates is force-deleted in a shutdown handler so the
 * read fixtures never drift.
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
$created = array();
function check( bool $ok, string $label, string $detail = '' ): void {
	global $pass, $fail;
	if ( $ok ) { $pass++; echo "  ok  $label\n"; }
	else { $fail++; echo "FAIL  $label" . ( $detail ? "\n      $detail" : '' ) . "\n"; }
}

// Mint sessions for admin (1) and author (3).
function mint( int $uid ): array {
	global $ROOT;
	return json_decode( (string) shell_exec(
		'wp --path=' . escapeshellarg( "$ROOT/wp-reference" ) . ' eval-file '
		. escapeshellarg( "$ROOT/tests/tools/mint-session.php" ) . ' ' . $uid . ' 2>/dev/null'
	), true );
}
$admin  = mint( 1 );
$author = mint( 3 );

/** HTTP with method, optional auth session, and JSON body. */
function req( string $base, string $method, string $route, ?array $session = null, ?array $body = null ): array {
	global $REF;
	$headers = array( 'Content-Type: application/json' );
	if ( $session ) {
		$alt       = 'wordpress_logged_in_' . md5( $REF );
		$headers[] = 'Cookie: ' . $session['cookie_name'] . '=' . $session['cookie'] . '; ' . $alt . '=' . $session['cookie'];
		$headers[] = 'X-WP-Nonce: ' . $session['nonce'];
	}
	$opts = array(
		'ssl'  => array( 'verify_peer' => false, 'verify_peer_name' => false ),
		'http' => array(
			'method'        => $method,
			'header'        => implode( "\r\n", $headers ),
			'ignore_errors' => true,
			'timeout'       => 10,
		),
	);
	if ( null !== $body ) {
		$opts['http']['content'] = json_encode( $body );
	}
	$ctx = stream_context_create( $opts );
	// Keep any &-args (force=true) as real query params, not part of the
	// rest_route value.
	[ $path, $query ] = array_pad( explode( '&', $route, 2 ), 2, '' );
	$url  = "$base/index.php?rest_route=" . rawurlencode( $path ) . ( '' !== $query ? "&$query" : '' );
	$resp = @file_get_contents( $url, false, $ctx );
	$code = 0;
	$loc  = null;
	foreach ( $http_response_header ?? array() as $h ) {
		if ( preg_match( '#^HTTP/\S+\s+(\d+)#', $h, $m ) ) { $code = (int) $m[1]; }
		if ( stripos( $h, 'Location:' ) === 0 ) { $loc = trim( substr( $h, 9 ) ); }
	}
	return array( $code, json_decode( (string) $resp, true ), $loc );
}

echo "writes suite: $ENGINE (engine) vs $REF (oracle)\n";

// --- 1. Create refused without auth, with the right code. ---
[ $c1 ] = req( $ENGINE, 'POST', '/wp/v2/posts', null, array( 'title' => 'nope' ) );
[ $c1r, $b1 ] = req( $ENGINE, 'POST', '/wp/v2/posts', null, array( 'title' => 'nope' ) );
check( 401 === $c1r && 'rest_cannot_create' === ( $b1['code'] ?? '' ), 'create without auth is 401 rest_cannot_create', "got $c1r " . ( $b1['code'] ?? '?' ) );

// --- 2. Admin creates a draft; the DB row exists and WordPress sees it. ---
[ $cc, $created_post, $loc ] = req( $ENGINE, 'POST', '/wp/v2/posts', $admin, array(
	'title'   => 'Engine write suite draft',
	'content' => "<!-- wp:paragraph -->\n<p>Body.</p>\n<!-- /wp:paragraph -->",
	'status'  => 'draft',
) );
$id = (int) ( $created_post['id'] ?? 0 );
if ( $id ) { $created[] = $id; }
check( 201 === $cc && $id > 0, 'admin create returns 201 with a new id', "status $cc" );
check( is_string( $loc ) && str_contains( (string) $loc, "/wp/v2/posts/$id" ), 'create sends a Location header', (string) $loc );
check( 'draft' === ( $created_post['status'] ?? '' ), 'created post is a draft' );
check( isset( $created_post['content']['raw'], $created_post['content']['block_version'] ), 'create returns edit-context content (raw + block_version)' );
check( 1 === ( $created_post['content']['block_version'] ?? -1 ), 'block content yields block_version 1' );

// WordPress sees the engine-created row at the same id.
[ $wc, $wp_view ] = req( $REF, 'GET', "/wp/v2/posts/$id", $admin );
check( 200 === $wc && (int) ( $wp_view['id'] ?? 0 ) === $id, 'WordPress reads the engine-created post', "status $wc" );

// --- 3. Edit-context body parity: same create against the reference. ---
[ , $ref_post ] = req( $REF, 'POST', '/wp/v2/posts', $admin, array(
	'title'   => 'Engine write suite draft',
	'content' => "<!-- wp:paragraph -->\n<p>Body.</p>\n<!-- /wp:paragraph -->",
	'status'  => 'draft',
) );
$ref_id = (int) ( $ref_post['id'] ?? 0 );
if ( $ref_id ) { $created[] = $ref_id; }
// Normalize the volatile fields (id, dates, guid, slug, links, permalink).
$strip = function ( array $o ): array {
	// class_list embeds the post id (post-{id}); it differs between the two
	// separately-created posts by construction, so drop it here.
	foreach ( array( 'id', 'date', 'date_gmt', 'modified', 'modified_gmt', 'guid', 'link', 'permalink_template', 'class_list', '_links' ) as $k ) {
		unset( $o[ $k ] );
	}
	return $o;
};
$d = minn_test_diff( $strip( $ref_post ), $strip( $created_post ) );
check( null === $d, 'create edit-context body matches the reference', (string) $d );
// The cap-gated action links: same set for the same admin user.
$ref_actions = array_values( array_filter( array_keys( $ref_post['_links'] ?? array() ), fn( $k ) => str_starts_with( $k, 'wp:action' ) ) );
$eng_actions = array_values( array_filter( array_keys( $created_post['_links'] ?? array() ), fn( $k ) => str_starts_with( $k, 'wp:action' ) ) );
sort( $ref_actions ); sort( $eng_actions );
check( $ref_actions === $eng_actions, 'admin action links match the reference', implode( ',', $eng_actions ) );

// --- 4. Update the draft, publish it, and confirm through WordPress. ---
[ $uc, $updated ] = req( $ENGINE, 'POST', "/wp/v2/posts/$id", $admin, array( 'title' => 'Engine write suite published', 'status' => 'publish' ) );
check( 200 === $uc && 'publish' === ( $updated['status'] ?? '' ), 'update publishes the post', "status $uc" );
check( 'Engine write suite published' === ( $updated['title']['raw'] ?? '' ), 'update changes the title' );
[ , $wp_after ] = req( $REF, 'GET', "/wp/v2/posts/$id", $admin );
check( 'publish' === ( $wp_after['status'] ?? '' ), 'WordPress sees the published status' );

// --- 5. Author action links are the smaller, cap-gated set. ---
[ , $author_post ] = req( $ENGINE, 'POST', '/wp/v2/posts', $author, array( 'title' => 'author draft', 'status' => 'draft' ) );
$aid = (int) ( $author_post['id'] ?? 0 );
if ( $aid ) { $created[] = $aid; }
$a_actions = array_filter( array_keys( $author_post['_links'] ?? array() ), fn( $k ) => str_starts_with( $k, 'wp:action' ) );
check( ! in_array( 'wp:action-unfiltered-html', $a_actions, true ) && ! in_array( 'wp:action-sticky', $a_actions, true ), 'author lacks unfiltered-html and sticky actions' );
check( in_array( 'wp:action-publish', $a_actions, true ), 'author keeps the publish action' );

// --- 6. Author cannot edit another user's post. ---
[ $ec, $eb ] = req( $ENGINE, 'POST', "/wp/v2/posts/9", $author, array( 'title' => 'hijack' ) );
check( 403 === $ec && 'rest_cannot_edit' === ( $eb['code'] ?? '' ), 'author editing another user post is 403 rest_cannot_edit', "got $ec " . ( $eb['code'] ?? '?' ) );

// --- 7. Delete: trash, then force. ---
[ $tc, $trashed ] = req( $ENGINE, 'DELETE', "/wp/v2/posts/$id", $admin );
check( 200 === $tc && 'trash' === ( $trashed['status'] ?? '' ), 'delete without force trashes the post', "status $tc" );
[ $ac, $already ] = req( $ENGINE, 'DELETE', "/wp/v2/posts/$id", $admin );
check( 410 === $ac && 'rest_already_trashed' === ( $already['code'] ?? '' ), 'deleting a trashed post is 410 rest_already_trashed', "got $ac " . ( $already['code'] ?? '?' ) );
[ $fc, $forced ] = req( $ENGINE, 'DELETE', "/wp/v2/posts/$id&force=true", $admin );
check( 200 === $fc && true === ( $forced['deleted'] ?? false ) && (int) ( $forced['previous']['id'] ?? 0 ) === $id, 'force delete returns deleted + previous', "status $fc" );
$created = array_values( array_diff( $created, array( $id ) ) );
[ $gc ] = req( $REF, 'GET', "/wp/v2/posts/$id", $admin );
check( 404 === $gc, 'the force-deleted post is gone from WordPress', "status $gc" );

// Clean up every post this suite created.
register_shutdown_function( function () use ( &$created, $ENGINE, $admin ) {
	foreach ( $created as $cid ) {
		req( $ENGINE, 'DELETE', "/wp/v2/posts/$cid&force=true", $admin );
	}
} );

echo "\n$pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
