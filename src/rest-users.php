<?php
/**
 * wp/v2/users management: create, update, delete-with-reassign, and the
 * edit-context list/single the Users view drives.
 *
 * Implemented from oracle captures recorded in contracts/rest/users.md;
 * no WordPress source is used. Engine-created users carry real
 * WordPress-scheme password hashes and the full default meta set, so they
 * can sign in on either stack.
 */

/** A statusless core WP_Error as REST serves it: HTTP 500, data null. */
function minn_rest_bare_error( string $code, string $message ): void {
	minn_rest_headers();
	http_response_code( 500 );
	echo json_encode( array( 'code' => $code, 'message' => $message, 'data' => null ) );
	exit;
}

/** A WordPress-scheme password hash: "$wp" + bcrypt over the pre-hash. */
function minn_wp_hash_password( string $password ): string {
	$pre = base64_encode( hash_hmac( 'sha384', $password, 'wp-sha384', true ) );
	return '$wp' . password_hash( $pre, PASSWORD_BCRYPT, array( 'cost' => 10 ) );
}

/** role => wp_user_level, as stored beside the capabilities meta. */
function minn_role_level( string $role ): int {
	return array(
		'administrator' => 10,
		'editor'        => 7,
		'author'        => 2,
		'contributor'   => 1,
		'subscriber'    => 0,
	)[ $role ] ?? 0;
}

function minn_serialize_role( string $role ): string {
	return 'a:1:{s:' . strlen( $role ) . ':"' . $role . '";b:1;}';
}

function minn_usermeta_upsert( int $uid, string $key, string $value ): void {
	minn_usermeta_set( $uid, $key, $value );
}

/** The unique-nicename rule: sanitized login, -2, -3 … on collision. */
function minn_unique_nicename( string $base, int $skip_id = 0 ): string {
	global $table_prefix;
	$slug = minn_sanitize_slug( $base );
	$try  = $slug;
	$n    = 1;
	while ( true ) {
		$stmt = minn_db()->prepare(
			"SELECT ID FROM {$table_prefix}users WHERE user_nicename = ? AND ID != ? LIMIT 1"
		);
		$stmt->bind_param( 'si', $try, $skip_id );
		$stmt->execute();
		if ( ! $stmt->get_result()->fetch_row() ) {
			return $try;
		}
		$n++;
		$try = "$slug-$n";
	}
}

