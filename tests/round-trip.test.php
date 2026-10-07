<?php
/**
 * The round trip (docs/vision.md §3): a real site, the same day of work on
 * each stack, and WordPress taking the result back.
 *
 *   1. Restore the site to its baseline.
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
 * public/ and WordPress parked in wp-reference/, served at its Cove twin
 * (cove twin cove-minn add --as-site=ref.cove-minn.localhost) as the site's own host. The site's
 * private/ folder holds round-trip.json (the owner's sign-in, the site URL,
 * the oracle URL, and skip_tables: big tables the day has no business
 * touching, left out of the baseline) and, after a run, the full report.
 * The baseline is a copy of the site's database beside it
 * ({database}_rtbase), made once with --set-baseline when the copy is as it
 * should start; snapshots and restores
 * compare against it inside MariaDB (tests/tools/round-trip.php, RtDatabase),
 * so a site of any size takes seconds. Nothing from the site is written into
 * this repository. The copy runs offline under a guard the suite writes into
 * wp-content/mu-plugins on every run (no outbound HTTP, no cron on page
 * loads, every message to the local mail catcher), on both stacks.
 *
 *   php tests/round-trip.test.php
 *   php tests/round-trip.test.php --set-baseline          (the copy as it is now becomes the starting point)
 *   MINN_ROUNDTRIP_KEEP=1 php tests/round-trip.test.php   (leave the site as the swap back left it)
 */
require __DIR__ . '/lib.php';
require __DIR__ . '/tools/round-trip.php';

$ROOT = rtrim( getenv( 'MINN_ROUNDTRIP_ROOT' ) ?: '~/Cove/Sites/cove-minn.localhost', '/' );
$cfg  = json_decode( (string) @file_get_contents( "$ROOT/private/round-trip.json" ), true );
if ( ! is_array( $cfg ) ) {
	echo "SKIP: no round-trip site at $ROOT (needs private/round-trip.json)\n";
	exit( 0 );
}
rt_guard( $ROOT );
$rt = RtDatabase::open( $ROOT );
if ( in_array( '--set-baseline', $argv, true ) ) {
	$rt->setBaseline( (array) ( $cfg['skip_tables'] ?? array() ) );
	echo "The baseline is now {$rt->base}, a copy of {$rt->live} as it stands" . ( $rt->skipped() ? ' but for ' . implode( ', ', $rt->skipped() ) : '' ) . ".\n";
	exit( 0 );
}
if ( ! $rt->hasBaseline() ) {
	echo "SKIP: no baseline for $ROOT yet (php tests/round-trip.test.php --set-baseline, once the copy is as it should start)\n";
	exit( 0 );
}
$REF     = rtrim( getenv( 'MINN_ROUNDTRIP_REF' ) ?: $cfg['oracle'], '/' );
$URL     = rtrim( $cfg['url'], '/' );
// A twin answers as the site already; an oracle on a bare port is told which site it is.
$AS_SITE = str_starts_with( $REF, 'https://' ) ? array() : array( 'Host: ' . parse_url( $URL, PHP_URL_HOST ), 'X-Forwarded-Proto: https' );
$UPLOADS = "$ROOT/public/wp-content/uploads";
$cfg['login_path'] ??= '/wp-login.php';
[ $probe ] = ( new RtClient( $REF, $AS_SITE ) )->request( 'GET', $cfg['login_path'] );
if ( 200 !== $probe ) {
	echo "SKIP: the parked WordPress is not answering at $REF\n";
	exit( 0 );
}
$cfg['editor_password'] = bin2hex( random_bytes( 9 ) );

