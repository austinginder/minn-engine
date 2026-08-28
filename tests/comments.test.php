<?php
/**
 * wp/v2/comments: reads (list tabs, single, contexts, refusals) in live
 * parity with the reference, and the write lifecycle proven CROSS-STACK —
 * engine-created comments read back through WordPress and vice versa,
 * moderation flips mirrored, trash bookkeeping, force delete.
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

function cm_mint( int $uid ): array {
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

function cm_fetch( string $base, string $query, ?array $mint, string $method = 'GET', ?string $body = null ): array {
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
	$raw     = @file_get_contents( "$base/?$query", false, $ctx );
	$status  = 0;
	$hdrs    = array();
	foreach ( $http_response_header ?? array() as $h ) {
		if ( preg_match( '#^HTTP/\S+\s+(\d+)#', $h, $m ) ) {
			$status = (int) $m[1];
		} elseif ( str_contains( $h, ':' ) ) {
			[ $k, $v ]                    = explode( ':', $h, 2 );
			$hdrs[ strtolower( trim( $k ) ) ] = trim( $v );
		}
	}
	return array( $status, json_decode( (string) $raw, true ), $hdrs );
}

/** Normalize reference-host URLs (and volatile IPs) before diffing. */
function cm_norm( $x ) {
	global $ENGINE, $REF;
	if ( is_array( $x ) ) {
		return array_map( 'cm_norm', $x );
	}
	if ( is_string( $x ) ) {
		$x = str_replace( array( str_replace( '/', '\\/', $REF ), $REF ), $ENGINE, $x );
		return in_array( $x, array( '127.0.0.1', '::1' ), true ) ? '«ip»' : $x;
	}
	return $x;
}

function cm_parity( string $label, string $query, ?array $mint, string $method = 'GET', ?string $body = null ): void {
	global $ENGINE, $REF;
	[ $rs, $rb ] = cm_fetch( $REF, $query, $mint, $method, $body );
	[ $es, $eb ] = cm_fetch( $ENGINE, $query, $mint, $method, $body );
	if ( $rs !== $es ) {
		check( false, $label, "status $rs vs $es" );
		return;
	}
	$d = minn_test_diff( cm_norm( $rb ), cm_norm( $eb ) );
	check( null === $d, $label, (string) $d );
}

/** Force-delete stray suite comments (id > 1) so fixtures never drift. */
function cm_cleanup(): void {
	global $ROOT;
	shell_exec(
		'wp --path=' . escapeshellarg( "$ROOT/wp-reference" ) . ' db query ' .
		escapeshellarg( 'DELETE FROM wp_comments WHERE comment_ID > 1; DELETE FROM wp_commentmeta WHERE comment_id > 1; UPDATE wp_posts SET comment_count = (SELECT COUNT(*) FROM wp_comments WHERE comment_post_ID = wp_posts.ID AND comment_approved = "1") WHERE ID > 0;' ) . ' 2>/dev/null'
	);
}
register_shutdown_function( 'cm_cleanup' );
cm_cleanup();

echo "comments suite: $ENGINE (engine) / $REF (oracle)\n";

$admin  = cm_mint( 1 );
$author = cm_mint( 3 );

$Q = 'rest_route=' . rawurlencode( '/wp/v2/comments' );

// 1. Read parity: contexts, tabs, refusals, invalid ids.
cm_parity( 'single view (anonymous)', $Q . '%2F1', null );
cm_parity( 'single edit context (admin)', $Q . '%2F1&context=edit', $admin );
cm_parity( 'single invalid id', $Q . '%2F999', $admin );
cm_parity( 'edit-context list (admin)', $Q . '&context=edit&status=approve&per_page=25&page=1', $admin );
cm_parity( 'list with _fields', $Q . '&context=edit&status=approve&_fields=id,author_name,content,date,post,link,author', $admin );
cm_parity( 'hold tab (author may query status)', $Q . '&status=hold', $author );
cm_parity( 'edit context refused (author)', $Q . '&context=edit', $author );
cm_parity( 'edit context refused (anonymous)', $Q . '&context=edit', null );
cm_parity( 'status param refused (anonymous)', $Q . '&status=hold', null );

[ , , $rh ] = cm_fetch( $REF, $Q . '&per_page=1', $admin );
[ , , $eh ] = cm_fetch( $ENGINE, $Q . '&per_page=1', $admin );
check(
	isset( $rh['x-wp-total'], $eh['x-wp-total'] ) && $rh['x-wp-total'] === $eh['x-wp-total'] && $rh['x-wp-totalpages'] === $eh['x-wp-totalpages'],
	'pagination headers agree',
	json_encode( array( $rh['x-wp-total'] ?? null, $eh['x-wp-total'] ?? null ) )
);

