<?php
/**
 * Minn Engine capabilities — roles and the meta-cap mapping.
 *
 * Roles come from the site's own {prefix}user_roles option (seeded into
 * src/data/roles.json as a fallback for a fresh install); a user's roles
 * come from {prefix}capabilities usermeta. The primitive-vs-meta split and
 * the edit_post/delete_post/read_post mapping are implemented from the
 * behavior matrix in contracts/rest/caps.md, cross-checked against a live
 * reference. No WordPress source is used.
 */

/** Role definitions: {role: {name, capabilities:{cap:bool}}}. */
function minn_roles(): array {
	static $roles = null;
	if ( null === $roles ) {
		global $table_prefix;
		$opt = minn_option( $table_prefix . 'user_roles' );
		// The option is a serialized array; if a tolerant parse is not worth
		// it here, fall back to the seeded contract copy. Roles change rarely
		// and are validated against the reference by the suite.
		$roles = minn_parse_roles_option( $opt )
			?? json_decode( (string) file_get_contents( __DIR__ . '/data/roles.json' ), true );
	}
	return $roles;
}

/**
 * Parse the serialized {prefix}user_roles option without unserialize().
 * Returns null when the shape is unexpected so the caller can fall back.
 * Structure: a:N:{s:L:"role";a:2:{s:4:"name";s:..;s:12:"capabilities";a:M:{s:..:"cap";b:1;...}}}
 */
function minn_parse_roles_option( ?string $blob ): ?array {
	if ( null === $blob || '' === $blob || 'a:' !== substr( $blob, 0, 2 ) ) {
		return null;
	}
	// Role names are top-level string keys whose value is a 2-entry array
	// carrying "name" and "capabilities". Walk role blocks by locating each
	// capabilities sub-array and the role slug that precedes it.
	if ( ! preg_match_all(
		'/s:\d+:"([a-z0-9_-]+)";a:2:\{s:4:"name";s:\d+:"(.*?)";s:12:"capabilities";a:\d+:\{(.*?)\}\}/s',
		$blob,
		$m,
		PREG_SET_ORDER
	) ) {
		return null;
	}
	$roles = array();
	foreach ( $m as $r ) {
		$caps = array();
		if ( preg_match_all( '/s:\d+:"([^"]+)";b:([01]);/', $r[3], $cm, PREG_SET_ORDER ) ) {
			foreach ( $cm as $c ) {
				$caps[ $c[1] ] = '1' === $c[2];
			}
		}
		$roles[ $r[1] ] = array( 'name' => $r[2], 'capabilities' => $caps );
	}
	return $roles ?: null;
}

/** A user's role slugs, from {prefix}capabilities usermeta. */
function minn_user_roles( int $uid ): array {
	global $table_prefix;
	$blob = minn_user_meta( $uid, $table_prefix . 'capabilities' );
	if ( null === $blob || '' === $blob ) {
		return array();
	}
	// {role: true} map; pull the string keys whose value is b:1.
	$roles = array();
	if ( preg_match_all( '/s:\d+:"([^"]+)";b:1;/', $blob, $m ) ) {
		$roles = $m[1];
	}
	return $roles;
}

/** The union of primitive capabilities a user holds via their roles. */
function minn_user_capabilities( int $uid ): array {
	$all   = minn_roles();
	$caps  = array();
	foreach ( minn_user_roles( $uid ) as $role ) {
		if ( isset( $all[ $role ]['capabilities'] ) ) {
			foreach ( $all[ $role ]['capabilities'] as $cap => $granted ) {
				if ( $granted ) {
					$caps[ $cap ] = true;
				}
			}
		}
	}
	return $caps;
}

/**
 * Map a meta capability to the primitive capabilities actually required.
 * Returns a list of primitive caps that must ALL be held. Mirrors the
 * subset of map_meta_cap the engine needs: edit_post, delete_post,
 * read_post, and the pass-through for primitives.
 */
function minn_map_meta_cap( string $cap, int $uid, ?int $post_id = null ): array {
	switch ( $cap ) {
		case 'edit_post':
		case 'delete_post':
		case 'read_post':
			if ( null === $post_id ) {
				// Called incorrectly; deny by requiring an impossible cap.
				return array( 'do_not_allow' );
			}
			return minn_map_post_cap( $cap, $uid, $post_id );
		case 'edit_page':
			return minn_map_post_cap( 'edit_post', $uid, (int) $post_id );
		case 'delete_page':
			return minn_map_post_cap( 'delete_post', $uid, (int) $post_id );
		default:
			// Primitive capability (or an unmapped meta cap): require it as-is.
			return array( $cap );
	}
}

/** The post-specific branch of map_meta_cap for edit/delete/read. */
function minn_map_post_cap( string $cap, int $uid, int $post_id ): array {
	global $table_prefix;
	$stmt = minn_db()->prepare(
		"SELECT post_author, post_status, post_type FROM {$table_prefix}posts WHERE ID = ? LIMIT 1"
	);
	$stmt->bind_param( 'i', $post_id );
	$stmt->execute();
	$post = $stmt->get_result()->fetch_assoc();
	if ( ! $post ) {
		return array( 'do_not_allow' );
	}

	$is_author  = (int) $post['post_author'] === $uid;
	$status     = $post['post_status'];
	$type       = 'page' === $post['post_type'] ? 'page' : 'post';
	$plural     = $type . 's';
	$published  = in_array( $status, array( 'publish', 'future', 'private' ), true );

	if ( 'read_post' === $cap ) {
		if ( 'publish' === $status ) {
			return array( 'read' );
		}
		return $is_author ? array( 'read' ) : array( "read_private_{$plural}" );
	}

	$verb = 'edit_post' === $cap ? 'edit' : 'delete';
	$required = array();
	if ( $is_author ) {
		$required[] = "{$verb}_{$plural}";
		if ( $published ) {
			$required[] = "{$verb}_published_{$plural}";
		} elseif ( 'private' === $status ) {
			$required[] = "{$verb}_private_{$plural}";
		}
	} else {
		$required[] = "{$verb}_others_{$plural}";
		if ( $published ) {
			$required[] = "{$verb}_published_{$plural}";
		}
		if ( 'private' === $status ) {
			$required[] = "{$verb}_private_{$plural}";
		}
	}
	return $required;
}

/**
 * The engine's current_user_can: does the user hold every primitive the
 * (possibly meta) capability maps to?
 */
function minn_user_can( int $uid, string $cap, ?int $post_id = null ): bool {
	if ( 0 === $uid ) {
		return false;
	}
	$primitives = minn_map_meta_cap( $cap, $uid, $post_id );
	$held       = minn_user_capabilities( $uid );
	foreach ( $primitives as $need ) {
		if ( 'do_not_allow' === $need || empty( $held[ $need ] ) ) {
			return false;
		}
	}
	return ! empty( $primitives );
}
