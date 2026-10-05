<?php
/**
 * The round trip's machinery, shared by tests/round-trip.test.php.
 *
 * A snapshot is a site's database as rows keyed by what they are: options by
 * name, meta by owner and key (with a count for repeats), everything else by
 * its primary key. A footprint is what changed between two snapshots, written
 * so two stacks' days can be laid side by side: times inside the run read
 * {now}, password and token hashes read {hash}, and the rows a day created
 * read by kind and order (attachment#1, revision#2) wherever their IDs
 * appear, so one missing row does not renumber everything after it.
 */

/** Meta tables: the column naming the owner, and the table the owner lives in. */
const RT_META = array(
	'postmeta'    => array( 'post_id', 'posts' ),
	'usermeta'    => array( 'user_id', 'users' ),
	'commentmeta' => array( 'comment_id', 'comments' ),
	'termmeta'    => array( 'term_id', 'terms' ),
);

/** Tables whose new rows get labels: the ID column and the column naming their kind. */
const RT_LABELLED = array(
	'posts'         => array( 'ID', 'post_type' ),
	'comments'      => array( 'comment_ID', null ),
	'terms'         => array( 'term_id', null ),
	'term_taxonomy' => array( 'term_taxonomy_id', 'taxonomy' ),
	'users'         => array( 'ID', null ),
);

/** Columns that hold another row's ID, and the table that row lives in. */
const RT_REFS = array(
	'posts'              => array( 'ID' => 'posts', 'post_parent' => 'posts', 'post_author' => 'users' ),
	'postmeta'           => array( 'post_id' => 'posts' ),
	'comments'           => array( 'comment_ID' => 'comments', 'comment_post_ID' => 'posts', 'comment_parent' => 'comments', 'user_id' => 'users' ),
	'commentmeta'        => array( 'comment_id' => 'comments' ),
	'terms'              => array( 'term_id' => 'terms' ),
	'term_taxonomy'      => array( 'term_taxonomy_id' => 'term_taxonomy', 'term_id' => 'terms', 'parent' => 'terms' ),
	'term_relationships' => array( 'object_id' => 'posts', 'term_taxonomy_id' => 'term_taxonomy' ),
	'termmeta'           => array( 'term_id' => 'terms' ),
	'users'              => array( 'ID' => 'users' ),
	'usermeta'           => array( 'user_id' => 'users' ),
);

/** Auto-increment columns that only number rows (nothing points at them); they never compare. */
const RT_SURROGATE = array( 'options' => 'option_id', 'postmeta' => 'meta_id', 'usermeta' => 'umeta_id', 'commentmeta' => 'meta_id', 'termmeta' => 'meta_id' );

/** Post meta whose value is another row's ID. */
const RT_META_REFS = array( '_thumbnail_id' => 'posts', '_edit_last' => 'users', '_menu_item_object_id' => 'posts', '_wp_trash_meta_status' => null );

/** A browser of sorts: cookies kept, redirects not followed, a REST nonce once signed in. */
final class RtClient {
	public array $cookies = array();
	public string $nonce  = '';

	/** @param list<string> $headers sent with every request (a Host for the oracle) */
	public function __construct( public readonly string $base, private readonly array $headers = array() ) {}

	/** @return array{0: int, 1: array<string, list<string>>, 2: string} */
	public function request( string $method, string $path, ?string $body = null, array $headers = array() ): array {
		$received = array();
		$all      = array_merge( $this->headers, $headers );
		if ( $this->cookies ) {
			$all[] = 'Cookie: ' . implode( '; ', array_map( static fn ( $k, $v ) => "$k=$v", array_keys( $this->cookies ), $this->cookies ) );
		}
		$ch = curl_init( $this->base . $path );
		curl_setopt_array(
			$ch,
			array(
				CURLOPT_CUSTOMREQUEST  => $method,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_FOLLOWLOCATION => false,
				CURLOPT_SSL_VERIFYPEER => false,
				CURLOPT_SSL_VERIFYHOST => 0,
				CURLOPT_TIMEOUT        => 90,
				// Over IPv4 on both stacks, so the visitor is 127.0.0.1 to each (plugins key visitors by address).
				CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
				CURLOPT_HTTPHEADER     => $all,
				CURLOPT_HEADERFUNCTION => static function ( $ch, string $line ) use ( &$received ): int {
					if ( str_contains( $line, ':' ) ) {
						[ $k, $v ] = explode( ':', $line, 2 );
						$received[ strtolower( trim( $k ) ) ][] = trim( $v );
					}
					return strlen( $line );
				},
			)
		);
		if ( null !== $body ) {
			curl_setopt( $ch, CURLOPT_POSTFIELDS, $body );
		}
		$raw    = (string) curl_exec( $ch );
		$status = (int) curl_getinfo( $ch, CURLINFO_RESPONSE_CODE );
		foreach ( $received['set-cookie'] ?? array() as $cookie ) {
			$this->keep( $cookie );
		}
		return array( $status, $received, $raw );
	}

