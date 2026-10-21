<?php
/**
 * Differential REST fuzzing (measure 33): requests generated from the
 * reference's own REST index, each sent to the engine and to the reference
 * (the same database), compared by status, body and the X-WP-Total headers.
 *
 * Every route the reference's index lists is requested once plain, once with
 * an unknown argument and once per mutation of each argument its schema
 * declares: wrong types, values outside an enum, boundaries past minimum and
 * maximum, bad formats, empty and oversized strings. Path captures are filled
 * with an existing id and a missing one. Each request goes out anonymous, as
 * the author (user 3) and as the administrator (user 1), signed in with a
 * session minted on the reference (the cookie and nonce hold on both stacks).
 * The generation is enumerated, not random, so a pass repeats exactly.
 *
 * This pass sends reads (GET) only: a write the engine wrongly accepted would
 * change the shared database. Writes come with the side-effect triggers.
 *
 *   php tests/tools/rest-fuzz.php [--route=<regex>] [--roles=anon,author,admin]
 *       [--limit=<requests per route>] [--write] [--show]
 *
 * --write records the pass in contracts/rest-fuzz.json (summary: routes,
 * requests, differences; then each difference), which scorecard.php reads.
 * --show prints each difference as it is found.
 */

require_once dirname(__DIR__) . '/lib.php';

$root = dirname(__DIR__, 2);
$engine = rtrim(getenv('MINN_TEST_URL') ?: minn_test_url(), '/');
$reference = minn_test_reference_url();
$options = getopt('', ['route:', 'roles:', 'limit:', 'write', 'show']);
$routeFilter = isset($options['route']) ? '#' . str_replace('#', '\\#', (string) $options['route']) . '#' : null;
$roleNames = explode(',', (string) ($options['roles'] ?? 'anon,author,admin'));
$limit = (int) ($options['limit'] ?? 60);
$show = isset($options['show']);

[$head, $body] = minn_test_fetch("{$reference}/?rest_route=/", 20);
$index = json_decode($body, true);
if ($head['status'] !== 200 || !is_array($index['routes'] ?? null)) {
    echo "SKIP: the reference's REST index did not answer at {$reference}\n";
    exit(0);
}

/** A session minted on the reference for a user: cookie name and value, and a wp_rest nonce. */
$mint = static function (int $user) use ($root): ?array {
    $command = 'wp --path=' . escapeshellarg(minn_test_site_root() . '/wp-reference') . ' eval-file ' . escapeshellarg("{$root}/tests/tools/mint-session.php") . " {$user} 2>/dev/null";
    $session = json_decode((string) shell_exec($command), true);
    return is_array($session) ? $session : null;
};
$users = ['anon' => null, 'author' => 3, 'admin' => 1];
$sessions = [];
foreach ($roleNames as $role) {
    if (!array_key_exists($role, $users)) {
        fwrite(STDERR, "unknown role {$role}\n");
        exit(1);
    }
    $sessions[$role] = $users[$role] === null ? null : $mint($users[$role]);
}
// The minted sessions are destroyed when the pass ends, so none is left in the user's meta.
register_shutdown_function(static function () use ($sessions, $users): void {
    foreach ($sessions as $role => $session) {
        if (is_array($session) && isset($session['token'])) {
            $code = sprintf('WP_Session_Tokens::get_instance(%d)->destroy(%s);', $users[$role], var_export((string) $session['token'], true));
            shell_exec('wp --path=' . escapeshellarg(minn_test_site_root() . '/wp-reference') . ' eval ' . escapeshellarg($code) . ' 2>/dev/null');
        }
    }
});

