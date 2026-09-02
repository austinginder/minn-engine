<?php
/**
 * REST gate suite: what plugin code may decide about REST requests before
 * and beside the engine's own routes, proven against the reference with the
 * same fixture plugin (tests/fixtures/runtime/minn-test-gate) active on the
 * shared database. A plugin route under wp/v2 answers; a
 * rest_authentication_errors refusal and a rest_pre_dispatch answer apply to
 * engine routes; a route a rest_endpoints filter removed is no route; and an
 * in-process rest_do_request runs as the outer request's user.
 *
 *   php tests/rest-gate.test.php
 */

declare(strict_types=1);

require __DIR__ . '/lib.php';
require __DIR__ . '/tools/engine-runtime.php';

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

$ROOT = dirname(__DIR__);
$ENGINE = rtrim((string) $site->option('home'), '/');
$REF = minn_test_reference_url();
$siteRoot = minn_test_site_root();
$fixture = $ROOT . '/tests/fixtures/runtime/minn-test-gate';
foreach ([$siteRoot . '/public/wp-content/plugins/minn-test-gate', $siteRoot . '/wp-reference/wp-content/plugins/minn-test-gate'] as $link) {
    if (!is_link($link) && !is_dir($link)) {
        @symlink($fixture, $link);
    }
}
if (@file_get_contents($REF . '/?rest_route=/', false, stream_context_create(['http' => ['timeout' => 3, 'ignore_errors' => true]])) === false) {
    echo "SKIP: reference not running at {$REF}\n";
    exit(0);
}

$plugin = 'minn-test-gate/minn-test-gate.php';
$before = get_option('active_plugins');
$before = is_array($before) ? $before : [];
register_shutdown_function(static function () use ($before): void {
    update_option('active_plugins', $before);
});
update_option('active_plugins', array_values(array_unique(array_merge($before, [$plugin]))));

$mint = json_decode((string) shell_exec('wp --path=' . escapeshellarg($siteRoot . '/wp-reference') . ' eval-file ' . escapeshellarg($ROOT . '/tests/tools/mint-session.php') . ' 1 2>/dev/null'), true);
if (!$mint || empty($mint['cookie'])) {
    echo "SKIP: could not mint a reference session\n";
    exit(0);
}
$fetch = static function (string $base, string $route, string $query = '', bool $signedIn = false) use ($mint, $REF): array {
    $headers = [];
    if ($signedIn) {
        $headers[] = 'Cookie: ' . $mint['cookie_name'] . '=' . $mint['cookie'] . '; wordpress_logged_in_' . md5($REF) . '=' . $mint['cookie'];
        $headers[] = 'X-WP-Nonce: ' . $mint['nonce'];
    }
    $url = $base . '/?rest_route=' . rawurlencode($route) . ($query === '' ? '' : '&' . $query);
    $ctx = stream_context_create([
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
        'http' => ['ignore_errors' => true, 'timeout' => 15, 'header' => implode("\r\n", $headers)],
    ]);
    $body = (string) @file_get_contents($url, false, $ctx);
    $status = 0;
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $h, $m)) {
            $status = (int) $m[1];
        }
    }
    return [$status, json_decode($body, true)];
};
$same = static function (string $label, string $route, string $query = '', bool $signedIn = false, ?callable $expect = null) use ($fetch, $check, $ENGINE, $REF): void {
    [$es, $eb] = $fetch($ENGINE, $route, $query, $signedIn);
    [$rs, $rb] = $fetch($REF, $route, $query, $signedIn);
    // Status and error code are compared across stacks; bodies carry each stack's own host in their links.
    $engine = [$es, is_array($eb) ? ($eb['code'] ?? null) : $eb];
    $reference = [$rs, is_array($rb) ? ($rb['code'] ?? null) : $rb];
    $ok = $engine === $reference && ($expect === null || $expect($es, $eb));
    $check($label, $ok, json_encode(['engine' => $engine, 'reference' => $reference]));
};

$same('a plugin route under wp/v2 answers on both stacks', '/wp/v2/gate-probe', '', false, static fn (int $s, $b): bool => $s === 200 && ($b['probe'] ?? '') === 'wp/v2 route from a plugin');
$same('an unknown wp/v2 base is still no route', '/wp/v2/no-such-base', '', false, static fn (int $s, $b): bool => $s === 404 && ($b['code'] ?? '') === 'rest_no_route');
$same('rest_authentication_errors refuses an engine route', '/wp/v2/posts', 'minn_gate_refuse=1', false, static fn (int $s, $b): bool => $s === 401 && ($b['code'] ?? '') === 'minn_gate_refused');
$same('rest_authentication_errors refuses the index too', '/', 'minn_gate_refuse=1', false, static fn (int $s, $b): bool => $s === 401);
$same('rest_pre_dispatch answers an engine route', '/wp/v2/posts', 'minn_gate_pre=1', false, static fn (int $s, $b): bool => $s === 200 && ($b['pre'] ?? false) === true && ($b['route'] ?? '') === '/wp/v2/posts');
$same('a route removed through rest_endpoints is no route', '/wp/v2/tags', '', false, static fn (int $s, $b): bool => $s === 404 && ($b['code'] ?? '') === 'rest_no_route');
$same('its sibling routes still answer', '/wp/v2/categories', '', false, static fn (int $s, $b): bool => $s === 200);
$same('an in-process rest_do_request runs as the signed-in user', '/wp/v2/gate-probe/me', '', true, static fn (int $s, $b): bool => $s === 200 && ($b['status'] ?? 0) === 200 && ($b['id'] ?? 0) === 1);
$same('and as nobody when nobody is signed in', '/wp/v2/gate-probe/me', '', false, static fn (int $s, $b): bool => $s === 200 && ($b['status'] ?? 0) === 401);

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
