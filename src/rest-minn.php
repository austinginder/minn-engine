<?php
/**
 * The minn-admin/v1 namespace, first slice: the dashboard endpoints.
 *
 *   GET  /minn-admin/v1/overview?days=N   stats cards, activity chart, recent activity
 *   GET  /minn-admin/v1/notifications     the bell feed
 *   POST /minn-admin/v1/notifications/read  mark one (body {id}) or all (body {}) read
 *
 * Implemented from oracle captures recorded in contracts/rest/minn-admin-v1.md,
 * cross-checked live against the reference running the real Minn Admin plugin;
 * no WordPress or plugin source is used.
 */

function minn_v1_dispatch( string $route, string $method ): void {
	minn_v1_editor_dispatch( $route, $method );
	if ( '/minn-admin/v1/overview' === $route && 'GET' === $method ) {
		minn_v1_overview();
	}
	if ( '/minn-admin/v1/overview/activity' === $route && 'GET' === $method ) {
		minn_v1_overview_activity();
	}
	if ( '/minn-admin/v1/notifications' === $route && 'GET' === $method ) {
		minn_v1_notifications();
	}
	if ( '/minn-admin/v1/notifications/read' === $route && 'POST' === $method ) {
		minn_v1_notifications_read();
	}
	if ( '/minn-admin/v1/core' === $route && 'GET' === $method ) {
		minn_v1_core();
	}
	if ( '/minn-admin/v1/boot-status' === $route && 'GET' === $method ) {
		minn_v1_boot_status();
	}
}

/** Auth + capability floor shared by every dashboard endpoint. */
function minn_v1_require( string $cap = 'edit_posts' ): array {
	$why  = '';
	$auth = minn_authenticate_rest( $why );
	if ( null === $auth ) {
		if ( 'rest_cookie_invalid_nonce' === $why ) {
			minn_rest_error( 'rest_cookie_invalid_nonce', 'Cookie check failed', 403 );
		}
		minn_rest_error( 'rest_forbidden', 'Sorry, you are not allowed to do that.', 401 );
	}
	$uid = (int) $auth[0]['ID'];
	if ( ! minn_user_can( $uid, $cap ) ) {
		minn_rest_error( 'rest_forbidden', 'Sorry, you are not allowed to do that.', 403 );
	}
	return array( $auth[0], $uid );
}

/** Reject a request whose ?days is not an in-range integer. */
function minn_v1_validate_days(): int {
	if ( ! isset( $_GET['days'] ) || '' === $_GET['days'] ) {
		return 30;
	}
	$raw = (string) $_GET['days'];
	if ( ! is_numeric( $raw ) || (float) $raw !== (float) (int) $raw ) {
		minn_v1_param_error( 'days', 'rest_invalid_type', 'days is not of type integer.', array( 'param' => 'days' ) );
	}
	$days = (int) $raw;
	if ( $days < 7 || $days > 90 ) {
		minn_v1_param_error( 'days', 'rest_out_of_bounds', 'days must be between 7 (inclusive) and 90 (inclusive)', null );
	}
	return $days;
}

function minn_v1_param_error( string $param, string $code, string $message, $data ): void {
	minn_rest_headers();
	http_response_code( 400 );
	echo json_encode(
		array(
			'code'    => 'rest_invalid_param',
			'message' => 'Invalid parameter(s): ' . $param,
			'data'    => array(
				'status'  => 400,
				'params'  => array( $param => $message ),
				'details' => array(
					$param => array(
						'code'    => $code,
						'message' => $message,
						'data'    => $data,
					),
				),
			),
		)
	);
	exit;
}

/** Site GMT offset in seconds (the gmt_offset option; may be fractional hours). */
function minn_gmt_offset(): int {
	return (int) round( ( (float) ( minn_option( 'gmt_offset' ) ?? 0 ) ) * 3600 );
}

/** number_format_i18n for the default locale: comma thousands. */
function minn_number_i18n( $n, int $decimals = 0 ): string {
	return number_format( (float) $n, $decimals );
}

/** size_format with one decimal: 1024-step units, comma thousands. */
function minn_size_format( int $bytes ): string {
	foreach ( array( 'TB' => 2 ** 40, 'GB' => 2 ** 30, 'MB' => 2 ** 20, 'KB' => 2 ** 10 ) as $unit => $mag ) {
		if ( $bytes >= $mag ) {
			return minn_number_i18n( $bytes / $mag, 1 ) . ' ' . $unit;
		}
	}
	return minn_number_i18n( $bytes, 1 ) . ' B';
}

/**
 * Humanized age. Behavior pinned by an oracle capture table
 * (contracts/rest/minn-admin-v1.md): each unit rounds, floors at 1, and
 * hands off at the next unit boundary (60s / 3600s / 1d / 1w / 30d / 365d).
 */
