<?php
/**
 * Minn Engine writes — create, update, and delete for posts and pages.
 *
 * Every write is gated by the capability engine (src/caps.php) and returns
 * the edit-context object WordPress returns, so a client cannot tell the
 * write went through the engine. Implemented from the behavior matrix in
 * contracts/rest/writes.md, cross-checked against a live reference; no
 * WordPress source is used.
 */

/** Decode a JSON (or form) request body into an associative array. */
function minn_request_body(): array {
	$raw = file_get_contents( 'php://input' );
	if ( '' === trim( (string) $raw ) ) {
		return $_POST;
	}
	$data = json_decode( (string) $raw, true );
	return is_array( $data ) ? $data : array();
}

/** Require an authenticated user for a write, or emit the WP refusal. */
function minn_require_auth_write( string $create_or_edit ): array {
	$why  = '';
	$auth = minn_authenticate_rest( $why );
	if ( null === $auth ) {
		if ( 'rest_cookie_invalid_nonce' === $why ) {
			minn_rest_error( 'rest_cookie_invalid_nonce', 'Cookie check failed', 403 );
		}
		// A write with no valid identity is 401 with the operation's code.
		$code = 'create' === $create_or_edit ? 'rest_cannot_create' : 'rest_cannot_edit';
		$msg  = 'create' === $create_or_edit
			? 'Sorry, you are not allowed to create posts as this user.'
			: 'Sorry, you are not allowed to edit this post.';
		minn_rest_error( $code, $msg, 401 );
	}
	return $auth;
}

/* ---------------------------------------------------------------- create */

function minn_rest_create_post( string $type ): void {
	$auth = minn_require_auth_write( 'create' );
	$uid  = (int) $auth[0]['ID'];
	$cfg  = minn_post_type_config( $type );

	// Creating requires the type's create/edit primitive.
	$create_cap = 'page' === $type ? 'edit_pages' : 'edit_posts';
	if ( ! minn_user_can( $uid, $create_cap ) ) {
		minn_rest_error( 'rest_cannot_create', 'Sorry, you are not allowed to create posts as this user.', 403 );
	}

	$body   = minn_request_body();
	$status = $body['status'] ?? 'draft';
	minn_check_sticky_password_conflict( $body, null );
	$author = $uid;
	if ( isset( $body['author'] ) && (int) $body['author'] !== $uid ) {
		if ( ! minn_user_can( $uid, 'page' === $type ? 'edit_others_pages' : 'edit_others_posts' ) ) {
			minn_rest_error( 'rest_cannot_edit_others', 'Sorry, you are not allowed to update posts as this user.', 403 );
		}
		$author = (int) $body['author'];
	}

	// Publishing requires publish_{type}s.
	if ( in_array( $status, array( 'publish', 'future', 'private' ), true ) ) {
		$publish_cap = 'page' === $type ? 'publish_pages' : 'publish_posts';
		if ( ! minn_user_can( $uid, $publish_cap ) ) {
			minn_rest_error( 'rest_cannot_publish', 'Sorry, you are not allowed to create private posts in this post type.', 403 );
		}
	}

	global $table_prefix;
	$now     = gmdate( 'Y-m-d H:i:s' );
	$now_gmt = $now;
	$local   = minn_local_now();

	$title   = (string) minn_extract_field( $body['title'] ?? '' );
	$content = (string) minn_extract_field( $body['content'] ?? '' );
	$excerpt = (string) minn_extract_field( $body['excerpt'] ?? '' );
	$slug    = isset( $body['slug'] ) ? minn_unique_slug( (string) $body['slug'], 0 ) : '';

	$date     = $local;
	$date_gmt = ( 'publish' === $status || 'private' === $status ) ? $now_gmt : '0000-00-00 00:00:00';
	$modified     = $local;
	$modified_gmt = $now_gmt;
	if ( isset( $body['date'] ) && '' !== (string) $body['date'] ) {
		// An explicit date is site-local; publishing into the future schedules.
		$date         = str_replace( 'T', ' ', (string) $body['date'] );
		$stamp        = strtotime( $date . ' UTC' ) - minn_gmt_offset();
		$date_gmt     = gmdate( 'Y-m-d H:i:s', $stamp );
		$modified     = $date;
		$modified_gmt = $date_gmt;
		if ( 'publish' === $status && $stamp > time() ) {
			$status = 'future';
		}
	}
	$comment_status = in_array( $body['comment_status'] ?? '', array( 'open', 'closed' ), true ) ? $body['comment_status'] : 'open';
	$ping_status    = in_array( $body['ping_status'] ?? '', array( 'open', 'closed' ), true ) ? $body['ping_status'] : 'open';
	$password       = (string) ( $body['password'] ?? '' );
	$parent         = 'page' === $type ? (int) ( $body['parent'] ?? 0 ) : 0;
	$menu_order     = 'page' === $type ? (int) ( $body['menu_order'] ?? 0 ) : 0;

	$stmt = minn_db()->prepare(
		"INSERT INTO {$table_prefix}posts
		 (post_author, post_date, post_date_gmt, post_content, post_title, post_excerpt,
		  post_status, comment_status, ping_status, post_password, post_name, post_parent,
		  menu_order, post_modified, post_modified_gmt,
		  post_type, guid, to_ping, pinged, post_content_filtered)
		 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, '', '', '', '')"
	);
	$stmt->bind_param(
		'issssssssssiisss',
		$author, $date, $date_gmt, $content, $title, $excerpt, $status, $comment_status, $ping_status, $password, $slug, $parent, $menu_order, $modified, $modified_gmt, $type
	);
	$stmt->execute();
	$id = (int) minn_db()->insert_id;
	minn_apply_extended_fields( $id, $body, $uid, $type );

	// GUID is set from the id after insert, as the reference does.
	$guid = minn_home_url( '/?' . ( 'page' === $type ? 'page_id' : 'p' ) . '=' . $id );
	$g    = minn_db()->prepare( "UPDATE {$table_prefix}posts SET guid = ? WHERE ID = ?" );
	$g->bind_param( 'si', $guid, $id );
	$g->execute();

	minn_apply_terms( $id, $body );
	// A post with no category given gets the site's default category, exactly
	// as the reference assigns it on create.
	if ( 'post' === $type && ( ! isset( $body['categories'] ) || array() === $body['categories'] ) ) {
		$default = (int) ( minn_option( 'default_category' ) ?? 1 );
		minn_set_object_terms( $id, 'category', array( $default ) );
	}

	$post = minn_get_post_row( $id );
	header( 'Location: ' . minn_rest_url( '/wp/v2/' . $cfg['rest_base'] . '/' . $id ) );
	minn_rest_send( minn_rest_post_object_edit( $post, $uid ), 201 );
}

