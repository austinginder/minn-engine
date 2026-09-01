<?php
/**
 * Non-GET methods on the public site. The reference runs canonical
 * redirects (trailing slash, pretty-URL mapping, 404 guessing) only for
 * GET and HEAD; POST and the rest render what the query alone finds, as
 * typed, except the old-slug redirect, which fires for every method.
 * Pinned rows in contracts/fixtures/front/methods.json; --capture rewrites
 * them from the reference. Body parity is diffed live for two POST renders.
 */
require_once __DIR__ . '/lib.php';

$ENGINE = rtrim(getenv('MINN_TEST_URL') ?: 'https://minn.localhost', '/');
$REF = 'http://127.0.0.1:8123';
$FIXTURE = dirname(__DIR__) . '/contracts/fixtures/front/methods.json';

// method + path; expected status and location live in the fixture.
$rows = [
    ['POST', '/sample-page/'],
    ['POST', '/sample-page'],
    ['POST', '/hello-world'],
    ['POST', '/nonexistent/'],
    ['POST', '/hello'],
    ['POST', '/sample-page/2'],
    ['POST', '/?p=1'],
    ['POST', '/?p=2'],
    ['POST', '/?page_id=2'],
    ['POST', '/?name=hello-world'],
    ['POST', '/?pagename=docs'],
    ['POST', '/?cat=1'],
    ['POST', '/?author=1'],
    ['POST', '/?m=202608'],
    ['POST', '/category/uncategorized'],
    ['POST', '/2026/08'],
    ['POST', '/block-battery-text/'],
    ['PUT', '/sample-page/'],
    ['DELETE', '/nonexistent/'],
];

function method_fetch(string $base, string $method, string $path): array
{
    $context = stream_context_create([
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
        'http' => ['method' => $method, 'ignore_errors' => true, 'timeout' => 20, 'follow_location' => 0, 'content' => ''],
    ]);
    $body = (string) @file_get_contents($base . $path, false, $context);
    $status = 0;
    $location = '';
    foreach ($http_response_header ?? [] as $header) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $header, $m)) {
            $status = (int) $m[1];
        } elseif (stripos($header, 'Location:') === 0) {
            $location = (string) preg_replace('#^https?://[^/]+#', '', trim(substr($header, 9)));
        }
    }
    return [$status, $location, minn_test_neutralise($body)];
}

$refUp = @file_get_contents($REF . '/?rest_route=/', false, stream_context_create(['http' => ['timeout' => 2, 'ignore_errors' => true]])) !== false;

if (in_array('--capture', $argv, true)) {
    if (!$refUp) {
        fwrite(STDERR, "reference not running on {$REF}\n");
        exit(1);
    }
    $fixture = [];
    foreach ($rows as [$method, $path]) {
        [$status, $location] = method_fetch($REF, $method, $path);
        $fixture[] = ['method' => $method, 'path' => $path, 'status' => $status, 'location' => $location];
    }
    file_put_contents($FIXTURE, json_encode($fixture, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    echo 'captured ' . count($fixture) . " rows\n";
    exit(0);
}

$passed = 0;
$failed = 0;
$check = function (bool $ok, string $name, string $detail = '') use (&$passed, &$failed): void {
    if ($ok) {
        $passed++;
        echo "  ok   {$name}\n";
    } else {
        $failed++;
        echo "  FAIL {$name}" . ($detail !== '' ? " ({$detail})" : '') . "\n";
    }
};

$fixture = json_decode((string) file_get_contents($FIXTURE), true);
$check(is_array($fixture) && $fixture !== [], 'fixture loads');

foreach ((array) $fixture as $row) {
    $label = "{$row['method']} {$row['path']}";
    [$status, $location] = method_fetch($ENGINE, (string) $row['method'], (string) $row['path']);
    $check(
        $status === (int) $row['status'] && $location === (string) $row['location'],
        "{$label} matches fixture",
        "{$status} {$location} vs {$row['status']} {$row['location']}"
    );
    if ($refUp) {
        [$refStatus, $refLocation] = method_fetch($REF, (string) $row['method'], (string) $row['path']);
        $check(
            $status === $refStatus && $location === $refLocation,
            "{$label} matches the reference",
            "{$status} {$location} vs {$refStatus} {$refLocation}"
        );
    }
}

// Two POST renders diff against the reference body (the theme suite's
// normalisation: body only, scripts/styles/links stripped, host mapped).
if ($refUp) {
    $normalise = static function (string $html) use ($REF, $ENGINE): string {
        if (!preg_match('/<body.*<\/body>/s', $html, $m)) {
            return '';
        }
        $body = (string) preg_replace(
            ['/<style\b[^>]*>.*?<\/style>/s', '/<script\b[^>]*>.*?<\/script>/s', '/<link\b[^>]*>/'],
            '',
            $m[0]
        );
        $body = str_replace([$REF, str_replace('https://', 'http://', $ENGINE), $ENGINE], '', $body);
        $lines = array_filter(array_map('rtrim', explode("\n", $body)), static fn (string $l) => trim($l) !== '');
        return implode("\n", $lines);
    };
    foreach (['/sample-page/', '/?cat=1'] as $path) {
        [, , $engineBody] = method_fetch($ENGINE, 'POST', $path);
        [, , $refBody] = method_fetch($REF, 'POST', $path);
        $check($normalise($engineBody) === $normalise($refBody), "POST {$path} body matches the reference");
    }
} else {
    echo "  skip body parity: reference not running\n";
}

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
