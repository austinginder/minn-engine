<?php
/**
 * Oracle tool: a session for a user and the log-out nonce that pairs with
 * it, for the log-out suites. Runs under the REFERENCE WordPress only:
 *
 *   wp --path=wp-reference eval-file tests/tools/mint-logout.php <user_id>
 *
 * Prints JSON: the session token, the logged_in and secure_auth cookies
 * (name and value) and the log-out nonce.
 */

$uid    = (int) ( $args[0] ?? 0 );
$exp    = time() + 3600;
$token  = WP_Session_Tokens::get_instance( $uid )->create( $exp );
$cookie = wp_generate_auth_cookie( $uid, $exp, 'logged_in', $token );
$secure = wp_generate_auth_cookie( $uid, $exp, 'secure_auth', $token );

$_COOKIE[ LOGGED_IN_COOKIE ] = $cookie;
wp_set_current_user( $uid );

echo json_encode(
	array(
		'token'     => $token,
		'logged_in' => array( LOGGED_IN_COOKIE, $cookie ),
		'secure'    => array( SECURE_AUTH_COOKIE, $secure ),
		'nonce'     => wp_create_nonce( 'log-out' ),
	)
), "\n";