function minn_human_time_diff( int $from, ?int $to = null ): string {
	$to   = $to ?? time();
	$diff = max( $to - $from, 1 );
	$units = array(
		array( 60, 1, 'second', 'seconds' ),
		array( 3600, 60, 'minute', 'minutes' ),
		array( 86400, 3600, 'hour', 'hours' ),
		array( 604800, 86400, 'day', 'days' ),
		array( 30 * 86400, 604800, 'week', 'weeks' ),
		array( 365 * 86400, 30 * 86400, 'month', 'months' ),
		array( PHP_INT_MAX, 365 * 86400, 'year', 'years' ),
	);
	foreach ( $units as [ $limit, $div, $one, $many ] ) {
		if ( $diff < $limit ) {
			$n = 60 === $limit ? $diff : max( 1, (int) round( $diff / $div ) );
			return $n . ' ' . ( 1 === $n ? $one : $many );
		}
	}
	return '';
}

/** Post title as the feed shows it: texturized, entities decoded, tags gone. */
function minn_v1_plain_title( string $raw ): string {
	$t = html_entity_decode( strip_tags( minn_texturize( $raw ) ), ENT_QUOTES, 'UTF-8' );
	return '' === trim( $t ) ? '(no title)' : $t;
}

/** Uploads footprint: the plugin's cached transient when fresh, else a walk. */
function minn_uploads_size(): int {
	$cached  = minn_option( '_transient_minn_admin_uploads_size' );
	$timeout = minn_option( '_transient_timeout_minn_admin_uploads_size' );
	if ( null !== $cached && ( null === $timeout || (int) $timeout >= time() ) ) {
		return (int) $cached;
	}
	$dir  = ABSPATH . 'wp-content/uploads';
	$size = 0;
	if ( is_dir( $dir ) ) {
		$it = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS )
		);
		foreach ( $it as $file ) {
			$size += $file->getSize();
		}
	}
	return $size;
}

/** Counts by status for one post type. */
function minn_v1_status_counts( string $type ): array {
	global $table_prefix;
	$stmt = minn_db()->prepare(
		"SELECT post_status, COUNT(*) AS c FROM {$table_prefix}posts WHERE post_type = ? GROUP BY post_status"
	);
	$stmt->bind_param( 's', $type );
	$stmt->execute();
	$out = array();
	foreach ( $stmt->get_result()->fetch_all( MYSQLI_ASSOC ) as $row ) {
		$out[ $row['post_status'] ] = (int) $row['c'];
	}
	return $out;
}

/** Approved / moderated comment totals (the two the cards read). */
function minn_v1_comment_counts(): array {
	global $table_prefix;
	$res = minn_db()->query(
		"SELECT comment_approved, COUNT(*) AS c FROM {$table_prefix}comments GROUP BY comment_approved"
	);
	$out = array( 'approved' => 0, 'moderated' => 0 );
	foreach ( $res->fetch_all( MYSQLI_ASSOC ) as $row ) {
		if ( '1' === $row['comment_approved'] ) {
			$out['approved'] = (int) $row['c'];
		} elseif ( '0' === $row['comment_approved'] ) {
			$out['moderated'] = (int) $row['c'];
		}
	}
	return $out;
}

/** May this caller see a comment row that names its post? */
function minn_v1_comment_row_visible( int $uid, int $post_id ): bool {
	if ( minn_user_can( $uid, 'edit_post', $post_id ) ) {
		return true;
	}
	global $table_prefix;
	$stmt = minn_db()->prepare( "SELECT post_password FROM {$table_prefix}posts WHERE ID = ? LIMIT 1" );
	$stmt->bind_param( 'i', $post_id );
	$stmt->execute();
	$row = $stmt->get_result()->fetch_assoc();
	if ( ! $row || '' !== (string) $row['post_password'] ) {
		return false;
	}
	return minn_user_can( $uid, 'read_post', $post_id );
}

function minn_v1_post_title( int $post_id ): string {
	global $table_prefix;
	$stmt = minn_db()->prepare( "SELECT post_title FROM {$table_prefix}posts WHERE ID = ? LIMIT 1" );
	$stmt->bind_param( 'i', $post_id );
	$stmt->execute();
	$row = $stmt->get_result()->fetch_assoc();
	return $row ? (string) $row['post_title'] : '';
}

/**
 * GET /minn-admin/v1/overview
 *
 * Oracle-caught quirk (recorded in the contract): the recent-activity post
 * query runs with a multi-type post_type array plus perm=editable, and on
 * that combination WordPress restricts EVERY status to post_author = caller —
 * admins included, published posts included. Authorless posts appear for
 * nobody. The engine reproduces the observed behavior, not the intent.
 */
