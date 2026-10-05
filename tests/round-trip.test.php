<?php
/**
 * The round trip (docs/vision.md §3): a real site, the same day of work on
 * each stack, and WordPress taking the result back.
 *
 *   1. Restore the site from its baseline dump.
 *   2. WordPress (the parked copy) has the day: a visitor browses, then the
 *      owner signs in and revises, uploads, files, publishes, comments, adds
 *      an editor, trashes a draft. Its footprint (the rows and uploaded files
 *      that changed) is what the day should leave behind.
 *   3. Restore, and Minn has the same day. Its footprint must be WordPress's,
 *      row for row, once times, hashes and new IDs are made comparable.
 *      Whatever is left over is listed; a difference that is understood and
 *      harmless goes in $EXPLAINED with the reason.
 *   4. Without restoring, WordPress takes over: signs in with the same
 *      password and with Minn's session, reads everything the day made, and
 *      keeps working; then Minn reads what WordPress wrote.
 *   5. Restore, and remove the files both days uploaded.
 *
 * The site is a local copy (MINN_ROUNDTRIP_ROOT) with Minn installed in
 * public/ and WordPress parked in wp-reference/, whose oracle must be running
 * (cd wp-reference && php -S 127.0.0.1:8129 router.php; the router serves a
 * request as HTTPS when it carries X-Forwarded-Proto: https). The site's
 * private/ folder holds round-trip.json (the owner's sign-in, the site URL,
 * the oracle URL), baseline.sql (the starting point) and, after a run, the
 * full report. Nothing from the site is written into this repository. The
 * copy runs offline: wp-content/mu-plugins/zz-round-trip-offline.php refuses
 * outbound HTTP and keeps cron off page loads, on both stacks.
 *
 *   php tests/round-trip.test.php
 *   MINN_ROUNDTRIP_KEEP=1 php tests/round-trip.test.php   (leave the site as the swap back left it)
 */
require __DIR__ . '/lib.php';
require __DIR__ . '/tools/round-trip.php';

$ROOT = rtrim( getenv( 'MINN_ROUNDTRIP_ROOT' ) ?: '~/Cove/Sites/cove-minn.localhost', '/' );
$cfg  = json_decode( (string) @file_get_contents( "$ROOT/private/round-trip.json" ), true );
$DUMP = "$ROOT/private/baseline.sql";
if ( ! is_array( $cfg ) || ! is_file( $DUMP ) ) {
	echo "SKIP: no round-trip site at $ROOT (needs private/round-trip.json and private/baseline.sql)\n";
	exit( 0 );
}
$REF     = rtrim( getenv( 'MINN_ROUNDTRIP_REF' ) ?: $cfg['oracle'], '/' );
$URL     = rtrim( $cfg['url'], '/' );
$AS_SITE = array( 'Host: ' . parse_url( $URL, PHP_URL_HOST ), 'X-Forwarded-Proto: https' );
$UPLOADS = "$ROOT/public/wp-content/uploads";
[ $probe ] = ( new RtClient( $REF, $AS_SITE ) )->request( 'GET', '/wp-login.php' );
if ( 200 !== $probe ) {
	echo "SKIP: the parked WordPress is not answering at $REF\n";
	exit( 0 );
}
$cfg['editor_password'] = bin2hex( random_bytes( 9 ) );

/** Rows every stack rewrites as it goes and no day owns: caches with an expiry. */
$NOISE = array(
	'#^options _(site_)?transient_#' => 'transients are caches; either stack may keep or drop them',
);
/**
 * Differences between the two days that are understood, with why. A key
 * pattern alone covers every difference on matching rows; a test narrows it
 * to the part of the row the reason is about.
 */
