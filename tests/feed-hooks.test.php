<?php
/**
 * Feed-hooks parity: a plugin on every seam of the site's feeds, as
 * podcast, SEO and syndication plugins are. The fixture mu-plugin
 * tests/fixtures/mu-plugins/minn-test-feed.php marks each feed action
 * where it fires and each feed filter's result, for a request whose
 * X-Minn-Feed header names a run this suite opened; both stacks then serve
 * the same feeds, compared on status, content type and the whole body
 * (hosts and versions normalised): RSS 2.0, Atom, RDF and RSS 0.92 for the
 * site, its archives, its comments and a post's comments, and the ?feed=
 * form. A custom feed added with add_feed answers too; with
 * X-Minn-Feed-Mode the RSS 2.0 handler is replaced (do_feed_rss2) or the
 * feed is redirected from template_redirect. The fixture and the run live
 * only while the suite runs.
 *
 *   php tests/feed-hooks.test.php [--show=<path>]
 */

declare(strict_types=1);

require __DIR__ . '/lib.php';

$ENGINE = minn_test_url();
$REF = minn_test_reference_url();
$SITE = minn_test_site_root();
$show = null;
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--show=')) {
        $show = substr($arg, 7);
    }
}

[$ph] = minn_test_fetch($REF . '/?rest_route=/', 3);
if (($ph['status'] ?? 0) !== 200) {
    echo "SKIP: reference WordPress not running at {$REF}\n";
    exit(0);
}

$pass = 0;
$fail = 0;
$check = static function (string $label, bool $ok, string $detail = '') use (&$pass, &$fail): void {
    if ($ok) {
        $pass++;
        echo "  ok   {$label}\n";
    } else {
        $fail++;
        echo "  FAIL {$label}" . ($detail !== '' ? "\n       {$detail}" : '') . "\n";
    }
};

$fixture = __DIR__ . '/fixtures/mu-plugins/minn-test-feed.php';
$stacks = ['reference' => [$REF, $SITE . '/wp-reference/wp-content'], 'engine' => [$ENGINE, $SITE . '/public/wp-content']];
$run = 'feed-' . bin2hex(random_bytes(6));
foreach ($stacks as [, $content]) {
    if (!is_link("{$content}/mu-plugins/minn-test-feed.php")) {
        symlink($fixture, "{$content}/mu-plugins/minn-test-feed.php");
    }
    @mkdir("{$content}/minn-feed", 0755, true);
    touch("{$content}/minn-feed/{$run}.open");
}
register_shutdown_function(static function () use ($stacks): void {
    foreach ($stacks as [, $content]) {
        @unlink("{$content}/mu-plugins/minn-test-feed.php");
        array_map('unlink', glob("{$content}/minn-feed/*") ?: []);
        @rmdir("{$content}/minn-feed");
    }
});

/** Status, content type, Location and body for a feed on one stack, the host and version normalised. */
$ask = static function (string $stack, string $path, string $mode) use ($stacks, $run, $ENGINE, $REF): array {
    $ch = curl_init($stacks[$stack][0] . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_TIMEOUT => 60, CURLOPT_HTTPHEADER => ["X-Minn-Feed: {$run}", "X-Minn-Feed-Mode: {$mode}"]]);
    $raw = (string) curl_exec($ch);
    $size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $head = substr($raw, 0, $size);
    $type = preg_match('/^content-type:\s*(.*)$/mi', $head, $m) === 1 ? trim($m[1]) : '';
    $location = preg_match('/^location:\s*(.*)$/mi', $head, $l) === 1 ? trim($l[1]) : '';
    // Hosts, versions, and the per-page counters blocks number their layouts and inputs by, as the probes suite masks them.
    $normalise = static fn (string $s): string => (string) preg_replace(['/(\?v=|version=")[0-9.]+|(WordPress\/)[0-9.]+/', '/(wp-container-core-[a-z-]+-is-layout-)[0-9a-f]{8}/', '/wp-block-search__input-\d+/', '/(wp-block-gallery-|is-style-[a-z-]+--)\d+/'], ['$1$2X', '$1HASH', 'wp-block-search__input-N', '$1N'], str_replace([$REF, str_replace('https://', 'http://', $ENGINE), $ENGINE], '{site}', $s));
    return ['status' => curl_getinfo($ch, CURLINFO_RESPONSE_CODE), 'type' => $type, 'location' => $normalise($location), 'body' => $normalise(substr($raw, $size))];
};
$firstDiff = static function (string $a, string $b): string {
    $la = explode("\n", $a);
    $lb = explode("\n", $b);
    foreach ($lb as $i => $line) {
        if (($la[$i] ?? null) !== $line) {
            return 'line ' . ($i + 1) . ': engine ' . json_encode(substr($la[$i] ?? '(missing)', 0, 300)) . "\n       reference " . json_encode(substr($line, 0, 300));
        }
    }
    return count($la) > count($lb) ? 'the engine\'s is longer' : '';
};

