<?php

declare(strict_types=1);

/**
 * Captures the six request pairs the marketing front page shows in its hero
 * panel, from the engine and the reference on the TEST site (minn.localhost
 * and 8123, never the marketing database: signing in writes a session), and
 * writes site/minn-site/content/parity.json. Each pair is compared the way
 * the suite that owns it compares it; the tool refuses to write when any
 * pair disagrees, so the page can never show a claim the stacks do not keep.
 *
 *   cove twin minn add --as-site=ref.minn.localhost   # once: wp-reference/ answers as the site at ref.minn.localhost
 *   php tests/tools/parity-sample.php
 */

require dirname(__DIR__) . '/lib.php';

$root = dirname(__DIR__, 2);
$target = "{$root}/site/minn-site/content/parity.json";
$ENGINE = minn_test_url();
$REF = minn_test_reference_url();
$SHOWN = 'https://example.com';

$src = (string) file_get_contents(dirname(__DIR__) . '/theme.test.php');
preg_match('/function theme_body.*?\n}\n/s', $src, $m);
eval($m[0]);

/** @return array{0: array<string, mixed>, 1: string} headers (set-cookie as a list) and body */
$request = static function (string $url, string $method = 'GET', string $body = '', array $headers = []): array {
    $ctx = stream_context_create([
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
        'http' => ['method' => $method, 'content' => $body, 'header' => implode("\r\n", $headers), 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 20],
    ]);
    $out = @file_get_contents($url, false, $ctx);
    $h = ['status' => 0, 'set-cookie' => []];
    foreach ($http_response_header ?? [] as $line) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $line, $s) === 1) {
            $h['status'] = (int) $s[1];
            continue;
        }
        [$k, $v] = array_map('trim', explode(':', $line, 2) + [1 => '']);
        $k = strtolower($k);
        if ($k === 'set-cookie') {
            $h['set-cookie'][] = $v;
        } else {
            $h[$k] = $v;
        }
    }
    return [$h, (string) $out];
};

$probe = $request($REF . '/');
if ($probe[0]['status'] === 0) {
    fwrite(STDERR, "the reference is not running at {$REF}\n");
    exit(1);
}

$swap = static fn (string $text): string => str_replace([$REF, str_replace('https://', 'http://', $ENGINE)], $ENGINE, $text);
$shown = static fn (string $text): string => str_replace([$ENGINE, str_replace('https://', 'http://', $ENGINE), $REF], $SHOWN, $text);
$clip = static function (string $text, int $lines = 14) use ($shown): string {
    $rows = array_values(array_filter(explode("\n", $shown($text)), static fn (string $l): bool => trim($l) !== ''));
    $out = array_map(static fn (string $l): string => mb_strlen($l) > 92 ? mb_substr($l, 0, 91) . '…' : $l, array_slice($rows, 0, $lines));
    return implode("\n", $out) . (count($rows) > $lines ? "\n…" : '');
};
$canonical = static function (mixed $value) use (&$canonical): mixed {
    if (!is_array($value)) {
        return $value;
    }
    $value = array_map($canonical, $value);
    if (!array_is_list($value)) {
        ksort($value);
    }
    return $value;
};
$hash = static fn (string $text): string => substr(hash('sha256', $text), 0, 12);
$keep = static function (array $h, array $names): array {
    $out = [];
    foreach ($names as $name) {
        if (isset($h[$name])) {
            $out[] = [$name, $h[$name]];
        }
    }
    return $out;
};
$side = static fn (int $status, array $headers, string $excerpt, ?string $compared): array => [
    'status' => $status,
    'headers' => $headers,
    'excerpt' => $excerpt,
    'bytes' => $compared === null ? 0 : strlen($compared),
    'sha' => $compared === null ? '' : $hash($compared),
];

$rows = [];
$fail = [];

