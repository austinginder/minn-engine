<?php
/**
 * Minn Engine × Minn Admin — serve the admin SPA from the engine.
 *
 * This is the milestone the whole project points at: Minn Admin (Austin's
 * MIT-licensed admin app) booting on Minn Engine instead of WordPress. The
 * engine authenticates the request, builds the window.MINN boot payload from
 * its own options + capability engine, renders the app shell, and serves the
 * app's CSS/JS from the symlinked dev copy. Minn Admin is MIT, so reading its
 * template shape is fine; the engine still runs no WordPress source.
 *
 * The app talks wp/v2 (which the engine serves) for content, media, users,
 * and terms. Its minn-admin/v1 custom endpoints (overview stats,
 * notifications, adapters) are not implemented here yet; app.js is defensive
 * about them, so those panels degrade rather than break the boot.
 */

/** Absolute path to the symlinked Minn Admin app, or null when absent. */
function minn_admin_app_dir(): ?string {
	$dir = dirname( __DIR__ ) . '/minn-admin-app';
	return is_dir( $dir ) ? $dir : null;
}

function minn_admin_version(): string {
	$dir = minn_admin_app_dir();
	if ( $dir && is_readable( "$dir/minn-admin.php" ) ) {
		$head = (string) file_get_contents( "$dir/minn-admin.php", false, null, 0, 2000 );
		if ( preg_match( "/MINN_ADMIN_VERSION',\s*'([^']+)'/", $head, $m ) ) {
			return $m[1];
		}
	}
	return '0.0.0';
}

/** Self-busting asset version: app version + file mtime. */
function minn_admin_asset_ver( string $rel ): string {
	$dir   = minn_admin_app_dir();
	$mtime = $dir ? @filemtime( "$dir/$rel" ) : 0;
	return minn_admin_version() . ( $mtime ? '.' . $mtime : '' );
}

/**
 * The window.MINN boot payload, assembled from the engine. A lean but honest
 * subset: the keys app.js needs to boot and drive the wp/v2 surface, with
 * capabilities from the engine's own cap model.
 */
function minn_admin_boot_payload( array $user, string $token ): array {
	$uid   = (int) $user['ID'];
	$roles = minn_user_roles( $uid );
	$role  = $roles ? ( minn_roles()[ $roles[0] ]['name'] ?? $roles[0] ) : '';

	$can = static fn( string $cap ) => minn_user_can( $uid, $cap );

	return array(
		// Pretty REST base: the client appends "wp/v2/posts?context=edit&…",
		// so the route must live in the path (not a rest_route query value) or
		// the first "?" folds the query into the route. The engine routes
		// /wp-json/* natively.
		'restUrl'    => minn_home_url( '/wp-json/' ),
		'nonce'      => minn_create_rest_nonce( $uid, $token ),
		'appUrl'     => minn_home_url( '/minn-admin' ),
		'version'    => minn_admin_version(),
		'engine'     => 'Minn Engine/' . MINN_ENGINE_VERSION,
		'user'       => array(
			'id'         => $uid,
			'login'      => $user['user_login'],
			'name'       => $user['display_name'],
			'role'       => $role,
			'avatar'     => 'https://secure.gravatar.com/avatar/' . hash( 'sha256', strtolower( trim( $user['user_email'] ) ) ) . '?s=64&d=mm&r=g',
			'appearance' => array( 'scheme' => 'minn' ),
			'policy'     => new stdClass(),
		),
		'site'       => array(
			'name'        => minn_option( 'blogname' ) ?? 'Site',
			'icon'        => '',
			'url'         => minn_home_url( '/' ),
			'adminUrl'    => minn_home_url( '/minn-admin/' ),
			'logout'      => minn_home_url( '/wp-login.php?action=logout' ),
			'blockTheme'  => false,
			'hasSidebars' => false,
		),
		'gmtOffset'  => (float) ( minn_option( 'gmt_offset' ) ?? 0 ),
		'locale'     => minn_option( 'WPLANG' ) ?: 'en_US',
		'rtl'        => false,
		'i18n'       => new stdClass(),
		'i18nPlural' => 'nplurals=2; plural=(n != 1);',
		'languages'  => array(),
		'caps'       => array(
			'plugins'      => $can( 'activate_plugins' ),
			'update'       => $can( 'update_plugins' ),
			'themes'       => $can( 'switch_themes' ),
			'settings'     => $can( 'manage_options' ),
			'moderate'     => $can( 'moderate_comments' ),
			'terms'        => $can( 'manage_categories' ),
			'upload'       => $can( 'upload_files' ),
			'users'        => $can( 'list_users' ),
			'readPrivate'  => $can( 'read_private_posts' ),
			'editPages'    => $can( 'edit_pages' ),
			'core'         => $can( 'update_core' ),
			'editUsers'    => $can( 'edit_users' ),
			'createUsers'  => $can( 'create_users' ),
			'promoteUsers' => $can( 'promote_users' ),
			'themeOptions' => $can( 'edit_theme_options' ),
			'editCss'      => $can( 'edit_css' ),
		),
		'ownOnly'    => array(),
		'multisite'  => false,
		'wc'         => false,
		'ajaxUrl'    => minn_home_url( '/wp-admin/admin-ajax.php' ),
	);
}

/**
 * Handle /minn-admin and /minn-admin/* (the SPA is path-routed; every
 * sub-path renders the same shell). Requires a logged-in user who can edit
 * content, else redirects to the login form.
 */
