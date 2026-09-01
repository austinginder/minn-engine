<?php
/**
 * Login-endpoint suite: the browser sign-in surface.
 *
 * The claim: a client can POST credentials to the engine's /wp-login.php,
 * receive real WordPress auth cookies, and that session then authenticates
 * against both the engine and WordPress. Logout clears the cookies. This
 * closes the identity loop: the engine issues sessions a browser can use.
 *
 * Run:  php tests/login-endpoint.test.php
 * Ref:  (cd wp-reference && php -S 127.0.0.1:8123) — SKIPs when down.
 */

$ENGINE = rtrim( getenv( 'MINN_TEST_URL' ) ?: 'https://minn.localhost', '/' );
$REF    = 'http://127.0.0.1:8123';
$ROOT   = dirname( __DIR__ );
$PASS   = getenv( 'MINN_ADMIN_PASS' ) ?: 'password';

require_once __DIR__ . '/lib.php';

// Load the engine in-process so the suite can mint the nonce that pairs with
// the session the login endpoint creates (a boot payload would supply it).
$config = file_get_contents( minn_test_site_root() . '/public/wp-config.php' );
$config = str_replace( "require_once ABSPATH . 'wp-settings.php';", '', $config );
eval( '?>' . $config );
$GLOBALS['table_prefix'] = $table_prefix;
require "$ROOT/public/minn/src/Minn/Autoloader.php";
Minn\Autoloader::register();

[ $ph ] = minn_test_fetch( "$REF/?rest_route=/wp/v2/posts", 3 );
if ( 200 !== $ph['status'] ) {
	echo "SKIP: reference WordPress not running at $REF\n";
	exit( 0 );
}

$pass = 0;
$fail = 0;
function check( bool $ok, string $label, string $detail = '' ): void {
	global $pass, $fail;
	if ( $ok ) { $pass++; echo "  ok  $label\n"; }
	else { $fail++; echo "FAIL  $label" . ( $detail ? "\n      $detail" : '' ) . "\n"; }
}

/** Low-level request returning [status, headers[], body]. Follows nothing. */
function http( string $method, string $url, array $headers = array(), ?string $body = null ): array {
	$opts = array(
		'ssl'  => array( 'verify_peer' => false, 'verify_peer_name' => false ),
		'http' => array(
			'method'         => $method,
			'header'         => implode( "\r\n", $headers ),
			'ignore_errors'  => true,
			'timeout'        => 10,
			'follow_location'=> 0,
		),
	);
	if ( null !== $body ) {
		$opts['http']['content'] = $body;
	}
	$ctx  = stream_context_create( $opts );
	$resp = @file_get_contents( $url, false, $ctx );
	$h    = array( 'status' => 0, 'set-cookie' => array(), 'location' => null );
	foreach ( $http_response_header ?? array() as $line ) {
		if ( preg_match( '#^HTTP/\S+\s+(\d+)#', $line, $m ) ) {
			$h['status'] = (int) $m[1];
		} elseif ( stripos( $line, 'Set-Cookie:' ) === 0 ) {
			$h['set-cookie'][] = trim( substr( $line, 11 ) );
		} elseif ( stripos( $line, 'Location:' ) === 0 ) {
			$h['location'] = trim( substr( $line, 9 ) );
		}
	}
	return array( $h['status'], $h, (string) $resp );
}

/** Pull one cookie's value from a Set-Cookie list by name prefix. */
function cookie_value( array $set_cookies, string $name_prefix ): ?string {
	foreach ( $set_cookies as $sc ) {
		if ( str_starts_with( $sc, $name_prefix ) ) {
			$pair = explode( ';', $sc, 2 )[0];
			return urldecode( explode( '=', $pair, 2 )[1] ?? '' );
		}
	}
	return null;
}

echo "login-endpoint suite: $ENGINE / $REF (oracle)\n";