/** POST /wp/v2/users — gate create_users. */
function minn_rest_users_create(): void {
	global $table_prefix;
	$why  = '';
	$auth = minn_authenticate_rest( $why );
	if ( null === $auth ) {
		if ( 'rest_cookie_invalid_nonce' === $why ) {
			minn_rest_error( 'rest_cookie_invalid_nonce', 'Cookie check failed', 403 );
		}
		minn_rest_error( 'rest_cannot_create_user', 'Sorry, you are not allowed to create new users.', 401 );
	}
	$uid = (int) $auth[0]['ID'];
	if ( ! minn_user_can( $uid, 'create_users' ) ) {
		minn_rest_error( 'rest_cannot_create_user', 'Sorry, you are not allowed to create new users.', 403 );
	}
	$body = minn_request_body();

	$missing = array();
	foreach ( array( 'username', 'email', 'password' ) as $req ) {
		if ( ! isset( $body[ $req ] ) || '' === (string) $body[ $req ] ) {
			$missing[] = $req;
		}
	}
	if ( $missing ) {
		minn_rest_headers();
		http_response_code( 400 );
		echo json_encode(
			array(
				'code'    => 'rest_missing_callback_param',
				'message' => 'Missing parameter(s): ' . implode( ', ', $missing ),
				'data'    => array( 'status' => 400, 'params' => $missing ),
			)
		);
		exit;
	}

	// Duplicate identities surface as core's bare WP_Error: 500, data null.
	$login = (string) $body['username'];
	if ( minn_get_user_by_login( $login ) ) {
		minn_rest_bare_error( 'existing_user_login', 'Sorry, that username already exists!' );
	}
	$stmt = minn_db()->prepare( "SELECT ID FROM {$table_prefix}users WHERE user_email = ? LIMIT 1" );
	$email = (string) $body['email'];
	$stmt->bind_param( 's', $email );
	$stmt->execute();
	if ( $stmt->get_result()->fetch_row() ) {
		minn_rest_bare_error( 'existing_user_email', 'Sorry, that email address is already used!' );
	}

	$role     = (string) ( $body['roles'][0] ?? ( minn_option( 'default_role' ) ?? 'subscriber' ) );
	$nicename = minn_unique_nicename( $login );
	$display  = '' !== (string) ( $body['name'] ?? '' ) ? (string) $body['name'] : $login;
	$hash     = minn_wp_hash_password( (string) $body['password'] );
	$url      = (string) ( $body['url'] ?? '' );
	$now_gmt  = gmdate( 'Y-m-d H:i:s' );

	$stmt = minn_db()->prepare(
		"INSERT INTO {$table_prefix}users
		 (user_login, user_pass, user_nicename, user_email, user_url, user_registered,
		  user_activation_key, user_status, display_name)
		 VALUES (?, ?, ?, ?, ?, ?, '', 0, ?)"
	);
	$stmt->bind_param( 'sssssss', $login, $hash, $nicename, $email, $url, $now_gmt, $display );
	$stmt->execute();
	$new_id = (int) minn_db()->insert_id;

	// The default meta set WordPress writes on insert, in its order.
	foreach ( array(
		'nickname'                    => (string) ( $body['nickname'] ?? $login ),
		'first_name'                  => (string) ( $body['first_name'] ?? '' ),
		'last_name'                   => (string) ( $body['last_name'] ?? '' ),
		'description'                 => (string) ( $body['description'] ?? '' ),
		'rich_editing'                => 'true',
		'syntax_highlighting'         => 'true',
		'infinite_scrolling'          => 'true',
		'comment_shortcuts'           => 'false',
		'admin_color'                 => 'modern',
		'use_ssl'                     => '0',
		'show_admin_bar_front'        => 'true',
		'locale'                      => (string) ( $body['locale'] ?? '' ),
		"{$table_prefix}capabilities" => minn_serialize_role( $role ),
		"{$table_prefix}user_level"   => (string) minn_role_level( $role ),
	) as $key => $value ) {
		minn_usermeta_set( $new_id, $key, $value );
	}

	header( 'Location: ' . minn_rest_url( '/wp/v2/users/' . $new_id ) );
	minn_rest_send( minn_rest_user_object_edit( minn_get_user_by_id( $new_id ) ), 201 );
}

/** POST/PUT/PATCH /wp/v2/users/{id}. */
function minn_rest_users_update( int $id ): void {
	global $table_prefix;
	$uid = minn_current_user_id();
	$u   = minn_get_user_by_id( $id );
	if ( ! $u ) {
		minn_rest_error( 'rest_user_invalid_id', 'Invalid user ID.', 404 );
	}
	if ( $uid !== $id && ! minn_user_can( $uid, 'edit_users' ) ) {
		minn_rest_error( 'rest_cannot_edit', 'Sorry, you are not allowed to edit this user.', $uid ? 403 : 401 );
	}
	$body = minn_request_body();

	$columns = array();
	if ( isset( $body['name'] ) ) {
		$columns['display_name'] = (string) $body['name'];
	}
	if ( isset( $body['email'] ) ) {
		$columns['user_email'] = (string) $body['email'];
	}
	if ( isset( $body['url'] ) ) {
		$columns['user_url'] = (string) $body['url'];
	}
	if ( isset( $body['slug'] ) ) {
		$columns['user_nicename'] = minn_unique_nicename( (string) $body['slug'], $id );
	}
	if ( isset( $body['password'] ) && '' !== (string) $body['password'] ) {
		$columns['user_pass'] = minn_wp_hash_password( (string) $body['password'] );
	}
	foreach ( $columns as $column => $value ) {
		$stmt = minn_db()->prepare( "UPDATE {$table_prefix}users SET {$column} = ? WHERE ID = ?" );
		$stmt->bind_param( 'si', $value, $id );
		$stmt->execute();
	}
	foreach ( array( 'first_name', 'last_name', 'description', 'nickname', 'locale' ) as $metaf ) {
		if ( isset( $body[ $metaf ] ) ) {
			minn_usermeta_set( $id, $metaf, (string) $body[ $metaf ] );
		}
	}
	if ( isset( $body['meta']['show_admin_bar_front'] ) ) {
		minn_usermeta_set( $id, 'show_admin_bar_front', 'false' === $body['meta']['show_admin_bar_front'] ? 'false' : 'true' );
	}
	if ( isset( $body['roles'][0] ) ) {
		if ( ! minn_user_can( $uid, 'promote_users' ) ) {
			minn_rest_error( 'rest_cannot_edit_roles', 'Sorry, you are not allowed to edit roles of this user.', $uid ? 403 : 401 );
		}
		$role = (string) $body['roles'][0];
		minn_usermeta_set( $id, "{$table_prefix}capabilities", minn_serialize_role( $role ) );
		minn_usermeta_set( $id, "{$table_prefix}user_level", (string) minn_role_level( $role ) );
	}

	minn_rest_send( minn_rest_user_object_edit( minn_get_user_by_id( $id ) ) );
}

