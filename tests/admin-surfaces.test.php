<?php
/**
 * The admin surfaces Minn Admin's Manage views ride: Structure (post types,
 * taxonomies, the terms switcher), Extensions (plugins, themes, the empty
 * update slots), System, sessions, the changelog and guide, appearance,
 * wp/v2 search + taxonomies, and the front-end Minn bar.
 *
 * Live parity against the reference running the real Minn Admin plugin on
 * the SAME database where the answer is site data; shape checks where the
 * engine answers for itself (System, the bar). SKIPs when the reference is down.
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

/** Pretty-URL request (query args survive) with an optional minted session. Returns [status, decoded, headers]. */
function as_fetch( string $base, string $route, ?array $mint, string $method = 'GET', ?string $body = null ): array {
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
			'http' => array( 'ignore_errors' => true, 'timeout' => 20, 'method' => $method, 'header' => implode( "\r\n", $headers ), 'content' => $body ?? '' ),
		)
	);
	$raw    = @file_get_contents( $base . '/wp-json' . $route, false, $ctx );
	$status = 0;
	$out    = array();
	foreach ( $http_response_header ?? array() as $h ) {
		if ( preg_match( '#^HTTP/\S+\s+(\d+)#', $h, $m ) ) {
			$status = (int) $m[1];
		} elseif ( str_contains( $h, ':' ) ) {
			[ $k, $v ]                  = explode( ':', $h, 2 );
			$out[ strtolower( trim( $k ) ) ] = trim( $v );
		}
	}
	return array( $status, json_decode( minn_test_neutralise( (string) $raw ), true ), $out );
}

function as_norm( $x ) {
	global $REF, $ENGINE;
	if ( is_array( $x ) ) {
		return array_map( 'as_norm', $x );
	}
	if ( is_string( $x ) ) {
		$x = str_replace( array( $REF, 'http://minn-engine.localhost', $ENGINE ), 'HOST', $x );
		return preg_replace( '/(\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}):\d{2}/', '$1', $x );
	}
	return $x;
}

/** @param list<string> $drop top-level keys (or item keys on a list) the two stacks answer differently by design */
function as_parity( string $label, string $route, ?array $mint, array $drop = array(), string $method = 'GET', ?string $body = null ): void {
	global $ENGINE, $REF;
	[ $rs, $rb ] = as_fetch( $REF, $route, $mint, $method, $body );
	[ $es, $eb ] = as_fetch( $ENGINE, $route, $mint, $method, $body );
	if ( $rs !== $es ) {
		check( false, $label, "status $rs vs $es" );
		return;
	}
	$strip = function ( $v ) use ( $drop, &$strip ) {
		if ( ! is_array( $v ) ) {
			return $v;
		}
		foreach ( $drop as $k ) {
			unset( $v[ $k ] );
		}
		return array_map( $strip, $v );
	};
	$d = minn_test_diff( as_norm( $strip( $rb ) ), as_norm( $strip( $eb ) ) );
	check( null === $d, $label, (string) $d );
}

function as_mint( int $uid ): array {
	global $ROOT;
	$mint = json_decode( (string) shell_exec( 'wp --path=' . escapeshellarg( "$ROOT/wp-reference" ) . ' eval-file ' . escapeshellarg( "$ROOT/tests/tools/mint-session.php" ) . " $uid 2>/dev/null" ), true );
	if ( ! $mint || empty( $mint['cookie'] ) ) {
		echo "SKIP: could not mint a reference session\n";
		exit( 0 );
	}
	return $mint;
}

$admin  = as_mint( 1 );
$author = as_mint( 3 );

echo "admin-surfaces suite: $ENGINE (engine) / $REF (reference)\n";

// 1. Structure.
as_parity( 'term-taxonomies matches for an admin', '/minn-admin/v1/term-taxonomies', $admin );
as_parity( 'term-taxonomies for an author (tags only need edit_posts to assign, not to manage)', '/minn-admin/v1/term-taxonomies', $author );
as_parity( 'post-types list matches (storage backends aside: the engine has none)', '/minn-admin/v1/post-types', $admin, array( 'backends' ) );
as_parity( 'taxonomies list matches', '/minn-admin/v1/taxonomies', $admin, array( 'backends' ) );
[ $s ] = as_fetch( $ENGINE, '/minn-admin/v1/post-types', $author );
check( 403 === $s, 'post-types needs manage_options', "status $s" );

