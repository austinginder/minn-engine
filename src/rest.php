<?php
/**
 * Minn Engine REST layer — wp/v2 compatibility surface.
 *
 * Implemented against captured fixtures in contracts/fixtures/rest/ (observed
 * behavior of a reference WordPress running on the same database), never
 * against WordPress source. Route names, response shapes, and header sets are
 * the Tier 1 contract; the implementation is original.
 *
 * Scope so far (view context, GET only): posts, pages, categories, tags,
 * types, users (with cookie + nonce authentication), and the _fields
 * response filter. Known gaps are listed in
 * contracts/rest/posts.md.
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

/* ---------------------------------------------------------------- _fields */

function minn_requested_fields(): ?array {
	$f = $_GET['_fields'] ?? null;
	if ( null === $f || '' === $f ) {
		return null;
	}
	if ( is_array( $f ) ) {
		$f = implode( ',', $f );
	}
	$paths = array_values( array_filter( array_map( 'trim', explode( ',', $f ) ), static fn( $s ) => '' !== $s ) );
	return $paths ?: null;
}

/**
 * Keep only the requested field paths, preserving the object's own key
 * order. Dot paths descend ("title.rendered", "_links.self"). Applied per
 * item on list responses and to the whole payload otherwise — which is
 * exactly why _fields on the associative types response strips every key
 * and yields [] over HTTP, a reference quirk the engine reproduces.
 */
function minn_fields_filter( array $obj, array $paths ): array {
	$out = array();
	foreach ( $obj as $k => $v ) {
		$keep_whole = false;
		$sub        = array();
		foreach ( $paths as $p ) {
			if ( $p === $k ) {
				$keep_whole = true;
				break;
			}
			if ( str_starts_with( $p, $k . '.' ) ) {
				$sub[] = substr( $p, strlen( $k ) + 1 );
			}
		}
		if ( $keep_whole ) {
			$out[ $k ] = $v;
		} elseif ( $sub && is_array( $v ) ) {
			$out[ $k ] = minn_fields_filter( $v, $sub );
		}
	}
	return $out;
}

/** @param bool $per_item true for list payloads (filtering applies per row). */
function minn_rest_send( $data, int $status = 200, bool $per_item = false ): void {
	$fields = minn_requested_fields();
	if ( null !== $fields && is_array( $data ) && $status < 400 ) {
		$data = $per_item
			? array_map( static fn( $row ) => minn_fields_filter( $row, $fields ), $data )
			: minn_fields_filter( $data, $fields );
	}
	minn_rest_headers();
	http_response_code( $status );
	echo json_encode( $data );
	exit;
}

function minn_rest_error( string $code, string $message, int $status ): void {
	minn_rest_headers();
	http_response_code( $status );
	echo json_encode(
		array(
			'code'    => $code,
			'message' => $message,
			'data'    => array( 'status' => $status ),
		)
	);
	exit;
}

/* ------------------------------------------------------------- utilities */

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
 * Texturize subset: straight quotes, apostrophes, ellipses, dashes, and
 * primes become the numeric entities the reference renders, applied only to
 * text outside the skip elements. Coverage is pinned by the texturize
 * battery post; anything beyond it is a documented gap.
 */
