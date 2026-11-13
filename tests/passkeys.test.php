<?php
/**
 * Passkeys suite (Minn on its own: WordPress has no passkeys, so there is no
 * oracle). Drives the minn-test-passkeys fixture extension's REST routes
 * over Seams::accounts() with the software authenticator
 * (tests/fixtures/passkeys/soft-authenticator.php), for what the browser
 * suite cannot easily stage: a challenge issued to one user or ceremony and
 * answered by another, an expired challenge, one authenticator claimed by
 * two users, a user handle naming someone else, a counter that went
 * backwards, label length, removing another user's passkey, and the sign-in
 * throttle. tests/browser/passkeys.test.js proves the same routes against
 * Chrome's own authenticator.
 *
 * Run:  php tests/passkeys.test.php
 */

declare(strict_types=1);

require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/fixtures/passkeys/soft-authenticator.php';

$BASE = minn_test_url();
$SITE = minn_test_site_root();
$HOST = (string) parse_url($BASE, PHP_URL_HOST);
$ORIGIN = 'https://' . $HOST;
$FIXTURE = __DIR__ . '/fixtures/extensions/minn-test-passkeys';
$LINK = "{$SITE}/public/wp-content/plugins/minn-test-passkeys";

$pass = 0;
$fail = 0;
$check = static function (bool $ok, string $label, string $detail = '') use (&$pass, &$fail): void {
    $ok ? $pass++ : $fail++;
    echo ($ok ? '  ok  ' : 'FAIL  ') . $label . ($ok || $detail === '' ? '' : "\n      {$detail}") . "\n";
};

$wp = static fn (string $args): string => trim((string) shell_exec('wp --path=' . escapeshellarg("{$SITE}/wp-reference") . " {$args} 2>/dev/null"));

$madeLink = !file_exists($LINK);
$extensionsBefore = $wp('option get minn_active_extensions');
$logins = ['zz-passkey-a', 'zz-passkey-b'];

$cleanup = static function () use ($wp, $logins, $extensionsBefore, $madeLink, $LINK): void {
    foreach ($logins as $login) {
        $id = $wp('user get ' . escapeshellarg($login) . ' --field=ID');
        if ($id !== '') {
            $wp("user delete {$id} --yes");
        }
    }
    foreach (['minn_passkey_challenge_*', 'minn_login_throttle_*'] as $pattern) {
        foreach (array_filter(explode("\n", $wp('option list --search=' . escapeshellarg($pattern) . ' --field=option_name'))) as $name) {
            $wp('option delete ' . escapeshellarg($name));
        }
    }
    $wp($extensionsBefore === '' ? 'option delete minn_active_extensions' : 'option update minn_active_extensions ' . escapeshellarg($extensionsBefore));
    if ($madeLink && is_link($LINK)) {
        unlink($LINK);
    }
};

/** One request to the fixture's routes: status, decoded body. */
$call = static function (string $method, string $route, ?array $body = null, ?array $session = null) use ($BASE): array {
    $headers = ['Content-Type: application/json'];
    if ($session !== null) {
        $headers[] = 'Cookie: ' . $session['cookie_name'] . '=' . $session['cookie'];
        $headers[] = 'X-WP-Nonce: ' . $session['nonce'];
    }
    $curl = curl_init("{$BASE}/wp-json/minn-test/v1/{$route}");
    curl_setopt_array($curl, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $body === null ? null : json_encode($body, JSON_UNESCAPED_SLASHES),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_TIMEOUT => 20,
    ]);
    $text = (string) curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    return [$status, json_decode($text, true)];
};

$b64 = static fn (string $bytes): string => MinnTestAuthenticator::b64($bytes);
$unb64 = static fn (string $text): string => (string) base64_decode(strtr($text, '-_', '+/'));