// 2. wp/v2 taxonomies.
as_parity( 'wp/v2/taxonomies (view) matches', '/wp/v2/taxonomies', null );
as_parity( 'wp/v2/taxonomies?type=post matches', '/wp/v2/taxonomies?type=post', null );
as_parity( 'wp/v2/taxonomies?context=edit matches', '/wp/v2/taxonomies?context=edit', $admin );
as_parity( 'wp/v2/taxonomies/category matches', '/wp/v2/taxonomies/category', null );
as_parity( 'wp/v2/taxonomies/nope is 404 both sides', '/wp/v2/taxonomies/nope', null );

// 3. wp/v2 search.
foreach ( array( '/wp/v2/search?per_page=6&search=hello', '/wp/v2/search?per_page=6&_fields=id,title,url,type,subtype&search=doc', '/wp/v2/search?search=zzzznothing', '/wp/v2/search?type=post&subtype=page&search=doc', '/wp/v2/search?search=hello&per_page=1&page=2' ) as $q ) {
	as_parity( "search $q matches", $q, null );
}
[ , , $rh ] = as_fetch( $REF, '/wp/v2/search?search=hello', null );
[ , , $eh ] = as_fetch( $ENGINE, '/wp/v2/search?search=hello', null );
check( ( $rh['x-wp-total'] ?? '?' ) === ( $eh['x-wp-total'] ?? '!' ), 'search X-WP-Total matches', ( $rh['x-wp-total'] ?? '?' ) . ' vs ' . ( $eh['x-wp-total'] ?? '!' ) );

// 4. Sessions.
[ $s, $b ] = as_fetch( $ENGINE, '/minn-admin/v1/users/1/sessions', $admin );
$first     = $b['sessions'][0] ?? array();
check( 200 === $s && array( 'verifier', 'ip', 'ua', 'login', 'expiration', 'current' ) === array_keys( $first ), 'sessions list carries the plugin\'s item shape', json_encode( array_keys( $first ) ) );
$mine = array_values( array_filter( $b['sessions'] ?? array(), static fn( $x ) => $x['current'] ) );
check( 1 === count( $mine ) && 64 === strlen( $mine[0]['verifier'] ), 'the minted session is flagged current, once', count( $mine ) . ' current' );
$logins = array_column( $b['sessions'] ?? array(), 'login' );
$sorted = $logins;
rsort( $sorted );
check( $logins === $sorted, 'sessions sort newest first' );
[ $s ] = as_fetch( $ENGINE, '/minn-admin/v1/users/1/sessions', $author );
check( 403 === $s, 'an author cannot read another user\'s sessions', "status $s" );
[ $s ] = as_fetch( $ENGINE, '/minn-admin/v1/users/1/sessions/' . str_repeat( 'a', 64 ), $admin, 'DELETE' );
check( 404 === $s, 'unknown verifier is not_found', "status $s" );
// Destroy-all on yourself keeps the session you are using.
$before = count( $b['sessions'] );
[ $s ]  = as_fetch( $ENGINE, '/minn-admin/v1/users/1/sessions', $admin, 'DELETE' );
[ , $after ] = as_fetch( $ENGINE, '/minn-admin/v1/users/1/sessions', $admin );
check( 200 === $s && 1 === count( $after['sessions'] ?? array() ) && $after['sessions'][0]['current'], "sign out everywhere keeps the current session ($before before)", json_encode( $after ) );
[ $s, $rb ] = as_fetch( $REF, '/minn-admin/v1/users/1/sessions', $admin );
check( 200 === $s && 1 === count( $rb['sessions'] ?? array() ), 'WordPress reads the engine-written session store identically', json_encode( $rb ) );

