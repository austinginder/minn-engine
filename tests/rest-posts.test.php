<?php
/**
 * Milestone 1 suite: the engine's wp/v2 posts surface matches the fixtures
 * captured from a reference WordPress running against the same database.
 *
 * Run: php tests/rest-posts.test.php
 * Env: MINN_TEST_URL overrides the engine origin (default the local site).
 *
 * Comparison is structural (object key order ignored, array order enforced)
 * after normalizing the fixture's capture origin to the engine origin, since
 * the reference stack rewrites URLs per request host.
 */

$ENGINE  = rtrim( getenv( 'MINN_TEST_URL' ) ?: 'https://minn-engine.localhost', '/' );
$FIXDIR  = __DIR__ . '/../contracts/fixtures/rest';
$CAPTURE = 'http://127.0.0.1:8123';

require_once __DIR__ . '/lib.php';

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

function fixture( string $name ): array {
	global $FIXDIR, $CAPTURE, $ENGINE;
	$raw = file_get_contents( "$FIXDIR/$name" );
	$raw = minn_test_neutralise( str_replace( $CAPTURE, $ENGINE, $raw ) );
	return json_decode( $raw, true );
}

echo "rest-posts suite against $ENGINE\n";

// 1. List route, /wp-json form.
[ $h, $body ] = minn_test_fetch( "$ENGINE/wp-json/wp/v2/posts" );
$engine_list  = json_decode( $body, true );
check( 200 === $h['status'], 'list returns 200' );
check( str_starts_with( $h['content-type'] ?? '', 'application/json' ), 'list content-type json' );
check( 'nosniff' === ( $h['x-content-type-options'] ?? '' ), 'list nosniff header' );
check( isset( $h['x-wp-total'], $h['x-wp-totalpages'] ), 'list pagination headers present' );
$d = minn_test_diff( fixture( 'posts-list.json' ), $engine_list );
check( null === $d, 'list body matches fixture', (string) $d );
check( (string) count( $engine_list ) <= $h['x-wp-total'], 'total covers page size' );

// 2. Same route via the plain-permalink rest_route form.
[ , $body2 ] = minn_test_fetch( "$ENGINE/?rest_route=/wp/v2/posts" );
$d = minn_test_diff( $engine_list, json_decode( $body2, true ) );
check( null === $d, 'rest_route form equals wp-json form', (string) $d );

// 3. Single post.
[ $h3, $body3 ] = minn_test_fetch( "$ENGINE/wp-json/wp/v2/posts/1" );
check( 200 === $h3['status'], 'single returns 200' );
$d = minn_test_diff( fixture( 'posts-single.json' ), json_decode( $body3, true ) );
check( null === $d, 'single body matches fixture', (string) $d );

// 4. Invalid id.
[ $h4, $body4 ] = minn_test_fetch( "$ENGINE/wp-json/wp/v2/posts/999" );
check( 404 === $h4['status'], 'invalid id returns 404' );
$d = minn_test_diff( fixture( 'posts-invalid-id.json' ), json_decode( $body4, true ) );
check( null === $d, 'invalid id error shape', (string) $d );

// 5. Unknown route.
[ $h5, $body5 ] = minn_test_fetch( "$ENGINE/wp-json/wp/v2/nonexistent" );
check( 404 === $h5['status'], 'unknown route returns 404' );
$d = minn_test_diff( fixture( 'no-route.json' ), json_decode( $body5, true ) );
check( null === $d, 'no-route error shape', (string) $d );

// 6. The wider read surface, pinned against captured fixtures.
foreach ( array(
	'pages list'      => array( 'pages-list.json', '/wp/v2/pages' ),
	'categories list' => array( 'categories.json', '/wp/v2/categories' ),
	'tags list'       => array( 'tags.json', '/wp/v2/tags' ),
	'types'           => array( 'types.json', '/wp/v2/types' ),
) as $label => $spec ) {
	[ $fx, $route ] = $spec;
	[ , $b ]        = minn_test_fetch( "$ENGINE/wp-json$route" );
	$d              = minn_test_diff( fixture( $fx ), json_decode( $b, true ) );
	check( null === $d, "$label matches fixture", (string) $d );
}

// 7. The types _fields quirk: the associative payload strips to [].
[ , $bq ] = minn_test_fetch( "$ENGINE/wp-json/wp/v2/types?_fields=name,slug" );
check( '[]' === trim( $bq ), 'types _fields quirk yields []', $bq );

echo "\n$pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
