<?php
/**
 * wp/v2/users management: edit-context list/single parity, ordering, and
 * the full lifecycle proven cross-stack — an engine-created user carries a
 * real WordPress password hash (they can sign in through WordPress), role
 * edits mirror, and delete reassigns authorship.
 *
 * Ref: (its Cove twin: cove twin minn add --as-site=ref.minn.localhost) — SKIPs cleanly when down.
 */

$ENGINE = 'https://minn.localhost';
$REF    = 'https://ref.minn.localhost';
$ROOT   = dirname( __DIR__ );

require_once __DIR__ . '/lib.php';

[ $ph ] = minn_test_fetch( "$REF/?rest_route=/wp/v2/posts", 3 );
if ( 200 !== $ph['status'] ) {
	echo "SKIP: reference WordPress not running at $REF (its Cove twin: cove twin minn add --as-site=ref.minn.localhost)\n";
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

function us_mint( int $uid ): array {
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

function us_fetch( string $base, string $query, ?array $mint, string $method = 'GET', ?string $body = null ): array {
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

function us_norm( $x ) {
	global $ENGINE, $REF;
	if ( is_array( $x ) ) {
		return array_map( 'us_norm', $x );
	}
	if ( is_string( $x ) ) {
		return str_replace( array( str_replace( '/', '\\/', $REF ), $REF ), $ENGINE, $x );
	}
	return $x;
}

function us_parity( string $label, string $query, ?array $mint, string $method = 'GET', ?string $body = null ): void {
	global $ENGINE, $REF;
	[ $rs, $rb ] = us_fetch( $REF, $query, $mint, $method, $body );
	[ $es, $eb ] = us_fetch( $ENGINE, $query, $mint, $method, $body );
	if ( $rs !== $es ) {
		check( false, $label, "status $rs vs $es" );
		return;
	}
	$d = minn_test_diff( us_norm( $rb ), us_norm( $eb ) );
	check( null === $d, $label, (string) $d );
}

/** Remove suite users (id > 3) and any posts they still own. */
function us_cleanup(): void {
	global $ROOT;
	shell_exec(
		'cd ' . escapeshellarg( minn_test_site_root() . '/wp-reference' ) .
		' && for id in $(wp user list --field=ID 2>/dev/null); do [ "$id" -gt 3 ] && wp user delete "$id" --reassign=1 --yes 2>/dev/null; done; true'
	);
}
register_shutdown_function( 'us_cleanup' );
us_cleanup();

echo "users suite: $ENGINE (engine) / $REF (oracle)\n";

$admin  = us_mint( 1 );
$editor = us_mint( 2 );
$author = us_mint( 3 );
$Q      = 'rest_route=' . rawurlencode( '/wp/v2/users' );

// 1. The Users view's own queries, plus ordering and gating.
us_parity( "the app's list query", $Q . '&context=edit&per_page=50&orderby=name&order=asc&_fields=id,name,email,roles,registered_date,avatar_urls,minn_switch_url&page=1', $admin );
us_parity( 'edit list full objects', $Q . '&context=edit&per_page=100', $admin );
us_parity( 'orderby registered_date desc', $Q . '&context=edit&orderby=registered_date&order=desc&_fields=id,name', $admin );
us_parity( 'include filter', $Q . '&context=edit&include=1,3&_fields=id,name', $admin );
us_parity( 'exclude filter', $Q . '&exclude=1&_fields=id', $admin );
us_parity( 'slug filter', $Q . '&slug=admin&_fields=id,slug', null );
us_parity( 'search login', $Q . '&search=admin&_fields=id,slug', null );
us_parity( 'search display name', $Q . '&search=Erin&_fields=id,name', null );
us_parity( 'edit list refused (editor)', $Q . '&context=edit', $editor );
us_parity( 'edit list refused (anonymous)', $Q . '&context=edit', null );
us_parity( 'single edit self (author)', $Q . '%2F3&context=edit', $author );
us_parity( 'single edit other refused (author)', $Q . '%2F1&context=edit', $author );

// 2. Engine creates a user; WordPress reads it back and signs them in.
[ $st, $created ] = us_fetch( $ENGINE, $Q, $admin, 'POST', json_encode( array(
	'username' => 'engine-user',
	'email'    => 'engine-user@minn-engine.localhost',
	'password' => 'engine-user-pass-1',
	'roles'    => array( 'author' ),
	'name'     => 'Engine User',
) ) );
check( 201 === $st && ! empty( $created['id'] ), 'engine creates a user (201)' );
$nid = (int) ( $created['id'] ?? 0 );
[ $st, $back ] = us_fetch( $REF, $Q . '%2F' . $nid . '&context=edit', $admin );
$d = minn_test_diff( us_norm( $back ), us_norm( $created ) );
check( 200 === $st && null === $d, 'WordPress reads the engine-created user identically', (string) $d );

// The engine-written password hash verifies through WordPress's login.
$ctx = stream_context_create(
	array(
		'http' => array(
			'ignore_errors' => true,
			'timeout'       => 10,
			'method'        => 'POST',
			'header'        => 'Content-Type: application/x-www-form-urlencoded',
			'content'       => http_build_query( array( 'log' => 'engine-user', 'pwd' => 'engine-user-pass-1', 'wp-submit' => 'Log In' ) ),
			'follow_location' => 0,
		),
	)
);
@file_get_contents( "$REF/wp-login.php", false, $ctx );
$set_cookie = implode( "\n", preg_grep( '/^Set-Cookie: wordpress_logged_in_/i', $http_response_header ?? array() ) );
check( '' !== $set_cookie, 'WordPress signs the engine-created user in (hash scheme verified)' );

// 3. Edits mirror across stacks, including a role change.
[ $st, $b ] = us_fetch( $ENGINE, $Q . '%2F' . $nid, $admin, 'POST', '{"name":"Renamed User","roles":["editor"]}' );
check( 200 === $st && 'Renamed User' === ( $b['name'] ?? '' ) && array( 'editor' ) === ( $b['roles'] ?? array() ), 'engine renames and promotes' );
[ , $b ] = us_fetch( $REF, $Q . '%2F' . $nid . '&context=edit', $admin );
check( 'Renamed User' === ( $b['name'] ?? '' ) && array( 'editor' ) === ( $b['roles'] ?? array() ) && true === ( $b['capabilities']['edit_others_posts'] ?? false ), 'WordPress sees the promotion (capabilities included)' );
[ $st ] = us_fetch( $REF, $Q . '%2F' . $nid, $admin, 'POST', '{"first_name":"Oracle"}' );
check( 200 === $st, 'WordPress edits the profile' );
[ , $b ] = us_fetch( $ENGINE, $Q . '%2F' . $nid . '&context=edit', $admin );
check( 'Oracle' === ( $b['first_name'] ?? '' ), 'engine sees the WordPress edit' );
// A role change the caller may not make is refused before anything else in the body lands.
$author = us_mint( 3 );
$refused = array();
foreach ( array( $ENGINE => 'engine', $REF => 'reference' ) as $base => $side ) {
	[ $st, $b ] = us_fetch( $base, $Q . '%2F3', $author, 'POST', '{"name":"Scribe Elevated","roles":["administrator"]}' );
	[ , $after ] = us_fetch( $base, $Q . '%2F3&context=edit', $author );
	$refused[ $side ] = array( $st, $b['code'] ?? '', $after['name'] ?? '', $after['roles'] ?? array() );
}
check( $refused['engine'] === $refused['reference'] && 403 === $refused['engine'][0] && 'scribe' === $refused['engine'][2], 'an author asking for a role is refused before the rename is written, on both stacks', json_encode( $refused ) );

// 4. Delete: refusal ladder, then reassignment of authorship.
us_parity( 'delete without reassign rejected', $Q . '%2F' . $nid, $admin, 'DELETE' );
us_parity( 'delete without force rejected', $Q . '%2F' . $nid . '&reassign=1', $admin, 'DELETE' );
global $ROOT;
$pid = (int) trim( (string) shell_exec(
	'cd ' . escapeshellarg( minn_test_site_root() . '/wp-reference' ) . ' && wp post create --post_title="Reassign probe" --post_status=draft --post_author=' . $nid . ' --porcelain 2>/dev/null'
) );
check( $pid > 0, 'probe post created for reassignment' );
[ $st, $b ] = us_fetch( $ENGINE, $Q . '%2F' . $nid . '&force=true&reassign=3', $admin, 'DELETE' );
check( 200 === $st && true === ( $b['deleted'] ?? null ) && 'engine-user' === ( $b['previous']['username'] ?? '' ), 'engine deletes with previous' );
$owner = (int) trim( (string) shell_exec(
	'cd ' . escapeshellarg( minn_test_site_root() . '/wp-reference' ) . ' && wp post get ' . $pid . ' --field=post_author 2>/dev/null'
) );
check( 3 === $owner, 'authorship reassigned to user 3' );
[ $st ] = us_fetch( $REF, $Q . '%2F' . $nid . '&context=edit', $admin );
check( 404 === $st, 'WordPress confirms the user is gone' );
shell_exec( 'cd ' . escapeshellarg( minn_test_site_root() . '/wp-reference' ) . ' && wp post delete ' . $pid . ' --force 2>/dev/null' );

// 5. Creation refusals agree.
us_parity( 'editor cannot create users', $Q, $editor, 'POST', '{"username":"x","email":"x@x.test","password":"pw"}' );
us_parity( 'duplicate username rejected', $Q, $admin, 'POST', '{"username":"admin","email":"fresh@minn-engine.localhost","password":"pw-123456"}' );

echo "\n$pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