function minn_v1_overview(): void {
	global $table_prefix;
	[ , $uid ] = minn_v1_require();
	$days   = minn_v1_validate_days();
	$now    = time();
	$offset = minn_gmt_offset();

	$posts    = minn_v1_status_counts( 'post' );
	$pages    = minn_v1_status_counts( 'page' );
	$media    = minn_v1_status_counts( 'attachment' );
	$comments = minn_v1_comment_counts();

	$draft_n = $posts['draft'] ?? 0;
	$stats   = array(
		array(
			'key'   => 'posts',
			'label' => 'Published posts',
			'value' => minn_number_i18n( $posts['publish'] ?? 0 ),
			'delta' => minn_number_i18n( $draft_n ) . ( 1 === $draft_n ? ' draft' : ' drafts' ),
			'up'    => null,
		),
		array(
			'key'   => 'pages',
			'label' => 'Pages',
			'value' => minn_number_i18n( $pages['publish'] ?? 0 ),
			'delta' => 'published',
			'up'    => null,
		),
		( 0 === $comments['approved'] && 0 === $comments['moderated'] && minn_user_can( $uid, 'list_users' ) )
			? array(
				'key'   => 'users',
				'label' => 'Users',
				'value' => minn_number_i18n( minn_v1_user_count() ),
				'delta' => 'registered',
				'up'    => null,
			)
			: array(
				'key'   => 'comments',
				'label' => 'Comments',
				'value' => minn_number_i18n( $comments['approved'] ),
				'delta' => minn_number_i18n( $comments['moderated'] ) . ' pending',
				'up'    => $comments['moderated'] > 0 ? 'warn' : null,
			),
		array(
			'key'   => 'media',
			'label' => 'Media files',
			'value' => minn_number_i18n( $media['inherit'] ?? 0 ),
			'delta' => minn_size_format( minn_uploads_size() ) . ' used',
			'up'    => null,
		),
	);

	// Activity chart: published posts + all comments per bucket.
	$bucket_days = $days > 45 ? 7 : 1;
	$buckets     = (int) ceil( $days / $bucket_days );
	$series      = array_fill( 0, $buckets, 0 );
	$since       = gmdate( 'Y-m-d H:i:s', $now - $days * 86400 );

	$stmt = minn_db()->prepare(
		"SELECT post_date_gmt FROM {$table_prefix}posts
		 WHERE post_status = 'publish' AND post_type IN ('post','page') AND post_date_gmt >= ?"
	);
	$stmt->bind_param( 's', $since );
	$stmt->execute();
	$dates = array_column( $stmt->get_result()->fetch_all( MYSQLI_NUM ), 0 );

	$stmt = minn_db()->prepare(
		"SELECT comment_date_gmt FROM {$table_prefix}comments WHERE comment_date_gmt >= ?"
	);
	$stmt->bind_param( 's', $since );
	$stmt->execute();
	$dates = array_merge( $dates, array_column( $stmt->get_result()->fetch_all( MYSQLI_NUM ), 0 ) );

	foreach ( $dates as $date ) {
		$age = $now - strtotime( $date . ' UTC' );
		$idx = $buckets - 1 - (int) floor( $age / ( $bucket_days * 86400 ) );
		if ( $idx >= 0 && $idx < $buckets ) {
			$series[ $idx ]++;
		}
	}

	$chart = array();
	foreach ( $series as $i => $count ) {
		$off_days = ( $buckets - 1 - $i ) * $bucket_days;
		$label    = 1 === $bucket_days
			? gmdate( 'M j', $now - $off_days * 86400 + $offset )
			: 'Week of ' . gmdate( 'M j', $now - ( $off_days + $bucket_days - 1 ) * 86400 + $offset );
		$chart[]  = array(
			'label' => $label,
			'value' => $count,
			'from'  => gmdate( 'Y-m-d H:i:s', $now - ( $buckets - $i ) * $bucket_days * 86400 ),
			'to'    => gmdate( 'Y-m-d H:i:s', $now - ( $buckets - 1 - $i ) * $bucket_days * 86400 ),
		);
	}

	// Recent activity: the caller's own posts (see the quirk note above),
	// merged from a by-modified and a by-date window, then recent comments.
	$activity = array();
	$statuses = "('publish','draft','future','pending')";
	$fields   = 'ID, post_type, post_status, post_author, post_title, post_date, post_modified_gmt';
	$rows     = array();
	foreach ( array( 'post_modified', 'post_date' ) as $order ) {
		$stmt = minn_db()->prepare(
			"SELECT {$fields} FROM {$table_prefix}posts
			 WHERE post_type IN ('post','page') AND post_status IN {$statuses} AND post_author = ?
			 ORDER BY {$order} DESC LIMIT 5"
		);
		$stmt->bind_param( 'i', $uid );
		$stmt->execute();
		foreach ( $stmt->get_result()->fetch_all( MYSQLI_ASSOC ) as $p ) {
			$rows[ (int) $p['ID'] ] = $rows[ (int) $p['ID'] ] ?? $p;
		}
	}
	foreach ( $rows as $p ) {
		if ( ! minn_user_can( $uid, 'read_post', (int) $p['ID'] ) ) {
			continue;
		}
		$time = strtotime( $p['post_modified_gmt'] . ' UTC' );
		if ( ! $time || $time < 0 ) {
			// A never-updated draft zeroes both GMT columns; post_date
			// (site-local) is the only truthful stamp.
			$time = strtotime( $p['post_date'] . ' UTC' ) - $offset;
		}
		if ( ! $time || $time < 0 ) {
			continue;
		}
		$author = minn_v1_display_name( (int) $p['post_author'] );
		if ( 'publish' === $p['post_status'] ) {
			$tpl = '%1$s published “%2$s”';
		} elseif ( 'future' === $p['post_status'] ) {
			$tpl = '%1$s scheduled “%2$s”';
		} else {
			$tpl = '%1$s drafted “%2$s”';
		}
		$activity[] = array(
			'text'  => sprintf( $tpl, $author, minn_v1_plain_title( $p['post_title'] ) ),
			'time'  => $time,
			'color' => 'publish' === $p['post_status'] ? 'green' : ( 'future' === $p['post_status'] ? 'blue' : 'accent' ),
			'goto'  => array(
				'kind' => 'editor',
				'type' => 'page' === $p['post_type'] ? 'pages' : 'posts',
				'id'   => (int) $p['ID'],
			),
		);
	}

	$moderator = minn_user_can( $uid, 'moderate_comments' );
	$where     = $moderator ? "comment_approved IN ('0','1')" : "comment_approved = '1'";
	$res       = minn_db()->query(
		"SELECT comment_ID, comment_post_ID, comment_author, comment_approved, comment_date_gmt
		 FROM {$table_prefix}comments WHERE {$where} ORDER BY comment_date_gmt DESC LIMIT 3"
	);
	foreach ( $res->fetch_all( MYSQLI_ASSOC ) as $c ) {
		if ( ! minn_v1_comment_row_visible( $uid, (int) $c['comment_post_ID'] ) ) {
			continue;
		}
		$pending    = '0' === $c['comment_approved'];
		$activity[] = array(
			'text'  => sprintf(
				$pending ? 'Comment from %s awaiting moderation on “%s”' : '%s commented on “%s”',
				'' !== $c['comment_author'] ? $c['comment_author'] : 'Anonymous',
				minn_v1_plain_title( minn_v1_post_title( (int) $c['comment_post_ID'] ) )
			),
			'time'  => (int) strtotime( $c['comment_date_gmt'] . ' UTC' ),
			'color' => $pending ? 'amber' : 'blue',
			'goto'  => array(
				'kind' => 'comments',
				'tab'  => $pending ? 'hold' : 'approve',
			),
		);
	}

	usort( $activity, static fn( $a, $b ) => $b['time'] - $a['time'] );
	$activity = array_slice( $activity, 0, 4 );
	foreach ( $activity as &$item ) {
		$item['time'] = minn_human_time_diff( $item['time'], $now ) . ' ago';
	}
	unset( $item );

	$hour = (int) gmdate( 'G', $now + $offset );
	$greeting = $hour < 12 ? 'Good morning' : ( $hour < 17 ? 'Good afternoon' : 'Good evening' );

	minn_rest_send(
		array(
			'stats'    => $stats,
			'chart'    => $chart,
			'traffic'  => null,
			'activity' => $activity,
			// The engine has no extension runtime yet, so the store (WooCommerce)
			// and traffic (analytics provider) sections are honestly absent.
			'store'    => null,
			'greeting' => $greeting,
		)
	);
}

