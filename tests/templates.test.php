<?php
/**
 * wp/v2/templates and wp/v2/template-parts: the Design > Templates screen's
 * reads, diffed against the oracle request by request, plus the write
 * lifecycle (customize a theme template, reset it, add one, delete it)
 * proven cross-stack. Cleans up every row it creates.
 *
 * Ref: (cd wp-reference && php -S 127.0.0.1:8123) — SKIPs cleanly when down.
 */

require_once __DIR__ . '/lib.php';

$ROOT   = dirname( __DIR__ );
$ENGINE = minn_test_url();
$REF    = minn_test_reference_url();

[ $ph ] = minn_test_fetch( "$REF/?rest_route=/wp/v2/posts", 3 );
if ( 200 !== $ph['status'] ) {
	echo "SKIP: reference WordPress not running at $REF (start: cd wp-reference && php -S 127.0.0.1:8123)\n";
	exit( 0 );
}

$pass = 0;
$fail = 0;

function check( bool $ok, string $label, string $detail = '' ): void {
	global $pass, $fail;
	if ( $ok ) {
		$pass++;
		echo "  ok  $label\n";
	} else {
		$fail++;
		echo "FAIL  $label" . ( $detail ? "\n      $detail" : '' ) . "\n";
	}
}

function tpl_mint( int $uid ): array {
	global $ROOT;
	$mint = json_decode( (string) shell_exec(
		'wp --path=' . escapeshellarg( minn_test_site_root() . '/wp-reference' ) . ' eval-file ' . escapeshellarg( "$ROOT/tests/tools/mint-session.php" ) . " $uid 2>/dev/null"
	), true );
	if ( ! $mint || empty( $mint['cookie'] ) ) {
		echo "SKIP: could not mint a reference session (wp-cli unavailable?)\n";
		exit( 0 );
	}
	return $mint;
}

/** One request against one stack. The route rides ?rest_route=, so its &-args are split off. */
function tpl_fetch( string $base, ?array $mint, string $route, array $args = array(), string $method = 'GET', ?string $body = null ): array {
	global $REF;
	$headers = array();
	if ( $mint ) {
		$alt       = 'wordpress_logged_in_' . md5( $REF );
		$headers[] = 'Cookie: ' . $mint['cookie_name'] . '=' . $mint['cookie'] . '; ' . $alt . '=' . $mint['cookie'];
		$headers[] = 'X-WP-Nonce: ' . $mint['nonce'];
	}
	if ( null !== $body ) {
		$headers[] = 'Content-Type: application/json';
	}
	$url = $base . '/?rest_route=' . rawurlencode( $route );
	foreach ( $args as $k => $v ) {
		$url .= '&' . $k . '=' . rawurlencode( (string) $v );
	}
	$ctx = stream_context_create(
		array(
			'ssl'  => array( 'verify_peer' => false, 'verify_peer_name' => false ),
			'http' => array(
				'ignore_errors' => true,
				'timeout'       => 15,
				'method'        => $method,
				'header'        => implode( "\r\n", $headers ),
				'content'       => $body ?? '',
			),
		)
	);
	$raw    = @file_get_contents( $url, false, $ctx );
	$status = 0;
	foreach ( $http_response_header ?? array() as $h ) {
		if ( preg_match( '#^HTTP/\S+\s+(\d+)#', $h, $m ) ) {
			$status = (int) $m[1];
		}
	}
	return array( $status, json_decode( (string) $raw, true ) );
}

function tpl_norm( $x ) {
	global $ENGINE, $REF;
	if ( is_array( $x ) ) {
		return array_map( 'tpl_norm', $x );
	}
	if ( ! is_string( $x ) ) {
		return $x;
	}
	// The reference reads its own home option through Cove's helper filter
	// and prints the site over http; the engine answers over the scheme the
	// request arrived on. Both name the same page.
	$plain = 'http://' . (string) parse_url( $ENGINE, PHP_URL_HOST );
	return str_replace( array( $REF, $plain ), $ENGINE, $x );
}

/** Both stacks answer the same request identically. */
function tpl_same( string $label, ?array $mint, string $route, array $args = array() ): void {
	global $ENGINE, $REF;
	[ $rs, $rb ] = tpl_fetch( $REF, $mint, $route, $args );
	[ $es, $eb ] = tpl_fetch( $ENGINE, $mint, $route, $args );
	$d = minn_test_diff( tpl_norm( $rb ), tpl_norm( $eb ) );
	check( $rs === $es && null === $d, $label, "status $rs vs $es; " . (string) $d );
}

