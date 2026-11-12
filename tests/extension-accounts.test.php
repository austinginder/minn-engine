<?php

declare(strict_types=1);

/**
 * Minn on its own: an extension's own sign-in surface over Seams::accounts().
 * Registration follows users_can_register and the site's default role, takes
 * the password the form chose and refuses what the sign-in page's
 * registration refuses; the sign-in counts against the same throttle and
 * mints the same session and cookies (the REST nonce and wp-login.php both
 * accept the result), a refusal never says which half was wrong, sign-out
 * ends the session, and the reset link is the lost-password page's. Drives
 * a copy of tests/fixtures/extensions/minn-test-records on the test site.
 *
 *   php tests/extension-accounts.test.php
 */

require_once __DIR__ . '/lib.php';

$pass = 0;
$fail = 0;
$check = static function (string $label, bool $ok, string $detail = '') use (&$pass, &$fail): void {
    if ($ok) {
        $pass++;
        echo "  ok   {$label}\n";
        return;
    }
    $fail++;
    echo "  FAIL {$label}" . ($detail !== '' ? "\n      " . substr($detail, 0, 400) : '') . "\n";
};

$ENGINE = minn_test_url();
$site = minn_test_site_root();
$wp = static fn (string $command): string => trim((string) shell_exec('cd ' . escapeshellarg($site . '/wp-reference') . " && wp {$command} 2>/dev/null"));
$jar = tempnam(sys_get_temp_dir(), 'minn-accounts-');
/** @return array{0: int, 1: mixed, 2: string} status, decoded body, headers */
$call = static function (string $method, string $path, ?array $body = null, array $headers = [], bool $cookies = true) use ($ENGINE, $jar): array {
    $args = ['-sk', '-D', '-', '-o', '-', '-X', $method, str_starts_with($path, '/wp-') ? "{$ENGINE}{$path}" : "{$ENGINE}/wp-json{$path}"];
    if ($cookies) {
        array_push($args, '-b', $jar, '-c', $jar);
    }
    foreach ($headers as $header) {
        array_push($args, '-H', $header);
    }
    if ($body !== null) {
        array_push($args, '-H', 'Content-Type: application/json', '--data-binary', json_encode($body));
    }
    $out = (string) shell_exec('/usr/bin/curl ' . implode(' ', array_map('escapeshellarg', $args)));
    [$head, $text] = array_pad(explode("\r\n\r\n", $out, 2), 2, '');
    preg_match('#^HTTP/\S+ (\d+)#', $head, $m);
    return [(int) ($m[1] ?? 0), json_decode($text, true), $head];
};
$nonce = static fn (): string => trim((string) shell_exec('/usr/bin/curl -sk -b ' . escapeshellarg($jar) . ' ' . escapeshellarg("{$ENGINE}/wp-admin/admin-ajax.php?action=rest-nonce")));

echo "extension-accounts suite: {$ENGINE}\n";
$fixture = __DIR__ . '/fixtures/extensions/minn-test-records';
$copy = "{$site}/public/wp-content/plugins/minn-test-records";
$logins = ['zz-acct-one', 'zz-acct-two', 'zz-acct-quiet', 'zz-acct-held'];
$sweep = static function () use ($wp, $copy, $logins): void {
    foreach ($logins as $login) {
        $id = $wp('user get ' . escapeshellarg($login) . ' --field=ID');
        if (ctype_digit($id)) {
            $wp("user delete {$id} --yes");
        }
    }
    $wp("db query \"DELETE FROM wp_options WHERE option_name LIKE 'minn_login_throttle_%'\"");
    // The extension's first request makes its declared table and records it: neither may outlive the run.
    $wp('db query "DROP TABLE IF EXISTS wp_minn_test_records_notes"');
    $tables = (array) json_decode($wp('option get minn_extension_tables') ?: '{}', true);
    unset($tables['minn-test-records']);
    $tables === [] ? $wp('option delete minn_extension_tables') : $wp('option update minn_extension_tables ' . escapeshellarg(json_encode($tables)));
    exec('rm -rf ' . escapeshellarg($copy));
};
$sweep();
exec('cp -R ' . escapeshellarg($fixture) . ' ' . escapeshellarg($copy));
$active = $wp('option get minn_active_extensions') ?: '[]';
$register = $wp('option get users_can_register');
$role = $wp('option get default_role');
$wp('option update minn_active_extensions ' . escapeshellarg(json_encode([...(array) json_decode($active, true), 'minn-test-records'])));
register_shutdown_function(static function () use ($wp, $active, $register, $role, $sweep, $jar): void {
    $wp('option update minn_active_extensions ' . escapeshellarg($active));
    $wp('option update users_can_register ' . escapeshellarg($register));
    $wp('option update default_role ' . escapeshellarg($role));
    $sweep();
    @unlink($jar);
});
$password = 'zz "quoted" pass\\word 1';

