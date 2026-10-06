<?php
/**
 * The REST server's envelope, engine vs oracle: what a plugin can do to a
 * request through the server's filters, on the core routes Minn answers
 * itself and on a plugin's own route. The fixture plugin
 * tests/fixtures/runtime/minn-test-rest-envelope hooks the filters as
 * plugins do, chosen per request by the X-Minn-Envelope header: refuse in
 * rest_request_before_callbacks, answer in rest_dispatch_request, edit in
 * rest_request_after_callbacks, add a header in rest_post_dispatch, serve
 * the body in rest_pre_serve_request, rewrite in rest_pre_echo_response,
 * take a capability away through user_has_cap or map_meta_cap, and record
 * what each filter was handed. The suite links it into both plugin
 * folders, switches it on through the shared active_plugins, sends both
 * stacks the same requests, and takes it all back on exit.
 *
 *   php tests/rest-envelope.test.php [--show]
 */

declare(strict_types=1);

require __DIR__ . '/lib.php';

/**
 * Requests whose answer still differs, with the reason; the list may only
 * shrink. A listed request that now agrees fails with "agrees now: drop it".
 */
const DIVERGENT = [
    'record: a write' => 'the handler carries no arguments for an edit: Minn publishes one endpoint per route with its query arguments, the reference one per method group with the schema\'s (contracts/runtime.md)',
    'an invalid parameter cleared before the callbacks' => 'a context no schema names leaves no fields on the reference; Minn answers it as view',
];

$ENGINE = minn_test_url();
$REF = minn_test_reference_url();
$SITE = minn_test_site_root();
$WP = '/opt/homebrew/bin/wp --path=' . escapeshellarg($SITE . '/wp-reference');
$show = in_array('--show', array_slice($argv, 1), true);

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

// The fixture lives in both plugin folders only while the suite runs.
$fixture = dirname(__DIR__) . '/tests/fixtures/runtime/minn-test-rest-envelope';
$links = [$SITE . '/public/wp-content/plugins/minn-test-rest-envelope', $SITE . '/wp-reference/wp-content/plugins/minn-test-rest-envelope'];
foreach ($links as $link) {
    if (!is_link($link)) {
        symlink($fixture, $link);
    }
}
// What a request below creates if a stack fails to refuse it, swept by its exact title (a draft may have no slug).
$sweep = static function () use ($WP): void {
    $posts = json_decode((string) shell_exec("{$WP} post list --post_type=post --post_status=any --fields=ID,post_title --format=json 2>/dev/null"), true);
    foreach (is_array($posts) ? $posts : [] as $post) {
        if (($post['post_title'] ?? '') === 'zz envelope create') {
            shell_exec("{$WP} post delete " . (int) $post['ID'] . ' --force >/dev/null 2>&1');
        }
    }
};
$guarded = (int) trim((string) shell_exec("{$WP} post create --post_title='zz envelope guarded' --post_name=zz-envelope-guarded --post_status=publish --porcelain 2>/dev/null"));
shell_exec("{$WP} option update minn_test_envelope_guarded {$guarded} >/dev/null 2>&1");
register_shutdown_function(static function () use ($WP, $links, $guarded, $sweep): void {
    shell_exec("{$WP} plugin deactivate minn-test-rest-envelope >/dev/null 2>&1");
    shell_exec("{$WP} option delete minn_test_envelope_guarded >/dev/null 2>&1");
    if ($guarded > 0) {
        shell_exec("{$WP} post delete {$guarded} --force >/dev/null 2>&1");
    }
    $sweep();
    foreach ($links as $link) {
        if (is_link($link)) {
            unlink($link);
        }
    }
});
shell_exec("{$WP} plugin activate minn-test-rest-envelope >/dev/null 2>&1");
$check('the guarded post exists', $guarded > 0);

$mint = json_decode((string) shell_exec("{$WP} eval-file " . escapeshellarg(dirname(__DIR__) . '/tests/tools/mint-session.php') . ' 1 2>/dev/null'), true);
if (!is_array($mint) || empty($mint['cookie'])) {
    echo "SKIP: could not mint a reference session\n";
    exit(0);
}
$cookie = 'Cookie: wordpress_logged_in_' . md5($ENGINE) . '=' . rawurlencode($mint['cookie']) . '; wordpress_logged_in_' . md5($REF) . '=' . rawurlencode($mint['cookie']);

/** One request: status, the plugin's headers, and the decoded body with the host masked. */
$ask = static function (string $base, string $method, string $route, string $modes, bool $admin, ?array $body) use ($cookie, $mint): array {
    $query = '';
    if (str_contains($route, '?')) {
        [$route, $query] = explode('?', $route, 2);
        $query = '&' . $query;
    }
    $headers = ['X-Minn-Envelope: ' . $modes];
    if ($admin) {
        $headers[] = $cookie;
        $headers[] = 'X-WP-Nonce: ' . $mint['nonce'];
    }
    if ($body !== null) {
        $headers[] = 'Content-Type: application/json';
    }
    $ch = curl_init($base . '/?rest_route=' . rawurlencode($route) . $query);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_TIMEOUT => 60]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $raw = (string) curl_exec($ch);
    $size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $kept = [];
    foreach (explode("\r\n", substr($raw, 0, $size)) as $line) {
        if (preg_match('/^(x-minn-[a-z-]+|content-type):\s*(.*)$/i', $line, $m)) {
            $kept[strtolower($m[1])] = trim($m[2]);
        }
    }
    ksort($kept);
    $text = str_replace([$base, str_replace('/', '\/', $base)], '{base}', substr($raw, $size));
    return ['status' => curl_getinfo($ch, CURLINFO_RESPONSE_CODE), 'headers' => $kept, 'body' => json_decode($text, true) ?? $text];
};

