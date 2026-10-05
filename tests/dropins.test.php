<?php
/**
 * Drop-ins: stages the files in tests/fixtures/dropins into both stacks'
 * wp-content (and a .maintenance file into both webroots for the maintenance
 * checks), runs tests/tools/dropins-probe.php through WP-CLI on the reference
 * and on the engine with WP_CACHE on, compares, and removes everything it
 * staged, also when a check dies halfway.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require __DIR__ . '/lib.php';
$pass = 0;
$fail = 0;
$check = static function (string $label, bool $ok, string $detail = '') use (&$pass, &$fail): void {
    if ($ok) {
        $pass++;
        echo "  ok   {$label}\n";
    } else {
        $fail++;
        echo "  FAIL {$label}" . ($detail !== '' ? " ({$detail})" : '') . "\n";
    }
};

$site = minn_test_site_root();
$stacks = ['engine' => "{$site}/public", 'reference' => "{$site}/wp-reference"];
$fixtures = "{$root}/tests/fixtures/dropins";
$staged = [];
$stage = static function (string $from, string $to) use (&$staged): void {
    copy($from, $to);
    $staged[] = $to;
};
$unstage = static function () use (&$staged): void {
    foreach ($staged as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    $staged = [];
};
register_shutdown_function($unstage);
$wp = static function (string $dir, string $args): string {
    return trim((string) shell_exec('cd ' . escapeshellarg($dir) . " && wp {$args} 2>/dev/null"));
};

// 1. The data-layer drop-ins and the page cache's early file, read from inside a command.
foreach ($stacks as $dir) {
    foreach (['object-cache.php', 'db.php', 'advanced-cache.php'] as $file) {
        $stage("{$fixtures}/{$file}", "{$dir}/wp-content/{$file}");
    }
}
$probe = escapeshellarg("{$root}/tests/tools/dropins-probe.php");
$exec = escapeshellarg('define("WP_CACHE", true);');
$out = [];
foreach ($stacks as $name => $dir) {
    $out[$name] = $wp($dir, "--exec={$exec} eval-file {$probe}");
}
$unstage();
if (($dump = getenv('MINN_DROPINS_DUMP')) !== false && is_dir($dump)) {
    file_put_contents("{$dump}/dropins-ref.json", $out['reference']);
    file_put_contents("{$dump}/dropins-eng.json", $out['engine']);
}
$expected = json_decode($out['reference'], true);
$actual = json_decode($out['engine'], true);
if (!is_array($expected) || !is_array($actual)) {
    echo '  FAIL the probe did not produce JSON on ' . (!is_array($expected) ? 'the reference' : 'the engine') . ":\n" . substr(!is_array($expected) ? $out['reference'] : $out['engine'], 0, 2000) . "\n";
    exit(1);
}
$byLabel = [];
foreach ($actual as [$label, $value]) {
    $byLabel[$label] = $value;
}
foreach ($expected as [$label, $value]) {
    $have = array_key_exists($label, $byLabel);
    $check($label, $have && json_encode($byLabel[$label]) === json_encode($value), ($have ? json_encode($byLabel[$label], JSON_UNESCAPED_SLASHES) : 'missing') . ' vs ' . json_encode($value, JSON_UNESCAPED_SLASHES));
}

// 2. Without WP_CACHE the page cache's file stays out.
foreach ($stacks as $dir) {
    $stage("{$fixtures}/advanced-cache.php", "{$dir}/wp-content/advanced-cache.php");
}
$plain = [];
foreach ($stacks as $name => $dir) {
    $plain[$name] = $wp($dir, "eval 'echo json_encode(\$GLOBALS[\"minn_probe_advanced_cache\"] ?? \"not loaded\");'");
}
$unstage();
$check('without WP_CACHE advanced-cache.php is not loaded', $plain['engine'] === $plain['reference'] && $plain['reference'] === '"not loaded"', json_encode($plain));

// 2b. With WP_CACHE on for a page request, the page cache's early file loads
// at global scope, after the hook API and before the database and object cache.
foreach ($stacks as $dir) {
    $stage("{$fixtures}/advanced-cache-early.php", "{$dir}/wp-content/advanced-cache.php");
}
$front = [];
foreach ($stacks as $name => $dir) {
    $boot = 'define("WP_CACHE", true); $_SERVER["HTTP_HOST"] = "minn.localhost"; $_SERVER["REQUEST_URI"] = "/"; $_SERVER["REQUEST_METHOD"] = "GET"; $_SERVER["SERVER_NAME"] = "minn.localhost"; $_SERVER["HTTPS"] = "on"; require "index.php";';
    $stderr = (string) shell_exec('cd ' . escapeshellarg($dir) . ' && php -r ' . escapeshellarg($boot) . ' 2>&1 >/dev/null');
    $front[$name] = preg_match('/^MINN-PROBE (.*)$/m', $stderr, $m) ? $m[1] : 'no report';
}
$unstage();
$check('a page request loads advanced-cache.php as the reference does', $front['engine'] === $front['reference'] && str_contains($front['reference'], '"top-level variable is global":true'), json_encode($front));

// 3. Maintenance mode over HTTP: the default page, then the site's own.
$fetch = static function (string $url): array {
    $context = stream_context_create(['http' => ['ignore_errors' => true, 'follow_location' => 0], 'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
    $body = (string) @file_get_contents($url, false, $context);
    $status = 0;
    $headers = [];
    foreach ($http_response_header ?? [] as $line) {
        if (preg_match('#^HTTP/\S+ (\d+)#', $line, $m)) {
            $status = (int) $m[1];
        } elseif (preg_match('/^(Retry-After|Content-Type):\s*(.*)$/i', $line, $m)) {
            $headers[strtolower($m[1])] = trim($m[2]);
        }
    }
    ksort($headers);
    return [$status, $headers, $body];
};
$urls = ['engine' => minn_test_url() . '/', 'reference' => minn_test_reference_url() . '/'];
foreach ($stacks as $dir) {
    file_put_contents("{$dir}/.maintenance", '<?php $upgrading = ' . time() . ';');
    $staged[] = "{$dir}/.maintenance";
}
$pages = [];
foreach ($urls as $name => $url) {
    $pages[$name] = $fetch($url);
}
$check('maintenance: status and headers', [$pages['engine'][0], $pages['engine'][1]] === [$pages['reference'][0], $pages['reference'][1]], json_encode([$pages['engine'][0], $pages['engine'][1]]) . ' vs ' . json_encode([$pages['reference'][0], $pages['reference'][1]]));
// The page is the engine's own design; what monitors read is the title and the message.
$said = static fn (string $html): array => [preg_match('#<title>(.*?)</title>#s', $html, $m) ? trim($m[1]) : null, str_contains($html, 'Briefly unavailable for scheduled maintenance. Check back in a minute.')];
$check('maintenance: the default page says so', $said($pages['engine'][2]) === $said($pages['reference'][2]) && $said($pages['reference'][2]) === ['Maintenance', true], json_encode($said($pages['engine'][2])) . ' vs ' . json_encode($said($pages['reference'][2])));
foreach ($stacks as $dir) {
    $stage("{$fixtures}/maintenance.php", "{$dir}/wp-content/maintenance.php");
}
foreach ($urls as $name => $url) {
    $pages[$name] = $fetch($url);
}
$check('maintenance: the site\'s own page', [$pages['engine'][0], $pages['engine'][2]] === [$pages['reference'][0], $pages['reference'][2]], json_encode([$pages['engine'][0], substr($pages['engine'][2], 0, 120)]) . ' vs ' . json_encode([$pages['reference'][0], substr($pages['reference'][2], 0, 120)]));
foreach ($stacks as $dir) {
    file_put_contents("{$dir}/.maintenance", '<?php $upgrading = ' . (time() - 700) . ';');
}
foreach ($urls as $name => $url) {
    $pages[$name] = $fetch($url)[0];
}
$check('maintenance: a stale .maintenance is ignored', $pages['engine'] === $pages['reference'] && $pages['reference'] === 200, json_encode($pages));
$unstage();

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
