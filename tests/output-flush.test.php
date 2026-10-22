<?php
/**
 * Ending every output buffer mid-page (wp_ob_end_flush_all), compared with
 * the reference: the fixture mu-plugin
 * tests/fixtures/mu-plugins/minn-test-flush.php calls it in wp_head, in
 * wp_footer, or after opening a buffer of its own. On each stack the page
 * must be the one it serves without the plugin, its markers aside, under
 * the same status and headers (the reference prints as it goes, so its
 * page only leaves early; the engine's captures hold, Runtime\OutputFloor),
 * and the markers must come in the reference's order. The fixture and the
 * run live only while it runs.
 *
 *   php tests/output-flush.test.php
 */

declare(strict_types=1);

require __DIR__ . '/lib.php';

const FLUSH_HEADERS = ['content-type', 'link', 'x-pingback', 'x-content-type-options', 'x-powered-by', 'cache-control'];

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

$fixture = __DIR__ . '/fixtures/mu-plugins/minn-test-flush.php';
$stacks = ['reference' => [$REF, $SITE . '/wp-reference/wp-content'], 'engine' => [$ENGINE, $SITE . '/public/wp-content']];
$run = 'flush-' . bin2hex(random_bytes(6));
foreach ($stacks as [, $content]) {
    if (!is_link("{$content}/mu-plugins/minn-test-flush.php")) {
        symlink($fixture, "{$content}/mu-plugins/minn-test-flush.php");
    }
    @mkdir("{$content}/minn-flush", 0755, true);
    touch("{$content}/minn-flush/{$run}.open");
}
register_shutdown_function(static function () use ($stacks): void {
    foreach ($stacks as [, $content]) {
        @unlink("{$content}/mu-plugins/minn-test-flush.php");
        array_map('unlink', glob("{$content}/minn-flush/*") ?: []);
        @rmdir("{$content}/minn-flush");
    }
});

/** Status, the compared headers and the body of a page on one stack, flushed in a mode or not. */
$ask = static function (string $stack, string $path, string $mode) use ($stacks, $run): array {
    $ch = curl_init($stacks[$stack][0] . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_TIMEOUT => 60, CURLOPT_HTTPHEADER => $mode === '' ? [] : ["X-Minn-Flush: {$run}:{$mode}"]]);
    $raw = (string) curl_exec($ch);
    $size = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    $headers = [];
    foreach (explode("\n", substr($raw, 0, $size)) as $line) {
        [$name, $value] = array_pad(explode(':', $line, 2), 2, null);
        if ($value !== null && in_array(strtolower(trim($name)), FLUSH_HEADERS, true)) {
            $headers[strtolower(trim($name))][] = trim($value);
        }
    }
    ksort($headers);
    return [$status, $headers, substr($raw, $size)];
};
$markers = static fn (string $body): array => preg_match_all('/<!-- zz-flush ([a-z ]+) -->/', $body, $m) ? $m[1] : [];
$unmarked = static fn (string $body): string => (string) preg_replace('/<!-- zz-flush [a-z ]+ -->\n/', '', $body);

echo "output-flush suite: {$ENGINE} (engine) vs {$REF} (reference)\n";
$path = '/hello-world/';
$plain = ['reference' => $ask('reference', $path, ''), 'engine' => $ask('engine', $path, '')];
foreach (['head', 'footer', 'own'] as $mode) {
    $found = [];
    foreach (array_keys($stacks) as $stack) {
        [$status, $headers, $body] = $ask($stack, $path, $mode);
        [$plainStatus, $plainHeaders, $plainBody] = $plain[$stack];
        $check("{$mode}, {$stack}: the status and headers it serves without the plugin", [$status, $headers] === [$plainStatus, $plainHeaders], json_encode([$status, $headers]) . ' vs ' . json_encode([$plainStatus, $plainHeaders]));
        $check("{$mode}, {$stack}: the page it serves without the plugin, its markers aside", $unmarked($body) === $plainBody, minn_test_diff($plainBody, $unmarked($body)) ?? '');
        $found[$stack] = $markers($body);
    }
    $check("{$mode}: the markers in the reference's order", $found['engine'] === $found['reference'] && $found['reference'] !== [], 'engine=' . json_encode($found['engine']) . ' reference=' . json_encode($found['reference']));
}

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
