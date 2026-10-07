<?php
/**
 * wp/v2/global-styles: the site editor's saved styles by id (read, write,
 * revisions) and the active theme's own styles and variations, diffed
 * request by request against the oracle for an admin, an editor, an author
 * and an anonymous caller, plus a write round trip through the engine that
 * WordPress reads back. Fixtures pin the captured shapes without the oracle.
 * Ref: the test site's Cove twin (cove twin minn add --as-site=ref.minn.localhost).
 */

require_once __DIR__ . '/lib.php';

$ENGINE = minn_test_url();
$REF    = minn_test_reference_url();
$ROOT   = dirname( __DIR__ );

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

function gs_wp( string $command ): string {
	return trim( (string) shell_exec( 'wp --path=' . escapeshellarg( minn_test_site_root() . '/wp-reference' ) . " $command 2>/dev/null" ) );
}

function gs_mint( int $uid ): array {
	global $ROOT;
	$mint = json_decode( (string) shell_exec(
		'wp --path=' . escapeshellarg( minn_test_site_root() . '/wp-reference' ) . ' eval-file ' . escapeshellarg( "$ROOT/tests/tools/mint-session.php" ) . " $uid 2>/dev/null"
	), true );
	if ( ! $mint || empty( $mint['cookie'] ) ) {
		echo "SKIP: could not mint a session for user $uid\n";
		exit( 0 );
	}
	return $mint;
}

/** @return array{0: int, 1: mixed} status and decoded body */
function gs_fetch( string $base, string $route, ?array $mint, string $method = 'GET', ?string $body = null ): array {
	$headers = array();
	if ( $mint ) {
		$alt       = 'wordpress_logged_in_' . md5( $base );
		$headers[] = 'Cookie: ' . $mint['cookie_name'] . '=' . $mint['cookie'] . '; ' . $alt . '=' . $mint['cookie'];
		$headers[] = 'X-WP-Nonce: ' . $mint['nonce'];
	}
	if ( null !== $body ) {
		$headers[] = 'Content-Type: application/json';
	}
	$ctx = stream_context_create(
		array(
			'ssl'  => array( 'verify_peer' => false, 'verify_peer_name' => false ),
			'http' => array( 'ignore_errors' => true, 'timeout' => 15, 'method' => $method, 'header' => implode( "\r\n", $headers ), 'content' => $body ?? '' ),
		)
	);
	[ $path, $query ] = array_pad( explode( '?', $route, 2 ), 2, '' );
	$raw    = @file_get_contents( "$base/?rest_route=" . rawurlencode( $path ) . ( '' === $query ? '' : "&$query" ), false, $ctx );
	$status = 0;
	foreach ( $http_response_header ?? array() as $h ) {
		if ( preg_match( '#^HTTP/\S+\s+(\d+)#', $h, $m ) ) {
			$status = (int) $m[1];
		}
	}
	return array( $status, json_decode( (string) $raw, true ) );
}

function gs_norm( $x ) {
	global $ENGINE, $REF;
	if ( is_array( $x ) ) {
		return array_map( 'gs_norm', $x );
	}
	if ( is_string( $x ) ) {
		return str_replace( array( str_replace( '/', '\\/', $REF ), $REF ), $ENGINE, $x );
	}
	return $x;
}

function gs_parity( string $label, string $route, ?array $mint = null, string $method = 'GET', ?string $body = null ): void {
	global $ENGINE, $REF;
	[ $rs, $rb ] = gs_fetch( $REF, $route, $mint, $method, $body );
	[ $es, $eb ] = gs_fetch( $ENGINE, $route, $mint, $method, $body );
	if ( $rs !== $es ) {
		check( false, $label, "status $rs vs $es " . json_encode( $eb ) );
		return;
	}
	$d = minn_test_diff( gs_norm( $rb ), gs_norm( $eb ) );
	check( null === $d, $label . " ($rs)", (string) $d );
}

/** The revision count in the links depends on the run; the fixtures pin everything else. */
function gs_without_count( $body ) {
	if ( is_array( $body ) && isset( $body['_links']['version-history'][0]['count'] ) ) {
		$body['_links']['version-history'][0]['count'] = 0;
	}
	return $body;
}

// The engine can answer the fixtures without the oracle.
[ $ph ] = minn_test_fetch( "$REF/?rest_route=/wp/v2/posts", 3 );
if ( 200 !== $ph['status'] ) {
	echo "SKIP: reference WordPress not running at $REF\n";
	exit( 0 );
}

echo "global-styles suite: $ENGINE (engine) / $REF (oracle)\n";

$admin  = gs_mint( 1 );
$editor = gs_mint( 2 );
$author = gs_mint( 3 );

