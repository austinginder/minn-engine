<?php
/**
 * Classic nav_menu storage: wp/v2/menus, menu-items, menu-locations
 * (reads) at live parity with the reference, and the front-end navigation
 * block falling back to those items when no wp_navigation post is
 * published. Ref: (cd wp-reference && php -S 127.0.0.1:8123) — SKIPs
 * cleanly when down.
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

function mn_mint( int $uid ): array {
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

function mn_fetch( string $base, string $query, ?array $mint, string $method = 'GET', ?string $body = null ): array {
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
	return trim( (string) shell_exec( 'wp --path=' . escapeshellarg( minn_test_site_root() . '/wp-reference' ) . " $command 2>/dev/null" ) );
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

$MQ = 'rest_route=' . rawurlencode( '/wp/v2/menus' );
$IQ = 'rest_route=' . rawurlencode( '/wp/v2/menu-items' );

[ $st, $refused ] = mn_fetch( $ENGINE, $MQ, $author, 'POST', json_encode( array( 'name' => 'zz-nope' ) ) );
check( 403 === $st && 'rest_cannot_create' === ( $refused['code'] ?? '' ), 'author POST menus is rest_cannot_create' );
[ $st, $missing ] = mn_fetch( $ENGINE, $MQ, $admin, 'POST', '{}' );
check( 400 === $st && 'rest_missing_callback_param' === ( $missing['code'] ?? '' ), 'POST menus without name is missing param' );
[ $st, $empty ] = mn_fetch( $ENGINE, $MQ, $admin, 'POST', '{"name":""}' );
check( 400 === $st && 'empty_term_name' === ( $empty['code'] ?? '' ), 'POST menus empty name is empty_term_name' );

[ $st, $created ] = mn_fetch( $ENGINE, $MQ, $admin, 'POST', json_encode( array( 'name' => 'zz-write-menu' ) ) );
check( 201 === $st && ! empty( $created['id'] ), 'engine creates a menu (201)', json_encode( $created ) );
$wid = (int) ( $created['id'] ?? 0 );
[ $st, $oracle_read ] = mn_fetch( $REF, $MQ . '%2F' . $wid, $admin );
check( 200 === $st, 'WordPress can read the engine-written menu' );
$d = minn_test_diff( mn_norm( $oracle_read ), mn_norm( $created ) );
check( null === $d, 'create menu response equals the WordPress read-back', (string) $d );

[ $st, $dup ] = mn_fetch( $ENGINE, $MQ, $admin, 'POST', json_encode( array( 'name' => 'zz-write-menu' ) ) );
check( 400 === $st && 'menu_exists' === ( $dup['code'] ?? '' ) && ( $dup['data']['term_id'] ?? 0 ) === $wid, 'duplicate menu name is menu_exists' );

[ $st, $renamed ] = mn_fetch( $ENGINE, $MQ . '%2F' . $wid, $admin, 'POST', json_encode( array( 'name' => 'zz-write-menu-renamed', 'description' => 'A description' ) ) );
check( 200 === $st && 'zz-write-menu-renamed' === ( $renamed['name'] ?? '' ) && 'A description' === ( $renamed['description'] ?? '' ), 'engine renames a menu' );
[ , $oracle_read ] = mn_fetch( $REF, $MQ . '%2F' . $wid, $admin );
check( 'zz-write-menu-renamed' === ( $oracle_read['name'] ?? '' ), 'WordPress sees the rename' );

[ $st, $item ] = mn_fetch( $ENGINE, $IQ, $admin, 'POST', json_encode( array(
	'title'  => 'Custom One',
	'url'    => 'https://example.com/one',
	'menus'  => $wid,
	'status' => 'publish',
) ) );
check( 201 === $st && ! empty( $item['id'] ) && 'Custom One' === ( $item['title']['raw'] ?? '' ), 'engine creates a custom menu item', json_encode( $item ) );
$iid = (int) ( $item['id'] ?? 0 );
[ $st, $oracle_item ] = mn_fetch( $REF, $IQ . '%2F' . $iid . '&context=edit', $admin );
check( 200 === $st, 'WordPress can read the engine-written item' );
$d = minn_test_diff( mn_norm( $oracle_item ), mn_norm( $item ) );
check( null === $d, 'create item response equals the WordPress edit read-back', (string) $d );

[ $st, $pageItem ] = mn_fetch( $ENGINE, $IQ, $admin, 'POST', json_encode( array(
	'title'     => 'Sample',
	'object'    => 'page',
	'object_id' => 2,
	'type'      => 'post_type',
	'menus'     => $wid,
	'status'    => 'publish',
) ) );
check( 201 === $st && 'page' === ( $pageItem['object'] ?? '' ) && 2 === (int) ( $pageItem['object_id'] ?? 0 ), 'engine creates a page menu item' );
$pid = (int) ( $pageItem['id'] ?? 0 );

[ $st, $moved ] = mn_fetch( $ENGINE, $IQ . '%2F' . $iid, $admin, 'POST', json_encode( array( 'menu_order' => 5, 'parent' => $pid ) ) );
check( 200 === $st && 5 === (int) ( $moved['menu_order'] ?? 0 ) && $pid === (int) ( $moved['parent'] ?? 0 ), 'engine reorders and parents an item' );
[ , $oracle_item ] = mn_fetch( $REF, $IQ . '%2F' . $iid . '&context=edit', $admin );
check( 5 === (int) ( $oracle_item['menu_order'] ?? 0 ) && $pid === (int) ( $oracle_item['parent'] ?? 0 ), 'WordPress sees the reorder' );

[ $st, $retitled ] = mn_fetch( $ENGINE, $IQ . '%2F' . $iid, $admin, 'POST', json_encode( array( 'title' => 'Custom One edited', 'url' => 'https://example.com/one-edited' ) ) );
check( 200 === $st && 'Custom One edited' === ( $retitled['title']['raw'] ?? '' ), 'engine retitles a custom item' );

[ $st, $noTitle ] = mn_fetch( $ENGINE, $IQ, $admin, 'POST', json_encode( array( 'url' => 'https://example.com/bare', 'menus' => $wid ) ) );
check( 400 === $st && 'rest_title_required' === ( $noTitle['code'] ?? '' ), 'custom item without title is rest_title_required' );

[ $st, $authorItem ] = mn_fetch( $ENGINE, $IQ, $author, 'POST', json_encode( array( 'title' => 'Author item', 'url' => 'https://example.com/a', 'menus' => $wid, 'status' => 'publish' ) ) );
check( 403 === $st && 'rest_cannot_create' === ( $authorItem['code'] ?? '' ), 'author cannot create menu items' );

[ $st, $noForce ] = mn_fetch( $ENGINE, $IQ . '%2F' . $iid, $admin, 'DELETE' );
check( 501 === $st && 'rest_trash_not_supported' === ( $noForce['code'] ?? '' ), 'DELETE item without force is 501' );
[ $st, $deletedItem ] = mn_fetch( $ENGINE, $IQ . '%2F' . $iid . '&force=true', $admin, 'DELETE' );
check( 200 === $st && true === ( $deletedItem['deleted'] ?? null ) && 'Custom One edited' === ( $deletedItem['previous']['title']['rendered'] ?? '' ), 'force-delete item returns previous' );
[ $st ] = mn_fetch( $REF, $IQ . '%2F' . $iid, $admin );
check( 404 === $st, 'WordPress confirms the item is gone' );

[ $st, $noForceMenu ] = mn_fetch( $ENGINE, $MQ . '%2F' . $wid, $admin, 'DELETE' );
check( 501 === $st && 'rest_trash_not_supported' === ( $noForceMenu['code'] ?? '' ), 'DELETE menu without force is 501' );
[ $st, $deletedMenu ] = mn_fetch( $ENGINE, $MQ . '%2F' . $wid . '&force=true', $admin, 'DELETE' );
check( 200 === $st && true === ( $deletedMenu['deleted'] ?? null ) && 'zz-write-menu-renamed' === ( $deletedMenu['previous']['name'] ?? '' ), 'force-delete menu returns previous' );
[ $st ] = mn_fetch( $REF, $MQ . '%2F' . $wid, $admin );
check( 404 === $st, 'WordPress confirms the menu is gone' );

// Reverse: WordPress creates, the engine reads and deletes.
[ $st, $wp_menu ] = mn_fetch( $REF, $MQ, $admin, 'POST', json_encode( array( 'name' => 'zz-oracle-menu' ) ) );
check( 201 === $st && ! empty( $wp_menu['id'] ), 'WordPress creates a menu' );
$oid = (int) ( $wp_menu['id'] ?? 0 );
[ $st, $engine_read ] = mn_fetch( $ENGINE, $MQ . '%2F' . $oid, $admin );
check( 200 === $st, 'engine reads the WordPress-written menu' );
$d = minn_test_diff( mn_norm( $wp_menu ), mn_norm( $engine_read ) );
check( null === $d, 'WordPress create menu equals the engine read-back', (string) $d );
[ $st, $wp_item ] = mn_fetch( $REF, $IQ, $admin, 'POST', json_encode( array( 'title' => array( 'raw' => 'From raw' ), 'url' => 'https://example.com/raw', 'menus' => $oid, 'status' => 'publish' ) ) );
check( 201 === $st && 'From raw' === ( $wp_item['title']['raw'] ?? '' ), 'WordPress creates an item from title.raw' );
$wiid = (int) ( $wp_item['id'] ?? 0 );
[ $st, $engine_item ] = mn_fetch( $ENGINE, $IQ . '%2F' . $wiid . '&context=edit', $admin );
check( 200 === $st, 'engine reads the WordPress-written item' );
$d = minn_test_diff( mn_norm( $wp_item ), mn_norm( $engine_item ) );
check( null === $d, 'WordPress create item equals the engine edit read-back', (string) $d );
mn_fetch( $ENGINE, $MQ . '%2F' . $oid . '&force=true', $admin, 'DELETE' );

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
