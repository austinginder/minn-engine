<?php
/**
 * Live parity suite: diff the engine against a running reference WordPress on
 * the SAME database, request by request. The reference is the oracle; any
 * divergence is an engine bug or a contract gap, and either way it is a
 * finding.
 *
 * Run:  php tests/rest-parity.test.php
 * Ref:  (its Cove twin: cove twin minn add --as-site=ref.minn.localhost) — SKIPs cleanly when down.
 * Env:  MINN_TEST_URL overrides the engine origin.
 */

$ENGINE = rtrim( getenv( 'MINN_TEST_URL' ) ?: 'https://minn.localhost', '/' );
$REF    = 'https://ref.minn.localhost';

require_once __DIR__ . '/lib.php';

// Reference reachable?
[ $ph ] = minn_test_fetch( "$REF/?rest_route=/wp/v2/posts", 3 );
if ( 200 !== $ph['status'] ) {
	echo "SKIP: reference WordPress not running at $REF (its Cove twin: cove twin minn add --as-site=ref.minn.localhost)\n";
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

// A path's integer capture sent again in the query is the argument the route reads, on every
// method: signed out, ?id=abc is refused as an invalid parameter before the caller is judged.
// The cases come from the reference's index, so a route added later is held to it too.
[ , $index ] = minn_test_fetch( "$REF/?rest_route=/" );
$same  = 0;
$cases = 0;
foreach ( ( json_decode( (string) $index, true )['routes'] ?? array() ) as $route => $info ) {
	if ( ! preg_match_all( '/\(\?P<(\w+)>/', $route, $names ) ) {
		continue;
	}
	$path = preg_replace( '/\(\?P<\w+>(?:[^()]|\((?:[^()]|\([^()]*\))*\))*\)/', '1', $route );
	foreach ( $info['endpoints'] as $endpoint ) {
		foreach ( $names[1] as $name ) {
			if ( 'integer' !== ( $endpoint['args'][ $name ]['type'] ?? null ) ) {
				continue;
			}
			foreach ( $endpoint['methods'] as $method ) {
				$answers = array();
				foreach ( array( $REF, $ENGINE ) as $base ) {
					$curl = curl_init( "$base/index.php?rest_route=" . rawurlencode( $path ) . "&$name=abc" );
					curl_setopt_array( $curl, array( CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_TIMEOUT => 20 ) );
					$body      = json_decode( (string) curl_exec( $curl ), true );
					$answers[] = curl_getinfo( $curl, CURLINFO_HTTP_CODE ) . ' ' . ( $body['code'] ?? '' );
				}
				$cases++;
				if ( $answers[0] === $answers[1] ) {
					$same++;
				} else {
					$fail++;
					echo "  FAIL path argument: $method $path?$name=abc ({$answers[0]} vs {$answers[1]})\n";
				}
			}
		}
	}
}
if ( $cases > 0 && $same === $cases ) {
	$pass++;
	echo "  ok   path argument: $cases requests naming a path's integer capture in the query answer alike\n";
}

$wp = static fn ( string $command ): string => trim( (string) shell_exec( 'cd ' . escapeshellarg( minn_test_site_root() . '/wp-reference' ) . " && wp $command 2>/dev/null" ) );
// The menus' edit context is judged before the menus are: signed out it is the context that is
// refused, and an author or an editor without edit_theme_options is refused it on a menu or an item.
$menu = (int) $wp( 'menu create zz-parity-context --porcelain' );
$item = (int) $wp( "menu item add-custom $menu Zz https://example.com --porcelain" );
foreach ( array( 0 => 'signed out', 3 => 'an author', 2 => 'an editor' ) as $uid => $who ) {
	$session = $uid > 0 ? json_decode( $wp( 'eval-file ' . escapeshellarg( __DIR__ . '/tools/mint-session.php' ) . " $uid" ), true ) : null;
	foreach ( array( '/wp/v2/menus', "/wp/v2/menus/$menu", '/wp/v2/menu-items', "/wp/v2/menu-items/$item" ) as $route ) {
		$answers = array();
		foreach ( array( $REF, $ENGINE ) as $base ) {
			$curl = curl_init( "$base/index.php?rest_route=" . rawurlencode( $route ) . '&context=edit' );
			curl_setopt_array( $curl, array( CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_HTTPHEADER => null === $session ? array() : array( 'X-WP-Nonce: ' . $session['nonce'], 'Cookie: ' . $session['cookie_name'] . '=' . $session['cookie'] ) ) );
			$body      = json_decode( (string) curl_exec( $curl ), true );
			$answers[] = curl_getinfo( $curl, CURLINFO_HTTP_CODE ) . ' ' . ( $body['code'] ?? 'ok' );
		}
		$ok = $answers[0] === $answers[1];
		$ok ? $pass++ : $fail++;
		echo ( $ok ? '  ok   ' : '  FAIL ' ) . 'menus edit context, ' . $who . ': ' . preg_replace( '/\d+$/', '{id}', $route ) . ( $ok ? " ({$answers[0]})" : " ({$answers[0]} vs {$answers[1]})" ) . "\n";
	}
	if ( null !== $session ) {
		$wp( 'eval ' . escapeshellarg( 'WP_Session_Tokens::get_instance(' . $uid . ')->destroy(' . var_export( (string) $session['token'], true ) . ');' ) );
	}
}
$wp( "menu delete $menu" );

// Menu items listed with a status the caller may not ask for: the status is refused before the caller is judged.
foreach ( array( 0 => 'signed out', 3 => 'an author', 1 => 'an administrator' ) as $uid => $who ) {
	$session = $uid > 0 ? json_decode( $wp( 'eval-file ' . escapeshellarg( __DIR__ . '/tools/mint-session.php' ) . " $uid" ), true ) : null;
	foreach ( array( 'status=draft', 'status=zz', 'status=draft&context=edit', 'status=publish' ) as $query ) {
		$answers = array();
		foreach ( array( $REF, $ENGINE ) as $base ) {
			$curl = curl_init( "$base/index.php?rest_route=" . rawurlencode( '/wp/v2/menu-items' ) . "&$query" );
			curl_setopt_array( $curl, array( CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_HTTPHEADER => null === $session ? array() : array( 'X-WP-Nonce: ' . $session['nonce'], 'Cookie: ' . $session['cookie_name'] . '=' . $session['cookie'] ) ) );
			$body      = json_decode( (string) curl_exec( $curl ), true );
			$answers[] = curl_getinfo( $curl, CURLINFO_HTTP_CODE ) . ' ' . ( $body['code'] ?? 'list' ) . ' ' . json_encode( $body['data']['details'] ?? null );
		}
		$ok = $answers[0] === $answers[1];
		$ok ? $pass++ : $fail++;
		echo ( $ok ? '  ok   ' : '  FAIL ' ) . "menu items $query, $who" . ( $ok ? '' : " ({$answers[0]} vs {$answers[1]})" ) . "\n";
	}
	if ( null !== $session ) {
		$wp( 'eval ' . escapeshellarg( 'WP_Session_Tokens::get_instance(' . $uid . ')->destroy(' . var_export( (string) $session['token'], true ) . ');' ) );
	}
}

// A write's body or query naming another id writes that one, judged as that one: an
// administrator's lands on the post named, an author's aimed at someone else's is refused.
foreach ( array( 1 => 'an administrator', 3 => 'an author' ) as $uid => $who ) {
	$session = json_decode( $wp( 'eval-file ' . escapeshellarg( __DIR__ . '/tools/mint-session.php' ) . " $uid" ), true );
	$seen    = array();
	foreach ( array( $REF, $ENGINE ) as $base ) {
		$a = (int) $wp( 'post create --post_title=redirect-a --post_status=draft --post_author=3 --porcelain' );
		$b = (int) $wp( 'post create --post_title=redirect-b --post_status=draft --post_author=1 --porcelain' );
		$curl = curl_init( "$base/index.php?rest_route=" . rawurlencode( "/wp/v2/posts/$a" ) );
		curl_setopt_array( $curl, array( CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_POSTFIELDS => json_encode( array( 'id' => $b, 'title' => 'redirected' ) ), CURLOPT_HTTPHEADER => array( 'Content-Type: application/json', 'X-WP-Nonce: ' . ( $session['nonce'] ?? '' ), 'Cookie: ' . ( $session['cookie_name'] ?? '' ) . '=' . ( $session['cookie'] ?? '' ) ) ) );
		$body   = json_decode( (string) curl_exec( $curl ), true );
		$seen[] = array( curl_getinfo( $curl, CURLINFO_HTTP_CODE ), $body['code'] ?? ( ( $body['id'] ?? 0 ) === $b ? 'B' : 'A' ), $wp( "post get $a --field=post_title" ), $wp( "post get $b --field=post_title" ) );
		$wp( "post delete $a $b --force" );
	}
	$wp( 'eval ' . escapeshellarg( 'WP_Session_Tokens::get_instance(' . $uid . ')->destroy(' . var_export( (string) ( $session['token'] ?? '' ), true ) . ');' ) );
	$ok = $seen[0] === $seen[1];
	$ok ? $pass++ : $fail++;
	echo ( $ok ? '  ok   ' : '  FAIL ' ) . "path argument: $who's write naming another post in its body" . ( $ok ? '' : ' (' . json_encode( $seen ) . ')' ) . "\n";
}

// The embed context's nested fields: templates keep their view fields but the dates, with an
// empty content; a navigation post keeps its raw title and content and the block version, a
// pattern's raw-only title shows empty, also when _fields leaves out the type. An empty slug
// looks no template up.
$nav   = (int) $wp( 'post create --post_type=wp_navigation --post_status=publish --post_title=embed-nav --post_content=' . escapeshellarg( '<!-- wp:page-list /-->' ) . ' --porcelain' );
$block = (int) $wp( 'post create --post_type=wp_block --post_status=publish --post_title=embed-pattern --post_content=' . escapeshellarg( '<!-- wp:paragraph --><p>zz</p><!-- /wp:paragraph -->' ) . ' --porcelain' );
if ( $nav > 0 && $block > 0 ) {
	$routes = array( '/wp/v2/templates?context=embed', '/wp/v2/template-parts?context=embed', '/wp/v2/templates?context=embed&_fields=content,id', '/wp/v2/templates/lookup?slug=', '/wp/v2/template-parts/lookup?slug=0', "/wp/v2/navigation/$nav?context=embed", "/wp/v2/navigation?context=embed&include=$nav&_fields=title,content", "/wp/v2/blocks/$block?context=embed", "/wp/v2/blocks?context=embed&include=$block&_fields=title,wp_pattern_sync_status" );
	foreach ( array( 0 => 'signed out', 3 => 'an author' ) as $uid => $who ) {
		$session = $uid > 0 ? json_decode( $wp( 'eval-file ' . escapeshellarg( __DIR__ . '/tools/mint-session.php' ) . " $uid" ), true ) : null;
		foreach ( $routes as $route ) {
			[ $path, $query ] = explode( '?', $route, 2 );
			$answers          = array();
			foreach ( array( $REF, $ENGINE ) as $base ) {
				$curl = curl_init( "$base/index.php?rest_route=" . rawurlencode( $path ) . "&$query" );
				curl_setopt_array( $curl, array( CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_HTTPHEADER => null === $session ? array() : array( 'X-WP-Nonce: ' . $session['nonce'], 'Cookie: ' . $session['cookie_name'] . '=' . $session['cookie'] ) ) );
				$answers[] = curl_getinfo( $curl, CURLINFO_HTTP_CODE ) . ' ' . (string) curl_exec( $curl );
			}
			$ok = $answers[0] === $answers[1];
			$ok ? $pass++ : $fail++;
			echo ( $ok ? '  ok   ' : '  FAIL ' ) . "embed context, $who: " . str_replace( array( (string) $nav, (string) $block ), '{id}', $route ) . ( $ok ? '' : "\n      " . substr( $answers[0], 0, 240 ) . "\n      " . substr( $answers[1], 0, 240 ) ) . "\n";
		}
		if ( null !== $session ) {
			$wp( 'eval ' . escapeshellarg( 'WP_Session_Tokens::get_instance(' . $uid . ')->destroy(' . var_export( (string) $session['token'], true ) . ');' ) );
		}
	}
}
foreach ( array( $nav, $block ) as $id ) {
	if ( $id > 0 ) {
		$wp( "post delete $id --force" );
	}
}

// A write reads its arguments from the body first and the query after: an author's post made
// from the query alone, a JSON or form body winning over the query, the query filling in what
// the body leaves out; a password or locale in the query is judged as one in the body.
$session = json_decode( $wp( 'eval-file ' . escapeshellarg( __DIR__ . '/tools/mint-session.php' ) . ' 3' ), true );
$writes  = array(
	'query only'          => array( '/wp/v2/posts', '?title=Q&status=draft', null, null ),
	'JSON over the query' => array( '/wp/v2/posts', '?title=Q', '{"title":"B","status":"draft"}', 'application/json' ),
	'form over the query' => array( '/wp/v2/posts', '?title=Q&status=draft', 'title=F', 'application/x-www-form-urlencoded' ),
	'the query filling in' => array( '/wp/v2/posts', '?excerpt=QE', '{"title":"B","status":"draft"}', 'application/json' ),
	'an empty JSON body'  => array( '/wp/v2/posts', '?title=Q&status=draft', '', 'application/json' ),
	'a password'          => array( '/wp/v2/users/3', '?password=' . rawurlencode( 'a\\b' ), null, null ),
	'a locale'            => array( '/wp/v2/users/3', '?locale=zz', null, null ),
);
foreach ( $writes as $label => [ $path, $query, $body, $type ] ) {
	$answers = array();
	foreach ( array( $REF, $ENGINE ) as $base ) {
		$curl    = curl_init( "$base/wp-json$path$query" );
		$headers = array( 'X-WP-Nonce: ' . ( $session['nonce'] ?? '' ), 'Cookie: ' . ( $session['cookie_name'] ?? '' ) . '=' . ( $session['cookie'] ?? '' ) );
		if ( null !== $type ) {
			$headers[] = "Content-Type: $type";
		}
		curl_setopt_array( $curl, array( CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body ?? '', CURLOPT_HTTPHEADER => $headers ) );
		$answer    = json_decode( (string) curl_exec( $curl ), true );
		$answers[] = curl_getinfo( $curl, CURLINFO_HTTP_CODE ) . ' ' . ( $answer['code'] ?? '' ) . ' ' . json_encode( $answer['data']['params'] ?? array( $answer['title']['raw'] ?? null, $answer['status'] ?? null, $answer['excerpt']['raw'] ?? null ) );
		if ( (int) ( $answer['id'] ?? 0 ) > 0 && 'post' === ( $answer['type'] ?? '' ) && 3 === (int) ( $answer['author'] ?? 0 ) ) {
			$wp( 'post delete ' . (int) $answer['id'] . ' --force' );
		}
	}
	$ok = $answers[0] === $answers[1];
	$ok ? $pass++ : $fail++;
	echo ( $ok ? '  ok   ' : '  FAIL ' ) . "a write's arguments from the query: $label" . ( $ok ? " ({$answers[0]})" : " ({$answers[0]} vs {$answers[1]})" ) . "\n";
}
$wp( 'eval ' . escapeshellarg( 'WP_Session_Tokens::get_instance(3)->destroy(' . var_export( (string) ( $session['token'] ?? '' ), true ) . ');' ) );

// A saved template's autosaves: each author keeps one, holding only what the body sends (what
// it leaves out is empty, then kept from the author's last autosave); the author's own draft is
// saved in place and published; a theme file's or an unknown template is refused. A draft
// template is found by its id, and trashing one suffixes its slug as any post's.
$template = (int) $wp( 'post create --post_type=wp_template --post_name=zz-parity-tpl --post_title=Parity --post_status=publish --post_author=1 --post_content=' . escapeshellarg( '<p>orig</p>' ) . ' --porcelain' );
if ( $template > 0 ) {
	$wp( "post term set $template wp_theme twentytwentyfive" );
	$session = json_decode( $wp( 'eval-file ' . escapeshellarg( __DIR__ . '/tools/mint-session.php' ) . ' 1' ), true );
	$send    = function ( string $base, string $method, string $path, string $body = '' ) use ( $session ): string {
		$curl = curl_init( "$base/wp-json$path" );
		curl_setopt_array( $curl, array( CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_POSTFIELDS => $body, CURLOPT_HTTPHEADER => array( 'Content-Type: application/json', 'X-WP-Nonce: ' . $session['nonce'], 'Cookie: ' . $session['cookie_name'] . '=' . $session['cookie'] ) ) );
		return curl_getinfo( $curl, CURLINFO_HTTP_CODE ) . ' ' . preg_replace( array( '#"(date|modified)":"[^"]*"#', '#\d{6,}#' ), array( '"$1":"X"', 'N' ), (string) curl_exec( $curl ) );
	};
	$state = function () use ( $wp, $template ): string {
		return $wp( "post get $template --fields=post_name,post_title,post_content,post_status --format=csv" ) . ' ' . preg_replace( '#\d{6,}#', 'N', $wp( "post list --post_type=revision --post_parent=$template --post_status=any --orderby=ID --order=asc --fields=post_name,post_title,post_content,post_excerpt --format=csv" ) );
	};
	$reset = function () use ( $wp, $template ): void {
		$wp( 'db query ' . escapeshellarg( 'UPDATE ' . $wp( 'db prefix' ) . "posts SET post_status = 'publish', post_name = 'zz-parity-tpl', post_title = 'Parity', post_content = '<p>orig</p>' WHERE ID = $template" ) );
		foreach ( array( '_wp_trash_meta_status', '_wp_trash_meta_time', '_wp_desired_post_slug' ) as $key ) {
			$wp( "post meta delete $template $key" );
		}
		$revisions = $wp( "post list --post_type=revision --post_parent=$template --post_status=any --format=ids" );
		if ( '' !== $revisions ) {
			$wp( "post delete $revisions --force" );
		}
	};
	$own      = '/wp/v2/templates/twentytwentyfive//zz-parity-tpl';
	$cases    = array(
		'an empty body'               => array( 'publish', array( array( 'POST', "$own/autosaves", '{}' ) ) ),
		'a description and objects'   => array( 'publish', array( array( 'POST', "$own/autosaves", '{"description":"d","title":{"raw":"T"},"content":{"raw":"<p>c</p>"}}' ) ) ),
		'a second save'               => array( 'publish', array( array( 'POST', "$own/autosaves", '{"title":"A","content":"<p>a</p>"}' ), array( 'POST', "$own/autosaves", '{"content":"<p>b</p>"}' ), array( 'GET', "$own/autosaves" ) ) ),
		'the author\'s own draft'     => array( 'draft', array( array( 'GET', $own ), array( 'POST', "$own/autosaves", '{"content":"<p>d</p>"}' ) ) ),
		'a theme file and a stranger' => array( 'publish', array( array( 'POST', '/wp/v2/templates/twentytwentyfive//single/autosaves', '{}' ), array( 'POST', '/wp/v2/template-parts/twentytwentyfive//header/autosaves', '{}' ), array( 'POST', '/wp/v2/templates/twentytwentyfive//zz-parity-none/autosaves', '{}' ) ) ),
		'trashing'                    => array( 'publish', array( array( 'DELETE', $own ), array( 'GET', $own ), array( 'DELETE', $own ) ) ),
	);
	foreach ( $cases as $label => [ $status, $requests ] ) {
		$seen = array();
		foreach ( array( $REF, $ENGINE ) as $base ) {
			$reset();
			if ( 'publish' !== $status ) {
				$wp( 'db query ' . escapeshellarg( 'UPDATE ' . $wp( 'db prefix' ) . "posts SET post_status = '$status' WHERE ID = $template" ) );
			}
			$replies = array();
			foreach ( $requests as $request ) {
				$replies[] = $send( $base, $request[0], $request[1], $request[2] ?? '' );
			}
			$seen[] = implode( "\n", $replies ) . "\n" . $state();
		}
		$ok = $seen[0] === $seen[1];
		$ok ? $pass++ : $fail++;
		echo ( $ok ? '  ok   ' : '  FAIL ' ) . "template autosaves and lookups: $label" . ( $ok ? '' : "\n      " . substr( $seen[0], 0, 400 ) . "\n      " . substr( $seen[1], 0, 400 ) ) . "\n";
	}
	// A reader who may not change templates still sees a saved copy's revision links.
	$reset();
	$wp( "post update $template --post_content=" . escapeshellarg( '<p>revised</p>' ) );
	$reader  = json_decode( $wp( 'eval-file ' . escapeshellarg( __DIR__ . '/tools/mint-session.php' ) . ' 3' ), true );
	$answers = array();
	foreach ( array( $REF, $ENGINE ) as $base ) {
		$curl = curl_init( "$base/wp-json$own" );
		curl_setopt_array( $curl, array( CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_HTTPHEADER => array( 'X-WP-Nonce: ' . $reader['nonce'], 'Cookie: ' . $reader['cookie_name'] . '=' . $reader['cookie'] ) ) );
		$answers[] = json_encode( json_decode( (string) curl_exec( $curl ), true )['_links'] ?? null );
	}
	$ok = $answers[0] === $answers[1] && str_contains( $answers[0], 'predecessor-version' );
	$ok ? $pass++ : $fail++;
	echo ( $ok ? '  ok   ' : '  FAIL ' ) . "template autosaves and lookups: an author's links to a revised template" . ( $ok ? '' : " ({$answers[0]} vs {$answers[1]})" ) . "\n";
	$wp( 'eval ' . escapeshellarg( 'WP_Session_Tokens::get_instance(3)->destroy(' . var_export( (string) $reader['token'], true ) . ');' ) );
	$reset();
	$wp( "post delete $template --force" );
	$wp( 'eval ' . escapeshellarg( 'WP_Session_Tokens::get_instance(1)->destroy(' . var_export( (string) $session['token'], true ) . ');' ) );
}

// The block and pattern directories (decision 1B): who may search them and the arguments
// refused before anything is asked of the directory; a page of patterns the reference keeps
// in its site transient for that query (made up here, never the directory's own) read the
// same by both stacks, with _fields picking from each pattern. A cookie caller whose nonce
// held is handed a fresh one in X-WP-Nonce, refused, missing or not; without one, nothing.
$admin  = json_decode( $wp( 'eval-file ' . escapeshellarg( __DIR__ . '/tools/mint-session.php' ) . ' 1' ), true );
$author = json_decode( $wp( 'eval-file ' . escapeshellarg( __DIR__ . '/tools/mint-session.php' ) . ' 3' ), true );
$ask    = function ( string $base, string $path, ?array $session, bool $nonce = true ): array {
	$headers = $session ? array( 'Cookie: ' . $session['cookie_name'] . '=' . $session['cookie'] ) : array();
	if ( $session && $nonce ) {
		$headers[] = 'X-WP-Nonce: ' . $session['nonce'];
	}
	$curl = curl_init( "$base$path" );
	curl_setopt_array( $curl, array( CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_TIMEOUT => 60, CURLOPT_HTTPHEADER => $headers ) );
	$raw  = (string) curl_exec( $curl );
	$size = curl_getinfo( $curl, CURLINFO_HEADER_SIZE );
	preg_match( '/^x-wp-nonce:\s*(\S+)/mi', substr( $raw, 0, $size ), $fresh );
	return array( curl_getinfo( $curl, CURLINFO_HTTP_CODE ), json_decode( substr( $raw, $size ), true ), $fresh[1] ?? '' );
};
$version = $wp( 'eval ' . escapeshellarg( 'echo get_bloginfo("version");' ) );
$query   = array( 'page' => 1, 'per_page' => 2, 'order' => 'desc', 'orderby' => 'date', 'search' => 'zzparity', 'locale' => 'en_US', 'wp-version' => $version );
$key     = 'wp_remote_block_patterns_' . md5( serialize( $query ) );
$made_up = '[{"id":901,"title":{"rendered":"Zz &amp; Parity"},"pattern_content":"<!-- wp:paragraph --><p>zz</p><!-- /wp:paragraph -->","category_slugs":["text","featured"],"meta":{"wpop_keywords":"zz, parity ,x","wpop_description":"Made up.","wpop_viewport_width":"1200","wpop_block_types":["core/paragraph"]}},{"id":"902","title":{"rendered":"Bare"},"pattern_content":"","category_slugs":[],"meta":{"wpop_keywords":"","wpop_description":"","wpop_viewport_width":0,"wpop_block_types":[]}}]';
$wp( 'eval ' . escapeshellarg( 'set_site_transient(' . var_export( $key, true ) . ', json_decode(' . var_export( $made_up, true ) . '), 600);' ) );
$cases = array(
	'block search signed out'              => array( '/wp-json/wp/v2/block-directory/search?term=x', null ),
	'block search by an author'            => array( '/wp-json/wp/v2/block-directory/search?term=x', $author ),
	'block search without a term'          => array( '/wp-json/wp/v2/block-directory/search', $admin ),
	'block search with an empty term'      => array( '/wp-json/wp/v2/block-directory/search?term=', $admin ),
	'block search per_page=0'              => array( '/wp-json/wp/v2/block-directory/search?term=x&per_page=0', $admin ),
	'block search in the edit context'     => array( '/wp-json/wp/v2/block-directory/search?term=x&context=edit', $admin ),
	'patterns signed out'                  => array( '/wp-json/wp/v2/pattern-directory/patterns', null ),
	'patterns per_page=0'                  => array( '/wp-json/wp/v2/pattern-directory/patterns?per_page=0', $admin ),
	'patterns order=zz'                    => array( '/wp-json/wp/v2/pattern-directory/patterns?order=zz', $admin ),
	'patterns with an empty search'        => array( '/wp-json/wp/v2/pattern-directory/patterns?search=', $admin ),
	'patterns category=0'                  => array( '/wp-json/wp/v2/pattern-directory/patterns?category=0', $admin ),
	'kept patterns for an author'          => array( '/wp-json/wp/v2/pattern-directory/patterns?per_page=2&search=zzparity', $author ),
	'kept patterns, _fields=id,keywords'   => array( '/wp-json/wp/v2/pattern-directory/patterns?per_page=2&search=zzparity&_fields=id,keywords', $admin ),
	'nonce: a route answered'              => array( '/wp-json/wp/v2/types/post', $author ),
	'nonce: from ?_wpnonce'                => array( '/wp-json/wp/v2/types/post?_wpnonce=' . $author['nonce'], $author, false ),
	'nonce: a missing route'               => array( '/wp-json/wp/v2/zz-nothing', $author ),
	'nonce: a refused route'               => array( '/wp-json/wp/v2/settings', $author ),
	'nonce: by ?rest_route='               => array( '/?rest_route=/wp/v2/types/post', $author ),
	'nonce: a cookie without one'          => array( '/wp-json/wp/v2/types/post', $author, false ),
);
foreach ( $cases as $label => $case ) {
	$seen = array();
	foreach ( array( $REF, $ENGINE ) as $base ) {
		$seen[] = $ask( $base, ...$case );
	}
	$ok = $seen[0] === $seen[1] && ( ! str_starts_with( $label, 'kept' ) || 2 === count( (array) $seen[0][1] ) );
	$ok ? $pass++ : $fail++;
	echo ( $ok ? '  ok   ' : '  FAIL ' ) . "directories and nonces: $label" . ( $ok ? '' : "\n      " . substr( json_encode( $seen[0] ), 0, 400 ) . "\n      " . substr( json_encode( $seen[1] ), 0, 400 ) ) . "\n";
}
// The root index tells someone who may upload how the browser processes images, and someone who
// may edit posts where the active theme is (its routes and namespaces aside).
foreach ( array( 'signed out' => null, 'an author' => $author, 'an administrator' => $admin ) as $label => $session ) {
	$seen = array();
	foreach ( array( $REF, $ENGINE ) as $base ) {
		$answer = $ask( $base, '/wp-json/', $session );
		unset( $answer[1]['routes'], $answer[1]['namespaces'] );
		$seen[] = $answer;
	}
	$ok = $seen[0] === $seen[1] && ( null === $session || isset( $seen[0][1]['image_sizes'], $seen[0][1]['_links']['wp:active-theme'] ) );
	$ok ? $pass++ : $fail++;
	echo ( $ok ? '  ok   ' : '  FAIL ' ) . "directories and nonces: the root index for $label" . ( $ok ? '' : "\n      " . substr( json_encode( $seen[0] ), 0, 400 ) . "\n      " . substr( json_encode( $seen[1] ), 0, 400 ) ) . "\n";
}
// OPTIONS on each route both indexes list tells a client its item schema, as the reference's controller
// describes it (asked signed out, on the route with each capture filled in). Set aside: any schema still
// told differently (none since 2026-10-22); the list only shrinks.
$schema_aside = array();
[ , $engine_index ] = minn_test_fetch( "$ENGINE/?rest_route=/" );
$shared  = array_intersect_key( json_decode( (string) $index, true )['routes'] ?? array(), json_decode( (string) $engine_index, true )['routes'] ?? array() );
$schemas = 0;
$told    = 0;
foreach ( array_keys( $shared ) as $route ) {
	$path    = preg_replace( '/\(\?P<\w+>(?:[^()]|\((?:[^()]|\([^()]*\))*\))*\)/', '1', $route );
	$answers = array();
	foreach ( array( $REF, $ENGINE ) as $base ) {
		$curl = curl_init( "$base/index.php?rest_route=" . rawurlencode( $path ) );
		curl_setopt_array( $curl, array( CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_CUSTOMREQUEST => 'OPTIONS', CURLOPT_TIMEOUT => 20 ) );
		$answers[] = json_decode( (string) curl_exec( $curl ), true )['schema'] ?? null;
	}
	$aside = in_array( $route, $schema_aside, true );
	if ( null === $answers[0] && null === $answers[1] ) {
		continue;
	}
	$schemas++;
	$same = $answers[0] == $answers[1];
	if ( $same && $aside ) {
		$fail++;
		echo "  FAIL OPTIONS schema: $route is told as the reference tells it now: take it off the list\n";
	} elseif ( ! $same && ! $aside ) {
		$fail++;
		echo "  FAIL OPTIONS schema: $route " . substr( (string) minn_test_diff( $answers[0], $answers[1] ), 0, 300 ) . "\n";
	} elseif ( $same ) {
		$told++;
	}
}
$ok = $schemas > 80 && $told === $schemas - count( $schema_aside );
$ok ? $pass++ : $fail++;
echo ( $ok ? '  ok   ' : '  FAIL ' ) . "OPTIONS schema: $told of $schemas routes' schemas told as the reference tells them, " . count( $schema_aside ) . " set aside\n";
$wp( 'eval ' . escapeshellarg( 'delete_site_transient(' . var_export( $key, true ) . ');' ) );
foreach ( array( 1 => $admin, 3 => $author ) as $id => $session ) {
	$wp( 'eval ' . escapeshellarg( "WP_Session_Tokens::get_instance($id)->destroy(" . var_export( (string) $session['token'], true ) . ');' ) );
}

echo "\n$pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