	/** A REST call as whoever is signed in. @return array{0: int, 1: mixed} */
	public function rest( string $method, string $route, ?array $json = null ): array {
		$headers = array( 'Content-Type: application/json' );
		if ( '' !== $this->nonce ) {
			$headers[] = 'X-WP-Nonce: ' . $this->nonce;
		}
		[ $status, , $raw ] = $this->request( $method, '/wp-json' . $route, null === $json ? null : json_encode( $json ), $headers );
		return array( $status, json_decode( $raw, true ) );
	}

	/** A file sent the way the media library sends one. @return array{0: int, 1: mixed} */
	public function upload( string $name, string $type, string $bytes ): array {
		$headers            = array( "Content-Type: $type", "Content-Disposition: attachment; filename=\"$name\"", 'X-WP-Nonce: ' . $this->nonce );
		[ $status, , $raw ] = $this->request( 'POST', '/wp-json/wp/v2/media', $bytes, $headers );
		return array( $status, json_decode( $raw, true ) );
	}

	/** Signs in through wp-login.php and fetches a REST nonce; true when both worked. */
	public function signIn( string $user, string $password, string $site ): bool {
		$this->cookies['wordpress_test_cookie'] = 'WP%20Cookie%20check';
		$form       = http_build_query( array( 'log' => $user, 'pwd' => $password, 'rememberme' => 'forever', 'wp-submit' => 'Log In', 'redirect_to' => "$site/wp-admin/", 'testcookie' => '1' ) );
		[ $status ] = $this->request( 'POST', '/wp-login.php', $form, array( 'Content-Type: application/x-www-form-urlencoded' ) );
		if ( 302 !== $status || ! $this->hasCookie( 'wordpress_logged_in_' ) ) {
			return false;
		}
		return $this->fetchNonce();
	}

	/** The REST nonce for the session the cookies carry. */
	public function fetchNonce(): bool {
		[ , , $nonce ] = $this->request( 'GET', '/wp-admin/admin-ajax.php?action=rest-nonce' );
		$this->nonce   = trim( $nonce );
		return 1 === preg_match( '/^[0-9a-f]{10}$/', $this->nonce );
	}

	public function hasCookie( string $prefix ): bool {
		foreach ( array_keys( $this->cookies ) as $name ) {
			if ( str_starts_with( $name, $prefix ) ) {
				return true;
			}
		}
		return false;
	}

	private function keep( string $header ): void {
		$parts         = array_map( 'trim', explode( ';', $header ) );
		[ $name, $value ] = array_pad( explode( '=', array_shift( $parts ), 2 ), 2, '' );
		$expired       = false;
		foreach ( $parts as $part ) {
			[ $k, $v ] = array_pad( explode( '=', $part, 2 ), 2, '' );
			$k         = strtolower( $k );
			$expired   = $expired || ( 'max-age' === $k && (int) $v <= 0 ) || ( 'expires' === $k && strtotime( $v ) < time() );
		}
		if ( $expired || 'deleted' === $value ) {
			unset( $this->cookies[ $name ] );
		} else {
			$this->cookies[ $name ] = $value;
		}
	}
}