function minn_v1_user_count(): int {
	global $table_prefix;
	return (int) minn_db()->query( "SELECT COUNT(*) FROM {$table_prefix}users" )->fetch_row()[0];
}

function minn_v1_display_name( int $uid ): string {
	global $table_prefix;
	$stmt = minn_db()->prepare( "SELECT display_name FROM {$table_prefix}users WHERE ID = ? LIMIT 1" );
	$stmt->bind_param( 'i', $uid );
	$stmt->execute();
	$row = $stmt->get_result()->fetch_assoc();
	return $row ? (string) $row['display_name'] : '';
}

/** String values of a serialized PHP string list, without unserialize(). */
function minn_serialized_string_list( ?string $blob ): array {
	if ( null === $blob || ! str_starts_with( $blob, 'a:' ) ) {
		return array();
	}
	preg_match_all( '/;s:\d+:"([^"]*)";/', $blob, $m );
	return $m[1];
}

/** Serialize a flat string list the way WordPress stores one. */
function minn_serialize_string_list( array $values ): string {
	$out = 'a:' . count( $values ) . ':{';
	$i   = 0;
	foreach ( $values as $v ) {
		$out .= 'i:' . $i++ . ';s:' . strlen( $v ) . ':"' . $v . '";';
	}
	return $out . '}';
}

