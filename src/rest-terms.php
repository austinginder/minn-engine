<?php
/**
 * Term management for wp/v2/categories and wp/v2/tags: create, update,
 * force delete — the editor's tag-on-the-fly flow and the taxonomy admin.
 *
 * Implemented from oracle captures recorded in contracts/rest/terms.md;
 * no WordPress source is used.
 */

function minn_term_row( int $id, string $taxonomy ): ?array {
	global $table_prefix;
	$stmt = minn_db()->prepare(
		"SELECT t.term_id, t.name, t.slug, tt.term_taxonomy_id, tt.description, tt.count, tt.parent
		 FROM {$table_prefix}terms t
		 JOIN {$table_prefix}term_taxonomy tt ON tt.term_id = t.term_id
		 WHERE t.term_id = ? AND tt.taxonomy = ? LIMIT 1"
	);
	$stmt->bind_param( 'is', $id, $taxonomy );
	$stmt->execute();
	return $stmt->get_result()->fetch_assoc() ?: null;
}

/** Unique term slug within the taxonomy: base, -2, -3 … */
function minn_unique_term_slug( string $base, string $taxonomy, int $skip_id = 0 ): string {
	global $table_prefix;
	$slug = minn_sanitize_slug( $base );
	$try  = $slug;
	$n    = 1;
	while ( true ) {
		$stmt = minn_db()->prepare(
			"SELECT t.term_id FROM {$table_prefix}terms t
			 JOIN {$table_prefix}term_taxonomy tt ON tt.term_id = t.term_id
			 WHERE t.slug = ? AND tt.taxonomy = ? AND t.term_id != ? LIMIT 1"
		);
		$stmt->bind_param( 'ssi', $try, $taxonomy, $skip_id );
		$stmt->execute();
		if ( ! $stmt->get_result()->fetch_row() ) {
			return $try;
		}
		$n++;
		$try = "$slug-$n";
	}
}

/** The create gate differs by taxonomy: tags open to edit_posts holders. */
function minn_term_can_create( int $uid, string $taxonomy ): bool {
	return 'post_tag' === $taxonomy
		? minn_user_can( $uid, 'edit_posts' )
		: minn_user_can( $uid, 'manage_categories' );
}

/** POST /wp/v2/{categories,tags} */
function minn_rest_terms_create( string $rest_base ): void {
	global $table_prefix;
	$cfg  = minn_taxonomy_config( $rest_base );
	$why  = '';
	$auth = minn_authenticate_rest( $why );
	if ( null === $auth ) {
		if ( 'rest_cookie_invalid_nonce' === $why ) {
			minn_rest_error( 'rest_cookie_invalid_nonce', 'Cookie check failed', 403 );
		}
		minn_rest_error( 'rest_cannot_create', 'Sorry, you are not allowed to create terms in this taxonomy.', 401 );
	}
	$uid = (int) $auth[0]['ID'];
	if ( ! minn_term_can_create( $uid, $cfg['taxonomy'] ) ) {
		minn_rest_error( 'rest_cannot_create', 'Sorry, you are not allowed to create terms in this taxonomy.', 403 );
	}
	$body = minn_request_body();
	$name = trim( (string) ( $body['name'] ?? '' ) );
	if ( '' === $name ) {
		minn_rest_headers();
		http_response_code( 400 );
		echo json_encode(
			array(
				'code'    => 'rest_missing_callback_param',
				'message' => 'Missing parameter(s): name',
				'data'    => array( 'status' => 400, 'params' => array( 'name' ) ),
			)
		);
		exit;
	}

	// Same-name term in the taxonomy: core's term_exists refusal, which
	// hands the existing id back in data AND an additional_data list.
	$stmt = minn_db()->prepare(
		"SELECT t.term_id FROM {$table_prefix}terms t
		 JOIN {$table_prefix}term_taxonomy tt ON tt.term_id = t.term_id
		 WHERE t.name = ? AND tt.taxonomy = ? LIMIT 1"
	);
	$stmt->bind_param( 'ss', $name, $cfg['taxonomy'] );
	$stmt->execute();
	$existing = $stmt->get_result()->fetch_row();
	if ( $existing ) {
		$eid = (int) $existing[0];
		minn_rest_headers();
		http_response_code( 400 );
		echo json_encode(
			array(
				'code'            => 'term_exists',
				'message'         => 'A term with the name provided already exists in this taxonomy.',
				'data'            => array( 'status' => 400, 'term_id' => $eid ),
				'additional_data' => array( $eid, $eid ),
			)
		);
		exit;
	}

	$slug        = minn_unique_term_slug( '' !== (string) ( $body['slug'] ?? '' ) ? (string) $body['slug'] : $name, $cfg['taxonomy'] );
	$description = (string) ( $body['description'] ?? '' );
	$parent      = $cfg['has_parent'] ? (int) ( $body['parent'] ?? 0 ) : 0;

	$stmt = minn_db()->prepare( "INSERT INTO {$table_prefix}terms (name, slug, term_group) VALUES (?, ?, 0)" );
	$stmt->bind_param( 'ss', $name, $slug );
	$stmt->execute();
	$term_id = (int) minn_db()->insert_id;

	$stmt = minn_db()->prepare(
		"INSERT INTO {$table_prefix}term_taxonomy (term_id, taxonomy, description, parent, count) VALUES (?, ?, ?, ?, 0)"
	);
	$stmt->bind_param( 'issi', $term_id, $cfg['taxonomy'], $description, $parent );
	$stmt->execute();

	header( 'Location: ' . minn_rest_url( '/wp/v2/' . $rest_base . '/' . $term_id ) );
	minn_rest_send( minn_rest_term_object( minn_term_row( $term_id, $cfg['taxonomy'] ), $rest_base ), 201 );
}

