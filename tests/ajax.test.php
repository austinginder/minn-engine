<?php
/**
 * admin-ajax.php and the sign-in chain, engine vs oracle. The fixture plugin
 * tests/fixtures/runtime/minn-test-ajax registers handlers in the shapes
 * plugins use (echo and return, wp_send_json, wp_die, a nonce check, a
 * handler only an admin request registers) and refuses a sign-in through
 * authenticate and wp_authenticate_user. The suite links it into both
 * plugin folders, switches it on through the shared active_plugins, asks
 * both stacks the same questions, and takes it all back on exit.
 *
 *   php tests/ajax.test.php
 */

declare(strict_types=1);

require __DIR__ . '/lib.php';

$ENGINE = minn_test_url();
$REF = minn_test_reference_url();
$SITE = minn_test_site_root();
$WP = '/opt/homebrew/bin/wp --path=' . escapeshellarg($SITE . '/wp-reference');

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
$fixture = dirname(__DIR__) . '/tests/fixtures/runtime/minn-test-ajax';
$links = [$SITE . '/public/wp-content/plugins/minn-test-ajax', $SITE . '/wp-reference/wp-content/plugins/minn-test-ajax'];
foreach ($links as $link) {
    if (!is_link($link)) {
        symlink($fixture, $link);
    }
}
$option = static fn (string $name): string => trim((string) shell_exec("{$WP} option get {$name} --format=json 2>/dev/null"));
$forget = static fn (): string => (string) shell_exec("{$WP} option delete minn_test_ajax_failed minn_test_ajax_authenticate >/dev/null 2>&1");
// The refused sign-ins below count against this address on the engine; later suites sign in from it too.
$unthrottle = static fn (): string => (string) shell_exec("{$WP} db query \"DELETE FROM wp_options WHERE option_name LIKE 'minn_login_throttle_%'\" >/dev/null 2>&1");
register_shutdown_function(static function () use ($WP, $links, $forget, $unthrottle): void {
    shell_exec("{$WP} plugin deactivate minn-test-ajax >/dev/null 2>&1");
    $forget();
    $unthrottle();
    foreach ($links as $link) {
        if (is_link($link)) {
            unlink($link);
        }
    }
});
shell_exec("{$WP} plugin activate minn-test-ajax >/dev/null 2>&1");

/** One request: status, the headers named, and the body with the host masked. */
$ask = static function (string $base, string $method, string $query, ?string $body = null, array $headers = []): array {
    $ch = curl_init($base . '/wp-admin/admin-ajax.php' . ($query !== '' ? '?' . $query : ''));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_NOBODY => $method === 'HEAD', CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_TIMEOUT => 20, CURLOPT_HTTPHEADER => $headers,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $raw = (string) curl_exec($ch);
    $size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $kept = [];
    foreach (explode("\r\n", substr($raw, 0, $size)) as $line) {
        if (preg_match('/^(content-type|x-robots-tag|cache-control|expires|referrer-policy|x-frame-options|x-minn-test|access-control-allow-[a-z]+):\s*(.*)$/i', $line, $m)) {
            $kept[strtolower($m[1])] = trim($m[2]);
        }
    }
    ksort($kept);
    return ['status' => curl_getinfo($ch, CURLINFO_RESPONSE_CODE), 'headers' => $kept, 'body' => substr($raw, $size)];
};