/** The site's database connection and table prefix, read through WP-CLI from the parked copy's wp-config.php. @return array{0: mysqli, 1: string} */
function rt_connect( string $root ): array {
	$list = json_decode( (string) shell_exec( 'wp --path=' . escapeshellarg( "$root/wp-reference" ) . ' config list DB_NAME DB_USER DB_PASSWORD DB_HOST table_prefix --strict --format=json 2>/dev/null' ), true );
	$conf = array_column( (array) $list, 'value', 'name' );
	if ( ! isset( $conf['DB_NAME'], $conf['table_prefix'] ) ) {
		throw new RuntimeException( "could not read the database settings for $root" );
	}
	[ $host, $port ] = array_pad( explode( ':', (string) $conf['DB_HOST'], 2 ), 2, null );
	$db              = new mysqli( $host, (string) $conf['DB_USER'], (string) $conf['DB_PASSWORD'], (string) $conf['DB_NAME'], null !== $port && ctype_digit( $port ) ? (int) $port : null, null !== $port && ! ctype_digit( $port ) ? $port : null );
	$db->set_charset( 'utf8mb4' );
	return array( $db, (string) $conf['table_prefix'] );
}

/** Every table with the site's prefix, rows keyed by what they are. @return array<string, array{pk: list<string>, rows: array<string, array<string, ?string>>}> */
function rt_snapshot( mysqli $db, string $prefix ): array {
	$tables = array();
	$like   = $db->real_escape_string( addcslashes( $prefix, '_%\\' ) );
	foreach ( $db->query( "SHOW TABLES LIKE '$like%'" )->fetch_all() as [ $name ] ) {
		$short = substr( $name, strlen( $prefix ) );
		$pk    = array_column( $db->query( "SHOW KEYS FROM `$name` WHERE Key_name = 'PRIMARY'" )->fetch_all( MYSQLI_ASSOC ), 'Column_name' );
		$order = $pk ? ' ORDER BY ' . implode( ', ', array_map( static fn ( $c ) => "`$c`", $pk ) ) : '';
		$rows  = array();
		$seen  = array();
		foreach ( $db->query( "SELECT * FROM `$name`$order" )->fetch_all( MYSQLI_ASSOC ) as $row ) {
			$key          = rt_raw_key( $short, $row, $pk );
			$seen[ $key ] = ( $seen[ $key ] ?? 0 ) + 1;
			$rows[ $seen[ $key ] > 1 ? $key . "\x1f" . $seen[ $key ] : $key ] = $row;
		}
		$tables[ $short ] = array( 'pk' => $pk, 'rows' => $rows );
	}
	return $tables;
}

/** Options by name, meta by owner and key, the rest by primary key (or by content when there is none). */
function rt_raw_key( string $table, array $row, array $pk ): string {
	if ( 'options' === $table ) {
		return (string) $row['option_name'];
	}
	if ( isset( RT_META[ $table ] ) ) {
		return $row[ RT_META[ $table ][0] ] . '/' . $row['meta_key'];
	}
	return $pk ? implode( '/', array_map( static fn ( $c ) => (string) $row[ $c ], $pk ) ) : md5( serialize( $row ) );
}

/** Labels for the rows `after` has and `before` does not, by kind and in ID order. @return array<string, array<string, string>> */
function rt_labels( array $before, array $after ): array {
	$labels = array();
	foreach ( RT_LABELLED as $table => [ $id, $kind ] ) {
		$old = array();
		foreach ( $before[ $table ]['rows'] ?? array() as $row ) {
			$old[ $row[ $id ] ] = true;
		}
		$new = array_filter( $after[ $table ]['rows'] ?? array(), static fn ( $row ) => ! isset( $old[ $row[ $id ] ] ) );
		usort( $new, static fn ( $a, $b ) => (int) $a[ $id ] <=> (int) $b[ $id ] );
		$count = array();
		foreach ( $new as $row ) {
			$name                               = null === $kind ? rtrim( $table, 's' ) : $row[ $kind ];
			$count[ $name ]                     = ( $count[ $name ] ?? 0 ) + 1;
			$labels[ $table ][ (string) $row[ $id ] ] = $name . '#' . $count[ $name ];
		}
	}
	return $labels;
}

/** A row ID as the footprint writes it: its label when the day made it. */
function rt_id( array $labels, string $table, $id ): string {
	return isset( $labels[ $table ][ (string) $id ] ) ? '{' . $labels[ $table ][ (string) $id ] . '}' : (string) $id;
}

/** The run's span in seconds, and the site's time zone for local times. */
function rt_window( int $from, int $to, string $timezone ): array {
	return array( $from - 120, $to + 120, new DateTimeZone( $timezone ) );
}

