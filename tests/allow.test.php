<?php

declare(strict_types=1);

/**
 * The Allow header, engine against the reference on the same database: the
 * methods the caller may use on the matched route, judged per caller
 * (anonymous, an author, an administrator), and no header when nothing is
 * allowed. Every case is compared live. The cases the engine still answers
 * differently are listed below with the change that settles each; the list
 * is a ratchet: a case that starts agreeing must leave it, and it never grows.
 */

require_once __DIR__ . '/lib.php';

$ENGINE = minn_test_url();
$REF = minn_test_reference_url();
$ROOT = dirname(__DIR__);

$pass = 0;
$fail = 0;
$check = static function (string $label, bool $ok, string $detail = '') use (&$pass, &$fail): void {
    if ($ok) {
        $pass++;
        echo "  ok   {$label}\n";
    } else {
        $fail++;
        echo "  FAIL {$label}" . ($detail !== '' ? "\n      {$detail}" : '') . "\n";
    }
};

$up = @file_get_contents($REF . '/?rest_route=/', false, stream_context_create(['http' => ['timeout' => 3, 'ignore_errors' => true]]));
if ($up === false) {
    echo "allow suite: reference not running at $REF; skipping\n";
    exit(0);
}

$mint = static function (int $uid) use ($ROOT): ?array {
    $mint = json_decode((string) shell_exec('wp --path=' . escapeshellarg(minn_test_site_root() . '/wp-reference') . ' eval-file ' . escapeshellarg("$ROOT/tests/tools/mint-session.php") . " $uid 2>/dev/null"), true);
    return is_array($mint) && !empty($mint['cookie']) ? $mint : null;
};
/** @return array{0: int, 1: string, 2: string} status, Allow (or "-"), body */
$fetch = static function (string $base, string $path, ?array $session, string $method = 'GET', ?string $body = null) use ($REF): array {
    $headers = [];
    if ($session !== null) {
        $alt = 'wordpress_logged_in_' . md5($REF);
        $headers[] = 'Cookie: ' . $session['cookie_name'] . '=' . $session['cookie'] . '; ' . $alt . '=' . $session['cookie'];
        $headers[] = 'X-WP-Nonce: ' . $session['nonce'];
    }
    if ($body !== null) {
        $headers[] = 'Content-Type: application/json';
    }
    $context = stream_context_create([
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
        'http' => ['ignore_errors' => true, 'timeout' => 20, 'method' => $method, 'header' => implode("\r\n", $headers), 'content' => $body ?? ''],
    ]);
    $raw = (string) @file_get_contents("$base/wp-json$path", false, $context);
    $status = 0;
    $allow = '-';
    foreach ($http_response_header ?? [] as $line) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $line, $m)) {
            $status = (int) $m[1];
        } elseif (stripos($line, 'Allow:') === 0) {
            $allow = trim(substr($line, 6));
        }
    }
    return [$status, $allow, $raw];
};

$sessions = ['anonymous' => null, 'author' => $mint(3), 'admin' => $mint(1)];
if ($sessions['author'] === null || $sessions['admin'] === null) {
    echo "allow suite: could not mint reference sessions; skipping\n";
    exit(0);
}

// The cases the engine still answers differently, each with what settles it:
//   B1   the write routes state no policy yet, so a bare route counts for GET only and the
//        engine lists GET where the reference lists the writes
//   B1   an Own policy cannot answer "missing" first, so a 404/401 carries Allow: GET where
//        the reference, refusing the caller, sends no header
//   B1s  the reference validates arguments (and existence) before it judges the caller, so a
//        few statuses differ too (400 vs 401, 404 vs 401, 500 vs 400)
//   B1b  the comment create permission depends on the body (a post the caller may comment on), which
//        no route policy states, so a signed-in caller is offered POST where the reference is not
//   B7   Access::Floor admits every editor, so a manage_options handler's refusal is not the
//        policy's, and the header carries the write methods the caller cannot really use
$divergent = [
    'anonymous GET /wp/v2/posts/10' => 'B1',     'anonymous POST /wp/v2/comments' => 'B1',     'author GET /wp/v2/users/me' => 'B1',
    'author GET /wp/v2/settings' => 'B7',     'author GET /wp/v2/blocks' => 'B1',     'admin GET /wp/v2/users/me' => 'B1',
    'admin GET /wp/v2/categories/1' => 'B1',     'admin GET /wp/v2/blocks' => 'B1',     'admin GET /wp/v2/templates' => 'B1',
    'admin GET /wp/v2/navigation' => 'B1',     'admin POST /wp/v2/categories' => 'B1s',     'author GET /wp/v2/comments' => 'B1b',
    'author POST /wp/v2/comments' => 'B1b',     'admin GET /wp/v2/comments' => 'B1b',     'admin POST /wp/v2/comments' => 'B1b',
];
$ceiling = 15;

