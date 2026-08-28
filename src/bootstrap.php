<?php
/**
 * Minn Engine — milestone 0.
 *
 * Proof of the Tier 1 thesis at its smallest: connect to a database that
 * WordPress created, read the site identity and content out of the stock
 * wp_* schema, and serve a page. No WordPress code runs anywhere in this
 * process; the schema is the contract.
 */

// PSR-4 autoloader for the Minn\ namespace: src/Minn/Http/Request.php is Minn\Http\Request.
spl_autoload_register( static function ( string $class ): void {
	if ( str_starts_with( $class, 'Minn\\' ) ) {
		$file = __DIR__ . '/' . str_replace( '\\', '/', $class ) . '.php';
		if ( is_file( $file ) ) {
			require $file;
		}
	}
} );

/** The legacy handle: the same connection Minn\Db holds. */
function minn_db(): mysqli {
	return Minn\Db::shared()->connection();
}

function minn_permalinks(): Minn\Front\Permalinks {
	static $permalinks = null;
	return $permalinks ??= Minn\Front\Permalinks::fromDb( Minn\Db::shared() );
}

function minn_option( string $name ): ?string {
	global $table_prefix;
	$stmt = minn_db()->prepare(
		"SELECT option_value FROM {$table_prefix}options WHERE option_name = ? LIMIT 1"
	);
	$stmt->bind_param( 's', $name );
	$stmt->execute();
	$row = $stmt->get_result()->fetch_row();
	return $row ? $row[0] : null;
}

function minn_esc( ?string $s ): string {
	return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
}

function minn_engine_serve(): void {
	require_once __DIR__ . '/caps.php';
	require_once __DIR__ . '/login.php';
	require_once __DIR__ . '/auth.php';
	require_once __DIR__ . '/rest.php';
	require_once __DIR__ . '/rest-minn.php';
	require_once __DIR__ . '/rest-comments.php';
	require_once __DIR__ . '/rest-media.php';
	require_once __DIR__ . '/rest-settings.php';
	require_once __DIR__ . '/rest-users.php';
	require_once __DIR__ . '/rest-terms.php';
	require_once __DIR__ . '/rest-editor.php';
	require_once __DIR__ . '/writes.php';
	require_once __DIR__ . '/login-endpoint.php';
	require_once __DIR__ . '/minn-admin.php';

	$path = parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH ) ?? '/';
	if ( isset( $_GET['rest_route'] ) ) {
		minn_rest_dispatch( (string) $_GET['rest_route'] );
	}
	if ( str_starts_with( $path, '/wp-json' ) ) {
		minn_rest_dispatch( substr( $path, strlen( '/wp-json' ) ) ?: '/' );
	}
	if ( '/wp-login.php' === $path ) {
		minn_handle_login();
	}
	if ( '/wp-admin/admin-ajax.php' === $path ) {
		minn_admin_handle_ajax();
	}
	if ( str_starts_with( $path, '/minn-admin-asset/' ) ) {
		minn_admin_serve_asset( substr( $path, strlen( '/minn-admin-asset/' ) ) );
	}
	if ( '/minn-admin' === $path || str_starts_with( $path, '/minn-admin/' ) ) {
		minn_admin_handle_app();
	}
	minn_front_serve();
}

/** The public site: everything the legacy routes above did not claim. */
function minn_front_serve(): never {
	$db       = Minn\Db::shared();
	$can_read = static function ( array $post ): bool {
		$why  = '';
		$user = minn_authenticate_session( $why );
		return null !== $user && minn_user_can( (int) $user['ID'], 'edit_post', (int) $post['ID'] );
	};
	$resolver = Minn\Front\Resolver::fromDb( $db, $can_read );
	$renderer = new Minn\Front\Renderer( $db, new Minn\Content\Posts( $db ), $resolver->permalinks(), $resolver->perPage() );
	$router   = ( new Minn\Http\Router() )->register( new Minn\Front\FrontController( $resolver, $renderer ) );
	$response = ( new Minn\Http\Kernel( $router ) )->handle( Minn\Http\Request::fromGlobals() );
	( $response ?? Minn\Http\Response::html( '<!doctype html><title>Not Found</title><p>Not found.', 404 ) )->send();
}
