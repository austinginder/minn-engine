<?php
/**
 * An attachment's public link: its slug under the parent's permalink when
 * attached, at the root when not, or the query form under plain permalinks.
 */
function minn_attachment_link( array $p ): string {
	$permalinks = minn_permalinks();
	$id         = (int) $p['ID'];
	if ( ! $permalinks->isPretty() || '' === $p['post_name'] ) {
		return minn_home_url( '/?attachment_id=' . $id );
	}
	$parent = (int) $p['post_parent'] > 0 ? ( new Minn\Content\Posts( Minn\Db::shared() ) )->find( (int) $p['post_parent'] ) : null;
	if ( $parent ) {
		return rtrim( $permalinks->forPost( $parent ), '/' ) . '/' . $p['post_name'] . '/';
	}
	return minn_home_url( '/' . $p['post_name'] . '/' );
}
/**
 * The wp/v2/media surface: list, single, upload (multipart or raw binary),
 * field edits, force delete — including GD sub-size generation and the
 * serialized attachment metadata WordPress reads back.
 *
 * Implemented from oracle captures recorded in contracts/rest/media.md,
 * cross-checked live against the reference on the same database and the
 * SHARED uploads root (wp-reference/wp-content/uploads is a symlink into
 * public/wp-content/uploads); no WordPress source is used.
 */

function minn_uploads_basedir(): string {
	return ABSPATH . 'wp-content/uploads';
}

function minn_uploads_baseurl(): string {
	return minn_home_url( '/wp-content/uploads' );
}

/* ------------------------------------------------ attachment metadata blob */

/**
 * Parse _wp_attachment_metadata without unserialize(): top-level dims and
 * file, the sizes map, and the image_meta scalars.
 */
function minn_media_meta_parse( ?string $blob ): array {
	$meta = array( 'width' => 0, 'height' => 0, 'file' => '', 'filesize' => 0, 'sizes' => array(), 'image_meta' => null );
	if ( null === $blob || '' === $blob ) {
		return $meta;
	}
	// Top-level scalars appear before the sizes array.
	$head = substr( $blob, 0, strpos( $blob, '"sizes"' ) ?: strlen( $blob ) );
	foreach ( array( 'width', 'height', 'filesize' ) as $k ) {
		if ( preg_match( '/s:' . strlen( $k ) . ':"' . $k . '";i:(\d+);/', $head, $m ) ) {
			$meta[ $k ] = (int) $m[1];
		}
	}
	if ( preg_match( '/s:4:"file";s:\d+:"([^"]*)";/', $head, $m ) ) {
		$meta['file'] = $m[1];
	}

	// Each named size is a 4-5 key map; scan them in stored order.
	if ( preg_match( '/s:5:"sizes";a:\d+:\{(.*)\}s:10:"image_meta"/s', $blob, $m )
		|| preg_match( '/s:5:"sizes";a:\d+:\{(.*)\}\}$/s', $blob, $m ) ) {
		$body = $m[1];
		if ( preg_match_all( '/s:\d+:"([^"]+)";a:\d+:\{s:4:"file";s:\d+:"([^"]*)";s:5:"width";i:(\d+);s:6:"height";i:(\d+);s:9:"mime-type";s:\d+:"([^"]*)";(?:s:8:"filesize";i:(\d+);)?\}/', $body, $all, PREG_SET_ORDER ) ) {
			foreach ( $all as $s ) {
				$meta['sizes'][ $s[1] ] = array(
					'file'      => $s[2],
					'width'     => (int) $s[3],
					'height'    => (int) $s[4],
					'mime-type' => $s[5],
				) + ( isset( $s[6] ) && '' !== $s[6] ? array( 'filesize' => (int) $s[6] ) : array() );
			}
		}
	}

	if ( str_contains( $blob, '"image_meta"' ) ) {
		$im = array();
		foreach ( array( 'aperture', 'credit', 'camera', 'caption', 'created_timestamp', 'copyright', 'focal_length', 'iso', 'shutter_speed', 'title', 'orientation' ) as $k ) {
			$im[ $k ] = '';
			if ( preg_match( '/s:' . strlen( $k ) . ':"' . $k . '";s:\d+:"([^"]*)";/', $blob, $m ) ) {
				$im[ $k ] = $m[1];
			}
		}
		$im['keywords'] = array();
		$im['alt']      = '';
		if ( preg_match( '/s:3:"alt";s:\d+:"([^"]*)";/', $blob, $m ) ) {
			$im['alt'] = $m[1];
		}
		$meta['image_meta'] = $im;
	}
	return $meta;
}