/** Rows every stack rewrites as it goes and no day owns: caches with an expiry. */
$NOISE = array(
	'#^options _(site_)?transient_#' => 'transients are caches; either stack may keep or drop them',
	'#^postmeta \S+/_oembed_#'       => "oEmbed results are cached in post meta as a page renders; the engine embeds without that cache",
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
	'#^options cleantalk_data$#'               => array( "CleanTalk's JavaScript keys are fresh random numbers each run; nothing else in its data may differ", static fn ( ?string $w, ?string $m ): bool => rt_without( $w, '[js_keys]' ) === rt_without( $m, '[js_keys]' ) ),
	'#^options wdp_un_general$#'               => array( 'the WPMU DEV dashboard records the PHP version the site runs on: the parked WordPress runs its own, Minn its own; nothing else in it may differ', static fn ( ?string $w, ?string $m ): bool => rt_without( $w, '[php_version]' ) === rt_without( $m, '[php_version]' ) ),
	'#^(options (wp-smush-optimization-global-stats|wp_smush_global_stats_json)|postmeta [^ ]+/wp-smpro-smush-data)$#' => array( "Smush's savings follow the bytes of the sizes it optimises, which GD and Imagick write differently; both stacks optimised the same upload", static fn ( ?string $w, ?string $m ): bool => null !== $w && null !== $m ),
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
$cleanup         = static function () use ( &$cleaned, &$files0, $UPLOADS, $rt ): void {
	if ( $cleaned ) {
		return;
	}
	$cleaned = true;
	rt_remove_added( $UPLOADS, $files0, rt_files( $UPLOADS ) );
	$rt->restore( $rt->cleanSince() );
};

// Whatever changed since the copy was last clean (a killed run, a look around) goes back first.
$rt->restore( $rt->cleanSince() );
register_shutdown_function( $cleanup );
$option = static fn ( string $name ): string => (string) $rt->db->execute_query( "SELECT option_value FROM `{$rt->prefix}options` WHERE option_name = ?", array( $name ) )->fetch_row()[0];
$zone   = $option( 'timezone_string' );
$zone   = '' !== $zone ? $zone : sprintf( '%+03d:00', (int) $option( 'gmt_offset' ) );
$photo  = rt_photo();
$window = static fn () => rt_window( $started, time(), $zone );

echo "\nThe control: WordPress has the day\n";
$mark      = $rt->mark();
$wpBrowse  = rt_browse( new RtClient( $REF, $AS_SITE ), $URL );
$wpBrowseF = rt_quiet( rt_footprint( ...$rt->snapshot( $mark ), window: $window() ) );
$wpDay     = rt_day( $wp = new RtClient( $REF, $AS_SITE ), $cfg, $photo );
[ $wpBefore, $wpAfter ] = $rt->snapshot( $mark );
check( ! $rt->touchedSkipped( $mark ), 'WordPress stays out of the tables the baseline left out', implode( ', ', $rt->touchedSkipped( $mark ) ) );
$wpDayF    = rt_quiet( rt_footprint( $wpBefore, $wpAfter, $window() ) );
$wpFiles   = rt_file_footprint( $UPLOADS, $files0, rt_files( $UPLOADS ) );
$failed    = array_keys( array_filter( $wpDay['steps'], static fn ( $s ) => ! $s['ok'] ) );
check( ! $failed, 'WordPress gets through the whole day', 'steps that failed: ' . implode( ', ', $failed ) );
check( count( $wpDayF ) > 0, 'the day leaves a footprint (' . count( $wpDayF ) . ' rows, ' . count( $wpFiles ) . ' files)' );
rt_remove_added( $UPLOADS, $files0, rt_files( $UPLOADS ) );
$rt->restore( $mark );
check( ! rt_footprint( ...$rt->snapshot( $mark ), window: $window() ), 'the baseline restores row for row' );

echo "\nMinn has the same day\n";
$mark      = $rt->mark();
$mnBrowse  = rt_browse( new RtClient( $URL ), $URL );
$mnBrowseF = rt_quiet( rt_footprint( ...$rt->snapshot( $mark ), window: $window() ) );
$mnDay     = rt_day( $minn = new RtClient( $URL ), $cfg, $photo );
[ $mnBefore, $mnAfter ] = $rt->snapshot( $mark );
check( ! $rt->touchedSkipped( $mark ), 'Minn stays out of the tables the baseline left out', implode( ', ', $rt->touchedSkipped( $mark ) ) );
$mnDayF    = rt_quiet( rt_footprint( $mnBefore, $mnAfter, $window() ) );
$mnFiles   = rt_file_footprint( $UPLOADS, $files0, rt_files( $UPLOADS ) );
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
$broken = rt_broken_serialized( $mnBefore, $mnAfter );
check( ! $broken, 'every serialized value Minn wrote reads back', implode( ', ', $broken ) );

echo "\nWordPress takes it back\n";
// A day that stopped early still gets every check, failing, rather than a crash.
$ids = $mnDay['ids'] + array( 'post' => 0, 'revised' => 0, 'photo' => 0, 'category' => 0, 'tag' => 0, 'comment' => 0, 'reply' => 0, 'page' => 0, 'draft' => 0, 'editor' => 0, 'old_path' => '/', 'new_path' => '/' );
$wb  = new RtClient( $REF, $AS_SITE );
check( $wb->signIn( $cfg['user'], $cfg['password'], $URL, $cfg['login_path'] ), 'signs the owner in with the same password' );
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
if ( $ids['comment'] > 0 ) {
	[ $s, $c ] = $wb->rest( 'GET', "/wp/v2/comments?post={$ids['post']}&context=edit&orderby=id&order=asc" );
	check( 2 === count( (array) $c ) && 'approved' === ( $c[0]['status'] ?? null ) && $ids['comment'] === ( $c[1]['parent'] ?? 0 ), 'reads the comment and the reply under it' );
}
[ $s, $st ] = $wb->rest( 'GET', '/wp/v2/settings' );
check( 'Notes from a round trip' === ( $st['description'] ?? null ), 'reads the tagline' );
[ $s, $pg ] = $wb->rest( 'GET', "/wp/v2/pages/{$ids['page']}?context=edit" );
check( str_contains( (string) ( $pg['content']['raw'] ?? '' ), 'Updated on the round trip.' ), 'reads the revised page' );
[ $s, $me ] = $wb->rest( 'GET', '/wp/v2/users/me?context=edit' );
check( 'Keeps notes on a round trip.' === ( $me['description'] ?? null ), "reads the owner's profile" );
if ( isset( $ids['order'] ) ) {
	[ $s, $o ] = $wb->rest( 'GET', "/wc/v3/orders/{$ids['order']}" );
	$total = $wpDay['ids']['total'] ?? null;
	check( 200 === $s && 'completed' === ( $o['status'] ?? null ) && $total === ( $o['total'] ?? null ) && $ids['product'] === (int) ( $o['line_items'][0]['product_id'] ?? 0 ) && ( 0 === $ids['coupon'] || 'roundtrip10' === ( $o['coupon_lines'][0]['code'] ?? null ) ) && 2 === (int) ( $o['line_items'][0]['quantity'] ?? 0 ), "reads Minn's order: completed, for the product, at the total WordPress's own day reached ({$total})" . ( $ids['coupon'] ? ', with its coupon' : '' ), json_encode( array( $o['total'] ?? null, $o['line_items'][0]['product_id'] ?? null ) ) );
	[ $s, $pr ] = $wb->rest( 'GET', "/wc/v3/products/{$ids['product']}" );
	$stock      = $wpDay['ids']['stock'] ?? null;
	check( $ids['price'] === ( $pr['regular_price'] ?? null ) && $stock === ( $pr['stock_quantity'] ?? null ), '…and the product at its new price, with the stock WordPress\'s own day left (' . json_encode( $stock ) . ')', json_encode( array( $pr['regular_price'] ?? null, $pr['stock_quantity'] ?? null ) ) );
}
[ $s, $d ] = $wb->rest( 'GET', "/wp/v2/posts/{$ids['draft']}?context=edit" );
check( 'trash' === ( $d['status'] ?? null ), 'finds the draft in the trash' );
$editor = new RtClient( $REF, $AS_SITE );
check( $editor->signIn( 'roundtrip-editor', $cfg['editor_password'], $URL, $cfg['login_path'] ), "signs Minn's new editor in" );
[ $s, $ed ] = $editor->rest( 'GET', '/wp/v2/users/me?context=edit' );
check( array( 'editor' ) === ( $ed['roles'] ?? null ), '…as an editor' );
$carried          = new RtClient( $REF, $AS_SITE );
$carried->cookies = $minn->cookies;
$carried->fetchNonce();
[ $s, $who ] = $carried->rest( 'GET', '/wp/v2/users/me' );
check( 200 === $s && ( $me['id'] ?? -1 ) === ( $who['id'] ?? 0 ), "accepts the session Minn signed in (no second sign-in)", json_encode( array( $s, $carried->nonce, $who['id'] ?? $who['code'] ?? null, $me['id'] ?? null ) ) );
[ $s ] = $wb->rest( 'POST', "/wp/v2/posts/{$ids['post']}", array( 'content' => $p['content']['raw'] . "\n\n<!-- wp:paragraph -->\n<p>Back on WordPress.</p>\n<!-- /wp:paragraph -->" ) );
check( 200 === $s, "keeps working: revises Minn's post" );
if ( $ids['reply'] > 0 ) {
	[ $s ] = $wb->rest( 'POST', '/wp/v2/comments', array( 'post' => $ids['post'], 'parent' => $ids['reply'], 'content' => 'Still here.' ) );
	check( 201 === $s, '…and answers the reply' );
}
[ $s, $again ] = $minn->rest( 'GET', "/wp/v2/posts/{$ids['post']}?context=edit" );
[ , $thread ]  = $minn->rest( 'GET', "/wp/v2/comments?post={$ids['post']}" );
check( str_contains( (string) ( $again['content']['raw'] ?? '' ), 'Back on WordPress.' ) && ( $ids['reply'] > 0 ? 3 : 0 ) === count( (array) $thread ), 'and Minn reads what WordPress wrote' );
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
	check( ! rt_footprint( ...$rt->snapshot( $mark ), window: $window() ), 'the site is back at its baseline' );
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
