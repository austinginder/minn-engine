<?php
/**
 * Probe suite: feeds, sitemaps, robots.txt, xmlrpc, cron, the admin entry,
 * and the REST index, compared with the reference on status, content type,
 * and (for feeds and sitemaps) body after host and generator normalisation.
 * Pinned copies in contracts/fixtures/probes/; re-capture with --capture.
 */
require_once __DIR__ . '/lib.php';

$ENGINE = rtrim(getenv('MINN_TEST_URL') ?: 'https://minn.localhost', '/');
$REF = 'https://ref.minn.localhost';
$DIR = dirname(__DIR__) . '/contracts/fixtures/probes';

// path => [status, content-type prefix, compare body?]
$probes = [
    '/feed/' => [200, 'application/rss+xml', true],
    '/feed' => [301, '', false],
    '/feed/rss2/' => [200, 'application/rss+xml', true],
    '/feed/atom/' => [200, 'application/atom+xml', true],
    '/feed/rdf/' => [200, 'application/rdf+xml', true],
    '/?feed=rss2' => [200, 'application/rss+xml', true],
    '/comments/feed/' => [200, 'application/rss+xml', true],
    '/hello-world/feed/' => [200, 'application/rss+xml', true],
    '/category/uncategorized/feed/' => [200, 'application/rss+xml', true],
    '/tag/engine/feed/' => [200, 'application/rss+xml', true],
    '/author/admin/feed/' => [200, 'application/rss+xml', true],
    '/wp-sitemap.xml' => [200, 'application/xml', true],
    '/wp-sitemap-posts-post-1.xml' => [200, 'application/xml', true],
    '/wp-sitemap-posts-page-1.xml' => [200, 'application/xml', true],
    '/wp-sitemap-taxonomies-category-1.xml' => [200, 'application/xml', true],
    '/wp-sitemap-taxonomies-post_tag-1.xml' => [200, 'application/xml', true],
    '/wp-sitemap-users-1.xml' => [200, 'application/xml', true],
    '/wp-sitemap-posts-post-9.xml' => [404, 'text/html', false],
    '/wp-sitemap.xsl' => [200, 'application/xml', false],
    '/robots.txt' => [200, 'text/plain', true],
    '/xmlrpc.php' => [405, 'text/plain', true],
    '/wp-cron.php' => [200, 'text/html', true],
    '/wp-admin/' => [302, '', false],
    '/wp-json/' => [200, 'application/json', false],
];

function probe_fetch(string $base, string $path): array
{
    $context = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false], 'http' => ['ignore_errors' => true, 'timeout' => 20, 'follow_location' => 0]]);
    $body = (string) @file_get_contents($base . $path, false, $context);
    $status = 0;
    $type = '';
    foreach ($http_response_header ?? [] as $header) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $header, $m)) {
            $status = (int) $m[1];
        } elseif (stripos($header, 'Content-Type:') === 0) {
            $type = trim(substr($header, 13));
        }
    }
    return [$status, $type, $body];
}

function probe_normalise(string $body, string $host, string $engine): string
{
    $body = str_replace([$host, str_replace('https://', 'http://', $engine)], $engine, $body);
    $body = preg_replace('/(\?v=|version=")[0-9.]+/', '${1}X', $body);
    $body = preg_replace('/(wp-container-core-[a-z-]+-is-layout-)[0-9a-f]{8}/', '$1HASH', $body);
    $body = preg_replace('/wp-block-search__input-\d+/', 'wp-block-search__input-N', $body);
    return preg_replace('/(wp-block-gallery-|is-style-[a-z-]+--)\d+/', '$1N', $body);
}

$slug = static fn (string $path) => trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(str_replace('?', '-query-', $path))), '-') ?: 'home';
if (in_array('--capture', $argv, true)) {
    @mkdir($DIR, 0755, true);
    foreach ($probes as $path => [, , $compare]) {
        if ($compare) {
            file_put_contents("$DIR/{$slug($path)}.txt", probe_normalise(probe_fetch($REF, $path)[2], $REF, $ENGINE));
        }
    }
    echo "Captured probe bodies to $DIR\n";
    exit(0);
}