// 1. GET renders a form with the expected fields; the page people see is /minn-admin/login.
[ $gs, , $form ] = http( 'GET', "$ENGINE/minn-admin/login" );
check( 200 === $gs, 'GET /minn-admin/login returns 200', "status $gs" );
[ $bs, $bh ] = http( 'GET', "$ENGINE/wp-login.php" );
check( 302 === $bs && str_ends_with( (string) $bh['location'], '/minn-admin/login' ), 'a bare GET /wp-login.php sends the browser to /minn-admin/login', "$bs " . (string) ( $bh['location'] ?? '' ) );
[ $ps, , $lost ] = http( 'GET', "$ENGINE/minn-admin/login/lost-password" );
check( 200 === $ps && str_contains( $lost, 'name="user_login"' ) && str_contains( $form, '/minn-admin/login/lost-password' ), 'the lost-password page has a clean path and the form links to it', "status $ps" );
check( str_contains( $form, 'class="switch"' ) && str_contains( $form, 'name="rememberme"' ), 'remember-me is a switch' );
[ $ts ] = http( 'GET', "$ENGINE/wp-login.php?redirect_to=%2Fsample-page%2F" );
check( 200 === $ts, 'GET /wp-login.php with a query still answers in place for tooling', "status $ts" );
check(
	str_contains( $form, 'name="log"' ) && str_contains( $form, 'name="pwd"' ) && str_contains( $form, 'method="post"' ),
	'login form has log, pwd, and posts to itself'
);

// 2. Wrong password re-renders the form with an error (no redirect).
[ $ws, , $wbody ] = http(
	'POST', "$ENGINE/wp-login.php",
	array( 'Content-Type: application/x-www-form-urlencoded' ),
	'log=admin&pwd=wrong-password'
);
check( 200 === $ws && str_contains( $wbody, 'incorrect' ), 'wrong password re-renders the form with an error', "status $ws" );

// 3. Correct password: 302, three auth cookies, redirect to the admin.
[ $ls, $lh ] = http(
	'POST', "$ENGINE/wp-login.php",
	array( 'Content-Type: application/x-www-form-urlencoded' ),
	'log=admin&pwd=' . rawurlencode( $PASS )
);
check( 302 === $ls, 'correct password redirects (302)', "status $ls" );
check( str_contains( (string) $lh['location'], '/minn-admin/' ), 'redirects to the admin by default', (string) $lh['location'] );
$hash      = md5( 'https://minn.localhost' );
$logged_in = cookie_value( $lh['set-cookie'], 'wordpress_logged_in_' . $hash );
$auth      = cookie_value( $lh['set-cookie'], 'wordpress_sec_' . $hash );
check( is_string( $logged_in ) && substr_count( $logged_in, '|' ) === 3, 'sets a well-formed logged_in cookie' );
check( is_string( $auth ), 'sets an auth (secure) cookie on the admin path' );

// 4. The session the login created authenticates against the engine.
//    Mint the matching nonce the way a boot payload would provide it.
$parts = explode( '|', (string) $logged_in );
$user  = ( new Minn\Content\Users( Minn\Db::shared() ) )->findByLogin( $parts[0] );
$nonce = Minn\Auth\Nonce::create( (int) $user['ID'], $parts[2] );
$cname = 'wordpress_logged_in_' . $hash;
$alt   = 'wordpress_logged_in_' . md5( $REF );

[ $es, , $ebody ] = http(
	'GET', "$ENGINE/wp-json/wp/v2/users/me",
	array( "Cookie: $cname=$logged_in", "X-WP-Nonce: $nonce" )
);
$me = json_decode( $ebody, true );
check( 200 === $es && 1 === ( $me['id'] ?? 0 ), 'the login session authenticates on the engine', "status $es" );

// 5. And the very same cookie authenticates against WordPress.
[ $rs, , $rbody ] = http(
	'GET', "$REF/index.php?rest_route=/wp/v2/users/me",
	array( "Cookie: $cname=$logged_in; $alt=$logged_in", "X-WP-Nonce: $nonce" )
);
$rme = json_decode( $rbody, true );
check( 200 === $rs && 1 === ( $rme['id'] ?? 0 ), 'the login session authenticates on WordPress too', "status $rs" );

// 6. Logout clears the cookies and redirects.
[ $os, $oh ] = http( 'GET', "$ENGINE/wp-login.php?action=logout" );
check( 302 === $os, 'logout redirects (302)', "status $os" );
$cleared = false;
foreach ( $oh['set-cookie'] as $sc ) {
	if ( str_starts_with( $sc, 'wordpress_logged_in_' . $hash ) && ( str_contains( $sc, 'Max-Age=0' ) || str_contains( $sc, '1970' ) || str_contains( $sc, 'Aug 2026 02:' ) ) ) {
		$cleared = true;
	}
}
// Robuster check: the logged_in cookie is set to an empty/expiring value.
$lo_val = cookie_value( $oh['set-cookie'], 'wordpress_logged_in_' . $hash );
check( '' === trim( (string) $lo_val ) || $cleared, 'logout clears the logged_in cookie' );
check( str_contains( (string) $oh['location'], 'loggedout=true' ), 'logout redirects to the loggedout page', (string) $oh['location'] );

echo "\n$pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