// 1. Registration follows the site's settings.
$wp('option update users_can_register 0');
[$status, $error] = $call('POST', '/minn-test/v1/players', ['login' => 'zz-acct-one', 'email' => 'zz-acct-one@example.com', 'password' => $password]);
$check('a site that takes no new accounts refuses registration', $status === 400 && ($error['code'] ?? '') === 'registration_closed', json_encode([$status, $error]));
$wp('option update users_can_register 1');
$wp('option update default_role subscriber');
[$status, $made] = $call('POST', '/minn-test/v1/players', ['login' => 'zz-acct-one', 'email' => 'zz-acct-one@example.com', 'password' => $password, 'shownAs' => 'Zz Shown One']);
$check('an open site makes the account', $status === 201 && ($made['login'] ?? '') === 'zz-acct-one' && (int) ($made['id'] ?? 0) > 0, json_encode([$status, $made]));
$check('with the site\'s default role', $wp('user get zz-acct-one --field=roles') === 'subscriber');
$check('shown by the name the form gave (display name and nickname)', $wp('user get zz-acct-one --field=display_name') === 'Zz Shown One' && $wp('user meta get zz-acct-one nickname') === 'Zz Shown One');
$check('and no nag to change the password it chose', $wp('user meta get zz-acct-one default_password_nag') === '');
[, $error] = $call('POST', '/minn-test/v1/players', ['login' => 'zz-acct-one', 'email' => 'not-an-email', 'password' => '']);
$codes = array_column((array) ($error['data']['notices'] ?? []), 'code');
$check('every refusal comes back at once: name taken, email malformed, no password', $codes === ['username_exists', 'invalid_email', 'empty_password'], json_encode($error));
$check('the answer is the first refusal, in plain words', ($error['code'] ?? '') === 'username_exists' && ($error['message'] ?? '') === 'This username is already registered. Please choose another one.', json_encode($error));
[, $error] = $call('POST', '/minn-test/v1/players', ['login' => 'zz-acct-two', 'email' => 'zz-acct-one@example.com', 'password' => 'x']);
$check('an email another account holds is refused', ($error['code'] ?? '') === 'email_exists', json_encode($error));
[, $error] = $call('POST', '/minn-test/v1/players', ['login' => 'zz-acct-two', 'email' => 'zz-acct-two@example.com', 'password' => 'zz-other-pass', 'role' => 'zz-no-such-role']);
$check('a role the site does not have is refused', ($error['code'] ?? '') === 'unknown_role', json_encode($error));
$wp('option update default_role author');
[$status] = $call('POST', '/minn-test/v1/players', ['login' => 'zz-acct-two', 'email' => 'zz-acct-two@example.com', 'password' => 'zz-other-pass', 'role' => 'subscriber']);
$check('a role asked for wins over the site\'s default role', $wp('user get zz-acct-two --field=roles') === 'subscriber');
$wp('option update default_role subscriber');
$check('without a name to show, the account is shown by its login', $wp('user get zz-acct-two --field=display_name') === 'zz-acct-two');
$check('nobody is signed in by registering', $status === 201 && !str_contains((string) @file_get_contents($jar), 'wordpress_logged_in_'));
[$status] = $call('POST', '/minn-test/v1/players', ['login' => 'zz-acct-quiet', 'email' => 'zz-acct-quiet@example.com', 'password' => 'zz-quiet-pass', 'quietly' => true]);
$check('registerQuietly makes the account too', $status === 201 && ctype_digit($wp('user get zz-acct-quiet --field=ID')));

