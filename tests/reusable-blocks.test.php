<?php
/**
 * wp/v2/blocks and wp/v2/wp_pattern_category: synced patterns and reusable
 * blocks, and the taxonomy that files them. Diffed request by request
 * against the oracle for an admin, an author and an anonymous caller, with a
 * write round trip the oracle reads back. The suite makes its own rows and
 * removes them on shutdown. Ref: the test site's Cove twin
 * (cove twin minn add --as-site=ref.minn.localhost).
 */

require_once __DIR__ . '/lib.php';

$ENGINE = minn_test_url();
$REF    = minn_test_reference_url();

$pass = 0;
$fail = 0;

function check( bool $ok, string $label, string $detail = '' ): void {
	global $pass, $fail;
	if ( $ok ) {
		$pass++;
		echo "  ok  $label\n";
	} else {
		$fail++;
		echo "FAIL  $label" . ( $detail ? "\n      " . substr( $detail, 0, 600 ) : '' ) . "\n";
	}
}

function rb_mint( int $uid ): array {
	$root = dirname( __DIR__ );
	$mint = json_decode( (string) shell_exec(
		'wp --path=' . escapeshellarg( minn_test_site_root() . '/wp-reference' ) . ' eval-file ' . escapeshellarg( "$root/tests/tools/mint-session.php" ) . " $uid 2>/dev/null"
	), true );
	if ( ! $mint || empty( $mint['cookie'] ) ) {
		echo "SKIP: could not mint a session for user $uid\n";
		exit( 0 );
	}
	return $mint;
}

/** @return array{0:int,1:mixed} */
function rb_fetch( string $base, string $route, ?array $mint, string $method = 'GET', ?string $body = null ): array {
	$headers = array();
	if ( $mint ) {
		$alt       = 'wordpress_logged_in_' . md5( $base );
		$headers[] = 'Cookie: ' . $mint['cookie_name'] . '=' . $mint['cookie'] . '; ' . $alt . '=' . $mint['cookie'];
		$headers[] = 'X-WP-Nonce: ' . $mint['nonce'];
	}
	if ( null !== $body ) {
		$headers[] = 'Content-Type: application/json';
	}
	[ $path, $query ] = array_pad( explode( '?', $route, 2 ), 2, '' );
	$ctx = stream_context_create( array(
		'ssl'  => array( 'verify_peer' => false, 'verify_peer_name' => false ),
		'http' => array( 'ignore_errors' => true, 'timeout' => 15, 'method' => $method, 'header' => implode( "\r\n", $headers ), 'content' => $body ?? '' ),
	) );
	$raw    = @file_get_contents( "$base/?rest_route=" . rawurlencode( $path ) . ( '' === $query ? '' : "&$query" ), false, $ctx );
	$status = 0;
	foreach ( $http_response_header ?? array() as $h ) {
		if ( preg_match( '#^HTTP/\S+\s+(\d+)#', $h, $m ) ) {
			$status = (int) $m[1];
		}
	}
	return array( $status, json_decode( (string) $raw, true ) );
}

function rb_norm( $x ) {
	global $ENGINE, $REF;
	if ( is_array( $x ) ) {
		return array_map( 'rb_norm', $x );
	}
	if ( is_string( $x ) ) {
		return str_replace( array( str_replace( '/', '\\/', $REF ), $REF ), $ENGINE, $x );
	}
	return $x;
}

function rb_parity( string $label, string $route, ?array $mint = null, string $method = 'GET', ?string $body = null ): void {
	global $ENGINE, $REF;
	[ $rs, $rb ] = rb_fetch( $REF, $route, $mint, $method, $body );
	[ $es, $eb ] = rb_fetch( $ENGINE, $route, $mint, $method, $body );
	if ( $rs !== $es ) {
		check( false, $label, "status $rs vs $es " . json_encode( $eb ) );
		return;
	}
	$d = minn_test_diff( rb_norm( $rb ), rb_norm( $eb ) );
	check( null === $d, "$label ($rs)", (string) $d );
}