/** A value as the footprint writes it: IDs labelled, run times as {now}, hashes as {hash}. */
function rt_value( string $table, string $col, ?string $value, array $labels, array $window, array $row = array() ): string {
	if ( null === $value ) {
		return 'NULL';
	}
	if ( ( RT_SURROGATE[ $table ] ?? null ) === $col ) {
		return '{id}';
	}
	$ref = RT_REFS[ $table ][ $col ] ?? null;
	if ( 'postmeta' === $table && 'meta_value' === $col ) {
		$ref = RT_META_REFS[ $row['meta_key'] ?? '' ] ?? null;
	}
	if ( null !== $ref && ctype_digit( $value ) ) {
		return rt_id( $labels, $ref, $value );
	}
	return rt_text( $value, $labels, $window );
}

function rt_text( string $value, array $labels, array $window ): string {
	$value = (string) preg_replace(
		array( '#\$wp\$2y\$\d\d\$[./A-Za-z0-9]{53}#', '#\$2y\$\d\d\$[./A-Za-z0-9]{53}#', '#\$P\$[./A-Za-z0-9]{31}#', '#\$generic\$[A-Za-z0-9_-]{40}#', '/\b[0-9a-f]{64}\b/', '/\b[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\b/' ),
		array( '{hash}', '{hash}', '{hash}', '{hash}', '{sha256}', '{uuid}' ),
		$value
	);
	$value = (string) preg_replace( array( '/\b127\.0\.0\.1\b/', '/(?<![\w:])::1(?![\w:])/' ), '{loopback}', $value );
	$value = (string) preg_replace_callback( '/\b\d{4}-\d\d-\d\d[ T]\d\d:\d\d:\d\d\b/', static fn ( $m ) => rt_in_window( $m[0], $window ) ? '{now}' : $m[0], $value );
	$value = (string) preg_replace_callback( '/\b1[6-9]\d{8}(?:\.\d+)?\b/', static fn ( $m ) => rt_stamp( (float) $m[0], $window ) ?? $m[0], $value );
	$value = (string) preg_replace_callback( '/(wp-image-|"id":|[?&]p=|[?&]page_id=|attachment_id=|"ref":)(\d+)/', static fn ( $m ) => $m[1] . rt_id( $labels, 'posts', $m[2] ), $value );
	return (string) preg_replace_callback( '/^(\d+)-(revision|autosave)-v1$/', static fn ( $m ) => rt_id( $labels, 'posts', $m[1] ) . "-{$m[2]}-v1", $value );
}

/** A Unix time inside the run, or a common span after it (sessions, nonces, schedules): {now}, {now+14d}. */
function rt_stamp( float $at, array $window ): ?string {
	foreach ( array( '' => 0, '+1h' => 3600, '+12h' => 43200, '+1d' => 86400, '+2d' => 172800, '+7d' => 604800, '+14d' => 1209600, '+30d' => 2592000, '+1y' => 31536000 ) as $label => $span ) {
		if ( $at - $span >= $window[0] && $at - $span <= $window[1] ) {
			return '{now' . $label . '}';
		}
	}
	return null;
}

/** A database time inside the run, read as UTC or as the site's local time. */
function rt_in_window( string $time, array $window ): bool {
	foreach ( array( new DateTimeZone( 'UTC' ), $window[2] ) as $zone ) {
		$at = ( new DateTimeImmutable( str_replace( 'T', ' ', $time ), $zone ) )->getTimestamp();
		if ( $at >= $window[0] && $at <= $window[1] ) {
			return true;
		}
	}
	return false;
}

/** A row key as the footprint writes it. */
function rt_key( string $table, array $row, string $raw, array $labels ): string {
	$repeat = str_contains( $raw, "\x1f" ) ? '#' . substr( $raw, strrpos( $raw, "\x1f" ) + 1 ) : '';
	if ( isset( RT_META[ $table ] ) ) {
		[ $owner, $in ] = RT_META[ $table ];
		return rt_id( $labels, $in, $row[ $owner ] ) . '/' . $row['meta_key'] . $repeat;
	}
	if ( 'term_relationships' === $table ) {
		return rt_id( $labels, 'posts', $row['object_id'] ) . '/' . rt_id( $labels, 'term_taxonomy', $row['term_taxonomy_id'] );
	}
	if ( isset( RT_LABELLED[ $table ] ) ) {
		return rt_id( $labels, $table, $row[ RT_LABELLED[ $table ][0] ] );
	}
	return str_replace( "\x1f", '#', $raw );
}