$EXPLAINED = array(
	'#^options minn_#'                         => array( "Minn's own bookkeeping, in options of its own name that WordPress never reads (the plugin symbol scan, for one)" ),
	'#^postmeta [^ ]+/_(pingme|encloseme)$#'   => array( 'Minn sends no pingbacks, trackbacks or enclosure checks, so it does not queue them: _pingme and _encloseme are the to-do list WordPress works through for that' ),
	'#^options cron$#'                         => array( '…and WordPress schedules do_pings to work through that list; nothing else in cron may differ', static fn ( ?string $w, ?string $m ): bool => rt_without( $w, '[do_pings]' ) === rt_without( $m, '[do_pings]' ) ),
	'#/_wp_attachment_metadata$#'              => array( 'the parked WordPress cuts image sizes with Imagick and Minn with GD: the same files at the same dimensions, a different number of bytes; nothing else in the metadata may differ', static fn ( ?string $w, ?string $m ): bool => null !== $w && null !== $m && rt_without_bytes( $w ) === rt_without_bytes( $m ) ),
	'#^options cleantalk_cron_pid$#'           => array( "CleanTalk's cron lock is a fresh random number each run", static fn ( ?string $w, ?string $m ): bool => null !== $w && null !== $m ),
);
/** Pages a visitor gets differently on purpose. */
$EXPLAINED_PAGES = array(
	'/wp-login.php' => array( '302 /minn-admin/login', "Minn's sign-in page is Minn Admin's (/minn-admin/login): wp-login.php sends a visitor there, and still takes the sign-in form's post" ),
);

$pass = 0;
$fail = 0;
function check( bool $ok, string $label, string $detail = '' ): void {
	global $pass, $fail;
	if ( $ok ) { $pass++; echo "  ok  $label\n"; } else { $fail++; echo "FAIL  $label" . ( $detail ? "\n      $detail" : '' ) . "\n"; }
}

/** A footprint line without the parts that mention a needle (tree changes are "; " separated). */
function rt_without( ?string $detail, string $needle ): string {
	$detail = preg_replace( '/^option_value: /', '', (string) $detail );
	return implode( '; ', array_filter( explode( '; ', $detail ), static fn ( $part ) => '' !== $part && ! str_contains( $part, $needle ) ) );
}

/** A serialized metadata row with its byte counts taken out. */
function rt_without_bytes( string $row ): string {
	return (string) preg_replace( '/s:8:.?"filesize.?";i:\d+;/', '', $row );
}

/** A footprint without the noise. */
function rt_quiet( array $footprint ): array {
	global $NOISE;
	return array_filter( $footprint, static fn ( $key ) => ! array_filter( array_keys( $NOISE ), static fn ( $re ) => preg_match( $re, $key ) ), ARRAY_FILTER_USE_KEY );
}

/** Where two footprints part: key => [what WordPress did, what Minn did]. */
function rt_compare( array $wordpress, array $minn ): array {
	$diffs = array();
	foreach ( array_unique( array_merge( array_keys( $wordpress ), array_keys( $minn ) ) ) as $key ) {
		if ( ( $wordpress[ $key ] ?? null ) !== ( $minn[ $key ] ?? null ) ) {
			$diffs[ $key ] = array( $wordpress[ $key ] ?? null, $minn[ $key ] ?? null );
		}
	}
	ksort( $diffs );
	return $diffs;
}

/** Moves the differences an entry in $EXPLAINED covers into $explained (key => why); returns the rest. */
function rt_explain( array $diffs, array $reasons, ?array &$explained ): array {
	$explained ??= array();
	foreach ( $diffs as $key => $pair ) {
		foreach ( $reasons as $re => $reason ) {
			if ( preg_match( $re, $key ) && ( ! isset( $reason[1] ) || $reason[1]( ...$pair ) ) ) {
				$explained[ $key ] = $reason[0];
				unset( $diffs[ $key ] );
				break;
			}
		}
	}
	return $diffs;
}

function rt_describe( string $key, array $pair ): string {
	[ $w, $m ] = $pair;
	if ( null === $m ) {
		return "$key\n        WordPress only: " . rt_show( $w );
	}
	if ( null === $w ) {
		return "$key\n        Minn only: " . rt_show( $m );
	}
	return "$key\n        WordPress: " . rt_show( $w, $m ) . '  (Minn)';
}

[ $db, $prefix ] = rt_connect( $ROOT );
// A run that kept its state (MINN_ROUNDTRIP_KEEP) left its uploads behind; they go first.
$kept = json_decode( (string) @file_get_contents( "$ROOT/private/round-trip-kept-files.json" ), true );
foreach ( is_array( $kept ) ? $kept : array() as $rel => $stamp ) {
	'dir' === $stamp ? @rmdir( "$UPLOADS/$rel" ) : @unlink( "$UPLOADS/$rel" );
}
@unlink( "$ROOT/private/round-trip-kept-files.json" );
$explained       = array();
$explainedBrowse = array();
$started         = time();
$cleaned         = false;
$files0          = rt_files( $UPLOADS );
$cleanup         = static function () use ( &$cleaned, &$files0, $ROOT, $DUMP, $UPLOADS, &$db, $prefix, &$tables0 ): void {
	if ( $cleaned ) {
		return;
	}
	$cleaned = true;
	rt_remove_added( $UPLOADS, $files0, rt_files( $UPLOADS ) );
	rt_restore( $ROOT, $DUMP, $db, $prefix, $tables0 ?? array() );
};

