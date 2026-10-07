<?php
/**
 * Canonical-redirect parity with a plugin on it: the move to an address's
 * canonical form (the trailing slash, ?p= to the permalink, doubled
 * slashes, a guess for a mistyped one) is redirect_canonical's at
 * template_redirect on both stacks, so the fixture mu-plugin
 * tests/fixtures/mu-plugins/minn-test-canonical.php can note what it is
 * handed, refuse it (the page then answers as typed), send it elsewhere,
 * or take it off template_redirect. A former slug's move is the
 * reference's own step (wp_old_slug_redirect) and holds whatever the
 * plugin does. Compared are the status, the destination and the notes
 * (for a swapped query-form move only the outcome: the reference's
 * chained-redirect guard asks the filter a second time).
 *
 *   php tests/canonical-hooks.test.php
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

$fixture = __DIR__ . '/fixtures/mu-plugins/minn-test-canonical.php';
$stacks = ['reference' => [$REF, $SITE . '/wp-reference/wp-content'], 'engine' => [$ENGINE, $SITE . '/public/wp-content']];
$run = 'canonical-' . bin2hex(random_bytes(6));
foreach ($stacks as [, $dir]) {
    if (!is_link("{$dir}/mu-plugins/minn-test-canonical.php")) {
        symlink($fixture, "{$dir}/mu-plugins/minn-test-canonical.php");
    }
    @mkdir("{$dir}/minn-canonical", 0755, true);
    touch("{$dir}/minn-canonical/{$run}.open");
}
register_shutdown_function(static function () use ($stacks): void {
    foreach ($stacks as [, $dir]) {
        @unlink("{$dir}/mu-plugins/minn-test-canonical.php");
        array_map('unlink', glob("{$dir}/minn-canonical/*") ?: []);
        @rmdir("{$dir}/minn-canonical");
    }
});

/** Status, destination and the fixture's notes, for one address on one stack. */
$ask = static function (string $stack, string $path, string $mode) use ($stacks, $run, $ENGINE, $REF): array {
    $ch = curl_init($stacks[$stack][0] . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_TIMEOUT => 60, CURLOPT_HTTPHEADER => ["X-Minn-Canonical: {$run}", "X-Minn-Canonical-Mode: {$mode}"]]);
    $raw = (string) curl_exec($ch);
    preg_match('/^location:\s*(\S+)/mi', substr($raw, 0, curl_getinfo($ch, CURLINFO_HEADER_SIZE)), $l);
    $log = "{$stacks[$stack][1]}/minn-canonical/{$run}.log";
    $notes = is_file($log) ? array_values(array_filter(explode("\n", (string) file_get_contents($log)))) : [];
    @unlink($log);
    return [curl_getinfo($ch, CURLINFO_RESPONSE_CODE), str_replace([$REF, str_replace('https://', 'http://', $ENGINE), $ENGINE], '{site}', $l[1] ?? ''), $notes];
};

$paths = ['/hello-world', '/sample-page/docs', '/category/uncategorized', '/?p=1', '/?page_id=2&zz=1', '//hello-world/', '/hello-world/', '/old-hello/'];
foreach (['note', 'off', 'swap', 'unhook'] as $mode) {
    echo "canonical, {$mode}\n";
    foreach ($paths as $path) {
        $e = $ask('engine', $path, $mode);
        $r = $ask('reference', $path, $mode);
        // A swapped query-form move: the reference's chained-redirect guard asks the filter again with the canonical form it
        // derives from the current query; the engine's guard asks its own resolution, so only the outcome is compared.
        if ($mode === 'swap' && str_starts_with($path, '/?')) {
            [$e, $r] = [array_slice($e, 0, 2), array_slice($r, 0, 2)];
        }
        $check("{$path}: status, destination and notes", $e === $r, json_encode(['engine' => $e, 'reference' => $r], JSON_UNESCAPED_SLASHES));
    }
}

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
