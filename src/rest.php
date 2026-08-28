<?php
/**
 * Minn Engine REST layer — wp/v2 compatibility surface.
 *
 * Implemented against captured fixtures in contracts/fixtures/rest/ (observed
 * behavior of a reference WordPress running on the same database), never
 * against WordPress source. Route names, response shapes, and header sets are
 * the Tier 1 contract; the implementation is original.
 *
 * Scope so far: GET /wp/v2/posts (list) and GET /wp/v2/posts/{id} (single),
 * public view context only. Known gaps are listed in contracts/rest/posts.md.
 */

/** Site origin from the options WordPress tooling writes. */
function minn_home_url( string $path = '' ): string {
	static $home = null;
	if ( null === $home ) {
		$home = rtrim( minn_option( 'home' ) ?? '', '/' );
	}
	return $home . $path;
}

/**
 * REST URL in the plain-permalink form the reference emits:
 * {home}/index.php?rest_route=/wp/v2/... — and, when query args ride along,
 * the rest_route value itself is URL-encoded.
 */
function minn_rest_url( string $route, array $args = array() ): string {
	if ( empty( $args ) ) {
		return minn_home_url( '/index.php?rest_route=' . $route );
	}
	$url = minn_home_url( '/index.php?rest_route=' . rawurlencode( $route ) );
	foreach ( $args as $k => $v ) {
		$url .= '&' . $k . '=' . rawurlencode( (string) $v );
	}
	return $url;
}

function minn_rest_headers(): void {
	header( 'Content-Type: application/json; charset=UTF-8' );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'Access-Control-Expose-Headers: X-WP-Total, X-WP-TotalPages, Link' );
	header( 'Access-Control-Allow-Headers: Authorization, X-WP-Nonce, Content-Disposition, Content-MD5, Content-Type' );
	header( 'Allow: GET' );
}

function minn_rest_send( $data, int $status = 200 ): void {
	minn_rest_headers();
	http_response_code( $status );
	echo json_encode( $data );
	exit;
}

function minn_rest_error( string $code, string $message, int $status ): void {
	minn_rest_send(
		array(
			'code'    => $code,
			'message' => $message,
			'data'    => array( 'status' => $status ),
		),
		$status
	);
}

/** Ints out of a serialized PHP array without ever calling unserialize(). */
function minn_serialized_int_list( ?string $blob ): array {
	if ( null === $blob || '' === $blob ) {
		return array();
	}
	if ( ! preg_match_all( '/i:\d+;i:(\d+);/', $blob, $m ) ) {
		return array();
	}
	return array_map( 'intval', $m[1] );
}

/**
 * Render a stored block-markup body the way the reference renders it for
 * content.rendered: block comment delimiters are removed (their surrounding
 * whitespace stays), and paragraph blocks gain the wp-block-paragraph class
 * on their tag.
 */
function minn_render_blocks( string $raw ): string {
	$out = preg_replace_callback(
		'/<!-- wp:paragraph( \{.*?\})? -->(.*?)<!-- \/wp:paragraph -->/s',
		function ( $m ) {
			$inner = $m[2];
			if ( preg_match( '/<p\s+[^>]*class="/', $inner ) ) {
				$inner = preg_replace( '/(<p\s+[^>]*class=")/', '$1wp-block-paragraph ', $inner, 1 );
			} else {
				$inner = preg_replace( '/<p(\s|>)/', '<p class="wp-block-paragraph"$1', $inner, 1 );
			}
			return $inner;
		},
		$raw
	);
	// Every other block: keep the inner markup, drop the delimiters.
	$out = preg_replace( '/<!-- \/?wp:[^>]*?-->/', '', $out );
	return $out;
}

