<?php
/**
 * wp/v2/font-families written: a family created and updated, a face created
 * from remote sources and from uploaded files, and every refusal on the way
 * (the settings, the theme.json version, the caller, the family, duplicates,
 * file types). One scenario runs against the oracle and then against the
 * engine on the shared database: each answer (status, Location, Allow,
 * body), then the rows, meta and font files the run left, are compared, ids
 * read as the run's own names. Throwaway users; every font post and file a
 * run makes is removed after it.
 * Ref: the test site's Cove twin (cove twin minn add --as-site=ref.minn.localhost).
 */

require_once __DIR__ . '/lib.php';

$ENGINE = minn_test_url();
$REF    = minn_test_reference_url();
$ROOT   = dirname( __DIR__ );

$pass = 0;
$fail = 0;

function check( bool $ok, string $label, string $detail = '' ): void {
	global $pass, $fail;
	if ( $ok ) {
		$pass++;
		echo "  ok  $label\n";
	} else {
		$fail++;
		echo "FAIL  $label" . ( $detail ? "\n      " . substr( $detail, 0, 900 ) : '' ) . "\n";
	}
}

function fw_wp( string $command ): string {
	return trim( (string) shell_exec( 'wp --path=' . escapeshellarg( minn_test_site_root() . '/wp-reference' ) . " $command 2>/dev/null" ) );
}

/** The font posts there are now, by id. @return list<int> */
function fw_font_posts(): array {
	return array_values( array_filter( array_map( 'intval', preg_split( '/\s+/', fw_wp( 'post list --post_type=wp_font_family,wp_font_face --post_status=any --format=ids' ) ) ?: array() ) ) );
}

/** The files in the fonts folder. @return list<string> */
function fw_font_files(): array {
	$dir = minn_test_site_root() . '/public/wp-content/uploads/fonts';
	return is_dir( $dir ) ? array_values( array_diff( scandir( $dir ) ?: array(), array( '.', '..' ) ) ) : array();
}

/**
 * One request: a multipart body (fields and files), urlencoded fields, a
 * JSON body, or none. @return array{0: int, 1: array<string, string>, 2: mixed}
 */
function fw_send( string $base, string $method, string $route, ?array $mint, array $body ): array {
	$ch      = curl_init( $base . '/wp-json' . $route );
	$headers = array();
	if ( $mint ) {
		$headers[] = 'Cookie: ' . $mint['cookie_name'] . '=' . $mint['cookie'];
		$headers[] = 'X-WP-Nonce: ' . $mint['nonce'];
	}
	[ $kind, $fields ] = $body + array( 'none', array() );
	$files = $body[2] ?? array();
	if ( 'multipart' === $kind ) {
		foreach ( $files as $field => [ $path, $name ] ) {
			$fields[ $field ] = new CURLFile( $path, '', $name );
		}
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $fields );
	} elseif ( 'urlencoded' === $kind ) {
		curl_setopt( $ch, CURLOPT_POSTFIELDS, http_build_query( $fields ) );
		$headers[] = 'Content-Type: application/x-www-form-urlencoded';
	} elseif ( 'json' === $kind ) {
		curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode( $fields ) );
		$headers[] = 'Content-Type: application/json';
	}
	curl_setopt_array( $ch, array( CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_TIMEOUT => 60 ) );
	$raw    = (string) curl_exec( $ch );
	$status = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
	$split  = (int) curl_getinfo( $ch, CURLINFO_HEADER_SIZE );
	$kept   = array();
	foreach ( explode( "\r\n", substr( $raw, 0, $split ) ) as $line ) {
		if ( preg_match( '/^(location|allow):\s*(.*)$/i', $line, $m ) ) {
			$kept[ strtolower( $m[1] ) ] = trim( $m[2] );
		}
	}
	$text = substr( $raw, $split );
	$data = json_decode( $text, true );
	return array( $status, $kept, null === $data && 'null' !== trim( $text ) ? '[not JSON: ' . strlen( $text ) . ' bytes]' : $data );
}

/** A value with each id the run made read as its name. */
function fw_named( $value, array $names ) {
	if ( is_array( $value ) ) {
		$out = array();
		foreach ( $value as $k => $v ) {
			$out[ $k ] = fw_named( $v, $names );
		}
		return $out;
	}
	if ( is_int( $value ) && isset( $names[ $value ] ) ) {
		return '{' . $names[ $value ] . '}';
	}
	if ( is_string( $value ) ) {
		foreach ( $names as $id => $name ) {
			$value = preg_replace( '/(?<![0-9])' . $id . '(?![0-9])/', '{' . $name . '}', $value );
		}
	}
	return $value;
}

