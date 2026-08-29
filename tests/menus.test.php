<?php
/**
 * Classic nav_menu storage: wp/v2/menus, menu-items, menu-locations
 * (reads) at live parity with the reference, and the front-end navigation
 * block falling back to those items when no wp_navigation post is
 * published. Ref: (cd wp-reference && php -S 127.0.0.1:8123) — SKIPs
 * cleanly when down.
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

function mn_mint( int $uid ): array {
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

function mn_fetch( string $base, string $query, ?array $mint ): array {
	$headers = array();
	if ( $mint ) {
		global $REF;
		$alt       = 'wordpress_logged_in_' . md5( $REF );
		$headers[] = 'Cookie: ' . $mint['cookie_name'] . '=' . $mint['cookie'] . '; ' . $alt . '=' . $mint['cookie'];
		$headers[] = 'X-WP-Nonce: ' . $mint['nonce'];
	}
	$ctx = stream_context_create(
		array(
			'ssl'  => array( 'verify_peer' => false, 'verify_peer_name' => false ),
			'http' => array(
				'ignore_errors' => true,
				'timeout'       => 10,
				'header'        => implode( "\r\n", $headers ),
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

function mn_norm( $x ) {
	global $ENGINE, $REF;
	if ( is_array( $x ) ) {
		return array_map( 'mn_norm', $x );
	}
	if ( is_string( $x ) ) {
		return str_replace( array( str_replace( '/', '\\/', $REF ), $REF ), $ENGINE, $x );
	}
	return $x;
}

function mn_parity( string $label, string $query, ?array $mint ): void {
	global $ENGINE, $REF;
	[ $rs, $rb ] = mn_fetch( $REF, $query, $mint );
	[ $es, $eb ] = mn_fetch( $ENGINE, $query, $mint );
	if ( $rs !== $es ) {
		check( false, $label, "status $rs vs $es " . json_encode( $eb ) );
		return;
	}
	$d = minn_test_diff( mn_norm( $rb ), mn_norm( $eb ) );
	check( null === $d, $label, (string) $d );
}

function mn_wp( string $command ): string {
	global $ROOT;
	return trim( (string) shell_exec( 'wp --path=' . escapeshellarg( "$ROOT/wp-reference" ) . " $command 2>/dev/null" ) );
}

register_shutdown_function( static function (): void {
	foreach ( preg_split( '/\s+/', trim( mn_wp( 'post list --post_type=wp_navigation --format=ids' ) ) ) ?: array() as $navId ) {
		if ( (int) $navId === 4 ) {
			mn_wp( 'post update 4 --post_status=publish' );
		} elseif ( (int) $navId > 0 ) {
			mn_wp( 'post delete ' . (int) $navId . ' --force' );
		}
	}
} );

echo "menus suite: $ENGINE (engine) / $REF (oracle)\n";

$admin  = mn_mint( 1 );
$author = mn_mint( 3 );

mn_parity( 'unauth menus 401', 'rest_route=' . rawurlencode( '/wp/v2/menus' ), null );
mn_parity( 'unauth menu-items 401', 'rest_route=' . rawurlencode( '/wp/v2/menu-items' ), null );
mn_parity( 'unauth menu-locations 401', 'rest_route=' . rawurlencode( '/wp/v2/menu-locations' ), null );
mn_parity( 'author can list menus', 'rest_route=' . rawurlencode( '/wp/v2/menus' ), $author );
mn_parity( 'admin menu-locations empty on a block theme', 'rest_route=' . rawurlencode( '/wp/v2/menu-locations' ), $admin );
mn_parity( 'missing menu 404', 'rest_route=' . rawurlencode( '/wp/v2/menus/999' ), $admin );
mn_parity( 'missing menu item 404', 'rest_route=' . rawurlencode( '/wp/v2/menu-items/999' ), $admin );

$menuId = (int) mn_wp( 'menu list --format=ids' );
if ( $menuId <= 0 ) {
	mn_wp( "menu create 'zz-minn-menu'" );
	mn_wp( 'menu item add-post zz-minn-menu 2 --title=' . escapeshellarg( 'Sample Page' ) );
	mn_wp( 'menu item add-term zz-minn-menu category 1' );
	$menuId = (int) mn_wp( 'menu list --format=ids' );
}
check( $menuId > 0, 'a classic menu exists for parity', (string) $menuId );

mn_parity( 'admin menus list', 'rest_route=' . rawurlencode( '/wp/v2/menus' ), $admin );
mn_parity( 'admin menus single', 'rest_route=' . rawurlencode( "/wp/v2/menus/{$menuId}" ), $admin );
mn_parity( 'admin menu-items list', 'rest_route=' . rawurlencode( '/wp/v2/menu-items' ), $admin );
mn_parity( 'admin menu-items for one menu', 'rest_route=' . rawurlencode( '/wp/v2/menu-items' ) . '&menus=' . $menuId, $admin );
mn_parity( 'author menus GET-only hints', 'rest_route=' . rawurlencode( "/wp/v2/menus/{$menuId}" ), $author );

$items = mn_fetch( $ENGINE, 'rest_route=' . rawurlencode( '/wp/v2/menu-items' ) . '&menus=' . $menuId, $admin )[1];
$first = is_array( $items ) && isset( $items[0]['id'] ) ? (int) $items[0]['id'] : 0;
if ( $first > 0 ) {
	mn_parity( 'admin menu-item single', 'rest_route=' . rawurlencode( "/wp/v2/menu-items/{$first}" ), $admin );
}

// Front: with no published wp_navigation, the header nav reads the classic menu.
// The reference may convert a classic menu into a wp_navigation post on view;
// draft every one so both stacks hit the storage fallback.
$navIds = preg_split( '/\s+/', trim( mn_wp( 'post list --post_type=wp_navigation --format=ids' ) ) ) ?: array();
foreach ( $navIds as $navId ) {
	if ( (int) $navId > 0 ) {
		mn_wp( 'post update ' . (int) $navId . ' --post_status=draft' );
	}
}
$ssl = stream_context_create( array( 'ssl' => array( 'verify_peer' => false, 'verify_peer_name' => false ) ) );
$engineHome = (string) file_get_contents( $ENGINE . '/', false, $ssl );
$oracleHome = (string) file_get_contents( $REF . '/' );
foreach ( $navIds as $navId ) {
	if ( (int) $navId > 0 ) {
		mn_wp( 'post update ' . (int) $navId . ' --post_status=publish' );
	}
}
$converted = preg_split( '/\s+/', trim( mn_wp( 'post list --post_type=wp_navigation --format=ids' ) ) ) ?: array();
foreach ( $converted as $navId ) {
	if ( (int) $navId > 0 && ! in_array( (string) $navId, $navIds, true ) ) {
		mn_wp( 'post delete ' . (int) $navId . ' --force' );
	}
}
check( str_contains( $engineHome, 'menu-item-type-post_type' ) && str_contains( $engineHome, 'Sample Page' ), 'engine header falls back to the classic menu' );
check( str_contains( $oracleHome, 'menu-item-type-post_type' ) && str_contains( $oracleHome, 'Sample Page' ), 'oracle header falls back to the classic menu' );
$engineNav = preg_match( '/<ul class="wp-block-navigation__container[^"]*">.*?<\/ul>/s', $engineHome, $em ) ? $em[0] : '';
$oracleNav = preg_match( '/<ul class="wp-block-navigation__container[^"]*">.*?<\/ul>/s', $oracleHome, $om ) ? $om[0] : '';
$oracleNav = str_replace( $REF, $ENGINE, $oracleNav );
check( $engineNav !== '' && $engineNav === $oracleNav, 'classic-fallback nav items match the oracle', substr( $engineNav, 0, 400 ) . "\n vs \n" . substr( $oracleNav, 0, 400 ) );

echo "\n$pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