/** DELETE /wp/v2/users/{id}?force=true&reassign=N — reassign is REQUIRED. */
function minn_rest_users_delete( int $id ): void {
	global $table_prefix;
	$uid = minn_current_user_id();
	$u   = minn_get_user_by_id( $id );
	if ( ! isset( $_GET['reassign'] ) ) {
		minn_rest_headers();
		http_response_code( 400 );
		echo json_encode(
			array(
				'code'    => 'rest_missing_callback_param',
				'message' => 'Missing parameter(s): reassign',
				'data'    => array( 'status' => 400, 'params' => array( 'reassign' ) ),
			)
		);
		exit;
	}
	if ( ! $u ) {
		minn_rest_error( 'rest_user_invalid_id', 'Invalid user ID.', 404 );
	}
	if ( ! minn_user_can( $uid, 'delete_users' ) ) {
		minn_rest_error( 'rest_user_cannot_delete', 'Sorry, you are not allowed to delete this user.', $uid ? 403 : 401 );
	}
	if ( ! filter_var( $_GET['force'] ?? false, FILTER_VALIDATE_BOOLEAN ) ) {
		minn_rest_error( 'rest_trash_not_supported', "Users do not support trashing. Set 'force=true' to delete.", 501 );
	}
	$reassign = (string) $_GET['reassign'];
	$target   = 0;
	if ( '' !== $reassign && 'false' !== $reassign ) {
		$target = (int) $reassign;
		if ( ! minn_get_user_by_id( $target ) ) {
			minn_rest_error( 'rest_user_invalid_reassign', 'Invalid user ID for reassignment.', 400 );
		}
	}

	$previous = minn_rest_user_object_edit( $u );

	if ( $target > 0 ) {
		$stmt = minn_db()->prepare( "UPDATE {$table_prefix}posts SET post_author = ? WHERE post_author = ?" );
		$stmt->bind_param( 'ii', $target, $id );
		$stmt->execute();
	} else {
		// No reassignment: the user's posts are deleted, as core does.
		$stmt = minn_db()->prepare( "SELECT ID FROM {$table_prefix}posts WHERE post_author = ?" );
		$stmt->bind_param( 'i', $id );
		$stmt->execute();
		foreach ( $stmt->get_result()->fetch_all( MYSQLI_NUM ) as [ $pid ] ) {
			$pid = (int) $pid;
			foreach ( array( 'postmeta WHERE post_id', 'posts WHERE ID' ) as $where ) {
				$del = minn_db()->prepare( "DELETE FROM {$table_prefix}{$where} = ?" );
				$del->bind_param( 'i', $pid );
				$del->execute();
			}
		}
	}
	foreach ( array( 'usermeta WHERE user_id', 'users WHERE ID' ) as $where ) {
		$stmt = minn_db()->prepare( "DELETE FROM {$table_prefix}{$where} = ?" );
		$stmt->bind_param( 'i', $id );
		$stmt->execute();
	}

	minn_rest_send( array( 'deleted' => true, 'previous' => $previous ) );
}