/** The JSON a browser's toJSON() gives for a new passkey. */
$created = static function (MinnTestAuthenticator $auth, string $challenge, array $o = []) use ($HOST, $ORIGIN, $b64): array {
    $made = $auth->create($HOST, $ORIGIN, $challenge, $o);
    return ['id' => $b64($auth->credentialId), 'rawId' => $b64($auth->credentialId), 'type' => 'public-key', 'response' => [
        'clientDataJSON' => $b64($made['clientDataJSON']),
        'attestationObject' => $b64($made['attestationObject']),
        'transports' => ['internal', 'made-up'],
    ]];
};

/** The JSON a browser's toJSON() gives for a sign-in. */
$asserted = static function (MinnTestAuthenticator $auth, string $challenge, array $o = [], string $handle = '') use ($HOST, $ORIGIN, $b64): array {
    $answer = $auth->get($HOST, $ORIGIN, $challenge, $o);
    return ['id' => $b64($auth->credentialId), 'rawId' => $b64($auth->credentialId), 'type' => 'public-key', 'response' => [
        'clientDataJSON' => $b64($answer['clientDataJSON']),
        'authenticatorData' => $b64($answer['authenticatorData']),
        'signature' => $b64($answer['signature']),
        'userHandle' => $handle,
    ]];
};

echo "passkeys suite: {$BASE}\n";
$cleanup();
if (!file_exists($LINK)) {
    symlink($FIXTURE, $LINK);
}
$active = json_decode($extensionsBefore !== '' ? $extensionsBefore : '[]', true) ?: [];
$wp('option update minn_active_extensions ' . escapeshellarg(json_encode(array_values(array_unique([...$active, 'minn-test-passkeys'])))));
$sessions = [];
foreach ($logins as $login) {
    $password = bin2hex(random_bytes(12));
    $wp('user create ' . escapeshellarg($login) . ' ' . escapeshellarg("{$login}@minn-engine.localhost") . ' --role=subscriber --user_pass=' . escapeshellarg($password));
    $sessions[$login] = json_decode((string) shell_exec('php ' . escapeshellarg(__DIR__ . '/tools/engine-login.php') . ' ' . escapeshellarg($login) . ' ' . escapeshellarg($password)), true);
}
[$a, $b] = [$sessions['zz-passkey-a'], $sessions['zz-passkey-b']];
$check(!empty($a['ok']) && !empty($b['ok']), 'two throwaway users signed in');

