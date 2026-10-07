<?php
/**
 * Permalink suite: every public URL shape the front end resolves.
 *
 * Fixture mode (default): each case in contracts/fixtures/front/permalinks.json
 * pins status, redirect target, and the body-class tokens; the engine must
 * match. Live mode adds the oracle: when the reference is running, every
 * case is fetched from it too and compared the same way.
 *
 * Re-capture from the oracle with:  php tests/permalinks.test.php --capture
 * Oracle: (its Cove twin: cove twin minn add --as-site=ref.minn.localhost)
 */

$ENGINE = rtrim(getenv('MINN_TEST_URL') ?: 'https://minn.localhost', '/');
$REF = 'https://ref.minn.localhost';
$FIXTURE = dirname(__DIR__) . '/contracts/fixtures/front/permalinks.json';

// The tokens that are contract; theme and template tokens are not.
const CORE_TOKENS = '/^(home|blog|single|single-post|single-format-\w+|postid-\d+|page|page-id-\d+|page-parent|page-child|parent-pageid-\d+|archive|category|category-[\w-]+|tag|tag-[\w-]+|author|author-[\w-]+|date|search|search-results|search-no-results|tax-post_format|term-[\w-]+|error404|paged|paged-\d+|[a-z-]+-paged-\d+)$/';

$cases = [
    '/', '/page/1/', '/page/2/', '/?paged=2',
    '/hello-world/', '/hello-world', '/HELLO-WORLD/', '/HELLO-WORLD', '/hello-world/?x=1', '/hello-world?x=1',
    '/hello-world/page/2/', '/hello-world/page/2', '/hello-world/2/', '/hello-world/embed/', '/hello-world/embed', '/hello-world/trackback/',
    '/sample-page/', '/sample-page/docs/', '/sample-page/docs', '/Sample-Page/Docs/', '/sample-page/docs/page/2/', '/sample-page/docs/2/',
    '/docs/', '/docs', '/docs/2/', '/hello-world/docs/', '/sample-page/nope/',
    '/privacy-policy/', '/nonexistent/', '/nonexistent', '/uncategorized/',
    '/old-hello/', '/old-hello', '/old-hello-two/', '/old-hello/page/2/', '/old-hello/?x=1', '/old-sample/', '/2024/old-hello/', '/?name=old-hello', '/?pagename=old-sample',
    // A live single's comment-page-N is left to tests/round-trip.test.php: the reference drops the
    // segment only when the request's host is the site's, and this oracle is reached as 127.0.0.1.
    '/old-hello/2/', '/old-hello/trackback/', '/old-hello/embed/', '/old-hello/comment-page-2/', '/nonexistent/comment-page-2/',
    '/?p=1', '/?p=5', '/?p=2', '/?page_id=2', '/?page_id=6', '/?page_id=1', '/?p=10', '/?p=3', '/?p=999',
    '/?name=hello-world', '/?pagename=docs', '/?pagename=sample-page/docs',
    '/?cat=1', '/?tag=engine', '/?author=1', '/?m=202608', '/?year=2026',
    // The root's archive forms: what moves (one taxonomy's term, a date before an author before a term, the
    // other arguments along), what never does (a search, an author or format by name), and what is a 404.
    '/?category_name=uncategorized', '/?category_name=bogus', '/?taxonomy=category&term=uncategorized', '/?taxonomy=post_format&term=post-format-aside',
    '/?cat=1&foo=bar', '/?cat=1&year=2026', '/?cat=1&author=1', '/?tag=engine&cat=1', '/?author_name=admin&cat=1', '/?cat=99', '/?author=99',
    '/?author_name=admin', '/?author_name=bogus', '/?post_format=aside', '/?post_format=bogus', '/?post_type=post', '/?post_type=bogus', '/?error=404',
    '/?s=', '/?s=&category_name=uncategorized', '/?s=x&category_name=uncategorized', '/?s=x&tag=engine', '/?s=x&year=2026', '/?cat=1&s=x',
    '/?year=2025', '/?year=2025&cat=1', '/?year=2025&s=x', '/?name=hello-world&post_type=post', '/?p=1&post_type=post',
    '/category/uncategorized/?s=hello', '/2026/?s=x', '/category/bogus/uncategorized/', '/category/uncategorized/bogus/',
    // Host-dependent moves (the reference makes them under the site's own host): page 1 dropped, a post's
    // listing page sent to the post, feed aliases to the feed form, and the query forms of both.
    '/page/1/', '/page/1', '/page/1/?x=1', '/category/uncategorized/page/1/', '/tag/engine/page/1/', '/author/admin/page/1/',
    '/2026/page/1/', '/search/hello/page/1/', '/?s=hello&paged=1', '/hello-world/page/1/', '/hello-world/page/3/',
    '/sample-page/page/1/', '/sample-page/page/2/', '/hello-world/?paged=2', '/hello-world/?cpage=2',
    '/?p=1&paged=2', '/?p=1&paged=3', '/?p=1&paged=1', '/?p=1&cpage=2', '/?p=1&cpage=1', '/?p=1&cpage=2&x=1', '/?page_id=2&paged=2',
    '/?page_id=2&paged=3', '/?page_id=6&paged=2', '/?p=1&page=2',
    '/rss2/', '/rss/', '/rdf/', '/atom/', '/feed/rss2/', '/feed/rss/', '/feed/atom/', '/feed/rdf/', '/feed/',
    '/comments/rss2/', '/comments/feed/rss2/', '/comments/feed/atom/', '/comments/feed/',
    '/category/uncategorized/rss2/', '/category/uncategorized/feed/rss2/', '/category/uncategorized/feed/atom/',
    '/tag/engine/rss2/', '/author/admin/rss2/', '/2026/rss2/', '/hello-world/rss2/', '/hello-world/feed/rss2/',
    '/hello-world/feed/rss/', '/hello-world/atom/', '/hello-world/feed/atom/', '/search/hello/rss2/', '/search/hello/feed/rss2/',
    '/?feed=rss2', '/?feed=rss', '/?feed=atom', '/?feed=rdf', '/?feed=comments-rss2', '/?feed=comments-atom',
    '/?feed=rss2&cat=1', '/?feed=atom&cat=1', '/?feed=rss2&p=1', '/?feed=rss2&x=1', '/?s=hello&feed=rss2', '/?feed=rss2&tag=engine',
    '/?feed=rss2&author=1', '/?feed=rss2&m=2026', '/?feed=bogus',
    '/hello-world/?feed=rss2', '/category/uncategorized/?feed=atom', '/feed/?feed=atom', '/2026/?feed=rss2', '/?name=hello-world&feed=rss2',
    '/search/hello/?feed=rss2', '/nonexistent/rss2/', '/category/nope/rss2/', '/?page_id=2&cpage=2', '/?page_id=2&paged=1',
    // Attachment pages are off on the test site: every address of one goes to its file.
    '/battery-image/', '/battery-image/?x=1', '/battery-image/embed/', '/battery-image/feed/', '/hello-world/battery-image/',
    '/?attachment_id=607', '/?attachment_id=607&x=1', '/?attachment_id=607&embed=true', '/?attachment=battery-image', '/?p=607',
    '/category/uncategorized/', '/category/uncategorized', '/category/Uncategorized/', '/category/uncategorized/page/2/',
    '/category/uncategorized/hello-world/', '/category/e',
    '/tag/engine/', '/tag/nope/', '/tag/nope', '/tag/e',
    '/author/admin/', '/author/admin', '/author/scribe/', '/author/nobody/', '/author/nobody', '/author/Admin/', '/author/admin/page/2/',
    '/2026/', '/2026', '/2026/08/', '/2026/08/28/', '/2025/', '/2025', '/2026/08/28/hello-world/',
    '/?s=hello', '/search/hello/', '/search/hello',
    '/hello', '/hello-wor', '/hello-wor/page/2/', '/s', '/sc', '/sa', '/e', '/ex', '/ed', '/p', '/t', '/b', '/h', '/d', '/x',
    '/sample-page/s', '/sample-page/e',
    '/index.php/hello-world/',
];

