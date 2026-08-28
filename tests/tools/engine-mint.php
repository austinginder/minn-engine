<?php
/**
 * Mint a session using ENGINE code only, for the cross-acceptance proof.
 * The session row is created through the reference's own session store (a
 * live token has to exist in usermeta either way); the cookie and nonce are
 * computed by the engine. Prints JSON.
 *
 *   php tests/tools/engine-mint.php <uid> <token> <expiration>
 */

$root   = dirname( __DIR__, 2 );
$config = file_get_contents( $root . '/public/wp-config.php' );
$config = str_replace( "require_once ABSPATH . 'wp-settings.php';", '', $config );
eval( '?>' . $config );

require $root . '/src/bootstrap.php';
require $root . '/src/auth.php';

$uid   = (int) ( $argv[1] ?? 1 );
$token = (string) ( $argv[2] ?? '' );
$exp   = (int) ( $argv[3] ?? ( time() + 172800 ) );

$user = minn_get_user_by_id( $uid );
echo json_encode(
	array(
		'uid'         => $uid,
		'cookie'      => minn_generate_auth_cookie( $user, $exp, $token ),
		'nonce'       => minn_create_rest_nonce( $uid, $token ),
		'cookie_name' => minn_logged_in_cookie_name(),
	)
);