// 2. Signing in.
[$status, $error] = $call('POST', '/minn-test/v1/session', ['login' => 'zz-acct-one', 'password' => 'wrong']);
$check('a wrong password is refused without saying which half was wrong', $status === 400 && ($error['code'] ?? '') === 'incorrect_credentials', json_encode([$status, $error]));
$check('and counts against the address\'s sign-in throttle', $wp("db query \"SELECT COUNT(*) FROM wp_options WHERE option_name LIKE 'minn_login_throttle_%'\" --skip-column-names") === '1');
[, $error] = $call('POST', '/minn-test/v1/session', ['login' => 'zz-nobody-here', 'password' => 'wrong']);
$check('an unknown name reads the same', ($error['code'] ?? '') === 'incorrect_credentials', json_encode($error));
[$status, , $head] = $call('POST', '/minn-test/v1/session', ['login' => 'zz-acct-one@example.com', 'password' => $password]);
$check('the right password (by email) signs in: the sign-in cookies ride on the answer', $status === 200 && preg_match('/set-cookie: wordpress_logged_in_[0-9a-f]{32}=zz-acct-one%7C/i', $head) === 1, $head);
$check('a session that ends with the browser unless remembered', preg_match('/set-cookie: wordpress_logged_in_[^\n]*expires=/i', $head) !== 1, $head);
[, , $kept] = $call('POST', '/minn-test/v1/session', ['login' => 'zz-acct-two', 'password' => 'zz-other-pass', 'remember' => true], [], false);
$check('remembered, the cookie outlives the browser session by fourteen days', preg_match('/set-cookie: wordpress_logged_in_[^\n]*expires=([^;\n]+)/i', $kept, $until) === 1 && strtotime($until[1]) - time() >= 14 * 86400 - 120 && strtotime($until[1]) - time() <= 14 * 86400 + 12 * 3600 + 120, implode("\n", preg_grep('/^set-cookie/i', explode("\r\n", $kept))));
$fresh = $nonce();
[, $who] = $call('GET', '/minn-test/v1/whoami', null, ["X-WP-Nonce: {$fresh}"]);
$check('the cookie and the nonce admin-ajax hands out are the new user to the REST gate', ($who['login'] ?? '') === 'zz-acct-one', json_encode([$fresh, $who]));
$check('one session is stored for the user', substr_count($wp('user meta get zz-acct-one session_tokens --format=json'), 'expiration') === 1);

// 3. Signing out.
[$status, , $head] = $call('DELETE', '/minn-test/v1/session');
$expired = preg_match('/set-cookie: wordpress_logged_in_[0-9a-f]{32}=[^\n]*expires=([^;\n]+)/i', $head, $gone) === 1 && strtotime($gone[1]) < time();
$check('signing out expires the sign-in cookie', $status === 200 && $expired, $head);
$check('and ends the session', !str_contains($wp('user meta get zz-acct-one session_tokens --format=json'), 'expiration'));
[$status, $okay] = $call('DELETE', '/minn-test/v1/session', null, [], false);
$check('signing out with no session is harmless', $status === 200 && ($okay['ok'] ?? false) === true, json_encode([$status, $okay]));