function permalink_fetch(string $base, string $path): array
{
    $context = stream_context_create([
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
        'http' => ['ignore_errors' => true, 'timeout' => 15, 'follow_location' => 0],
    ]);
    $body = (string) @file_get_contents($base . $path, false, $context);
    $status = 0;
    $location = null;
    foreach ($http_response_header ?? [] as $header) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $header, $m)) {
            $status = (int) $m[1];
        } elseif (stripos($header, 'Location:') === 0) {
            $location = trim(substr($header, 9));
        }
    }
    if ($location !== null) {
        $location = preg_replace('#^https?://[^/]+#', '', $location);
    }
    $classes = [];
    if (preg_match('/<body[^>]*class="([^"]*)"/', $body, $m)) {
        $classes = array_values(array_filter(explode(' ', $m[1]), static fn (string $c) => preg_match(CORE_TOKENS, $c) === 1));
        sort($classes);
    }
    return ['status' => $status, 'location' => $location, 'classes' => $classes];
}

if (in_array('--capture', $argv, true)) {
    $probe = @file_get_contents("{$REF}/wp-json/", false, stream_context_create(['http' => ['timeout' => 30, 'ignore_errors' => true]]));
    if ($probe === false) {
        fwrite(STDERR, "Reference not running at {$REF}\n");
        exit(1);
    }
    $captured = [];
    foreach ($cases as $path) {
        $captured[$path] = permalink_fetch($REF, $path);
    }
    @mkdir(dirname($FIXTURE), 0755, true);
    file_put_contents($FIXTURE, json_encode($captured, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    echo 'Captured ' . count($captured) . " cases to {$FIXTURE}\n";
    exit(0);
}

$expected = json_decode((string) file_get_contents($FIXTURE), true);
if (!is_array($expected)) {
    fwrite(STDERR, "Missing fixture; run with --capture while the oracle is up.\n");
    exit(1);
}

$liveProbe = @file_get_contents("{$REF}/wp-json/", false, stream_context_create(['http' => ['timeout' => 30, 'ignore_errors' => true]]));
$live = $liveProbe !== false;
echo $live ? "Live mode: engine vs fixture vs reference\n" : "Fixture mode: reference not running, engine vs fixture only\n";

$pass = 0;
$fail = 0;
foreach ($expected as $path => $want) {
    $got = permalink_fetch($ENGINE, $path);
    $sources = ['engine' => $got];
    if ($live) {
        $sources['reference'] = permalink_fetch($REF, $path);
    }
    foreach ($sources as $label => $actual) {
        $diff = [];
        foreach (['status', 'location', 'classes'] as $key) {
            if ($actual[$key] !== $want[$key]) {
                $diff[] = "{$key}: " . json_encode($actual[$key]) . ' vs ' . json_encode($want[$key]);
            }
        }
        if ($diff === []) {
            $pass++;
            continue;
        }
        $fail++;
        echo "  FAIL {$label} {$path}: " . implode('; ', $diff) . "\n";
    }
}
echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
