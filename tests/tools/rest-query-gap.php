<?php

/**
 * Find REST collection query args the oracle honours and the engine ignores.
 *
 * Method (oracle capture, never WordPress source):
 *   1. OPTIONS each collection on the running oracle; that JSON lists GET args.
 *   2. GET the collection on both stacks, then GET again with each arg set.
 *   3. An arg is IGNORED when the oracle's X-WP-Total moves and the engine's
 *      does not — the Mine filter on media was this shape (`author=3`).
 *
 * Static unread (controller never calls query('arg')) is printed as a todo
 * list. Live ignored is the finding.
 *
 *   php tests/tools/rest-query-gap.php
 *   php tests/tools/rest-query-gap.php --engine=https://dogfood.localhost --ref=http://127.0.0.1:8124
 */

declare(strict_types=1);

$ENGINE = rtrim((string) (getenv('MINN_TEST_URL') ?: 'https://minn-engine.localhost'), '/');
$REF = rtrim((string) (getenv('MINN_REF_URL') ?: 'http://127.0.0.1:8123'), '/');
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--engine=')) {
        $ENGINE = rtrim(substr($arg, 9), '/');
    }
    if (str_starts_with($arg, '--ref=')) {
        $REF = rtrim(substr($arg, 6), '/');
    }
}

$ROUTES = [
    '/wp/v2/posts' => 'PostsController.php',
    '/wp/v2/pages' => 'PostsController.php',
    '/wp/v2/media' => 'MediaController.php',
    '/wp/v2/comments' => 'CommentsController.php',
    '/wp/v2/categories' => 'TermsController.php',
    '/wp/v2/tags' => 'TermsController.php',
    '/wp/v2/users' => 'UsersController.php',
];

$SKIP = ['context', 'page', 'per_page', 'offset', 'order', 'orderby', '_fields', '_embed', 'search_columns', 'search_semantics'];

$ROOT = dirname(__DIR__, 2);
$CTRL = $ROOT . '/public/minn/src/Minn/Rest';

$fetch = static function (string $url, string $method = 'GET'): array {
    $ctx = stream_context_create([
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
        'http' => ['ignore_errors' => true, 'timeout' => 20, 'method' => $method],
    ]);
    $body = @file_get_contents($url, false, $ctx);
    $headers = ['status' => 0];
    foreach ($http_response_header ?? [] as $line) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $line, $m)) {
            $headers['status'] = (int) $m[1];
        } elseif (str_contains($line, ':')) {
            [$k, $v] = explode(':', $line, 2);
            $headers[strtolower(trim($k))] = trim($v);
        }
    }
    return [$headers, (string) $body];
};

$listUrl = static function (string $origin, string $route, string $extra = '') use ($ENGINE): string {
    $query = 'per_page=1' . ($extra !== '' ? '&' . $extra : '');
    if (str_contains($origin, '127.0.0.1')) {
        return $origin . '/?rest_route=' . rawurlencode($route) . '&' . $query;
    }
    return $origin . '/wp-json' . $route . '?' . $query;
};

$totalOf = static function (array $headers): ?int {
    if (!isset($headers['x-wp-total'])) {
        return null;
    }
    return (int) $headers['x-wp-total'];
};

$probeValue = static function (string $name, array $schema, string $route): ?string {
    $enum = $schema['enum'] ?? null;
    if (is_array($enum) && $enum !== []) {
        return (string) $enum[0];
    }
    if (($schema['format'] ?? '') === 'date-time') {
        return str_contains($name, 'before') ? '2099-01-01T00:00:00' : '2000-01-01T00:00:00';
    }
    return match ($name) {
        'author', 'author_exclude' => '1',
        'parent', 'parent_exclude' => '0',
        'include', 'exclude', 'post' => '1',
        'search' => 'the',
        'slug' => $route === '/wp/v2/pages' ? 'sample-page' : 'hello-world',
        'status' => str_contains($route, 'media') ? 'inherit' : 'publish',
        'media_type' => 'image',
        'mime_type' => 'image/jpeg',
        'hide_empty' => '0',
        'roles' => 'author',
        'who' => 'authors',
        default => is_string($schema['type'] ?? null) && in_array($schema['type'], ['integer', 'array'], true) ? '1' : null,
    };
};

$engineReads = static function (string $file) use ($CTRL): array {
    $src = (string) file_get_contents($CTRL . '/' . $file);
    preg_match_all("/query\\('([a-z_]+)'/", $src, $m);
    return array_values(array_unique($m[1] ?? []));
};

[$probe] = $fetch($REF . '/?rest_route=' . rawurlencode('/wp/v2/posts'));
if (($probe['status'] ?? 0) !== 200) {
    fwrite(STDERR, "rest-query-gap: reference not running at {$REF}\n");
    exit(0);
}

echo "rest-query-gap: oracle {$REF} vs engine {$ENGINE}\n";
echo "An IGNORED arg is one that moves the oracle total and leaves the engine total still.\n\n";

$ignored = 0;
$unreadTotal = 0;
foreach ($ROUTES as $route => $file) {
    [$optHeaders, $optBody] = $fetch(rtrim($REF, '/') . '/wp-json' . $route, 'OPTIONS');
    $opt = json_decode($optBody, true);
    $args = [];
    foreach ((array) ($opt['endpoints'] ?? []) as $endpoint) {
        if (in_array('GET', (array) ($endpoint['methods'] ?? []), true)) {
            $args = (array) ($endpoint['args'] ?? []);
            break;
        }
    }
    if ($args === []) {
        echo "{$route}: no OPTIONS GET args (status " . ($optHeaders['status'] ?? '?') . ")\n\n";
        continue;
    }
    $read = $engineReads($file);
    $declared = array_keys($args);
    $unread = array_values(array_diff($declared, $read, $SKIP));
    $unreadTotal += count($unread);

    [$baseRefH] = $fetch($listUrl($REF, $route));
    [$baseEngH] = $fetch($listUrl($ENGINE, $route));
    $baseRef = $totalOf($baseRefH);
    $baseEng = $totalOf($baseEngH);

    echo "{$route}  (oracle {$baseRef}, engine {$baseEng})\n";
    if ($unread !== []) {
        echo '  unread by ' . $file . ': ' . implode(', ', $unread) . "\n";
    }
    $hits = [];
    foreach ($args as $name => $schema) {
        if (in_array($name, $SKIP, true)) {
            continue;
        }
        $value = $probeValue($name, is_array($schema) ? $schema : [], $route);
        if ($value === null) {
            continue;
        }
        $extra = rawurlencode($name) . '=' . rawurlencode($value);
        [$refH] = $fetch($listUrl($REF, $route, $extra));
        [$engH] = $fetch($listUrl($ENGINE, $route, $extra));
        $refN = $totalOf($refH);
        $engN = $totalOf($engH);
        if ($refN === null || $baseRef === null || $refN === $baseRef) {
            continue;
        }
        if ($engN === $baseEng) {
            $hits[] = "{$name}={$value}  oracle {$baseRef}→{$refN}  engine {$baseEng}→" . ($engN ?? '∅');
            $ignored++;
        }
    }
    if ($hits === []) {
        echo "  live: no ignored filters (oracle-moving args also moved the engine)\n";
    } else {
        echo "  LIVE IGNORED:\n";
        foreach ($hits as $hit) {
            echo "    {$hit}\n";
        }
    }
    echo "\n";
}

echo "{$unreadTotal} unread declared args, {$ignored} live ignored.\n";
echo $ignored === 0 ? "no Mine-shaped gaps on these collections\n" : "engine is swallowing filters the oracle applies\n";
exit($ignored === 0 ? 0 : 1);
