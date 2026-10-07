<?php
/**
 * Request-parse parity: what the reference's request parse amounts to, as
 * plugins read it at template_redirect. The fixture mu-plugin
 * tests/fixtures/mu-plugins/minn-test-request.php notes $wp->query_vars,
 * $wp->request and $wp->query_string, the main query's own query array and
 * a few get_query_var answers, for a request whose X-Minn-Request header
 * names a run this suite opened. Both stacks are asked for the core
 * address shapes: home and its pages, a post, pages and a child page, an
 * attachment, the archives (category, tag, author, dates, search), their
 * paged, feed, comment-page and embed forms, query-string forms, and
 * addresses that find nothing, and query-string forms that move (the
 * move is redirect_canonical's, at template_redirect after the fixture's
 * note, on both stacks).
 *
 *   php tests/request-vars.test.php
 */

declare(strict_types=1);

require __DIR__ . '/lib.php';

$ENGINE = minn_test_url();
$REF = minn_test_reference_url();
$SITE = minn_test_site_root();

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

$fixture = __DIR__ . '/fixtures/mu-plugins/minn-test-request.php';
$stacks = ['reference' => [$REF, $SITE . '/wp-reference/wp-content'], 'engine' => [$ENGINE, $SITE . '/public/wp-content']];
$run = 'request-' . bin2hex(random_bytes(6));
foreach ($stacks as [, $dir]) {
    if (!is_link("{$dir}/mu-plugins/minn-test-request.php")) {
        symlink($fixture, "{$dir}/mu-plugins/minn-test-request.php");
    }
    @mkdir("{$dir}/minn-request", 0755, true);
    touch("{$dir}/minn-request/{$run}.open");
}
register_shutdown_function(static function () use ($stacks): void {
    foreach ($stacks as [, $dir]) {
        @unlink("{$dir}/mu-plugins/minn-test-request.php");
        array_map('unlink', glob("{$dir}/minn-request/*") ?: []);
        @rmdir("{$dir}/minn-request");
    }
});

/** Status, destination and what the fixture noted, for one address on one stack. */
$ask = static function (string $stack, string $path) use ($stacks, $run, $ENGINE, $REF): array {
    $ch = curl_init($stacks[$stack][0] . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_TIMEOUT => 60, CURLOPT_HTTPHEADER => ["X-Minn-Request: {$run}"]]);
    $raw = (string) curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    preg_match('/^location:\s*(\S+)/mi', substr($raw, 0, curl_getinfo($ch, CURLINFO_HEADER_SIZE)), $l);
    $log = "{$stacks[$stack][1]}/minn-request/{$run}.log";
    $noted = is_file($log) ? json_decode((string) file_get_contents($log), true) : null;
    @unlink($log);
    return ['status' => $status, 'to' => str_replace([$REF, str_replace('https://', 'http://', $ENGINE), $ENGINE], '{site}', $l[1] ?? ''), 'noted' => $noted];
};

echo "request vars\n";
foreach (['/', '/page/2/', '/hello-world/', '/sample-page/', '/sample-page/docs/', '/battery-image/', '/category/uncategorized/', '/category/uncategorized/page/2/', '/tag/engine/', '/author/admin/',
    '/2026/', '/2026/08/', '/2026/08/28/', '/search/hello/', '/?s=hello', '/?s=hello&paged=2', '/hello-world/embed/', '/sample-page/embed/', '/nope/', '/a/b/c/', '/category/nope/',
    '/?p=1', '/?page_id=2', '/?cat=1', '/?p=1&preview=true', '/?p=1&preview_id=1&zz=1', '/?p=1&cpage=2', '/?p=1&page=2', '/?p=1&paged=2', '/?p=1&embed=true'] as $path) {
    $e = $ask('engine', $path);
    $r = $ask('reference', $path);
    $check("{$path}: status, move and the parse", $e === $r, json_encode(['engine' => $e, 'reference' => $r], JSON_UNESCAPED_SLASHES));
}

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