function rb_post( string $base, string $route, array $mint, string $body ): array {
	return rb_fetch( $base, $route, $mint, 'POST', $body )[1];
}

[ $ph ] = minn_test_fetch( "$REF/?rest_route=/wp/v2/posts", 3 );
if ( 200 !== $ph['status'] ) {
	echo "SKIP: reference WordPress not running at $REF\n";
	exit( 0 );
}

echo "reusable-blocks suite: $ENGINE (engine) / $REF (oracle)\n";

$admin  = rb_mint( 1 );
$author = rb_mint( 3 );

// The oracle makes the rows both stacks read, since they share one database.
$cat = rb_post( $REF, '/wp/v2/wp_pattern_category', $admin, json_encode( array( 'name' => 'Suite category', 'description' => 'from the suite' ) ) );
$tid = (int) ( $cat['id'] ?? 0 );
$pub = rb_post( $REF, '/wp/v2/blocks', $admin, json_encode( array(
	'title'   => 'Suite block',
	'content' => '<!-- wp:paragraph --><p>Hello</p><!-- /wp:paragraph -->',
	'status'  => 'publish',
	'excerpt' => 'An excerpt',
	'wp_pattern_category' => array( $tid ),
	'meta'    => array( 'wp_pattern_sync_status' => 'unsynced' ),
) ) );
$bid = (int) ( $pub['id'] ?? 0 );
rb_post( $REF, "/wp/v2/blocks/$bid", $admin, json_encode( array( 'title' => 'Suite block 2' ) ) ); // leaves a revision
$draft = rb_post( $REF, '/wp/v2/blocks', $author, json_encode( array( 'title' => 'Author draft', 'status' => 'draft' ) ) );
$did   = (int) ( $draft['id'] ?? 0 );
$revs  = rb_fetch( $REF, "/wp/v2/blocks/$bid/revisions", $admin )[1];
$rid   = (int) ( $revs[0]['id'] ?? 0 );

check( $tid > 0 && $bid > 0 && $did > 0 && $rid > 0, "the oracle made a category ($tid), a block ($bid), a draft ($did) and a revision ($rid)" );

register_shutdown_function( static function () use ( $bid, $did, $tid, $admin, $REF ): void {
	rb_fetch( $REF, "/wp/v2/blocks/$bid?force=true", $admin, 'DELETE' );
	rb_fetch( $REF, "/wp/v2/blocks/$did?force=true", $admin, 'DELETE' );
	rb_fetch( $REF, "/wp/v2/wp_pattern_category/$tid?force=true", $admin, 'DELETE' );
} );

// ---- reads, admin
rb_parity( 'admin: list', '/wp/v2/blocks', $admin );
rb_parity( 'admin: list edit context', '/wp/v2/blocks?context=edit', $admin );
rb_parity( 'admin: a block', "/wp/v2/blocks/$bid", $admin );
rb_parity( 'admin: a block in edit context', "/wp/v2/blocks/$bid?context=edit", $admin );
rb_parity( 'admin: a draft block', "/wp/v2/blocks/$did", $admin );
rb_parity( 'admin: the draft in edit context', "/wp/v2/blocks/$did?context=edit", $admin );
rb_parity( 'admin: list with _fields', '/wp/v2/blocks?_fields=id,title,meta,wp_pattern_sync_status,wp_pattern_category', $admin );
rb_parity( 'admin: revisions', "/wp/v2/blocks/$bid/revisions", $admin );
rb_parity( 'admin: one revision', "/wp/v2/blocks/$bid/revisions/$rid", $admin );
rb_parity( 'admin: one revision in edit context', "/wp/v2/blocks/$bid/revisions/$rid?context=edit", $admin );
rb_parity( 'admin: autosaves', "/wp/v2/blocks/$bid/autosaves", $admin );