$id = (int) gs_wp( "eval 'echo WP_Theme_JSON_Resolver::get_user_global_styles_post_id();'" );
check( $id > 0, "the user global-styles post exists ($id)" );
$original_content = gs_wp( "post get $id --field=post_content" );
$original_title   = gs_wp( "post get $id --field=post_title" );
$revisions_before = array_filter( array_map( 'intval', preg_split( '/\s+/', gs_wp( "post list --post_type=revision --post_parent=$id --post_status=any --format=ids" ) ) ?: array() ) );

register_shutdown_function( static function () use ( $id, $original_content, $original_title, $revisions_before ): void {
	gs_wp( 'post update ' . $id . ' --post_content=' . escapeshellarg( $original_content ) . ' --post_title=' . escapeshellarg( $original_title ) );
	foreach ( array_filter( array_map( 'intval', preg_split( '/\s+/', gs_wp( "post list --post_type=revision --post_parent=$id --post_status=any --format=ids" ) ) ?: array() ) ) as $rid ) {
		if ( ! in_array( $rid, $revisions_before, true ) ) {
			gs_wp( "post delete $rid --force" );
		}
	}
} );

// ---- fixtures: the captured shapes, host-normalized, the revision count aside
foreach ( array(
	'global-styles-theme'      => array( '/wp/v2/global-styles/themes/twentytwentyfive', $admin ),
	'global-styles-variations' => array( '/wp/v2/global-styles/themes/twentytwentyfive/variations', $admin ),
	'global-styles-item'       => array( "/wp/v2/global-styles/$id", $admin ),
	'global-styles-item-edit'  => array( "/wp/v2/global-styles/$id?context=edit", $admin ),
) as $fixture => [ $route, $mint ] ) {
	$expected = json_decode( (string) file_get_contents( "$ROOT/contracts/fixtures/rest/$fixture.json" ), true );
	[ $st, $body ] = gs_fetch( $ENGINE, $route, $mint );
	$expected = gs_norm( gs_without_count( str_contains( $route, "/$id" ) ? array_replace( (array) $expected, array( 'id' => $id ) ) : $expected ) );
	$expected = str_contains( $route, "/$id" ) ? json_decode( str_replace( '/global-styles/9555', "/global-styles/$id", (string) json_encode( $expected, JSON_UNESCAPED_SLASHES ) ), true ) : $expected;
	$d        = minn_test_diff( $expected, gs_norm( gs_without_count( $body ) ) );
	check( 200 === $st && null === $d, "fixture $fixture", "status $st " . (string) $d );
}

// ---- reads, as an admin
gs_parity( 'admin: item', "/wp/v2/global-styles/$id", $admin );
gs_parity( 'admin: item in edit context', "/wp/v2/global-styles/$id?context=edit", $admin );
gs_parity( 'admin: item with _fields', "/wp/v2/global-styles/$id?_fields=id,title,_links", $admin );
gs_parity( 'admin: the active theme', '/wp/v2/global-styles/themes/twentytwentyfive', $admin );
gs_parity( 'admin: the active theme\'s variations', '/wp/v2/global-styles/themes/twentytwentyfive/variations', $admin );
gs_parity( 'admin: revisions', "/wp/v2/global-styles/$id/revisions", $admin );
gs_parity( 'admin: revisions paged', "/wp/v2/global-styles/$id/revisions?per_page=2&page=1", $admin );
gs_parity( 'admin: revisions past the last page', "/wp/v2/global-styles/$id/revisions?per_page=2&page=99", $admin );
gs_parity( 'admin: revisions with _fields', "/wp/v2/global-styles/$id/revisions?_fields=id,parent", $admin );

// ---- what is not there
gs_parity( 'admin: unknown id', '/wp/v2/global-styles/99999', $admin );
gs_parity( 'admin: a post id is not a global-styles id', '/wp/v2/global-styles/1', $admin );
gs_parity( 'admin: an inactive theme', '/wp/v2/global-styles/themes/twentytwentyfour', $admin );
gs_parity( 'admin: an unknown theme\'s variations', '/wp/v2/global-styles/themes/nope-theme/variations', $admin );
gs_parity( 'admin: revisions of a post', '/wp/v2/global-styles/1/revisions', $admin );
gs_parity( 'admin: revisions of nothing', '/wp/v2/global-styles/99999/revisions', $admin );
gs_parity( 'admin: an unknown revision', "/wp/v2/global-styles/$id/revisions/99999", $admin );
gs_parity( 'admin: no collection', '/wp/v2/global-styles', $admin );
gs_parity( 'admin: no create', '/wp/v2/global-styles', $admin, 'POST', '{"styles":{}}' );
gs_parity( 'admin: no delete', "/wp/v2/global-styles/$id", $admin, 'DELETE' );
gs_parity( 'admin: styles must be an object', "/wp/v2/global-styles/$id", $admin, 'POST', '{"styles":"nope"}' );
gs_parity( 'admin: settings must be an object', "/wp/v2/global-styles/$id", $admin, 'POST', '{"settings":7}' );

