<?php
/**
 * Sitemap-hooks parity: a plugin on every seam of the core sitemaps and
 * robots.txt, as SEO plugins are. The fixture mu-plugin
 * tests/fixtures/mu-plugins/minn-test-sitemap.php hooks them for a request
 * whose X-Minn-Sitemap header names a run this suite opened, in the mode
 * X-Minn-Sitemap-Mode names: "markers" (the query arguments, entries,
 * subtypes, page size and stylesheets filtered, a provider and a public
 * post type of its own, a line added to robots.txt), "off" (the sitemaps
 * turned off), "replace" (lists and page counts answered by the plugin),
 * "redirect" (the index sent elsewhere from template_redirect), and
 * "plain" (nothing changed: the query form, page 0, a stray provider or
 * subtype). Both stacks serve the same addresses; each is compared on
 * status, content type and redirect, the body when it is not the theme's
 * page (a stylesheet only for the plugin's mark), and what the plugin heard (each hook with what it was handed, in
 * order). The fixture and the run live only while the suite runs.
 *
 *   php tests/sitemap-hooks.test.php [--show=<mode> <path>]
 */

declare(strict_types=1);

require __DIR__ . '/lib.php';

$ENGINE = minn_test_url();
$REF = minn_test_reference_url();
$SITE = minn_test_site_root();
$show = array_slice($argv, 1);
$show = ($show[0] ?? '') !== '' && str_starts_with($show[0], '--show=') ? [substr($show[0], 7), $show[1] ?? ''] : null;

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

$fixture = __DIR__ . '/fixtures/mu-plugins/minn-test-sitemap.php';
$stacks = ['reference' => [$REF, $SITE . '/wp-reference/wp-content'], 'engine' => [$ENGINE, $SITE . '/public/wp-content']];
$run = 'sitemap-' . bin2hex(random_bytes(6));
foreach ($stacks as [, $content]) {
    if (!is_link("{$content}/mu-plugins/minn-test-sitemap.php")) {
        symlink($fixture, "{$content}/mu-plugins/minn-test-sitemap.php");
    }
    @mkdir("{$content}/minn-sitemap", 0755, true);
    touch("{$content}/minn-sitemap/{$run}.open");
}
register_shutdown_function(static function () use ($stacks): void {
    foreach ($stacks as [, $content]) {
        @unlink("{$content}/mu-plugins/minn-test-sitemap.php");
        array_map('unlink', glob("{$content}/minn-sitemap/*") ?: []);
        @rmdir("{$content}/minn-sitemap");
    }
});

/** Status, content type, Location, body (hosts normalised) and what the fixture heard, for one address on one stack. */
$ask = static function (string $stack, string $path, string $mode) use ($stacks, $run, $ENGINE, $REF): array {
    $ch = curl_init($stacks[$stack][0] . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_TIMEOUT => 60, CURLOPT_HTTPHEADER => ["X-Minn-Sitemap: {$run}", "X-Minn-Sitemap-Mode: {$mode}"]]);
    $raw = (string) curl_exec($ch);
    $size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $head = substr($raw, 0, $size);
    $field = static fn (string $name): string => preg_match('/^' . $name . ':\s*(.*)$/mi', $head, $m) === 1 ? trim($m[1]) : '';
    $normalise = static fn (string $s): string => str_replace([$REF, str_replace('https://', 'http://', $ENGINE), $ENGINE], '{site}', $s);
    $log = "{$stacks[$stack][1]}/minn-sitemap/{$run}.log";
    $heard = is_file($log) ? array_values(array_filter(explode("\n", (string) file_get_contents($log)))) : [];
    @unlink($log);
    $type = $field('content-type');
    // The theme's own pages differ in markup between the stacks, and each has its own stylesheet (the plugin's mark is looked for); a sitemap or robots.txt is compared whole.
    $body = match (true) {
        str_starts_with($type, 'text/html') => '(the theme\'s page)',
        str_ends_with((string) parse_url($path, PHP_URL_PATH), '.xsl') => 'a stylesheet, the plugin\'s mark ' . (str_contains($raw, '<!-- zz index stylesheet -->') ? 'in it' : 'not in it'),
        default => $normalise(substr($raw, $size)),
    };
    return ['status' => curl_getinfo($ch, CURLINFO_RESPONSE_CODE), 'type' => $type, 'location' => $normalise($field('location')), 'body' => $body, 'heard' => $heard];
};
$firstDiff = static function (array $a, array $b): string {
    foreach ($b as $i => $line) {
        if (($a[$i] ?? null) !== $line) {
            return 'line ' . ($i + 1) . ': engine ' . json_encode(substr($a[$i] ?? '(missing)', 0, 300), JSON_UNESCAPED_SLASHES) . "\n       reference " . json_encode(substr($line, 0, 300), JSON_UNESCAPED_SLASHES);
        }
    }
    return count($a) > count($b) ? 'the engine\'s is longer: ' . json_encode(array_slice($a, count($b), 3), JSON_UNESCAPED_SLASHES) : '';
};

$paths = [
    'markers' => ['/wp-sitemap.xml', '/wp-sitemap-posts-post-1.xml', '/wp-sitemap-posts-page-1.xml', '/wp-sitemap-taxonomies-category-1.xml', '/wp-sitemap-taxonomies-post_tag-2.xml', '/wp-sitemap-users-1.xml', '/wp-sitemap-zzextra-1.xml', '/wp-sitemap-zzextra-2.xml', '/wp-sitemap-posts-zz_sitemap_book-1.xml', '/wp-sitemap-posts-post-2.xml', '/wp-sitemap-index.xsl', '/robots.txt', '/hello-world/'],
    'off' => ['/wp-sitemap.xml', '/wp-sitemap-posts-post-1.xml', '/wp-sitemap.xsl', '/robots.txt'],
    'replace' => ['/wp-sitemap.xml', '/wp-sitemap-posts-post-2.xml', '/wp-sitemap-posts-post-9.xml', '/wp-sitemap-taxonomies-post_tag-1.xml', '/wp-sitemap-users-1.xml', '/wp-sitemap-posts-page-1.xml'],
    'redirect' => ['/wp-sitemap.xml', '/wp-sitemap-users-1.xml'],
    'plain' => ['/wp-sitemap-foo-1.xml', '/?sitemap=index', '/?sitemap=posts&sitemap-subtype=post&paged=1', '/wp-sitemap-posts-post-0.xml', '/wp-sitemap-taxonomies-nope-1.xml', '/robots.txt'],
];
foreach ($paths as $mode => $list) {
    echo "sitemaps, {$mode}\n";
    foreach ($list as $path) {
        $e = $ask('engine', $path, $mode);
        $r = $ask('reference', $path, $mode);
        if ($show === [$mode, $path]) {
            echo "--- reference\n{$r['body']}\n" . implode("\n", $r['heard']) . "\n--- engine\n{$e['body']}\n" . implode("\n", $e['heard']) . "\n";
        }
        $check("{$path}: status, type and redirect", [$e['status'], $e['type'], $e['location']] === [$r['status'], $r['type'], $r['location']], json_encode(['engine' => [$e['status'], $e['type'], $e['location']], 'reference' => [$r['status'], $r['type'], $r['location']]], JSON_UNESCAPED_SLASHES));
        $check("{$path}: the body", $e['body'] === $r['body'], $firstDiff(explode("\n", $e['body']), explode("\n", $r['body'])));
        $check("{$path}: what the plugin heard", $e['heard'] === $r['heard'], $firstDiff($e['heard'], $r['heard']));
    }
}

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
