<?php
/**
 * Log a user in using ENGINE code only: verify the password, create a real
 * session row, and return the cookie + nonce. Proves the engine can mint a
 * session from credentials that WordPress then accepts. Prints JSON.
 *
 *   php tests/tools/engine-login.php <username> <password>
 */

$root   = dirname( __DIR__, 2 );
$config = file_get_contents( $root . '/public/wp-config.php' );
$config = str_replace( "require_once ABSPATH . 'wp-settings.php';", '', $config );
eval( '?>' . $config );

require $root . '/src/bootstrap.php';
require $root . '/src/caps.php';
require $root . '/src/login.php';
require $root . '/src/auth.php';

$username = (string) ( $argv[1] ?? '' );
$password = (string) ( $argv[2] ?? '' );

$user = minn_login( $username, $password );
if ( ! $user ) {
	echo json_encode( array( 'ok' => false ) );
	exit;
}
$uid   = (int) $user['ID'];
$exp   = time() + 172800;
$token = minn_create_session( $uid, $exp );

echo json_encode(
	array(
		'ok'          => true,
		'uid'         => $uid,
		'cookie'      => minn_generate_auth_cookie( $user, $exp, $token ),
		'nonce'       => minn_create_rest_nonce( $uid, $token ),
		'cookie_name' => minn_logged_in_cookie_name(),
		'token'       => $token,
	)
);