/**
 * What changed from one snapshot to the next, one line per row: "added"
 * with the row, "removed", or the columns that changed (old → new).
 *
 * @return array<string, string> "table key" => what happened
 */
function rt_footprint( array $before, array $after, array $window ): array {
	$labels = rt_labels( $before, $after );
	$out    = array();
	foreach ( array_unique( array_merge( array_keys( $before ), array_keys( $after ) ) ) as $table ) {
		if ( ! isset( $after[ $table ] ) || ! isset( $before[ $table ] ) ) {
			$out[ "$table (table)" ] = isset( $after[ $table ] ) ? 'created' : 'dropped';
		}
		$old = $before[ $table ]['rows'] ?? array();
		$new = $after[ $table ]['rows'] ?? array();
		foreach ( $new as $raw => $row ) {
			$was = $old[ $raw ] ?? null;
			if ( $was === $row ) {
				continue;
			}
			$line = null === $was ? 'added ' . rt_row_text( $table, $row, $labels, $window ) : rt_changes( $table, $was, $row, $labels, $window );
			if ( '' !== $line ) {
				$out[ $table . ' ' . rt_key( $table, $row, (string) $raw, $labels ) ] = $line;
			}
		}
		foreach ( array_diff_key( $old, $new ) as $raw => $row ) {
			$out[ $table . ' ' . rt_key( $table, $row, (string) $raw, $labels ) ] = 'removed';
		}
	}
	ksort( $out );
	return $out;
}

function rt_row_text( string $table, array $row, array $labels, array $window ): string {
	$cells = array();
	foreach ( $row as $col => $value ) {
		$cells[ $col ] = rt_value( $table, $col, $value, $labels, $window, $row );
	}
	return json_encode( $cells, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
}

function rt_changes( string $table, array $was, array $row, array $labels, array $window ): string {
	$lines = array();
	foreach ( $row as $col => $value ) {
		if ( ( $was[ $col ] ?? null ) === $value || ( RT_SURROGATE[ $table ] ?? null ) === $col ) {
			continue;
		}
		$old  = rt_value( $table, $col, $was[ $col ] ?? null, $labels, $window, $was );
		$new  = rt_value( $table, $col, $value, $labels, $window, $row );
		$tree = rt_tree_changes( $was[ $col ] ?? null, $value, $labels, $window );
		if ( null !== $tree ) {
			$lines[] = "$col: " . ( $tree ? implode( '; ', $tree ) : 'rewritten' );
		} else {
			$lines[] = $old !== $new ? "$col: $old → $new" : "$col: rewritten";
		}
	}
	return implode( "\n", $lines );
}

/**
 * Two serialized arrays compared leaf by leaf ("[key][key]: old → new"),
 * keys and leaves normalized like any other value. Null when either side is
 * not a serialized array.
 *
 * @return list<string>|null
 */
function rt_tree_changes( ?string $old, ?string $new, array $labels, array $window ): ?array {
	$a = null === $old ? false : @unserialize( $old, array( 'allowed_classes' => false ) );
	$b = null === $new ? false : @unserialize( $new, array( 'allowed_classes' => false ) );
	if ( ! is_array( $a ) || ! is_array( $b ) ) {
		return null;
	}
	$flat = static function ( array $tree, string $path = '' ) use ( &$flat, $labels, $window ): array {
		$out = array();
		foreach ( $tree as $k => $v ) {
			$at = $path . '[' . rt_text( (string) $k, $labels, $window ) . ']';
			if ( is_array( $v ) && $v ) {
				$out += $flat( $v, $at );
			} else {
				$text = is_array( $v ) ? '[]' : rt_text( var_export( $v, true ), $labels, $window );
				$out[ isset( $out[ $at ] ) ? $at . '+' : $at ] = $text;
			}
		}
		return $out;
	};
	$x     = $flat( $a );
	$y     = $flat( $b );
	$lines = array();
	foreach ( array_unique( array_merge( array_keys( $x ), array_keys( $y ) ) ) as $at ) {
		if ( ( $x[ $at ] ?? null ) !== ( $y[ $at ] ?? null ) ) {
			$lines[] = $at . ': ' . ( $x[ $at ] ?? '(none)' ) . ' → ' . ( $y[ $at ] ?? '(none)' );
		}
	}
	sort( $lines );
	return $lines;
}

/**
 * Cells a day wrote that hold serialized PHP that no longer reads back: the
 * one way a value can be present and still lost.
 *
 * @return list<string> "table key column"
 */
function rt_broken_serialized( array $before, array $after ): array {
	$broken = array();
	foreach ( $after as $table => $t ) {
		foreach ( $t['rows'] as $raw => $row ) {
			foreach ( $row as $col => $value ) {
				if ( null === $value || ( $before[ $table ]['rows'][ $raw ][ $col ] ?? null ) === $value ) {
					continue;
				}
				if ( 1 === preg_match( '/^(?:a:\d+:\{|O:\d+:"|s:\d+:"|i:-?\d+;|b:[01];|d:[-\d.E+]+;|N;)/', $value ) && false === @unserialize( $value, array( 'allowed_classes' => false ) ) && 'b:0;' !== $value ) {
					$broken[] = "$table $raw $col";
				}
			}
		}
	}
	return $broken;
}

/** Every file and folder under a directory, with size and modification time. @return array<string, string> */
function rt_files( string $dir ): array {
	$list = array();
	if ( ! is_dir( $dir ) ) {
		return $list;
	}
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::SELF_FIRST );
	foreach ( $it as $file ) {
		$rel          = substr( $file->getPathname(), strlen( $dir ) + 1 );
		$list[ $rel ] = $file->isDir() ? 'dir' : $file->getSize() . ':' . $file->getMTime();
	}
	return $list;
}

