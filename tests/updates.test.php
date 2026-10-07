<?php
/**
 * Updates on the dogfood site: wordpress.org offers, directory meta, the
 * forced check, notification rows, the auto-update lists, and a real
 * update of an inactive plugin, engine vs the site's own reference
 * (contracts/rest/minn-admin-v1.md "Updates").
 *
 * Ref: dogfood's parked WordPress at its Cove twin (cove twin dogfood add --as-site=ref.dogfood.localhost) — SKIPs cleanly when down.
 * Needs the network: wordpress.org answers both stacks.
 */

$ENGINE = 'https://dogfood.localhost';
$REF    = 'https://ref.dogfood.localhost';
$SITE   = getenv( 'MINN_DOGFOOD_SITE' ) ?: '~/Cove/Sites/dogfood.localhost';
$ROOT   = dirname( __DIR__ );

require_once __DIR__ . '/lib.php';

[ $ph ] = minn_test_fetch( "$REF/?rest_route=/wp/v2/posts", 30 );
if ( 200 !== $ph['status'] || ! is_dir( "$SITE/public" ) ) {
	echo "SKIP: dogfood reference not running at $REF (its Cove twin: cove twin dogfood add --as-site=ref.dogfood.localhost)\n";
	exit( 0 );
}

$pass = 0;
$fail = 0;

// The parked reference's wp-config turns the automatic updater off so the
// oracle stays still; the engine's site does not. Both stacks load this
// mu-plugins folder (the reference's wp-content is the site's), and the
// updater's filter outranks the constant on both, so for the run the gate is
// one option, the same for each (contracts/rest/minn-admin-v1.md "Auto-updates").
$GATE = "$SITE/public/wp-content/mu-plugins/zz-minn-updates-gate.php";
@mkdir( dirname( $GATE ), 0755, true );
file_put_contents( $GATE, "<?php\n// Written by the engine's tests/updates.test.php for its run, then removed.\nadd_filter( 'automatic_updater_disabled', static fn () => get_option( 'zz_minn_updater_disabled' ) === '1', 999 );\n" );
function up_gate( bool $off ): void {
	global $SITE;
	shell_exec( 'wp --path=' . escapeshellarg( "$SITE/wp-reference" ) . ' option update zz_minn_updater_disabled ' . ( $off ? '1' : '0' ) . ' --skip-plugins --skip-themes 2>/dev/null' );
}
up_gate( false );
register_shutdown_function( static function () use ( $GATE, $SITE ): void {
	@unlink( $GATE );
	shell_exec( 'wp --path=' . escapeshellarg( "$SITE/wp-reference" ) . ' option delete zz_minn_updater_disabled --skip-plugins --skip-themes 2>/dev/null' );
} );


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

function up_mint( int $uid ): array {
	global $ROOT, $SITE;
	$mint = json_decode( (string) shell_exec(
		'wp --path=' . escapeshellarg( "$SITE/wp-reference" ) . ' eval-file ' . escapeshellarg( "$ROOT/tests/tools/mint-session.php" ) . " $uid 2>/dev/null"
	), true );
	if ( ! $mint || empty( $mint['cookie'] ) ) {
		echo "SKIP: could not mint a reference session on the dogfood site\n";
		exit( 0 );
	}
	return $mint;
}

function up_fetch( string $base, string $route, ?array $mint, string $method = 'GET', ?string $body = null ): array {
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
			'http' => array( 'ignore_errors' => true, 'timeout' => 120, 'method' => $method, 'header' => implode( "\r\n", $headers ), 'content' => $body ?? '' ),
		)
	);
	$raw    = @file_get_contents( "$base/wp-json$route", false, $ctx );
	$status = 0;
	foreach ( $http_response_header ?? array() as $h ) {
		if ( preg_match( '#^HTTP/\S+\s+(\d+)#', $h, $m ) ) {
			$status = (int) $m[1];
		}
	}
	return array( $status, json_decode( (string) $raw, true ) );
}

function up_norm( $x ) {
	global $ENGINE, $REF;
	if ( is_array( $x ) ) {
		return array_map( 'up_norm', $x );
	}
	return is_string( $x ) ? str_replace( array( str_replace( '/', '\\/', $REF ), $REF ), $ENGINE, $x ) : $x;
}

function up_parity( string $label, string $route, ?array $mint, array $drop = array(), string $method = 'GET', ?string $body = null ): void {
	global $ENGINE, $REF;
	[ $rs, $rb ] = up_fetch( $REF, $route, $mint, $method, $body );
	[ $es, $eb ] = up_fetch( $ENGINE, $route, $mint, $method, $body );
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
	$d = minn_test_diff( up_norm( $strip( $rb ) ), up_norm( $strip( $eb ) ) );
	check( null === $d, $label, (string) $d );
}

echo "updates suite: $ENGINE (engine) / $REF (reference)\n";
$admin = up_mint( 1 );