$live = @file_get_contents("$REF/wp-json/", false, stream_context_create(['http' => ['timeout' => 30, 'ignore_errors' => true]])) !== false;
$pass = 0;
$fail = 0;
$check = static function (bool $ok, string $label, string $detail = '') use (&$pass, &$fail): void {
    if ($ok) { $pass++; echo "  ok   $label\n"; } else { $fail++; echo "  FAIL $label" . ($detail === '' ? '' : ": $detail") . "\n"; }
};
$firstDiff = static function (string $a, string $b): string {
    $la = explode("\n", $a); $lb = explode("\n", $b);
    foreach ($lb as $i => $line) {
        if (($la[$i] ?? null) !== $line) {
            return 'line ' . ($i + 1) . ' want ' . json_encode(substr($line, 0, 120)) . ' got ' . json_encode(substr($la[$i] ?? '(missing)', 0, 120));
        }
    }
    return count($la) > count($lb) ? 'engine output longer' : 'identical';
};

foreach ($probes as $path => [$status, $type, $compare]) {
    [$es, $et, $eb] = probe_fetch($ENGINE, $path);
    $check($es === $status && ($type === '' || str_starts_with($et, $type)), "$path status and type", "$es $et");
    if (!$compare) {
        continue;
    }
    $engine = probe_normalise($eb, $REF, $ENGINE);
    $fixture = @file_get_contents("$DIR/{$slug($path)}.txt");
    if ($fixture !== false) {
        $check($engine === $fixture, "$path body matches fixture", $firstDiff($engine, $fixture));
    }
    if ($live) {
        $reference = probe_normalise(probe_fetch($REF, $path)[2], $REF, $ENGINE);
        $check($engine === $reference, "$path body matches reference", $firstDiff($engine, $reference));
    }
}
if ($live) {
    $index = json_decode(probe_fetch($ENGINE, '/wp-json/')[2], true);
    $reference = json_decode(probe_fetch($REF, '/wp-json/')[2], true);
    foreach (['name', 'description', 'gmt_offset', 'timezone_string', 'page_for_posts', 'page_on_front', 'show_on_front', 'site_logo', 'site_icon'] as $key) {
        $check(($index[$key] ?? null) === ($reference[$key] ?? null), "index $key matches", json_encode($index[$key] ?? null));
    }
    $check(in_array('wp/v2', $index['namespaces'] ?? [], true) && in_array('minn-admin/v1', $index['namespaces'] ?? [], true), 'index lists the wp/v2 and minn-admin/v1 namespaces');
    $check(isset($index['routes']['/wp/v2/posts']), 'index describes /wp/v2/posts');
    $advertised = array_keys($index['routes'] ?? []);
    $check(array_filter($advertised, static fn (string $r): bool => str_contains($r, '(?P<base>')) === [], 'index advertises no per-type catch-all (each declared type is listed under its rest_base)');
    $check(count(array_filter($advertised, static fn (string $r): bool => str_starts_with($r, '/minn-admin/v1/system/logs/'))) === 1, 'system/logs/{id} is listed once, in the escaping the plugin uses', implode(' ', array_filter($advertised, static fn (string $r): bool => str_starts_with($r, '/minn-admin/v1/system/logs/'))));
    $noRoute = [];
    foreach ($advertised as $route) {
        if (str_contains($route, '(?P<') || $route === '/' || !in_array('GET', $index['routes'][$route]['methods'] ?? [], true)) {
            continue;
        }
        [$status, , $body] = probe_fetch($ENGINE, '/wp-json' . $route);
        if ($status === 404 && str_contains((string) $body, 'rest_no_route')) {
            $noRoute[] = $route;
        }
    }
    $check($noRoute === [], 'every advertised literal GET route answers something other than no-route', implode(' ', $noRoute));
    foreach (['/wp/v2', '/minn-admin/v1'] as $namespace) {
        [$status, , $body] = probe_fetch($ENGINE, '/wp-json' . $namespace);
        [$refStatus, , $refBody] = probe_fetch($REF, '/wp-json' . $namespace);
        $ours = json_decode((string) $body, true);
        $theirs = json_decode((string) $refBody, true);
        $check($status === 200 && $refStatus === 200 && array_keys($ours ?? []) === array_keys($theirs ?? []) && ($ours['namespace'] ?? '') === ($theirs['namespace'] ?? '-'), "namespace index $namespace has the reference's shape", "$status vs $refStatus: " . implode(',', array_keys($ours ?? [])));
        $check(($ours['_links']['up'][0]['href'] ?? '') === "$ENGINE/wp-json/" && isset($ours['routes'][$namespace]) && array_filter(array_keys($ours['routes'] ?? []), static fn (string $r): bool => !str_starts_with($r, $namespace)) === [], "namespace index $namespace links up and lists only its own routes");
    }
    [$status, , $body] = probe_fetch($ENGINE, '/wp-json/nope/v9');
    $check($status === 404 && str_contains((string) $body, 'rest_no_route'), 'an unknown namespace index is no-route');
}
echo "\n$pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