/** The files a day added or changed, images by their dimensions. @return array<string, string> */
function rt_file_footprint( string $dir, array $before, array $after ): array {
	$out = array();
	foreach ( $after as $rel => $stamp ) {
		if ( 'dir' === $stamp || ( $before[ $rel ] ?? null ) === $stamp ) {
			continue;
		}
		$size        = @getimagesize( "$dir/$rel" );
		$out[ $rel ] = ( isset( $before[ $rel ] ) ? 'changed ' : 'added ' ) . ( $size ? $size[0] . 'x' . $size[1] : 'file' );
	}
	foreach ( array_diff_key( $before, $after ) as $rel => $stamp ) {
		$out[ $rel ] = 'removed';
	}
	ksort( $out );
	return $out;
}

/** Removes what a day added under a directory: new files, then new folders, deepest first. */
function rt_remove_added( string $dir, array $before, array $after ): void {
	$added = array_diff_key( $after, $before );
	krsort( $added );
	foreach ( $added as $rel => $stamp ) {
		'dir' === $stamp ? @rmdir( "$dir/$rel" ) : @unlink( "$dir/$rel" );
	}
}

/** Puts the database back to the baseline dump and drops any table a day created. */
function rt_restore( string $root, string $dump, mysqli $db, string $prefix, array $keep ): void {
	exec( 'wp --path=' . escapeshellarg( "$root/wp-reference" ) . ' db import ' . escapeshellarg( $dump ) . ' 2>&1', $output, $code );
	if ( 0 !== $code ) {
		throw new RuntimeException( 'restore failed: ' . implode( "\n", $output ) );
	}
	$like = $db->real_escape_string( addcslashes( $prefix, '_%\\' ) );
	foreach ( $db->query( "SHOW TABLES LIKE '$like%'" )->fetch_all() as [ $name ] ) {
		if ( ! in_array( substr( $name, strlen( $prefix ) ), $keep, true ) ) {
			$db->query( "DROP TABLE `$name`" );
		}
	}
}

/** A photo made on the spot (no binary in the repository): a dusk gradient, a sun, a horizon. Same bytes every run. */
function rt_photo(): string {
	$img = imagecreatetruecolor( 1600, 1000 );
	for ( $y = 0; $y < 1000; $y++ ) {
		$t = $y / 1000;
		imageline( $img, 0, $y, 1599, $y, imagecolorallocate( $img, (int) ( 40 + 200 * $t ), (int) ( 60 + 80 * $t ), (int) ( 120 - 60 * $t ) ) );
	}
	imagefilledellipse( $img, 1100, 620, 260, 260, imagecolorallocate( $img, 250, 200, 120 ) );
	imagefilledrectangle( $img, 0, 700, 1599, 999, imagecolorallocate( $img, 20, 40, 70 ) );
	ob_start();
	imagejpeg( $img, null, 82 );
	return (string) ob_get_clean();
}

