<?php
/**
 * Embed-template parity: a post's /embed/ page, the card another site's
 * iframe shows, with a plugin on every seam of it. The fixture mu-plugin
 * tests/fixtures/mu-plugins/minn-test-embed.php hooks the page for a
 * request whose X-Minn-Embed header names a run this suite opened (a head
 * tag and a style, marks on the content, meta, footer, excerpt and site
 * name, the template and image filters heard). Both stacks serve the same
 * addresses: posts and a page, one with a wide featured image and one
 * with a square one (and the wide one asked square by the plugin), a
 * protected post, an address that embeds nothing,
 * the ?embed= form and the addresses that move. Each is compared on
 * status, redirect and X-WP-embed, the whole page (hosts, the sharing
 * dialog's random ids and the embed secret masked; the reference's emoji
 * plumbing and per-block stylesheets set aside, as on every engine page),
 * and what the plugin heard. The suite's posts, images and run live only
 * while it runs.
 *
 *   php tests/embed-template.test.php [--show=<path>]
 */

declare(strict_types=1);

require __DIR__ . '/lib.php';

$ENGINE = minn_test_url();
$REF = minn_test_reference_url();
$SITE = minn_test_site_root();
$WP = '/opt/homebrew/bin/wp --path=' . escapeshellarg($SITE . '/wp-reference');
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

// The suite's own posts and images, by exact title; anything an earlier run left is removed first.
$made = __DIR__ . '/fixtures/embed-template-content.php';
$content = json_decode((string) shell_exec("{$WP} eval-file " . escapeshellarg($made) . ' make 2>/dev/null'), true);
if (!is_array($content) || !isset($content['wide'], $content['square'], $content['locked'])) {
    echo "FAIL: could not make the suite's posts\n";
    exit(1);
}
register_shutdown_function(static function () use ($WP, $made): void {
    shell_exec("{$WP} eval-file " . escapeshellarg($made) . ' remove >/dev/null 2>&1');
});

$fixture = __DIR__ . '/fixtures/mu-plugins/minn-test-embed.php';
$stacks = ['reference' => [$REF, $SITE . '/wp-reference/wp-content'], 'engine' => [$ENGINE, $SITE . '/public/wp-content']];
$run = 'embed-' . bin2hex(random_bytes(6));
foreach ($stacks as [, $dir]) {
    if (!is_link("{$dir}/mu-plugins/minn-test-embed.php")) {
        symlink($fixture, "{$dir}/mu-plugins/minn-test-embed.php");
    }
    @mkdir("{$dir}/minn-embed", 0755, true);
    touch("{$dir}/minn-embed/{$run}.open");
}
register_shutdown_function(static function () use ($stacks): void {
    foreach ($stacks as [, $dir]) {
        @unlink("{$dir}/mu-plugins/minn-test-embed.php");
        array_map('unlink', glob("{$dir}/minn-embed/*") ?: []);
        @rmdir("{$dir}/minn-embed");
    }
});

/** Status, Location, X-WP-embed, the page (normalised) and what the fixture heard, for one address on one stack. */
$ask = static function (string $stack, string $path, string $mode) use ($stacks, $run, $ENGINE, $REF): array {
    $ch = curl_init($stacks[$stack][0] . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_TIMEOUT => 60, CURLOPT_HTTPHEADER => ["X-Minn-Embed: {$run}", "X-Minn-Embed-Mode: {$mode}"]]);
    $raw = (string) curl_exec($ch);
    $size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $head = substr($raw, 0, $size);
    $field = static fn (string $name): string => preg_match('/^' . $name . ':\s*(.*)$/mi', $head, $m) === 1 ? trim($m[1]) : '';
    $hosts = static fn (string $s): string => str_replace([$REF, str_replace('https://', 'http://', $ENGINE), $ENGINE], '{site}', $s);
    $body = (string) preg_replace([
        // The reference's emoji plumbing and per-block stylesheets, which no engine page prints.
        '#<style id="wp-emoji-styles-inline-css">.*?</style>\n#s',
        '#<script id="wp-emoji-settings" type="application/json">.*?</script>\n#s',
        '#<script type="module">\n/\*! This file is auto-generated \*/\nvar e="script\#wp-emoji-settings".*?</script>\n#s',
        "#<link rel='stylesheet' id='wp-block-[a-z-]+-css' [^>]*/>\n#",
        // The sharing dialog's ids carry a random number; the embed code carries a random secret.
        '/(wp-embed-share-(?:tab|description)-(?:wordpress|html)-\d+)-\d+/',
        '/(data-secret=&quot;|#\?secret=)[A-Za-z0-9]{10}/',
        '/([?&]ver=)[0-9.]+/',
    ], ['', '', '', '', '$1-R', '$1S', '$1X'], $hosts(substr($raw, $size)));
    $log = "{$stacks[$stack][1]}/minn-embed/{$run}.log";
    $heard = is_file($log) ? array_values(array_filter(explode("\n", (string) file_get_contents($log)))) : [];
    @unlink($log);
    return ['status' => curl_getinfo($ch, CURLINFO_RESPONSE_CODE), 'location' => $hosts($field('location')), 'embed' => $field('x-wp-embed'), 'body' => $body, 'heard' => $heard];
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
    'markers' => ['/hello-world/embed/', '/sample-page/embed/', '/zz-embed-wide/embed/', '/zz-embed-square/embed/', '/zz-embed-locked/embed/', '/nope/embed/', '/hello-world/?embed=true', '/hello-world/embed', '/?p=1&embed=true', '/?page_id=2&embed=true&zz=1'],
    'square' => ['/zz-embed-wide/embed/'],
];
foreach ($paths as $mode => $list) {
    echo "embeds, {$mode}\n";
    foreach ($list as $path) {
        $e = $ask('engine', $path, $mode);
        $r = $ask('reference', $path, $mode);
        if ($show === $path) {
            echo "--- reference\n{$r['body']}\n" . implode("\n", $r['heard']) . "\n--- engine\n{$e['body']}\n" . implode("\n", $e['heard']) . "\n";
        }
        $check("{$path}: status, redirect and X-WP-embed", [$e['status'], $e['location'], $e['embed']] === [$r['status'], $r['location'], $r['embed']], json_encode(['engine' => [$e['status'], $e['location'], $e['embed']], 'reference' => [$r['status'], $r['location'], $r['embed']]], JSON_UNESCAPED_SLASHES));
        if ($r['status'] < 300 || $r['status'] >= 400) {
            $check("{$path}: the page", $e['body'] === $r['body'], $firstDiff(explode("\n", $e['body']), explode("\n", $r['body'])));
        }
        $check("{$path}: what the plugin heard", $e['heard'] === $r['heard'], $firstDiff($e['heard'], $r['heard']));
    }
}

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