echo "admin-ajax.php, signed out\n";
$cases = [
    ['GET', '', null], ['GET', 'action=', null], ['POST', '', ''], ['GET', 'action=nope', null], ['POST', '', 'action=nope'],
    ['GET', 'action=minn_echo&word=hi', null], ['POST', '', 'action=minn_echo&word=hi%20there'], ['GET', 'action=minn_private', null],
    ['POST', '', 'action=minn_json&word=w'], ['GET', 'action=minn_json', null], ['POST', '', 'action=minn_json_error'], ['GET', 'action=minn_json_plain', null],
    ['GET', 'action=minn_die', null], ['GET', 'action=minn_die_status', null], ['GET', 'action=minn_die_empty', null],
    ['GET', 'action=minn_die_error', null], ['GET', 'action=minn_die_int', null],
    ['GET', 'action=minn_nonce&nonce=bad', null], ['GET', 'action=minn_nonce', null], ['GET', 'action=minn_nonce_soft&nonce=bad', null],
    ['GET', 'action=minn_state', null], ['POST', 'action=minn_state', 'action=other'],
    ['GET', 'action=minn_headers', null], ['GET', 'action=minn_exit', null],
    ['GET', 'action=minn_admin_only', null], ['GET', 'action=minn_from_admin_init', null],
    ['HEAD', 'action=minn_echo', null], ['GET', 'action[]=minn_echo', null], ['GET', 'action=rest-nonce', null],
    ['POST', '', 'action=heartbeat&screen_id=front&data[a]=1'], ['POST', '', 'action=heartbeat'],
];
foreach ($cases as [$method, $query, $body]) {
    $e = $ask($ENGINE, $method, $query, $body);
    $r = $ask($REF, $method, $query, $body);
    // The engine's hardening sends nosniff on every response; the reference sends it from the action check on.
    unset($e['headers']['x-content-type-options'], $r['headers']['x-content-type-options']);
    $label = "{$method} ?{$query}" . ($body !== null ? " body={$body}" : '');
    $mask = static fn (string $s): string => preg_replace('/"server_time":\d+/', '"server_time":0', $s);
    $same = $e['status'] === $r['status'] && $e['headers'] === $r['headers'] && $mask($e['body']) === $mask($r['body']);
    $check($label, $same, json_encode(['engine' => $e, 'reference' => $r], JSON_UNESCAPED_SLASHES));
}

echo "admin-ajax.php, signed in\n";
$mint = json_decode((string) shell_exec("{$WP} eval-file " . escapeshellarg(dirname(__DIR__) . '/tests/tools/mint-session.php') . ' 2 2>/dev/null'), true);
if (!is_array($mint) || empty($mint['cookie'])) {
    $check('a session mints on the reference', false);
} else {
    $cookie = 'Cookie: wordpress_logged_in_' . md5($ENGINE) . '=' . rawurlencode($mint['cookie']) . '; wordpress_logged_in_' . md5($REF) . '=' . rawurlencode($mint['cookie']) . '; ' . $mint['cookie_name'] . '=' . rawurlencode($mint['cookie']);
    $nonce = trim($ask($REF, 'GET', 'action=minn_mint', null, [$cookie])['body']);
    $nonce = substr($nonce, 0, -1);
    $check('the reference mints a nonce for the session', preg_match('/^[0-9a-f]{10}$/', $nonce) === 1, $nonce);
    foreach (['action=minn_echo', 'action=minn_private', 'action=minn_state', "action=minn_nonce&nonce={$nonce}", "action=minn_nonce&_ajax_nonce={$nonce}", 'action=minn_nonce&nonce=bad', 'action=minn_json', 'action=rest-nonce'] as $query) {
        $e = $ask($ENGINE, 'GET', $query, null, [$cookie]);
        $r = $ask($REF, 'GET', $query, null, [$cookie]);
        $check("GET ?{$query}", $e['status'] === $r['status'] && $e['body'] === $r['body'], "engine {$e['status']} {$e['body']} | reference {$r['status']} {$r['body']}");
    }
}