/** Serialize the metadata array in WordPress's stored shape. */
function minn_media_meta_serialize( array $meta ): string {
	$s  = static fn( string $v ): string => 's:' . strlen( $v ) . ':"' . $v . '";';
	$kv = static fn( string $k, string $enc ): string => 's:' . strlen( $k ) . ':"' . $k . '";' . $enc;

	$sizes = '';
	foreach ( $meta['sizes'] as $name => $sz ) {
		$entry = $kv( 'file', $s( $sz['file'] ) )
			. $kv( 'width', 'i:' . $sz['width'] . ';' )
			. $kv( 'height', 'i:' . $sz['height'] . ';' )
			. $kv( 'mime-type', $s( $sz['mime-type'] ) )
			. ( isset( $sz['filesize'] ) ? $kv( 'filesize', 'i:' . $sz['filesize'] . ';' ) : '' );
		$sizes .= $s( $name ) . 'a:' . ( isset( $sz['filesize'] ) ? 5 : 4 ) . ':{' . $entry . '}';
	}

	$im  = $meta['image_meta'];
	$imx = '';
	foreach ( array( 'aperture', 'credit', 'camera', 'caption', 'created_timestamp', 'copyright', 'focal_length', 'iso', 'shutter_speed', 'title', 'orientation' ) as $k ) {
		$imx .= $kv( $k, $s( (string) $im[ $k ] ) );
	}
	$imx .= $kv( 'keywords', 'a:0:{}' );
	$imx .= $kv( 'alt', $s( (string) ( $im['alt'] ?? '' ) ) );

	return 'a:6:{'
		. $kv( 'width', 'i:' . $meta['width'] . ';' )
		. $kv( 'height', 'i:' . $meta['height'] . ';' )
		. $kv( 'file', $s( $meta['file'] ) )
		. $kv( 'filesize', 'i:' . $meta['filesize'] . ';' )
		. $kv( 'sizes', 'a:' . count( $meta['sizes'] ) . ':{' . $sizes . '}' )
		. $kv( 'image_meta', 'a:13:{' . $imx . '}' )
		. '}';
}

/* --------------------------------------------------------- sub-size maker */

/** The registered image sizes, from the options WordPress stores. */
function minn_media_size_ladder(): array {
	return array(
		'medium'       => array( (int) ( minn_option( 'medium_size_w' ) ?? 300 ), (int) ( minn_option( 'medium_size_h' ) ?? 300 ), false ),
		'large'        => array( (int) ( minn_option( 'large_size_w' ) ?? 1024 ), (int) ( minn_option( 'large_size_h' ) ?? 1024 ), false ),
		'thumbnail'    => array( (int) ( minn_option( 'thumbnail_size_w' ) ?? 150 ), (int) ( minn_option( 'thumbnail_size_h' ) ?? 150 ), '1' === ( minn_option( 'thumbnail_crop' ) ?? '1' ) ),
		'medium_large' => array( (int) ( minn_option( 'medium_large_size_w' ) ?? 768 ), (int) ( minn_option( 'medium_large_size_h' ) ?? 0 ), false ),
	);
}

/** Fit (w,h) inside (max_w,max_h); 0 means unconstrained. Round like WP. */
function minn_media_constrain( int $w, int $h, int $max_w, int $max_h ): array {
	$ratios = array();
	if ( $max_w > 0 ) {
		$ratios[] = $max_w / $w;
	}
	if ( $max_h > 0 ) {
		$ratios[] = $max_h / $h;
	}
	$ratio = $ratios ? min( $ratios ) : 1;
	if ( $ratio >= 1 ) {
		return array( $w, $h );
	}
	return array( (int) round( $w * $ratio ), (int) round( $h * $ratio ) );
}

