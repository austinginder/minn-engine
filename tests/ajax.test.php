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
    // The heartbeat a signed-in page beats: its nonce checked (or refreshed), what plugins hear and add, the tick.
    $beat = substr(trim($ask($REF, 'GET', 'action=minn_mint_heartbeat', null, [$cookie])['body']), 0, -1);
    $check('the reference mints a heartbeat nonce for the session', preg_match('/^[0-9a-f]{10}$/', $beat) === 1, $beat);
    $form = ['Content-Type: application/x-www-form-urlencoded', $cookie];
    $mask = static fn (string $s): string => preg_replace('/"server_time":\d+/', '"server_time":0', $s);
    foreach (['action=heartbeat', "action=heartbeat&_nonce={$beat}&screen_id=front&data[minn_test]=1", "action=heartbeat&_nonce={$beat}&screen_id=Edit-Post!", "action=heartbeat&_nonce={$beat}", 'action=heartbeat&_nonce=bad&screen_id=front&data[minn_test]=1', 'action=heartbeat&_nonce=bad', "action=heartbeat&_nonce={$beat}&data[wp-check-locked-posts][]=post-1"] as $body) {
        $e = $ask($ENGINE, 'POST', '', $body, $form);
        $r = $ask($REF, 'POST', '', $body, $form);
        $same = $e['status'] === $r['status'] && ($e['headers']['x-minn-test'] ?? null) === ($r['headers']['x-minn-test'] ?? null) && $mask($e['body']) === $mask($r['body']);
        $check('POST ' . str_replace($beat, '{nonce}', $body), $same, json_encode(['engine' => [$e['status'], $e['headers']['x-minn-test'] ?? null, $e['body']], 'reference' => [$r['status'], $r['headers']['x-minn-test'] ?? null, $r['body']]], JSON_UNESCAPED_SLASHES));
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

echo "wp-admin pages a plugin answers\n";
// A page builder opens its editor from a wp-admin page whose action it answers itself
// (post.php?action=elementor). Both stacks ask for the admin cookie, run admin_init, then the action.
$door = static function (string $base, string $query, array $headers) use ($ENGINE, $REF): array {
    $ch = curl_init($base . '/wp-admin/post.php?' . $query);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_TIMEOUT => 30, CURLOPT_HTTPHEADER => $headers]);
    $raw = (string) curl_exec($ch);
    $size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $head = substr($raw, 0, $size);
    $masked = static fn (string $v): string => str_replace([$ENGINE, $REF, rawurlencode($ENGINE), rawurlencode($REF)], '{site}', $v);
    return [
        'status' => curl_getinfo($ch, CURLINFO_RESPONSE_CODE),
        'location' => preg_match('/^location:\s*(.*)$/mi', $head, $m) === 1 ? $masked(trim($m[1])) : '',
        'door' => preg_match('/^x-minn-test:\s*(.*)$/mi', $head, $m) === 1 ? trim($m[1]) : '',
        'body' => $masked(trim(substr($raw, $size))),
    ];
};
$doorMint = json_decode((string) shell_exec("{$WP} eval-file " . escapeshellarg(dirname(__DIR__) . '/tests/tools/mint-session.php') . ' 2 2>/dev/null'), true);
if (!is_array($doorMint) || empty($doorMint['admin_cookie'])) {
    $check('an admin session mints on the reference', false);
} else {
    $signIn = 'Cookie: wordpress_logged_in_' . md5($ENGINE) . '=' . rawurlencode($doorMint['cookie']) . '; ' . $doorMint['cookie_name'] . '=' . rawurlencode($doorMint['cookie']);
    $admin = $signIn . '; ' . $doorMint['admin_cookie_name'] . '=' . rawurlencode($doorMint['admin_cookie']);
    foreach (['signed out' => [], 'the sign-in cookie alone' => [$signIn], 'signed in, with the admin cookie' => [$admin]] as $label => $headers) {
        $e = $door($ENGINE, 'action=minn_door', $headers);
        $r = $door($REF, 'action=minn_door', $headers);
        $check("post.php?action=minn_door, {$label}: the same answer", $e === $r, json_encode(['engine' => $e, 'reference' => $r], JSON_UNESCAPED_SLASHES));
    }
    $e = $door($ENGINE, 'action=minn_door', [$admin]);
    $check('the handler ran as an admin request, after admin_init, for the signed-in user', $e['body'] === 'door admin post.php user 2 admin_init 1' && $e['door'] === 'door', $e['body']);
    // Where no plugin answers, the engine's admin is Minn Admin; the reference would draw its own screen.
    $e = $door($ENGINE, 'action=minn_nobody', [$admin]);
    $check('an action no plugin answers lands in Minn Admin', $e['status'] === 302 && $e['location'] === '{site}/minn-admin/', json_encode($e));

    // The app is told which of these pages the engine opens, while the Minn Legacy Pack is active
    // (a stand-in here): it keeps links to them and drops every other wp-admin link.
    $adminActions = static function () use ($ENGINE, $signIn): ?array {
        $ch = curl_init($ENGINE . '/minn-admin/');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_TIMEOUT => 30, CURLOPT_HTTPHEADER => [$signIn]]);
        $html = (string) curl_exec($ch);
        $boot = preg_match('/window\.MINN = (\{.*\});\s*$/m', $html, $m) === 1 ? json_decode($m[1], true) : null;
        return is_array($boot) && is_array($boot['adminActions'] ?? null) ? $boot['adminActions'] : null;
    };
    $packLinks = [$SITE . '/public/wp-content/plugins/minn-legacy-pack', $SITE . '/wp-reference/wp-content/plugins/minn-legacy-pack'];
    foreach ($packLinks as $link) {
        if (!file_exists($link)) {
            symlink(dirname(__DIR__) . '/tests/fixtures/runtime/minn-legacy-pack', $link);
        }
    }
    register_shutdown_function(static function () use ($WP, $packLinks): void {
        shell_exec("{$WP} plugin deactivate minn-legacy-pack >/dev/null 2>&1");
        foreach ($packLinks as $link) {
            if (is_link($link)) {
                unlink($link);
            }
        }
    });
    $off = $adminActions();
    shell_exec("{$WP} plugin activate minn-legacy-pack >/dev/null 2>&1");
    $on = $adminActions();
    shell_exec("{$WP} plugin deactivate minn-legacy-pack >/dev/null 2>&1");
    $check('without the pack the app is told of no wp-admin page to open', $off === [], json_encode($off));
    $check('with the pack it is told the actions plugins answer', is_array($on) && in_array('minn_door', $on, true), json_encode($on));

    // An editor that needs the pack (the fixture declares one through minn_legacy_pack_editors): while the pack
    // is not active, its page sends the visitor to Minn Admin's offer to install it, with the way back; the app
    // is told the pack's state and the editors that want it. Once the pack is active the editor opens.
    $boot = static function (string $cookie) use ($ENGINE): ?array {
        $ch = curl_init($ENGINE . '/minn-admin/');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_TIMEOUT => 30, CURLOPT_HTTPHEADER => [$cookie]]);
        $html = (string) curl_exec($ch);
        return preg_match('/window\.MINN = (\{.*\});\s*$/m', $html, $m) === 1 ? json_decode($m[1], true) : null;
    };
    $packDoor = static fn (): array => $door($ENGINE, 'action=minn_door_pack', [$admin]);
    $e = $packDoor();
    $offer = '{site}/minn-admin/?' . http_build_query(['legacy-pack' => 'minn_door_pack', 'return' => '{site}/wp-admin/post.php?action=minn_door_pack']);
    $check('an editor that needs the pack, while it is not active, lands on the offer to install it', $e['status'] === 302 && $e['location'] === str_replace(rawurlencode('{site}'), '{site}', $offer), json_encode($e));
    $state = $boot($signIn)['legacyPack'] ?? null;
    $check('the app is told the pack is not active and which editors want it', is_array($state) && $state['active'] === false && in_array(['plugin' => 'minn-test-ajax/minn-test-ajax.php', 'theme' => '', 'name' => 'Test Door', 'action' => 'minn_door_pack', 'query' => ''], $state['editors'], true) && in_array(['plugin' => '', 'theme' => 'twentytwentyfive', 'name' => 'Test Front', 'action' => '', 'query' => 'minn_front_pack=on'], $state['editors'], true), json_encode($state));
    // An editor the theme opens by a query on the page it edits: someone who may edit lands on the offer, with the
    // way back; a visitor, or another value of the query, gets the page.
    $frontDoor = static function (string $query, array $headers) use ($ENGINE): array {
        $ch = curl_init($ENGINE . '/?' . $query);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_TIMEOUT => 30, CURLOPT_HTTPHEADER => $headers]);
        $raw = (string) curl_exec($ch);
        $head = substr($raw, 0, curl_getinfo($ch, CURLINFO_HEADER_SIZE));
        $location = preg_match('/^location:\s*(.*)$/mi', $head, $m) === 1 ? str_replace([$ENGINE, rawurlencode($ENGINE)], '{site}', trim($m[1])) : '';
        return ['status' => curl_getinfo($ch, CURLINFO_RESPONSE_CODE), 'location' => $location];
    };
    $frontOffer = str_replace(rawurlencode('{site}'), '{site}', '{site}/minn-admin/?' . http_build_query(['legacy-pack' => 'minn_front_pack=on', 'return' => '{site}/?minn_front_pack=on']));
    $f = $frontDoor('minn_front_pack=on', [$signIn]);
    $check('an editor opened by a query on the page, while the pack is not active, lands on the offer', $f['status'] === 302 && $f['location'] === $frontOffer, json_encode($f));
    $f = [$frontDoor('minn_front_pack=on', []), $frontDoor('minn_front_pack=off', [$signIn])];
    $check('a visitor, or another value of the query, gets the page', $f[0]['status'] === 200 && $f[1]['status'] === 200, json_encode($f));
    $adminMint = json_decode((string) shell_exec("{$WP} eval-file " . escapeshellarg(dirname(__DIR__) . '/tests/tools/mint-session.php') . ' 1 2>/dev/null'), true);
    if (is_array($adminMint) && !empty($adminMint['nonce'])) {
        // The stand-in is installed (its folder is linked in), so the offer only activates it: nothing is downloaded.
        $ch = curl_init($ENGINE . '/wp-json/minn-admin/v1/legacy-pack/install');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => '{}', CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_TIMEOUT => 60, CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-WP-Nonce: ' . $adminMint['nonce'], 'Cookie: ' . $adminMint['cookie_name'] . '=' . rawurlencode($adminMint['cookie'])]]);
        $answer = json_decode((string) curl_exec($ch), true);
        $check('installing the pack where it is already on disk activates it, downloading nothing', curl_getinfo($ch, CURLINFO_RESPONSE_CODE) === 200 && is_array($answer) && array_key_exists('installed', $answer) && $answer['installed'] === null && ($answer['legacyPack']['active'] ?? null) === true, json_encode($answer));
        $e = $packDoor();
        $check('with the pack active the editor opens', $e['status'] === 200 && $e['body'] === 'door-pack opened', json_encode($e));
        $f = $frontDoor('minn_front_pack=on', [$signIn]);
        $check('with the pack active the page an editor opens on is served', $f['status'] === 200, json_encode($f));
        $ch = curl_init($ENGINE . '/wp-json/minn-admin/v1/legacy-pack/install');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => '{}', CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_TIMEOUT => 60, CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-WP-Nonce: ' . $doorMint['nonce'], 'Cookie: ' . $doorMint['cookie_name'] . '=' . rawurlencode($doorMint['cookie'])]]);
        curl_exec($ch);
        $check('only someone who may install and activate plugins can install it', curl_getinfo($ch, CURLINFO_RESPONSE_CODE) === 403, (string) curl_getinfo($ch, CURLINFO_RESPONSE_CODE));
        shell_exec("{$WP} plugin deactivate minn-legacy-pack >/dev/null 2>&1");
    } else {
        $check('an administrator session mints on the reference', false);
    }

    // The media modal's upload: async-upload.php boots as an ajax request on both stacks, asks for the
    // admin cookie, fires the page's action and checks the media-form nonce before anything is stored.
    // Past the nonce, WordPress's own upload answers on the reference and the pack's on the engine.
    $upload = static function (string $base, array $fields, array $headers) use ($ENGINE, $REF): array {
        $ch = curl_init($base . '/wp-admin/async-upload.php');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_TIMEOUT => 30, CURLOPT_HTTPHEADER => $headers, CURLOPT_POSTFIELDS => $fields]);
        $raw = (string) curl_exec($ch);
        $size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $head = substr($raw, 0, $size);
        $header = static fn (string $name): string => preg_match('/^' . $name . ':\s*(.*)$/mi', $head, $m) === 1 ? trim($m[1]) : '';
        return [
            'status' => curl_getinfo($ch, CURLINFO_RESPONSE_CODE),
            'location' => str_replace([$ENGINE, $REF, rawurlencode($ENGINE), rawurlencode($REF)], '{site}', $header('location')),
            'type' => $header('content-type'),
            'cache' => $header('cache-control'),
            'upload' => $header('x-minn-test'),
            'body' => trim(substr($raw, $size)),
        ];
    };
    shell_exec("{$WP} plugin activate minn-legacy-pack >/dev/null 2>&1");
    $media = substr(trim($ask($REF, 'GET', 'action=minn_mint_upload', null, [$signIn])['body']), 0, -1);
    $check('the reference mints an upload nonce for the session', preg_match('/^[0-9a-f]{10}$/', $media) === 1, $media);
    $refused = ['action' => 'upload-attachment', '_wpnonce' => 'nope', 'name' => 'probe.png'];
    foreach (['signed out' => [], 'the sign-in cookie alone' => [$signIn], 'signed in, with a nonce that is not the upload\'s' => [$admin]] as $label => $headers) {
        $e = $upload($ENGINE, $refused, $headers);
        $r = $upload($REF, $refused, $headers);
        $check("async-upload.php, {$label}: the same answer", $e === $r, json_encode(['engine' => $e, 'reference' => $r], JSON_UNESCAPED_SLASHES));
    }
    $e = $upload($ENGINE, $refused, [$admin]);
    $check('the upload boots as an ajax request, after admin_init, for the signed-in user', $e['upload'] === 'upload ajax admin async-upload.php user 2 admin_init 1', $e['upload']);
    $allowed = ['action' => 'upload-attachment', '_wpnonce' => $media, 'name' => 'probe.png'];
    $e = $upload($ENGINE, $allowed, [$admin]);
    $check('with its nonce the upload reaches the handler the pack registers', $e['status'] === 200 && $e['body'] === 'stand-in upload', json_encode($e));
    shell_exec("{$WP} plugin deactivate minn-legacy-pack >/dev/null 2>&1");
    $e = $upload($ENGINE, $allowed, [$admin]);
    $check('without the pack nothing answers the upload: "0", as admin-ajax.php answers an action nobody handles', $e['status'] === 400 && $e['body'] === '0', json_encode($e));
}

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