echo "admin-ajax.php, origins (engine)\n";
$own = $ask($ENGINE, 'GET', 'action=minn_echo', null, ['Origin: ' . $ENGINE]);
$check('the site\'s own origin is allowed with credentials', ($own['headers']['access-control-allow-origin'] ?? '') === $ENGINE && ($own['headers']['access-control-allow-credentials'] ?? '') === 'true', json_encode($own['headers']));
$http = $ask($ENGINE, 'GET', 'action=minn_echo', null, ['Origin: ' . preg_replace('#^https://#', 'http://', $ENGINE)]);
$check('so is its plain-http twin', ($http['headers']['access-control-allow-origin'] ?? '') === preg_replace('#^https://#', 'http://', $ENGINE));
foreach (['https://evil.example', $ENGINE . '/', $ENGINE . ':443', 'null'] as $origin) {
    $other = $ask($ENGINE, 'GET', 'action=minn_echo', null, ['Origin: ' . $origin]);
    $check("no cross-origin headers for {$origin}", !isset($other['headers']['access-control-allow-origin']));
}
$preflight = $ask($ENGINE, 'OPTIONS', 'action=minn_echo', null, ['Origin: ' . $ENGINE]);
$check('a preflight from the site is answered 200 and empty', $preflight['status'] === 200 && $preflight['body'] === '' && isset($preflight['headers']['access-control-allow-origin']));
$foreign = $ask($ENGINE, 'OPTIONS', 'action=minn_echo', null, ['Origin: https://evil.example']);
$check('a preflight from anywhere else is refused 403 and empty', $foreign['status'] === 403 && $foreign['body'] === '');

echo "sign-in through the authenticate chain\n";
$signIn = static function (string $base, string $body): array {
    $ch = curl_init($base . '/wp-login.php');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_POSTFIELDS => $body,
        CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_HTTPHEADER => ['Cookie: wordpress_test_cookie=WP%20Cookie%20check'],
    ]);
    $raw = (string) curl_exec($ch);
    preg_match('#<div id="login_error"[^>]*>(.*?)</div>#s', $raw, $reference);
    preg_match('#<div class="err">(.*?)</div>#s', $raw, $engine);
    $message = trim((string) preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($reference[1] ?? $engine[1] ?? ''), ENT_QUOTES)));
    return ['status' => curl_getinfo($ch, CURLINFO_RESPONSE_CODE), 'signed' => preg_match('/^set-cookie: wordpress_logged_in_/mi', $raw) === 1, 'message' => $message];
};
$attempts = [
    // label, form, the plugin's own words to expect on both stacks (null: the engine's single sentence)
    ['a plugin refuses through authenticate', 'log=editor&pwd=minn-editor-pass-1&minn_refuse=1', 'Error: A plugin refused this sign-in.'],
    ['a plugin refuses through wp_authenticate_user', 'log=editor&pwd=minn-editor-pass-1&minn_refuse_user=1', 'Error: This account is paused.'],
    ['a wrong password', 'log=editor&pwd=wrong', null],
    ['an unknown username', 'log=nobody-here&pwd=x', null],
    ['an empty password', 'log=editor&pwd=', null],
    ['the right password', 'log=editor&pwd=minn-editor-pass-1', ''],
    ['an email address and a padded password', 'log=editor%40minn-engine.localhost&pwd=%20minn-editor-pass-1%20', ''],
];
foreach ($attempts as [$label, $form, $words]) {
    $forget();
    $r = $signIn($REF, $form);
    $heardRef = [$option('minn_test_ajax_failed'), $option('minn_test_ajax_authenticate')];
    $forget();
    $e = $signIn($ENGINE, $form);
    $heardEng = [$option('minn_test_ajax_failed'), $option('minn_test_ajax_authenticate')];
    $check("{$label}: signed in on both or neither", $e['signed'] === $r['signed'] && ($e['status'] === 302) === ($r['status'] === 302), "engine {$e['status']} | reference {$r['status']}");
    $check("{$label}: the plugin heard the same (wp_login_failed, wp_authenticate)", $heardEng === $heardRef, json_encode(['engine' => $heardEng, 'reference' => $heardRef]));
    if ($words === null) {
        $check("{$label}: the engine says its one sentence", $e['message'] === 'Error: The username or password you entered is incorrect.', $e['message']);
    } else {
        $check("{$label}: the words shown", $e['message'] === $words && $r['message'] === $words, "engine '{$e['message']}' | reference '{$r['message']}'");
    }
}

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
