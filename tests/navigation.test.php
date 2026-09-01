<?php
/**
 * wp/v2/navigation: the Design > Navigation screen's reads, diffed against
 * the oracle request by request, plus the create / rename / edit / delete
 * lifecycle proven cross-stack. Removes every menu it creates and leaves
 * the fixture menu untouched.
 *
 * Ref: (cd wp-reference && php -S 127.0.0.1:8123) — SKIPs cleanly when down.
 */

require_once __DIR__ . '/lib.php';

$ROOT   = dirname( __DIR__ );
$ENGINE = minn_test_url();
$REF    = minn_test_reference_url();
/** The fixture site's one navigation menu. */
$MENU   = 4;

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

function nav_mint( int $uid ): array {
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

function nav_fetch( string $base, ?array $mint, string $route, array $args = array(), string $method = 'GET', ?string $body = null ): array {
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
	// The route rides ?rest_route=, so its &-args are appended separately.
	$url = $base . '/?rest_route=' . rawurlencode( $route );
	foreach ( $args as $k => $v ) {
		$url .= '&' . $k . '=' . rawurlencode( (string) $v );
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
	$raw    = @file_get_contents( $url, false, $ctx );
	$status = 0;
	foreach ( $http_response_header ?? array() as $h ) {
		if ( preg_match( '#^HTTP/\S+\s+(\d+)#', $h, $m ) ) {
			$status = (int) $m[1];
		}
	}
	return array( $status, json_decode( (string) $raw, true ) );
}

function nav_norm( $x ) {
	global $ENGINE, $REF;
	if ( ! is_array( $x ) ) {
		// The reference reads its own home option through Cove's helper filter
		// and prints the site over http; the engine answers over the request's
		// own scheme. Both name the same page.
		$plain = 'http://' . (string) parse_url( $ENGINE, PHP_URL_HOST );
		return is_string( $x ) ? str_replace( array( $REF, $plain ), $ENGINE, $x ) : $x;
	}
	return array_map( 'nav_norm', $x );
}

function nav_same( string $label, ?array $mint, string $route, array $args = array() ): void {
	global $ENGINE, $REF;
	[ $rs, $rb ] = nav_fetch( $REF, $mint, $route, $args );
	[ $es, $eb ] = nav_fetch( $ENGINE, $mint, $route, $args );
	$d = minn_test_diff( nav_norm( $rb ), nav_norm( $eb ) );
	check( $rs === $es && null === $d, $label, "status $rs vs $es; " . (string) $d );
}

/** Menus this suite creates go away even when a check throws; menu 4 is fixture. */
function nav_cleanup(): void {
	global $MENU;
	shell_exec(
		'cd ' . escapeshellarg( minn_test_site_root() . '/wp-reference' ) .
		' && wp eval \'foreach ( get_posts( array( "post_type" => "wp_navigation", "post_status" => array( "publish", "draft", "trash", "pending", "private" ), "numberposts" => -1 ) ) as $p ) { if ( ' . $MENU . ' !== $p->ID ) { wp_delete_post( $p->ID, true ); } }\' 2>/dev/null; true'
	);
}
register_shutdown_function( 'nav_cleanup' );
nav_cleanup();

echo "navigation suite: $ENGINE (engine) / $REF (oracle)\n";

$admin  = nav_mint( 1 );
$editor = nav_mint( 2 );
$author = nav_mint( 3 );

// 1. The list the Design > Navigation screen loads, exactly as it asks for it.
nav_same( 'the app\'s list request matches', $admin, '/wp/v2/navigation', array(
	'context'  => 'edit',
	'per_page' => 100,
	'orderby'  => 'title',
	'order'    => 'asc',
	'_fields'  => 'id,title,status,modified_gmt',
) );
nav_same( 'list matches (view context)', $admin, '/wp/v2/navigation' );
nav_same( 'list matches (edit context)', $admin, '/wp/v2/navigation', array( 'context' => 'edit' ) );
nav_same( 'list matches for a signed-out reader', null, '/wp/v2/navigation' );

// 2. The single menu, both contexts, including the rendered markup.
nav_same( 'the fixture menu matches', $admin, "/wp/v2/navigation/$MENU" );
nav_same( 'the fixture menu matches (edit)', $admin, "/wp/v2/navigation/$MENU", array( 'context' => 'edit' ) );
nav_same( 'the editor\'s own view of it matches', $editor, "/wp/v2/navigation/$MENU" );
nav_same( 'the app\'s editor request matches', $admin, "/wp/v2/navigation/$MENU", array( 'context' => 'edit', '_fields' => 'id,title,content,status' ) );
nav_same( 'an unknown menu is not found', $admin, '/wp/v2/navigation/999999' );

// 3. Reading is public; editing needs edit_theme_options, not edit_posts.
foreach ( array( 'signed out' => null, 'author' => $author, 'editor' => $editor ) as $who => $mint ) {
	nav_same( "$who may read the list", $mint, '/wp/v2/navigation', array( '_fields' => 'id,title' ) );
	nav_same( "$who is refused edit context", $mint, '/wp/v2/navigation', array( 'context' => 'edit' ) );
	nav_same( "$who is refused the single in edit context", $mint, "/wp/v2/navigation/$MENU", array( 'context' => 'edit' ) );
}
foreach ( array( 'signed out' => null, 'author' => $author, 'editor' => $editor ) as $who => $mint ) {
	[ $rs, $rb ] = nav_fetch( $REF, $mint, "/wp/v2/navigation/$MENU", array(), 'POST', '{"title":"nope"}' );
	[ $es, $eb ] = nav_fetch( $ENGINE, $mint, "/wp/v2/navigation/$MENU", array(), 'POST', '{"title":"nope"}' );
	check( $rs === $es && null === minn_test_diff( $rb, $eb ), "$who write refused identically", "status $rs vs $es" );
	[ $rs, $rb ] = nav_fetch( $REF, $mint, '/wp/v2/navigation', array(), 'POST', '{"title":"nope","status":"publish"}' );
	[ $es, $eb ] = nav_fetch( $ENGINE, $mint, '/wp/v2/navigation', array(), 'POST', '{"title":"nope","status":"publish"}' );
	check( $rs === $es && null === minn_test_diff( $rb, $eb ), "$who create refused identically", "status $rs vs $es" );
}

// 4. The engine creates a menu the way the app does; WordPress reads it back.
$markup = '<!-- wp:navigation-link {"label":"Home","url":"/","kind":"custom"} /-->';
[ $st, $made ] = nav_fetch( $ENGINE, $admin, '/wp/v2/navigation', array(), 'POST', json_encode( array(
	'title'   => 'ZZ Engine Menu',
	'content' => $markup,
	'status'  => 'publish',
) ) );
$id = (int) ( $made['id'] ?? 0 );
check( 201 === $st && $id > 0 && 'ZZ Engine Menu' === ( $made['title']['raw'] ?? '' ) && 'publish' === ( $made['status'] ?? '' ), 'engine creates a menu', "status $st" );
if ( $id > 0 ) {
	nav_same( 'the new menu reads back identically', $admin, "/wp/v2/navigation/$id", array( 'context' => 'edit' ) );
	nav_same( 'it joins both lists in the same place', $admin, '/wp/v2/navigation', array( 'context' => 'edit', 'orderby' => 'title', 'order' => 'asc', '_fields' => 'id,title,status,modified_gmt' ) );

	// 5. Rename, the way the row menu does it.
	[ $st ] = nav_fetch( $ENGINE, $admin, "/wp/v2/navigation/$id", array(), 'POST', '{"title":"ZZ Renamed"}' );
	check( 200 === $st, 'engine renames a menu' );
	[ , $rb ] = nav_fetch( $REF, $admin, "/wp/v2/navigation/$id", array( 'context' => 'edit' ) );
	check( 'ZZ Renamed' === ( $rb['title']['raw'] ?? '' ) && $markup === ( $rb['content']['raw'] ?? '' ), 'WordPress reads the rename and keeps the markup' );

	// 6. WordPress edits the markup; the engine reads it back the same way.
	$next = $markup . '<!-- wp:page-list /-->';
	[ $st ] = nav_fetch( $REF, $admin, "/wp/v2/navigation/$id", array(), 'POST', json_encode( array( 'content' => $next ) ) );
	check( 200 === $st, 'WordPress edits the markup' );
	nav_same( 'the edited menu matches, rendered markup included', $admin, "/wp/v2/navigation/$id", array( 'context' => 'edit' ) );

	// 7. Delete, the way the row menu does it.
	[ $st, $gone ] = nav_fetch( $ENGINE, $admin, "/wp/v2/navigation/$id", array( 'force' => 'true' ), 'DELETE' );
	check( 200 === $st && true === ( $gone['deleted'] ?? null ) && isset( $gone['previous']['id'] ), 'engine deletes a menu', "status $st" );
	nav_same( 'both stacks agree it is gone', $admin, "/wp/v2/navigation/$id" );
}

// 8. A menu the reference made is listed and read the same by the engine.
shell_exec(
	'cd ' . escapeshellarg( minn_test_site_root() . '/wp-reference' ) .
	' && wp eval \'$id = wp_insert_post( array( "post_type" => "wp_navigation", "post_title" => "ZZ Oracle Menu", "post_name" => "zz-oracle-menu", "post_status" => "publish", "post_content" => "<!-- wp:page-list /-->" ) );\' 2>/dev/null'
);
nav_same( 'a menu the oracle made matches', $admin, '/wp/v2/navigation', array( 'context' => 'edit', 'orderby' => 'title', 'order' => 'asc' ) );
nav_same( 'and its rendered page list matches', $admin, '/wp/v2/navigation', array( 'slug' => 'zz-oracle-menu' ) );

// 9. A draft menu is private to callers who can edit menus.
shell_exec(
	'cd ' . escapeshellarg( minn_test_site_root() . '/wp-reference' ) .
	' && wp eval \'wp_insert_post( array( "post_type" => "wp_navigation", "post_title" => "ZZ Draft Menu", "post_status" => "draft", "post_content" => "x" ) );\' 2>/dev/null'
);
nav_same( 'a draft menu is hidden from the public list', null, '/wp/v2/navigation', array( '_fields' => 'id,title,status' ) );
nav_same( 'and shown to an administrator asking for it', $admin, '/wp/v2/navigation', array( 'context' => 'edit', 'status' => 'draft', '_fields' => 'id,title,status' ) );
nav_same( 'a status an editor may not ask for is refused identically', $editor, '/wp/v2/navigation', array( 'status' => 'draft' ) );

nav_cleanup();
nav_same( 'the fixture menu is the only one left', $admin, '/wp/v2/navigation', array( '_fields' => 'id,slug' ) );

echo "\n$pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