/** POST/PUT/PATCH /wp/v2/{categories,tags}/{id} */
function minn_rest_terms_update( string $rest_base, int $id ): void {
	global $table_prefix;
	$cfg = minn_taxonomy_config( $rest_base );
	$uid = minn_current_user_id();
	$t   = minn_term_row( $id, $cfg['taxonomy'] );
	if ( ! $t ) {
		minn_rest_error( 'rest_term_invalid', 'Term does not exist.', 404 );
	}
	if ( ! minn_user_can( $uid, 'manage_categories' ) ) {
		minn_rest_error( 'rest_cannot_update', 'Sorry, you are not allowed to edit this term.', $uid ? 403 : 401 );
	}
	$body = minn_request_body();

	if ( isset( $body['name'] ) || isset( $body['slug'] ) ) {
		$name = isset( $body['name'] ) ? (string) $body['name'] : $t['name'];
		$slug = isset( $body['slug'] ) ? minn_unique_term_slug( (string) $body['slug'], $cfg['taxonomy'], $id ) : $t['slug'];
		$stmt = minn_db()->prepare( "UPDATE {$table_prefix}terms SET name = ?, slug = ? WHERE term_id = ?" );
		$stmt->bind_param( 'ssi', $name, $slug, $id );
		$stmt->execute();
	}
	if ( isset( $body['description'] ) || ( $cfg['has_parent'] && isset( $body['parent'] ) ) ) {
		$description = isset( $body['description'] ) ? (string) $body['description'] : $t['description'];
		$parent      = $cfg['has_parent'] && isset( $body['parent'] ) ? (int) $body['parent'] : (int) $t['parent'];
		$stmt        = minn_db()->prepare(
			"UPDATE {$table_prefix}term_taxonomy SET description = ?, parent = ? WHERE term_id = ? AND taxonomy = ?"
		);
		$stmt->bind_param( 'siis', $description, $parent, $id, $cfg['taxonomy'] );
		$stmt->execute();
	}

	minn_rest_send( minn_rest_term_object( minn_term_row( $id, $cfg['taxonomy'] ), $rest_base ) );
}

/** DELETE /wp/v2/{categories,tags}/{id}?force=true */
function minn_rest_terms_delete( string $rest_base, int $id, bool $force ): void {
	global $table_prefix;
	$cfg = minn_taxonomy_config( $rest_base );
	$uid = minn_current_user_id();
	$t   = minn_term_row( $id, $cfg['taxonomy'] );
	if ( ! $t ) {
		minn_rest_error( 'rest_term_invalid', 'Term does not exist.', 404 );
	}
	if ( ! minn_user_can( $uid, 'manage_categories' ) ) {
		minn_rest_error( 'rest_cannot_delete', 'Sorry, you are not allowed to delete this term.', $uid ? 403 : 401 );
	}
	// The default category is capability-denied before the force check.
	if ( 'category' === $cfg['taxonomy'] && $id === (int) ( minn_option( 'default_category' ) ?? 0 ) ) {
		minn_rest_error( 'rest_cannot_delete', 'Sorry, you are not allowed to delete this term.', 403 );
	}
	if ( ! $force ) {
		minn_rest_error( 'rest_trash_not_supported', "Terms do not support trashing. Set 'force=true' to delete.", 501 );
	}

	$previous = minn_rest_term_object( $t, $rest_base );
	$ttid     = (int) $t['term_taxonomy_id'];

	// Reparent children, detach relationships, then drop the term rows.
	if ( $cfg['has_parent'] ) {
		$stmt = minn_db()->prepare(
			"UPDATE {$table_prefix}term_taxonomy SET parent = ? WHERE parent = ? AND taxonomy = ?"
		);
		$grand = (int) $t['parent'];
		$stmt->bind_param( 'iis', $grand, $id, $cfg['taxonomy'] );
		$stmt->execute();
	}
	foreach ( array(
		array( "DELETE FROM {$table_prefix}term_relationships WHERE term_taxonomy_id = ?", $ttid ),
		array( "DELETE FROM {$table_prefix}term_taxonomy WHERE term_taxonomy_id = ?", $ttid ),
		array( "DELETE FROM {$table_prefix}terms WHERE term_id = ?", $id ),
	) as [ $sql, $bind ] ) {
		$stmt = minn_db()->prepare( $sql );
		$stmt->bind_param( 'i', $bind );
		$stmt->execute();
	}

	minn_rest_send( array( 'deleted' => true, 'previous' => $previous ) );
}