// ---- the fixtures: font files of each kind, and files that are not fonts
$dir = sys_get_temp_dir() . '/minn-font-writes-' . getmypid();
@mkdir( $dir );
$theme_fonts = glob( minn_test_site_root() . '/public/wp-content/themes/twentytwentyfive/assets/fonts/*/*.woff2' ) ?: array();
$real_woff2  = (string) file_get_contents( (string) ( $theme_fonts[0] ?? '' ) );
$fixtures    = array(
	'real.woff2'                => $real_woff2,
	'Inter Variable,Font.woff2' => $real_woff2,
	'evil.php.woff2'            => $real_woff2,
	'UPPER.WOFF2'               => $real_woff2,
	'woff2-as.ttf'              => $real_woff2,
	'magic.woff'                => 'wOFF',
	'magic.otf'                 => 'OTTO',
	'magic.ttf'                 => "\x00\x01\x00\x00",
	'text.woff2'                => 'hello, this is not a font',
	'shell.php'                 => '<?php echo 1;',
);
foreach ( $fixtures as $name => $bytes ) {
	file_put_contents( "$dir/" . md5( $name ), $bytes );
}
$file = static fn ( string $name ): array => array( "$dir/" . md5( $name ), $name );
register_shutdown_function( static function () use ( $dir, $fixtures ): void {
	foreach ( array_keys( $fixtures ) as $name ) {
		@unlink( "$dir/" . md5( $name ) );
	}
	@rmdir( $dir );
} );

[ $ph ] = minn_test_fetch( "$REF/?rest_route=/wp/v2/posts", 3 );
if ( 200 !== $ph['status'] ) {
	echo "SKIP: reference WordPress not running at $REF\n";
	exit( 0 );
}
if ( '' === $real_woff2 ) {
	echo "SKIP: no woff2 in the active theme to upload\n";
	exit( 0 );
}
echo "font-writes suite: $ENGINE (engine) / $REF (oracle)\n";

// ---- throwaway callers: an administrator and an editor (who may not edit theme options)
$suffix = substr( md5( (string) microtime( true ) ), 0, 6 );
$users  = array();
$mints  = array();
foreach ( array( 'admin' => 'administrator', 'editor' => 'editor' ) as $who => $role ) {
	$uid = (int) fw_wp( "user create zzfw{$who}{$suffix} zzfw{$who}{$suffix}@example.invalid --role=$role --porcelain" );
	if ( $uid <= 1 ) {
		echo "SKIP: could not create the $who\n";
		exit( 0 );
	}
	$users[ $who ] = $uid;
	$mint          = json_decode( fw_wp( 'eval-file ' . escapeshellarg( "$ROOT/tests/tools/mint-session.php" ) . " $uid" ), true );
	if ( ! is_array( $mint ) || empty( $mint['cookie'] ) ) {
		echo "SKIP: could not mint a session for the $who\n";
		exit( 0 );
	}
	$mints[ $who ] = $mint;
}
$before_posts = fw_font_posts();
$before_files = fw_font_files();
register_shutdown_function( static function () use ( $users, $mints, $before_posts, $before_files ): void {
	foreach ( array_diff( fw_font_posts(), $before_posts ) as $id ) {
		if ( $id > 1 ) {
			fw_wp( "post delete $id --force" );
		}
	}
	foreach ( array_diff( fw_font_files(), $before_files ) as $name ) {
		@unlink( minn_test_site_root() . '/public/wp-content/uploads/fonts/' . $name );
	}
	foreach ( $users as $who => $uid ) {
		fw_wp( "eval 'WP_Session_Tokens::get_instance($uid)->destroy(\"" . $mints[ $who ]['token'] . "\");'" );
		if ( $uid > 1 ) {
			fw_wp( "user delete $uid --yes" );
		}
	}
} );