/** One GET to a stack: status, the X-WP-Total headers, and the body with the reference's origin written as the engine's. */
$send = static function (string $base, string $route, array $query, ?array $session) use ($reference, $engine): array {
    $headers = [];
    if ($session !== null) {
        $headers[] = 'Cookie: ' . $session['cookie_name'] . '=' . $session['cookie'] . '; wordpress_logged_in_' . md5($reference) . '=' . $session['cookie'];
        $headers[] = 'X-WP-Nonce: ' . $session['nonce'];
    }
    $context = stream_context_create([
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
        'http' => ['method' => 'GET', 'header' => implode("\r\n", $headers), 'ignore_errors' => true, 'timeout' => 20],
    ]);
    $url = "{$base}/index.php?rest_route=" . rawurlencode($route) . ($query === [] ? '' : '&' . http_build_query($query));
    $raw = @file_get_contents($url, false, $context);
    $status = 0;
    $totals = [];
    foreach ($http_response_header ?? [] as $line) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $line, $m) === 1) {
            $status = (int) $m[1];
        } elseif (preg_match('#^(x-wp-total|x-wp-totalpages):\s*(.*)$#i', $line, $m) === 1) {
            $totals[strtolower($m[1])] = trim($m[2]);
        }
    }
    $text = str_replace([str_replace('/', '\\/', $reference), $reference], [str_replace('/', '\\/', $engine), $engine], (string) $raw);
    $json = json_decode(minn_test_neutralise($text), true);
    return ['status' => $status, 'totals' => $totals, 'body' => $json ?? $text];
};

/**
 * The values a schema invites trouble with, each with a label.
 *
 * @param array<string, mixed> $schema
 * @return list<array{0: string, 1: mixed}>
 */
$mutations = static function (array $schema): array {
    $types = (array) ($schema['type'] ?? 'string');
    $out = [['empty', '']];
    if (array_intersect($types, ['integer', 'number']) !== []) {
        array_push($out, ['word', 'abc'], ['negative', '-1'], ['zero', '0'], ['fraction', '1.5'], ['huge', '99999999999999999999'], ['padded', ' 5']);
        foreach (['minimum' => -1, 'maximum' => 1] as $bound => $step) {
            if (is_numeric($schema[$bound] ?? null)) {
                $out[] = ["past {$bound}", (string) ($schema[$bound] + $step)];
                $out[] = ["at {$bound}", (string) $schema[$bound]];
            }
        }
    }
    if (in_array('boolean', $types, true)) {
        array_push($out, ['maybe', 'maybe'], ['one', '1'], ['upper TRUE', 'TRUE'], ['no', 'no']);
    }
    if (in_array('string', $types, true)) {
        array_push($out, ['long', str_repeat('a', 300)], ['markup', '<b>zzfuzz</b>'], ['as array', ['zzfuzz']]);
        foreach (array_slice((array) ($schema['enum'] ?? []), 0, 3) as $value) {
            $out[] = ['enum ' . json_encode($value), (string) $value];
        }
        if (isset($schema['enum'])) {
            $out[] = ['outside the enum', 'zzfuzz-not-listed'];
        }
        $format = (string) ($schema['format'] ?? '');
        $bad = ['date-time' => ['not-a-date', '2026-13-45T25:61:00'], 'uri' => ['not a url', 'javascript:alert(1)'], 'email' => ['not-an-email', 'a@'], 'uuid' => ['not-a-uuid'], 'hex-color' => ['#zzz'], 'ip' => ['999.1.1.1']];
        foreach ($bad[$format] ?? [] as $value) {
            $out[] = ["bad {$format}", $value];
        }
    }
    if (in_array('array', $types, true)) {
        array_push($out, ['comma list', 'a,b'], ['mixed items', ['1', 'zzfuzz']], ['nested', [['zzfuzz']]]);
        if (isset($schema['items']['enum'])) {
            $out[] = ['item outside the enum', ['zzfuzz-not-listed']];
        }
    }
    if (in_array('object', $types, true)) {
        array_push($out, ['scalar for object', 'zzfuzz'], ['object', ['zzfuzz' => '1']]);
    }
    return $out;
};

/**
 * The route with its captures filled: an existing id and a missing one where a capture takes digits, a slug otherwise.
 *
 * @return array<string, string> label => route
 */