$paths = [
    'markers' => ['/feed/', '/feed/atom/', '/feed/rdf/', '/feed/rss/', '/comments/feed/', '/comments/feed/atom/', '/hello-world/feed/', '/hello-world/feed/atom/', '/category/uncategorized/feed/', '/?feed=rss2', '/?feed=atom&cat=1', '/?feed=zzfeed', '/?feed=nope'],
    'replace' => ['/feed/', '/hello-world/feed/', '/feed/atom/'],
    'redirect' => ['/feed/', '/?feed=zzfeed'],
];
foreach ($paths as $mode => $list) {
    echo "feeds, {$mode}\n";
    foreach ($list as $path) {
        $e = $ask('engine', $path, $mode);
        $r = $ask('reference', $path, $mode);
        if ($show === $path) {
            echo "--- reference\n{$r['body']}\n--- engine\n{$e['body']}\n";
        }
        $check("{$path}: status, type and redirect", [$e['status'], $e['type'], $e['location']] === [$r['status'], $r['type'], $r['location']], json_encode(['engine' => [$e['status'], $e['type'], $e['location']], 'reference' => [$r['status'], $r['type'], $r['location']]], JSON_UNESCAPED_SLASHES));
        $check("{$path}: the body, marks and all", $e['body'] === $r['body'], $firstDiff($e['body'], $r['body']));
    }
}

echo "feeds, headers and conditional GET\n";
/** The headers a feed is sent with that say when it changed, and the status for a reader who sends them back. */
$conditional = static function (string $stack, string $path, array $send = []) use ($stacks, $ENGINE, $REF): array {
    $ch = curl_init($stacks[$stack][0] . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_NOBODY => false, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_TIMEOUT => 60, CURLOPT_HTTPHEADER => $send]);
    $raw = (string) curl_exec($ch);
    $head = substr($raw, 0, curl_getinfo($ch, CURLINFO_HEADER_SIZE));
    $field = static fn (string $name): string => preg_match('/^' . $name . ':\s*(.*)$/mi', $head, $m) === 1 ? trim($m[1]) : '';
    return ['status' => curl_getinfo($ch, CURLINFO_RESPONSE_CODE), 'last-modified' => $field('last-modified'), 'etag' => $field('etag'), 'link' => str_replace([$REF, $ENGINE], '{site}', $field('link')), 'body' => strlen(substr($raw, curl_getinfo($ch, CURLINFO_HEADER_SIZE)))];
};
foreach (['/feed/', '/comments/feed/', '/hello-world/feed/'] as $path) {
    $e = $conditional('engine', $path);
    $r = $conditional('reference', $path);
    $check("{$path}: Last-Modified, ETag and the REST link", [$e['last-modified'], $e['etag'], $e['link']] === [$r['last-modified'], $r['etag'], $r['link']], json_encode(['engine' => $e, 'reference' => $r], JSON_UNESCAPED_SLASHES));
    foreach (['the same ETag' => ["If-None-Match: {$r['etag']}"], 'the same date' => ["If-Modified-Since: {$r['last-modified']}"], 'both' => ["If-None-Match: {$r['etag']}", "If-Modified-Since: {$r['last-modified']}"], 'another ETag and the same date' => ['If-None-Match: "zz"', "If-Modified-Since: {$r['last-modified']}"]] as $what => $send) {
        $ec = $conditional('engine', $path, $send);
        $rc = $conditional('reference', $path, $send);
        $check("{$path}: a reader sending {$what} is answered the same", [$ec['status'], $ec['body'] === 0] === [$rc['status'], $rc['body'] === 0], json_encode(['engine' => [$ec['status'], $ec['body']], 'reference' => [$rc['status'], $rc['body']]]));
    }
}

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