try {
    $auth = new MinnTestAuthenticator();
    [, $optionsA] = $call('POST', 'passkeys/options', null, $a);
    $check(($optionsA['rp']['id'] ?? '') === $HOST && count($optionsA['pubKeyCredParams'] ?? []) === 3, 'creation options name the site and offer three key kinds');

    [$status, $body] = $call('POST', 'passkeys', ['credential' => $created($auth, $unb64($optionsA['challenge'])), 'label' => 'B uses A'], $b);
    $check($status === 400 && ($body['data']['reason'] ?? '') === 'challenge', "a challenge issued to one user is refused for another", json_encode($body));

    [, $optionsA] = $call('POST', 'passkeys/options', null, $a);
    [$status, $body] = $call('POST', 'passkeys', ['credential' => $created($auth, $unb64($optionsA['challenge'])), 'label' => str_repeat('é', 300)], $a);
    $check($status === 201 && mb_strlen($body['label'] ?? '') === 100, 'a passkey is added, its label cut to 100 characters', json_encode($body));
    $check(($body['transports'] ?? null) === ['internal'], 'transports the browser made up are dropped', json_encode($body['transports'] ?? null));
    $passkeyId = (string) ($body['id'] ?? '');

    [, $optionsB] = $call('POST', 'passkeys/options', null, $b);
    $check(($optionsB['excludeCredentials'] ?? null) === [], "another user's options exclude none of A's passkeys");
    [$status, $body] = $call('POST', 'passkeys', ['credential' => $created($auth, $unb64($optionsB['challenge'])), 'label' => 'stolen'], $b);
    $check($status === 400 && ($body['data']['reason'] ?? '') === 'taken', 'one authenticator cannot be claimed by a second user', json_encode($body));

    [, $optionsA] = $call('POST', 'passkeys/options', null, $a);
    $check(($optionsA['excludeCredentials'][0]['id'] ?? '') === $passkeyId, "the owner's options exclude the passkey they have");
    [$status, $body] = $call('POST', 'passkeys/sign-in', ['credential' => $asserted($auth, $unb64($optionsA['challenge']))]);
    $check($status === 400 && ($body['data']['reason'] ?? '') === 'challenge', 'a creation challenge cannot sign anyone in', json_encode($body));

    [, $request] = $call('POST', 'passkeys/sign-in-options');
    [$status, $body] = $call('POST', 'passkeys', ['credential' => $created(new MinnTestAuthenticator(), $unb64($request['challenge'])), 'label' => 'x'], $a);
    $check($status === 400 && ($body['data']['reason'] ?? '') === 'challenge', 'a sign-in challenge cannot add a passkey', json_encode($body));

    [, $request] = $call('POST', 'passkeys/sign-in-options');
    [$status, $body] = $call('POST', 'passkeys/sign-in', ['credential' => $asserted($auth, $unb64($request['challenge']), [], $optionsB['user']['id'])]);
    $check($status === 400 && ($body['data']['reason'] ?? '') === 'handle', "a sign-in naming another user's handle is refused", json_encode($body));

    [, $request] = $call('POST', 'passkeys/sign-in-options');
    [$status, $body] = $call('POST', 'passkeys/sign-in', ['credential' => $asserted($auth, $unb64($request['challenge']), [], $optionsA['user']['id'])]);
    $check($status === 200 && ($body['login'] ?? '') === 'zz-passkey-a', 'the passkey signs its owner in', json_encode($body));

    [, $request] = $call('POST', 'passkeys/sign-in-options');
    [$status, $body] = $call('POST', 'passkeys/sign-in', ['credential' => $asserted($auth, $unb64($request['challenge']), ['count' => 1])]);
    $check($status === 400 && ($body['data']['reason'] ?? '') === 'counter', 'a counter that went backwards is refused', json_encode($body));

    [, $request] = $call('POST', 'passkeys/sign-in-options');
    $name = $wp('option list --search=' . escapeshellarg('minn_passkey_challenge_*') . ' --field=option_name');
    $check(count(array_filter(explode("\n", $name))) === 1, 'used challenges are gone; only the one just issued is kept', $name);
    $wp('option update ' . escapeshellarg($name) . ' ' . escapeshellarg('1:get:0'));
    [$status, $body] = $call('POST', 'passkeys/sign-in', ['credential' => $asserted($auth, $unb64($request['challenge']))]);
    $check($status === 400 && ($body['data']['reason'] ?? '') === 'challenge', 'an expired challenge is refused', json_encode($body));

    [, $removed] = $call('DELETE', "passkeys/{$passkeyId}", null, $b);
    [, $list] = $call('GET', 'passkeys', null, $a);
    $check(($removed['removed'] ?? null) === false && count($list) === 1, "another user cannot remove A's passkey");

    $statuses = [];
    for ($i = 0; $i < 21; $i++) {
        [, $request] = $call('POST', 'passkeys/sign-in-options');
        [$statuses[]] = $call('POST', 'passkeys/sign-in', ['credential' => $asserted($auth, $unb64($request['challenge']), ['corrupt' => true])]);
    }
    // Four sign-ins above already failed from this address, so the twentieth failure lands sixteen into the loop.
    $first429 = array_search(429, $statuses, true);
    $check($first429 === 16 && array_unique(array_slice($statuses, 0, 16)) === [400] && array_unique(array_slice($statuses, 16)) === [429], 'the twentieth failed passkey sign-in from an address meets the sign-in throttle', implode(',', $statuses));
} finally {
    $cleanup();
}

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