// 2. Engine creates; WordPress reads it back.
$content = "Engine reply: it's alive\n\nSecond paragraph\nwith a soft break";
[ $st, $created ] = cm_fetch( $ENGINE, $Q, $admin, 'POST', json_encode( array( 'post' => 1, 'parent' => 1, 'content' => $content ) ) );
check( 201 === $st && ! empty( $created['id'] ), 'engine creates a reply (201)' );
$cid = (int) ( $created['id'] ?? 0 );
[ $st, $oracle_read ] = cm_fetch( $REF, $Q . '%2F' . $cid . '&context=edit', $admin );
check( 200 === $st, 'WordPress can read the engine-written comment' );
$d = minn_test_diff( cm_norm( $oracle_read ), cm_norm( $created ) );
check( null === $d, 'create response equals the WordPress read-back', (string) $d );
check(
	str_contains( $oracle_read['content']['rendered'] ?? '', 'it&#8217;s alive' )
	&& str_contains( $oracle_read['content']['rendered'] ?? '', "<br />\n" ),
	'rendered pipeline matches (texturize + paragraphs + soft breaks)',
	(string) ( $oracle_read['content']['rendered'] ?? '' )
);

// 3. Moderation flips mirror across stacks.
[ $st, $b ] = cm_fetch( $ENGINE, $Q . '%2F' . $cid, $admin, 'POST', '{"status":"hold"}' );
check( 200 === $st && 'hold' === ( $b['status'] ?? '' ), 'engine flips to hold' );
[ , $b ] = cm_fetch( $REF, $Q . '%2F' . $cid . '&context=edit', $admin );
check( 'hold' === ( $b['status'] ?? '' ), 'WordPress sees the hold' );
[ $st, $b ] = cm_fetch( $REF, $Q . '%2F' . $cid, $admin, 'POST', '{"status":"approved"}' );
check( 200 === $st && 'approved' === ( $b['status'] ?? '' ), 'WordPress flips back to approved' );
[ , $b ] = cm_fetch( $ENGINE, $Q . '%2F' . $cid . '&context=edit', $admin );
check( 'approved' === ( $b['status'] ?? '' ), 'engine sees the approval' );

// 4. Trash keeps core's bookkeeping; force delete reports the previous state.
[ $st, $b ] = cm_fetch( $ENGINE, $Q . '%2F' . $cid, $admin, 'DELETE' );
check( 200 === $st && 'trash' === ( $b['status'] ?? '' ), 'engine trashes without force' );
[ , $b ] = cm_fetch( $REF, $Q . '%2F' . $cid . '&context=edit', $admin );
check( 'trash' === ( $b['status'] ?? '' ), 'WordPress sees the trash' );
$meta = shell_exec(
	'wp --path=' . escapeshellarg( "$ROOT/wp-reference" ) . ' db query ' .
	escapeshellarg( "SELECT meta_key FROM wp_commentmeta WHERE comment_id = $cid ORDER BY meta_key" ) . ' 2>/dev/null'
);
check(
	str_contains( (string) $meta, '_wp_trash_meta_status' ) && str_contains( (string) $meta, '_wp_trash_meta_time' ),
	'trash writes core\'s restore bookkeeping'
);
[ $st, $b ] = cm_fetch( $ENGINE, $Q . '%2F' . $cid . '&force=true', $admin, 'DELETE' );
check( 200 === $st && true === ( $b['deleted'] ?? null ) && 'trash' === ( $b['previous']['status'] ?? '' ), 'force delete returns deleted+previous' );
[ $st ] = cm_fetch( $REF, $Q . '%2F' . $cid, $admin );
check( 404 === $st, 'WordPress confirms the comment is gone' );

// 5. The reverse round trip: WordPress creates, the engine reads and deletes.
[ $st, $wp_created ] = cm_fetch( $REF, $Q, $admin, 'POST', json_encode( array( 'post' => 5, 'content' => 'Oracle-authored comment' ) ) );
check( 201 === $st && ! empty( $wp_created['id'] ), 'WordPress creates a comment' );
$wid = (int) ( $wp_created['id'] ?? 0 );
[ $st, $engine_read ] = cm_fetch( $ENGINE, $Q . '%2F' . $wid . '&context=edit', $admin );
check( 200 === $st, 'engine reads the WordPress-written comment' );
$d = minn_test_diff( cm_norm( $wp_created ), cm_norm( $engine_read ) );
check( null === $d, 'WordPress create equals the engine read-back', (string) $d );
[ $st, $b ] = cm_fetch( $ENGINE, $Q . '%2F' . $wid . '&force=true', $admin, 'DELETE' );
check( 200 === $st && true === ( $b['deleted'] ?? null ), 'engine force-deletes it' );

// 6. Write refusals agree.
cm_parity( 'author cannot moderate', $Q . '%2F1', $author, 'POST', '{"status":"hold"}' );
cm_parity( 'anonymous cannot create', $Q, null, 'POST', '{"post":1,"content":"nope"}' );

// 7. The fixture comment count survived the whole ride.
[ , , $rh ] = cm_fetch( $REF, $Q . '&per_page=1', $admin );
check( '1' === ( $rh['x-wp-total'] ?? '' ), 'fixture state restored (one approved comment)' );

echo "\n$pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