/** Generate the sub-sizes for one image; returns the sizes metadata map. */
function minn_media_make_subsizes( string $path, string $mime ): array {
	[ $w, $h ] = getimagesize( $path );
	$src = match ( $mime ) {
		'image/png'  => imagecreatefrompng( $path ),
		'image/jpeg' => imagecreatefromjpeg( $path ),
		'image/gif'  => imagecreatefromgif( $path ),
		'image/webp' => imagecreatefromwebp( $path ),
		default      => null,
	};
	if ( ! $src ) {
		return array();
	}
	imagesavealpha( $src, true );

	$dir   = dirname( $path );
	$stem  = preg_replace( '/\.[^.]+$/', '', basename( $path ) );
	$ext   = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
	$sizes = array();

	foreach ( minn_media_size_ladder() as $name => [ $max_w, $max_h, $crop ] ) {
		if ( $crop ) {
			if ( $w < $max_w || $h < $max_h ) {
				continue;
			}
			$dst_w = $max_w;
			$dst_h = $max_h;
			// Center crop: cover the target box, then trim.
			$scale = max( $max_w / $w, $max_h / $h );
			$crop_w = (int) round( $max_w / $scale );
			$crop_h = (int) round( $max_h / $scale );
			$sx     = (int) floor( ( $w - $crop_w ) / 2 );
			$sy     = (int) floor( ( $h - $crop_h ) / 2 );
		} else {
			[ $dst_w, $dst_h ] = minn_media_constrain( $w, $h, $max_w, $max_h );
			if ( ( $dst_w === $w && $dst_h === $h ) || $dst_w < 1 || $dst_h < 1 ) {
				continue;
			}
			$sx     = 0;
			$sy     = 0;
			$crop_w = $w;
			$crop_h = $h;
		}
		$dst = imagecreatetruecolor( $dst_w, $dst_h );
		imagealphablending( $dst, false );
		imagesavealpha( $dst, true );
		imagecopyresampled( $dst, $src, 0, 0, $sx, $sy, $dst_w, $dst_h, $crop_w, $crop_h );
		$file = "{$stem}-{$dst_w}x{$dst_h}.{$ext}";
		$out  = "$dir/$file";
		match ( $mime ) {
			'image/png'  => imagepng( $dst, $out ),
			'image/jpeg' => imagejpeg( $dst, $out, 82 ),
			'image/gif'  => imagegif( $dst, $out ),
			'image/webp' => imagewebp( $dst, $out, 82 ),
		};
		imagedestroy( $dst );
		$sizes[ $name ] = array(
			'file'      => $file,
			'width'     => $dst_w,
			'height'    => $dst_h,
			'mime-type' => $mime,
			'filesize'  => (int) filesize( $out ),
		);
	}
	imagedestroy( $src );
	return $sizes;
}

/* ----------------------------------------------------------- object build */

function minn_media_row( int $id ): ?array {
	global $table_prefix;
	$stmt = minn_db()->prepare(
		"SELECT * FROM {$table_prefix}posts WHERE ID = ? AND post_type = 'attachment' LIMIT 1"
	);
	$stmt->bind_param( 'i', $id );
	$stmt->execute();
	return $stmt->get_result()->fetch_assoc() ?: null;
}

/** media_details for the REST object: dims, file, filesize, sizes with URLs. */
function minn_media_details( int $id, array $meta ): array {
	if ( '' === $meta['file'] ) {
		// Non-image attachments carry no parsed metadata yet (recorded gap).
		return array();
	}
	$base_url = minn_uploads_baseurl() . '/' . dirname( $meta['file'] );
	$sizes    = array();
	foreach ( $meta['sizes'] as $name => $sz ) {
		$sizes[ $name ] = array(
			'file'      => $sz['file'],
			'width'     => $sz['width'],
			'height'    => $sz['height'],
		) + ( isset( $sz['filesize'] ) ? array( 'filesize' => $sz['filesize'] ) : array() ) + array(
			'mime_type'  => $sz['mime-type'],
			'source_url' => $base_url . '/' . $sz['file'],
		);
	}
	if ( $meta['width'] ) {
		$sizes['full'] = array(
			'file'       => basename( $meta['file'] ),
			'width'      => $meta['width'],
			'height'     => $meta['height'],
			'mime_type'  => minn_media_row_mime( $id ),
			'source_url' => minn_uploads_baseurl() . '/' . $meta['file'],
		);
	}
	$out = array(
		'width'  => $meta['width'],
		'height' => $meta['height'],
		'file'   => $meta['file'],
	);
	if ( $meta['filesize'] ) {
		$out['filesize'] = $meta['filesize'];
	}
	$out['sizes'] = $sizes;
	if ( null !== $meta['image_meta'] ) {
		$out['image_meta'] = $meta['image_meta'];
	}
	return $out;
}