// 4. The account is the site's like any other.
$form = (string) shell_exec('/usr/bin/curl -sk -D - -o /dev/null -b wordpress_test_cookie=WP%20Cookie%20check --data-urlencode ' . escapeshellarg('log=zz-acct-one') . ' --data-urlencode ' . escapeshellarg('pwd=' . $password) . ' -d testcookie=1 ' . escapeshellarg("{$ENGINE}/wp-login.php"));
$check('the password it chose signs in at wp-login.php too (quotes and a backslash in it)', preg_match('#^HTTP/\S+ 302#', $form) === 1 && stripos($form, 'set-cookie: wordpress_logged_in_') !== false, $form);
[$status, $sent] = $call('POST', '/minn-test/v1/lost-password', ['login' => 'zz-acct-one'], [], false);
$check('a reset link is mailed as the lost-password page mails it', $status === 200 && ($sent['sent'] ?? false) === true && $wp('user get zz-acct-one --field=user_activation_key') !== '', json_encode([$status, $sent]));
[, $error] = $call('POST', '/minn-test/v1/lost-password', ['login' => 'zz-nobody@example.com'], [], false);
$check('no account by that address is refused in plain words', ($error['code'] ?? '') === 'invalid_email' && !str_contains((string) ($error['message'] ?? ''), '<'), json_encode($error));

// 5. A reset page of the extension's own.
$mailpit = static function (string $path): array {
    $context = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
    return (array) json_decode((string) @file_get_contents((getenv('MINN_MAILPIT') ?: 'https://cove.localhost/mail-api/v1') . "/{$path}", false, $context), true);
};
$told = static fn (string $login): int => (int) ($mailpit('search?query=' . rawurlencode('subject:"New User Registration" ' . $login) . '&limit=5')['messages_count'] ?? 0);
$check('register tells the site\'s address; registerQuietly tells nobody', $told('zz-acct-two') >= 1 && $told('zz-acct-quiet') === 0, json_encode([$told('zz-acct-two'), $told('zz-acct-quiet')]));
$call('POST', '/minn-test/v1/session', ['login' => 'zz-acct-one', 'password' => $password]);
[$status] = $call('POST', '/minn-test/v1/lost-password', ['login' => 'zz-acct-one@example.com', 'page' => 'https://minn.localhost/zz-reset/'], [], false);
usleep(500000);
$found = $mailpit('search?query=' . rawurlencode('to:zz-acct-one@example.com') . '&limit=1');
$text = (string) ($mailpit('message/' . ($found['messages'][0]['ID'] ?? ''))['Text'] ?? '');
$linked = preg_match('#https://minn\.localhost/zz-reset/\#key=([A-Za-z0-9]{20})&login=zz-acct-one#', $text, $m) === 1;
$check('with a page of its own, the mailed link opens that page, the key and login in its fragment', $status === 200 && $linked, substr($text, 0, 300));
$check('without words of its own, the mail is the sign-in page\'s', str_contains($text, 'zz-acct-one') && str_contains($text, 'Username'), substr($text, 0, 200));
$call('POST', '/minn-test/v1/lost-password', ['login' => 'zz-acct-one@example.com', 'page' => 'https://minn.localhost/zz-reset/', 'words' => true], [], false);
usleep(500000);
$found = $mailpit('search?query=' . rawurlencode('to:zz-acct-one@example.com') . '&limit=1');
$mail = $mailpit('message/' . ($found['messages'][0]['ID'] ?? ''));
$text = (string) ($mail['Text'] ?? '');
$check('with words of its own, the mail is the extension\'s: the display name, the link, no login', ($mail['Subject'] ?? '') === 'Reset for Zz Shown One' && preg_match('#^Hello Zz Shown One, choose a new password: https://minn\.localhost/zz-reset/\#key=([A-Za-z0-9]{20})&login=zz-acct-one\s*$#', $text, $m) === 1, json_encode([$mail['Subject'] ?? null, $text]));
$call('POST', '/minn-test/v1/lost-password', ['login' => 'zz-acct-one@example.com', 'page' => 'https://minn.localhost/zz-reset/', 'html' => true], [], false);
usleep(500000);
$found = $mailpit('search?query=' . rawurlencode('to:zz-acct-one@example.com') . '&limit=1');
$html = $mailpit('message/' . ($found['messages'][0]['ID'] ?? ''));
$check('its words may carry headers: an HTML mail from a sender of its own', str_contains((string) ($html['HTML'] ?? ''), '<b>Zz Shown One</b>') && preg_match('#href="https://minn\.localhost/zz-reset/\#key=([A-Za-z0-9]{20})&(?:amp;)?login=zz-acct-one"#', (string) ($html['HTML'] ?? ''), $m) === 1 && ($html['From']['Name'] ?? '') === 'Records Probe' && ($html['From']['Address'] ?? '') === 'records@minn.localhost', json_encode([$html['From'] ?? null, substr((string) ($html['HTML'] ?? ''), 0, 200)]));
$key = $m[1] ?? '';
[, $error] = $call('POST', '/minn-test/v1/reset', ['login' => 'zz-acct-one', 'key' => 'zzzzzzzzzzzzzzzzzzzz', 'password' => 'zz-new-pass-1'], [], false);
$check('a key that does not match is refused', ($error['code'] ?? '') === 'invalid_key', json_encode($error));
[, $error] = $call('POST', '/minn-test/v1/reset', ['login' => 'zz-acct-one', 'key' => $key, 'password' => ''], [], false);
$check('an empty password is refused', ($error['code'] ?? '') === 'empty_password', json_encode($error));
[$status, $done] = $call('POST', '/minn-test/v1/reset', ['login' => 'zz-acct-one', 'key' => $key, 'password' => 'zz-new-pass-1'], [], false);
$check('the right key saves the new password', $status === 200 && ($done['id'] ?? 0) > 0, json_encode([$status, $done]));
$check('and ends every session of the account', !str_contains($wp('user meta get zz-acct-one session_tokens --format=json'), 'expiration'));
[, $error] = $call('POST', '/minn-test/v1/session', ['login' => 'zz-acct-one', 'password' => $password], [], false);
[$status] = $call('POST', '/minn-test/v1/session', ['login' => 'zz-acct-one', 'password' => 'zz-new-pass-1'], [], false);
$check('the old password no longer signs in, the new one does', ($error['code'] ?? '') === 'incorrect_credentials' && $status === 200, json_encode($error));
[, $error] = $call('POST', '/minn-test/v1/reset', ['login' => 'zz-acct-one', 'key' => $key, 'password' => 'zz-other-pass-2'], [], false);
$check('a key works once', ($error['code'] ?? '') === 'invalid_key', json_encode($error));

