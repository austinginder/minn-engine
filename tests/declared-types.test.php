<?php
/**
 * Extra post types declared in minn.json: they join wp/v2/types and get a
 * collection at their rest_base, at live parity with a WordPress plugin
 * that register_post_type's the same slug. Ref: the Cove twin (cove twin minn add --as-site=ref.minn.localhost).
 */

$ENGINE = 'https://minn.localhost';
$REF    = 'https://ref.minn.localhost';
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
	foreach ( preg_split( '/\s+/', dt_wp( 'post list --post_type=zz_note,zz_entry --format=ids' ) ) ?: array() as $id ) {
		if ( (int) $id > 0 ) {
			dt_wp( 'post delete ' . (int) $id . ' --force' );
		}
	}
} );

echo "declared-types suite: $ENGINE (engine) / $REF (oracle)\n";

dt_wp( 'plugin activate minn-test-types' );
dt_wp( 'option update minn_active_extensions \'["minn-block-visibility","minn-test-types"]\'' );

// The index lists the declared type under its rest_base, as the reference lists a registered type, never as a catch-all.
[ $s, $index ] = dt_fetch( $ENGINE, 'rest_route=/', null );
check( 200 === $s && isset( $index['routes']['/wp/v2/zz-note'], $index['routes']['/wp/v2/zz-note/(?P<id>[\d]+)'] ), 'the index advertises the declared type under its rest_base, spelled as the reference spells it', implode( ' ', array_filter( array_keys( $index['routes'] ?? array() ), static fn ( $r ) => str_contains( $r, 'zz-note' ) || str_contains( $r, '<base>' ) ) ) );
check( isset( $index['routes']['/wp/v2/zz-note']['_links']['self'][0]['href'] ) && str_ends_with( $index['routes']['/wp/v2/zz-note']['_links']['self'][0]['href'], '/wp/v2/zz-note' ), 'the declared list route carries a self link' );
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

// The front: a declared type with an archive under its own prefix, and a hierarchical taxonomy beside it,
// answer as a plugin's registrations do on the reference. Both stacks share the site's stored rewrite rules.
dt_wp( 'option delete rewrite_rules' );
minn_test_fetch( "$ENGINE/zz-wiki/" );
$engine_rules = dt_wp( 'option get rewrite_rules --format=json' );
dt_wp( 'rewrite flush' );
$ref_rules = dt_wp( 'option get rewrite_rules --format=json' );
$wiki_rules = array_filter( (array) json_decode( $engine_rules, true ), static fn ( $q, $r ) => str_starts_with( (string) $r, 'zz-wiki' ), ARRAY_FILTER_USE_BOTH );
check( $engine_rules !== '' && $engine_rules === $ref_rules, 'the engine makes and stores the same rewrite rules as WordPress, the declared ones among them (' . count( $wiki_rules ) . ' under zz-wiki/)', (string) minn_test_diff( json_decode( $ref_rules, true ), json_decode( $engine_rules, true ) ) );
$keys = array_keys( $wiki_rules );
check( array_search( 'zz-wiki/topic/(.+?)/?$', $keys, true ) < array_search( 'zz-wiki/([^/]+)(?:/([0-9]+))?/?$', $keys, true ), 'the taxonomy\'s rules come before the type\'s, so zz-wiki/topic/… is a term', implode( ' ', array_slice( $keys, 0, 8 ) ) );