$fill = static function (string $pattern): array {
    if (preg_match_all('#\(\?P<([a-z_]+)>([^)]*)\)#', $pattern, $captures, PREG_SET_ORDER) === 0) {
        return ['' => $pattern];
    }
    $variants = [];
    foreach (['existing' => ['1', 'twentytwentyfive'], 'missing' => ['999999', 'zzfuzz-missing']] as $label => [$digits, $slug]) {
        $route = $pattern;
        foreach ($captures as [$whole, $name, $inner]) {
            $value = str_contains($inner, '\\d') ? $digits : ($name === 'stylesheet' || $name === 'theme' ? $slug : ($label === 'existing' ? 'core/paragraph' : $slug));
            $route = str_replace($whole, $value, $route);
        }
        $variants[$label] = str_replace(['\\/', '^', '$'], ['/', '', ''], $route);
    }
    return $variants;
};

$routes = 0;
$requests = 0;
$differences = [];
foreach ($index['routes'] as $pattern => $info) {
    if ($routeFilter !== null && preg_match($routeFilter, (string) $pattern) !== 1) {
        continue;
    }
    $args = [];
    foreach ((array) ($info['endpoints'] ?? []) as $endpoint) {
        if (in_array('GET', (array) ($endpoint['methods'] ?? []), true)) {
            $args += (array) ($endpoint['args'] ?? []);
        }
    }
    if (!in_array('GET', (array) ($info['methods'] ?? []), true)) {
        continue;
    }
    $routes++;
    $cases = [['plain', []], ['unknown argument', ['zzfuzz' => '1']]];
    foreach ($args as $name => $schema) {
        foreach ($mutations((array) $schema) as [$label, $value]) {
            $cases[] = ["{$name} {$label}", [$name => $value]];
        }
    }
    $cases = array_slice($cases, 0, $limit);
    foreach ($fill((string) $pattern) as $variant => $route) {
        foreach ($cases as [$label, $query]) {
            foreach ($sessions as $role => $session) {
                $requests++;
                $ref = $send($reference, $route, $query, $session);
                $eng = $send($engine, $route, $query, $session);
                $diff = $ref['status'] !== $eng['status'] ? "status {$ref['status']} vs {$eng['status']}" : minn_test_diff($ref['body'], $eng['body']);
                $diff ??= $ref['totals'] === $eng['totals'] ? null : 'totals ' . json_encode($ref['totals']) . ' vs ' . json_encode($eng['totals']);
                if ($diff === null) {
                    continue;
                }
                $found = ['route' => (string) $pattern, 'request' => $route, 'query' => $query, 'case' => trim("{$variant} {$label}"), 'role' => $role, 'reference' => $ref['status'], 'engine' => $eng['status'], 'difference' => mb_substr($diff, 0, 400)];
                $differences[] = $found;
                if ($show) {
                    printf("  DIFF %s %s [%s] %s: %s\n", $role, $route, $found['case'], http_build_query($query), $found['difference']);
                }
            }
        }
    }
}

printf("rest-fuzz: %d routes, %d requests, %d differences\n", $routes, $requests, count($differences));
$byRoute = array_count_values(array_column($differences, 'route'));
arsort($byRoute);
foreach (array_slice($byRoute, 0, 20, true) as $route => $count) {
    printf("  %4d  %s\n", $count, $route);
}
if (isset($options['write'])) {
    $record = ['method' => 'tests/tools/rest-fuzz.php: GET requests generated from the reference REST index, sent to both stacks, compared by status, body and X-WP-Total headers', 'measured' => date('Y-m-d'), 'summary' => ['routes' => $routes, 'requests' => $requests, 'differences' => count($differences)], 'differences' => $differences];
    file_put_contents("{$root}/contracts/rest-fuzz.json", json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
    echo "wrote contracts/rest-fuzz.json\n";
}
