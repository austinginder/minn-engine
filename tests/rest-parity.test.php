<?php
/**
 * Live parity suite: diff the engine against a running reference WordPress on
 * the SAME database, request by request. The reference is the oracle; any
 * divergence is an engine bug or a contract gap, and either way it is a
 * finding.
 *
 * Run:  php tests/rest-parity.test.php
 * Ref:  (cd wp-reference && php -S 127.0.0.1:8123) — SKIPs cleanly when down.
 * Env:  MINN_TEST_URL overrides the engine origin.
 */

$ENGINE = rtrim( getenv( 'MINN_TEST_URL' ) ?: 'https://minn.localhost', '/' );
$REF    = 'http://127.0.0.1:8123';

require_once __DIR__ . '/lib.php';

// Reference reachable?
[ $ph ] = minn_test_fetch( "$REF/?rest_route=/wp/v2/posts", 3 );
if ( 200 !== $ph['status'] ) {
	echo "SKIP: reference WordPress not running at $REF (start: cd wp-reference && php -S 127.0.0.1:8123)\n";
	exit( 0 );
}

$pass = 0;
$fail = 0;

function parity( string $label, string $route ): void {
	global $ENGINE, $REF, $pass, $fail;
	$path  = $route;
	$extra = '';
	if ( str_contains( $route, '&' ) ) {
		[ $path, $qs ] = explode( '&', $route, 2 );
		$extra         = '&' . $qs;
	}
	[ $rh, $rb ] = minn_test_fetch( "$REF/?rest_route=" . rawurlencode( $path ) . $extra );
	[ $eh, $eb ] = minn_test_fetch( "$ENGINE/?rest_route=" . rawurlencode( $path ) . $extra );

	$ok = true;
	$why = '';
	if ( $rh['status'] !== $eh['status'] ) {
		$ok  = false;
		$why = "status {$rh['status']} vs {$eh['status']}";
	} else {
		// The reference emits \/-escaped slashes in JSON strings; normalize
		// both the plain and escaped origin forms before decoding.
		$ref_escaped = str_replace( '/', '\\/', $REF );
		$ref_json    = json_decode( str_replace( array( $ref_escaped, $REF ), $ENGINE, $rb ), true );
		$engine_json = json_decode( $eb, true );
		$d           = minn_test_diff( $ref_json, $engine_json );
		if ( null !== $d ) {
			$ok  = false;
			$why = $d;
		}
	}
	foreach ( array( 'x-wp-total', 'x-wp-totalpages' ) as $h ) {
		if ( ( $rh[ $h ] ?? null ) !== ( $eh[ $h ] ?? null ) ) {
			$ok   = false;
			$why .= " header $h " . ( $rh[ $h ] ?? '∅' ) . ' vs ' . ( $eh[ $h ] ?? '∅' );
		}
	}
	if ( $ok ) {
		$pass++;
		echo "  ok  $label\n";
	} else {
		$fail++;
		echo "FAIL  $label\n      $why\n";
	}
}

echo "rest-parity suite: $REF (oracle) vs $ENGINE\n";

parity( 'posts list', '/wp/v2/posts' );
parity( 'posts list page 1 of 1-per-page', '/wp/v2/posts&per_page=1' );
parity( 'posts single 1', '/wp/v2/posts/1' );
parity( 'posts single 5', '/wp/v2/posts/5' );
parity( 'invalid id', '/wp/v2/posts/999' );
parity( 'unknown route', '/wp/v2/nonexistent' );

parity( 'posts _fields id,title,status', '/wp/v2/posts&_fields=id,title,status' );
parity( 'posts _fields nested title.rendered', '/wp/v2/posts&_fields=id,title.rendered' );
parity( 'posts _fields into _links', '/wp/v2/posts&_fields=id,_links.self' );
parity( 'posts _fields unknown key', '/wp/v2/posts&_fields=bogus' );
parity( 'single _fields', '/wp/v2/posts/1&_fields=id,slug,link' );

parity( 'pages list', '/wp/v2/pages' );
parity( 'posts author_exclude', '/wp/v2/posts&author_exclude=1&_fields=id,author' );
parity( 'posts author list', '/wp/v2/posts&author=1,3&_fields=id,author' );
parity( 'pages author_exclude', '/wp/v2/pages&author_exclude=1&_fields=id,author' );
parity( 'pages menu_order', '/wp/v2/pages&menu_order=0&_fields=id,menu_order' );
parity( 'pages parent_exclude', '/wp/v2/pages&parent_exclude=0&_fields=id,parent' );
parity( 'pages single parent', '/wp/v2/pages/2' );
parity( 'pages single child (up link)', '/wp/v2/pages/6' );
parity( 'pages invalid id', '/wp/v2/pages/999' );

parity( 'categories list', '/wp/v2/categories' );
parity( 'categories exclude', '/wp/v2/categories&exclude=1&_fields=id' );
parity( 'categories slug', '/wp/v2/categories&slug=uncategorized&_fields=id,slug' );
parity( 'categories single', '/wp/v2/categories/1' );
parity( 'tags list', '/wp/v2/tags' );
parity( 'tags single', '/wp/v2/tags/2' );
parity( 'term invalid id', '/wp/v2/categories/99' );
parity( 'term wrong taxonomy', '/wp/v2/categories/2' );

parity( 'types list', '/wp/v2/types' );
parity( 'types single post', '/wp/v2/types/post' );
parity( 'types single wp_navigation', '/wp/v2/types/wp_navigation' );
parity( 'types invalid', '/wp/v2/types/bogus' );
parity( 'types _fields quirk strips to []', '/wp/v2/types&_fields=name,slug' );

// The Link header: a collection's previous and next pages, a viewable post's page on the
// site, and the API root on everything else, with the reference's origin read as the engine's.
function link_parity( string $label, string $path ): void {
	global $ENGINE, $REF, $pass, $fail;
	[ $rh ] = minn_test_fetch( $REF . $path );
	[ $eh ] = minn_test_fetch( $ENGINE . $path );
	$want = str_replace( $REF, $ENGINE, (string) ( $rh['link'] ?? '' ) );
	$got  = (string) ( $eh['link'] ?? '' );
	if ( $want === $got ) {
		$pass++;
		echo "  ok   link: $label\n";
	} else {
		$fail++;
		echo "  FAIL link: $label ($want vs $got)\n";
	}
}
link_parity( 'posts, a middle page', '/wp-json/wp/v2/posts?per_page=2&page=2' );
link_parity( 'posts by rest_route, a middle page', '/?rest_route=/wp/v2/posts&per_page=2&page=2' );
link_parity( 'users, the first page', '/wp-json/wp/v2/users?per_page=1' );
link_parity( 'comments, a last page', '/wp-json/wp/v2/comments?per_page=1&page=2' );
link_parity( 'categories, one page', '/wp-json/wp/v2/categories?per_page=1' );
link_parity( 'a post', '/wp-json/wp/v2/posts/1' );
link_parity( 'a page', '/wp-json/wp/v2/pages/2' );
link_parity( 'the index', '/wp-json/' );
link_parity( 'no such route', '/wp-json/wp/v2/nope' );
link_parity( 'a refusal', '/wp-json/wp/v2/settings' );

echo "\n$pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