function minn_usermeta_set( int $uid, string $key, string $value ): void {
	global $table_prefix;
	if ( null === minn_user_meta( $uid, $key ) ) {
		$stmt = minn_db()->prepare(
			"INSERT INTO {$table_prefix}usermeta (user_id, meta_key, meta_value) VALUES (?, ?, ?)"
		);
	} else {
		$stmt = minn_db()->prepare(
			"UPDATE {$table_prefix}usermeta SET meta_value = ? WHERE user_id = ? AND meta_key = ?"
		);
		$stmt->bind_param( 'sis', $value, $uid, $key );
		$stmt->execute();
		return;
	}
	$stmt->bind_param( 'iss', $uid, $key, $value );
	$stmt->execute();
}

function minn_usermeta_delete( int $uid, string $key ): void {
	global $table_prefix;
	$stmt = minn_db()->prepare(
		"DELETE FROM {$table_prefix}usermeta WHERE user_id = ? AND meta_key = ?"
	);
	$stmt->bind_param( 'is', $uid, $key );
	$stmt->execute();
}

/** First "field";"value" pair inside a serialized blob, tolerant byte scan. */
function minn_serialized_field( ?string $blob, string $field ): ?string {
	if ( null === $blob ) {
		return null;
	}
	$q = preg_quote( $field, '/' );
	if ( preg_match( '/s:' . strlen( $field ) . ':"' . $q . '";(?:s:\d+:"([^"]*)"|i:(-?\d+)|d:([0-9.Ee+-]+))/', $blob, $m ) ) {
		return '' !== ( $m[1] ?? '' ) ? $m[1] : ( $m[2] ?? $m[3] ?? '' );
	}
	return null;
}

/** Total translation offers across the three update transients. */
function minn_v1_translation_count(): int {
	$total = 0;
	foreach ( array( '_site_transient_update_plugins', '_site_transient_update_themes', '_site_transient_update_core' ) as $name ) {
		$blob = minn_option( $name );
		if ( null !== $blob && preg_match( '/s:12:"translations";a:(\d+):/', $blob, $m ) ) {
			$total += (int) $m[1];
		}
	}
	return $total;
}

/**
 * GET /minn-admin/v1/notifications
 *
 * Sections the engine can honestly produce: pending + recent comments, a
 * core "upgrade" offer, the core auto-update notice, translation counts and
 * new-user registrations. Plugin/theme update rows require an installed
 * extension inventory the engine does not have yet (recorded as a gap in
 * the contract); on the reference database those sections are empty, so
 * parity holds by construction.
 */
function minn_v1_notifications(): void {
	[ , $uid ] = minn_v1_require();
	minn_rest_send( minn_v1_notifications_data( $uid ) );
}