function minn_admin_handle_app(): void {
	$why  = '';
	$auth = minn_authenticate_session( $why );
	if ( null === $auth ) {
		$dest = minn_home_url( '/wp-login.php?redirect_to=' . rawurlencode( minn_current_url() ) );
		header( 'Location: ' . $dest, true, 302 );
		exit;
	}
	[ $user, $token ] = $auth;
	if ( ! minn_user_can( (int) $user['ID'], 'edit_posts' ) ) {
		http_response_code( 403 );
		header( 'Content-Type: text/html; charset=utf-8' );
		echo '<!doctype html><title>Not allowed</title><p>You do not have permission to access this admin.';
		exit;
	}
	if ( null === minn_admin_app_dir() ) {
		http_response_code( 500 );
		header( 'Content-Type: text/html; charset=utf-8' );
		echo '<!doctype html><title>Minn Admin not installed</title><p>The Minn Admin app is not linked into this engine.';
		exit;
	}

	$boot = minn_admin_boot_payload( $user, $token );
	minn_admin_render_shell( $boot );
	exit;
}

/**
 * The app shell. Reproduces the structure of Minn Admin's own template.php
 * (an MIT file): pre-paint theme, inline window.MINN, mount point, app.js.
 * Assets are served by the engine from the symlinked app at /minn-admin-asset.
 */
function minn_admin_render_shell( array $boot ): void {
	$name    = minn_esc( (string) ( $boot['site']['name'] ?? 'Site' ) );
	$css_ver = minn_admin_asset_ver( 'assets/css/app.css' );
	$js_ver  = minn_admin_asset_ver( 'assets/js/app.js' );
	$json    = json_encode( $boot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );

	header( 'Content-Type: text/html; charset=utf-8' );
	header( 'X-Powered-By: Minn Engine/' . MINN_ENGINE_VERSION );
	?>
<!DOCTYPE html>
<html lang="en" dir="ltr" data-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<title>Minn Admin — <?php echo $name; ?></title>
<link rel="stylesheet" href="/minn-admin-asset/assets/css/app.css?ver=<?php echo minn_esc( $css_ver ); ?>">
<script>
try {
	var stored = localStorage.getItem( 'minn-theme' );
	if ( ! stored ) { localStorage.setItem( 'minn-theme', 'system' ); stored = 'system'; }
	var follow = stored === 'system';
	if ( follow && window.matchMedia ) {
		var mq = window.matchMedia( '(prefers-color-scheme: light)' );
		document.documentElement.setAttribute( 'data-theme', mq.matches ? 'light' : 'dark' );
		mq.addEventListener( 'change', function ( e ) {
			if ( localStorage.getItem( 'minn-theme' ) === 'system' ) {
				document.documentElement.setAttribute( 'data-theme', e.matches ? 'light' : 'dark' );
				document.dispatchEvent( new CustomEvent( 'minn-theme-change' ) );
			}
		} );
	} else if ( stored === 'light' || stored === 'dark' ) {
		document.documentElement.setAttribute( 'data-theme', stored );
	}
} catch ( e ) {}
window.MINN = <?php echo $json; ?>;
</script>
</head>
<body>
<div id="minn-app"><div class="minn-boot-spinner"></div></div>
<script src="/minn-admin-asset/assets/js/app.js?ver=<?php echo minn_esc( $js_ver ); ?>"></script>
</body>
</html>
	<?php
}

/** Serve a static asset from the symlinked Minn Admin app (read-only, safe path). */
function minn_admin_serve_asset( string $rel ): void {
	$dir = minn_admin_app_dir();
	if ( null === $dir ) {
		http_response_code( 404 );
		exit;
	}
	// Refuse traversal; only serve from within the app dir.
	$rel  = ltrim( $rel, '/' );
	$full = realpath( "$dir/$rel" );
	if ( false === $full || ! str_starts_with( $full, realpath( $dir ) . '/' ) || ! is_file( $full ) ) {
		http_response_code( 404 );
		exit;
	}
	$types = array(
		'css'   => 'text/css',
		'js'    => 'application/javascript',
		'woff2' => 'font/woff2',
		'woff'  => 'font/woff',
		'svg'   => 'image/svg+xml',
		'png'   => 'image/png',
		'json'  => 'application/json',
	);
	$ext = strtolower( pathinfo( $full, PATHINFO_EXTENSION ) );
	header( 'Content-Type: ' . ( $types[ $ext ] ?? 'application/octet-stream' ) );
	header( 'Cache-Control: public, max-age=300' );
	readfile( $full );
	exit;
}

/** Minimal admin-ajax: the rest-nonce action app.js uses to refresh nonces. */
function minn_admin_handle_ajax(): void {
	$action = $_REQUEST['action'] ?? '';
	if ( 'rest-nonce' === $action ) {
		$why  = '';
		$auth = minn_authenticate_session( $why );
		header( 'Content-Type: text/html; charset=utf-8' );
		if ( null === $auth ) {
			echo '0';
			exit;
		}
		echo minn_create_rest_nonce( (int) $auth[0]['ID'], $auth[1] );
		exit;
	}
	http_response_code( 400 );
	echo '-1';
	exit;
}

/** Current absolute URL, for the login redirect_to. */
function minn_current_url(): string {
	$scheme = minn_is_ssl() ? 'https' : 'http';
	$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
	$uri    = $_SERVER['REQUEST_URI'] ?? '/';
	return $scheme . '://' . $host . $uri;
}