/* ---------------------------------------------------------------- update */

function minn_rest_update_post( string $type, int $id ): void {
	$auth = minn_require_auth_write( 'edit' );
	$uid  = (int) $auth[0]['ID'];

	$post = minn_get_post_row( $id );
	if ( ! $post || $post['post_type'] !== $type ) {
		minn_rest_error( 'rest_post_invalid_id', 'Invalid post ID.', 404 );
	}
	if ( ! minn_user_can( $uid, 'edit_post', $id ) ) {
		minn_rest_error( 'rest_cannot_edit', 'Sorry, you are not allowed to edit this post.', 403 );
	}

	$body = minn_request_body();
	minn_check_sticky_password_conflict( $body, $post );
	$sets = array();
	$vals = array();
	$typs = '';

	if ( isset( $body['author'] ) && (int) $body['author'] !== (int) $post['post_author'] ) {
		if ( ! minn_user_can( $uid, 'page' === $type ? 'edit_others_pages' : 'edit_others_posts' ) ) {
			minn_rest_error( 'rest_cannot_edit_others', 'Sorry, you are not allowed to update posts as this user.', 403 );
		}
		$sets[] = 'post_author = ?';
		$vals[] = (int) $body['author'];
		$typs  .= 'i';
	}
	foreach ( array( 'comment_status', 'ping_status' ) as $flag ) {
		if ( isset( $body[ $flag ] ) && in_array( $body[ $flag ], array( 'open', 'closed' ), true ) ) {
			$sets[] = $flag . ' = ?';
			$vals[] = (string) $body[ $flag ];
			$typs  .= 's';
		}
	}
	if ( array_key_exists( 'password', $body ) ) {
		$sets[] = 'post_password = ?';
		$vals[] = (string) $body['password'];
		$typs  .= 's';
	}
	if ( 'page' === $type && isset( $body['parent'] ) ) {
		$sets[] = 'post_parent = ?';
		$vals[] = (int) $body['parent'];
		$typs  .= 'i';
	}
	if ( 'page' === $type && isset( $body['menu_order'] ) ) {
		$sets[] = 'menu_order = ?';
		$vals[] = (int) $body['menu_order'];
		$typs  .= 'i';
	}
	if ( isset( $body['date'] ) && '' !== (string) $body['date'] ) {
		$new_date = str_replace( 'T', ' ', (string) $body['date'] );
		$stamp    = strtotime( $new_date . ' UTC' ) - minn_gmt_offset();
		$sets[]   = 'post_date = ?';
		$vals[]   = $new_date;
		$typs    .= 's';
		$sets[]   = 'post_date_gmt = ?';
		$vals[]   = gmdate( 'Y-m-d H:i:s', $stamp );
		$typs    .= 's';
		$effective = (string) ( $body['status'] ?? $post['post_status'] );
		if ( 'publish' === $effective && $stamp > time() && ! isset( $body['status'] ) ) {
			$body['status'] = 'future';
		} elseif ( 'publish' === ( $body['status'] ?? '' ) && $stamp > time() ) {
			$body['status'] = 'future';
		}
	}

	if ( array_key_exists( 'title', $body ) ) {
		$sets[] = 'post_title = ?';
		$vals[] = (string) minn_extract_field( $body['title'] );
		$typs  .= 's';
	}
	if ( array_key_exists( 'content', $body ) ) {
		$sets[] = 'post_content = ?';
		$vals[] = (string) minn_extract_field( $body['content'] );
		$typs  .= 's';
	}
	if ( array_key_exists( 'excerpt', $body ) ) {
		$sets[] = 'post_excerpt = ?';
		$vals[] = (string) minn_extract_field( $body['excerpt'] );
		$typs  .= 's';
	}
	if ( array_key_exists( 'slug', $body ) ) {
		$sets[] = 'post_name = ?';
		$vals[] = minn_unique_slug( (string) $body['slug'], $id );
		$typs  .= 's';
	}
	if ( array_key_exists( 'status', $body ) ) {
		$new_status = (string) $body['status'];
		if ( in_array( $new_status, array( 'publish', 'future', 'private' ), true )
			&& ! minn_user_can( $uid, 'page' === $type ? 'publish_pages' : 'publish_posts' ) ) {
			minn_rest_error( 'rest_cannot_publish', 'Sorry, you are not allowed to publish posts in this post type.', 403 );
		}
		$sets[] = 'post_status = ?';
		$vals[] = $new_status;
		$typs  .= 's';
		// Moving to publish for the first time stamps the publish date.
		if ( 'publish' === $new_status && ! in_array( $post['post_status'], array( 'publish', 'private', 'future' ), true ) ) {
			$now      = minn_local_now();
			$now_gmt  = gmdate( 'Y-m-d H:i:s' );
			$sets[]   = 'post_date = ?';
			$vals[]   = $now;
			$typs    .= 's';
			$sets[]   = 'post_date_gmt = ?';
			$vals[]   = $now_gmt;
			$typs    .= 's';
		}
	}

	$now     = minn_local_now();
	$now_gmt = gmdate( 'Y-m-d H:i:s' );
	$sets[]  = 'post_modified = ?';
	$vals[]  = $now;
	$typs   .= 's';
	$sets[]  = 'post_modified_gmt = ?';
	$vals[]  = $now_gmt;
	$typs   .= 's';

	global $table_prefix;
	$sql  = "UPDATE {$table_prefix}posts SET " . implode( ', ', $sets ) . ' WHERE ID = ?';
	$vals[] = $id;
	$typs  .= 'i';
	$stmt = minn_db()->prepare( $sql );
	$stmt->bind_param( $typs, ...$vals );
	$stmt->execute();

	minn_apply_terms( $id, $body );
	minn_apply_extended_fields( $id, $body, $uid, $type );
	// A publish/unpublish transition changes the terms' published counts.
	if ( array_key_exists( 'status', $body ) && $body['status'] !== $post['post_status'] ) {
		minn_recount_post_taxonomies( $id );
	}

	minn_rest_send( minn_rest_post_object_edit( minn_get_post_row( $id ), $uid ) );
}

