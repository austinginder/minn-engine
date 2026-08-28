<?php
/**
 * minn-admin/v1 dashboard slice: overview, notifications, notifications/read.
 *
 * Live parity against the reference WordPress running the real Minn Admin
 * plugin on the SAME database, plus a read-marker round trip proving the
 * engine's serialized usermeta writes are WordPress-readable and vice versa.
 *
 * Ref: (cd wp-reference && php -S 127.0.0.1:8123) with minn-admin active —
 * SKIPs cleanly when the reference is down.
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

/** Request with optional cookie/nonce/method/body; returns [status, decoded]. */
function v1_fetch( string $base, string $route, ?array $mint, string $method = 'GET', ?string $body = null ): array {
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
	$raw    = @file_get_contents( $base . '/?rest_route=' . rawurlencode( $route ), false, $ctx );
	$status = 0;
	foreach ( $http_response_header ?? array() as $h ) {
		if ( preg_match( '#^HTTP/\S+\s+(\d+)#', $h, $m ) ) {
			$status = (int) $m[1];
		}
	}
	return array( $status, json_decode( (string) $raw, true ) );
}

/** Second-precision timestamps drift between the two requests; compare at minutes. */
function v1_norm( $x ) {
	if ( is_array( $x ) ) {
		return array_map( 'v1_norm', $x );
	}
	if ( is_string( $x ) ) {
		return preg_replace( '/(\d{4}-\d{2}-\d{2} \d{2}:\d{2}):\d{2}/', '$1', $x );
	}
	return $x;
}

function v1_parity( string $label, string $route, ?array $mint, string $method = 'GET', ?string $body = null ): void {
	global $ENGINE, $REF;
	[ $rs, $rb ] = v1_fetch( $REF, $route, $mint, $method, $body );
	[ $es, $eb ] = v1_fetch( $ENGINE, $route, $mint, $method, $body );
	if ( $rs !== $es ) {
		check( false, $label, "status $rs vs $es" );
		return;
	}
	$d = minn_test_diff( v1_norm( $rb ), v1_norm( $eb ) );
	check( null === $d, $label, (string) $d );
}

