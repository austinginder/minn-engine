<?php
/**
 * Front lifecycle parity: what plugins are told on a page request, and the
 * headers it ends with. Signed-out GETs run on both stacks with the fixture
 * mu-plugin tests/fixtures/mu-plugins/minn-test-trace.php recording every
 * action; the lifecycle's own actions (parse_request, the main query's
 * parse_query, pre_get_posts and posts_selection, set_404, send_headers,
 * wp, template_redirect) must come in the reference's order up to
 * template_redirect (rendering's own queries come after), and the
 * Link, X-Pingback, no-cache and content-type headers must agree. After
 * template_redirect, the loop as the template renders it must agree too:
 * loop_start, the_post and loop_end in order (a singular page's template
 * renders inside the main query's loop; a query block or a latest-posts
 * block walks its query with the_post and sets the main query's post up
 * again after). loop_no_results is left out: the
 * reference also fires it as each template part looks for a saved copy,
 * a query the engine does not make.
 *
 *   php tests/front-lifecycle.test.php
 */

declare(strict_types=1);

require __DIR__ . '/lib.php';

const LIFECYCLE = ['parse_request', 'parse_query', 'pre_get_posts', 'posts_selection', 'set_404', 'send_headers', 'wp', 'template_redirect'];
const HEADERS = ['link', 'x-pingback', 'cache-control', 'expires', 'content-type'];

$ENGINE = minn_test_url();
$REF = minn_test_reference_url();
$SITE = minn_test_site_root();
$WP = '/opt/homebrew/bin/wp --path=' . escapeshellarg($SITE . '/wp-reference');

[$ph] = minn_test_fetch($REF . '/', 3);
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

// The tracer lives in both mu-plugin folders, and records, only while the suite runs.
$fixture = dirname(__DIR__) . '/tests/fixtures/mu-plugins/minn-test-trace.php';
$stacks = ['reference' => [$REF, $SITE . '/wp-reference/wp-content'], 'engine' => [$ENGINE, $SITE . '/public/wp-content']];
foreach ($stacks as [, $content]) {
    if (!is_link("{$content}/mu-plugins/minn-test-trace.php")) {
        symlink($fixture, "{$content}/mu-plugins/minn-test-trace.php");
    }
    @mkdir("{$content}/minn-trace", 0755, true);
}
register_shutdown_function(static function () use ($stacks): void {
    foreach ($stacks as [, $content]) {
        @unlink("{$content}/mu-plugins/minn-test-trace.php");
        array_map('unlink', glob("{$content}/minn-trace/*") ?: []);
        @rmdir("{$content}/minn-trace");
    }
});

// A page whose content holds query loops: one inheriting the main query inside the singular page's loop, one that finds nothing, one that finds two posts.
$loopQuery = static fn (int $id, string $query): string => '<!-- wp:query {"queryId":' . $id . ',"query":' . $query . '} --><div class="wp-block-query"><!-- wp:post-template --><!-- wp:post-title /--><!-- /wp:post-template --><!-- wp:query-no-results --><!-- wp:paragraph --><p>None.</p><!-- /wp:paragraph --><!-- /wp:query-no-results --></div><!-- /wp:query -->';
$loopContent = $loopQuery(71, '{"perPage":3,"inherit":true}')
    . $loopQuery(72, '{"perPage":3,"postType":"post","order":"desc","orderBy":"date","search":"zz-no-such-words","inherit":false}')
    . $loopQuery(73, '{"perPage":2,"postType":"post","order":"desc","orderBy":"date","sticky":"exclude","inherit":false}');
$loopPage = (int) trim((string) shell_exec("{$WP} post create --post_type=page --post_status=publish --post_name=zz-lifecycle-loops --post_title='zz lifecycle loops' --post_content=" . escapeshellarg($loopContent) . ' --porcelain 2>/dev/null'));
register_shutdown_function(static function () use ($WP, $loopPage): void {
    if ($loopPage > 1) {
        shell_exec("{$WP} post delete {$loopPage} --force >/dev/null 2>&1");
    }
});

/** One traced GET: [status, the lifecycle actions in order, the compared headers, the loop as rendering walks it]. */
$get = static function (string $base, string $content, string $run, string $path): array {
    touch("{$content}/minn-trace/{$run}.open");
    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_HTTPHEADER => ['X-Minn-Trace: ' . $run], CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_TIMEOUT => 60]);
    $raw = (string) curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $head = substr($raw, 0, (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE));
    $headers = [];
    foreach (preg_split('/\r?\n/', $head) ?: [] as $line) {
        if (preg_match('/^([A-Za-z-]+):\s*(.*)$/', $line, $m) === 1 && in_array(strtolower($m[1]), HEADERS, true)) {
            $headers[] = strtolower($m[1]) . ': ' . str_replace([$base, minn_test_url()], '{home}', trim($m[2]));
        }
    }
    sort($headers);
    // The request's own steps end at template_redirect; the loop rendering walks comes after it.
    $actions = [];
    $loop = [];
    $rendering = false;
    foreach (@file("{$content}/minn-trace/{$run}.ndjson", FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        $hook = (string) (json_decode($line, true)[0] ?? '');
        if (!$rendering && in_array($hook, LIFECYCLE, true)) {
            $actions[] = $hook;
        }
        if ($rendering && in_array($hook, ['loop_start', 'the_post', 'loop_end'], true)) {
            $loop[] = $hook;
        }
        $rendering = $rendering || $hook === 'template_redirect';
    }
    return [$status, $actions, array_map(static fn (string $h) => str_replace('charset=utf-8', 'charset=UTF-8', $h), $headers), $loop];
};

$paths = [
    'the home listing' => '/',
    'a search' => '/?s=alpha',
    'a post' => '/hello-world/',
    'a page' => '/sample-page/',
    'a category' => '/category/uncategorized/',
    'an author' => '/author/admin/',
    'a missing page' => '/no-such-page/',
    'a page of query loops' => '/zz-lifecycle-loops/',
];
foreach ($paths as $label => $path) {
    $run = 'front-' . substr(md5($path), 0, 10);
    [$refStatus, $refActions, $refHeaders, $refLoop] = $get($REF, $stacks['reference'][1], "{$run}-r", $path);
    [$status, $actions, $headers, $loop] = $get($ENGINE, $stacks['engine'][1], "{$run}-e", $path);
    $check("{$label}: status", $status === $refStatus, "{$status} vs {$refStatus}");
    $check("{$label}: the lifecycle's actions in order", $actions === $refActions, implode(' ', $actions) . "\n       vs " . implode(' ', $refActions));
    $check("{$label}: the loop as the template renders it", $loop === $refLoop, implode(' ', $loop) . "\n       vs " . implode(' ', $refLoop));
    $check("{$label}: Link, pingback, caching and type headers", $headers === $refHeaders, json_encode($headers, JSON_UNESCAPED_SLASHES) . "\n       vs " . json_encode($refHeaders, JSON_UNESCAPED_SLASHES));
}

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