/* ---------------------------------------------------------------- delete */

function minn_rest_delete_post( string $type, int $id ): void {
	$auth = minn_require_auth_write( 'edit' );
	$uid  = (int) $auth[0]['ID'];

	$post = minn_get_post_row( $id );
	if ( ! $post || $post['post_type'] !== $type ) {
		minn_rest_error( 'rest_post_invalid_id', 'Invalid post ID.', 404 );
	}
	if ( ! minn_user_can( $uid, 'delete_post', $id ) ) {
		minn_rest_error( 'rest_cannot_delete', 'Sorry, you are not allowed to delete this post.', 403 );
	}

	$force = filter_var( $_GET['force'] ?? false, FILTER_VALIDATE_BOOLEAN );
	global $table_prefix;

	if ( ! $force ) {
		if ( 'trash' === $post['post_status'] ) {
			minn_rest_error( 'rest_already_trashed', 'The post has already been deleted.', 410 );
		}
		$before = minn_rest_post_object_edit( $post, $uid );
		$stmt   = minn_db()->prepare(
			"UPDATE {$table_prefix}posts SET post_status = 'trash' WHERE ID = ?"
		);
		$stmt->bind_param( 'i', $id );
		$stmt->execute();
		// Preserve the pre-trash status the way core stores _wp_trash_meta_status.
		minn_set_post_meta( $id, '_wp_trash_meta_status', $post['post_status'] );
		minn_set_post_meta( $id, '_wp_trash_meta_time', (string) time() );
		// A trashed post no longer counts toward its terms' published totals.
		minn_recount_post_taxonomies( $id );
		$after = minn_rest_post_object_edit( minn_get_post_row( $id ), $uid );
		minn_rest_send( $after );
	}

	// Force delete: capture the object, remove the row, its revisions and
	// its term links (core cascades child revisions the same way).
	$previous = minn_rest_post_object_edit( $post, $uid );
	$del      = minn_db()->prepare( "DELETE FROM {$table_prefix}posts WHERE post_parent = ? AND post_type = 'revision'" );
	$del->bind_param( 'i', $id );
	$del->execute();
	$del = minn_db()->prepare( "DELETE FROM {$table_prefix}posts WHERE ID = ?" );
	$del->bind_param( 'i', $id );
	$del->execute();
	// Capture the taxonomies this post touched before dropping the links, so
	// their published counts can be refreshed afterward.
	$taxes = minn_post_taxonomies( $id );
	$tr    = minn_db()->prepare( "DELETE FROM {$table_prefix}term_relationships WHERE object_id = ?" );
	$tr->bind_param( 'i', $id );
	$tr->execute();
	$pm = minn_db()->prepare( "DELETE FROM {$table_prefix}postmeta WHERE post_id = ?" );
	$pm->bind_param( 'i', $id );
	$pm->execute();
	foreach ( $taxes as $tax ) {
		minn_recount_taxonomy( $tax );
	}

	minn_rest_send( array( 'deleted' => true, 'previous' => $previous ) );
}