/** Rows the suite creates go away even when a check throws. */
function tpl_cleanup(): void {
	shell_exec(
		'cd ' . escapeshellarg( minn_test_site_root() . '/wp-reference' ) .
		' && wp eval \'foreach ( get_posts( array( "post_type" => array( "wp_template", "wp_template_part" ), "post_status" => array( "publish", "trash", "draft", "pending", "private", "future", "auto-draft" ), "numberposts" => -1 ) ) as $p ) { wp_delete_post( $p->ID, true ); }\' 2>/dev/null; true'
	);
}
register_shutdown_function( 'tpl_cleanup' );
tpl_cleanup();

echo "templates suite: $ENGINE (engine) / $REF (oracle)\n";

$admin  = tpl_mint( 1 );
$editor = tpl_mint( 2 );
$author = tpl_mint( 3 );

$FIELDS = 'id,slug,title,description,source,origin,has_theme_file,is_custom,author,modified,type,area';

// 1. The two lists the Design > Templates screen loads, in both contexts.
foreach ( array( '/wp/v2/templates', '/wp/v2/template-parts' ) as $route ) {
	tpl_same( "$route lists identically", $admin, $route );
	tpl_same( "$route edit context matches", $admin, $route, array( 'context' => 'edit' ) );
	tpl_same( "$route with the app's _fields", $admin, $route, array( 'context' => 'edit', '_fields' => $FIELDS ) );
}

// 2. Every template and part, one by one, in both contexts.
[ , $list ] = tpl_fetch( $REF, $admin, '/wp/v2/templates', array( '_fields' => 'id' ) );
[ , $parts ] = tpl_fetch( $REF, $admin, '/wp/v2/template-parts', array( '_fields' => 'id' ) );
check( count( (array) $list ) > 0 && count( (array) $parts ) > 0, 'the fixture theme ships templates and parts' );
foreach ( (array) $list as $row ) {
	tpl_same( "single {$row['id']}", $admin, '/wp/v2/templates/' . $row['id'], array( 'context' => 'edit' ) );
}
foreach ( (array) $parts as $row ) {
	tpl_same( "single part {$row['id']}", $admin, '/wp/v2/template-parts/' . $row['id'], array( 'context' => 'edit' ) );
}

// 3. The gates. Reading is an editor's and an author's business; writing is not.
tpl_same( 'anonymous read refused identically', null, '/wp/v2/templates' );
tpl_same( 'editor may read the list', $editor, '/wp/v2/templates', array( '_fields' => 'id' ) );
tpl_same( 'author may read the list', $author, '/wp/v2/templates', array( '_fields' => 'id' ) );
tpl_same( "an author's links drop the write actions", $author, '/wp/v2/templates/twentytwentyfive//404', array( '_fields' => '_links' ) );

// 4. An unknown id, and one that names another theme.
tpl_same( 'unknown slug is not found', $admin, '/wp/v2/templates/twentytwentyfive//nope' );
tpl_same( 'another theme is not found', $admin, '/wp/v2/templates/someothertheme//index' );
tpl_same( 'a bare id is not found', $admin, '/wp/v2/templates/nope' );

// 5. Writes refused below edit_theme_options, on both stacks.
foreach ( array( 'editor' => $editor, 'author' => $author ) as $who => $mint ) {
	[ $rs, $rb ] = tpl_fetch( $REF, $mint, '/wp/v2/templates/twentytwentyfive//page', array(), 'POST', '{"content":"x"}' );
	[ $es, $eb ] = tpl_fetch( $ENGINE, $mint, '/wp/v2/templates/twentytwentyfive//page', array(), 'POST', '{"content":"x"}' );
	check( $rs === $es && null === minn_test_diff( $rb, $eb ), "$who write refused identically", "status $rs vs $es" );
}

// 6. The engine customizes a theme template; WordPress reads the site copy back.
$markup = '<!-- wp:paragraph --><p>engine copy</p><!-- /wp:paragraph -->';
[ $st, $b ] = tpl_fetch( $ENGINE, $admin, '/wp/v2/templates/twentytwentyfive//page', array(), 'POST', json_encode( array( 'content' => $markup, 'title' => 'Pages' ) ) );
check( 200 === $st && 'custom' === ( $b['source'] ?? '' ) && true === ( $b['has_theme_file'] ?? null ) && ( $b['wp_id'] ?? 0 ) > 0, 'engine write makes a site copy', "status $st" );
[ , $rb ] = tpl_fetch( $REF, $admin, '/wp/v2/templates/twentytwentyfive//page', array( 'context' => 'edit' ) );
check( 'custom' === ( $rb['source'] ?? '' ) && $markup === ( $rb['content']['raw'] ?? '' ) && 'theme' === ( $rb['origin'] ?? '' ), 'WordPress reads the engine-written template' );
tpl_same( 'the customized row matches in both lists', $admin, '/wp/v2/templates', array( 'context' => 'edit', '_fields' => $FIELDS ) );

