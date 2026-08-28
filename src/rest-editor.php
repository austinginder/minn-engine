<?php
/**
 * The editor-support surface: revisions, autosaves, the edit lock, and
 * the small minn-admin/v1 routes the editor and Settings views load
 * (templates, styles, patterns, permalinks, spam, languages, site-logo,
 * media months, reusable blocks).
 *
 * Implemented from oracle captures recorded in contracts/rest/editor.md;
 * no WordPress source is used.
 */

/** One revision row as wp/v2 serves it (autosaves and revisions alike). */
function minn_rest_revision_object( array $r, bool $with_preview = false ): array {
	$id     = (int) $r['ID'];
	$parent = (int) $r['post_parent'];
	$obj    = array(
		'author'       => (int) $r['post_author'],
		'date'         => str_replace( ' ', 'T', $r['post_date'] ),
		'date_gmt'     => str_replace( ' ', 'T', $r['post_date_gmt'] ),
		'id'           => $id,
		'modified'     => str_replace( ' ', 'T', $r['post_modified'] ),
		'modified_gmt' => str_replace( ' ', 'T', $r['post_modified_gmt'] ),
		'parent'       => $parent,
		'slug'         => $r['post_name'],
		'guid'         => $with_preview
			? array( 'rendered' => minn_home_url( '/?p=' . $id ), 'raw' => minn_home_url( '/?p=' . $id ) )
			: array( 'rendered' => minn_home_url( '/?p=' . $id ) ),
		'title'        => $with_preview
			? array( 'raw' => $r['post_title'], 'rendered' => minn_texturize( $r['post_title'] ) )
			: array( 'rendered' => minn_texturize( $r['post_title'] ) ),
		'content'      => $with_preview
			? array( 'raw' => $r['post_content'], 'rendered' => minn_render_blocks( $r['post_content'] ) )
			: array( 'rendered' => minn_render_blocks( $r['post_content'] ) ),
		'excerpt'      => $with_preview
			? array( 'raw' => $r['post_excerpt'], 'rendered' => '' === $r['post_excerpt'] ? '' : minn_comment_render( $r['post_excerpt'] ) )
			: array( 'rendered' => '' === $r['post_excerpt'] ? '' : minn_comment_render( $r['post_excerpt'] ) ),
		'meta'         => array( 'footnotes' => minn_post_meta_value( $id, 'footnotes' ) ?? '' ),
	);
	if ( $with_preview ) {
		$obj['preview_link'] = minn_home_url(
			'/?p=' . $parent . '&preview_id=' . $parent . '&preview_nonce=' . substr( bin2hex( random_bytes( 8 ) ), 0, 10 ) . '&preview=true'
		);
	}
	$parent_type = 'page' === ( minn_get_post_row( $parent )['post_type'] ?? 'post' ) ? 'pages' : 'posts';
	$obj['_links'] = array(
		'parent' => array( array( 'href' => minn_rest_url( '/wp/v2/' . $parent_type . '/' . $parent ) ) ),
	);
	return $obj;
}

/** Gate a revision surface on edit_post of the parent. */
function minn_revisions_require( int $parent_id, string $type ): int {
	$why  = '';
	$auth = minn_authenticate_rest( $why );
	if ( null === $auth ) {
		if ( 'rest_cookie_invalid_nonce' === $why ) {
			minn_rest_error( 'rest_cookie_invalid_nonce', 'Cookie check failed', 403 );
		}
		minn_rest_error( 'rest_cannot_read', 'Sorry, you are not allowed to view revisions of this post.', 401 );
	}
	$uid  = (int) $auth[0]['ID'];
	$post = minn_get_post_row( $parent_id );
	if ( ! $post || $post['post_type'] !== $type ) {
		minn_rest_error( 'rest_post_invalid_parent', 'Invalid post parent ID.', 404 );
	}
	if ( ! minn_user_can( $uid, 'edit_post', $parent_id ) ) {
		minn_rest_error( 'rest_cannot_read', 'Sorry, you are not allowed to view revisions of this post.', 403 );
	}
	return $uid;
}

/** GET /wp/v2/{posts,pages}/{id}/revisions — real revisions, not autosaves. */
function minn_rest_revisions_list( string $type, int $parent_id ): void {
	global $table_prefix;
	minn_revisions_require( $parent_id, $type );
	$like = $parent_id . '-autosave%';
	$stmt = minn_db()->prepare(
		"SELECT * FROM {$table_prefix}posts
		 WHERE post_parent = ? AND post_type = 'revision' AND post_name NOT LIKE ?
		 ORDER BY post_date DESC, ID DESC"
	);
	$stmt->bind_param( 'is', $parent_id, $like );
	$stmt->execute();
	$rows = $stmt->get_result()->fetch_all( MYSQLI_ASSOC );
	header( 'X-WP-Total: ' . count( $rows ) );
	header( 'X-WP-TotalPages: 1' );
	minn_rest_send( array_map( static fn( $r ) => minn_rest_revision_object( $r ), $rows ), 200, true );
}

