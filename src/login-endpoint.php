<?php
/**
 * Minn Engine login endpoint — the HTTP surface a browser signs in through.
 *
 * Serves the wp-login.php contract: a GET form, a POST that verifies the
 * password, creates a session (src/login.php), and sets the real WordPress
 * auth cookies, and a logout that clears them. The cookies it sets validate
 * against the engine AND against WordPress, so a browser can sign in at the
 * engine and its session works everywhere. Implemented from the behavior
 * matrix in contracts/rest/auth.md; no WordPress source is used.
 */

/** md5 of the site URL, the hash WordPress suffixes onto every cookie name. */
function minn_cookie_hash(): string {
	return md5( minn_option( 'siteurl' ) ?? '' );
}

function minn_is_ssl(): bool {
	return ( ! empty( $_SERVER['HTTPS'] ) && 'off' !== $_SERVER['HTTPS'] )
		|| ( ( $_SERVER['SERVER_PORT'] ?? '' ) === '443' )
		|| ( ( $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '' ) === 'https' );
}

/**
 * Build an auth-scheme cookie value (auth / secure_auth), the same
 * username|expiration|token|hmac shape as logged_in but keyed off the
 * scheme's salt.
 */
function minn_generate_scheme_cookie( array $user, int $expiration, string $token, string $scheme ): string {
	$username  = $user['user_login'];
	$pass_frag = minn_pass_fragment( $user['user_pass'] );
	$key       = minn_hash( $username . '|' . $pass_frag . '|' . $expiration . '|' . $token, $scheme );
	$hmac      = hash_hmac( 'sha256', $username . '|' . $expiration . '|' . $token, $key );
	return $username . '|' . $expiration . '|' . $token . '|' . $hmac;
}

/**
 * Set the three WordPress auth cookies for a freshly created session: the
 * auth cookie on the admin and plugins paths, and the logged_in cookie on
 * the site root. Over HTTPS the secure variants are used.
 */
function minn_set_auth_cookies( array $user, int $expiration, string $token ): void {
	$hash   = minn_cookie_hash();
	$secure = minn_is_ssl();

	$auth_scheme = $secure ? 'secure_auth' : 'auth';
	$auth_name   = ( $secure ? 'wordpress_sec_' : 'wordpress_' ) . $hash;
	$auth_value  = minn_generate_scheme_cookie( $user, $expiration, $token, $auth_scheme );
	$logged_in   = minn_generate_auth_cookie( $user, $expiration, $token );

	$common = array(
		'expires'  => $expiration,
		'httponly' => true,
		'secure'   => $secure,
		'samesite' => 'Lax',
	);
	// Auth cookie: admin and plugins scopes.
	setcookie( $auth_name, $auth_value, array( 'path' => '/wp-admin' ) + $common );
	setcookie( $auth_name, $auth_value, array( 'path' => '/wp-content/plugins' ) + $common );
	// Logged-in cookie: whole site. This is the one the REST layer reads.
	setcookie( 'wordpress_logged_in_' . $hash, $logged_in, array( 'path' => '/' ) + $common );
}

/** Clear every auth cookie (logout). */
function minn_clear_auth_cookies(): void {
	$hash = minn_cookie_hash();
	$past = array( 'expires' => time() - 3600, 'httponly' => true );
	foreach ( array(
		array( 'wordpress_' . $hash, '/wp-admin' ),
		array( 'wordpress_' . $hash, '/wp-content/plugins' ),
		array( 'wordpress_sec_' . $hash, '/wp-admin' ),
		array( 'wordpress_sec_' . $hash, '/wp-content/plugins' ),
		array( 'wordpress_logged_in_' . $hash, '/' ),
	) as $c ) {
		setcookie( $c[0], ' ', array( 'path' => $c[1] ) + $past );
	}
}

/** Default post-login destination. */
function minn_login_redirect_default(): string {
	return minn_home_url( '/minn-admin/' );
}