/* --------------------------------------------------------------- helpers */

/** A field that may arrive as a scalar or as {raw: ...}. */
function minn_extract_field( $value ): string {
	if ( is_array( $value ) ) {
		return (string) ( $value['raw'] ?? $value['rendered'] ?? '' );
	}
	return (string) $value;
}

function minn_local_now(): string {
	$offset = (float) ( minn_option( 'gmt_offset' ) ?? 0 );
	return gmdate( 'Y-m-d H:i:s', time() + (int) round( $offset * 3600 ) );
}

function minn_get_post_row( int $id ): ?array {
	global $table_prefix;
	$stmt = minn_db()->prepare( "SELECT * FROM {$table_prefix}posts WHERE ID = ? LIMIT 1" );
	$stmt->bind_param( 'i', $id );
	$stmt->execute();
	return $stmt->get_result()->fetch_assoc() ?: null;
}

function minn_set_post_meta( int $id, string $key, string $value ): void {
	global $table_prefix;
	$stmt = minn_db()->prepare(
		"INSERT INTO {$table_prefix}postmeta (post_id, meta_key, meta_value) VALUES (?, ?, ?)"
	);
	$stmt->bind_param( 'iss', $id, $key, $value );
	$stmt->execute();
}

/** A slug unique within the posts table (append -2, -3, … on collision). */
function minn_unique_slug( string $desired, int $exclude_id ): string {
	global $table_prefix;
	$base = minn_sanitize_slug( $desired );
	if ( '' === $base ) {
		return '';
	}
	$slug = $base;
	$n    = 1;
	while ( true ) {
		$stmt = minn_db()->prepare(
			"SELECT ID FROM {$table_prefix}posts WHERE post_name = ? AND ID <> ? LIMIT 1"
		);
		$stmt->bind_param( 'si', $slug, $exclude_id );
		$stmt->execute();
		if ( ! $stmt->get_result()->fetch_row() ) {
			return $slug;
		}
		$n++;
		$slug = $base . '-' . $n;
	}
}