/** GET /wp/v2/{posts,pages}/{id}/autosaves */
function minn_rest_autosaves_list( string $type, int $parent_id ): void {
	global $table_prefix;
	minn_revisions_require( $parent_id, $type );
	$like = $parent_id . '-autosave%';
	$stmt = minn_db()->prepare(
		"SELECT * FROM {$table_prefix}posts
		 WHERE post_parent = ? AND post_type = 'revision' AND post_name LIKE ?
		 ORDER BY post_date DESC, ID DESC"
	);
	$stmt->bind_param( 'is', $parent_id, $like );
	$stmt->execute();
	$rows = $stmt->get_result()->fetch_all( MYSQLI_ASSOC );
	minn_rest_send( array_map( static fn( $r ) => minn_rest_revision_object( $r ), $rows ), 200, true );
}

/** POST /wp/v2/{posts,pages}/{id}/autosaves — one autosave slot per author. */
function minn_rest_autosaves_create( string $type, int $parent_id ): void {
	global $table_prefix;
	$uid  = minn_revisions_require( $parent_id, $type );
	$body = minn_request_body();

	$title   = (string) minn_extract_field( $body['title'] ?? '' );
	$content = (string) minn_extract_field( $body['content'] ?? '' );
	$excerpt = (string) minn_extract_field( $body['excerpt'] ?? '' );

	$slug    = $parent_id . '-autosave-v1';
	$now     = gmdate( 'Y-m-d H:i:s', time() + minn_gmt_offset() );
	$now_gmt = gmdate( 'Y-m-d H:i:s' );

	$stmt = minn_db()->prepare(
		"SELECT ID FROM {$table_prefix}posts
		 WHERE post_parent = ? AND post_type = 'revision' AND post_name = ? AND post_author = ? LIMIT 1"
	);
	$stmt->bind_param( 'isi', $parent_id, $slug, $uid );
	$stmt->execute();
	$existing = $stmt->get_result()->fetch_row();

	if ( $existing ) {
		$rid  = (int) $existing[0];
		$stmt = minn_db()->prepare(
			"UPDATE {$table_prefix}posts SET post_title = ?, post_content = ?, post_excerpt = ?,
			 post_date = ?, post_date_gmt = ?, post_modified = ?, post_modified_gmt = ? WHERE ID = ?"
		);
		$stmt->bind_param( 'sssssssi', $title, $content, $excerpt, $now, $now_gmt, $now, $now_gmt, $rid );
		$stmt->execute();
	} else {
		$stmt = minn_db()->prepare(
			"INSERT INTO {$table_prefix}posts
			 (post_author, post_date, post_date_gmt, post_content, post_title, post_excerpt,
			  post_status, comment_status, ping_status, post_password, post_name, to_ping, pinged,
			  post_modified, post_modified_gmt, post_content_filtered, post_parent, guid, menu_order,
			  post_type, post_mime_type, comment_count)
			 VALUES (?, ?, ?, ?, ?, ?, 'inherit', 'closed', 'closed', '', ?, '', '', ?, ?, '', ?, '', 0, 'revision', '', 0)"
		);
		$stmt->bind_param( 'issssssssi', $uid, $now, $now_gmt, $content, $title, $excerpt, $slug, $now, $now_gmt, $parent_id );
		$stmt->execute();
		$rid  = (int) minn_db()->insert_id;
		$guid = minn_home_url( '/?p=' . $rid );
		$g    = minn_db()->prepare( "UPDATE {$table_prefix}posts SET guid = ? WHERE ID = ?" );
		$g->bind_param( 'si', $guid, $rid );
		$g->execute();
	}

	minn_rest_send( minn_rest_revision_object( minn_get_post_row( $rid ), true ) );
}

/* --------------------------------------- the small minn-admin/v1 helpers */

