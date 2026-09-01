<?php
/**
 * The editor-support surface: revisions and autosaves cross-stack, the
 * edit lock feeding minn_lock, and the small minn-admin/v1 routes the
 * editor and Settings views load.
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

function ed_mint( int $uid ): array {
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

function ed_fetch( string $base, string $query, ?array $mint, string $method = 'GET', ?string $body = null ): array {
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

function ed_norm( $x ) {
	global $ENGINE, $REF;
	if ( is_array( $x ) ) {
		return array_map( 'ed_norm', $x );
	}
	if ( is_string( $x ) ) {
		$x = str_replace( array( str_replace( '/', '\\/', $REF ), $REF ), $ENGINE, $x );
		return preg_replace( '/preview_nonce=[0-9a-f]+/', 'preview_nonce=«n»', $x );
	}
	return $x;
}

function ed_parity( string $label, string $query, ?array $mint, string $method = 'GET', ?string $body = null ): void {
	global $ENGINE, $REF;
	[ $rs, $rb ] = ed_fetch( $REF, $query, $mint, $method, $body );
	[ $es, $eb ] = ed_fetch( $ENGINE, $query, $mint, $method, $body );
	if ( $rs !== $es ) {
		check( false, $label, "status $rs vs $es" );
		return;
	}
	$d = minn_test_diff( ed_norm( $rb ), ed_norm( $eb ) );
	check( null === $d, $label, (string) $d );
}

function ed_cleanup(): void {
$battery = (array) json_decode((string) @file_get_contents($GLOBALS['ROOT'] . "/contracts/fixtures/blocks/manifest.json"), true);
$keep    = implode(',', array_merge(array_values((array) ($battery['posts'] ?? [])), [(int) ($battery['image'] ?? 0)]));
	global $ROOT;
	shell_exec(
		'cd ' . escapeshellarg( minn_test_site_root() . '/wp-reference' ) .
		' && wp db query "DELETE FROM wp_posts WHERE post_type = \'revision\'; DELETE FROM wp_postmeta WHERE meta_key = \'_edit_lock\'" 2>/dev/null'
		. ' && for id in $(wp post list --post_type=post,page --post_status=any --field=ID --post__not_in=' . $keep . ' 2>/dev/null); do [ "$id" -gt 29 ] && wp post delete "$id" --force 2>/dev/null; done; true'
	);
}
register_shutdown_function( 'ed_cleanup' );
ed_cleanup();

echo "editor suite: $ENGINE (engine) / $REF (oracle)\n";

$admin  = ed_mint( 1 );
$editor = ed_mint( 2 );

// 1. The small routes at parity.
ed_parity( 'templates', 'rest_route=' . rawurlencode( '/minn-admin/v1/templates' ) . '&type=post', $admin );
ed_parity( 'site-logo', 'rest_route=' . rawurlencode( '/minn-admin/v1/site-logo' ), $admin );
ed_parity( 'permalinks', 'rest_route=' . rawurlencode( '/minn-admin/v1/permalinks' ), $admin );
ed_parity( 'permalinks refused below manage_options', 'rest_route=' . rawurlencode( '/minn-admin/v1/permalinks' ), $editor );
ed_parity( 'spam summary', 'rest_route=' . rawurlencode( '/minn-admin/v1/spam' ), $admin );
ed_parity( 'languages', 'rest_route=' . rawurlencode( '/minn-admin/v1/languages' ), $admin );
ed_parity( 'media months', 'rest_route=' . rawurlencode( '/minn-admin/v1/media/months' ), $admin );
ed_parity( 'blocks list', 'rest_route=' . rawurlencode( '/wp/v2/blocks' ) . '&context=edit&status=publish&_fields=id,title,meta,wp_pattern_sync_status', $admin );
ed_parity( 'revisions empty', 'rest_route=' . rawurlencode( '/wp/v2/posts/1/revisions' ), $admin );

// 2. Autosave lifecycle: the engine saves, WordPress reads, the slot reuses.
$QAS = 'rest_route=' . rawurlencode( '/wp/v2/posts/1/autosaves' );
[ $st, $as1 ] = ed_fetch( $ENGINE, $QAS, $admin, 'POST', '{"title":"Hello world!","content":"<!-- wp:paragraph --><p>engine autosave</p><!-- /wp:paragraph -->"}' );
check( 200 === $st && '1-autosave-v1' === ( $as1['slug'] ?? '' ), 'engine creates the autosave slot' );
[ $st, $list ] = ed_fetch( $REF, $QAS, $admin );
check( 200 === $st && 1 === count( $list ?? array() ) && ( $as1['id'] ?? 0 ) === ( $list[0]['id'] ?? -1 ), 'WordPress lists the engine autosave' );
// The create response is edit-context (raw duals + preview_link); the
// list serves view-context objects — compare the rendered halves.
$flat = static function ( $o ) {
	unset( $o['preview_link'] );
	foreach ( array( 'guid', 'title', 'content', 'excerpt' ) as $k ) {
		$o[ $k ] = $o[ $k ]['rendered'] ?? null;
	}
	return $o;
};
$d = minn_test_diff( ed_norm( $flat( $as1 ) ), ed_norm( $flat( $list[0] ?? array() ) ) );
check( null === $d, 'autosave object matches the WordPress read', (string) $d );
[ $st, $as2 ] = ed_fetch( $ENGINE, $QAS, $admin, 'POST', '{"title":"Hello world!","content":"<!-- wp:paragraph --><p>second save</p><!-- /wp:paragraph -->"}' );
check( 200 === $st && ( $as1['id'] ?? 0 ) === ( $as2['id'] ?? -1 ), 'a second save reuses the slot (one autosave per author)' );
ed_parity( 'autosaves list parity', $QAS, $admin );

// The oracle saves too; the engine must serve the updated slot.
[ $st ] = ed_fetch( $REF, $QAS, $admin, 'POST', '{"title":"Hello world!","content":"<!-- wp:paragraph --><p>oracle save</p><!-- /wp:paragraph -->"}' );
check( 200 === $st, 'WordPress writes into the same slot' );
[ , $b ] = ed_fetch( $ENGINE, $QAS, $admin );
check( str_contains( $b[0]['content']['rendered'] ?? '', 'oracle save' ), 'engine serves the WordPress-updated autosave' );

// 3. A real revision: WordPress updates a suite post, both stacks list it.
[ $st, $post ] = ed_fetch( $ENGINE, 'rest_route=' . rawurlencode( '/wp/v2/posts' ), $admin, 'POST', '{"title":"Revision probe","status":"draft","content":"v1"}' );
check( 201 === $st, 'suite post created' );
$rid = (int) ( $post['id'] ?? 0 );
[ $st ] = ed_fetch( $REF, 'rest_route=' . rawurlencode( '/wp/v2/posts/' . $rid ), $admin, 'POST', '{"content":"v2 makes a revision"}' );
check( 200 === $st, 'WordPress update creates a revision' );
ed_parity( 'revisions list parity', 'rest_route=' . rawurlencode( '/wp/v2/posts/' . $rid . '/revisions' ), $admin );
ed_parity( "the app's revision fields", 'rest_route=' . rawurlencode( '/wp/v2/posts/' . $rid . '/revisions' ) . '&per_page=6&_fields=id,modified,author', $admin );
// The engine's own updates snapshot revisions the way core does.
[ $st ] = ed_fetch( $ENGINE, 'rest_route=' . rawurlencode( '/wp/v2/posts/' . $rid ), $admin, 'POST', '{"content":"v3 engine revision"}' );
check( 200 === $st, 'engine update snapshots a revision' );
[ , $b ] = ed_fetch( $REF, 'rest_route=' . rawurlencode( '/wp/v2/posts/' . $rid . '/revisions' ), $admin );
check( 2 === count( $b ?? array() ) && str_contains( $b[0]['content']['rendered'] ?? '', 'v3 engine revision' ), 'WordPress lists the engine-written revision' );
[ $st ] = ed_fetch( $ENGINE, 'rest_route=' . rawurlencode( '/wp/v2/posts/' . $rid ), $admin, 'POST', '{"content":"v3 engine revision"}' );
[ , $b ] = ed_fetch( $ENGINE, 'rest_route=' . rawurlencode( '/wp/v2/posts/' . $rid . '/revisions' ), $admin );
check( 2 === count( $b ?? array() ), 'a no-change update adds no revision' );
ed_parity( 'revisions parity after engine writes', 'rest_route=' . rawurlencode( '/wp/v2/posts/' . $rid . '/revisions' ), $admin );

[ $st, $b ] = ed_fetch( $ENGINE, 'rest_route=' . rawurlencode( '/wp/v2/posts/' . $rid ) . '&force=true', $admin, 'DELETE' );
check( 200 === $st && true === ( $b['deleted'] ?? null ), 'engine force delete cascades' );
[ , $b ] = ed_fetch( $REF, 'rest_route=' . rawurlencode( '/wp/v2/posts/' . $rid . '/revisions' ), $admin );
check( isset( $b['code'] ) && 'rest_post_invalid_parent' === $b['code'], 'no orphan revisions left behind' );

// 4. The edit lock: engine acquires, the OTHER user's minn_lock names the holder.
[ $st, $b ] = ed_fetch( $ENGINE, 'rest_route=' . rawurlencode( '/minn-admin/v1/posts/1/lock' ), $admin, 'POST', '{}' );
check( 200 === $st && true === ( $b['acquired'] ?? null ), 'engine acquires the edit lock' );
ed_parity( 'the other user sees the lock holder', 'rest_route=' . rawurlencode( '/wp/v2/posts/1' ) . '&context=edit&_fields=id,minn_lock', $editor );
[ , $b ] = ed_fetch( $ENGINE, 'rest_route=' . rawurlencode( '/wp/v2/posts/1' ) . '&context=edit&_fields=minn_lock', $editor );
check( 'admin' === ( $b['minn_lock']['name'] ?? '' ), 'minn_lock names the admin' );
[ $st, $b ] = ed_fetch( $ENGINE, 'rest_route=' . rawurlencode( '/minn-admin/v1/posts/1/unlock' ), $admin, 'POST', '{}' );
check( 200 === $st && true === ( $b['unlocked'] ?? null ), 'engine releases the lock' );
[ , $b ] = ed_fetch( $ENGINE, 'rest_route=' . rawurlencode( '/wp/v2/posts/1' ) . '&context=edit&_fields=minn_lock', $editor );
check( is_array( $b ) && array_key_exists( 'minn_lock', $b ) && null === $b['minn_lock'], 'the lock is gone for the other user' );

echo "\n$pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
