<?php
/**
 * Rewrite-endpoint parity: endpoints a plugin adds (add_rewrite_endpoint,
 * as a shop's account pages and an app's routes are), on both stacks. The
 * fixture mu-plugin tests/fixtures/mu-plugins/minn-test-endpoint.php adds
 * zzend (pages, posts and the root) and zzpage (pages only) while the
 * suite's run is open; the reference's stored rules are rebuilt with them
 * and rebuilt again without them at the end. Each address is compared on
 * status and where a move goes, and on what the request amounted to at
 * template_redirect: the endpoint vars, get_query_var, the conditionals
 * and the queried object (a move is the engine's before the page's steps
 * run, so for one only the move is compared).
 *
 *   php tests/rewrite-endpoints.test.php
 */

declare(strict_types=1);

require __DIR__ . '/lib.php';

$ENGINE = minn_test_url();
$REF = minn_test_reference_url();
$SITE = minn_test_site_root();
$WP = '/opt/homebrew/bin/wp --path=' . escapeshellarg($SITE . '/wp-reference');

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

$fixture = __DIR__ . '/fixtures/mu-plugins/minn-test-endpoint.php';
$stacks = ['reference' => [$REF, $SITE . '/wp-reference/wp-content'], 'engine' => [$ENGINE, $SITE . '/public/wp-content']];
$run = 'endpoint-' . bin2hex(random_bytes(6));
/** The reference keeps its rules stored: dropped, they are rebuilt by the next request, with the fixture's endpoints or without. */
$rebuild = static function () use ($WP, $REF): void {
    shell_exec("{$WP} option delete rewrite_rules >/dev/null 2>&1");
    minn_test_fetch($REF . '/', 10);
};
foreach ($stacks as [, $dir]) {
    if (!is_link("{$dir}/mu-plugins/minn-test-endpoint.php")) {
        symlink($fixture, "{$dir}/mu-plugins/minn-test-endpoint.php");
    }
    @mkdir("{$dir}/minn-endpoint", 0755, true);
    touch("{$dir}/minn-endpoint/{$run}.open");
}
register_shutdown_function(static function () use ($stacks, $rebuild): void {
    foreach ($stacks as [, $dir]) {
        @unlink("{$dir}/mu-plugins/minn-test-endpoint.php");
        array_map('unlink', glob("{$dir}/minn-endpoint/*") ?: []);
        @rmdir("{$dir}/minn-endpoint");
    }
    $rebuild();
});
$rebuild();

/** Status, where a move goes, and what the fixture noted, for one address on one stack. */
$ask = static function (string $stack, string $path) use ($stacks, $run, $ENGINE, $REF): array {
    $ch = curl_init($stacks[$stack][0] . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_TIMEOUT => 60, CURLOPT_HTTPHEADER => ["X-Minn-Endpoint: {$run}"]]);
    $raw = (string) curl_exec($ch);
    $head = substr($raw, 0, curl_getinfo($ch, CURLINFO_HEADER_SIZE));
    preg_match('/^location:\s*(\S+)/mi', $head, $l);
    $log = "{$stacks[$stack][1]}/minn-endpoint/{$run}.log";
    $noted = is_file($log) ? array_values(array_filter(explode("\n", (string) file_get_contents($log)))) : [];
    @unlink($log);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    return [$status, str_replace([$REF, str_replace('https://', 'http://', $ENGINE), $ENGINE], '{site}', $l[1] ?? ''), $status >= 300 && $status < 400 ? '(moved)' : $noted];
};

echo "rewrite endpoints\n";
foreach (['/sample-page/zzend/', '/sample-page/zzend/abc/', '/sample-page/zzend/a/b/', '/hello-world/zzend/', '/hello-world/zzend/7/', '/zzend/', '/zzend/x/', '/hello-world/zzpage/',
    '/sample-page/zzpage/v/', '/sample-page/docs/zzend/x/', '/category/uncategorized/zzend/', '/sample-page/zzend', '/hello-world/zzend', '/nope/zzend/', '/sample-page/'] as $path) {
    $e = $ask('engine', $path);
    $r = $ask('reference', $path);
    $check("{$path}: status, move and what the request amounted to", $e === $r, json_encode(['engine' => $e, 'reference' => $r], JSON_UNESCAPED_SLASHES));
}

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
