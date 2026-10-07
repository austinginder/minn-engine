<?php
/**
 * Application passwords, engine vs oracle: the same sequence (list, create,
 * validation refusals, read, rename, Basic auth as the user, introspect,
 * delete one, delete all) on both stacks, responses diffed with the values
 * that cannot match (uuids, plaintexts, times, addresses, hosts) masked. The
 * oracle needs wp-reference/wp-content/mu-plugins/zz-application-passwords.php
 * (run-all writes it) because it runs on plain HTTP.
 *
 *   php tests/application-passwords.test.php
 */
require __DIR__ . '/lib.php';

$ROOT   = dirname( __DIR__ );
$ENGINE = getenv( 'MINN_ENGINE' ) ?: 'https://minn.localhost';
$REF    = getenv( 'MINN_REF' ) ?: 'https://ref.minn.localhost';

[ $ph ] = minn_test_fetch( "$REF/?rest_route=/wp/v2/posts", 3 );
if ( 200 !== $ph['status'] ) {
	echo "SKIP: reference WordPress not running at $REF\n";
	exit( 0 );
}
$enabler = minn_test_site_root() . '/wp-reference/wp-content/mu-plugins/zz-application-passwords.php';
if ( ! is_file( $enabler ) ) {
	@mkdir( dirname( $enabler ), 0755, true );
	file_put_contents( $enabler, "<?php\n// The oracle runs on plain HTTP, where the reference refuses application passwords; the suites need them on.\nadd_filter(\"wp_is_application_passwords_available\", \"__return_true\");\n" );
}

$pass = 0;
$fail = 0;
function check( bool $ok, string $label, string $detail = '' ): void {
	global $pass, $fail;
	if ( $ok ) { $pass++; echo "  ok  $label\n"; } else { $fail++; echo "FAIL  $label" . ( $detail ? "\n      $detail" : '' ) . "\n"; }
}

$mint = json_decode( (string) shell_exec( 'wp --path=' . escapeshellarg( minn_test_site_root() . '/wp-reference' ) . ' eval-file ' . escapeshellarg( "$ROOT/tests/tools/mint-session.php" ) . ' 1 2>/dev/null' ), true );
if ( ! $mint || empty( $mint['cookie'] ) ) {
	echo "SKIP: could not mint a reference session\n";
	exit( 0 );
}
$cookieHeader = 'Cookie: ' . $mint['cookie_name'] . '=' . $mint['cookie'] . '; wordpress_logged_in_' . md5( $REF ) . '=' . $mint['cookie'] . '; wordpress_logged_in_' . md5( $ENGINE ) . '=' . $mint['cookie'];

/** A REST call with a cookie session, Basic credentials, or nothing. */
function ap_call( string $base, string $method, string $route, ?array $body = null, string $auth = 'cookie', string $basic = '' ): array {
	global $cookieHeader, $mint;
	$headers = array( 'Content-Type: application/json' );
	if ( 'cookie' === $auth ) {
		$headers[] = $cookieHeader;
		$headers[] = 'X-WP-Nonce: ' . $mint['nonce'];
	} elseif ( 'basic' === $auth ) {
		$headers[] = 'Authorization: Basic ' . base64_encode( $basic );
	}
	$ctx = stream_context_create( array(
		'ssl'  => array( 'verify_peer' => false, 'verify_peer_name' => false ),
		'http' => array( 'method' => $method, 'ignore_errors' => true, 'timeout' => 15, 'header' => implode( "\r\n", $headers ), 'content' => null === $body ? '' : json_encode( $body ) ),
	) );
	$raw    = @file_get_contents( $base . '/?rest_route=' . rawurlencode( $route ), false, $ctx );
	$status = 0;
	foreach ( $http_response_header ?? array() as $h ) {
		if ( preg_match( '#^HTTP/\S+\s+(\d+)#', $h, $m ) ) { $status = (int) $m[1]; }
	}
	return array( $status, json_decode( (string) $raw, true ) );
}

/** Masks what legitimately differs between two stacks and two runs. */
function ap_mask( $value, string $base ) {
	if ( is_array( $value ) ) {
		foreach ( $value as $k => $v ) {
			if ( in_array( $k, array( 'uuid', 'password', 'created', 'last_used', 'last_ip' ), true ) ) {
				$value[ $k ] = null === $v ? null : '{' . $k . '}';
			} else {
				$value[ $k ] = ap_mask( $v, $base );
			}
		}
		return $value;
	}
	if ( is_string( $value ) ) {
		global $ENGINE, $REF;
		$value = str_replace( array( $base, $ENGINE, $REF ), '{host}', $value );
		return preg_replace( '#/application-passwords/[0-9a-f-]{36}#', '/application-passwords/{uuid}', $value );
	}
	return $value;
}