/** The first difference between two strings, with a little context either side. */
function rt_show( string $a, string $b = '', int $width = 160 ): string {
	if ( '' === $b ) {
		return mb_strlen( $a ) > $width ? mb_substr( $a, 0, $width ) . '…' : $a;
	}
	$at = strspn( $a ^ $b, "\0" );
	$at = max( 0, $at - 40 );
	return '…' . mb_strcut( $a, $at, $width ) . "\n         vs …" . mb_strcut( $b, $at, $width );
}

/**
 * A visitor's half hour: the front page, a post, the feed, a search, a page
 * that is not there, the REST index of posts, the sign-in form. Nothing here
 * should write. @return array<string, int> path => status
 */
function rt_browse( RtClient $c ): array {
	[ , $posts ] = $c->rest( 'GET', '/wp/v2/posts?per_page=1&orderby=id&order=asc' );
	$post        = (string) parse_url( (string) ( $posts[0]['link'] ?? '/' ), PHP_URL_PATH );
	$seen        = array();
	foreach ( array( '/', $post, '/feed/', '/?s=harbor', '/no-such-page/', '/wp-json/wp/v2/posts', '/wp-login.php' ) as $path ) {
		[ $seen[ $path ] ] = $c->request( 'GET', $path );
	}
	return $seen;
}

/**
 * The owner's day, the same requests on either stack: sign in, change the
 * tagline, revise the oldest post, upload a photo and describe it, file a
 * category and a tag, publish a post that uses all of them, comment and
 * reply, revise a page, update the profile, add an editor, trash a draft.
 *
 * @return array{steps: array<string, array{status: int, ok: bool}>, ids: array<string, int>}
 */
