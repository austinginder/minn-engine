<?php
/**
 * Log a user in using ENGINE code only: verify the password, create a real
 * session row, and return the cookie + nonce. Proves the engine can mint a
 * session from credentials that WordPress then accepts. Prints JSON.
 *
 *   php tests/tools/engine-login.php <username> <password>
 */

$root   = dirname( __DIR__, 2 );
require_once dirname( __DIR__ ) . '/local.php';
$site   = getenv( 'MINN_TEST_ROOT' ) ?: minn_test_site_dir( 'minn' );
$config = file_get_contents( $site . '/public/wp-config.php' );
$config = str_replace( "require_once ABSPATH . 'wp-settings.php';", '', $config );
eval( '?>' . $config );

require $root . '/public/minn/src/Minn/Autoloader.php';
Minn\Autoloader::register();

$username = (string) ( $argv[1] ?? '' );
$password = (string) ( $argv[2] ?? '' );

$db       = Minn\Db::shared();
$users    = new Minn\Content\Users( $db );
$sessions = new Minn\Auth\Sessions( $users );
$cookie   = new Minn\Auth\Cookie( $db, $users, $sessions );
$user     = ( new Minn\Auth\Authenticator( $cookie, $users ) )->login( $username, $password );
if ( ! $user ) {
	echo json_encode( array( 'ok' => false ) );
	exit;
}
$uid   = (int) $user['ID'];
$exp   = time() + 172800;
$token = $sessions->create( $uid, $exp, '', '' );

echo json_encode(
	array(
		'ok'          => true,
		'uid'         => $uid,
		'cookie'      => $cookie->mint( $user, $exp, $token ),
		'nonce'       => Minn\Auth\Nonce::create( $uid, $token ),
		'cookie_name' => $cookie->name(),
		'token'       => $token,
	)
);