echo "application-passwords suite: $ENGINE (engine) / $REF (oracle)\n";
$idx = array();
foreach ( array( 'engine' => $ENGINE, 'oracle' => $REF ) as $which => $base ) {
	[ , $index ] = ap_call( $base, 'GET', '/', null, 'none' );
	$idx[ $which ] = ap_mask( $index['authentication'] ?? null, $base );
}
check( $idx['engine'] === $idx['oracle'], 'the index advertises application passwords the same way', json_encode( $idx ) );

/** The whole sequence on one stack, every step's masked (status, body) and the plaintext for Basic auth. */
function ap_sequence( string $base ): array {
	$steps = array();
	$record = function ( string $label, array $result ) use ( &$steps, $base ) {
		$steps[ $label ] = array( $result[0], ap_mask( $result[1], $base ) );
	};
	ap_call( $base, 'DELETE', '/wp/v2/users/me/application-passwords' );
	$record( 'anonymous list', ap_call( $base, 'GET', '/wp/v2/users/me/application-passwords', null, 'none' ) );
	$record( 'list empty', ap_call( $base, 'GET', '/wp/v2/users/me/application-passwords' ) );
	$created = ap_call( $base, 'POST', '/wp/v2/users/me/application-passwords', array( 'name' => 'testing' ) );
	$record( 'create', $created );
	$plain = (string) ( $created[1]['password'] ?? '' );
	$uuid  = (string) ( $created[1]['uuid'] ?? '' );
	$record( 'create no name', ap_call( $base, 'POST', '/wp/v2/users/me/application-passwords', array() ) );
	$record( 'create empty name', ap_call( $base, 'POST', '/wp/v2/users/me/application-passwords', array( 'name' => '' ) ) );
	$record( 'create blank name', ap_call( $base, 'POST', '/wp/v2/users/me/application-passwords', array( 'name' => '   ' ) ) );
	$record( 'create duplicate name', ap_call( $base, 'POST', '/wp/v2/users/me/application-passwords', array( 'name' => 'testing' ) ) );
	$record( 'create with app_id', ap_call( $base, 'POST', '/wp/v2/users/me/application-passwords', array( 'name' => 'second', 'app_id' => '123e4567-e89b-12d3-a456-426614174000' ) ) );
	$record( 'create bad app_id', ap_call( $base, 'POST', '/wp/v2/users/me/application-passwords', array( 'name' => 'third', 'app_id' => 'nope' ) ) );
	$record( 'list', ap_call( $base, 'GET', '/wp/v2/users/me/application-passwords' ) );
	$record( 'list by id', ap_call( $base, 'GET', '/wp/v2/users/1/application-passwords' ) );
	$record( 'list unknown user', ap_call( $base, 'GET', '/wp/v2/users/999999/application-passwords' ) );
	$record( 'get one', ap_call( $base, 'GET', "/wp/v2/users/me/application-passwords/$uuid" ) );
	$record( 'get missing', ap_call( $base, 'GET', '/wp/v2/users/me/application-passwords/123e4567-e89b-12d3-a456-426614174999' ) );
	$record( 'rename', ap_call( $base, 'PUT', "/wp/v2/users/me/application-passwords/$uuid", array( 'name' => 'renamed' ) ) );
	$record( 'basic users/me', ap_call( $base, 'GET', '/wp/v2/users/me?context=edit', null, 'basic', "admin:$plain" ) );
	$record( 'basic without spaces', ap_call( $base, 'GET', '/wp/v2/users/me', null, 'basic', 'admin:' . str_replace( ' ', '', $plain ) ) );
	$record( 'introspect via basic', ap_call( $base, 'GET', '/wp/v2/users/me/application-passwords/introspect', null, 'basic', "admin:$plain" ) );
	$record( 'introspect via cookie', ap_call( $base, 'GET', '/wp/v2/users/me/application-passwords/introspect' ) );
	$record( 'after use', ap_call( $base, 'GET', "/wp/v2/users/me/application-passwords/$uuid" ) );
	$record( 'basic wrong password', ap_call( $base, 'GET', '/wp/v2/users/me', null, 'basic', 'admin:wrong wrong wrong wrong wrong wrong' ) );
	$record( 'basic wrong user', ap_call( $base, 'GET', '/wp/v2/users/me', null, 'basic', "nobody:$plain" ) );
	$record( 'basic wrong on public route', ap_call( $base, 'GET', '/wp/v2/categories?per_page=1&_fields=id', null, 'basic', 'admin:wrong' ) );
	$record( 'delete one', ap_call( $base, 'DELETE', "/wp/v2/users/me/application-passwords/$uuid" ) );
	$record( 'delete missing', ap_call( $base, 'DELETE', "/wp/v2/users/me/application-passwords/$uuid" ) );
	$record( 'delete all', ap_call( $base, 'DELETE', '/wp/v2/users/me/application-passwords' ) );
	$record( 'list after', ap_call( $base, 'GET', '/wp/v2/users/me/application-passwords' ) );
	return array( $steps, $plain );
}