function minn_media_row_mime( int $id ): string {
	$row = minn_media_row( $id );
	return $row ? (string) $row['post_mime_type'] : '';
}

/** The attachment-page image HTML that description.rendered carries. */
function minn_media_description_html( array $meta, string $full_url, string $alt ): string {
	if ( empty( $meta['sizes']['medium'] ) ) {
		return '';
	}
	$base   = dirname( $full_url );
	$medium = $meta['sizes']['medium'];
	$ratio  = $meta['width'] > 0 ? $meta['height'] / $meta['width'] : 0;

	// srcset: same-ratio sizes in stored order, then the full image.
	$srcset = array();
	foreach ( $meta['sizes'] as $sz ) {
		if ( $sz['width'] < 1 || abs( $sz['height'] - $sz['width'] * $ratio ) > 1 ) {
			continue;
		}
		$srcset[ $sz['width'] ] = $base . '/' . $sz['file'] . ' ' . $sz['width'] . 'w';
	}
	$srcset[ $meta['width'] ] = $full_url . ' ' . $meta['width'] . 'w';

	$img = '<img loading="lazy" decoding="async" width="' . $medium['width'] . '" height="' . $medium['height'] . '"'
		. ' src="' . $base . '/' . $medium['file'] . '" class="attachment-medium size-medium" alt="' . minn_esc( $alt ) . '"'
		. ' srcset="' . implode( ', ', $srcset ) . '"'
		. ' sizes="auto, (max-width: ' . $medium['width'] . 'px) 100vw, ' . $medium['width'] . 'px" />';
	return "<p class=\"attachment\"><a href='" . $full_url . "'>" . $img . '</a></p>' . "\n";
}