// 6. Changing the password and the email of a signed-in account.
$call('POST', '/minn-test/v1/session', ['login' => 'zz-acct-one', 'password' => 'zz-new-pass-1']);
$other = tempnam(sys_get_temp_dir(), 'minn-accounts-other-');
shell_exec('/usr/bin/curl -sk -o /dev/null -c ' . escapeshellarg($other) . " -H 'Content-Type: application/json' --data-binary '{\"login\":\"zz-acct-one\",\"password\":\"zz-new-pass-1\"}' " . escapeshellarg("{$ENGINE}/wp-json/minn-test/v1/session"));
$check('more than one session open before the change', substr_count($wp('user meta get zz-acct-one session_tokens --format=json'), 'expiration') >= 2);
$fresh = $nonce();
[$status, $error] = $call('PUT', '/minn-test/v1/password', ['current' => 'not-it', 'password' => 'zz-third-pass-3'], ["X-WP-Nonce: {$fresh}"]);
$check('a wrong current password is refused', $status === 400 && ($error['code'] ?? '') === 'incorrect_password', json_encode([$status, $error]));
[$status, , $head] = $call('PUT', '/minn-test/v1/password', ['current' => 'zz-new-pass-1', 'password' => 'zz-third-pass-3', 'words' => true], ["X-WP-Nonce: {$fresh}"]);
$check('the right one saves the new password, fresh cookies on the answer', $status === 200 && stripos($head, 'set-cookie: wordpress_logged_in_') !== false, $head);
$check('and only the new session is left', substr_count($wp('user meta get zz-acct-one session_tokens --format=json'), 'expiration') === 1);
[, $who] = $call('GET', '/minn-test/v1/whoami', null, ['X-WP-Nonce: ' . $nonce()]);
$check('the fresh cookies sign this browser in', ($who['login'] ?? '') === 'zz-acct-one', json_encode($who));
$oldOther = trim((string) shell_exec('/usr/bin/curl -sk -b ' . escapeshellarg($other) . ' ' . escapeshellarg("{$ENGINE}/wp-admin/admin-ajax.php?action=rest-nonce")));
$check('the other browser is signed out', $oldOther === '0', $oldOther);
@unlink($other);
usleep(500000);
$found = $mailpit('search?query=' . rawurlencode('to:zz-acct-one@example.com subject:"Password changed"') . '&limit=1');
$mail = $mailpit('message/' . ($found['messages'][0]['ID'] ?? ''));
$check('the notice is in the extension\'s words, to the account\'s address', ($mail['Subject'] ?? '') === 'Password changed for Zz Shown One' && str_contains((string) ($mail['HTML'] ?? ''), 'your password changed'), json_encode([$mail['Subject'] ?? null]));
[, $error] = $call('PUT', '/minn-test/v1/email', ['current' => 'zz-third-pass-3', 'email' => 'zz-acct-two@example.com'], ['X-WP-Nonce: ' . $nonce()]);
$check('an email another account holds is refused', ($error['code'] ?? '') === 'email_exists', json_encode($error));
[, $error] = $call('PUT', '/minn-test/v1/email', ['current' => 'zz-third-pass-3', 'email' => 'nope'], ['X-WP-Nonce: ' . $nonce()]);
$check('a malformed one too', ($error['code'] ?? '') === 'invalid_email', json_encode($error));
[$status, $changed] = $call('PUT', '/minn-test/v1/email', ['current' => 'zz-third-pass-3', 'email' => 'zz-acct-one-new@example.com', 'words' => true], ['X-WP-Nonce: ' . $nonce()]);
$check('the right password saves the new email', $status === 200 && ($changed['email'] ?? '') === 'zz-acct-one-new@example.com' && $wp('user get zz-acct-one --field=user_email') === 'zz-acct-one-new@example.com', json_encode([$status, $changed]));
usleep(500000);
$found = $mailpit('search?query=' . rawurlencode('to:zz-acct-one@example.com subject:"Email changed"') . '&limit=1');
$check('the old address is told, in the extension\'s words', ($found['messages'][0]['Subject'] ?? '') === 'Email changed for Zz Shown One', json_encode($found['messages'][0]['Subject'] ?? null));
$wp("db query \"DELETE FROM wp_options WHERE option_name LIKE 'minn_login_throttle_%'\"");

