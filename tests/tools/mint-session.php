<?php
/**
 * Oracle tool: mint a real WordPress session for the auth suites.
 * Runs under the REFERENCE WordPress only (never engine code):
 *
 *   wp --path=wp-reference eval-file tests/tools/mint-session.php [user_id]
 *
 * Prints JSON: the session token, the logged_in auth cookie value, a
 * wp_rest nonce bound to that token, and the CLI-computed cookie name.
 */

$uid     = isset( $args[0] ) ? (int) $args[0] : 1;
$exp     = time() + 2 * 86400;
$manager = WP_Session_Tokens::get_instance( $uid );
$token   = $manager->create( $exp );
$cookie  = wp_generate_auth_cookie( $uid, $exp, 'logged_in', $token );

$_COOKIE[ LOGGED_IN_COOKIE ] = $cookie;
wp_set_current_user( $uid );
$nonce = wp_create_nonce( 'wp_rest' );

echo json_encode(
	array(
		'uid'         => $uid,
		'token'       => $token,
		'cookie'      => $cookie,
		'nonce'       => $nonce,
		'cookie_name' => LOGGED_IN_COOKIE,
		'expiration'  => $exp,
	)
);