rt_restore( $ROOT, $DUMP, $db, $prefix, array_keys( rt_snapshot( $db, $prefix ) ) );
$base    = rt_snapshot( $db, $prefix );
$tables0 = array_keys( $base );
register_shutdown_function( $cleanup );
$zone   = (string) ( $base['options']['rows']['timezone_string']['option_value'] ?? '' );
$zone   = '' !== $zone ? $zone : sprintf( '%+03d:00', (int) ( $base['options']['rows']['gmt_offset']['option_value'] ?? 0 ) );
$photo  = rt_photo();
$window = static fn () => rt_window( $started, time(), $zone );

echo "\nThe control: WordPress has the day\n";
$wpBrowse  = rt_browse( new RtClient( $REF, $AS_SITE ), $URL );
$wpAfterB  = rt_snapshot( $db, $prefix );
$wpDay     = rt_day( $wp = new RtClient( $REF, $AS_SITE ), $cfg, $photo );
$wpAfter   = rt_snapshot( $db, $prefix );
$wpFiles   = rt_file_footprint( $UPLOADS, $files0, rt_files( $UPLOADS ) );
$wpBrowseF = rt_quiet( rt_footprint( $base, $wpAfterB, $window() ) );
$wpDayF    = rt_quiet( rt_footprint( $wpAfterB, $wpAfter, $window() ) );
$failed    = array_keys( array_filter( $wpDay['steps'], static fn ( $s ) => ! $s['ok'] ) );
check( ! $failed, 'WordPress gets through the whole day', 'steps that failed: ' . implode( ', ', $failed ) );
check( count( $wpDayF ) > 0, 'the day leaves a footprint (' . count( $wpDayF ) . ' rows, ' . count( $wpFiles ) . ' files)' );
rt_remove_added( $UPLOADS, $files0, rt_files( $UPLOADS ) );
rt_restore( $ROOT, $DUMP, $db, $prefix, $tables0 );
check( ! rt_footprint( $base, rt_snapshot( $db, $prefix ), $window() ), 'the baseline restores row for row' );

echo "\nMinn has the same day\n";
$mnBrowse  = rt_browse( new RtClient( $URL ), $URL );
$mnAfterB  = rt_snapshot( $db, $prefix );
$mnDay     = rt_day( $minn = new RtClient( $URL ), $cfg, $photo );
$mnAfter   = rt_snapshot( $db, $prefix );
$mnFiles   = rt_file_footprint( $UPLOADS, $files0, rt_files( $UPLOADS ) );
$mnBrowseF = rt_quiet( rt_footprint( $base, $mnAfterB, $window() ) );
$mnDayF    = rt_quiet( rt_footprint( $mnAfterB, $mnAfter, $window() ) );
foreach ( $wpBrowse as $path => $answer ) {
	$mine = $mnBrowse[ $path ] ?? '';
	$why  = ( $EXPLAINED_PAGES[ $path ][0] ?? null ) === $mine ? $EXPLAINED_PAGES[ $path ][1] : null;
	check( $answer === $mine || null !== $why, "a visitor gets $path ($answer" . ( null !== $why ? "; Minn $mine, explained" : '' ) . ')', "Minn answered $mine" );
}
$browseDiffs = rt_explain( rt_compare( $wpBrowseF, $mnBrowseF ), $EXPLAINED, $explainedBrowse );
check( ! $browseDiffs, 'browsing writes what WordPress writes (' . count( $mnBrowseF ) . ' rows, ' . count( $explainedBrowse ) . ' differences explained)', implode( "\n      ", array_map( 'rt_describe', array_keys( $browseDiffs ), $browseDiffs ) ) );
foreach ( $wpDay['steps'] as $name => $step ) {
	$mine = $mnDay['steps'][ $name ] ?? null;
	check( (bool) ( $mine['ok'] ?? false ), "Minn $name", null === $mine ? 'never got there' : "answered {$mine['status']}, WordPress {$step['status']}" );
}