// ---- filters
rb_parity( 'admin: by pattern category', "/wp/v2/blocks?wp_pattern_category=$tid", $admin );
rb_parity( 'admin: excluding a pattern category', "/wp/v2/blocks?wp_pattern_category_exclude=$tid", $admin );
rb_parity( 'admin: by search', '/wp/v2/blocks?search=Suite', $admin );
rb_parity( 'admin: by status', '/wp/v2/blocks?status=draft', $admin );
rb_parity( 'admin: including an id', "/wp/v2/blocks?include=$bid", $admin );
rb_parity( 'admin: past the last page', '/wp/v2/blocks?per_page=1&page=99', $admin );

// ---- what is not there
rb_parity( 'admin: unknown block', '/wp/v2/blocks/99999', $admin );
rb_parity( 'admin: an unknown revision', "/wp/v2/blocks/$bid/revisions/99999", $admin );
rb_parity( 'admin: the type', '/wp/v2/types/wp_block', $admin );

// ---- the pattern-category taxonomy
rb_parity( 'admin: categories', '/wp/v2/wp_pattern_category', $admin );
rb_parity( 'admin: a category', "/wp/v2/wp_pattern_category/$tid", $admin );
rb_parity( 'admin: a category in edit context', "/wp/v2/wp_pattern_category/$tid?context=edit", $admin );
rb_parity( 'admin: categories on a block', "/wp/v2/wp_pattern_category?post=$bid", $admin );
rb_parity( 'admin: an unknown category', '/wp/v2/wp_pattern_category/99999', $admin );
rb_parity( 'admin: the taxonomy', '/wp/v2/taxonomies/wp_pattern_category', $admin );

// ---- the author and the anonymous caller
foreach ( array( 'author' => $author, 'anonymous' => null ) as $who => $mint ) {
	rb_parity( "$who: list", '/wp/v2/blocks', $mint );
	rb_parity( "$who: a block", "/wp/v2/blocks/$bid", $mint );
	rb_parity( "$who: a block in edit context", "/wp/v2/blocks/$bid?context=edit", $mint );
	rb_parity( "$who: revisions", "/wp/v2/blocks/$bid/revisions", $mint );
	rb_parity( "$who: unknown block", '/wp/v2/blocks/99999', $mint );
	rb_parity( "$who: categories", '/wp/v2/wp_pattern_category', $mint );
	rb_parity( "$who: a category", "/wp/v2/wp_pattern_category/$tid", $mint );
	rb_parity( "$who: a category in edit context", "/wp/v2/wp_pattern_category/$tid?context=edit", $mint );
}
rb_parity( 'anonymous: creating a block is refused', '/wp/v2/blocks', null, 'POST', '{"title":"x"}' );

// An author may create a block; the engine's reply matches WordPress's read-back.
$authored = rb_post( $ENGINE, '/wp/v2/blocks', $author, json_encode( array( 'title' => 'Author block', 'status' => 'publish' ) ) );
$aid      = (int) ( $authored['id'] ?? 0 );
$oracle   = rb_fetch( $REF, "/wp/v2/blocks/$aid?context=edit", $author )[1];
check( $aid > 0 && null === minn_test_diff( rb_norm( $oracle ), rb_norm( $authored ) ), 'author: creates a block WordPress reads back', (string) minn_test_diff( rb_norm( $oracle ), rb_norm( $authored ) ) );
rb_fetch( $REF, "/wp/v2/blocks/$aid?force=true", $admin, 'DELETE' );
rb_parity( 'author: reads own draft', "/wp/v2/blocks/$did", $author );
rb_parity( 'author: edits own draft', "/wp/v2/blocks/$did?context=edit", $author );
rb_parity( "author: cannot edit another's block", "/wp/v2/blocks/$bid?context=edit", $author );