/**
 * Handle /wp-login.php. GET renders the form; POST authenticates and sets
 * cookies; ?action=logout clears them. Exits.
 */
function minn_handle_login(): void {
	$action = $_GET['action'] ?? 'login';

	if ( 'logout' === $action ) {
		minn_clear_auth_cookies();
		header( 'Location: ' . minn_home_url( '/wp-login.php?loggedout=true' ), true, 302 );
		exit;
	}

	if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) {
		$username = (string) ( $_POST['log'] ?? '' );
		$password = (string) ( $_POST['pwd'] ?? '' );
		$user     = minn_login( $username, $password );
		if ( ! $user ) {
			minn_render_login_form( 'Error: The username or password you entered is incorrect.' );
			exit;
		}
		$remember   = ! empty( $_POST['rememberme'] );
		$expiration = time() + ( $remember ? 14 * MINN_DAY : 2 * MINN_DAY );
		$token      = minn_create_session( (int) $user['ID'], $expiration );
		minn_set_auth_cookies( $user, $expiration, $token );

		$redirect = (string) ( $_POST['redirect_to'] ?? '' );
		if ( '' === $redirect ) {
			$redirect = minn_login_redirect_default();
		}
		header( 'Location: ' . $redirect, true, 302 );
		exit;
	}

	minn_render_login_form();
	exit;
}

function minn_render_login_form( string $error = '' ): void {
	$site     = minn_esc( minn_option( 'blogname' ) ?? 'Site' );
	$redirect = minn_esc( $_GET['redirect_to'] ?? '' );
	$err_html = '' === $error
		? ''
		: '<div class="err">' . minn_esc( $error ) . '</div>';

	header( 'Content-Type: text/html; charset=utf-8' );
	echo '<!doctype html><html lang="en"><head><meta charset="utf-8">';
	echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
	echo '<title>Log In &lsaquo; ' . $site . '</title>';
	echo '<style>
		body { margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center;
		       background:#0b0b0d; color:#ececed; font:15px/1.5 "Hanken Grotesk","Helvetica Neue",sans-serif; }
		form { width:320px; background:#151518; border:1px solid #242429; border-radius:14px; padding:28px; }
		h1 { font-size:19px; font-weight:700; margin:0 0 18px; }
		label { display:block; font-size:12px; color:#9d9da7; margin:14px 0 5px; }
		input[type=text],input[type=password] { width:100%; box-sizing:border-box; padding:9px 11px;
		       background:#0b0b0d; border:1px solid #31313a; border-radius:8px; color:#ececed; font-size:14px; }
		.row { display:flex; align-items:center; gap:7px; margin-top:14px; font-size:13px; color:#9d9da7; }
		button { margin-top:18px; width:100%; padding:10px; background:#6459f0; color:#fff; border:0;
		       border-radius:8px; font-size:14px; font-weight:600; cursor:pointer; }
		.err { background:rgba(228,107,107,.12); border:1px solid rgba(228,107,107,.4); color:#e46b6b;
		       padding:9px 11px; border-radius:8px; font-size:13px; margin-bottom:14px; }
	</style></head><body>';
	echo '<form method="post" action="' . minn_home_url( '/wp-login.php' ) . '">';
	echo '<h1>' . $site . '</h1>' . $err_html;
	echo '<label for="log">Username or Email</label>';
	echo '<input type="text" name="log" id="log" autocapitalize="none" autocomplete="username" autofocus>';
	echo '<label for="pwd">Password</label>';
	echo '<input type="password" name="pwd" id="pwd" autocomplete="current-password">';
	echo '<label class="row"><input type="checkbox" name="rememberme" value="forever"> Remember Me</label>';
	if ( '' !== $redirect ) {
		echo '<input type="hidden" name="redirect_to" value="' . $redirect . '">';
	}
	echo '<button type="submit" name="wp-submit">Log In</button>';
	echo '</form></body></html>';
}
