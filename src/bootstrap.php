<?php
/**
 * Minn Engine — milestone 0.
 *
 * Proof of the Tier 1 thesis at its smallest: connect to a database that
 * WordPress created, read the site identity and content out of the stock
 * wp_* schema, and serve a page. No WordPress code runs anywhere in this
 * process; the schema is the contract.
 */

function minn_db(): mysqli {
	static $db = null;
	if ( $db instanceof mysqli ) {
		return $db;
	}
	$db = new mysqli( DB_HOST, DB_USER, DB_PASSWORD, DB_NAME );
	$db->set_charset( 'utf8mb4' );
	return $db;
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

function minn_latest_posts( int $limit = 5 ): array {
	global $table_prefix;
	$stmt = minn_db()->prepare(
		"SELECT ID, post_title, post_date FROM {$table_prefix}posts
		 WHERE post_type = 'post' AND post_status = 'publish'
		 ORDER BY post_date DESC LIMIT ?"
	);
	$stmt->bind_param( 'i', $limit );
	$stmt->execute();
	return $stmt->get_result()->fetch_all( MYSQLI_ASSOC );
}

function minn_esc( ?string $s ): string {
	return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
}

function minn_engine_serve(): void {
	require_once __DIR__ . '/auth.php';
	require_once __DIR__ . '/rest.php';

	$path = parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH ) ?? '/';
	if ( isset( $_GET['rest_route'] ) ) {
		minn_rest_dispatch( (string) $_GET['rest_route'] );
	}
	if ( str_starts_with( $path, '/wp-json' ) ) {
		minn_rest_dispatch( substr( $path, strlen( '/wp-json' ) ) ?: '/' );
	}
	if ( '/' !== $path ) {
		http_response_code( 404 );
		header( 'Content-Type: text/html; charset=utf-8' );
		echo '<!doctype html><title>Not Found</title><p>Not found.';
		exit;
	}

	minn_homepage();
}

function minn_homepage(): void {
	$title   = minn_option( 'blogname' ) ?? 'Untitled';
	$tagline = minn_option( 'blogdescription' ) ?? '';
	$posts   = minn_latest_posts();

	header( 'Content-Type: text/html; charset=utf-8' );
	header( 'X-Powered-By: Minn Engine/' . MINN_ENGINE_VERSION );

	echo '<!doctype html><html lang="en"><head><meta charset="utf-8">';
	echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
	echo '<title>' . minn_esc( $title ) . '</title>';
	echo '<style>
		body { margin:0; background:#0b0b0d; color:#ececed; font:17px/1.6 "Hanken Grotesk", "Helvetica Neue", sans-serif;
		       display:flex; min-height:100vh; align-items:center; justify-content:center; }
		main { max-width:560px; padding:40px 28px; }
		h1 { font-size:38px; font-weight:800; letter-spacing:-0.02em; margin:0 0 6px; }
		.tag { color:#9d9da7; margin:0 0 28px; }
		ul { list-style:none; margin:0; padding:0; }
		li { padding:10px 0; border-top:1px solid #242429; color:#9d9da7; }
		li b { color:#ececed; font-weight:600; display:block; }
		footer { margin-top:34px; padding-top:14px; border-top:1px solid #242429;
		         font:12px/1.6 "JetBrains Mono", monospace; color:#8a8a94; }
		footer em { color:#8a80f8; font-style:normal; }
	</style></head><body><main>';
	echo '<h1>' . minn_esc( $title ) . '</h1>';
	if ( '' !== $tagline ) {
		echo '<p class="tag">' . minn_esc( $tagline ) . '</p>';
	}
	echo '<ul>';
	foreach ( $posts as $p ) {
		echo '<li><b>' . minn_esc( $p['post_title'] ) . '</b>' . minn_esc( substr( $p['post_date'], 0, 10 ) ) . '</li>';
	}
	echo '</ul>';
	echo '<footer>Served by <em>Minn Engine ' . MINN_ENGINE_VERSION . '</em> from a database WordPress made · PHP ' . PHP_VERSION . '</footer>';
	echo '</main></body></html>';
}
