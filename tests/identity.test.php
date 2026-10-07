<?php
/**
 * Identity parity: a plugin that says who the request is. The fixture
 * mu-plugin tests/fixtures/mu-plugins/minn-test-identity.php answers
 * determine_current_user for a request whose X-Minn-Identity header names a
 * token this suite wrote, as a token plugin (JWT, OAuth, single sign-on)
 * does; both stacks then answer the same requests: who /users/me is, what
 * an editor's draft list shows, the front page's signed-in state, and the
 * same without a token, with a token naming nobody, and with one naming a
 * user who does not exist; then with an administrator's sign-in cookie and
 * its REST nonce, where the cookie vouches only for its own user. The fixture and tokens live in both stacks only
 * while the suite runs.
 *
 *   php tests/identity.test.php
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

// The fixture lives in both mu-plugin folders, and the tokens beside it, only while the suite runs.
$fixture = __DIR__ . '/fixtures/mu-plugins/minn-test-identity.php';
$stacks = [$SITE . '/wp-reference/wp-content', $SITE . '/public/wp-content'];
foreach ($stacks as $content) {
    if (!is_link("{$content}/mu-plugins/minn-test-identity.php")) {
        symlink($fixture, "{$content}/mu-plugins/minn-test-identity.php");
    }
    @mkdir("{$content}/minn-identity", 0755, true);
}
register_shutdown_function(static function () use ($stacks, $WP): void {
    foreach ($stacks as $content) {
        @unlink("{$content}/mu-plugins/minn-test-identity.php");
        array_map('unlink', glob("{$content}/minn-identity/*.json") ?: []);
        @rmdir("{$content}/minn-identity");
    }
    shell_exec("{$WP} user delete \$({$WP} user list --field=ID --login__in=identity-reader 2>/dev/null) --reassign=1 --yes >/dev/null 2>&1");
});

shell_exec("{$WP} user create identity-reader identity-reader@minn-engine.localhost --role=subscriber >/dev/null 2>&1");
$reader = (int) trim((string) shell_exec("{$WP} user get identity-reader --field=ID 2>/dev/null"));
/** A token for a user id, written to both stacks. */
$token = static function (int $user) use ($stacks): string {
    $token = bin2hex(random_bytes(16));
    foreach ($stacks as $content) {
        file_put_contents("{$content}/minn-identity/{$token}.json", json_encode(['user' => $user]));
    }
    return $token;
};

/** Status and body for a request carrying the token (none when '') and any other headers. */
$ask = static function (string $base, string $path, string $token, array $headers = []): array {
    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_TIMEOUT => 30, CURLOPT_HTTPHEADER => [...($token === '' ? [] : ["X-Minn-Identity: {$token}"]), ...$headers]]);
    $body = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return [$status, $body];
};
$compare = static function (string $label, string $path, string $token, callable $shape, array $headers = []) use ($ENGINE, $REF, $ask, $check): void {
    [$es, $eb] = $ask($ENGINE, $path, $token, $headers);
    [$rs, $rb] = $ask($REF, $path, $token, $headers);
    $engine = [$es, $shape($eb)];
    $reference = [$rs, $shape($rb)];
    $check($label, $engine === $reference, 'engine=' . json_encode($engine) . ' reference=' . json_encode($reference));
};
$who = static function (string $body): mixed {
    $data = json_decode($body, true);
    return is_array($data) ? ($data['code'] ?? $data['slug'] ?? null) : null;
};
$drafts = static function (string $body): mixed {
    $data = json_decode($body, true);
    return is_array($data) && array_is_list($data) ? 'a list' : ($data['code'] ?? null);
};
$signedIn = static fn (string $body): bool => preg_match('/<body class="[^"]*\blogged-in\b/', $body) === 1;

echo "identity suite: {$ENGINE} (engine) vs {$REF} (reference)\n";
$cases = [
    'no token' => '',
    'an administrator\'s token' => $token(1),
    'a subscriber\'s token' => $token($reader),
    'a token naming nobody' => $token(0),
    'a token naming no such user' => $token(987654321),
];
foreach ($cases as $label => $value) {
    $compare("{$label}: who /users/me is", '/wp-json/wp/v2/users/me', $value, $who);
    $compare("{$label}: the edit context's drafts", '/wp-json/wp/v2/posts?status=draft&context=edit&per_page=1', $value, $drafts);
    $compare("{$label}: the front page's signed-in state", '/', $value, $signedIn);
}

// An administrator signed in by cookie, with the REST nonce bound to that session.
$mint = json_decode((string) shell_exec("{$WP} eval-file " . escapeshellarg(__DIR__ . '/tools/mint-session.php') . ' 1 2>/dev/null'), true);
if (is_array($mint) && !empty($mint['cookie'])) {
    $session = ['Cookie: wordpress_logged_in_' . md5($ENGINE) . '=' . rawurlencode($mint['cookie']) . '; wordpress_logged_in_' . md5($REF) . '=' . rawurlencode($mint['cookie']), 'X-WP-Nonce: ' . $mint['nonce']];
    $subscriber = $token($reader);
    $compare('signed in with the nonce, no token: who /users/me is', '/wp-json/wp/v2/users/me', '', $who, $session);
    $compare('signed in with the nonce, a subscriber\'s token: who /users/me is', '/wp-json/wp/v2/users/me', $subscriber, $who, $session);
    $compare('signed in with the nonce, a token naming nobody: who /users/me is', '/wp-json/wp/v2/users/me', $token(0), $who, $session);
    $compare('signed in, a token naming nobody: the front page\'s signed-in state', '/', $token(0), $signedIn, [$session[0]]);
}

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