[ $engineSteps, $enginePlain ] = ap_sequence( $ENGINE );
[ $oracleSteps ] = ap_sequence( $REF );
foreach ( $oracleSteps as $label => $expected ) {
	$got = $engineSteps[ $label ] ?? null;
	check( $got === $expected, $label, 'engine ' . json_encode( $got ) . "\n      oracle " . json_encode( $expected ) );
}
check( 1 === preg_match( '/^[A-Za-z0-9]{4}( [A-Za-z0-9]{4}){5}$/', $enginePlain ), 'the plaintext is six groups of four letters and digits', $enginePlain );
// Across stacks: a password either stack made signs in on the other, so a site can switch either way.
$refWp = 'wp --path=' . escapeshellarg( minn_test_site_root() . '/wp-reference' );
[ , $refMade ] = ap_call( $REF, 'POST', '/wp/v2/users/me/application-passwords', array( 'name' => 'made on the reference' ) );
$refPlain = (string) ( $refMade['password'] ?? '' );
[ $status ] = ap_call( $ENGINE, 'GET', '/wp/v2/users/me', null, 'basic', "admin:$refPlain" );
check( 200 === $status, 'a password the reference made signs in on the engine', (string) $status );
[ , $engMade ] = ap_call( $ENGINE, 'POST', '/wp/v2/users/me/application-passwords', array( 'name' => 'made on the engine' ) );
$engPlain = (string) ( $engMade['password'] ?? '' );
[ $status ] = ap_call( $REF, 'GET', '/wp/v2/users/me', null, 'basic', "admin:$engPlain" );
check( 200 === $status, 'a password the engine made signs in on the reference', (string) $status );
$stored = array_column( (array) json_decode( (string) shell_exec( "$refWp user meta get 1 _application_passwords --format=json 2>/dev/null" ), true ), 'password', 'name' );
check( 2 === count( $stored ) && 2 === count( preg_grep( '/^\$generic\$[A-Za-z0-9_-]{40}$/', $stored ) ), 'both stacks store the reference\'s own hash', json_encode( $stored ) );
// A password stored before WordPress 6.8 (phpass) still signs in here.
shell_exec( "$refWp eval " . escapeshellarg( 'require_once ABSPATH . WPINC . "/class-phpass.php"; $all = WP_Application_Passwords::get_user_application_passwords(1); foreach ($all as $i => $item) { if ($item["name"] === "made on the reference") { $all[$i]["password"] = (new PasswordHash(8, true))->HashPassword(str_replace(" ", "", "' . $refPlain . '")); } } update_user_meta(1, "_application_passwords", $all);' ) . ' 2>/dev/null' );
[ $status ] = ap_call( $ENGINE, 'GET', '/wp/v2/users/me', null, 'basic', "admin:$refPlain" );
check( 200 === $status, 'a password stored as phpass (before WordPress 6.8) still signs in on the engine', (string) $status );
ap_call( $ENGINE, 'DELETE', '/wp/v2/users/me/application-passwords' );
$meta = json_decode( (string) shell_exec( 'wp --path=' . escapeshellarg( minn_test_site_root() . '/wp-reference' ) . ' user meta get 1 _application_passwords --format=json 2>/dev/null' ), true );
check( array() === $meta || null === $meta, 'nothing is left in the user meta afterwards', json_encode( $meta ) );

echo "\n$pass passed, $fail failed\n";
exit( $fail ? 1 : 0 );