function minn_sanitize_slug( string $s ): string {
	$s = strtolower( trim( $s ) );
	$s = preg_replace( '/[^a-z0-9\-_]+/', '-', $s );
	$s = preg_replace( '/-+/', '-', $s );
	return trim( $s, '-' );
}

/** Assign categories/tags arrays from a write body, replacing existing links. */
function minn_apply_terms( int $id, array $body ): void {
	$map = array( 'categories' => 'category', 'tags' => 'post_tag' );
	foreach ( $map as $field => $taxonomy ) {
		if ( ! array_key_exists( $field, $body ) || ! is_array( $body[ $field ] ) ) {
			continue;
		}
		minn_set_object_terms( $id, $taxonomy, array_map( 'intval', $body[ $field ] ) );
	}
}

function minn_set_object_terms( int $id, string $taxonomy, array $term_ids ): void {
	global $table_prefix;
	// Remove existing links for this taxonomy.
	$existing = minn_db()->prepare(
		"SELECT tt.term_taxonomy_id FROM {$table_prefix}term_taxonomy tt
		 JOIN {$table_prefix}term_relationships tr ON tr.term_taxonomy_id = tt.term_taxonomy_id
		 WHERE tr.object_id = ? AND tt.taxonomy = ?"
	);
	$existing->bind_param( 'is', $id, $taxonomy );
	$existing->execute();
	foreach ( $existing->get_result()->fetch_all( MYSQLI_NUM ) as $r ) {
		$d = minn_db()->prepare(
			"DELETE FROM {$table_prefix}term_relationships WHERE object_id = ? AND term_taxonomy_id = ?"
		);
		$ttid = (int) $r[0];
		$d->bind_param( 'ii', $id, $ttid );
		$d->execute();
	}
	// Add the requested terms and refresh counts.
	foreach ( $term_ids as $term_id ) {
		$q = minn_db()->prepare(
			"SELECT term_taxonomy_id FROM {$table_prefix}term_taxonomy WHERE term_id = ? AND taxonomy = ? LIMIT 1"
		);
		$q->bind_param( 'is', $term_id, $taxonomy );
		$q->execute();
		$ttid = $q->get_result()->fetch_row();
		if ( ! $ttid ) {
			continue;
		}
		$ttid = (int) $ttid[0];
		$ins  = minn_db()->prepare(
			"INSERT IGNORE INTO {$table_prefix}term_relationships (object_id, term_taxonomy_id) VALUES (?, ?)"
		);
		$ins->bind_param( 'ii', $id, $ttid );
		$ins->execute();
	}
	minn_recount_taxonomy( $taxonomy );
}

/** The distinct taxonomies a post has term relationships in. */
function minn_post_taxonomies( int $id ): array {
	global $table_prefix;
	$stmt = minn_db()->prepare(
		"SELECT DISTINCT tt.taxonomy FROM {$table_prefix}term_relationships tr
		 JOIN {$table_prefix}term_taxonomy tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
		 WHERE tr.object_id = ?"
	);
	$stmt->bind_param( 'i', $id );
	$stmt->execute();
	return array_map( static fn( $r ) => $r[0], $stmt->get_result()->fetch_all( MYSQLI_NUM ) );
}

/** Recount every taxonomy a post participates in (used on status changes). */
function minn_recount_post_taxonomies( int $id ): void {
	foreach ( minn_post_taxonomies( $id ) as $tax ) {
		minn_recount_taxonomy( $tax );
	}
}

function minn_recount_taxonomy( string $taxonomy ): void {
	global $table_prefix;
	// Recompute counts for every term in this taxonomy (small taxonomies; the
	// reference keeps term_taxonomy.count in step with published relationships).
	$stmt = minn_db()->prepare(
		"UPDATE {$table_prefix}term_taxonomy tt SET tt.count = (
			SELECT COUNT(*) FROM {$table_prefix}term_relationships tr
			JOIN {$table_prefix}posts p ON p.ID = tr.object_id
			WHERE tr.term_taxonomy_id = tt.term_taxonomy_id
			  AND p.post_status = 'publish'
		) WHERE tt.taxonomy = ?"
	);
	$stmt->bind_param( 's', $taxonomy );
	$stmt->execute();
}

