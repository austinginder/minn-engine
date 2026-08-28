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

require $root . '/src/Minn/Autoloader.php';
Minn\Autoloader::register();

$uid   = (int) ( $argv[1] ?? 1 );
$token = (string) ( $argv[2] ?? '' );
$exp   = (int) ( $argv[3] ?? ( time() + 172800 ) );

$db     = Minn\Db::shared();
$users  = new Minn\Content\Users( $db );
$cookie = new Minn\Auth\Cookie( $db, $users, new Minn\Auth\Sessions( $users ) );
$user   = $users->find( $uid );
echo json_encode(
	array(
		'uid'         => $uid,
		'cookie'      => $cookie->mint( $user, $exp, $token ),
		'nonce'       => Minn\Auth\Nonce::create( $uid, $token ),
		'cookie_name' => $cookie->name(),
	)
);