// 7. Reset to theme: the app sends force=true and expects the file back.
[ $rs, $rb ] = tpl_fetch( $REF, $admin, '/wp/v2/templates/twentytwentyfive//page', array( 'force' => 'true' ), 'DELETE' );
check( 200 === $rs && true === ( $rb['deleted'] ?? null ), 'WordPress resets the engine-written template', "status $rs" );
[ , $eb ] = tpl_fetch( $ENGINE, $admin, '/wp/v2/templates/twentytwentyfive//page' );
check( 'theme' === ( $eb['source'] ?? '' ) && 0 === ( $eb['wp_id'] ?? -1 ), 'the engine hands the theme file back' );

// 8. The same lifecycle the other way round, and the without-force trash step.
[ $st ] = tpl_fetch( $REF, $admin, '/wp/v2/templates/twentytwentyfive//page', array(), 'POST', json_encode( array( 'content' => $markup ) ) );
check( 200 === $st, 'WordPress makes a site copy' );
[ $es, $eb ] = tpl_fetch( $ENGINE, $admin, '/wp/v2/templates/twentytwentyfive//page', array(), 'DELETE' );
check( 200 === $es && 'trash' === ( $eb['status'] ?? '' ), 'a delete without force trashes the copy', "status $es" );
tpl_same( 'a trashed copy leaves the theme file listed', $admin, '/wp/v2/templates', array( '_fields' => 'id,source,wp_id' ) );
tpl_cleanup();

// 9. A template that exists only as a theme file cannot be removed.
foreach ( array( $REF, $ENGINE ) as $base ) {
	[ $s, $b ] = tpl_fetch( $base, $admin, '/wp/v2/templates/twentytwentyfive//404', array( 'force' => 'true' ), 'DELETE' );
	check( 400 === $s && 'rest_invalid_template' === ( $b['code'] ?? '' ), 'theme-file template refuses deletion (' . ( $base === $REF ? 'oracle' : 'engine' ) . ')', "status $s" );
}

// 10. An added-here template: created on the oracle, listed and deleted by the engine.
shell_exec(
	'cd ' . escapeshellarg( minn_test_site_root() . '/wp-reference' ) .
	' && wp eval \'$id = wp_insert_post( array( "post_type" => "wp_template", "post_name" => "zz-suite-added", "post_title" => "ZZ Added", "post_status" => "publish", "post_author" => 1, "post_content" => "<p>x</p>" ) ); wp_set_object_terms( $id, "twentytwentyfive", "wp_theme" );\' 2>/dev/null'
);
tpl_same( 'an added-here template matches', $admin, '/wp/v2/templates/twentytwentyfive//zz-suite-added', array( 'context' => 'edit' ) );
[ $es, $eb ] = tpl_fetch( $ENGINE, $admin, '/wp/v2/templates/twentytwentyfive//zz-suite-added', array( 'force' => 'true' ), 'DELETE' );
check( 200 === $es && true === ( $eb['deleted'] ?? null ) && isset( $eb['previous']['id'] ), 'the engine deletes an added-here template', "status $es" );
tpl_same( 'both stacks agree it is gone', $admin, '/wp/v2/templates/twentytwentyfive//zz-suite-added' );

// 11. The theme's own file order is the order the directory hands back, unsorted.
[ , $ids ] = tpl_fetch( $ENGINE, $admin, '/wp/v2/templates', array( '_fields' => 'id' ) );
$dir  = minn_test_site_root() . '/public/wp-content/themes/twentytwentyfive/templates';
$disk = array();
foreach ( (array) @scandir( $dir, SCANDIR_SORT_NONE ) as $entry ) {
	if ( str_ends_with( (string) $entry, '.html' ) ) {
		$disk[] = 'twentytwentyfive//' . substr( (string) $entry, 0, -5 );
	}
}
check( $disk === array_column( (array) $ids, 'id' ), 'theme files list in directory order', implode( ',', $disk ) );

tpl_cleanup();
echo "\n$pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