// 5. Bundled documents, appearance, the update slots.
as_parity( 'changelog matches', '/minn-admin/v1/changelog', $admin );
as_parity( 'guide matches', '/minn-admin/v1/guide', $admin );
as_parity( 'plugin-updates matches (auto-updates are never offered here)', '/minn-admin/v1/plugin-updates', $admin, array( 'autoAllowed' ) );
as_parity( 'plugin-meta matches', '/minn-admin/v1/plugin-meta', $admin );
as_parity( 'translations matches', '/minn-admin/v1/translations', $admin );
as_parity( 'users/1/hidden matches', '/minn-admin/v1/users/1/hidden', $admin );
[ $s, $b ] = as_fetch( $ENGINE, '/wp/v2/users/me/application-passwords', $admin );
check( 200 === $s && array() === $b, 'application-passwords lists none', "status $s " . json_encode( $b ) );
as_parity( 'appearance matches apart from the two switches the engine keeps on', '/minn-admin/v1/me/appearance', $admin, array( 'defaultAdmin', 'frontBar' ) );
[ $s, $b ] = as_fetch( $ENGINE, '/minn-admin/v1/me/appearance', $admin, 'POST', '{"scheme":"ocean"}' );
[ , $rb ]  = as_fetch( $REF, '/minn-admin/v1/me/appearance', $admin );
check( 200 === $s && 'ocean' === ( $b['scheme'] ?? '' ) && true === ( $b['frontBar'] ?? null ), 'engine saves a scheme and reports the switches on', json_encode( $b ) );
check( 'ocean' === ( $rb['scheme'] ?? '' ) && ( $rb['custom']['dark']['bg'] ?? '' ) === ( $b['custom']['dark']['bg'] ?? '!' ), 'WordPress reads the engine-written appearance', json_encode( $rb ) );
as_fetch( $REF, '/minn-admin/v1/me/appearance', $admin, 'POST', '{"scheme":"minn"}' );
[ , $b ] = as_fetch( $ENGINE, '/minn-admin/v1/me/appearance', $admin );
check( 'minn' === ( $b['scheme'] ?? '' ), 'engine reads the WordPress-written appearance', json_encode( $b ) );

// 6. Extensions: themes and plugins.
[ $rs, $rb ] = as_fetch( $REF, '/minn-admin/v1/themes', $admin );
[ $es, $eb ] = as_fetch( $ENGINE, '/minn-admin/v1/themes', $admin );
$slim = static fn( array $t ) => array_intersect_key( $t, array_flip( array( 'stylesheet', 'name', 'version', 'author', 'active', 'parent', 'block' ) ) );
check( 200 === $rs && 200 === $es && array_map( $slim, $rb['themes'] ) === array_map( $slim, $eb['themes'] ), 'themes list matches on identity, order, parent, block', (string) minn_test_diff( array_map( $slim, $rb['themes'] ), array_map( $slim, $eb['themes'] ) ) );
check( array_keys( $rb['themes'][0] ) === array_keys( $eb['themes'][0] ), 'theme item keys match', json_encode( array_keys( $eb['themes'][0] ) ) );
[ $rs, $rb ] = as_fetch( $REF, '/wp/v2/plugins', $admin );
[ $es, $eb ] = as_fetch( $ENGINE, '/wp/v2/plugins', $admin );
$byFile = static fn( array $list ) => array_column( $list, null, 'plugin' );
$rmap   = $byFile( $rb );
$emap   = $byFile( $eb );
$missing = array_diff( array_keys( $rmap ), array_keys( $emap ) );
check( 200 === $rs && 200 === $es && array() === $missing, 'every WordPress plugin the reference lists is listed by the engine', json_encode( $missing ) );
$one = $rmap['minn-admin/minn-admin'] ?? null;
check( null !== $one && array_keys( $one ) === array_keys( $emap['minn-admin/minn-admin'] ?? array() ), 'plugin item keys match', json_encode( array_keys( $emap['minn-admin/minn-admin'] ?? array() ) ) );
check( null !== $one && $one['status'] === $emap['minn-admin/minn-admin']['status'] && $one['version'] === $emap['minn-admin/minn-admin']['version'], 'stored active state and version agree' );
$ext = array_values( array_filter( $eb, static fn( $p ) => str_starts_with( $p['plugin'], 'minn-block-visibility/' ) ) );
check( 1 === count( $ext ) && 'active' === $ext[0]['status'], 'Minn extensions join the list with their own activation state', json_encode( $ext ) );
[ $s, $b ] = as_fetch( $ENGINE, '/wp/v2/plugins/minn-simple-custom-css/minn-simple-custom-css', $admin, 'PUT', '{"status":"inactive"}' );
[ $s2, $b2 ] = as_fetch( $ENGINE, '/wp/v2/plugins/minn-simple-custom-css/minn-simple-custom-css', $admin, 'PUT', '{"status":"active"}' );
check( 'inactive' === ( $b['status'] ?? '' ) && 'active' === ( $b2['status'] ?? '' ), 'extension activation round-trips through minn_active_extensions', json_encode( array( $b['status'] ?? $s, $b2['status'] ?? $s2 ) ) );
[ $s ] = as_fetch( $ENGINE, '/wp/v2/plugins', $author );
check( 403 === $s, 'plugins need activate_plugins', "status $s" );