/** The notifications payload for one user (shared with boot-status). */
function minn_v1_notifications_data( int $uid ): array {
	global $table_prefix;
	$now     = time();
	$offset  = minn_gmt_offset();
	$read_at = (int) ( minn_user_meta( $uid, 'minn_admin_notif_read_at' ) ?? 0 );
	$items   = array();

	$moderator = minn_user_can( $uid, 'moderate_comments' );
	if ( $moderator ) {
		$res = minn_db()->query(
			"SELECT comment_ID, comment_post_ID, comment_author, comment_date_gmt
			 FROM {$table_prefix}comments WHERE comment_approved = '0'
			 ORDER BY comment_date_gmt DESC LIMIT 5"
		);
		foreach ( $res->fetch_all( MYSQLI_ASSOC ) as $c ) {
			if ( ! minn_v1_comment_row_visible( $uid, (int) $c['comment_post_ID'] ) ) {
				continue;
			}
			$items[] = array(
				'id'    => 'comment-' . $c['comment_ID'],
				'kind'  => 'comments',
				'icon'  => '💬',
				'title' => sprintf(
					'New comment from %s awaiting moderation on “%s”',
					'' !== $c['comment_author'] ? $c['comment_author'] : 'Anonymous',
					minn_texturize( minn_v1_post_title( (int) $c['comment_post_ID'] ) )
				),
				'time'  => (int) strtotime( $c['comment_date_gmt'] . ' UTC' ),
			);
		}
	}
	$res = minn_db()->query(
		"SELECT comment_ID, comment_post_ID, comment_author, comment_date_gmt
		 FROM {$table_prefix}comments WHERE comment_approved = '1'
		 ORDER BY comment_date_gmt DESC LIMIT 3"
	);
	foreach ( $res->fetch_all( MYSQLI_ASSOC ) as $c ) {
		if ( ! minn_v1_comment_row_visible( $uid, (int) $c['comment_post_ID'] ) ) {
			continue;
		}
		$items[] = array(
			'id'    => 'comment-' . $c['comment_ID'],
			'kind'  => 'comments',
			'icon'  => '💬',
			'title' => sprintf(
				'%s commented on “%s”',
				'' !== $c['comment_author'] ? $c['comment_author'] : 'Anonymous',
				minn_texturize( minn_v1_post_title( (int) $c['comment_post_ID'] ) )
			),
			'time'  => (int) strtotime( $c['comment_date_gmt'] . ' UTC' ),
		);
	}

	if ( minn_user_can( $uid, 'update_plugins' ) || minn_user_can( $uid, 'update_themes' ) ) {
		$count = minn_v1_translation_count();
		if ( $count ) {
			$items[] = array(
				'id'     => 'translations-' . $count,
				'kind'   => 'updates',
				'icon'   => '⬆',
				'title'  => sprintf(
					1 === $count ? '%d translation is available to update' : '%d translations are available to update',
					$count
				),
				'time'   => $now,
				'update' => array( 'type' => 'translations', 'count' => $count ),
			);
		}
	}

	if ( minn_user_can( $uid, 'update_core' ) ) {
		$core = minn_option( '_site_transient_update_core' );
		if ( null !== $core && 'upgrade' === minn_serialized_field( $core, 'response' ) ) {
			$version = (string) minn_serialized_field( $core, 'version' );
			$items[] = array(
				'id'     => 'core-' . $version,
				'kind'   => 'system',
				'icon'   => '🛡',
				'title'  => sprintf( 'WordPress %s is available', $version ),
				'time'   => (int) minn_serialized_field( $core, 'last_checked' ),
				'update' => array( 'type' => 'core', 'version' => $version, 'name' => 'WordPress' ),
			);
		}
		$auto = minn_option( 'auto_core_update_notified' );
		if ( null !== $auto && 'success' === minn_serialized_field( $auto, 'type' ) ) {
			$version = (string) minn_serialized_field( $auto, 'version' );
			$stamp   = (int) minn_serialized_field( $auto, 'timestamp' );
			if ( '' !== $version && ( $now - $stamp ) < 14 * 86400 ) {
				$items[] = array(
					'id'    => 'core-auto-' . $version,
					'kind'  => 'system',
					'icon'  => '🛡',
					'title' => sprintf( 'WordPress updated itself to %s', $version ),
					'time'  => $stamp,
				);
			}
		}
	}

	if ( minn_user_can( $uid, 'list_users' ) ) {
		$since = gmdate( 'Y-m-d H:i:s', $now + $offset - 7 * 86400 );
		$stmt  = minn_db()->prepare(
			"SELECT ID, display_name, user_registered FROM {$table_prefix}users
			 WHERE user_registered > ? ORDER BY user_registered DESC LIMIT 2"
		);
		$stmt->bind_param( 's', $since );
		$stmt->execute();
		foreach ( $stmt->get_result()->fetch_all( MYSQLI_ASSOC ) as $u ) {
			$items[] = array(
				'id'    => 'user-' . $u['ID'],
				'kind'  => 'system',
				'icon'  => '👤',
				'title' => sprintf( 'New user registered: %s', $u['display_name'] ),
				'time'  => (int) strtotime( $u['user_registered'] . ' UTC' ),
			);
		}
	}

	usort( $items, static fn( $a, $b ) => $b['time'] - $a['time'] );

	$read_ids = minn_serialized_string_list( minn_user_meta( $uid, 'minn_admin_notif_read_ids' ) );
	// Site-local midnight, expressed back in GMT epoch.
	$today = strtotime( gmdate( 'Y-m-d', $now + $offset ) . ' 00:00:00 UTC' ) - $offset;
	foreach ( $items as &$item ) {
		$item['unread'] = $item['time'] > $read_at && ! in_array( $item['id'], $read_ids, true );
		$item['group']  = $item['time'] >= $today ? 'Today' : 'Earlier';
		$item['ago']    = minn_human_time_diff( $item['time'], $now ) . ' ago';
	}
	unset( $item );

	return array( 'items' => $items );
}