[ $st, $topic ] = dt_fetch( $ENGINE, 'rest_route=' . rawurlencode( '/wp/v2/zz-topic' ), $admin, 'POST', json_encode( array( 'name' => 'Zz Music', 'description' => 'Tunes.' ) ) );
check( 201 === $st && ( $topic['taxonomy'] ?? '' ) === 'zz_topic', 'the engine creates a zz_topic term over REST', json_encode( $topic ) );
$tid = (int) ( $topic['id'] ?? 0 );
[ $st, $entry ] = dt_fetch( $ENGINE, 'rest_route=' . rawurlencode( '/wp/v2/zz-entry' ), $admin, 'POST', json_encode( array( 'title' => 'Zz Musicblocks', 'status' => 'publish', 'content' => '<!-- wp:paragraph --><p>Every tune.</p><!-- /wp:paragraph -->', 'zz-topic' => array( $tid ) ) ) );
check( 201 === $st && in_array( $tid, (array) ( $entry['zz-topic'] ?? array() ), true ), 'the engine creates a zz_entry in that topic', json_encode( $entry ) );
$eid = (int) ( $entry['id'] ?? 0 );
check( str_ends_with( (string) ( $entry['link'] ?? '' ), '/zz-wiki/zz-musicblocks/' ), 'an entry\'s link is under the declared prefix', (string) ( $entry['link'] ?? '' ) );
dt_parity( 'the entry read back as the reference reads it', 'rest_route=' . rawurlencode( '/wp/v2/zz-entry/' . $eid ) );
dt_parity( 'the term read back as the reference reads it', 'rest_route=' . rawurlencode( '/wp/v2/zz-topic/' . $tid ) );
dt_parity( 'the taxonomy as the reference describes it', 'rest_route=' . rawurlencode( '/wp/v2/taxonomies/zz_topic' ) );
// Pages are compared as the theme suites compare them: status, title, body classes, and the body (theme_body).
$theme_src = (string) file_get_contents( __DIR__ . '/theme.test.php' );
foreach ( array( 'theme_body', 'theme_first_diff' ) as $fn ) {
	preg_match( '/function ' . $fn . '.*?\n}\n/s', $theme_src, $m );
	eval( $m[0] );
}
$page_of = static function ( string $html, string $host ) use ( $ENGINE ): array {
	preg_match( '/<title>(.*?)<\/title>/s', $html, $t );
	preg_match( '/<body class="([^"]*)"/', $html, $b );
	return array( $t[1] ?? '', $b[1] ?? '', theme_body( $html, $host, $ENGINE ) );
};
foreach ( array( 'the entry' => '/zz-wiki/zz-musicblocks/', 'the archive' => '/zz-wiki/', 'the topic' => '/zz-wiki/topic/zz-music/', 'an entry that is not there' => '/zz-wiki/zz-nothing/' ) as $label => $path ) {
	[ $rh, $rb ] = minn_test_fetch( $REF . $path );
	[ $eh, $eb ] = minn_test_fetch( $ENGINE . $path );
	[ $rt, $rc, $rbody ] = $page_of( $rb, $REF );
	[ $et, $ec, $ebody ] = $page_of( $eb, $ENGINE );
	$detail = $rh['status'] !== $eh['status'] ? "status {$rh['status']} vs {$eh['status']}" : ( $rt !== $et ? "title '$rt' vs '$et'" : ( $rc !== $ec ? "body class '$rc' vs '$ec'" : theme_first_diff( $ebody, $rbody ) ) );
	check( $rh['status'] === $eh['status'] && $rt === $et && $rc === $ec && $rbody === $ebody && $rbody !== '', "front: {$label} ({$path}) answers as the reference: {$eh['status']}, {$et}", $detail );
}
// Revisions and autosaves under a declared type, at its rest_base, as WordPress registers them per type.
foreach ( array( 'engine' => $ENGINE, 'reference' => $REF ) as $side => $base_url ) {
	[ , $idx ] = dt_fetch( $base_url, 'rest_route=/', null );
	$routes = $idx['routes'] ?? array();
	check( isset( $routes['/wp/v2/zz-entry/(?P<parent>[\d]+)/revisions'], $routes['/wp/v2/zz-entry/(?P<id>[\d]+)/autosaves'] ) && ! isset( $routes['/wp/v2/zz-topic/(?P<parent>[\d]+)/revisions'] ), "{$side}: the index lists revisions and autosaves under the declared type, never under its taxonomy", implode( ' ', array_filter( array_keys( $routes ), static fn ( $r ) => str_contains( $r, 'zz-' ) ) ) );
}
dt_fetch( $ENGINE, 'rest_route=' . rawurlencode( '/wp/v2/zz-entry/' . $eid ), $admin, 'POST', json_encode( array( 'content' => '<!-- wp:paragraph --><p>Every tune, again.</p><!-- /wp:paragraph -->' ) ) );
dt_parity( 'the entry\'s revisions as the reference lists them', 'rest_route=' . rawurlencode( '/wp/v2/zz-entry/' . $eid . '/revisions' ) . '&context=edit', $admin );
dt_parity( 'the entry\'s autosaves as the reference lists them (none yet)', 'rest_route=' . rawurlencode( '/wp/v2/zz-entry/' . $eid . '/autosaves' ) . '&context=edit', $admin );
[ $st, $auto ] = dt_fetch( $ENGINE, 'rest_route=' . rawurlencode( '/wp/v2/zz-entry/' . $eid . '/autosaves' ), $admin, 'POST', json_encode( array( 'title' => 'Zz Musicblocks', 'content' => '<!-- wp:paragraph --><p>Every tune, autosaved.</p><!-- /wp:paragraph -->' ) ) );
check( 200 === $st && ( $auto['parent'] ?? 0 ) === $eid, 'the engine saves an autosave of a published entry', json_encode( $auto ) );
dt_parity( 'the autosave read back as the reference reads it', 'rest_route=' . rawurlencode( '/wp/v2/zz-entry/' . $eid . '/autosaves' ) . '&context=edit', $admin );
dt_parity( 'a revisions route under a taxonomy\'s base answers as the reference does', 'rest_route=' . rawurlencode( '/wp/v2/zz-topic/' . $tid . '/revisions' ), $admin );
dt_fetch( $ENGINE, 'rest_route=' . rawurlencode( '/wp/v2/zz-entry/' . $eid ) . '&force=true', $admin, 'DELETE' );
dt_fetch( $ENGINE, 'rest_route=' . rawurlencode( '/wp/v2/zz-topic/' . $tid ) . '&force=true', $admin, 'DELETE' );

echo "\n$pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