/** One wp/v2 media object. */
function minn_rest_media_object( array $p, bool $edit, int $viewer_uid ): array {
	$id       = (int) $p['ID'];
	$meta     = minn_media_meta_parse( minn_post_meta_value( $id, '_wp_attachment_metadata' ) );
	$file     = minn_post_meta_value( $id, '_wp_attached_file' ) ?? '';
	$alt      = minn_post_meta_value( $id, '_wp_attachment_image_alt' ) ?? '';
	$full_url = '' !== $file ? minn_uploads_baseurl() . '/' . $file : '';
	$is_image = str_starts_with( $p['post_mime_type'], 'image/' );

	$obj = array(
		'id'       => $id,
		'date'     => str_replace( ' ', 'T', $p['post_date'] ),
		'date_gmt' => str_replace( ' ', 'T', $p['post_date_gmt'] ),
		'guid'     => $edit
			? array( 'rendered' => $p['guid'], 'raw' => $p['guid'] )
			: array( 'rendered' => $p['guid'] ),
		'modified'     => str_replace( ' ', 'T', $p['post_modified'] ),
		'modified_gmt' => str_replace( ' ', 'T', $p['post_modified_gmt'] ),
		'slug'         => $p['post_name'],
		'status'       => $p['post_status'],
		'type'         => 'attachment',
		'link'         => minn_attachment_link( $p ),
		'title'        => $edit
			? array( 'raw' => $p['post_title'], 'rendered' => minn_texturize( $p['post_title'] ) )
			: array( 'rendered' => minn_texturize( $p['post_title'] ) ),
		'author'         => (int) $p['post_author'],
		'featured_media' => 0,
		'comment_status' => $p['comment_status'],
		'ping_status'    => $p['ping_status'],
		'template'       => '',
		'meta'           => array(),
	);
	if ( $edit ) {
		$obj['permalink_template'] = minn_home_url( '/?attachment_id=' . $id );
		$obj['generated_slug']     = minn_sanitize_slug( $p['post_title'] );
	}
	$obj['class_list'] = array( 'post-' . $id, 'attachment', 'type-attachment', 'status-' . $p['post_status'], 'hentry' );
	$obj['minn_attached_to'] = null;

	$desc_rendered = $is_image ? minn_media_description_html( $meta, $full_url, $alt ) : '';
	$obj['description'] = $edit
		? array( 'raw' => $p['post_content'], 'rendered' => $desc_rendered )
		: array( 'rendered' => $desc_rendered );
	$obj['caption'] = $edit
		? array( 'raw' => $p['post_excerpt'], 'rendered' => '' === $p['post_excerpt'] ? '' : minn_comment_render( $p['post_excerpt'] ) )
		: array( 'rendered' => '' === $p['post_excerpt'] ? '' : minn_comment_render( $p['post_excerpt'] ) );
	$obj['alt_text']      = $alt;
	$obj['media_type']    = $is_image ? 'image' : 'file';
	$obj['mime_type']     = $p['post_mime_type'];
	$obj['media_details'] = minn_media_details( $id, $meta );
	$obj['post']          = (int) $p['post_parent'] > 0 ? (int) $p['post_parent'] : null;
	$obj['source_url']    = $full_url;
	$obj['filename'] = basename( $file );
	$obj['filesize'] = $meta['filesize'] ?: ( '' !== $file && is_file( minn_uploads_basedir() . '/' . $file ) ? (int) filesize( minn_uploads_basedir() . '/' . $file ) : 0 );
	if ( $edit ) {
		$obj['missing_image_sizes'] = array();
		// The plugin's image-editor facts ride edit context.
		$obj['image_quality']          = array( 'default' => 82, 'sizes' => array() );
		$obj['exif_orientation']       = 1;
		$obj['image_save_progressive'] = false;
		$obj['image_output_format']    = null;
	}

	$can_edit = minn_user_can( $viewer_uid, 'edit_post', $id );
	$self     = array( 'href' => minn_rest_url( '/wp/v2/media/' . $id ) );
	$self['targetHints'] = array( 'allow' => $can_edit ? array( 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' ) : array( 'GET' ) );
	$links = array(
		'self'       => array( $self ),
		'collection' => array( array( 'href' => minn_rest_url( '/wp/v2/media' ) ) ),
		'about'      => array( array( 'href' => minn_rest_url( '/wp/v2/types/attachment' ) ) ),
	);
	if ( (int) $p['post_author'] > 0 ) {
		$links['author'] = array( array( 'embeddable' => true, 'href' => minn_rest_url( '/wp/v2/users/' . (int) $p['post_author'] ) ) );
	}
	$links['replies'] = array( array( 'embeddable' => true, 'href' => minn_rest_url( '/wp/v2/comments', array( 'post' => $id ) ) ) );
	if ( $edit && $can_edit ) {
		$links['wp:action-unfiltered-html'] = array( array( 'href' => minn_rest_url( '/wp/v2/media/' . $id ) ) );
		if ( minn_user_can( $viewer_uid, 'edit_others_posts' ) ) {
			$links['wp:action-assign-author'] = array( array( 'href' => minn_rest_url( '/wp/v2/media/' . $id ) ) );
		}
	}
	foreach ( $links as $rel => $_ ) {
		if ( str_starts_with( (string) $rel, 'wp:' ) ) {
			$links['curies'] = array( array( 'name' => 'wp', 'href' => 'https://api.w.org/{rel}', 'templated' => true ) );
			break;
		}
	}
	$obj['_links'] = $links;
	return $obj;
}

/* ---------------------------------------------------------------- routes */

/** GET /wp/v2/media */
function minn_rest_media_list(): void {
	global $table_prefix;
	$uid     = minn_current_user_id();
	$context = ( $_GET['context'] ?? 'view' ) === 'edit' ? 'edit' : 'view';
	if ( 'edit' === $context && ! minn_user_can( $uid, 'edit_posts' ) ) {
		minn_rest_error( 'rest_forbidden_context', 'Sorry, you are not allowed to edit posts in this post type.', $uid ? 403 : 401 );
	}
	$per_page = max( 1, min( 100, (int) ( $_GET['per_page'] ?? 10 ) ) );
	$page     = max( 1, (int) ( $_GET['page'] ?? 1 ) );
	$offset   = ( $page - 1 ) * $per_page;
	$order    = strtoupper( (string) ( $_GET['order'] ?? 'desc' ) ) === 'ASC' ? 'ASC' : 'DESC';

	$where = "post_type = 'attachment' AND post_status = 'inherit'";
	$args  = array();
	$types = '';
	if ( ! empty( $_GET['include'] ) ) {
		$ids = array_filter( array_map( 'intval', explode( ',', (string) $_GET['include'] ) ) );
		if ( $ids ) {
			$where .= ' AND ID IN (' . implode( ',', array_fill( 0, count( $ids ), '?' ) ) . ')';
			$args   = $ids;
			$types  = str_repeat( 'i', count( $ids ) );
		}
	}

	$stmt = minn_db()->prepare( "SELECT COUNT(*) FROM {$table_prefix}posts WHERE $where" );
	if ( $types ) {
		$stmt->bind_param( $types, ...$args );
	}
	$stmt->execute();
	$total = (int) $stmt->get_result()->fetch_row()[0];

	$stmt = minn_db()->prepare(
		"SELECT * FROM {$table_prefix}posts WHERE $where ORDER BY post_date $order, ID $order LIMIT ? OFFSET ?"
	);
	$stmt->bind_param( $types . 'ii', ...array_merge( $args, array( $per_page, $offset ) ) );
	$stmt->execute();
	$rows = $stmt->get_result()->fetch_all( MYSQLI_ASSOC );

	$edit = 'edit' === $context;
	header( 'X-WP-Total: ' . $total );
	header( 'X-WP-TotalPages: ' . (int) ceil( $total / $per_page ) );
	minn_rest_send( array_map( static fn( $p ) => minn_rest_media_object( $p, $edit, $uid ), $rows ), 200, true );
}

/** GET /wp/v2/media/{id} */
function minn_rest_media_single( int $id ): void {
	$uid     = minn_current_user_id();
	$context = ( $_GET['context'] ?? 'view' ) === 'edit' ? 'edit' : 'view';
	$p       = minn_media_row( $id );
	if ( ! $p ) {
		minn_rest_error( 'rest_post_invalid_id', 'Invalid post ID.', 404 );
	}
	if ( 'edit' === $context && ! minn_user_can( $uid, 'edit_post', $id ) ) {
		minn_rest_error( 'rest_forbidden_context', 'Sorry, you are not allowed to edit this post.', $uid ? 403 : 401 );
	}
	minn_rest_send( minn_rest_media_object( $p, 'edit' === $context, $uid ) );
}

/** Allowed upload types: extension → canonical mime. */
function minn_media_mime_map(): array {
	return array(
		'png'  => 'image/png',
		'jpg'  => 'image/jpeg',
		'jpeg' => 'image/jpeg',
		'gif'  => 'image/gif',
		'webp' => 'image/webp',
		'svg'  => 'image/svg+xml',
		'pdf'  => 'application/pdf',
		'txt'  => 'text/plain',
		'mp4'  => 'video/mp4',
		'mp3'  => 'audio/mpeg',
		'zip'  => 'application/zip',
	);
}

/** POST /wp/v2/media — multipart field `file`, or raw body + Content-Disposition. */
function minn_rest_media_create(): void {
	global $table_prefix;
	$why  = '';
	$auth = minn_authenticate_rest( $why );
	if ( null === $auth ) {
		if ( 'rest_cookie_invalid_nonce' === $why ) {
			minn_rest_error( 'rest_cookie_invalid_nonce', 'Cookie check failed', 403 );
		}
		minn_rest_error( 'rest_cannot_create', 'Sorry, you are not allowed to create posts as this user.', 401 );
	}
	$uid = (int) $auth[0]['ID'];
	if ( ! minn_user_can( $uid, 'upload_files' ) ) {
		minn_rest_error( 'rest_cannot_create', 'Sorry, you are not allowed to upload media on this site.', 403 );
	}

	// The two transports WordPress accepts.
	$filename = '';
	$tmp      = '';
	$raw      = null;
	if ( ! empty( $_FILES['file']['tmp_name'] ) ) {
		$filename = (string) $_FILES['file']['name'];
		$tmp      = (string) $_FILES['file']['tmp_name'];
	} else {
		$cd = (string) ( $_SERVER['HTTP_CONTENT_DISPOSITION'] ?? '' );
		if ( preg_match( '/filename\*?="?([^";]+)"?/', $cd, $m ) ) {
			$filename = trim( $m[1] );
		}
		$raw = file_get_contents( 'php://input' );
		if ( '' === $filename || false === $raw || '' === $raw ) {
			minn_rest_error( 'rest_upload_no_data', 'No data supplied.', 400 );
		}
	}

	// Sanitize the name and validate the type by extension.
	$filename = strtolower( preg_replace( '/[^A-Za-z0-9._-]+/', '-', basename( $filename ) ) );
	$ext      = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
	$mimes    = minn_media_mime_map();
	if ( ! isset( $mimes[ $ext ] ) ) {
		minn_rest_error( 'rest_upload_unknown_error', 'Sorry, you are not allowed to upload this file type.', 500 );
	}
	$mime = $mimes[ $ext ];

	// Land it in the dated directory with a unique name.
	$subdir = gmdate( 'Y/m', time() + minn_gmt_offset() );
	$dir    = minn_uploads_basedir() . '/' . $subdir;
	if ( ! is_dir( $dir ) ) {
		mkdir( $dir, 0755, true );
	}
	$stem   = preg_replace( '/\.[^.]+$/', '', $filename );
	$try    = $filename;
	$n      = 0;
	while ( file_exists( "$dir/$try" ) ) {
		$n++;
		$try = "{$stem}-{$n}.{$ext}";
	}
	$filename = $try;
	$path     = "$dir/$filename";
	if ( null !== $raw ) {
		file_put_contents( $path, $raw );
	} else {
		rename( $tmp, $path );
	}

	$is_image = str_starts_with( $mime, 'image/' ) && 'image/svg+xml' !== $mime;
	$rel      = "$subdir/$filename";
	$url      = minn_uploads_baseurl() . '/' . $rel;

	$title   = preg_replace( '/\.[^.]+$/', '', $filename );
	$slug    = minn_sanitize_slug( $title );
	$now_gmt = gmdate( 'Y-m-d H:i:s' );
	$now     = gmdate( 'Y-m-d H:i:s', time() + minn_gmt_offset() );
	$parent  = (int) ( $_POST['post'] ?? $_GET['post'] ?? 0 );

	$stmt = minn_db()->prepare(
		"INSERT INTO {$table_prefix}posts
		 (post_author, post_date, post_date_gmt, post_content, post_title, post_excerpt, post_status,
		  comment_status, ping_status, post_password, post_name, to_ping, pinged, post_modified,
		  post_modified_gmt, post_content_filtered, post_parent, guid, menu_order, post_type,
		  post_mime_type, comment_count)
		 VALUES (?, ?, ?, '', ?, '', 'inherit', 'open', 'closed', '', ?, '', '', ?, ?, '', ?, ?, 0, 'attachment', ?, 0)"
	);
	$stmt->bind_param( 'issssssiss', $uid, $now, $now_gmt, $title, $slug, $now, $now_gmt, $parent, $url, $mime );
	$stmt->execute();
	$id = (int) minn_db()->insert_id;

	minn_post_meta_insert( $id, '_wp_attached_file', $rel );
	if ( $is_image ) {
		[ $w, $h ] = getimagesize( $path ) ?: array( 0, 0 );
		$meta      = array(
			'width'      => (int) $w,
			'height'     => (int) $h,
			'file'       => $rel,
			'filesize'   => (int) filesize( $path ),
			'sizes'      => minn_media_make_subsizes( $path, $mime ),
			'image_meta' => array(
				'aperture'          => '0',
				'credit'            => '',
				'camera'            => '',
				'caption'           => '',
				'created_timestamp' => '0',
				'copyright'         => '',
				'focal_length'      => '0',
				'iso'               => '0',
				'shutter_speed'     => '0',
				'title'             => '',
				'orientation'       => '0',
				'keywords'          => array(),
				'alt'               => '',
			),
		);
		minn_post_meta_insert( $id, '_wp_attachment_metadata', minn_media_meta_serialize( $meta ) );
	}

	header( 'Location: ' . minn_rest_url( '/wp/v2/media/' . $id ) );
	minn_rest_send( minn_rest_media_object( minn_media_row( $id ), true, $uid ), 201 );
}

function minn_post_meta_insert( int $post_id, string $key, string $value ): void {
	global $table_prefix;
	$stmt = minn_db()->prepare(
		"INSERT INTO {$table_prefix}postmeta (post_id, meta_key, meta_value) VALUES (?, ?, ?)"
	);
	$stmt->bind_param( 'iss', $post_id, $key, $value );
	$stmt->execute();
}

function minn_post_meta_upsert( int $post_id, string $key, string $value ): void {
	global $table_prefix;
	if ( null === minn_post_meta_value( $post_id, $key ) ) {
		minn_post_meta_insert( $post_id, $key, $value );
		return;
	}
	$stmt = minn_db()->prepare(
		"UPDATE {$table_prefix}postmeta SET meta_value = ? WHERE post_id = ? AND meta_key = ?"
	);
	$stmt->bind_param( 'sis', $value, $post_id, $key );
	$stmt->execute();
}

/** POST/PUT/PATCH /wp/v2/media/{id} — the editable fields the app uses. */
function minn_rest_media_update( int $id ): void {
	global $table_prefix;
	$uid = minn_current_user_id();
	$p   = minn_media_row( $id );
	if ( ! $p ) {
		minn_rest_error( 'rest_post_invalid_id', 'Invalid post ID.', 404 );
	}
	if ( ! minn_user_can( $uid, 'edit_post', $id ) ) {
		minn_rest_error( 'rest_cannot_edit', 'Sorry, you are not allowed to edit this post.', $uid ? 403 : 401 );
	}
	$body = minn_request_body();

	$map = array();
	if ( isset( $body['title'] ) ) {
		$map['post_title'] = is_array( $body['title'] ) ? (string) ( $body['title']['raw'] ?? '' ) : (string) $body['title'];
	}
	if ( isset( $body['caption'] ) ) {
		$map['post_excerpt'] = is_array( $body['caption'] ) ? (string) ( $body['caption']['raw'] ?? '' ) : (string) $body['caption'];
	}
	if ( isset( $body['description'] ) ) {
		$map['post_content'] = is_array( $body['description'] ) ? (string) ( $body['description']['raw'] ?? '' ) : (string) $body['description'];
	}
	if ( array_key_exists( 'post', $body ) ) {
		$map['post_parent'] = (int) $body['post'];
	}
	foreach ( $map as $column => $value ) {
		$stmt = minn_db()->prepare( "UPDATE {$table_prefix}posts SET {$column} = ? WHERE ID = ?" );
		$stmt->bind_param( 'si', $value, $id );
		$stmt->execute();
	}
	if ( isset( $body['alt_text'] ) ) {
		minn_post_meta_upsert( $id, '_wp_attachment_image_alt', (string) $body['alt_text'] );
	}
	if ( $map ) {
		$now     = gmdate( 'Y-m-d H:i:s', time() + minn_gmt_offset() );
		$now_gmt = gmdate( 'Y-m-d H:i:s' );
		$stmt    = minn_db()->prepare( "UPDATE {$table_prefix}posts SET post_modified = ?, post_modified_gmt = ? WHERE ID = ?" );
		$stmt->bind_param( 'ssi', $now, $now_gmt, $id );
		$stmt->execute();
	}

	minn_rest_send( minn_rest_media_object( minn_media_row( $id ), true, $uid ) );
}

/** DELETE /wp/v2/media/{id}?force=true — attachments cannot be trashed. */
function minn_rest_media_delete( int $id, bool $force ): void {
	global $table_prefix;
	$uid = minn_current_user_id();
	$p   = minn_media_row( $id );
	if ( ! $p ) {
		minn_rest_error( 'rest_post_invalid_id', 'Invalid post ID.', 404 );
	}
	if ( ! minn_user_can( $uid, 'delete_post', $id ) ) {
		minn_rest_error( 'rest_cannot_delete', 'Sorry, you are not allowed to delete this post.', $uid ? 403 : 401 );
	}
	if ( ! $force ) {
		minn_rest_error( 'rest_trash_not_supported', "The post does not support trashing. Set 'force=true' to delete.", 501 );
	}

	$previous = minn_rest_media_object( $p, true, $uid );

	// Remove the files: original plus every generated size.
	$file = minn_post_meta_value( $id, '_wp_attached_file' );
	if ( null !== $file && '' !== $file ) {
		$dir  = minn_uploads_basedir() . '/' . dirname( $file );
		$meta = minn_media_meta_parse( minn_post_meta_value( $id, '_wp_attachment_metadata' ) );
		foreach ( $meta['sizes'] as $sz ) {
			@unlink( $dir . '/' . $sz['file'] );
		}
		@unlink( minn_uploads_basedir() . '/' . $file );
	}

	foreach ( array( 'postmeta WHERE post_id', 'posts WHERE ID' ) as $where ) {
		$stmt = minn_db()->prepare( "DELETE FROM {$table_prefix}{$where} = ?" );
		$stmt->bind_param( 'i', $id );
		$stmt->execute();
	}

	minn_rest_send( array( 'deleted' => true, 'previous' => $previous ) );
}