/** POST /minn-admin/v1/notifications/read — body {id} marks one, {} marks all. */
function minn_v1_notifications_read(): void {
	[ , $uid ] = minn_v1_require();
	$body = minn_request_body();
	$id   = trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) ( $body['id'] ?? '' ) ) ) );
	if ( '' !== $id ) {
		$ids   = minn_serialized_string_list( minn_user_meta( $uid, 'minn_admin_notif_read_ids' ) );
		$ids[] = $id;
		$ids   = array_slice( array_values( array_unique( $ids ) ), -200 );
		minn_usermeta_set( $uid, 'minn_admin_notif_read_ids', minn_serialize_string_list( $ids ) );
	} else {
		minn_usermeta_set( $uid, 'minn_admin_notif_read_at', (string) time() );
		minn_usermeta_delete( $uid, 'minn_admin_notif_read_ids' );
	}
	minn_rest_send( array( 'ok' => true ) );
}

/**
 * GET /minn-admin/v1/core — gate update_core.
 *
 * The installed version comes from the update_core transient's
 * version_checked (the database's own record of what last phoned home);
 * the engine never reads WordPress code files and never phones home
 * itself. dbUpgrade is false by definition: there is no newer core code
 * on disk for the database to lag behind.
 */
function minn_v1_core(): void {
	[ , $uid ] = minn_v1_require();
	if ( ! minn_user_can( $uid, 'update_core' ) ) {
		minn_rest_error( 'rest_forbidden', 'Sorry, you are not allowed to do that.', 403 );
	}
	minn_rest_send( minn_v1_core_data() );
}

function minn_v1_core_data(): array {
	$blob  = minn_option( '_site_transient_update_core' );
	$offer = null;
	if ( null !== $blob && 'upgrade' === minn_serialized_field( $blob, 'response' ) ) {
		$offer = array(
			'version' => (string) minn_serialized_field( $blob, 'current' ),
			'locale'  => (string) minn_serialized_field( $blob, 'locale' ),
		);
	}
	return array(
		'version'   => (string) ( minn_serialized_field( $blob, 'version_checked' ) ?? '' ),
		'dbUpgrade' => false,
		'update'    => $offer,
	);
}