/* ------------------------------------------------- extended write fields */

/** Core refuses a sticky+password combination outright. */
function minn_check_sticky_password_conflict( array $body, ?array $post ): void {
	$wants_sticky   = ! empty( $body['sticky'] )
		|| ( ! isset( $body['sticky'] ) && $post && in_array( (int) $post['ID'], minn_serialized_int_list( minn_option( 'sticky_posts' ) ), true ) );
	$wants_password = '' !== (string) ( $body['password'] ?? '' )
		|| ( ! array_key_exists( 'password', $body ) && $post && '' !== $post['post_password'] );
	if ( $wants_sticky && $wants_password && ( isset( $body['sticky'] ) || array_key_exists( 'password', $body ) ) ) {
		minn_rest_error( 'rest_invalid_field', 'A post can not be sticky and have a password.', 400 );
	}
}

/** Rewrite the sticky_posts option with/without one id. */
function minn_write_sticky( int $id, bool $on ): void {
	$ids = minn_serialized_int_list( minn_option( 'sticky_posts' ) );
	if ( $on && ! in_array( $id, $ids, true ) ) {
		$ids[] = $id;
	} elseif ( ! $on ) {
		$ids = array_values( array_diff( $ids, array( $id ) ) );
	}
	$out = 'a:' . count( $ids ) . ':{';
	foreach ( array_values( $ids ) as $i => $v ) {
		$out .= 'i:' . $i . ';i:' . $v . ';';
	}
	minn_option_set( 'sticky_posts', $out . '}' );
}

/** Assign (or clear) the post-format term. */
function minn_set_post_format( int $id, string $format ): void {
	global $table_prefix;
	if ( '' === $format || 'standard' === $format ) {
		minn_set_object_terms( $id, 'post_format', array() );
		return;
	}
	$slug = 'post-format-' . $format;
	$stmt = minn_db()->prepare(
		"SELECT t.term_id FROM {$table_prefix}terms t
		 JOIN {$table_prefix}term_taxonomy tt ON tt.term_id = t.term_id
		 WHERE t.slug = ? AND tt.taxonomy = 'post_format' LIMIT 1"
	);
	$stmt->bind_param( 's', $slug );
	$stmt->execute();
	$row = $stmt->get_result()->fetch_row();
	if ( $row ) {
		$tid = (int) $row[0];
	} else {
		$stmt = minn_db()->prepare( "INSERT INTO {$table_prefix}terms (name, slug, term_group) VALUES (?, ?, 0)" );
		$stmt->bind_param( 'ss', $slug, $slug );
		$stmt->execute();
		$tid  = (int) minn_db()->insert_id;
		$stmt = minn_db()->prepare(
			"INSERT INTO {$table_prefix}term_taxonomy (term_id, taxonomy, description, parent, count) VALUES (?, 'post_format', '', 0, 0)"
		);
		$stmt->bind_param( 'i', $tid );
		$stmt->execute();
	}
	minn_set_object_terms( $id, 'post_format', array( $tid ) );
}

function minn_delete_post_meta( int $id, string $key ): void {
	global $table_prefix;
	$stmt = minn_db()->prepare( "DELETE FROM {$table_prefix}postmeta WHERE post_id = ? AND meta_key = ?" );
	$stmt->bind_param( 'is', $id, $key );
	$stmt->execute();
}

/** The post-row side effects shared by create and update. */
function minn_apply_extended_fields( int $id, array $body, int $uid, string $type ): void {
	if ( isset( $body['sticky'] ) && 'post' === $type ) {
		minn_write_sticky( $id, (bool) $body['sticky'] );
	}
	if ( isset( $body['format'] ) && 'post' === $type ) {
		minn_set_post_format( $id, (string) $body['format'] );
	}
	if ( isset( $body['featured_media'] ) ) {
		$media = (int) $body['featured_media'];
		if ( $media > 0 ) {
			minn_set_post_meta( $id, '_thumbnail_id', (string) $media );
		} else {
			minn_delete_post_meta( $id, '_thumbnail_id' );
		}
	}
	if ( isset( $body['meta']['footnotes'] ) ) {
		minn_set_post_meta( $id, 'footnotes', (string) $body['meta']['footnotes'] );
	}
}