function minn_v1_editor_dispatch( string $route, string $method ): void {
	if ( preg_match( '#^/minn-admin/v1/posts/(\d+)/lock$#', $route, $m ) && 'POST' === $method ) {
		[ , $uid ] = minn_v1_require();
		$id = (int) $m[1];
		if ( minn_user_can( $uid, 'edit_post', $id ) ) {
			minn_set_post_meta( $id, '_edit_lock', time() . ':' . $uid );
			minn_rest_send( array( 'acquired' => true ) );
		}
		minn_rest_error( 'rest_cannot_edit', 'Sorry, you are not allowed to edit this post.', 403 );
	}
	if ( '/minn-admin/v1/templates' === $route && 'GET' === $method ) {
		minn_v1_require();
		// No theme, no page templates. Honest empty set.
		minn_rest_send( array( 'templates' => array() ) );
	}
	if ( '/minn-admin/v1/editor-styles' === $route && 'GET' === $method ) {
		minn_v1_require();
		// The reference serves core's block CSS from wp-includes; the engine
		// has no wp-includes to serve (recorded divergence).
		minn_rest_send( array( 'urls' => array() ) );
	}
	if ( '/minn-admin/v1/patterns' === $route && 'GET' === $method ) {
		minn_v1_require();
		// Theme patterns are GPL theme content the engine does not carry.
		minn_rest_send( array( 'patterns' => array() ) );
	}
	if ( '/minn-admin/v1/site-logo' === $route && 'GET' === $method ) {
		minn_v1_require();
		minn_rest_send( array( 'supported' => false, 'id' => 0, 'url' => '' ) );
	}
	if ( '/minn-admin/v1/permalinks' === $route && 'GET' === $method ) {
		[ , $uid ] = minn_v1_require();
		if ( ! minn_user_can( $uid, 'manage_options' ) ) {
			minn_rest_error( 'rest_forbidden', 'Sorry, you are not allowed to do that.', 403 );
		}
		$structure = (string) ( minn_option( 'permalink_structure' ) ?? '' );
		minn_rest_send(
			array(
				'structure'     => $structure,
				'category_base' => (string) ( minn_option( 'category_base' ) ?? '' ),
				'tag_base'      => (string) ( minn_option( 'tag_base' ) ?? '' ),
				'pretty'        => '' !== $structure,
				'app_url'       => minn_home_url( '' !== $structure ? '/minn-admin' : '/?minn_admin=1' ),
			)
		);
	}
	if ( '/minn-admin/v1/spam' === $route && 'GET' === $method ) {
		global $table_prefix;
		[ , $uid ] = minn_v1_require();
		if ( ! minn_user_can( $uid, 'moderate_comments' ) ) {
			minn_rest_error( 'rest_forbidden', 'Sorry, you are not allowed to do that.', 403 );
		}
		$counts = array( 'spam' => 0, 'pending' => 0 );
		$res    = minn_db()->query(
			"SELECT comment_approved, COUNT(*) AS c FROM {$table_prefix}comments
			 WHERE comment_approved IN ('spam', '0') GROUP BY comment_approved"
		);
		foreach ( $res->fetch_all( MYSQLI_ASSOC ) as $row ) {
			$counts[ 'spam' === $row['comment_approved'] ? 'spam' : 'pending' ] = (int) $row['c'];
		}
		minn_rest_send(
			array(
				'providers'       => array(),
				'queue'           => $counts,
				'disallowed_keys' => (string) ( minn_option( 'disallowed_keys' ) ?? '' ),
			)
		);
	}
	if ( '/minn-admin/v1/languages' === $route && 'GET' === $method ) {
		[ , $uid ] = minn_v1_require();
		if ( ! minn_user_can( $uid, 'manage_options' ) ) {
			minn_rest_error( 'rest_forbidden', 'Sorry, you are not allowed to do that.', 403 );
		}
		// The available list is captured registry data (data/languages.json);
		// installed reflects this site (no language packs on disk).
		$data = json_decode( (string) file_get_contents( __DIR__ . '/data/languages.json' ), true );
		$data['current'] = (string) ( minn_option( 'WPLANG' ) ?? '' );
		minn_rest_send( $data );
	}
	if ( '/minn-admin/v1/media/months' === $route && 'GET' === $method ) {
		global $table_prefix;
		minn_v1_require();
		$res = minn_db()->query(
			"SELECT DISTINCT DATE_FORMAT(post_date, '%Y-%m') AS ym FROM {$table_prefix}posts
			 WHERE post_type = 'attachment' AND post_status = 'inherit' ORDER BY ym DESC"
		);
		minn_rest_send( array_column( $res->fetch_all( MYSQLI_ASSOC ), 'ym' ) );
	}
}

/** GET /wp/v2/blocks — reusable blocks / synced patterns (wp_block rows). */
function minn_rest_blocks_list(): void {
	global $table_prefix;
	$uid = minn_current_user_id();
	if ( ( $_GET['context'] ?? 'view' ) === 'edit' && ! minn_user_can( $uid, 'edit_posts' ) ) {
		minn_rest_error( 'rest_forbidden_context', 'Sorry, you are not allowed to edit posts in this post type.', $uid ? 403 : 401 );
	}
	$status = (string) ( $_GET['status'] ?? 'publish' );
	$stmt   = minn_db()->prepare(
		"SELECT * FROM {$table_prefix}posts WHERE post_type = 'wp_block' AND post_status = ?
		 ORDER BY post_date DESC LIMIT 100"
	);
	$stmt->bind_param( 's', $status );
	$stmt->execute();
	$rows = $stmt->get_result()->fetch_all( MYSQLI_ASSOC );
	header( 'X-WP-Total: ' . count( $rows ) );
	header( 'X-WP-TotalPages: ' . ( count( $rows ) ? 1 : 0 ) );
	$out = array();
	foreach ( $rows as $r ) {
		$rid   = (int) $r['ID'];
		$out[] = array(
			'id'    => $rid,
			'title' => array( 'raw' => $r['post_title'] ),
			'meta'  => array( 'footnotes' => minn_post_meta_value( $rid, 'footnotes' ) ?? '' ),
			'wp_pattern_sync_status' => (string) ( minn_post_meta_value( $rid, 'wp_pattern_sync_status' ) ?? '' ),
		);
	}
	minn_rest_send( $out, 200, true );
}
