<?php
/**
 * Plugin-rules parity: rewrite rules a plugin changes rather than adds
 * (category archives without their base, as SEO plugins strip it; rules
 * put in through rewrite_rules_array, generate_rewrite_rules and the
 * section filters), on both stacks. The fixture mu-plugin
 * tests/fixtures/mu-plugins/minn-test-rules.php changes them while the
 * suite's run is open; the reference's stored rules are rebuilt with the
 * changes and rebuilt again without them at the end. Each address is
 * compared on status and where a move goes, and on what the request
 * amounted to at template_redirect: the query vars, the conditionals and
 * the queried object.
 *
 *   php tests/plugin-rules.test.php
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

$fixture = __DIR__ . '/fixtures/mu-plugins/minn-test-rules.php';
$stacks = ['reference' => [$REF, $SITE . '/wp-reference/wp-content'], 'engine' => [$ENGINE, $SITE . '/public/wp-content']];
$run = 'rules-' . bin2hex(random_bytes(6));
/** The reference keeps its rules stored: dropped, they are rebuilt by the next request, with the fixture's changes or without. */
$rebuild = static function () use ($WP, $REF): void {
    shell_exec("{$WP} option delete rewrite_rules >/dev/null 2>&1");
    minn_test_fetch($REF . '/', 10);
};
foreach ($stacks as [, $dir]) {
    if (!is_link("{$dir}/mu-plugins/minn-test-rules.php")) {
        symlink($fixture, "{$dir}/mu-plugins/minn-test-rules.php");
    }
    @mkdir("{$dir}/minn-rules", 0755, true);
    touch("{$dir}/minn-rules/{$run}.open");
}
register_shutdown_function(static function () use ($stacks, $rebuild): void {
    foreach ($stacks as [, $dir]) {
        @unlink("{$dir}/mu-plugins/minn-test-rules.php");
        array_map('unlink', glob("{$dir}/minn-rules/*") ?: []);
        @rmdir("{$dir}/minn-rules");
    }
    $rebuild();
});
$rebuild();

/** Status, where a move goes, and what the fixture noted, for one address on one stack. */
$ask = static function (string $stack, string $path) use ($stacks, $run, $ENGINE, $REF): array {
    $ch = curl_init($stacks[$stack][0] . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_TIMEOUT => 60, CURLOPT_HTTPHEADER => ["X-Minn-Rules: {$run}"]]);
    $raw = (string) curl_exec($ch);
    $head = substr($raw, 0, curl_getinfo($ch, CURLINFO_HEADER_SIZE));
    preg_match('/^location:\s*(\S+)/mi', $head, $l);
    $log = "{$stacks[$stack][1]}/minn-rules/{$run}.log";
    $noted = is_file($log) ? array_values(array_filter(explode("\n", (string) file_get_contents($log)))) : [];
    @unlink($log);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    return [$status, str_replace([$REF, str_replace('https://', 'http://', $ENGINE), $ENGINE], '{site}', $l[1] ?? ''), $noted];
};

echo "plugin rules\n";
foreach (['/uncategorized/', '/uncategorized/page/2/', '/uncategorized/feed/', '/category/uncategorized/', '/zz-shelf/engine/', '/zz-shelf/nope/', '/zz-writer/admin/', '/zz-go/1/', '/zz-go/999999/',
    '/zz-day/2026/08/28/', '/zz-in/uncategorized/hello-world/', '/zz-view/sample-page/', '/hello-world/', '/sample-page/', '/tag/engine/', '/author/admin/'] as $path) {
    $e = $ask('engine', $path);
    $r = $ask('reference', $path);
    $check("{$path}: status, move and what the request amounted to", $e === $r, json_encode(['engine' => $e, 'reference' => $r], JSON_UNESCAPED_SLASHES));
}

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