// 7. A sign-up held until its email is proven: the password kept only as its hash, the account made from it later, signed in by the link.
[, $held] = $call('POST', '/minn-test/v1/players/hash', ['password' => $password], [], false);
$hash = (string) ($held['hash'] ?? '');
$check('passwordHash hashes as an account stores it, never the password itself', str_starts_with($hash, '$wp$2y$') && !str_contains($hash, 'quoted'), $hash);
[, $error] = $call('POST', '/minn-test/v1/players/hashed', ['login' => 'zz-acct-held', 'email' => 'zz-acct-held@example.com', 'hash' => 'not a hash'], [], false);
$check('a hash that is not one is refused as no password', ($error['code'] ?? '') === 'empty_password', json_encode($error));
[, $error] = $call('POST', '/minn-test/v1/players/hashed', ['login' => 'zz-acct-held', 'email' => 'zz-acct-two@example.com', 'hash' => $hash], [], false);
$check('the registration checks still run: an email another account holds is refused', ($error['code'] ?? '') === 'email_exists', json_encode($error));
[$status, $made] = $call('POST', '/minn-test/v1/players/hashed', ['login' => 'zz-acct-held', 'email' => 'zz-acct-held@example.com', 'hash' => $hash, 'shownAs' => 'Zz Held', 'role' => 'subscriber'], [], false);
$check('registerQuietlyWithHash makes the account, with the role and name asked for', $status === 201 && $wp('user get zz-acct-held --field=roles') === 'subscriber' && $wp('user get zz-acct-held --field=display_name') === 'Zz Held', json_encode([$status, $made]));
$check('storing the held hash as the account\'s password', $wp('user get zz-acct-held --field=user_pass') === $hash);
$check('and telling nobody', $told('zz-acct-held') === 0);
[$status] = $call('POST', '/minn-test/v1/session', ['login' => 'zz-acct-held@example.com', 'password' => $password], [], false);
$form = (string) shell_exec('/usr/bin/curl -sk -D - -o /dev/null -b wordpress_test_cookie=WP%20Cookie%20check --data-urlencode ' . escapeshellarg('log=zz-acct-held') . ' --data-urlencode ' . escapeshellarg('pwd=' . $password) . ' -d testcookie=1 ' . escapeshellarg("{$ENGINE}/wp-login.php"));
$check('the password that was hashed signs in, here and at wp-login.php (quotes and a backslash in it)', $status === 200 && preg_match('#^HTTP/\S+ 302#', $form) === 1 && stripos($form, 'set-cookie: wordpress_logged_in_') !== false, $form);
$call('DELETE', '/minn-test/v1/session');
@unlink($jar);
[$status, , $head] = $call('POST', '/minn-test/v1/session/verified', ['login' => 'zz-acct-held']);
$check('signInVerified signs in without the password, remembered: the cookies outlive the browser session', $status === 200 && preg_match('/set-cookie: wordpress_logged_in_[0-9a-f]{32}=zz-acct-held%7C[^\n]*expires=([^;\n]+)/i', $head, $until) === 1 && strtotime($until[1]) - time() >= 14 * 86400 - 120, $head);
[, $who] = $call('GET', '/minn-test/v1/whoami', null, ['X-WP-Nonce: ' . $nonce()]);
$check('and the REST gate takes that session as the user', ($who['login'] ?? '') === 'zz-acct-held', json_encode($who));
$check('one session is stored for it', substr_count($wp('user meta get zz-acct-held session_tokens --format=json'), 'expiration') >= 1);
$call('DELETE', '/minn-test/v1/session');
$subject = 'zz-acct-mail ' . bin2hex(random_bytes(3));
[$status, $sent] = $call('POST', '/minn-test/v1/mail', ['to' => 'zz-acct-held@example.com', 'subject' => $subject, 'body' => '<p>Open <a href="https://minn.localhost/zz-verify/#t=abc">the link</a>.</p>', 'html' => true], [], false);
usleep(500000);
$found = $mailpit('search?query=' . rawurlencode('subject:"' . $subject . '"') . '&limit=1');
$mail = $mailpit('message/' . ($found['messages'][0]['ID'] ?? ''));
$check('mail sends the extension\'s own email through the site\'s mailer, HTML from its own sender', $status === 200 && ($sent['sent'] ?? false) === true && ($mail['Subject'] ?? '') === $subject && str_contains((string) ($mail['HTML'] ?? ''), 'href="https://minn.localhost/zz-verify/#t=abc"') && ($mail['From']['Address'] ?? '') === 'records@minn.localhost' && ($mail['To'][0]['Address'] ?? '') === 'zz-acct-held@example.com', json_encode([$mail['Subject'] ?? null, $mail['From'] ?? null]));
$wp("db query \"DELETE FROM wp_options WHERE option_name LIKE 'minn_login_throttle_%'\"");

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