/** The reference's generated excerpt: plain text, 55-word cap, <p>-wrapped. */
function minn_rendered_excerpt( array $post ): string {
	$source = '' !== $post['post_excerpt'] ? $post['post_excerpt'] : $post['post_content'];
	$text   = trim( strip_tags( preg_replace( '/<!-- \/?wp:[^>]*?-->/', '', $source ) ) );
	// Whitespace always collapses to single spaces in generated excerpts.
	$words = preg_split( '/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY );
	if ( count( $words ) > 55 ) {
		$text = implode( ' ', array_slice( $words, 0, 55 ) ) . ' [&hellip;]';
	} else {
		$text = implode( ' ', $words );
	}
	return '<p>' . $text . "</p>\n";
}

/** Terms attached to a post for one taxonomy: [ [term_id, slug], ... ]. */
function minn_post_terms( int $post_id, string $taxonomy ): array {
	global $table_prefix;
	$stmt = minn_db()->prepare(
		"SELECT t.term_id, t.slug FROM {$table_prefix}term_relationships tr
		 JOIN {$table_prefix}term_taxonomy tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
		 JOIN {$table_prefix}terms t ON t.term_id = tt.term_id
		 WHERE tr.object_id = ? AND tt.taxonomy = ?
		 ORDER BY t.name ASC"
	);
	$stmt->bind_param( 'is', $post_id, $taxonomy );
	$stmt->execute();
	return $stmt->get_result()->fetch_all( MYSQLI_NUM );
}

function minn_post_meta_value( int $post_id, string $key ): ?string {
	global $table_prefix;
	$stmt = minn_db()->prepare(
		"SELECT meta_value FROM {$table_prefix}postmeta WHERE post_id = ? AND meta_key = ? LIMIT 1"
	);
	$stmt->bind_param( 'is', $post_id, $key );
	$stmt->execute();
	$row = $stmt->get_result()->fetch_row();
	return $row ? $row[0] : null;
}

function minn_revision_count( int $post_id ): int {
	global $table_prefix;
	$stmt = minn_db()->prepare(
		"SELECT COUNT(*) FROM {$table_prefix}posts WHERE post_type = 'revision' AND post_parent = ?"
	);
	$stmt->bind_param( 'i', $post_id );
	$stmt->execute();
	return (int) $stmt->get_result()->fetch_row()[0];
}

function minn_rest_date( string $mysql ): string {
	return str_replace( ' ', 'T', $mysql );
}

/** Build one wp/v2 post object in the reference's view-context shape. */
function minn_rest_post_object( array $p ): array {
	$id         = (int) $p['ID'];
	$cats       = minn_post_terms( $id, 'category' );
	$tags       = minn_post_terms( $id, 'post_tag' );
	$format_t   = minn_post_terms( $id, 'post_format' );
	$format     = $format_t ? str_replace( 'post-format-', '', $format_t[0][1] ) : 'standard';
	$sticky_ids = minn_serialized_int_list( minn_option( 'sticky_posts' ) );

	$class_list = array( 'post-' . $id, $p['post_type'], 'type-' . $p['post_type'], 'status-' . $p['post_status'], 'format-' . $format, 'hentry' );
	foreach ( $cats as $c ) {
		$class_list[] = 'category-' . $c[1];
	}
	foreach ( $tags as $t ) {
		$class_list[] = 'tag-' . $t[1];
	}

	return array(
		'id'             => $id,
		'date'           => minn_rest_date( $p['post_date'] ),
		'date_gmt'       => minn_rest_date( $p['post_date_gmt'] ),
		'guid'           => array( 'rendered' => $p['guid'] ),
		'modified'       => minn_rest_date( $p['post_modified'] ),
		'modified_gmt'   => minn_rest_date( $p['post_modified_gmt'] ),
		'slug'           => $p['post_name'],
		'status'         => $p['post_status'],
		'type'           => $p['post_type'],
		'link'           => minn_home_url( '/?p=' . $id ),
		'title'          => array( 'rendered' => $p['post_title'] ),
		'content'        => array(
			'rendered'  => minn_render_blocks( $p['post_content'] ),
			'protected' => false,
		),
		'excerpt'        => array(
			'rendered'  => minn_rendered_excerpt( $p ),
			'protected' => false,
		),
		'author'         => (int) $p['post_author'],
		'featured_media' => (int) ( minn_post_meta_value( $id, '_thumbnail_id' ) ?? 0 ),
		'comment_status' => $p['comment_status'],
		'ping_status'    => $p['ping_status'],
		'sticky'         => in_array( $id, $sticky_ids, true ),
		'template'       => '',
		'format'         => $format,
		'meta'           => array( 'footnotes' => minn_post_meta_value( $id, 'footnotes' ) ?? '' ),
		'categories'     => array_map( static fn( $c ) => (int) $c[0], $cats ),
		'tags'           => array_map( static fn( $t ) => (int) $t[0], $tags ),
		'class_list'     => $class_list,
		'_links'         => minn_rest_post_links( $id, (int) $p['post_author'] ),
	);
}

function minn_rest_post_links( int $id, int $author ): array {
	$links = array(
		'self'            => array(
			array(
				'href'        => minn_rest_url( '/wp/v2/posts/' . $id ),
				'targetHints' => array( 'allow' => array( 'GET' ) ),
			),
		),
		'collection'      => array( array( 'href' => minn_rest_url( '/wp/v2/posts' ) ) ),
		'about'           => array( array( 'href' => minn_rest_url( '/wp/v2/types/post' ) ) ),
		// An authorless post (post_author 0) carries no author link at all.
		'author'          => $author > 0 ? array(
			array(
				'embeddable' => true,
				'href'       => minn_rest_url( '/wp/v2/users/' . $author ),
			),
		) : null,
		'replies'         => array(
			array(
				'embeddable' => true,
				'href'       => minn_rest_url( '/wp/v2/comments', array( 'post' => $id ) ),
			),
		),
		'version-history' => array(
			array(
				'count' => minn_revision_count( $id ),
				'href'  => minn_rest_url( '/wp/v2/posts/' . $id . '/revisions' ),
			),
		),
		'wp:attachment'   => array(
			array( 'href' => minn_rest_url( '/wp/v2/media', array( 'parent' => $id ) ) ),
		),
		'wp:term'         => array(
			array(
				'taxonomy'   => 'category',
				'embeddable' => true,
				'href'       => minn_rest_url( '/wp/v2/categories', array( 'post' => $id ) ),
			),
			array(
				'taxonomy'   => 'post_tag',
				'embeddable' => true,
				'href'       => minn_rest_url( '/wp/v2/tags', array( 'post' => $id ) ),
			),
		),
		'curies'          => array(
			array(
				'name'      => 'wp',
				'href'      => 'https://api.w.org/{rel}',
				'templated' => true,
			),
		),
	);
	return array_filter( $links, static fn( $v ) => null !== $v );
}

function minn_rest_posts_list(): void {
	global $table_prefix;
	$per_page = max( 1, min( 100, (int) ( $_GET['per_page'] ?? 10 ) ) );
	$page     = max( 1, (int) ( $_GET['page'] ?? 1 ) );
	$offset   = ( $page - 1 ) * $per_page;

	$total = (int) minn_db()->query(
		"SELECT COUNT(*) FROM {$table_prefix}posts WHERE post_type = 'post' AND post_status = 'publish'"
	)->fetch_row()[0];
	$total_pages = (int) ceil( $total / $per_page );

	if ( $page > 1 && $page > $total_pages ) {
		minn_rest_error( 'rest_post_invalid_page_number', 'The page number requested is larger than the number of pages available.', 400 );
	}

	$stmt = minn_db()->prepare(
		"SELECT * FROM {$table_prefix}posts WHERE post_type = 'post' AND post_status = 'publish'
		 ORDER BY post_date DESC LIMIT ?, ?"
	);
	$stmt->bind_param( 'ii', $offset, $per_page );
	$stmt->execute();
	$rows = $stmt->get_result()->fetch_all( MYSQLI_ASSOC );

	minn_rest_headers();
	header( 'X-WP-Total: ' . $total );
	header( 'X-WP-TotalPages: ' . $total_pages );
	echo json_encode( array_map( 'minn_rest_post_object', $rows ) );
	exit;
}

function minn_rest_posts_single( int $id ): void {
	global $table_prefix;
	$stmt = minn_db()->prepare(
		"SELECT * FROM {$table_prefix}posts WHERE ID = ? AND post_type = 'post' AND post_status = 'publish' LIMIT 1"
	);
	$stmt->bind_param( 'i', $id );
	$stmt->execute();
	$row = $stmt->get_result()->fetch_assoc();
	if ( ! $row ) {
		minn_rest_error( 'rest_post_invalid_id', 'Invalid post ID.', 404 );
	}
	minn_rest_send( minn_rest_post_object( $row ) );
}

function minn_rest_dispatch( string $route ): void {
	$route = '/' . trim( $route, '/' );

	if ( 'GET' !== ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) {
		minn_rest_error( 'rest_no_route', 'No route was found matching the URL and request method.', 404 );
	}
	if ( '/wp/v2/posts' === $route ) {
		minn_rest_posts_list();
	}
	if ( preg_match( '#^/wp/v2/posts/(\d+)$#', $route, $m ) ) {
		minn_rest_posts_single( (int) $m[1] );
	}
	minn_rest_error( 'rest_no_route', 'No route was found matching the URL and request method.', 404 );
}