// ---- the scenario: [label, method, route, caller, body, name to give the id it makes]
$fam  = static fn ( $settings, array $more = array() ): array => array( 'multipart', array( 'font_family_settings' => is_string( $settings ) ? $settings : json_encode( $settings ) ) + $more );
$face = static fn ( $settings, array $files = array(), array $more = array() ): array => array( 'multipart', array( 'font_face_settings' => is_string( $settings ) ? $settings : json_encode( $settings ) ) + $more, $files );
$min  = array( 'name' => 'ZZ Probe', 'slug' => 'zz-probe', 'fontFamily' => 'ZZProbe' );
$src  = 'https://example.com/fonts/a.woff2';
$steps = array(
	// Creating a family.
	array( 'family: minimal', 'POST', '/wp/v2/font-families', 'admin', $fam( $min ), 'F1' ),
	array( 'family: full, sanitized', 'POST', '/wp/v2/font-families', 'admin', $fam( array( 'name' => '<b>Bold</b> <script>x</script>Name', 'slug' => 'ZZ Upper Slug!', 'fontFamily' => 'Open Sans, Probe2, Probe_Two, Probe-Two, \'Single Q\', serif', 'preview' => 'not a url' ), array( 'theme_json_version' => '2' ) ), 'F2' ),
	array( 'family: preview javascript, html-only name', 'POST', '/wp/v2/font-families', 'admin', $fam( array( 'name' => '<b></b>', 'slug' => 'zz-js', 'fontFamily' => '<b>Foo</b>; color:red', 'preview' => 'javascript:alert(1)' ) ), 'F3' ),
	array( 'family: urlencoded', 'POST', '/wp/v2/font-families', 'admin', array( 'urlencoded', array( 'font_family_settings' => json_encode( array( 'name' => 'ZZ Url', 'slug' => 'zz-url', 'fontFamily' => 'ZZUrl' ) ) ) ), 'F4' ),
	array( 'family: a JSON body naming a string', 'POST', '/wp/v2/font-families', 'admin', array( 'json', array( 'font_family_settings' => json_encode( array( 'name' => 'ZZ Json', 'slug' => 'zz-json', 'fontFamily' => 'ZZJson' ) ) ) ), 'F5' ),
	array( 'family: duplicate slug', 'POST', '/wp/v2/font-families', 'admin', $fam( array( 'slug' => 'ZZ PROBE' ) + $min ) ),
	array( 'family: a slug of symbols matches any family', 'POST', '/wp/v2/font-families', 'admin', $fam( array( 'slug' => '!!!' ) + $min ) ),
	array( 'family: missing name', 'POST', '/wp/v2/font-families', 'admin', $fam( array( 'slug' => 'zz-q', 'fontFamily' => 'Q' ) ) ),
	array( 'family: missing slug and font family', 'POST', '/wp/v2/font-families', 'admin', $fam( array( 'name' => 'Q' ) ) ),
	array( 'family: {}', 'POST', '/wp/v2/font-families', 'admin', $fam( '{}' ) ),
	array( 'family: a JSON list', 'POST', '/wp/v2/font-families', 'admin', $fam( '[1,2]' ) ),
	array( 'family: a JSON string', 'POST', '/wp/v2/font-families', 'admin', $fam( '"hello"' ) ),
	array( 'family: a JSON number', 'POST', '/wp/v2/font-families', 'admin', $fam( '5' ) ),
	array( 'family: JSON null', 'POST', '/wp/v2/font-families', 'admin', $fam( 'null' ) ),
	array( 'family: not JSON', 'POST', '/wp/v2/font-families', 'admin', $fam( '{not json' ) ),
	array( 'family: empty settings', 'POST', '/wp/v2/font-families', 'admin', $fam( '' ) ),
	array( 'family: no settings', 'POST', '/wp/v2/font-families', 'admin', array( 'multipart', array( 'theme_json_version' => 'abc' ) ) ),
	array( 'family: name of the wrong type', 'POST', '/wp/v2/font-families', 'admin', $fam( array( 'name' => 5 ) + $min ) ),
	array( 'family: name null', 'POST', '/wp/v2/font-families', 'admin', $fam( array( 'name' => null ) + $min ) ),
	array( 'family: slug a list', 'POST', '/wp/v2/font-families', 'admin', $fam( array( 'slug' => array( 'a' ) ) + $min ) ),
	array( 'family: preview a number', 'POST', '/wp/v2/font-families', 'admin', $fam( $min + array( 'preview' => 5 ) ) ),
	array( 'family: an unknown key', 'POST', '/wp/v2/font-families', 'admin', $fam( $min + array( 'foo' => 'bar', 'id' => 5 ) ) ),
	array( 'family: a wrong type before an unknown key', 'POST', '/wp/v2/font-families', 'admin', $fam( array( 'name' => 5, 'slug' => 'zz-q', 'fontFamily' => 'X', 'zzz' => 1 ) ) ),
	array( 'family: an unknown key before an empty name', 'POST', '/wp/v2/font-families', 'admin', $fam( array( 'name' => '', 'slug' => 'zz-q', 'fontFamily' => 'X', 'zzz' => 1 ) ) ),
	array( 'family: empty name', 'POST', '/wp/v2/font-families', 'admin', $fam( array( 'name' => '' ) + $min ) ),
	array( 'family: empty slug', 'POST', '/wp/v2/font-families', 'admin', $fam( array( 'slug' => '' ) + $min ) ),
	array( 'family: empty font family', 'POST', '/wp/v2/font-families', 'admin', $fam( array( 'fontFamily' => '' ) + $min ) ),
	array( 'family: version 1', 'POST', '/wp/v2/font-families', 'admin', $fam( $min, array( 'theme_json_version' => '1' ) ) ),
	array( 'family: version abc', 'POST', '/wp/v2/font-families', 'admin', $fam( $min, array( 'theme_json_version' => 'abc' ) ) ),
	array( 'family: version 2.5', 'POST', '/wp/v2/font-families', 'admin', $fam( $min, array( 'theme_json_version' => '2.5' ) ) ),
	array( 'family: a bad version under bad settings', 'POST', '/wp/v2/font-families', 'admin', $fam( '{bad', array( 'theme_json_version' => '9' ) ) ),
	array( 'family: settings as a JSON object', 'POST', '/wp/v2/font-families', 'admin', array( 'json', array( 'font_family_settings' => $min ) ) ),
	array( 'family: the editor', 'POST', '/wp/v2/font-families', 'editor', $fam( array( 'slug' => 'zz-ed' ) + $min ) ),
	array( 'family: the editor, bad settings', 'POST', '/wp/v2/font-families', 'editor', $fam( '{bad' ) ),
	array( 'family: the editor, a duplicate', 'POST', '/wp/v2/font-families', 'editor', $fam( $min ) ),
	array( 'family: signed out', 'POST', '/wp/v2/font-families', null, $fam( array( 'slug' => 'zz-out' ) + $min ) ),
	array( 'family: signed out, no settings', 'POST', '/wp/v2/font-families', null, array( 'multipart', array( 'x' => '1' ) ) ),
	// Updating a family.
	array( 'update: the name alone', 'POST', '/wp/v2/font-families/{F1}', 'admin', $fam( array( 'name' => '<em>ZZ</em> Renamed <script>x</script>' ) ) ),
	array( 'update: nothing', 'POST', '/wp/v2/font-families/{F1}', 'admin', $fam( '{}' ) ),
	array( 'update: the preview and font family', 'POST', '/wp/v2/font-families/{F1}', 'admin', $fam( array( 'preview' => 'not a url', 'fontFamily' => 'Arial, Helvetica Neue, sans-serif' ) ) ),
	array( 'update: an empty preview', 'POST', '/wp/v2/font-families/{F2}', 'admin', $fam( array( 'preview' => '' ) ) ),
	array( 'update: the slug', 'POST', '/wp/v2/font-families/{F1}', 'admin', $fam( array( 'slug' => 'zz-probe' ) ) ),
	array( 'update: an empty name', 'POST', '/wp/v2/font-families/{F1}', 'admin', $fam( array( 'name' => '' ) ) ),
	array( 'update: a name of the wrong type', 'POST', '/wp/v2/font-families/{F1}', 'admin', $fam( array( 'name' => 5 ) ) ),
	array( 'update: an unknown key', 'POST', '/wp/v2/font-families/{F1}', 'admin', $fam( array( 'name' => 'X', 'zzz' => 1 ) ) ),
	array( 'update: not JSON', 'POST', '/wp/v2/font-families/{F1}', 'admin', $fam( '{bad' ) ),
	array( 'update: version 4', 'POST', '/wp/v2/font-families/{F1}', 'admin', $fam( array( 'name' => 'X' ), array( 'theme_json_version' => '4' ) ) ),
	array( 'update: no settings', 'POST', '/wp/v2/font-families/{F1}', 'admin', array( 'multipart', array( 'theme_json_version' => '2' ) ) ),
	array( 'update: a family that does not exist', 'POST', '/wp/v2/font-families/999999991', 'admin', $fam( array( 'name' => 'X' ) ) ),
	array( 'update: a family that does not exist, bad settings', 'POST', '/wp/v2/font-families/999999991', 'admin', $fam( '{bad' ) ),
	array( 'update: id 0', 'POST', '/wp/v2/font-families/0', 'admin', $fam( array( 'name' => 'X' ) ) ),
	array( 'update: the editor', 'POST', '/wp/v2/font-families/{F1}', 'editor', $fam( array( 'name' => 'X' ) ) ),
	array( 'update: the editor, a family that does not exist', 'POST', '/wp/v2/font-families/999999991', 'editor', $fam( array( 'name' => 'X' ) ) ),
	array( 'update: PUT with the settings as a string', 'PUT', '/wp/v2/font-families/{F1}', 'admin', array( 'urlencoded', array( 'font_family_settings' => '{"name":"X"}' ) ) ),
	array( 'update: PATCH with a JSON string', 'PATCH', '/wp/v2/font-families/{F1}', 'admin', array( 'json', array( 'font_family_settings' => '{"name":"X"}' ) ) ),
	array( 'update: PUT with an object naming an unknown key', 'PUT', '/wp/v2/font-families/{F1}', 'admin', array( 'json', array( 'font_family_settings' => array( 'q' => 1 ) ) ) ),
	array( 'update: PUT multipart', 'PUT', '/wp/v2/font-families/{F1}', 'admin', $fam( array( 'name' => 'X' ) ) ),
	array( 'update: the collection takes no PUT', 'PUT', '/wp/v2/font-families', 'admin', $fam( $min ) ),
	// Creating a face from remote sources.
	array( 'face: minimal', 'POST', '/wp/v2/font-families/{F1}/font-faces', 'admin', $face( array( 'fontFamily' => 'ZZProbe', 'src' => $src ) ), 'A1' ),
	array( 'face: a list of sources', 'POST', '/wp/v2/font-families/{F1}/font-faces', 'admin', $face( array( 'fontFamily' => 'ZZProbe', 'fontStyle' => 'italic', 'src' => array( $src, 'https://example.com/fonts/a b.woff' ) ) ), 'A2' ),
	array( 'face: every key, sanitized', 'POST', '/wp/v2/font-families/{F1}/font-faces', 'admin', $face( array( 'fontFamily' => 'ZZ <b>Probe</b> Two, serif', 'fontStyle' => '<i>oblique</i>', 'fontWeight' => 450, 'fontDisplay' => 'swap', 'src' => $src, 'fontStretch' => 'condensed', 'ascentOverride' => '90%', 'descentOverride' => '10%', 'fontVariant' => 'small-caps', 'fontFeatureSettings' => '"liga" 1', 'fontVariationSettings' => '"wght" 400', 'lineGapOverride' => '0%', 'sizeAdjust' => '100%', 'unicodeRange' => 'U+0-7F<script>', 'preview' => 'javascript:alert(1)' ) ), 'A3' ),
	array( 'face: a weight range', 'POST', '/wp/v2/font-families/{F1}/font-faces', 'admin', $face( array( 'fontFamily' => 'ZZProbe', 'fontWeight' => '100 900', 'src' => $src ) ), 'A4' ),
	array( 'face: another family\'s name', 'POST', '/wp/v2/font-families/{F1}/font-faces', 'admin', $face( array( 'fontFamily' => 'SomethingElse', 'src' => $src ) ), 'A5' ),
	array( 'face: a duplicate', 'POST', '/wp/v2/font-families/{F1}/font-faces', 'admin', $face( array( 'fontFamily' => 'ZZPROBE', 'src' => 'https://example.com/other.woff2' ) ) ),
	array( 'face: a duplicate with the defaults written out', 'POST', '/wp/v2/font-families/{F1}/font-faces', 'admin', $face( array( 'fontFamily' => 'ZZProbe', 'fontStyle' => 'normal', 'fontWeight' => '400', 'fontStretch' => 'normal', 'unicodeRange' => 'U+0-10FFFF', 'src' => $src ) ) ),
	array( 'face: another unicode range', 'POST', '/wp/v2/font-families/{F1}/font-faces', 'admin', $face( array( 'fontFamily' => 'ZZProbe', 'unicodeRange' => 'U+0-FF', 'src' => $src ) ), 'A6' ),
	array( 'face: the same face under another family', 'POST', '/wp/v2/font-families/{F2}/font-faces', 'admin', $face( array( 'fontFamily' => 'ZZProbe', 'src' => $src ) ), 'A7' ),
	array( 'face: missing font family', 'POST', '/wp/v2/font-families/{F1}/font-faces', 'admin', $face( array( 'src' => $src ) ) ),
	array( 'face: missing src', 'POST', '/wp/v2/font-families/{F1}/font-faces', 'admin', $face( array( 'fontFamily' => 'ZZProbe', 'foo' => 'bar' ) ) ),
	array( 'face: {}', 'POST', '/wp/v2/font-families/{F1}/font-faces', 'admin', $face( '{}' ) ),
	array( 'face: empty font family', 'POST', '/wp/v2/font-families/{F1}/font-faces', 'admin', $face( array( 'fontFamily' => '', 'src' => $src ) ) ),
	array( 'face: empty src', 'POST', '/wp/v2/font-families/{F1}/font-faces', 'admin', $face( array( 'fontFamily' => 'ZZProbe', 'src' => '' ) ) ),
	array( 'face: an empty src list', 'POST', '/wp/v2/font-families/{F1}/font-faces', 'admin', $face( array( 'fontFamily' => 'ZZProbe', 'src' => array() ) ) ),
	array( 'face: src a number', 'POST', '/wp/v2/font-families/{F1}/font-faces', 'admin', $face( array( 'fontFamily' => 'ZZProbe', 'src' => 5 ) ) ),
	array( 'face: a number in the src list', 'POST', '/wp/v2/font-families/{F1}/font-faces', 'admin', $face( array( 'fontFamily' => 'ZZProbe', 'src' => array( $src, 5 ) ) ) ),
	array( 'face: src not an address', 'POST', '/wp/v2/font-families/{F1}/font-faces', 'admin', $face( array( 'fontFamily' => 'ZZProbe', 'fontWeight' => '841', 'src' => 'not a url' ) ) ),
	array( 'face: a relative src', 'POST', '/wp/v2/font-families/{F1}/font-faces', 'admin', $face( array( 'fontFamily' => 'ZZProbe', 'fontWeight' => '842', 'src' => '/fonts/a.woff2' ) ) ),
	array( 'face: a file: src', 'POST', '/wp/v2/font-families/{F1}/font-faces', 'admin', $face( array( 'fontFamily' => 'ZZProbe', 'fontWeight' => '843', 'src' => 'file:///etc/passwd' ) ) ),
	array( 'face: a local address', 'POST', '/wp/v2/font-families/{F1}/font-faces', 'admin', $face( array( 'fontFamily' => 'ZZProbe', 'fontWeight' => '844', 'src' => 'http://127.0.0.1/a.woff2' ) ) ),
	array( 'face: the site\'s own address', 'POST', '/wp/v2/font-families/{F1}/font-faces', 'admin', $face( array( 'fontFamily' => 'ZZProbe', 'fontWeight' => '845', 'src' => 'https://minn.localhost/wp-content/uploads/fonts/x.woff2' ) ), 'A8' ),
	array( 'face: a list with one bad src', 'POST', '/wp/v2/font-families/{F1}/font-faces', 'admin', $face( array( 'fontFamily' => 'ZZProbe', 'fontWeight' => '846', 'src' => array( $src, 'nope' ) ) ) ),
	array( 'face: a file named but not sent', 'POST', '/wp/v2/font-families/{F1}/font-faces', 'admin', $face( array( 'fontFamily' => 'ZZProbe', 'fontWeight' => '847', 'src' => 'file-0-0' ) ) ),
	array( 'face: an unknown key', 'POST', '/wp/v2/font-families/{F1}/font-faces', 'admin', $face( array( 'fontFamily' => 'ZZProbe', 'src' => $src, 'foo' => 'bar' ) ) ),
	array( 'face: a display not in the list', 'POST', '/wp/v2/font-families/{F1}/font-faces', 'admin', $face( array( 'fontFamily' => 'ZZProbe', 'fontDisplay' => 'sometimes', 'src' => $src ) ) ),
	array( 'face: a weight of the wrong type', 'POST', '/wp/v2/font-families/{F1}/font-faces', 'admin', $face( array( 'fontFamily' => 'ZZProbe', 'fontWeight' => true, 'src' => $src ) ) ),
	array( 'face: a style of the wrong type', 'POST', '/wp/v2/font-families/{F1}/font-faces', 'admin', $face( array( 'fontFamily' => 'ZZProbe', 'fontStyle' => 5, 'src' => $src ) ) ),
	array( 'face: not JSON', 'POST', '/wp/v2/font-families/{F1}/font-faces', 'admin', $face( '{bad', array(), array( 'theme_json_version' => '7' ) ) ),
	array( 'face: a JSON string', 'POST', '/wp/v2/font-families/{F1}/font-faces', 'admin', $face( '"x"' ) ),
	array( 'face: a JSON list', 'POST', '/wp/v2/font-families/{F1}/font-faces', 'admin', $face( '[1]' ) ),
	array( 'face: version 4', 'POST', '/wp/v2/font-families/{F1}/font-faces', 'admin', $face( array( 'fontFamily' => 'ZZProbe', 'fontWeight' => '848', 'src' => $src ), array(), array( 'theme_json_version' => '4' ) ) ),
	array( 'face: settings as a JSON object', 'POST', '/wp/v2/font-families/{F1}/font-faces', 'admin', array( 'json', array( 'font_face_settings' => array( 'fontFamily' => 'ZZProbe', 'src' => $src ) ) ) ),
	array( 'face: a family that does not exist', 'POST', '/wp/v2/font-families/999999991/font-faces', 'admin', $face( array( 'fontFamily' => 'ZZProbe', 'fontWeight' => '849', 'src' => $src ) ) ),
	array( 'face: a family that does not exist, bad settings', 'POST', '/wp/v2/font-families/999999991/font-faces', 'admin', $face( '{bad' ) ),
	array( 'face: under a face', 'POST', '/wp/v2/font-families/{A1}/font-faces', 'admin', $face( array( 'fontFamily' => 'ZZProbe', 'fontWeight' => '850', 'src' => $src ) ) ),
	array( 'face: the editor', 'POST', '/wp/v2/font-families/{F1}/font-faces', 'editor', $face( array( 'fontFamily' => 'ZZProbe', 'fontWeight' => '851', 'src' => $src ) ) ),
	array( 'face: the editor, a family that does not exist', 'POST', '/wp/v2/font-families/999999991/font-faces', 'editor', $face( array( 'fontFamily' => 'ZZProbe', 'fontWeight' => '852', 'src' => $src ) ) ),
	array( 'face: the editor, an upload', 'POST', '/wp/v2/font-families/{F1}/font-faces', 'editor', $face( array( 'fontFamily' => 'ZZProbe', 'fontWeight' => '853', 'src' => 'file-0-0' ), array( 'file-0-0' => $file( 'real.woff2' ) ) ) ),
	array( 'face: signed out', 'POST', '/wp/v2/font-families/{F1}/font-faces', null, $face( array( 'fontFamily' => 'ZZProbe', 'fontWeight' => '854', 'src' => $src ) ) ),
	array( 'face: the item takes no update', 'POST', '/wp/v2/font-families/{F1}/font-faces/{A1}', 'admin', $face( array( 'fontFamily' => 'ZZProbe', 'src' => $src ) ) ),
	// Creating a face from uploaded files.
	array( 'upload: a woff2', 'POST', '/wp/v2/font-families/{F1}/font-faces', 'admin', $face( array( 'fontFamily' => 'ZZProbe', 'fontWeight' => '500', 'src' => 'file-0-0' ), array( 'file-0-0' => $file( 'real.woff2' ) ) ), 'U1' ),
	array( 'upload: the same file name again', 'POST', '/wp/v2/font-families/{F1}/font-faces', 'admin', $face( array( 'fontFamily' => 'ZZProbe', 'fontWeight' => '501', 'src' => 'file-0-0' ), array( 'file-0-0' => $file( 'real.woff2' ) ) ), 'U2' ),
	array( 'upload: a name with spaces and a comma', 'POST', '/wp/v2/font-families/{F1}/font-faces', 'admin', $face( array( 'fontFamily' => 'ZZProbe', 'fontWeight' => '502', 'src' => 'myfont' ), array( 'myfont' => $file( 'Inter Variable,Font.woff2' ) ) ), 'U3' ),
	array( 'upload: a .php inside the name', 'POST', '/wp/v2/font-families/{F1}/font-faces', 'admin', $face( array( 'fontFamily' => 'ZZProbe', 'fontWeight' => '503', 'src' => 'file-0-0' ), array( 'file-0-0' => $file( 'evil.php.woff2' ) ) ), 'U4' ),
	array( 'upload: an upper-case extension', 'POST', '/wp/v2/font-families/{F1}/font-faces', 'admin', $face( array( 'fontFamily' => 'ZZProbe', 'fontWeight' => '504', 'src' => 'file-0-0' ), array( 'file-0-0' => $file( 'UPPER.WOFF2' ) ) ), 'U5' ),
	array( 'upload: two files and an address', 'POST', '/wp/v2/font-families/{F1}/font-faces', 'admin', $face( array( 'fontFamily' => 'ZZProbe', 'fontWeight' => '505', 'src' => array( 'file-0-0', $src, 'file-0-1' ) ), array( 'file-0-0' => $file( 'magic.woff' ), 'file-0-1' => $file( 'magic.otf' ) ) ), 'U6' ),
	array( 'upload: woff2 content as a .ttf', 'POST', '/wp/v2/font-families/{F1}/font-faces', 'admin', $face( array( 'fontFamily' => 'ZZProbe', 'fontWeight' => '506', 'src' => 'file-0-0' ), array( 'file-0-0' => $file( 'woff2-as.ttf' ) ) ) ),
	array( 'upload: a .ttf the type check cannot tell', 'POST', '/wp/v2/font-families/{F1}/font-faces', 'admin', $face( array( 'fontFamily' => 'ZZProbe', 'fontWeight' => '507', 'src' => 'file-0-0' ), array( 'file-0-0' => $file( 'magic.ttf' ) ) ) ),
	array( 'upload: text as a .woff2', 'POST', '/wp/v2/font-families/{F1}/font-faces', 'admin', $face( array( 'fontFamily' => 'ZZProbe', 'fontWeight' => '508', 'src' => 'file-0-0' ), array( 'file-0-0' => $file( 'text.woff2' ) ) ) ),
	array( 'upload: a .php', 'POST', '/wp/v2/font-families/{F1}/font-faces', 'admin', $face( array( 'fontFamily' => 'ZZProbe', 'fontWeight' => '509', 'src' => 'file-0-0' ), array( 'file-0-0' => $file( 'shell.php' ) ) ) ),
	array( 'upload: a refused file after a good one', 'POST', '/wp/v2/font-families/{F1}/font-faces', 'admin', $face( array( 'fontFamily' => 'ZZProbe', 'fontWeight' => '510', 'src' => array( 'file-0-0', 'file-0-1' ) ), array( 'file-0-0' => $file( 'magic.woff' ), 'file-0-1' => $file( 'shell.php' ) ) ) ),
	array( 'upload: a file the sources do not name', 'POST', '/wp/v2/font-families/{F1}/font-faces', 'admin', $face( array( 'fontFamily' => 'ZZProbe', 'fontWeight' => '511', 'src' => $src ), array( 'file-0-0' => $file( 'shell.php' ) ) ) ),
	array( 'upload: a duplicate with a file', 'POST', '/wp/v2/font-families/{F1}/font-faces', 'admin', $face( array( 'fontFamily' => 'ZZProbe', 'fontWeight' => '500', 'src' => 'file-0-0' ), array( 'file-0-0' => $file( 'shell.php' ) ) ) ),
	array( 'upload: a family that does not exist', 'POST', '/wp/v2/font-families/999999991/font-faces', 'admin', $face( array( 'fontFamily' => 'ZZProbe', 'fontWeight' => '512', 'src' => 'file-0-0' ), array( 'file-0-0' => $file( 'real.woff2' ) ) ) ),
	// Reading back.
	array( 'read: the family and its faces', 'GET', '/wp/v2/font-families/{F1}?context=edit', 'admin', array( 'none', array() ) ),
	array( 'read: the faces', 'GET', '/wp/v2/font-families/{F1}/font-faces?per_page=100&orderby=id&order=asc', 'admin', array( 'none', array() ) ),
	array( 'read: OPTIONS on the families', 'OPTIONS', '/wp/v2/font-families', 'admin', array( 'none', array() ) ),
	array( 'read: OPTIONS on a family', 'OPTIONS', '/wp/v2/font-families/{F1}', 'admin', array( 'none', array() ) ),
	array( 'read: OPTIONS on its faces', 'OPTIONS', '/wp/v2/font-families/{F1}/font-faces', 'admin', array( 'none', array() ) ),
);