function rt_day( RtClient $c, array $cfg, string $photo ): array {
	$steps = array();
	$ids   = array();
	$step  = static function ( string $name, int $status, bool $ok ) use ( &$steps ): bool {
		$steps[ $name ] = array( 'status' => $status, 'ok' => $ok );
		return $ok;
	};
	if ( ! $step( 'signs in', 302, $c->signIn( $cfg['user'], $cfg['password'], $cfg['url'] ) ) ) {
		return array( 'steps' => $steps, 'ids' => $ids );
	}

	[ $s, $r ] = $c->rest( 'POST', '/wp/v2/settings', array( 'description' => 'Notes from a round trip' ) );
	$step( 'changes the tagline', $s, 200 === $s && 'Notes from a round trip' === ( $r['description'] ?? null ) );
	$front = (int) ( $r['page_on_front'] ?? 0 );

	[ , $list ] = $c->rest( 'GET', '/wp/v2/posts?per_page=1&orderby=id&order=asc&context=edit' );
	$post       = $list[0] ?? array( 'id' => 0, 'title' => array( 'raw' => '' ), 'content' => array( 'raw' => '' ) );
	$ids['revised'] = (int) $post['id'];
	[ $s ]      = $c->rest( 'POST', "/wp/v2/posts/{$post['id']}", array( 'title' => $post['title']['raw'] . ' (revised)', 'content' => $post['content']['raw'] . "\n\n<!-- wp:paragraph -->\n<p>Revised on the round trip.</p>\n<!-- /wp:paragraph -->" ) );
	$step( 'revises the oldest post', $s, 200 === $s );

	[ $s, $media ] = $c->upload( 'round-trip-harbor.jpg', 'image/jpeg', $photo );
	$ids['photo']  = (int) ( $media['id'] ?? 0 );
	$step( 'uploads a photo', $s, 201 === $s && $ids['photo'] > 0 );
	[ $s ]         = $c->rest( 'POST', "/wp/v2/media/{$ids['photo']}", array( 'title' => 'Harbor at dusk', 'alt_text' => 'A harbor at dusk, the sun low over the water', 'caption' => 'Shot for the round trip.' ) );
	$step( 'describes the photo', $s, 200 === $s );

	[ $s, $cat ]     = $c->rest( 'POST', '/wp/v2/categories', array( 'name' => 'Field Notes', 'description' => 'Notes from the field.' ) );
	$ids['category'] = (int) ( $cat['id'] ?? 0 );
	$step( 'adds a category', $s, 201 === $s );
	[ $s, $tag ]     = $c->rest( 'POST', '/wp/v2/tags', array( 'name' => 'Round Trip' ) );
	$ids['tag']      = (int) ( $tag['id'] ?? 0 );
	$step( 'adds a tag', $s, 201 === $s );

	$src           = (string) ( $media['media_details']['sizes']['large']['source_url'] ?? $media['source_url'] ?? '' );
	$content       = "<!-- wp:paragraph -->\n<p>The harbor at dusk, from the end of the pier.</p>\n<!-- /wp:paragraph -->\n\n"
		. "<!-- wp:image {\"id\":{$ids['photo']},\"sizeSlug\":\"large\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-large\"><img src=\"$src\" alt=\"A harbor at dusk, the sun low over the water\" class=\"wp-image-{$ids['photo']}\"/><figcaption class=\"wp-element-caption\">Shot for the round trip.</figcaption></figure>\n<!-- /wp:image -->";
	[ $s, $new ]   = $c->rest( 'POST', '/wp/v2/posts', array( 'title' => 'Notes from the harbor', 'status' => 'publish', 'excerpt' => 'A short one about the harbor.', 'content' => $content, 'categories' => array( $ids['category'] ), 'tags' => array( $ids['tag'] ), 'featured_media' => $ids['photo'] ) );
	$ids['post']   = (int) ( $new['id'] ?? 0 );
	$step( 'publishes a post', $s, 201 === $s && 'publish' === ( $new['status'] ?? null ) );

	[ $s, $comment ] = $c->rest( 'POST', '/wp/v2/comments', array( 'post' => $ids['post'], 'content' => 'Saving this one for later.' ) );
	$ids['comment']  = (int) ( $comment['id'] ?? 0 );
	$step( 'comments', $s, 201 === $s );
	[ $s, $reply ]   = $c->rest( 'POST', '/wp/v2/comments', array( 'post' => $ids['post'], 'parent' => $ids['comment'], 'content' => 'Me too.' ) );
	$ids['reply']    = (int) ( $reply['id'] ?? 0 );
	$step( 'replies', $s, 201 === $s );

	[ , $pages ]  = $c->rest( 'GET', '/wp/v2/pages?per_page=100&orderby=id&order=asc&context=edit' );
	$pages        = array_values( array_filter( (array) $pages, static fn ( $p ) => (int) $p['id'] !== $front ) );
	$page         = end( $pages ) ?: array( 'id' => 0, 'content' => array( 'raw' => '' ) );
	$ids['page']  = (int) $page['id'];
	[ $s ]        = $c->rest( 'POST', "/wp/v2/pages/{$page['id']}", array( 'content' => $page['content']['raw'] . "\n\n<!-- wp:paragraph -->\n<p>Updated on the round trip.</p>\n<!-- /wp:paragraph -->" ) );
	$step( 'revises a page', $s, 200 === $s );

	[ $s ] = $c->rest( 'POST', '/wp/v2/users/me', array( 'description' => 'Keeps notes on a round trip.' ) );
	$step( 'updates the profile', $s, 200 === $s );

	[ $s, $user ]  = $c->rest( 'POST', '/wp/v2/users', array( 'username' => 'roundtrip-editor', 'email' => 'roundtrip-editor@example.com', 'password' => $cfg['editor_password'], 'roles' => array( 'editor' ), 'first_name' => 'Round', 'last_name' => 'Trip' ) );
	$ids['editor'] = (int) ( $user['id'] ?? 0 );
	$step( 'adds an editor', $s, 201 === $s );

	[ , $drafts ]  = $c->rest( 'GET', '/wp/v2/posts?status=draft&per_page=1&orderby=id&order=asc&context=edit' );
	$ids['draft']  = (int) ( $drafts[0]['id'] ?? 0 );
	[ $s, $gone ]  = $c->rest( 'DELETE', "/wp/v2/posts/{$ids['draft']}" );
	$step( 'trashes a draft', $s, 200 === $s && 'trash' === ( $gone['status'] ?? null ) );

	return array( 'steps' => $steps, 'ids' => $ids );
}