// 7. System: the engine's own diagnostics in the plugin's shape.
[ $s, $b ] = as_fetch( $ENGINE, '/minn-admin/v1/system', $admin );
check( 200 === $s && array( 'generated', 'checks', 'config', 'logs', 'licenses', 'extensions', 'integrations', 'groups' ) === array_keys( $b ), 'system carries the plugin\'s top-level keys', json_encode( array_keys( $b ) ) );
check( array( 'Minn Engine', 'PHP', 'Database', 'Server' ) === array_column( $b['groups'], 'title' ), 'system groups name the engine, not WordPress', json_encode( array_column( $b['groups'], 'title' ) ) );
check( 'engine' === ( $b['checks'][0]['key'] ?? '' ) && 'pass' === $b['checks'][0]['status'], 'the first health check is the engine itself' );
check( false === ( $b['config']['editable'] ?? true ) && 5 === count( $b['config']['constants'] ?? array() ), 'wp-config constants are reported read-only' );
check( isset( $b['groups'][2]['tables'] ) && isset( $b['groups'][2]['autoload']['top'] ), 'database group carries tables and autoload like the plugin' );
[ $s ] = as_fetch( $ENGINE, '/minn-admin/v1/system/config', $admin, 'POST', '{"constant":"WP_DEBUG","value":false}' );
check( 400 === $s, 'config writes are refused', "status $s" );
[ $s, $b ] = as_fetch( $ENGINE, '/minn-admin/v1/system/cron', $admin );
check( 200 === $s && array( 'now', 'disabled', 'items' ) === array_keys( $b ), 'system/cron shape' );
[ $s, $b ] = as_fetch( $ENGINE, '/minn-admin/v1/system/autoload', $admin );
check( 200 === $s && array( 'count', 'size', 'size_human', 'shown', 'items' ) === array_keys( $b ) && $b['count'] > 0, 'system/autoload shape' );
[ $s, $b ] = as_fetch( $ENGINE, '/minn-admin/v1/system/logs', $admin );
check( 200 === $s && isset( $b['sources'] ), 'system/logs shape' );
[ $s, $b ] = as_fetch( $ENGINE, '/minn-admin/v1/system/logs/debug', $admin );
check( 200 === $s && isset( $b['exists'], $b['path'], $b['content'], $b['id'] ), 'system/logs/debug reads the tail', json_encode( array_keys( $b ) ) );
[ $s ] = as_fetch( $ENGINE, '/minn-admin/v1/system/logs/nope', $admin );
check( 404 === $s, 'unknown log source is 404' );
[ $s ] = as_fetch( $ENGINE, '/minn-admin/v1/system', $author );
check( 403 === $s, 'system needs manage_options', "status $s" );

// 8. The Minn bar on the front end.
$ctx  = stream_context_create( array( 'ssl' => array( 'verify_peer' => false, 'verify_peer_name' => false ), 'http' => array( 'ignore_errors' => true, 'header' => 'Cookie: ' . $admin['cookie_name'] . '=' . $admin['cookie'] ) ) );
$html = (string) @file_get_contents( "$ENGINE/hello-world/", false, $ctx );
check( str_contains( $html, 'id="minn-bar-root"' ) && str_contains( $html, 'minn-front-bar' ), 'a signed-in editor gets the Minn bar and its body class' );
check( str_contains( $html, '/minn-admin-asset/assets/css/bar.css' ) && str_contains( $html, '/minn-admin-asset/assets/js/bar.js' ) && str_contains( $html, 'window.MINN_BAR = {' ), 'the bar loads the app bundle\'s own assets and config' );
check( str_contains( $html, 'minn-admin/editor/posts/1' ), 'the bar offers Edit for the post being viewed' );
$anon = (string) @file_get_contents( "$ENGINE/hello-world/", false, stream_context_create( array( 'ssl' => array( 'verify_peer' => false, 'verify_peer_name' => false ) ) ) );
check( ! str_contains( $anon, 'minn-bar-root' ) && ! str_contains( $anon, 'minn-front-bar' ), 'anonymous readers see no bar' );
$css = minn_test_fetch( "$ENGINE/minn-admin-asset/assets/css/bar.css" );
check( 200 === $css[0]['status'] && str_starts_with( $css[0]['content-type'] ?? '', 'text/css' ), 'bar.css is served', json_encode( $css[0] ) );

echo "\n$pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
