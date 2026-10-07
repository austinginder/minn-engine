<?php
/**
 * Front lifecycle parity: what plugins are told on a page request, and the
 * headers it ends with. Signed-out GETs run on both stacks with the fixture
 * mu-plugin tests/fixtures/mu-plugins/minn-test-trace.php recording every
 * action; the lifecycle's own actions (parse_request, the main query's
 * parse_query, pre_get_posts and posts_selection, set_404, send_headers,
 * wp, template_redirect) must come in the reference's order up to
 * template_redirect (rendering's own queries come after), and the
 * Link, X-Pingback, no-cache and content-type headers must agree.
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

/** One traced GET: [status, the lifecycle actions in order, the compared headers]. */
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
            $headers[] = strtolower($m[1]) . ': ' . str_replace($base, '{home}', trim($m[2]));
        }
    }
    sort($headers);
    // The request's own steps end at template_redirect; what rendering queries after it is another comparison.
    $actions = [];
    foreach (@file("{$content}/minn-trace/{$run}.ndjson", FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        $hook = (string) (json_decode($line, true)[0] ?? '');
        if (in_array($hook, LIFECYCLE, true)) {
            $actions[] = $hook;
        }
        if ($hook === 'template_redirect') {
            break;
        }
    }
    return [$status, $actions, array_map(static fn (string $h) => str_replace('charset=utf-8', 'charset=UTF-8', $h), $headers)];
};

$paths = [
    'the home listing' => '/',
    'a search' => '/?s=alpha',
    'a post' => '/hello-world/',
    'a page' => '/sample-page/',
    'a category' => '/category/uncategorized/',
    'an author' => '/author/admin/',
    'a missing page' => '/no-such-page/',
];
foreach ($paths as $label => $path) {
    $run = 'front-' . substr(md5($path), 0, 10);
    [$refStatus, $refActions, $refHeaders] = $get($REF, $stacks['reference'][1], "{$run}-r", $path);
    [$status, $actions, $headers] = $get($ENGINE, $stacks['engine'][1], "{$run}-e", $path);
    $check("{$label}: status", $status === $refStatus, "{$status} vs {$refStatus}");
    $check("{$label}: the lifecycle's actions in order", $actions === $refActions, implode(' ', $actions) . "\n       vs " . implode(' ', $refActions));
    $check("{$label}: Link, pingback, caching and type headers", $headers === $refHeaders, json_encode($headers, JSON_UNESCAPED_SLASHES) . "\n       vs " . json_encode($refHeaders, JSON_UNESCAPED_SLASHES));
}

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