/** The scenario on one stack: each step's answer, then the rows, meta and files it left, ids read as names. */
function fw_run( string $base, array $steps, array $mints, array $before_posts, array $before_files ): array {
	$ids     = array();
	$answers = array();
	foreach ( $steps as $step ) {
		[ $label, $method, $route, $who, $body ] = $step;
		$name  = $step[5] ?? null;
		$route = preg_replace_callback( '/\{(\w+)\}/', static fn ( $m ) => (string) ( $ids[ $m[1] ] ?? 0 ), $route );
		[ $status, $headers, $data ] = fw_send( $base, $method, $route, null === $who ? null : $mints[ $who ], $body );
		if ( null !== $name && is_array( $data ) && isset( $data['id'] ) ) {
			$ids[ $name ] = (int) $data['id'];
		}
		$answers[ $label ] = array( $status, $headers, $data );
	}
	// Every font post the run made, oldest first, named by the step that made it or by its order.
	$made = array_values( array_unique( array_filter( array_merge( array_diff( fw_font_posts(), $before_posts ), array_values( $ids ) ), static fn ( int $id ): bool => $id > 1 ) ) );
	sort( $made );
	$names = array_flip( $ids );
	foreach ( $made as $i => $id ) {
		$names[ $id ] = $names[ $id ] ?? 'post' . $i;
	}
	$rows = array();
	foreach ( $made as $id ) {
		$row  = json_decode( fw_wp( "db query 'SELECT post_type, post_status, post_title, post_name, post_content, post_parent, post_author > 0 AS has_author, comment_status, ping_status, menu_order, post_mime_type, guid FROM wp_posts WHERE ID = $id' --json" ), true )[0] ?? null;
		$meta = json_decode( fw_wp( "db query 'SELECT meta_key, meta_value FROM wp_postmeta WHERE post_id = $id ORDER BY meta_id' --json" ), true ) ?: array();
		$rows[ $names[ $id ] ] = array( $row, $meta );
	}
	$state = array( 'rows' => $rows, 'files' => array_values( array_diff( fw_font_files(), $before_files ) ) );
	// Clean up for the other stack: the posts (their files with them), then any file left behind.
	foreach ( $made as $id ) {
		if ( $id > 1 ) {
			fw_wp( "post delete $id --force" );
		}
	}
	foreach ( array_diff( fw_font_files(), $before_files ) as $name ) {
		@unlink( minn_test_site_root() . '/public/wp-content/uploads/fonts/' . $name );
	}
	return fw_named( array( $answers, $state ), $names );
}