function v1_mint( int $uid ): array {
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

/** Wipe the read-marker meta so this suite leaves no state behind. */
function v1_cleanup(): void {
	global $ROOT;
	foreach ( array( 'minn_admin_notif_read_at', 'minn_admin_notif_read_ids' ) as $key ) {
		shell_exec(
			'wp --path=' . escapeshellarg( "$ROOT/wp-reference" ) . " user meta delete 1 $key 2>/dev/null"
		);
	}
}
register_shutdown_function( 'v1_cleanup' );
v1_cleanup();

echo "minn-admin/v1 suite: $ENGINE (engine) / $REF (oracle)\n";

$admin  = v1_mint( 1 );
$editor = v1_mint( 2 );
$author = v1_mint( 3 );

// 1. The refusal matrix, engine and reference agreeing on every rung.
v1_parity( 'anonymous overview 401', '/minn-admin/v1/overview', null );
v1_parity( 'anonymous notifications 401', '/minn-admin/v1/notifications', null );
[ $st, $body ] = v1_fetch( $ENGINE, '/minn-admin/v1/overview', array_merge( $admin, array( 'nonce' => '0123456789' ) ) );
check( 403 === $st && 'rest_cookie_invalid_nonce' === ( $body['code'] ?? '' ), 'bad nonce beats the permission gate' );

// 2. Parameter validation, including its ordering against the auth gate.
foreach ( array( '200' => 'out of bounds', 'abc' => 'wrong type' ) as $bad => $why ) {
	// The plain rest_route form folds &-args into the route only when they
	// ride the same value; days is a separate query arg here on purpose.
	global $ENGINE, $REF;
	$route = '/minn-admin/v1/overview';
	$alt   = 'wordpress_logged_in_' . md5( $REF );
	foreach ( array( 'oracle' => $REF, 'engine' => $ENGINE ) as $which => $base ) {
		$ctx = stream_context_create(
			array(
				'ssl'  => array( 'verify_peer' => false, 'verify_peer_name' => false ),
				'http' => array(
					'ignore_errors' => true,
					'timeout'       => 10,
					'header'        => 'Cookie: ' . $admin['cookie_name'] . '=' . $admin['cookie'] . '; ' . $alt . '=' . $admin['cookie'] . "\r\nX-WP-Nonce: " . $admin['nonce'],
				),
			)
		);
		$raw = @file_get_contents( "$base/?rest_route=" . rawurlencode( $route ) . "&days=$bad", false, $ctx );
		$out[ $which ] = json_decode( (string) $raw, true );
	}
	$d = minn_test_diff( $out['oracle'], $out['engine'] );
	check( null === $d, "days=$bad rejected identically ($why)", (string) $d );
}
v1_parity( 'anonymous outranks validation', '/minn-admin/v1/overview', null );

// 3. Unknown routes inside the namespace.
v1_parity( 'unknown v1 route 404', '/minn-admin/v1/nope', $admin );
v1_parity( 'GET on the read marker 404', '/minn-admin/v1/notifications/read', $admin );

// 4. Overview parity across the capability spectrum and both bucket widths.
foreach ( array( 'admin' => $admin, 'editor' => $editor, 'author' => $author ) as $who => $mint ) {
	$ctx_route = '/minn-admin/v1/overview';
	// days rides as a separate arg; the helper's single-arg form covers default 30.
	v1_parity( "overview default days ($who)", $ctx_route, $mint );
}
// Weekly buckets: exercise days>45 via the oracle-vs-engine pair directly.
$alt = 'wordpress_logged_in_' . md5( $REF );
foreach ( array( 'oracle' => $REF, 'engine' => $ENGINE ) as $which => $base ) {
	$ctx = stream_context_create(
		array(
			'ssl'  => array( 'verify_peer' => false, 'verify_peer_name' => false ),
			'http' => array(
				'ignore_errors' => true,
				'timeout'       => 10,
				'header'        => 'Cookie: ' . $admin['cookie_name'] . '=' . $admin['cookie'] . '; ' . $alt . '=' . $admin['cookie'] . "\r\nX-WP-Nonce: " . $admin['nonce'],
			),
		)
	);
	$raw = @file_get_contents( "$base/?rest_route=" . rawurlencode( '/minn-admin/v1/overview' ) . '&days=90', false, $ctx );
	$wk[ $which ] = json_decode( (string) $raw, true );
}
$d = minn_test_diff( v1_norm( $wk['oracle'] ), v1_norm( $wk['engine'] ) );
check( null === $d, 'overview days=90 weekly buckets (admin)', (string) $d );
check( 13 === count( $wk['engine']['chart'] ?? array() ), 'ceil(90/7) yields 13 buckets' );

// 5. Notifications parity: clean read state, all three capability views.
foreach ( array( 'admin' => $admin, 'editor' => $editor, 'author' => $author ) as $who => $mint ) {
	v1_parity( "notifications ($who)", '/minn-admin/v1/notifications', $mint );
}

// 6. Read-marker round trip. Engine writes the serialized id list; the
// REFERENCE (real WordPress) must read it back — and the other way round.
[ $st, $body ] = v1_fetch( $ENGINE, '/minn-admin/v1/notifications/read', $admin, 'POST', '{"id":"comment-1"}' );
check( 200 === $st && true === ( $body['ok'] ?? null ), 'engine marks one notification read' );
[ , $oracle_view ] = v1_fetch( $REF, '/minn-admin/v1/notifications', $admin );
$row = null;
foreach ( $oracle_view['items'] ?? array() as $item ) {
	if ( 'comment-1' === $item['id'] ) {
		$row = $item;
	}
}
check( $row && false === $row['unread'], 'WordPress reads the engine-written id list' );
v1_parity( 'notifications parity with a read id (admin)', '/minn-admin/v1/notifications', $admin );

[ $st, $body ] = v1_fetch( $REF, '/minn-admin/v1/notifications/read', $admin, 'POST', '{}' );
check( 200 === $st && true === ( $body['ok'] ?? null ), 'reference marks everything read' );
[ , $engine_view ] = v1_fetch( $ENGINE, '/minn-admin/v1/notifications', $admin );
$all_read = ! empty( $engine_view['items'] );
foreach ( $engine_view['items'] ?? array() as $item ) {
	$all_read = $all_read && false === $item['unread'];
}
check( $all_read, 'engine reads the WordPress-written read_at' );
v1_parity( 'notifications parity after mark-all (admin)', '/minn-admin/v1/notifications', $admin );

// 7. /core: payload parity for the admin, the capability refusal for the editor.
v1_parity( 'core status (admin)', '/minn-admin/v1/core', $admin );
v1_parity( 'core status refused below update_core (editor)', '/minn-admin/v1/core', $editor );

// 8. /overview/activity: a live day window per role, plus the validation shapes.
$win = '&from=' . rawurlencode( gmdate( 'Y-m-d H:i:s', time() - 86400 ) ) . '&to=' . rawurlencode( gmdate( 'Y-m-d H:i:s' ) );
function v1_pair( string $q, array $mint ): array {
	global $ENGINE, $REF;
	$alt = 'wordpress_logged_in_' . md5( $REF );
	$out = array();
	foreach ( array( 'oracle' => $REF, 'engine' => $ENGINE ) as $which => $base ) {
		$ctx = stream_context_create(
			array(
				'ssl'  => array( 'verify_peer' => false, 'verify_peer_name' => false ),
				'http' => array(
					'ignore_errors' => true,
					'timeout'       => 10,
					'header'        => 'Cookie: ' . $mint['cookie_name'] . '=' . $mint['cookie'] . '; ' . $alt . '=' . $mint['cookie'] . "\r\nX-WP-Nonce: " . $mint['nonce'],
				),
			)
		);
		$out[ $which ] = json_decode( (string) @file_get_contents( "$base/?$q", false, $ctx ), true );
	}
	return $out;
}
foreach ( array( 'admin' => $admin, 'author' => $author ) as $who => $mint ) {
	$r = v1_pair( 'rest_route=' . rawurlencode( '/minn-admin/v1/overview/activity' ) . $win, $mint );
	$d = minn_test_diff( v1_norm( $r['oracle'] ), v1_norm( $r['engine'] ) );
	check( null === $d, "overview activity drill-down ($who)", (string) $d );
}
$r = v1_pair( 'rest_route=' . rawurlencode( '/minn-admin/v1/overview/activity' ), $admin );
$d = minn_test_diff( $r['oracle'], $r['engine'] );
check( null === $d, 'activity missing both bounds rejected identically', (string) $d );
$r = v1_pair( 'rest_route=' . rawurlencode( '/minn-admin/v1/overview/activity' ) . '&from=nope&to=also-nope', $admin );
$d = minn_test_diff( $r['oracle'], $r['engine'] );
check( null === $d, 'activity malformed bounds rejected identically', (string) $d );

// 9. /boot-status: section-level parity. The plugin-inventory sections
// (plugins, pluginUpdates, pluginMeta) are the contract's own fallback
// mechanism — the engine omits them and the client loads those standalone.
foreach ( array( 'admin' => $admin, 'editor' => $editor, 'author' => $author ) as $who => $mint ) {
	[ , $ob ] = v1_fetch( $REF, '/minn-admin/v1/boot-status', $mint );
	[ , $eb ] = v1_fetch( $ENGINE, '/minn-admin/v1/boot-status', $mint );
	$skip     = array( 'plugins', 'pluginUpdates', 'pluginMeta' );
	$problems = array();
	foreach ( array_diff( array_keys( $eb ?? array() ), array_keys( $ob ?? array() ) ) as $k ) {
		$problems[] = "engine-only section $k";
	}
	foreach ( array_diff( array_keys( $ob ?? array() ), array_keys( $eb ?? array() ), $skip ) as $k ) {
		$problems[] = "missing section $k";
	}
	foreach ( array_diff( array_keys( $ob ?? array() ), $skip ) as $k ) {
		if ( isset( $eb[ $k ] ) ) {
			$d = minn_test_diff( v1_norm( $ob[ $k ] ), v1_norm( $eb[ $k ] ) );
			if ( null !== $d ) {
				$problems[] = "$k: $d";
			}
		}
	}
	check( ! $problems, "boot-status sections match ($who)", implode( '; ', $problems ) );
}

echo "\n$pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
