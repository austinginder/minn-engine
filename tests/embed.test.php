<?php
/**
 * The _embed decoration and the embed context: author, featured media,
 * terms, replies, parents, in lists and singles, with and without _fields,
 * as the reference answers them (contracts/rest/embed.md).
 *
 * Ref: (its Cove twin: cove twin minn add --as-site=ref.minn.localhost) — SKIPs cleanly when down.
 */

$ENGINE = 'https://minn.localhost';
$REF    = 'https://ref.minn.localhost';
$ROOT   = dirname( __DIR__ );

require_once __DIR__ . '/lib.php';

[ $ph ] = minn_test_fetch( "$REF/?rest_route=/wp/v2/posts", 3 );
if ( 200 !== $ph['status'] ) {
	echo "SKIP: reference WordPress not running at $REF (its Cove twin: cove twin minn add --as-site=ref.minn.localhost)\n";
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

function em_wp( string $args ): string {
	global $ROOT;
	return trim( (string) shell_exec( 'wp --path=' . escapeshellarg( minn_test_site_root() . '/wp-reference' ) . " $args 2>/dev/null" ) );
}

function em_mint( int $uid ): array {
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

function em_fetch( string $base, string $path, ?array $mint ): array {
	global $REF;
	$headers = array();
	if ( $mint ) {
		$alt       = 'wordpress_logged_in_' . md5( $REF );
		$headers[] = 'Cookie: ' . $mint['cookie_name'] . '=' . $mint['cookie'] . '; ' . $alt . '=' . $mint['cookie'];
		$headers[] = 'X-WP-Nonce: ' . $mint['nonce'];
	}
	$ctx = stream_context_create(
		array(
			'ssl'  => array( 'verify_peer' => false, 'verify_peer_name' => false ),
			'http' => array( 'ignore_errors' => true, 'timeout' => 15, 'header' => implode( "\r\n", $headers ) ),
		)
	);
	$raw    = @file_get_contents( "$base/wp-json/wp/v2/$path", false, $ctx );
	$status = 0;
	foreach ( $http_response_header ?? array() as $h ) {
		if ( preg_match( '#^HTTP/\S+\s+(\d+)#', $h, $m ) ) {
			$status = (int) $m[1];
		}
	}
	return array( $status, json_decode( (string) $raw, true ) );
}

/** Host normalised; stored-host fields (guid, user url, source_url) carry the engine's host on both stacks. */
function em_norm( $x ) {
	global $ENGINE, $REF;
	if ( is_array( $x ) ) {
		return array_map( 'em_norm', $x );
	}
	if ( is_string( $x ) ) {
		return str_replace( array( str_replace( '/', '\\/', $REF ), $REF ), $ENGINE, $x );
	}
	return $x;
}

function em_parity( string $label, string $path, ?array $mint = null ): void {
	global $ENGINE, $REF;
	[ $rs, $rb ] = em_fetch( $REF, $path, $mint );
	[ $es, $eb ] = em_fetch( $ENGINE, $path, $mint );
	if ( $rs !== $es ) {
		check( false, $label, "status $rs vs $es" );
		return;
	}
	$d = minn_test_diff( em_norm( $rb ), em_norm( $eb ) );
	check( null === $d, $label, (string) $d );
}

/** Only the suite's own post (found by exact title, never a filtered list) and its tag. */
function em_cleanup(): void {
	foreach ( explode( "\n", em_wp( 'db query "SELECT ID FROM wp_posts WHERE post_title = \'zz-embed-suite\' AND post_type = \'post\'" --skip-column-names' ) ) as $id ) {
		if ( ctype_digit( trim( $id ) ) && (int) $id > 29 ) {
			em_wp( 'post delete ' . (int) $id . ' --force' );
		}
	}
	em_wp( 'term delete post_tag zz-embed-tag --by=slug' );
}
register_shutdown_function( 'em_cleanup' );
em_cleanup();

echo "embed suite: $ENGINE (engine) / $REF (oracle)\n";

$admin  = em_mint( 1 );
$author = em_mint( 3 );

// A published post with everything embeddable: an author, a featured image,
// a category, a tag, and an approved comment.
$image = (int) ( json_decode( (string) file_get_contents( "$ROOT/contracts/fixtures/blocks/manifest.json" ), true )['image'] ?? 0 );
$post  = (int) em_wp( 'post create --post_type=post --post_status=publish --post_author=2 --post_title=zz-embed-suite --post_content="<!-- wp:paragraph --><p>Embed me.</p><!-- /wp:paragraph -->" --tags_input=zz-embed-tag --porcelain' );
if ( $post <= 0 || $image <= 0 ) {
	echo "SKIP: could not create the scratch post (post $post, image $image)\n";
	exit( 0 );
}
em_wp( "post meta set $post _thumbnail_id $image" );
em_wp( "comment create --comment_post_ID=$post --comment_content='Embedded reply' --comment_author='Embed Reader' --comment_author_email=embed@example.com --comment_approved=1" );

// 1. Every embeddable rel on one object.
em_parity( 'single: every rel', "posts/$post?_embed" );
em_parity( 'single: chosen rels under _fields keep _links', "posts/$post?_embed=author,wp:featuredmedia&_fields=id,_embedded" );
em_parity( 'single: _fields without _embedded embeds nothing', "posts/$post?_embed&_fields=id" );
em_parity( 'single: _embed=1', "posts/$post?_embed=1&_fields=_embedded" );
em_parity( 'single: unknown rel is ignored', "posts/$post?_embed=wp:term,bogus&_fields=_embedded" );
em_parity( 'single: empty tags list stays beside categories', 'posts/9?_embed&_fields=id,_embedded' );
em_parity( 'single: empty replies rel is dropped', 'posts/9?_embed=replies&_fields=id,_embedded' );
em_parity( 'single: authorless post has nothing to embed', 'posts/611?_embed=author,wp:featuredmedia&_fields=id,_links,_embedded' );
em_parity( 'page: parent under up', 'pages/6?_embed&_fields=id,_embedded' );
em_parity( 'comment: its post under up', 'comments?_embed&per_page=2&_fields=id,_embedded' );

// 2. Lists filter first and embed from what survives.
em_parity( 'list: _fields with _links embeds', 'posts?_embed&per_page=5&_fields=id,_links,_embedded' );
em_parity( 'list: _fields without _links embeds nothing', 'posts?_embed&per_page=5&_fields=id,_embedded' );
em_parity( 'list: pages with parents', 'pages?_embed&per_page=5&_fields=id,_embedded,_links' );
em_parity( 'list: whole objects', "posts?_embed&include=$post" );

// 3. The embed context over HTTP.
em_parity( 'context=embed: post', "posts/$post?context=embed" );
em_parity( 'context=embed: page', 'pages/2?context=embed' );
em_parity( 'context=embed: user', 'users/2?context=embed' );
em_parity( 'context=embed: category', 'categories/1?context=embed' );
em_parity( 'context=embed: media', "media/$image?context=embed" );
em_parity( 'context=embed: comment', 'comments/1?context=embed' );
em_parity( 'context=embed: posts list', 'posts?context=embed&per_page=3' );
em_parity( 'context=embed: with _fields', "posts/$post?context=embed&_fields=id,title,status" );

// 4. The app's own Content list: edit context, author and featured media embedded.
$app = 'context=edit&status=publish,draft,pending,private,future&_embed=author,wp:featuredmedia&_fields=id,title,status,author,featured_media,_links,_embedded&per_page=5';
em_parity( 'app: posts list as admin', "posts?$app", $admin );
// Pages 2 and 3 share a post_date; under a LIMIT smaller than the row count the
// reference's own tie order flips between pages, so this list asks for a full page.
em_parity( 'app: pages list as admin', 'pages?' . str_replace( 'per_page=5', 'per_page=10', $app ), $admin );
em_parity( 'app: posts list as author', "posts?$app", $author );

// 5. The list parameters the app sends, and the post filters the embeds depend on.
em_parity( 'list: include', "posts?include=$post,9&_fields=id" );
em_parity( 'list: include in include order', "posts?include=9,$post&orderby=include&_fields=id" );
em_parity( 'list: exclude', "posts?exclude=$post,1&per_page=20&_fields=id" );
em_parity( 'list: slug', 'posts?slug=hello-world,zz-embed-suite&_fields=id' );
em_parity( 'list: search two words', 'posts?search=embed%20me&orderby=date&_fields=id' );
em_parity( 'list: search, titles ascending', 'posts?search=building&orderby=title&order=asc&_fields=id,title' );
em_parity( 'list: pages by parent', 'pages?parent=2&_fields=id' );
em_parity( 'list: pages by title, ascending', 'pages?orderby=title&order=asc&per_page=20&_fields=id,title' );
em_parity( 'list: posts by modified', 'posts?include=1,5,7&orderby=modified&_fields=id' );
em_parity( 'author: edit context lists own items only', 'posts?context=edit&status=publish&_fields=id', $author );
em_parity( 'author: view context with drafts', 'posts?status=publish,draft&_fields=id,status', $author );
em_parity( 'author: page statuses are forbidden', 'pages?context=edit&status=publish,draft&_fields=id', $author );
em_parity( 'anonymous: draft status is forbidden', 'pages?status=draft' );
em_parity( 'app: search in edit context', "posts?context=edit&status=publish,draft&search=scribe&orderby=date&order=desc&_fields=id,title", $admin );
em_parity( 'terms of a post', "tags?post=$post&_fields=id,name" );
em_parity( 'terms of an unknown post', 'categories?post=99999&_fields=id' );
em_parity( 'comments of a post', "comments?post=$post&_fields=id,content" );
em_parity( 'comments of an unknown post', 'comments?post=99999' );
em_parity( 'comments of a draft, anonymous', 'comments?post=3' );
em_parity( 'comments of a draft, admin', 'comments?post=3', $admin );

echo "\n$pass passed, $fail failed\n";
exit( $fail ? 1 : 0 );