$reference = fw_run( $REF, $steps, $mints, $before_posts, $before_files );
$engine    = fw_run( $ENGINE, $steps, $mints, $before_posts, $before_files );

foreach ( $reference[0] as $label => [ $status, $headers, $data ] ) {
	[ $es, $eh, $ed ] = $engine[0][ $label ];
	if ( $status !== $es ) {
		check( false, $label, "status $status vs $es " . json_encode( $ed, JSON_UNESCAPED_SLASHES ) . ' / reference ' . json_encode( $data, JSON_UNESCAPED_SLASHES ) );
		continue;
	}
	// The routes' schemas come from controllers the engine keeps as dead ends, so its OPTIONS answer has none yet (contracts/runtime.md, the font library's writes).
	if ( str_starts_with( $label, 'read: OPTIONS' ) ) {
		unset( $data['schema'], $ed['schema'] );
	}
	// Faces made in one second come in the order the database gives them, which each run's timing decides.
	foreach ( array( 'data', 'ed' ) as $side ) {
		if ( is_array( $$side ) && isset( $$side['font_faces'] ) ) {
			sort( $$side['font_faces'] );
			if ( isset( $$side['_links']['font_faces'] ) ) {
				usort( $$side['_links']['font_faces'], static fn ( $a, $b ) => strcmp( (string) $a['href'], (string) $b['href'] ) );
			}
		}
	}
	$d = minn_test_diff( array( $headers, $data ), array( $eh, $ed ) );
	check( null === $d, "$label ($status)", (string) $d );
}
foreach ( $reference[1]['rows'] as $name => $row ) {
	$d = minn_test_diff( $row, $engine[1]['rows'][ $name ] ?? null );
	check( null === $d, "stored: $name", (string) $d );
}
$extra = array_diff_key( $engine[1]['rows'], $reference[1]['rows'] );
check( array() === $extra, 'stored: no post the reference did not make', implode( ', ', array_keys( $extra ) ) );
$d = minn_test_diff( $reference[1]['files'], $engine[1]['files'] );
check( null === $d, 'the font files the run left', (string) $d );
check( array() === array_diff( fw_font_posts(), $before_posts ) && array() === array_diff( fw_font_files(), $before_files ), 'each run cleaned up after itself' );

echo "\n$pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