echo "\nThe footprints\n";
$diffs = rt_explain( rt_compare( $wpDayF, $mnDayF ), $EXPLAINED, $explained );
$matched = count( array_intersect_assoc( $wpDayF, $mnDayF ) );
check( ! $diffs, "Minn's day leaves WordPress's footprint ($matched of " . count( $wpDayF ) . ' rows match, ' . count( $explained ) . ' differences explained)', count( $diffs ) . ' rows differ' );
foreach ( array_unique( $explained ) as $why ) {
	echo "      explained: $why\n";
}
foreach ( array_slice( $diffs, 0, 40, true ) as $key => $pair ) {
	echo '      ' . rt_describe( $key, $pair ) . "\n";
}
if ( count( $diffs ) > 40 ) {
	echo '      … and ' . ( count( $diffs ) - 40 ) . " more in private/round-trip-report.txt\n";
}
$fileDiffs = rt_compare( $wpFiles, $mnFiles );
check( ! $fileDiffs, 'Minn writes the same files at the same sizes (' . count( $mnFiles ) . ')', implode( "\n      ", array_map( 'rt_describe', array_keys( $fileDiffs ), $fileDiffs ) ) );
$broken = rt_broken_serialized( $mnAfterB, $mnAfter );
check( ! $broken, 'every serialized value Minn wrote reads back', implode( ', ', $broken ) );

echo "\nWordPress takes it back\n";
$ids = $mnDay['ids'];
$wb  = new RtClient( $REF, $AS_SITE );
check( $wb->signIn( $cfg['user'], $cfg['password'], $URL ), 'signs the owner in with the same password' );
[ $s, $p ] = $wb->rest( 'GET', "/wp/v2/posts/{$ids['post']}?context=edit" );
check( 200 === $s && 'Notes from the harbor' === ( $p['title']['raw'] ?? null ) && 'publish' === $p['status'], "reads Minn's new post" );
check( in_array( $ids['category'], $p['categories'] ?? array(), true ) && in_array( $ids['tag'], $p['tags'] ?? array(), true ), '…filed under its category and tag' );
check( $ids['photo'] === ( $p['featured_media'] ?? 0 ) && str_contains( (string) ( $p['content']['raw'] ?? '' ), "wp-image-{$ids['photo']}" ), '…with its photo featured and in the content' );
[ $s, , $html ] = $wb->request( 'GET', (string) parse_url( (string) ( $p['link'] ?? '' ), PHP_URL_PATH ) );
check( 200 === $s && str_contains( $html, 'Notes from the harbor' ), '…and renders it at its address' );
[ $s, $m ] = $wb->rest( 'GET', "/wp/v2/media/{$ids['photo']}?context=edit" );
$sizes     = (array) ( $m['media_details']['sizes'] ?? array() );
$missing   = array_filter( $sizes, static fn ( $size ) => ! is_file( $UPLOADS . '/' . dirname( (string) $m['media_details']['file'] ) . '/' . $size['file'] ) );
check( 200 === $s && 'A harbor at dusk, the sun low over the water' === ( $m['alt_text'] ?? null ) && 'Shot for the round trip.' === ( $m['caption']['raw'] ?? null ), "reads the photo's description" );
check( count( $sizes ) >= 4 && ! $missing, '…and finds every size on disk (' . implode( ', ', array_keys( $sizes ) ) . ')', 'missing: ' . implode( ', ', array_keys( $missing ) ) );
[ $s, $r ] = $wb->rest( 'GET', "/wp/v2/posts/{$ids['revised']}?context=edit" );
check( str_ends_with( (string) ( $r['title']['raw'] ?? '' ), ' (revised)' ) && str_contains( (string) ( $r['content']['raw'] ?? '' ), 'Revised on the round trip.' ), 'reads the revised post' );
$moved = '301 ' . $ids['new_path'];
check( rt_answer( new RtClient( $REF, $AS_SITE ), $ids['old_path'], $URL ) === $moved, "…sends its old address on to the new one ({$ids['old_path']} → {$ids['new_path']})" );
[ $s, $revs ] = $wb->rest( 'GET', "/wp/v2/posts/{$ids['revised']}/revisions?context=edit" );
check( 200 === $s && count( (array) $revs ) > 0, '…and its revisions (' . count( (array) $revs ) . ')' );
[ $s, $c ] = $wb->rest( 'GET', "/wp/v2/comments?post={$ids['post']}&context=edit&orderby=id&order=asc" );
check( 2 === count( (array) $c ) && 'approved' === ( $c[0]['status'] ?? null ) && $ids['comment'] === ( $c[1]['parent'] ?? 0 ), 'reads the comment and the reply under it' );
[ $s, $st ] = $wb->rest( 'GET', '/wp/v2/settings' );
check( 'Notes from a round trip' === ( $st['description'] ?? null ), 'reads the tagline' );
[ $s, $pg ] = $wb->rest( 'GET', "/wp/v2/pages/{$ids['page']}?context=edit" );
check( str_contains( (string) ( $pg['content']['raw'] ?? '' ), 'Updated on the round trip.' ), 'reads the revised page' );
[ $s, $me ] = $wb->rest( 'GET', '/wp/v2/users/me?context=edit' );
check( 'Keeps notes on a round trip.' === ( $me['description'] ?? null ), "reads the owner's profile" );
[ $s, $d ] = $wb->rest( 'GET', "/wp/v2/posts/{$ids['draft']}?context=edit" );
check( 'trash' === ( $d['status'] ?? null ), 'finds the draft in the trash' );
$editor = new RtClient( $REF, $AS_SITE );
check( $editor->signIn( 'roundtrip-editor', $cfg['editor_password'], $URL ), "signs Minn's new editor in" );
[ $s, $ed ] = $editor->rest( 'GET', '/wp/v2/users/me?context=edit' );
check( array( 'editor' ) === ( $ed['roles'] ?? null ), '…as an editor' );
$carried          = new RtClient( $REF, $AS_SITE );
$carried->cookies = $minn->cookies;
$carried->fetchNonce();
[ $s, $who ] = $carried->rest( 'GET', '/wp/v2/users/me' );
check( 200 === $s && 1 === ( $who['id'] ?? 0 ), "accepts the session Minn signed in (no second sign-in)" );
[ $s ] = $wb->rest( 'POST', "/wp/v2/posts/{$ids['post']}", array( 'content' => $p['content']['raw'] . "\n\n<!-- wp:paragraph -->\n<p>Back on WordPress.</p>\n<!-- /wp:paragraph -->" ) );
check( 200 === $s, "keeps working: revises Minn's post" );
[ $s ] = $wb->rest( 'POST', '/wp/v2/comments', array( 'post' => $ids['post'], 'parent' => $ids['reply'], 'content' => 'Still here.' ) );
check( 201 === $s, '…and answers the reply' );
[ $s, $again ] = $minn->rest( 'GET', "/wp/v2/posts/{$ids['post']}?context=edit" );
[ , $thread ]  = $minn->rest( 'GET', "/wp/v2/comments?post={$ids['post']}" );
check( str_contains( (string) ( $again['content']['raw'] ?? '' ), 'Back on WordPress.' ) && 3 === count( (array) $thread ), 'and Minn reads what WordPress wrote' );
check( rt_answer( $minn, $ids['old_path'], $URL ) === $moved, 'and Minn sends the old address on too' );