/** What the comparison reads of a body: the whole of a small one, the recorded trail, or an error's code. */
$project = static function (string $how, $body) {
    return match ($how) {
        'trail' => is_array($body) ? ($body['minn_trail'] ?? 'no trail') : $body,
        'code' => is_array($body) ? [$body['code'] ?? null, $body['data']['status'] ?? null] : $body,
        'id' => is_array($body) ? [$body['id'] ?? null, $body['code'] ?? null] : $body,
        default => $body,
    };
};

$requests = [
    // label => [method, route, modes, as admin, body, projection]
    'record: an item' => ['GET', '/wp/v2/posts/1', 'record', true, null, 'trail'],
    'record: an invalid parameter' => ['GET', '/wp/v2/posts/1?context=nope', 'record', true, null, 'trail'],
    'record: refused' => ['GET', '/wp/v2/settings', 'record', false, null, 'trail'],
    'record: a missing post' => ['GET', '/wp/v2/posts/999999', 'record', true, null, 'trail'],
    'record: a write' => ['POST', "/wp/v2/posts/{$guarded}", 'record', true, ['excerpt' => 'Guarded'], 'trail'],
    'record: a plugin route' => ['GET', '/minn-test/v1/echo?word=hi', 'record', false, null, 'trail'],
    'refused before the callbacks' => ['GET', '/wp/v2/posts/1', 'block-before', true, null, 'body'],
    'refused before the callbacks, signed out' => ['GET', '/wp/v2/posts', 'block-before', false, null, 'body'],
    'a response before the callbacks is not the answer' => ['GET', '/wp/v2/posts/1', 'replace-before', true, null, 'id'],
    'an invalid parameter cleared before the callbacks' => ['GET', '/wp/v2/posts/1?context=nope', 'clear-before', true, null, 'id'],
    'answered at dispatch' => ['GET', '/wp/v2/posts/1', 'answer-dispatch', true, null, 'body'],
    'refused at dispatch' => ['GET', '/wp/v2/posts/1', 'answer-dispatch-error', true, null, 'body'],
    'dispatch never answers a caller the route refuses' => ['GET', '/wp/v2/settings', 'answer-dispatch', false, null, 'code'],
    'edited after the callbacks' => ['GET', '/wp/v2/posts/1', 'edit-after', true, null, 'body'],
    'an error recovered after the callbacks' => ['GET', '/wp/v2/posts/999999', 'edit-after', true, null, 'body'],
    'a header and status after dispatch' => ['GET', '/wp/v2/posts/1', 'post-dispatch', true, null, 'id'],
    'a header and status after dispatch, plugin route' => ['GET', '/minn-test/v1/echo', 'post-dispatch', false, null, 'body'],
    'a plugin serves the body' => ['GET', '/wp/v2/posts/1', 'serve', true, null, 'body'],
    'a plugin serves the body, plugin route' => ['GET', '/minn-test/v1/echo', 'serve', false, null, 'body'],
    'a plugin rewrites what is echoed' => ['GET', '/wp/v2/posts/1', 'echo', true, null, 'body'],
    'a plugin rewrites what is echoed, a collection' => ['GET', '/wp/v2/posts?per_page=2', 'echo', true, null, 'body'],
    'a plugin rewrites what is echoed, plugin route' => ['GET', '/minn-test/v1/echo', 'echo', false, null, 'body'],
    'a capability taken away by user_has_cap' => ['POST', '/wp/v2/posts', 'deny-cap', true, ['title' => 'zz envelope create', 'slug' => 'zz-envelope-create', 'status' => 'draft'], 'code'],
    'a deletion refused by map_meta_cap' => ['DELETE', "/wp/v2/posts/{$guarded}", 'map-meta', true, null, 'code'],
];

foreach ($requests as $label => [$method, $route, $modes, $admin, $body, $how]) {
    $reference = $ask($REF, $method, $route, $modes, $admin, $body);
    $engine = $ask($ENGINE, $method, $route, $modes, $admin, $body);
    $r = [$reference['status'], $reference['headers'], $project($how, $reference['body'])];
    $e = [$engine['status'], $engine['headers'], $project($how, $engine['body'])];
    if ($show) {
        echo "--- {$label}\n  reference: " . json_encode($r, JSON_UNESCAPED_SLASHES) . "\n  engine:    " . json_encode($e, JSON_UNESCAPED_SLASHES) . "\n";
    }
    $detail = 'reference ' . json_encode($r, JSON_UNESCAPED_SLASHES) . "\n       engine    " . json_encode($e, JSON_UNESCAPED_SLASHES);
    if (isset(DIVERGENT[$label])) {
        $check("{$label}: still listed as divergent (" . DIVERGENT[$label] . ')', $r !== $e, 'agrees now: drop it from DIVERGENT');
        continue;
    }
    $check($label, $r === $e, $detail);
}
foreach (array_diff(array_keys(DIVERGENT), array_keys($requests)) as $missing) {
    $check("DIVERGENT names a request that exists ({$missing})", false);
}
$sweep();

$alive = trim((string) shell_exec("{$WP} post get {$guarded} --field=post_status 2>/dev/null"));
$check('the guarded post is still there', $alive === 'publish', "status {$alive}");

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
