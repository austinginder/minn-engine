<?php
/**
 * A child theme of a block theme: with a child that leaves theme.json to
 * its parent and keeps a PHP page.php of its own active for the suite (the
 * stylesheet option alone, restored on shutdown), probe child-theme run on
 * the reference and on the engine, and the template the site editor is
 * told a post gets (?_wp-find-template), compared.
 * Ref: the test site's Cove twin (cove twin minn add --as-site=ref.minn.localhost).
 */

require_once __DIR__ . '/lib.php';

$ENGINE = minn_test_url();
$REF    = minn_test_reference_url();
$ROOT   = dirname( __DIR__ );

$pass = 0;
$fail = 0;

function check( bool $ok, string $label, string $detail = '' ): void {
	global $pass, $fail;
	if ( $ok ) {
		$pass++;
		echo "  ok  $label\n";
	} else {
		$fail++;
		echo "FAIL  $label" . ( $detail ? "\n      " . substr( $detail, 0, 600 ) : '' ) . "\n";
	}
}

[ $ph ] = minn_test_fetch( "$REF/?rest_route=/wp/v2/posts", 3 );
if ( 200 !== $ph['status'] ) {
	echo "SKIP: reference WordPress not running at $REF\n";
	exit( 0 );
}
echo "child-theme suite: $ENGINE (engine) / $REF (oracle)\n";

$wp     = static fn ( string $command ): string => trim( (string) shell_exec( 'wp --path=' . escapeshellarg( minn_test_site_root() . '/wp-reference' ) . " $command 2>/dev/null" ) );
$themes = minn_test_site_root() . '/wp-reference/wp-content/themes';
$slug   = 'zz-child-suite';
$before = $wp( 'option get stylesheet' );
@mkdir( "$themes/$slug" );
file_put_contents( "$themes/$slug/style.css", "/*\nTheme Name: ZZ Child Suite\nTemplate: $before\n*/\n" );
// The child's page.php renders a page through its own header.php and footer.php (the hierarchy picks it over the
// parent's block template, contracts/runtime.md "The 0.1.0 field test, and the other entry scripts").
file_put_contents( "$themes/$slug/page.php", "<?php // zz child page\nget_header();\necho '<main>';\nwhile ( have_posts() ) { the_post(); the_title( '<h1>', '</h1>' ); the_content(); }\necho '</main>';\nget_footer();\n" );
file_put_contents( "$themes/$slug/header.php", "<!DOCTYPE html>\n<html <?php language_attributes(); ?>>\n<head><meta charset=\"<?php bloginfo( 'charset' ); ?>\"><?php wp_head(); ?></head>\n<body <?php body_class(); ?>><?php wp_body_open(); ?><header>ZZCHILDHEADER <?php bloginfo( 'name' ); ?></header>\n" );
file_put_contents( "$themes/$slug/footer.php", "<footer>ZZCHILDFOOTER</footer><?php wp_footer(); ?></body></html>\n" );
// A page's own PHP templates, one at the root and one a folder down.
@mkdir( "$themes/$slug/page-templates" );
file_put_contents( "$themes/$slug/template-full.php", "<?php\n/* Template Name: ZZ Full */\necho '<main>ZZFULLTEMPLATE</main>';\n" );
file_put_contents( "$themes/$slug/page-templates/sub.php", "<?php\n/* Template Name: ZZ Sub */\necho '<main>ZZSUBTEMPLATE</main>';\n" );
$pageTemplate = $wp( 'post meta get 2 _wp_page_template' );
register_shutdown_function( static function () use ( $wp, $themes, $slug, $before ): void {
	$wp( 'option update stylesheet ' . escapeshellarg( $before ) );
	$wp( 'cache flush' );
	@unlink( "$themes/$slug/style.css" );
	@unlink( "$themes/$slug/page.php" );
	@unlink( "$themes/$slug/header.php" );
	@unlink( "$themes/$slug/footer.php" );
	@unlink( "$themes/$slug/template-full.php" );
	@unlink( "$themes/$slug/page-templates/sub.php" );
	@rmdir( "$themes/$slug/page-templates" );
	@rmdir( "$themes/$slug" );
} );
$wp( 'option update stylesheet ' . $slug );
$wp( 'cache flush' );