// 1. The edit-context posts list, as the administrator, through a session the reference minted.
$mint = json_decode((string) shell_exec('wp --path=' . escapeshellarg(minn_test_site_root() . '/wp-reference') . ' eval-file ' . escapeshellarg("{$root}/tests/tools/mint-session.php") . ' 1 2>/dev/null'), true);
if (!is_array($mint)) {
    fwrite(STDERR, "could not mint a session on the reference\n");
    exit(1);
}
$names = array_unique([$mint['cookie_name'], 'wordpress_logged_in_' . md5($REF)]);
$auth = ['Cookie: ' . implode('; ', array_map(static fn (string $n): string => $n . '=' . rawurlencode($mint['cookie']), $names)), 'X-WP-Nonce: ' . $mint['nonce']];
$path = '/wp-json/wp/v2/posts/1?context=edit';
$pair = [];
foreach (['engine' => $ENGINE, 'reference' => $REF] as $who => $base) {
    [$h, $b] = $request($base . $path, 'GET', '', $auth);
    // JSON escapes its slashes, so the host swap runs on the decoded text.
    $data = json_decode($swap((string) json_encode(json_decode(minn_test_neutralise($b), true), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)), true);
    $compared = (string) json_encode($canonical($data), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $pretty = (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $pair[$who] = [$h, $data, $side($h['status'], $keep($h, ['content-type']), $clip($pretty, 14), $compared)];
}
$same = $pair['engine'][0]['status'] === $pair['reference'][0]['status'] && minn_test_diff($pair['engine'][1], $pair['reference'][1]) === null;
$same || $fail[] = "{$path}: " . $pair['engine'][0]['status'] . ' vs ' . $pair['reference'][0]['status'] . ', ' . substr((string) minn_test_diff($pair['engine'][1], $pair['reference'][1]), 0, 240);
$rows[] = ['method' => 'GET', 'path' => $path, 'claim' => '200 · identical', 'engine' => $pair['engine'][2], 'reference' => $pair['reference'][2],
    'verdict' => 'The same JSON, key for key, for a signed-in administrator, once the host name is swapped and the per-render layout hash in rendered blocks is masked. The session was minted by WordPress and read by both.'];

// 2. Signing in: each side's logged_in cookie is then sent to the other side's front page.
$form = http_build_query(['log' => 'admin', 'pwd' => 'password', 'testcookie' => '1']);
$jar = [];
$pair = [];
foreach (['engine' => $ENGINE, 'reference' => $REF] as $who => $base) {
    [$h] = $request($base . '/wp-login.php', 'POST', $form, ['Content-Type: application/x-www-form-urlencoded', 'Cookie: wordpress_test_cookie=WP%20Cookie%20check']);
    $set = [];
    foreach ($h['set-cookie'] as $cookie) {
        [$pairText] = explode(';', $cookie, 2);
        [$name, $value] = explode('=', $pairText, 2) + [1 => ''];
        if ($name === 'wordpress_test_cookie' || $value === '') {
            continue;
        }
        $set[$name] = $value;
        str_starts_with($name, 'wordpress_logged_in_') && $jar[$who] = $value;
    }
    $lines = ["HTTP {$h['status']}", 'Location: ' . $shown((string) ($h['location'] ?? ''))];
    foreach (array_keys($set) as $name) {
        $lines[] = 'Set-Cookie: ' . preg_replace('/_[0-9a-f]{32}$/', '_{hash}', $name) . '=…';
    }
    $pair[$who] = [$h, implode("\n", array_unique($lines))];
}
$accepted = [];
foreach ([['engine', $REF, 'reference'], ['reference', $ENGINE, 'engine']] as [$from, $base, $to]) {
    $cookieNames = array_unique(['wordpress_logged_in_' . md5($ENGINE), 'wordpress_logged_in_' . md5($REF)]);
    [$h, $b] = $request($base . '/', 'GET', '', ['Cookie: ' . implode('; ', array_map(static fn (string $n): string => $n . '=' . ($jar[$from] ?? ''), $cookieNames))]);
    $accepted[$to] = preg_match('/<body[^>]*class="[^"]*\blogged-in\b/', $b) === 1;
}
$location = static fn (array $h): string => (string) preg_replace('#^https?://[^/]+#', '', (string) ($h['location'] ?? ''));
$same = $pair['engine'][0]['status'] === 302 && $pair['reference'][0]['status'] === 302
    && $location($pair['engine'][0]) === $location($pair['reference'][0]) && $accepted === ['reference' => true, 'engine' => true];
$same || $fail[] = '/wp-login.php: ' . json_encode(['engine' => $pair['engine'][0]['status'], 'reference' => $pair['reference'][0]['status'], 'accepted' => $accepted]);
foreach (['engine', 'reference'] as $who) {
    $other = $who === 'engine' ? 'reference' : 'engine';
    $pair[$who][1] .= "\n\nIts logged_in cookie on the {$other}'s front page:\n<body class=\"… logged-in …\">";
}
$rows[] = ['method' => 'POST', 'path' => '/wp-login.php', 'claim' => '302 · cookie accepted by both',
    'engine' => $side(302, [], $pair['engine'][1], null),
    'reference' => $side(302, [], $pair['reference'][1], null),
    'verdict' => 'Both answer 302 to the same place. The cookie each side set signs the visitor in on the other. Cookie names carry a hash of each server\'s own address, and the engine answers over HTTPS, so its auth cookie is the secure one.'];

// 3-5. A page, the feed, and the sitemap index.
$pages = [
    ['/sample-page/', '200 · body identical', 'page', 'The same <body>, line for line, compared the way the theme suite compares pages: inline styles and scripts set aside, the per-render layout hash masked.'],
    ['/feed/', '200 · byte for byte', 'probe', 'The same bytes once the host name is swapped and the generator version masked, the way the probe suite compares feeds.'],
    ['/wp-sitemap.xml', '200 · identical', 'probe', 'The same sitemap index once the host name is swapped.'],
];
foreach ($pages as [$path, $claim, $mode, $verdict]) {
    $pair = [];
    foreach (['engine' => $ENGINE, 'reference' => $REF] as $who => $base) {
        [$h, $b] = $request($base . $path);
        $compared = $mode === 'page'
            ? theme_body($b, $REF, $ENGINE)
            : (string) preg_replace('/(\?v=|version=")[0-9.]+/', '${1}X', $swap(minn_test_neutralise($b)));
        // A page shows what was compared; a feed shows its own bytes, since the version mask also rewrites the XML declaration.
        $excerpt = $mode === 'page' ? $clip($compared, 12) : $clip($b, 12);
        $pair[$who] = [$h['status'], $compared, $side($h['status'], $keep($h, ['content-type']), $excerpt, $compared)];
    }
    $same = $pair['engine'][0] === $pair['reference'][0] && $pair['engine'][1] === $pair['reference'][1];
    $same || $fail[] = "{$path}: bodies differ";
    $rows[] = ['method' => 'GET', 'path' => $path, 'claim' => $claim, 'engine' => $pair['engine'][2], 'reference' => $pair['reference'][2], 'verdict' => $verdict];
}

// 6. WP-CLI in each webroot, on the same database.
$cli = [
    'engine' => 'cd ' . escapeshellarg(minn_test_site_root() . '/public') . ' && wp option get siteurl 2>/dev/null',
    'reference' => 'cd ' . escapeshellarg(minn_test_site_root() . '/wp-reference') . ' && wp option get siteurl --skip-plugins --skip-themes 2>/dev/null',
];
$out = array_map(static fn (string $command): string => trim((string) shell_exec($command)), $cli);
$same = $out['engine'] !== '' && $out['engine'] === $out['reference'];
$same || $fail[] = 'wp option get siteurl: ' . json_encode($out);
$rows[] = ['method' => 'CLI', 'path' => 'wp option get siteurl', 'claim' => 'identical',
    'engine' => $side(0, [], '$ wp option get siteurl' . "\n" . $shown($out['engine']), $out['engine']),
    'reference' => $side(0, [], '$ wp option get siteurl' . "\n" . $shown($out['reference']), $out['reference']),
    'verdict' => 'The same answer from WP-CLI run in the engine\'s webroot and in the reference\'s, against one database.'];

if ($fail !== []) {
    fwrite(STDERR, "not written, the stacks disagree:\n  " . implode("\n  ", $fail) . "\n");
    exit(1);
}
$report = ['captured' => date('Y-m-d'), 'host' => $SHOWN, 'rows' => $rows];
file_put_contents($target, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
echo 'wrote site/minn-site/content/parity.json: ' . count($rows) . " pairs, every one identical\n";