$reads = ['/', '/wp/v2', '/wp/v2/posts', '/wp/v2/posts/1', '/wp/v2/posts/11', '/wp/v2/posts/10', '/wp/v2/posts/999999', '/wp/v2/pages', '/wp/v2/pages/2',
    '/wp/v2/users', '/wp/v2/users/me', '/wp/v2/users/1', '/wp/v2/users/2', '/wp/v2/categories', '/wp/v2/categories/1', '/wp/v2/tags', '/wp/v2/comments',
    '/wp/v2/comments/1', '/wp/v2/media', '/wp/v2/types', '/wp/v2/types/post', '/wp/v2/taxonomies', '/wp/v2/settings', '/wp/v2/search', '/wp/v2/blocks',
    '/wp/v2/templates', '/wp/v2/global-styles/themes/twentytwentyfive', '/wp/v2/posts/1/revisions', '/wp/v2/posts/1/autosaves', '/wp/v2/menus',
    '/wp/v2/menu-items', '/wp/v2/plugins', '/wp/v2/users/me/application-passwords', '/wp/v2/navigation'];
// Only writes that refuse without mutating shared state, plus a draft create the suite
// cleans up. A settings write is left out: the engine stores an invalid value (B1), so
// probing one on a shared database would dirty it for later suites.
$writes = [
    ['POST', '/wp/v2/users', '{}'],
    ['POST', '/wp/v2/categories', '{"name":""}'],
    ['POST', '/wp/v2/comments', '{}'],
    ['DELETE', '/wp/v2/posts/999999', null],
    ['POST', '/wp/v2/posts', '{"title":"zz allow probe","status":"draft"}'],
];

$agreed = 0;
$differing = [];
$created = [];
// Whatever the run creates by name goes, on the engine (which reaches the shared row) and
// on the reference, even if the comparison loop dies partway.
register_shutdown_function(static function () use ($fetch, $sessions): void {
    foreach ([$sessions['admin'], $sessions['author']] as $who) {
        foreach (['posts', 'pages'] as $type) {
            $list = json_decode((string) $fetch('https://minn.localhost', "/wp/v2/{$type}?status=draft&per_page=50&search=zz+allow+probe", $who)[2], true);
            foreach (is_array($list) ? $list : [] as $item) {
                if (is_array($item) && isset($item['id'])) {
                    $fetch('https://minn.localhost', "/wp/v2/{$type}/{$item['id']}?force=true", $sessions['admin'], 'DELETE');
                }
            }
        }
    }
});
$compare = static function (string $who, string $method, string $path, string $engineAllow, int $engineStatus, string $refAllow, int $refStatus) use (&$agreed, &$differing, $divergent, $check): void {
    $key = "$who $method $path";
    $same = $engineAllow === $refAllow && $engineStatus === $refStatus;
    if ($same && isset($divergent[$key])) {
        $check("$key agrees now: drop it from the divergent list and lower the ceiling", false, "both [$engineStatus $engineAllow]");
        return;
    }
    if ($same) {
        $agreed++;
        return;
    }
    if (!isset($divergent[$key])) {
        $check("$key matches the reference", false, "engine [$engineStatus $engineAllow] reference [$refStatus $refAllow]");
        return;
    }
    $differing[] = $key;
};
foreach ($sessions as $who => $session) {
    foreach ($reads as $path) {
        [$es, $ea] = $fetch($ENGINE, $path, $session);
        [$rs, $ra] = $fetch($REF, $path, $session);
        $compare($who, 'GET', $path, $ea, $es, $ra, $rs);
    }
    foreach ($writes as [$method, $path, $body]) {
        [$es, $ea, $eb] = $fetch($ENGINE, $path, $session, $method, $body);
        [$rs, $ra, $rb] = $fetch($REF, $path, $session, $method, $body);
        foreach ([$eb, $rb] as $made) {
            $id = (int) (json_decode($made, true)['id'] ?? 0);
            if ($id > 0) {
                $created[] = $id;
            }
        }
        $compare($who, $method, $path, $ea, $es, $ra, $rs);
    }
}
foreach ($created as $id) {
    $fetch($ENGINE, "/wp/v2/posts/$id?force=true", $sessions['admin'], 'DELETE');
}
$check("$agreed cases agree with the reference on status and Allow", $agreed >= 60, (string) $agreed);
$check('the divergent cases are the listed ones and no more (' . count($differing) . ' of a ceiling of ' . $ceiling . ')', count($differing) <= $ceiling, implode(', ', $differing));
$check('every listed divergence still diverges (the list is a ratchet)', count($differing) === count($divergent), implode(', ', array_diff(array_keys($divergent), $differing)));

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