if ( getenv( 'MINN_ROUNDTRIP_KEEP' ) ) {
	$cleaned = true;
	$added   = array_diff_key( rt_files( $UPLOADS ), $files0 );
	krsort( $added );
	file_put_contents( "$ROOT/private/round-trip-kept-files.json", json_encode( $added ) );
	echo "\n  (MINN_ROUNDTRIP_KEEP: the site is left as Minn's day and WordPress's follow-up made it)\n";
} else {
	$cleanup();
	$files0 = rt_files( $UPLOADS );
	check( ! rt_footprint( $base, rt_snapshot( $db, $prefix ), $window() ), 'the site is back at its baseline' );
}

$lines = array( '# Round trip ' . gmdate( 'c' ), '', '## Differences', '' );
foreach ( $diffs as $key => $pair ) {
	$lines[] = rt_describe( $key, $pair );
}
foreach ( array( 'Explained' => $explained + $explainedBrowse, 'WordPress browse' => $wpBrowseF, 'Minn browse' => $mnBrowseF, 'WordPress day' => $wpDayF, 'Minn day' => $mnDayF, 'WordPress files' => $wpFiles, 'Minn files' => $mnFiles ) as $title => $rows ) {
	$lines[] = "\n## $title\n";
	foreach ( $rows as $key => $what ) {
		$lines[] = "$key\n    " . str_replace( "\n", "\n    ", $what );
	}
}
file_put_contents( "$ROOT/private/round-trip-report.txt", implode( "\n", $lines ) . "\n" );

echo "\n$pass passed, $fail failed (full report: $ROOT/private/round-trip-report.txt)\n";
exit( $fail ? 1 : 0 );
