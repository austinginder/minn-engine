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
file_put_contents( "$themes/$slug/page.php", "<?php // zz child page\n" );
register_shutdown_function( static function () use ( $wp, $themes, $slug, $before ): void {
	$wp( 'option update stylesheet ' . escapeshellarg( $before ) );
	$wp( 'cache flush' );
	@unlink( "$themes/$slug/style.css" );
	@unlink( "$themes/$slug/page.php" );
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

echo "\n$pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