// ---- a write through the engine that WordPress reads back
$made = rb_post( $ENGINE, '/wp/v2/blocks', $admin, json_encode( array(
	'title'   => 'Engine block',
	'content' => '<!-- wp:paragraph --><p>From the engine</p><!-- /wp:paragraph -->',
	'status'  => 'publish',
	'excerpt' => 'Made here',
	'wp_pattern_category' => array( $tid ),
	'meta'    => array( 'wp_pattern_sync_status' => 'unsynced' ),
) ) );
$eid = (int) ( $made['id'] ?? 0 );
check( $eid > 0 && 'Engine block' === ( $made['title']['raw'] ?? '' ), 'engine: creates a block (201-shaped reply)', json_encode( $made ) );
$oracle = rb_fetch( $REF, "/wp/v2/blocks/$eid?context=edit", $admin )[1];
check( null === minn_test_diff( rb_norm( $oracle ), rb_norm( $made ) ), 'engine: the create reply equals the WordPress edit read-back', (string) minn_test_diff( rb_norm( $oracle ), rb_norm( $made ) ) );
check( ( $made['wp_pattern_category'] ?? array() ) === array( $tid ), 'engine: the pattern category is stored', json_encode( $made['wp_pattern_category'] ?? null ) );
check( 'unsynced' === ( $made['wp_pattern_sync_status'] ?? '' ), 'engine: the sync status is stored' );

// The sync status is cleared with null; '' is outside its enum, which the reference refuses too.
[ $st, $refused ] = rb_fetch( $ENGINE, "/wp/v2/blocks/$eid", $admin, 'POST', json_encode( array( 'meta' => array( 'wp_pattern_sync_status' => '' ) ) ) );
[ $ref_st, $ref_refused ] = rb_fetch( $REF, "/wp/v2/blocks/$eid", $admin, 'POST', json_encode( array( 'meta' => array( 'wp_pattern_sync_status' => '' ) ) ) );
check( 400 === $st && 'rest_not_in_enum' === ( $refused['code'] ?? '' ) && null === minn_test_diff( $ref_refused, $refused ), 'engine: an empty sync status is refused, as the reference refuses it', "status $st/$ref_st " . json_encode( array( $refused, $ref_refused ) ) );
$updated = rb_fetch( $ENGINE, "/wp/v2/blocks/$eid", $admin, 'POST', json_encode( array( 'title' => 'Engine block 2', 'wp_pattern_category' => array(), 'meta' => array( 'wp_pattern_sync_status' => null ) ) ) )[1];
$oracle  = rb_fetch( $REF, "/wp/v2/blocks/$eid?context=edit", $admin )[1];
check( null === minn_test_diff( rb_norm( $oracle ), rb_norm( $updated ) ), 'engine: the update equals the WordPress edit read-back', (string) minn_test_diff( rb_norm( $oracle ), rb_norm( $updated ) ) );
rb_parity( 'after the write: revisions', "/wp/v2/blocks/$eid/revisions", $admin );

$trashed = rb_fetch( $ENGINE, "/wp/v2/blocks/$eid", $admin, 'DELETE' )[1];
check( 'trash' === ( $trashed['status'] ?? '' ), 'engine: DELETE trashes the block', json_encode( $trashed ) );
rb_parity( 'after the trash: the WordPress read', "/wp/v2/blocks/$eid?context=edit", $admin );
$deleted = rb_fetch( $ENGINE, "/wp/v2/blocks/$eid?force=true", $admin, 'DELETE' )[1];
check( true === ( $deleted['deleted'] ?? null ) && isset( $deleted['previous'] ), 'engine: DELETE force removes it', json_encode( $deleted ) );

// a term made through the engine
$term = rb_post( $ENGINE, '/wp/v2/wp_pattern_category', $admin, json_encode( array( 'name' => 'Engine category' ) ) );
$etid = (int) ( $term['id'] ?? 0 );
$oracle = rb_fetch( $REF, "/wp/v2/wp_pattern_category/$etid?context=edit", $admin )[1];
check( $etid > 0 && null === minn_test_diff( rb_norm( $oracle ), rb_norm( $term ) ), 'engine: a pattern category equals the WordPress read-back', (string) minn_test_diff( rb_norm( $oracle ), rb_norm( $term ) ) );
rb_fetch( $REF, "/wp/v2/wp_pattern_category/$etid?force=true", $admin, 'DELETE' );

echo "\n$pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