function minn_texturize( string $html ): string {
	static $skip = 'pre|code|kbd|style|script|tt|textarea';
	$parts = preg_split( '/(<[^>]*>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE );
	$depth = 0;
	foreach ( $parts as $i => $part ) {
		if ( '' === $part ) {
			continue;
		}
		if ( '<' === $part[0] ) {
			if ( preg_match( '#^</(?:' . $skip . ')\b#i', $part ) ) {
				$depth = max( 0, $depth - 1 );
			} elseif ( preg_match( '#^<(?:' . $skip . ')\b#i', $part ) && ! str_ends_with( $part, '/>' ) ) {
				$depth++;
			}
			continue;
		}
		if ( $depth > 0 ) {
			continue;
		}
		$parts[ $i ] = minn_texturize_run( $part );
	}
	return implode( '', $parts );
}

function minn_texturize_run( string $t ): string {
	$t = str_replace( '...', '&#8230;', $t );
	$t = str_replace( '---', '&#8212;', $t );
	$t = str_replace( ' -- ', ' &#8212; ', $t );
	$t = str_replace( '--', '&#8211;', $t );
	// Abbreviated years: '99
	$t = preg_replace( '/(^|[\s(\[{<])\'(?=\d\d)/', '$1&#8217;', $t );
	// Double prime after a digit (6'2" → 2&#8243;); a single quote after a
	// digit stays an apostrophe (&#8217;) per the reference.
	$t = preg_replace( '/(?<=\d)"/', '&#8243;', $t );
	// Opening singles, then everything left is a closing quote or apostrophe.
	$t = preg_replace( '/(^|[\s(\[{<"])\'(?=\S)/', '$1&#8216;', $t );
	$t = str_replace( "'", '&#8217;', $t );
	// Opening doubles, then closers.
	$t = preg_replace( '/(^|[\s(\[{<])"(?=\S)/', '$1&#8220;', $t );
	$t = str_replace( '"', '&#8221;', $t );
	return $t;
}

/**
 * Render a stored block-markup body the way the reference renders it for
 * content.rendered: block comment delimiters are removed (their surrounding
 * whitespace stays), paragraph blocks gain the wp-block-paragraph class,
 * quote blocks gain their layout-support classes, and the result is
 * texturized.
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
	// Quote blocks carry layout-support classes at render.
	$out = preg_replace_callback(
		'/<!-- wp:quote( \{.*?\})? -->(.*?)<!-- \/wp:quote -->/s',
		function ( $m ) {
			return preg_replace(
				'/(<blockquote\s+[^>]*class="[^"]*)"/',
				'$1 is-layout-flow wp-block-quote-is-layout-flow"',
				$m[2],
				1
			);
		},
		$out
	);
	// Every other block: keep the inner markup, drop the delimiters.
	$out = preg_replace( '/<!-- \/?wp:[^>]*?-->/', '', $out );
	return minn_texturize( $out );
}

/**
 * The reference's generated excerpt: disallowed blocks are removed whole
 * (a code block's text never reaches the excerpt), tags become spaces (list
 * items read as separate words), whitespace collapses, 55-word cap,
 * <p>-wrapped, texturized. Allowed-block coverage is oracle-proven:
 * paragraph, heading, list, quote (nested content included), preformatted.
 */
function minn_rendered_excerpt( array $post ): string {
	$source = $post['post_excerpt'];
	if ( '' === $source ) {
		$source  = $post['post_content'];
		$allowed = array( 'paragraph', 'heading', 'list', 'quote', 'preformatted' );
		if ( str_contains( $source, '<!-- wp:' ) ) {
			$source = preg_replace_callback(
				'/<!-- wp:([a-z0-9\/-]+)( \{.*?\})? -->(.*?)<!-- \/wp:\1 -->/s',
				static fn( $m ) => in_array( $m[1], $allowed, true ) ? $m[0] : '',
				$source
			);
			$source = preg_replace( '/<!-- wp:[^>]*?\/-->/', '', $source );
		}
	}
	$text = preg_replace( '/<!-- \/?wp:[^>]*?-->/', '', $source );
	$text = trim( preg_replace( '/<[^>]*>/', ' ', $text ) );
	// Whitespace always collapses to single spaces in generated excerpts.
	$words = preg_split( '/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY );
	if ( count( $words ) > 55 ) {
		$text = implode( ' ', array_slice( $words, 0, 55 ) ) . ' [&hellip;]';
	} else {
		$text = implode( ' ', $words );
	}
	return minn_texturize( '<p>' . $text . "</p>\n" );
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

/* ----------------------------------------------------------- posts/pages */

/** Post-type table for the content controllers. */
function minn_post_type_config( string $type ): array {
	$types = array(
		'post' => array( 'rest_base' => 'posts', 'link' => '/?p=%d' ),
		'page' => array( 'rest_base' => 'pages', 'link' => '/?page_id=%d' ),
	);
	return $types[ $type ];
}

/** Build one wp/v2 content object in the reference's view-context shape. */
function minn_rest_post_object( array $p ): array {
	$id   = (int) $p['ID'];
	$type = $p['post_type'];
	$cfg  = minn_post_type_config( $type );

	$obj = array(
		'id'           => $id,
		'date'         => minn_rest_date( $p['post_date'] ),
		'date_gmt'     => minn_rest_date( $p['post_date_gmt'] ),
		'guid'         => array( 'rendered' => $p['guid'] ),
		'modified'     => minn_rest_date( $p['post_modified'] ),
		'modified_gmt' => minn_rest_date( $p['post_modified_gmt'] ),
		'slug'         => $p['post_name'],
		'status'       => $p['post_status'],
		'type'         => $type,
		'link'         => minn_home_url( sprintf( $cfg['link'], $id ) ),
		'title'        => array( 'rendered' => minn_texturize( $p['post_title'] ) ),
		'content'      => array(
			'rendered'  => minn_render_blocks( $p['post_content'] ),
			'protected' => false,
		),
		'excerpt'      => array(
			'rendered'  => minn_rendered_excerpt( $p ),
			'protected' => false,
		),
		'author'         => (int) $p['post_author'],
		'featured_media' => (int) ( minn_post_meta_value( $id, '_thumbnail_id' ) ?? 0 ),
	);

	$class_list = array( 'post-' . $id, $type, 'type-' . $type, 'status-' . $p['post_status'] );

	if ( 'page' === $type ) {
		$obj['parent']         = (int) $p['post_parent'];
		$obj['menu_order']     = (int) $p['menu_order'];
		$obj['comment_status'] = $p['comment_status'];
		$obj['ping_status']    = $p['ping_status'];
		$obj['template']       = '';
		$obj['meta']           = array( 'footnotes' => minn_post_meta_value( $id, 'footnotes' ) ?? '' );
		$class_list[]          = 'hentry';
	} else {
		$cats       = minn_post_terms( $id, 'category' );
		$tags       = minn_post_terms( $id, 'post_tag' );
		$format_t   = minn_post_terms( $id, 'post_format' );
		$format     = $format_t ? str_replace( 'post-format-', '', $format_t[0][1] ) : 'standard';
		$sticky_ids = minn_serialized_int_list( minn_option( 'sticky_posts' ) );

		$obj['comment_status'] = $p['comment_status'];
		$obj['ping_status']    = $p['ping_status'];
		$obj['sticky']         = in_array( $id, $sticky_ids, true );
		$obj['template']       = '';
		$obj['format']         = $format;
		$obj['meta']           = array( 'footnotes' => minn_post_meta_value( $id, 'footnotes' ) ?? '' );
		$obj['categories']     = array_map( static fn( $c ) => (int) $c[0], $cats );
		$obj['tags']           = array_map( static fn( $t ) => (int) $t[0], $tags );

		$class_list[] = 'format-' . $format;
		$class_list[] = 'hentry';
		foreach ( $cats as $c ) {
			$class_list[] = 'category-' . $c[1];
		}
		foreach ( $tags as $t ) {
			$class_list[] = 'tag-' . $t[1];
		}
	}

	$obj['class_list'] = $class_list;
	$obj['_links']     = minn_rest_post_links( $p );
	return $obj;
}

function minn_rest_post_links( array $p ): array {
	$id     = (int) $p['ID'];
	$type   = $p['post_type'];
	$cfg    = minn_post_type_config( $type );
	$base   = '/wp/v2/' . $cfg['rest_base'];
	$author = (int) $p['post_author'];

	$links = array(
		'self'            => array(
			array(
				'href'        => minn_rest_url( $base . '/' . $id ),
				'targetHints' => array( 'allow' => array( 'GET' ) ),
			),
		),
		'collection'      => array( array( 'href' => minn_rest_url( $base ) ) ),
		'about'           => array( array( 'href' => minn_rest_url( '/wp/v2/types/' . $type ) ) ),
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
				'href'  => minn_rest_url( $base . '/' . $id . '/revisions' ),
			),
		),
		'up'              => ( 'page' === $type && (int) $p['post_parent'] > 0 ) ? array(
			array(
				'embeddable' => true,
				'href'       => minn_rest_url( $base . '/' . (int) $p['post_parent'] ),
			),
		) : null,
		'wp:attachment'   => array(
			array( 'href' => minn_rest_url( '/wp/v2/media', array( 'parent' => $id ) ) ),
		),
		'wp:term'         => 'post' === $type ? array(
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
		) : null,
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

function minn_rest_posts_list( string $type ): void {
	global $table_prefix;
	$per_page = max( 1, min( 100, (int) ( $_GET['per_page'] ?? 10 ) ) );
	$page     = max( 1, (int) ( $_GET['page'] ?? 1 ) );
	$offset   = ( $page - 1 ) * $per_page;

	$stmt = minn_db()->prepare(
		"SELECT COUNT(*) FROM {$table_prefix}posts WHERE post_type = ? AND post_status = 'publish'"
	);
	$stmt->bind_param( 's', $type );
	$stmt->execute();
	$total       = (int) $stmt->get_result()->fetch_row()[0];
	$total_pages = (int) ceil( $total / $per_page );

	if ( $page > 1 && $page > $total_pages ) {
		minn_rest_error( 'rest_post_invalid_page_number', 'The page number requested is larger than the number of pages available.', 400 );
	}

	$stmt = minn_db()->prepare(
		"SELECT * FROM {$table_prefix}posts WHERE post_type = ? AND post_status = 'publish'
		 ORDER BY post_date DESC LIMIT ?, ?"
	);
	$stmt->bind_param( 'sii', $type, $offset, $per_page );
	$stmt->execute();
	$rows = $stmt->get_result()->fetch_all( MYSQLI_ASSOC );

	header( 'X-WP-Total: ' . $total );
	header( 'X-WP-TotalPages: ' . $total_pages );
	minn_rest_send( array_map( 'minn_rest_post_object', $rows ), 200, true );
}

function minn_rest_posts_single( string $type, int $id ): void {
	global $table_prefix;
	$stmt = minn_db()->prepare(
		"SELECT * FROM {$table_prefix}posts WHERE ID = ? AND post_type = ? AND post_status = 'publish' LIMIT 1"
	);
	$stmt->bind_param( 'is', $id, $type );
	$stmt->execute();
	$row = $stmt->get_result()->fetch_assoc();
	if ( ! $row ) {
		minn_rest_error( 'rest_post_invalid_id', 'Invalid post ID.', 404 );
	}
	minn_rest_send( minn_rest_post_object( $row ) );
}

/* ----------------------------------------------------------------- terms */

/** Taxonomy table for the term controllers. */
function minn_taxonomy_config( string $rest_base ): array {
	$taxes = array(
		'categories' => array(
			'taxonomy'   => 'category',
			'link'       => static fn( array $t ) => '/?cat=' . $t['term_id'],
			'has_parent' => true,
			'post_arg'   => 'categories',
		),
		'tags'       => array(
			'taxonomy'   => 'post_tag',
			'link'       => static fn( array $t ) => '/?tag=' . $t['slug'],
			'has_parent' => false,
			'post_arg'   => 'tags',
		),
	);
	return $taxes[ $rest_base ];
}

function minn_rest_term_object( array $t, string $rest_base ): array {
	$cfg = minn_taxonomy_config( $rest_base );
	$obj = array(
		'id'          => (int) $t['term_id'],
		'count'       => (int) $t['count'],
		'description' => $t['description'],
		'link'        => minn_home_url( ( $cfg['link'] )( $t ) ),
		'name'        => $t['name'],
		'slug'        => $t['slug'],
		'taxonomy'    => $cfg['taxonomy'],
	);
	if ( $cfg['has_parent'] ) {
		$obj['parent'] = (int) $t['parent'];
	}
	$obj['meta']   = array();
	$obj['_links'] = array(
		'self'         => array(
			array(
				'href'        => minn_rest_url( '/wp/v2/' . $rest_base . '/' . $t['term_id'] ),
				'targetHints' => array( 'allow' => array( 'GET' ) ),
			),
		),
		'collection'   => array( array( 'href' => minn_rest_url( '/wp/v2/' . $rest_base ) ) ),
		'about'        => array( array( 'href' => minn_rest_url( '/wp/v2/taxonomies/' . $cfg['taxonomy'] ) ) ),
		'wp:post_type' => array(
			array( 'href' => minn_rest_url( '/wp/v2/posts', array( $cfg['post_arg'] => $t['term_id'] ) ) ),
		),
		'curies'       => array(
			array(
				'name'      => 'wp',
				'href'      => 'https://api.w.org/{rel}',
				'templated' => true,
			),
		),
	);
	return $obj;
}

function minn_rest_terms_list( string $rest_base ): void {
	global $table_prefix;
	$cfg      = minn_taxonomy_config( $rest_base );
	$per_page = max( 1, min( 100, (int) ( $_GET['per_page'] ?? 10 ) ) );
	$page     = max( 1, (int) ( $_GET['page'] ?? 1 ) );
	$offset   = ( $page - 1 ) * $per_page;

	$stmt = minn_db()->prepare(
		"SELECT COUNT(*) FROM {$table_prefix}term_taxonomy WHERE taxonomy = ?"
	);
	$stmt->bind_param( 's', $cfg['taxonomy'] );
	$stmt->execute();
	$total = (int) $stmt->get_result()->fetch_row()[0];

	$stmt = minn_db()->prepare(
		"SELECT t.term_id, t.name, t.slug, tt.description, tt.count, tt.parent
		 FROM {$table_prefix}terms t
		 JOIN {$table_prefix}term_taxonomy tt ON tt.term_id = t.term_id
		 WHERE tt.taxonomy = ? ORDER BY t.name ASC LIMIT ?, ?"
	);
	$stmt->bind_param( 'sii', $cfg['taxonomy'], $offset, $per_page );
	$stmt->execute();
	$rows = $stmt->get_result()->fetch_all( MYSQLI_ASSOC );

	header( 'X-WP-Total: ' . $total );
	header( 'X-WP-TotalPages: ' . (int) ceil( $total / $per_page ) );
	minn_rest_send( array_map( static fn( $t ) => minn_rest_term_object( $t, $rest_base ), $rows ), 200, true );
}

function minn_rest_terms_single( string $rest_base, int $id ): void {
	global $table_prefix;
	$cfg  = minn_taxonomy_config( $rest_base );
	$stmt = minn_db()->prepare(
		"SELECT t.term_id, t.name, t.slug, tt.description, tt.count, tt.parent
		 FROM {$table_prefix}terms t
		 JOIN {$table_prefix}term_taxonomy tt ON tt.term_id = t.term_id
		 WHERE tt.taxonomy = ? AND t.term_id = ? LIMIT 1"
	);
	$stmt->bind_param( 'si', $cfg['taxonomy'], $id );
	$stmt->execute();
	$row = $stmt->get_result()->fetch_assoc();
	if ( ! $row ) {
		minn_rest_error( 'rest_term_invalid', 'Term does not exist.', 404 );
	}
	minn_rest_send( minn_rest_term_object( $row, $rest_base ) );
}

/* ----------------------------------------------------------------- types */

/**
 * The engine's own registry of built-in types, seeded from the observed
 * contract (src/data/types.json, _links attached at runtime).
 */
function minn_types_registry(): array {
	static $types = null;
	if ( null === $types ) {
		$types = json_decode( (string) file_get_contents( __DIR__ . '/data/types.json' ), true );
		foreach ( $types as $slug => &$t ) {
			$t['_links'] = array(
				'collection' => array( array( 'href' => minn_rest_url( '/wp/v2/types' ) ) ),
				'wp:items'   => array( array( 'href' => minn_rest_url( '/wp/v2/' . $t['rest_base'] ) ) ),
				'curies'     => array(
					array(
						'name'      => 'wp',
						'href'      => 'https://api.w.org/{rel}',
						'templated' => true,
					),
				),
			);
		}
	}
	return $types;
}

function minn_rest_types_list(): void {
	// Deliberately NOT per-item: the reference filters the whole associative
	// payload, so _fields strips every type key and the response is [].
	minn_rest_send( minn_types_registry() );
}

function minn_rest_types_single( string $slug ): void {
	$types = minn_types_registry();
	if ( ! isset( $types[ $slug ] ) ) {
		minn_rest_error( 'rest_type_invalid', 'Invalid post type.', 404 );
	}
	minn_rest_send( $types[ $slug ] );
}


/* ----------------------------------------------------------------- users */

/** Gravatar URLs in the sizes the reference emits (sha256 of the email). */
function minn_avatar_urls( string $email ): array {
	$hash = hash( 'sha256', strtolower( trim( $email ) ) );
	$out  = array();
	foreach ( array( 24, 48, 96 ) as $size ) {
		$out[ (string) $size ] = 'https://secure.gravatar.com/avatar/' . $hash . '?s=' . $size . '&d=mm&r=g';
	}
	return $out;
}

/** One wp/v2 user object in view context (the only public shape). */
function minn_rest_user_object( array $u, bool $is_self = false ): array {
	$id = (int) $u['ID'];
	return array(
		'id'          => $id,
		'name'        => $u['display_name'],
		'url'         => $u['user_url'],
		'description' => minn_user_meta( $id, 'description' ) ?? '',
		'link'        => minn_home_url( '/?author=' . $id ),
		'slug'        => $u['user_nicename'],
		'avatar_urls' => minn_avatar_urls( $u['user_email'] ),
		'meta'        => array(),
		'_links'      => array(
			'self'       => array(
				array(
					'href'        => minn_rest_url( '/wp/v2/users/' . $id ),
					// An authenticated caller viewing their own record sees the
					// write verbs advertised in targetHints.
					'targetHints' => array(
						'allow' => $is_self
							? array( 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' )
							: array( 'GET' ),
					),
				),
			),
			'collection' => array( array( 'href' => minn_rest_url( '/wp/v2/users' ) ) ),
		),
	);
}

/** Users who have authored published content are public. */
function minn_rest_users_list(): void {
	global $table_prefix;
	$per_page = max( 1, min( 100, (int) ( $_GET['per_page'] ?? 10 ) ) );
	$page     = max( 1, (int) ( $_GET['page'] ?? 1 ) );
	$offset   = ( $page - 1 ) * $per_page;

	$where = "u.ID IN ( SELECT post_author FROM {$table_prefix}posts
	          WHERE post_status = 'publish' AND post_type IN ('post','page') )";
	$total = (int) minn_db()->query( "SELECT COUNT(*) FROM {$table_prefix}users u WHERE $where" )->fetch_row()[0];

	$stmt = minn_db()->prepare(
		"SELECT u.* FROM {$table_prefix}users u WHERE $where ORDER BY u.display_name ASC LIMIT ?, ?"
	);
	$stmt->bind_param( 'ii', $offset, $per_page );
	$stmt->execute();
	$rows = $stmt->get_result()->fetch_all( MYSQLI_ASSOC );

	$self = minn_current_user_id();
	header( 'X-WP-Total: ' . $total );
	header( 'X-WP-TotalPages: ' . (int) ceil( $total / $per_page ) );
	minn_rest_send(
		array_map( static fn( $u ) => minn_rest_user_object( $u, (int) $u['ID'] === $self ), $rows ),
		200,
		true
	);
}

function minn_rest_users_single( int $id ): void {
	$u = minn_get_user_by_id( $id );
	if ( ! $u ) {
		minn_rest_error( 'rest_user_invalid_id', 'Invalid user ID.', 404 );
	}
	minn_rest_send( minn_rest_user_object( $u, minn_current_user_id() === $id ) );
}

/** GET /wp/v2/users/me — requires a valid cookie AND a valid wp_rest nonce. */
function minn_rest_users_me(): void {
	$why  = '';
	$auth = minn_authenticate_rest( $why );
	if ( null === $auth ) {
		if ( 'rest_cookie_invalid_nonce' === $why ) {
			minn_rest_error( 'rest_cookie_invalid_nonce', 'Cookie check failed', 403 );
		}
		minn_rest_error( 'rest_not_logged_in', 'You are not currently logged in.', 401 );
	}
	minn_rest_send( minn_rest_user_object( $auth[0], true ) );
}

/** Authenticated user id for this request, or 0. Resolved once. */
function minn_current_user_id(): int {
	static $uid = null;
	if ( null === $uid ) {
		$why  = '';
		$auth = minn_authenticate_rest( $why );
		$uid  = $auth ? (int) $auth[0]['ID'] : 0;
	}
	return $uid;
}

/* -------------------------------------------------------------- dispatch */

function minn_rest_dispatch( string $route ): void {
	$route = '/' . trim( $route, '/' );

	if ( 'GET' !== ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) {
		minn_rest_error( 'rest_no_route', 'No route was found matching the URL and request method.', 404 );
	}
	if ( preg_match( '#^/wp/v2/(posts|pages)$#', $route, $m ) ) {
		minn_rest_posts_list( 'posts' === $m[1] ? 'post' : 'page' );
	}
	if ( preg_match( '#^/wp/v2/(posts|pages)/(\d+)$#', $route, $m ) ) {
		minn_rest_posts_single( 'posts' === $m[1] ? 'post' : 'page', (int) $m[2] );
	}
	if ( preg_match( '#^/wp/v2/(categories|tags)$#', $route, $m ) ) {
		minn_rest_terms_list( $m[1] );
	}
	if ( preg_match( '#^/wp/v2/(categories|tags)/(\d+)$#', $route, $m ) ) {
		minn_rest_terms_single( $m[1], (int) $m[2] );
	}
	if ( '/wp/v2/users/me' === $route ) {
		minn_rest_users_me();
	}
	if ( '/wp/v2/users' === $route ) {
		minn_rest_users_list();
	}
	if ( preg_match( '#^/wp/v2/users/(\\d+)$#', $route, $m ) ) {
		minn_rest_users_single( (int) $m[1] );
	}
	if ( '/wp/v2/types' === $route ) {
		minn_rest_types_list();
	}
	if ( preg_match( '#^/wp/v2/types/([\w-]+)$#', $route, $m ) ) {
		minn_rest_types_single( $m[1] );
	}
	minn_rest_error( 'rest_no_route', 'No route was found matching the URL and request method.', 404 );
}