// ---- the other callers
foreach ( array( 'editor' => $editor, 'author' => $author, 'anonymous' => null ) as $who => $mint ) {
	gs_parity( "$who: item", "/wp/v2/global-styles/$id", $mint );
	gs_parity( "$who: item in edit context", "/wp/v2/global-styles/$id?context=edit", $mint );
	gs_parity( "$who: the active theme", '/wp/v2/global-styles/themes/twentytwentyfive', $mint );
	gs_parity( "$who: the active theme's variations", '/wp/v2/global-styles/themes/twentytwentyfive/variations', $mint );
	gs_parity( "$who: revisions", "/wp/v2/global-styles/$id/revisions", $mint );
	gs_parity( "$who: unknown id", '/wp/v2/global-styles/99999', $mint );
	gs_parity( "$who: write refused", "/wp/v2/global-styles/$id", $mint, 'POST', '{"styles":{}}' );
}

// ---- a write through the engine that WordPress reads back
$body = json_encode( array(
	'title'    => 'Engine wrote',
	'styles'   => array( 'color' => array( 'text' => 'var:preset|color|contrast' ), 'blocks' => array( 'core/group' => array( 'color' => array( 'text' => '#123' ) ) ) ),
	'settings' => array(
		'appearanceTools' => true,
		'color'           => array( 'palette' => array( array( 'slug' => 'a', 'color' => '#000', 'name' => 'A' ) ) ),
		'blocks'          => array( 'core/button' => array( 'color' => array( 'palette' => array( array( 'slug' => 'b', 'color' => '#111', 'name' => 'B' ) ) ) ) ),
	),
) );
[ $st, $reply ] = gs_fetch( $ENGINE, "/wp/v2/global-styles/$id", $admin, 'POST', $body );
check( 200 === $st && 'Engine wrote' === ( $reply['title']['raw'] ?? '' ), 'engine: POST answers the saved item (200)', json_encode( $reply ) );
[ $st, $oracle ] = gs_fetch( $REF, "/wp/v2/global-styles/$id?context=edit", $admin );
$d = minn_test_diff( gs_norm( $oracle ), gs_norm( $reply ) );
check( 200 === $st && null === $d, 'engine: the POST reply equals the WordPress edit read-back', (string) $d );
check( ! isset( $reply['settings']['appearanceTools'] ) && ( $reply['settings']['position']['sticky'] ?? false ) === true, 'engine: appearanceTools expanded into its flags' );
check( ( $reply['settings']['color']['palette']['custom'][0]['slug'] ?? '' ) === 'a', 'engine: the site editor\'s presets sit under custom' );
check( ( $reply['styles']['color']['text'] ?? '' ) === 'var(--wp--preset--color--contrast)', 'engine: var:preset tokens resolve on the way out' );
check( str_contains( gs_wp( "post get $id --field=post_content" ), '"styles":{"color":{"text":"var:preset|color|contrast"}' ), 'engine: the stored body keeps the tokens as written' );
gs_parity( 'after the write: item', "/wp/v2/global-styles/$id", $admin );
gs_parity( 'after the write: revisions', "/wp/v2/global-styles/$id/revisions", $admin );
$revisions_now = gs_fetch( $REF, "/wp/v2/global-styles/$id/revisions", $admin )[1];
check( count( $revisions_now ) === count( $revisions_before ) + 1, 'engine: one revision per changed save', count( $revisions_now ) . ' vs ' . count( $revisions_before ) );
gs_parity( 'after the write: the newest revision', "/wp/v2/global-styles/$id/revisions/" . (int) ( $revisions_now[0]['id'] ?? 0 ), $admin );

[ $st, $reply ] = gs_fetch( $ENGINE, "/wp/v2/global-styles/$id", $admin, 'PUT', '{"styles":{"color":{"background":"#eee"}}}' );
check( 200 === $st && ( $reply['settings']['color']['palette']['custom'][0]['slug'] ?? '' ) === 'a' && ( $reply['styles'] ?? null ) === array( 'color' => array( 'background' => '#eee' ) ), 'engine: a body naming only styles keeps the settings', json_encode( $reply ) );
[ $st, $reply ] = gs_fetch( $ENGINE, "/wp/v2/global-styles/$id", $admin, 'PATCH', '{"title":{"raw":"Custom Styles"},"settings":{},"styles":{}}' );
check( 200 === $st && $reply['settings'] === array() && $reply['styles'] === array() && 'Custom Styles' === ( $reply['title']['raw'] ?? '' ), 'engine: emptied again, title from {raw}', json_encode( $reply ) );
gs_parity( 'after emptying: item in edit context', "/wp/v2/global-styles/$id?context=edit", $admin );
gs_parity( 'after emptying: revisions', "/wp/v2/global-styles/$id/revisions", $admin );

// ---- the app's own route reads the saved styles through the engine's route
gs_parity( 'minn-admin/v1/styles/variations', '/minn-admin/v1/styles/variations', $admin );

echo "\n$pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
