<?php
/**
 * The wp/v2/comments surface: list (status tabs, pagination headers),
 * single, create (reply), moderation updates, trash and force delete.
 *
 * Implemented from oracle captures recorded in contracts/rest/comments.md,
 * cross-checked live against the reference on the same database; no
 * WordPress source is used.
 */

/** DB status token → wp/v2 status string. */
function minn_comment_status_str( string $approved ): string {
	switch ( $approved ) {
		case '1':
			return 'approved';
		case '0':
			return 'hold';
		default:
			return $approved; // spam, trash
	}
}

/** wp/v2 status parameter → DB token(s); null = unknown status. */
function minn_comment_status_tokens( string $status ): ?array {
	switch ( $status ) {
		case 'approve':
		case 'approved':
			return array( '1' );
		case 'hold':
			return array( '0' );
		case 'all':
			return array( '1', '0' );
		case 'spam':
		case 'trash':
			return array( $status );
		default:
			return null;
	}
}

/**
 * Rendered comment text: texturize, then paragraphs on blank lines with
 * <br /> on single newlines — the captured comment_text pipeline for plain
 * prose. (make_clickable's bare-URL autolinking is a recorded gap.)
 */
function minn_comment_render( string $raw ): string {
	$text = minn_texturize( trim( $raw ) );
	if ( '' === $text ) {
		return '';
	}
	$paras = preg_split( '/\n\s*\n/', str_replace( "\r\n", "\n", $text ) );
	$out   = '';
	foreach ( $paras as $p ) {
		$out .= '<p>' . str_replace( "\n", "<br />\n", trim( $p ) ) . "</p>\n";
	}
	return $out;
}

function minn_comment_row( int $id ): ?array {
	global $table_prefix;
	$stmt = minn_db()->prepare( "SELECT * FROM {$table_prefix}comments WHERE comment_ID = ? LIMIT 1" );
	$stmt->bind_param( 'i', $id );
	$stmt->execute();
	return $stmt->get_result()->fetch_assoc() ?: null;
}

function minn_comment_meta_value( int $id, string $key ): ?string {
	global $table_prefix;
	$stmt = minn_db()->prepare(
		"SELECT meta_value FROM {$table_prefix}commentmeta WHERE comment_id = ? AND meta_key = ? LIMIT 1"
	);
	$stmt->bind_param( 'is', $id, $key );
	$stmt->execute();
	$row = $stmt->get_result()->fetch_row();
	return $row ? $row[0] : null;
}