/** Validate the overview-activity window: both bounds required, "Y-m-d H:i:s". */
function minn_v1_require_window(): array {
	$missing = array();
	foreach ( array( 'from', 'to' ) as $name ) {
		if ( ! isset( $_GET[ $name ] ) ) {
			$missing[] = $name;
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
	$pattern = '^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$';
	$out     = array();
	$bad     = array();
	foreach ( array( 'from', 'to' ) as $name ) {
		$value = (string) $_GET[ $name ];
		if ( ! preg_match( '/' . $pattern . '/', $value ) ) {
			$bad[ $name ] = $name . ' does not match pattern ' . $pattern . '.';
		}
		$out[] = $value;
	}
	if ( $bad ) {
		$details = array();
		foreach ( $bad as $name => $message ) {
			$details[ $name ] = array( 'code' => 'rest_invalid_pattern', 'message' => $message, 'data' => null );
		}
		minn_rest_headers();
		http_response_code( 400 );
		echo json_encode(
			array(
				'code'    => 'rest_invalid_param',
				'message' => 'Invalid parameter(s): ' . implode( ', ', array_keys( $bad ) ),
				'data'    => array( 'status' => 400, 'params' => $bad, 'details' => $details ),
			)
		);
		exit;
	}
	return $out;
}

/**
 * GET /minn-admin/v1/overview/activity — the events behind one chart bar,
 * (from, to] GMT. Post rows decode the RAW stored title; comment rows
 * decode the texturized one (the oracle's asymmetry, kept).
 */
function minn_v1_overview_activity(): void {
	global $table_prefix;
	[ , $uid ] = minn_v1_require();
	[ $from, $to ] = minn_v1_require_window();
	$now   = time();
	$items = array();

	$stmt = minn_db()->prepare(
		"SELECT ID, post_title, post_type, post_author, post_date_gmt FROM {$table_prefix}posts
		 WHERE post_status = 'publish' AND post_type IN ('post','page')
		 AND post_date_gmt > ? AND post_date_gmt <= ?
		 ORDER BY post_date_gmt DESC LIMIT 100"
	);
	$stmt->bind_param( 'ss', $from, $to );
	$stmt->execute();
	foreach ( $stmt->get_result()->fetch_all( MYSQLI_ASSOC ) as $p ) {
		$author  = minn_v1_display_name( (int) $p['post_author'] );
		$title   = html_entity_decode( '' !== $p['post_title'] ? $p['post_title'] : '(no title)', ENT_QUOTES );
		$items[] = array(
			'kind'  => 'post',
			'id'    => (int) $p['ID'],
			'type'  => 'page' === $p['post_type'] ? 'pages' : 'posts',
			'text'  => sprintf( '%1$s published “%2$s”', '' !== $author ? $author : 'Someone', $title ),
			'time'  => (int) strtotime( $p['post_date_gmt'] . ' UTC' ),
			'color' => 'green',
		);
	}

	$approved_only = ! minn_user_can( $uid, 'moderate_comments' );
	$comment_where = $approved_only ? " AND comment_approved = '1'" : '';
	$stmt          = minn_db()->prepare(
		"SELECT comment_ID, comment_author, comment_post_ID, comment_date_gmt, comment_approved
		 FROM {$table_prefix}comments
		 WHERE comment_date_gmt > ? AND comment_date_gmt <= ?{$comment_where}
		 AND comment_type IN ( '', 'comment' )
		 ORDER BY comment_date_gmt DESC LIMIT 300"
	);
	$stmt->bind_param( 'ss', $from, $to );
	$stmt->execute();
	foreach ( $stmt->get_result()->fetch_all( MYSQLI_ASSOC ) as $c ) {
		if ( ! minn_v1_comment_row_visible( $uid, (int) $c['comment_post_ID'] ) ) {
			continue;
		}
		$pending = '0' === $c['comment_approved'];
		$title   = minn_texturize( minn_v1_post_title( (int) $c['comment_post_ID'] ) );
		$items[] = array(
			'kind'  => 'comment',
			'id'    => (int) $c['comment_ID'],
			'text'  => sprintf(
				$pending ? 'Comment from %1$s awaiting moderation on “%2$s”' : '%1$s commented on “%2$s”',
				'' !== $c['comment_author'] ? $c['comment_author'] : 'Anonymous',
				html_entity_decode( '' !== $title ? $title : '(no title)', ENT_QUOTES )
			),
			'time'  => (int) strtotime( $c['comment_date_gmt'] . ' UTC' ),
			'color' => $pending ? 'amber' : 'blue',
		);
	}

	usort( $items, static fn( $a, $b ) => $b['time'] - $a['time'] );
	$items = array_slice( $items, 0, 100 );
	foreach ( $items as &$item ) {
		$item['ago'] = minn_human_time_diff( $item['time'], $now ) . ' ago';
	}
	unset( $item );

	minn_rest_send( array( 'items' => $items ) );
}

/**
 * Admin-facing type facts (viewable, labels, supports, the edit gate) live
 * beside — not inside — the wp/v2 registry, so the types route's payload
 * stays byte-faithful.
 */
function minn_v1_types_admin(): array {
	static $extra = null;
	if ( null === $extra ) {
		$extra = json_decode( (string) file_get_contents( __DIR__ . '/data/types-admin.json' ), true );
	}
	return $extra;
}

/** The boot-status types section: edit-visible types for this user, slimmed. */
function minn_v1_types_section( int $uid ): array {
	$extra = minn_v1_types_admin();
	$out   = array();
	foreach ( minn_types_registry() as $slug => $t ) {
		$a = $extra[ $slug ] ?? array();
		if ( ! minn_user_can( $uid, $a['edit_cap'] ?? 'edit_theme_options' ) ) {
			continue;
		}
		$out[] = array(
			'slug'         => $slug,
			'rest_base'    => $t['rest_base'],
			'name'         => $t['name'],
			'viewable'     => (bool) ( $a['viewable'] ?? false ),
			'labels'       => array( 'singular_name' => $a['labels']['singular_name'] ?? '' ),
			'supports'     => $a['supports'] ?? array(),
			'hierarchical' => (bool) ( $t['hierarchical'] ?? false ),
		);
	}
	return $out;
}

/**
 * GET /minn-admin/v1/boot-status — the app's one-round-trip boot burst.
 *
 * Absent sections are the CONTRACT'S OWN fallback mechanism: the client
 * treats a missing section as "load it standalone". The engine serves the
 * sections it can honestly answer (notifications, core, types,
 * pendingComments) and omits the plugin-inventory ones (plugins,
 * pluginUpdates, pluginMeta) it has no installation for.
 */
function minn_v1_boot_status(): void {
	global $table_prefix;
	[ , $uid ] = minn_v1_require();
	$out = array( 'notifications' => minn_v1_notifications_data( $uid ) );

	if ( minn_user_can( $uid, 'update_core' ) ) {
		$out['core'] = minn_v1_core_data();
	}

	$out['types'] = minn_v1_types_section( $uid );

	// comments_enabled: a UI post type still supports comments.
	$extra   = minn_v1_types_admin();
	$enabled = ! empty( $extra['post']['supports']['comments'] ) || ! empty( $extra['page']['supports']['comments'] );
	if ( $enabled ) {
		$out['pendingComments'] = (int) minn_db()->query(
			"SELECT COUNT(*) FROM {$table_prefix}comments
			 WHERE comment_approved = '0' AND comment_type IN ( '', 'comment' )"
		)->fetch_row()[0];
	}

	minn_rest_send( $out );
}