$reference = json_decode( $wp( 'eval-file ' . escapeshellarg( "$ROOT/tests/tools/child-theme-probe.php" ) ), true );
$engine    = json_decode( (string) shell_exec( 'php ' . escapeshellarg( "$ROOT/tests/tools/run-api-probe.php" ) . ' child-theme-probe.php 2>/dev/null' ), true );
check( is_array( $reference ) && is_array( $engine ), 'the probe ran on both stacks' );
$byLabel = array();
foreach ( (array) $engine as $row ) {
	$byLabel[ $row[0] ] = $row[1];
}
foreach ( (array) $reference as [ $label, $value ] ) {
	$d = minn_test_diff( $value, $byLabel[ $label ] ?? null );
	check( null === $d, $label, (string) $d );
}

// The site editor asking which template a post gets: the child's name on the template.
$answers = array();
foreach ( array( $REF, $ENGINE ) as $base ) {
	[ $page, $body ] = minn_test_fetch( "$base/?p=1&_wp-find-template=1", 15 );
	$answers[] = array( $page['status'], (string) $body );
}
check( $answers[0] === $answers[1] && str_contains( $answers[0][1], "\"theme\":\"$slug\"" ), 'the template a post gets names the child', substr( $answers[1][1], 0, 200 ) );

// The page the child's page.php draws: its header, the post, its footer, the body classes, and the global styles
// printed before the theme's stylesheet, as on the reference.
$pages = array();
foreach ( array( $REF, $ENGINE ) as $base ) {
	[ $page, $body ] = minn_test_fetch( "$base/sample-page/", 15 );
	$html  = str_replace( $REF, $ENGINE, (string) $body );
	$main  = preg_match( '#<main>.*?</main>#s', $html, $m ) ? $m[0] : '';
	$class = preg_match( '#<body class="([^"]*)"#', $html, $m ) ? $m[1] : '';
	$order = array_values( array_intersect( preg_match_all( "#id=['\"]([a-z0-9-]+)['\"]#", $html, $ids ) ? $ids[1] : array(), array( 'global-styles-inline-css', 'twentytwentyfive-style-inline-css', 'twentytwentyfive-style-css' ) ) );
	$pages[] = array( $page['status'], str_contains( $html, 'ZZCHILDHEADER' ) && str_contains( $html, 'ZZCHILDFOOTER' ), $main, $class, $order );
}
check( $pages[0][1] && $pages[1][1], 'the child\'s page.php renders through its own header and footer on both stacks', json_encode( array( $pages[0][1], $pages[1][1] ) ) );
check( $pages[0][2] !== '' && $pages[0][2] === $pages[1][2], 'the page it draws matches the reference', substr( $pages[1][2], 0, 300 ) );
check( $pages[0][3] === $pages[1][3], 'its body classes match the reference', $pages[1][3] );
check( $pages[0][4] === $pages[1][4] && $pages[1][4] !== array(), 'global styles print before the theme\'s stylesheet, as on the reference', json_encode( array( $pages[0][4], $pages[1][4] ) ) );

// The page's own template, chosen in its meta: named at the root or a folder down it renders; a name that climbs out is not taken.
foreach ( array( 'template-full.php' => 'ZZFULLTEMPLATE', 'page-templates/sub.php' => 'ZZSUBTEMPLATE', 'page-templates/../template-full.php' => '' ) as $chosen => $marker ) {
	$wp( 'post meta update 2 _wp_page_template ' . escapeshellarg( $chosen ) );
	$wp( 'cache flush' );
	$seen = array();
	foreach ( array( $REF, $ENGINE ) as $base ) {
		[ , $body ] = minn_test_fetch( "$base/sample-page/", 15 );
		$seen[] = preg_match( '#ZZ(FULL|SUB)TEMPLATE#', (string) $body, $m ) ? $m[0] : 'block template';
	}
	check( $seen[0] === $seen[1] && $seen[0] === ( $marker === '' ? 'block template' : $marker ), "the page template $chosen renders as on the reference", json_encode( $seen ) );
}
$wp( '' === $pageTemplate ? 'post meta delete 2 _wp_page_template' : 'post meta update 2 _wp_page_template ' . escapeshellarg( $pageTemplate ) );

echo "\n$pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