/** One wp/v2 comment object; $edit adds the moderation-desk fields. */
function minn_rest_comment_object( array $c, bool $edit, int $viewer_uid ): array {
	global $table_prefix;
	$id      = (int) $c['comment_ID'];
	$post_id = (int) $c['comment_post_ID'];

	$obj = array(
		'id'          => $id,
		'post'        => $post_id,
		'parent'      => (int) $c['comment_parent'],
		'author'      => (int) $c['user_id'],
		'author_name' => $c['comment_author'],
	);
	if ( $edit ) {
		$obj['author_email'] = $c['comment_author_email'];
		$obj['author_url']   = $c['comment_author_url'];
		$obj['author_ip']    = $c['comment_author_IP'];
		$obj['author_user_agent'] = $c['comment_agent'];
	} else {
		$obj['author_url'] = $c['comment_author_url'];
	}
	$obj['date']     = str_replace( ' ', 'T', $c['comment_date'] );
	$obj['date_gmt'] = str_replace( ' ', 'T', $c['comment_date_gmt'] );
	$obj['content']  = $edit
		? array( 'rendered' => minn_comment_render( $c['comment_content'] ), 'raw' => $c['comment_content'] )
		: array( 'rendered' => minn_comment_render( $c['comment_content'] ) );
	$obj['link']     = minn_home_url( '/?p=' . $post_id . '#comment-' . $id );
	$obj['status']   = minn_comment_status_str( $c['comment_approved'] );
	$obj['type']     = '' === $c['comment_type'] ? 'comment' : $c['comment_type'];
	$obj['author_avatar_urls'] = minn_avatar_urls( $c['comment_author_email'] );
	$obj['meta']     = array( '_wp_note_status' => minn_comment_meta_value( $id, '_wp_note_status' ) );

	$self = array( 'href' => minn_rest_url( '/wp/v2/comments/' . $id ) );
	$self['targetHints'] = array(
		'allow' => minn_user_can( $viewer_uid, 'moderate_comments' )
			? array( 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' )
			: array( 'GET' ),
	);
	$links = array(
		'self'       => array( $self ),
		'collection' => array( array( 'href' => minn_rest_url( '/wp/v2/comments' ) ) ),
	);
	if ( (int) $c['user_id'] > 0 ) {
		$links['author'] = array(
			array( 'embeddable' => true, 'href' => minn_rest_url( '/wp/v2/users/' . (int) $c['user_id'] ) ),
		);
	}
	if ( $post_id > 0 ) {
		$stmt = minn_db()->prepare( "SELECT post_type FROM {$table_prefix}posts WHERE ID = ? LIMIT 1" );
		$stmt->bind_param( 'i', $post_id );
		$stmt->execute();
		$row  = $stmt->get_result()->fetch_assoc();
		$type = $row ? $row['post_type'] : 'post';
		$base = 'page' === $type ? 'pages' : 'posts';
		$links['up'] = array(
			array( 'embeddable' => true, 'post_type' => $type, 'href' => minn_rest_url( '/wp/v2/' . $base . '/' . $post_id ) ),
		);
	}
	if ( (int) $c['comment_parent'] > 0 ) {
		$links['in-reply-to'] = array(
			array( 'embeddable' => true, 'href' => minn_rest_url( '/wp/v2/comments/' . (int) $c['comment_parent'] ) ),
		);
	}
	$obj['_links'] = $links;
	return $obj;
}

/** Recompute a post's stored comment_count (approved comments only). */
function minn_comment_recount( int $post_id ): void {
	global $table_prefix;
	$stmt = minn_db()->prepare(
		"UPDATE {$table_prefix}posts SET comment_count =
		 (SELECT COUNT(*) FROM {$table_prefix}comments WHERE comment_post_ID = ? AND comment_approved = '1')
		 WHERE ID = ?"
	);
	$stmt->bind_param( 'ii', $post_id, $post_id );
	$stmt->execute();
}

/** GET /wp/v2/comments */
function minn_rest_comments_list(): void {
	global $table_prefix;
	$uid     = minn_current_user_id();
	$context = ( $_GET['context'] ?? 'view' ) === 'edit' ? 'edit' : 'view';
	$status  = (string) ( $_GET['status'] ?? 'approve' );

	if ( 'edit' === $context && ! minn_user_can( $uid, 'moderate_comments' ) ) {
		minn_rest_error(
			'rest_forbidden_context',
			'Sorry, you are not allowed to edit comments.',
			$uid ? 403 : 401
		);
	}
	if ( 'approve' !== $status && ! minn_user_can( $uid, 'edit_posts' ) ) {
		minn_rest_error(
			'rest_forbidden_param',
			'Query parameter not permitted: status',
			$uid ? 403 : 401
		);
	}
	$tokens = minn_comment_status_tokens( $status );
	if ( null === $tokens ) {
		$tokens = array( $status ); // an unknown status simply matches nothing
	}

	$per_page = max( 1, min( 100, (int) ( $_GET['per_page'] ?? 10 ) ) );
	$page     = max( 1, (int) ( $_GET['page'] ?? 1 ) );
	$offset   = ( $page - 1 ) * $per_page;

	$in   = implode( ',', array_fill( 0, count( $tokens ), '?' ) );
	$args = $tokens;
	$sql  = "FROM {$table_prefix}comments WHERE comment_approved IN ($in) AND comment_type IN ('', 'comment')";

	$stmt = minn_db()->prepare( "SELECT COUNT(*) $sql" );
	$stmt->bind_param( str_repeat( 's', count( $args ) ), ...$args );
	$stmt->execute();
	$total = (int) $stmt->get_result()->fetch_row()[0];

	$stmt = minn_db()->prepare( "SELECT * $sql ORDER BY comment_date_gmt DESC LIMIT ? OFFSET ?" );
	$types = str_repeat( 's', count( $args ) ) . 'ii';
	$stmt->bind_param( $types, ...array_merge( $args, array( $per_page, $offset ) ) );
	$stmt->execute();
	$rows = $stmt->get_result()->fetch_all( MYSQLI_ASSOC );

	$edit = 'edit' === $context;
	$out  = array_map( static fn( $c ) => minn_rest_comment_object( $c, $edit, $uid ), $rows );

	header( 'X-WP-Total: ' . $total );
	header( 'X-WP-TotalPages: ' . (int) ceil( $total / $per_page ) );
	minn_rest_send( $out, 200, true );
}

/** GET /wp/v2/comments/{id} */
function minn_rest_comments_single( int $id ): void {
	$uid     = minn_current_user_id();
	$context = ( $_GET['context'] ?? 'view' ) === 'edit' ? 'edit' : 'view';
	$c       = minn_comment_row( $id );
	if ( ! $c || ! in_array( $c['comment_type'], array( '', 'comment' ), true ) ) {
		minn_rest_error( 'rest_comment_invalid_id', 'Invalid comment ID.', 404 );
	}
	$moderator = minn_user_can( $uid, 'moderate_comments' );
	if ( '1' !== $c['comment_approved'] && ! $moderator ) {
		minn_rest_error( 'rest_cannot_read', 'Sorry, you are not allowed to read this comment.', $uid ? 403 : 401 );
	}
	if ( 'edit' === $context && ! $moderator ) {
		minn_rest_error( 'rest_forbidden_context', 'Sorry, you are not allowed to edit comments.', $uid ? 403 : 401 );
	}
	minn_rest_send( minn_rest_comment_object( $c, 'edit' === $context, $uid ) );
}

/** POST /wp/v2/comments — a signed-in reply; fields come from the user. */
function minn_rest_comments_create(): void {
	global $table_prefix;
	$why  = '';
	$auth = minn_authenticate_rest( $why );
	if ( null === $auth ) {
		if ( 'rest_cookie_invalid_nonce' === $why ) {
			minn_rest_error( 'rest_cookie_invalid_nonce', 'Cookie check failed', 403 );
		}
		minn_rest_error( 'rest_comment_login_required', 'Sorry, you must be logged in to comment.', 401 );
	}
	$user = $auth[0];
	$uid  = (int) $user['ID'];
	$body = minn_request_body();

	$post_id = (int) ( $body['post'] ?? 0 );
	$content = (string) ( $body['content'] ?? ( is_array( $body['content'] ?? null ) ? '' : '' ) );
	if ( is_array( $body['content'] ?? null ) ) {
		$content = (string) ( $body['content']['raw'] ?? '' );
	}
	$parent = (int) ( $body['parent'] ?? 0 );

	$stmt = minn_db()->prepare( "SELECT ID, comment_status FROM {$table_prefix}posts WHERE ID = ? LIMIT 1" );
	$stmt->bind_param( 'i', $post_id );
	$stmt->execute();
	$post = $stmt->get_result()->fetch_assoc();
	if ( ! $post ) {
		minn_rest_error( 'rest_comment_invalid_post_id', 'Sorry, you are not allowed to create this comment without a post.', 403 );
	}
	if ( '' === trim( $content ) ) {
		minn_rest_error( 'rest_comment_content_invalid', 'Invalid comment content.', 400 );
	}

	$now_gmt = gmdate( 'Y-m-d H:i:s' );
	$now     = gmdate( 'Y-m-d H:i:s', time() + minn_gmt_offset() );
	// A signed-in author with moderation rights self-approves; everyone else
	// lands in the queue (the previously-approved shortcut is a recorded gap).
	$approved = minn_user_can( $uid, 'moderate_comments' ) ? '1' : '0';
	$ip       = (string) ( $_SERVER['REMOTE_ADDR'] ?? '' );
	$agent    = substr( (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' ), 0, 254 );
	$email    = $user['user_email'];
	$name     = $user['display_name'];
	$url      = $user['user_url'];

	$stmt = minn_db()->prepare(
		"INSERT INTO {$table_prefix}comments
		 (comment_post_ID, comment_author, comment_author_email, comment_author_url, comment_author_IP,
		  comment_date, comment_date_gmt, comment_content, comment_karma, comment_approved, comment_agent,
		  comment_type, comment_parent, user_id)
		 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, 'comment', ?, ?)"
	);
	$stmt->bind_param( 'isssssssssii', $post_id, $name, $email, $url, $ip, $now, $now_gmt, $content, $approved, $agent, $parent, $uid );
	$stmt->execute();
	$id = (int) minn_db()->insert_id;
	if ( '1' === $approved ) {
		minn_comment_recount( $post_id );
	}

	header( 'Location: ' . minn_rest_url( '/wp/v2/comments/' . $id ) );
	minn_rest_send( minn_rest_comment_object( minn_comment_row( $id ), true, $uid ), 201 );
}

/** POST/PUT/PATCH /wp/v2/comments/{id} — status flips and content edits. */
function minn_rest_comments_update( int $id ): void {
	global $table_prefix;
	$uid = minn_current_user_id();
	$c   = minn_comment_row( $id );
	if ( ! $c || ! in_array( $c['comment_type'], array( '', 'comment' ), true ) ) {
		minn_rest_error( 'rest_comment_invalid_id', 'Invalid comment ID.', 404 );
	}
	if ( ! minn_user_can( $uid, 'moderate_comments' ) ) {
		minn_rest_error( 'rest_cannot_edit', 'Sorry, you are not allowed to edit this comment.', $uid ? 403 : 401 );
	}
	$body = minn_request_body();

	if ( isset( $body['status'] ) ) {
		$tokens = minn_comment_status_tokens( (string) $body['status'] );
		if ( null === $tokens || count( $tokens ) > 1 ) {
			minn_rest_error( 'rest_invalid_param', 'Invalid parameter(s): status', 400 );
		}
		$token = $tokens[0];
		$stmt  = minn_db()->prepare( "UPDATE {$table_prefix}comments SET comment_approved = ? WHERE comment_ID = ?" );
		$stmt->bind_param( 'si', $token, $id );
		$stmt->execute();
		minn_comment_recount( (int) $c['comment_post_ID'] );
	}
	if ( isset( $body['content'] ) ) {
		$content = is_array( $body['content'] ) ? (string) ( $body['content']['raw'] ?? '' ) : (string) $body['content'];
		$stmt    = minn_db()->prepare( "UPDATE {$table_prefix}comments SET comment_content = ? WHERE comment_ID = ?" );
		$stmt->bind_param( 'si', $content, $id );
		$stmt->execute();
	}
	foreach ( array( 'author_name' => 'comment_author', 'author_email' => 'comment_author_email', 'author_url' => 'comment_author_url' ) as $field => $column ) {
		if ( isset( $body[ $field ] ) ) {
			$value = (string) $body[ $field ];
			$stmt  = minn_db()->prepare( "UPDATE {$table_prefix}comments SET {$column} = ? WHERE comment_ID = ?" );
			$stmt->bind_param( 'si', $value, $id );
			$stmt->execute();
		}
	}

	minn_rest_send( minn_rest_comment_object( minn_comment_row( $id ), true, $uid ) );
}

/** DELETE /wp/v2/comments/{id}[?force=true] */
function minn_rest_comments_delete( int $id, bool $force ): void {
	global $table_prefix;
	$uid = minn_current_user_id();
	$c   = minn_comment_row( $id );
	if ( ! $c || ! in_array( $c['comment_type'], array( '', 'comment' ), true ) ) {
		minn_rest_error( 'rest_comment_invalid_id', 'Invalid comment ID.', 404 );
	}
	if ( ! minn_user_can( $uid, 'moderate_comments' ) ) {
		minn_rest_error( 'rest_cannot_delete', 'Sorry, you are not allowed to delete this comment.', $uid ? 403 : 401 );
	}
	$post_id = (int) $c['comment_post_ID'];

	if ( $force ) {
		$previous = minn_rest_comment_object( $c, true, $uid );
		$stmt     = minn_db()->prepare( "DELETE FROM {$table_prefix}comments WHERE comment_ID = ?" );
		$stmt->bind_param( 'i', $id );
		$stmt->execute();
		$stmt = minn_db()->prepare( "DELETE FROM {$table_prefix}commentmeta WHERE comment_id = ?" );
		$stmt->bind_param( 'i', $id );
		$stmt->execute();
		minn_comment_recount( $post_id );
		minn_rest_send( array( 'deleted' => true, 'previous' => $previous ) );
	}

	if ( 'trash' === $c['comment_approved'] ) {
		minn_rest_error( 'rest_already_trashed', 'The comment has already been trashed.', 410 );
	}
	// Trash remembers where the comment came from, exactly like core.
	foreach ( array(
		'_wp_trash_meta_status' => $c['comment_approved'],
		'_wp_trash_meta_time'   => (string) time(),
	) as $key => $value ) {
		$stmt = minn_db()->prepare(
			"INSERT INTO {$table_prefix}commentmeta (comment_id, meta_key, meta_value) VALUES (?, ?, ?)"
		);
		$stmt->bind_param( 'iss', $id, $key, $value );
		$stmt->execute();
	}
	$stmt = minn_db()->prepare( "UPDATE {$table_prefix}comments SET comment_approved = 'trash' WHERE comment_ID = ?" );
	$stmt->bind_param( 'i', $id );
	$stmt->execute();
	minn_comment_recount( $post_id );
	minn_rest_send( minn_rest_comment_object( minn_comment_row( $id ), true, $uid ) );
}