// 1. Both stacks ask wordpress.org now; the offers agree.
up_parity( 'check-updates agrees (translation offers are wordpress.org state the engine does not carry)', '/minn-admin/v1/check-updates', $admin, array( 'translations', 'translationGroups' ), 'POST', '{}' );
up_parity( 'plugin-updates agrees (translations are wordpress.org state the engine does not carry)', '/minn-admin/v1/plugin-updates', $admin, array( 'translations', 'translationGroups', 'autoAllowed' ) );
up_parity( 'plugin-meta agrees', '/minn-admin/v1/plugin-meta', $admin );
up_parity( 'themes carry the offers and directory flags', '/minn-admin/v1/themes', $admin, array( 'screenshot' ) );
[ , $eb ] = up_fetch( $ENGINE, '/minn-admin/v1/plugin-updates', $admin );
check( true === ( $eb['autoAllowed'] ?? null ), 'per-item auto-updates are offered (cron applies them)', json_encode( $eb['autoAllowed'] ?? null ) );

// 2. Notification rows for the offers: same ids and titles (the check time differs by a second or two).
$rows = function ( $b ) {
	$out = array();
	foreach ( $b['items'] ?? array() as $it ) {
		if ( 'updates' === ( $it['kind'] ?? '' ) && ! str_starts_with( (string) $it['id'], 'translations-' ) ) {
			$out[ $it['id'] ] = array( $it['title'], $it['update'] ?? null );
		}
	}
	ksort( $out );
	return $out;
};
[ , $rb ] = up_fetch( $REF, '/minn-admin/v1/notifications', $admin );
[ , $eb ] = up_fetch( $ENGINE, '/minn-admin/v1/notifications', $admin );
$d = minn_test_diff( $rows( $rb ), $rows( $eb ) );
check( null === $d && count( $rows( $eb ) ) > 0, 'update notifications agree', (string) $d );

// 3. The auto-update lists: toggle on both stacks and read back.
[ , $before ] = up_fetch( $ENGINE, '/minn-admin/v1/plugin-updates', $admin );
$plugin = 'block-visibility/block-visibility.php';
$was    = in_array( $plugin, $before['auto'] ?? array(), true );
up_parity( 'auto-updates toggle on', '/minn-admin/v1/auto-updates', $admin, array(), 'POST', json_encode( array( 'type' => 'plugin', 'asset' => $plugin, 'enabled' => true ) ) );
up_parity( 'auto-updates toggle off', '/minn-admin/v1/auto-updates', $admin, array(), 'POST', json_encode( array( 'type' => 'plugin', 'asset' => $plugin, 'enabled' => false ) ) );
if ( $was ) {
	up_fetch( $ENGINE, '/minn-admin/v1/auto-updates', $admin, 'POST', json_encode( array( 'type' => 'plugin', 'asset' => $plugin, 'enabled' => true ) ) );
}
up_parity( 'auto-updates refuses an unknown plugin', '/minn-admin/v1/auto-updates', $admin, array(), 'POST', '{"type":"plugin","asset":"nothing/nothing.php","enabled":true}' );
up_parity( 'auto-updates for themes', '/minn-admin/v1/auto-updates', $admin, array(), 'POST', '{"type":"theme","asset":"twentytwentyfour","enabled":false}' );
up_gate( true );
up_parity( 'auto-updates refused while the updater is off', '/minn-admin/v1/auto-updates', $admin, array(), 'POST', json_encode( array( 'type' => 'plugin', 'asset' => $plugin, 'enabled' => true ) ) );
up_parity( 'themes say auto-updates are off while the updater is', '/minn-admin/v1/themes', $admin, array( 'themes' ) );
up_gate( false );

// 4. A real update on the engine: the first INACTIVE plugin with an offer.
[ , $plugins ] = up_fetch( $ENGINE, '/wp/v2/plugins', $admin );
$target = null;
foreach ( $before['updates'] ?? array() as $file => $version ) {
	foreach ( $plugins as $p ) {
		if ( $p['plugin'] . '.php' === $file && 'inactive' === $p['status'] ) {
			$target = array( $file, $version, $p['plugin'] );
			break 2;
		}
	}
}
if ( null === $target ) {
	echo "  --  no inactive plugin has an offer; the update step is skipped\n";
} else {
	[ $file, $version, $key ] = $target;
	[ $s, $b ] = up_fetch( $ENGINE, '/minn-admin/v1/plugins/update', $admin, 'POST', json_encode( array( 'plugin' => $file ) ) );
	check( 200 === $s && true === ( $b['updated'] ?? null ) && $version === ( $b['version'] ?? '' ), "engine updates $file to $version", "status $s " . json_encode( $b ) );
	up_parity( 'both stacks read the new version', "/wp/v2/plugins/$key", $admin, array( '_links' ) );
	[ $s, $b ] = up_fetch( $ENGINE, '/minn-admin/v1/plugins/update', $admin, 'POST', json_encode( array( 'plugin' => $file ) ) );
	check( 400 === $s && 'no_update' === ( $b['code'] ?? '' ), 'a second update finds nothing to do', "status $s " . json_encode( $b ) );
	up_parity( 'the offers agree after the update', '/minn-admin/v1/check-updates', $admin, array(), 'POST', '{}' );
}

echo "\n$pass passed, $fail failed\n";
exit( $fail ? 1 : 0 );
