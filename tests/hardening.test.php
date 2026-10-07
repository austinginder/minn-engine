<?php

declare(strict_types=1);

/**
 * The internet-facing hardening: sign-in throttling, the security headers
 * the reference sends on its sign-in page, and the failure pages that
 * stand in for stack traces. The throttle rows this suite creates are
 * removed at the end so other suites can still sign in.
 */

require_once __DIR__ . '/lib.php';

$ROOT = dirname(__DIR__);
$ENGINE = 'https://minn.localhost';
$REF_DIR = minn_test_site_root() . '/wp-reference';

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

/** @return array{status: string, headers: array<string, string>, body: string} */
$request = static function (string $url, string $post = ''): array {
    $args = ['-sk', '-D', '-', $url];
    if ($post !== '') {
        array_push($args, '-d', $post);
    }
    exec('/usr/bin/curl ' . implode(' ', array_map('escapeshellarg', $args)), $lines);
    $status = '';
    $headers = [];
    $body = [];
    $inBody = false;
    foreach ($lines as $line) {
        if ($inBody) {
            $body[] = $line;
        } elseif (preg_match('#^HTTP/\S+\s+(\d+)#', $line, $m)) {
            $status = $m[1];
        } elseif (trim($line) === '') {
            $inBody = true;
        } elseif (str_contains($line, ':')) {
            [$k, $v] = explode(':', $line, 2);
            $headers[strtolower(trim($k))] = trim($v);
        }
    }
    return ['status' => $status, 'headers' => $headers, 'body' => implode("\n", $body)];
};
$clearThrottle = static function () use ($REF_DIR): void {
    exec('cd ' . escapeshellarg($REF_DIR) . ' && wp db query "DELETE FROM wp_options WHERE option_name LIKE \'minn_login_throttle_%\'" 2>/dev/null');
};

echo "hardening suite: $ENGINE\n";

// Headers.
$login = $request("$ENGINE/wp-login.php");
$check('sign-in page: X-Frame-Options SAMEORIGIN', ($login['headers']['x-frame-options'] ?? '') === 'SAMEORIGIN');
$check('sign-in page: Referrer-Policy strict-origin-when-cross-origin', ($login['headers']['referrer-policy'] ?? '') === 'strict-origin-when-cross-origin');
$check('sign-in page: Cache-Control no-store', str_contains($login['headers']['cache-control'] ?? '', 'no-store'));
$check('sign-in page: nosniff', ($login['headers']['x-content-type-options'] ?? '') === 'nosniff');
$home = $request("$ENGINE/");
$check('front page: nosniff', ($home['headers']['x-content-type-options'] ?? '') === 'nosniff');
$check('front page: X-Powered-By is Minn', ($home['headers']['x-powered-by'] ?? '') === 'Minn', $home['headers']['x-powered-by'] ?? '');
$check('front page: no X-Frame-Options (embeddable, as the reference)', !isset($home['headers']['x-frame-options']));
$admin = $request("$ENGINE/minn-admin/");
$check('admin: X-Frame-Options SAMEORIGIN on the redirect too', ($admin['headers']['x-frame-options'] ?? '') === 'SAMEORIGIN', json_encode($admin['headers']));

// Throttle: twenty failures, then a wall.
$clearThrottle();
$statuses = [];
for ($i = 1; $i <= 21; $i++) {
    $statuses[] = $request("$ENGINE/wp-login.php", 'log=admin&pwd=wrong-' . $i)['status'];
}
$check('twenty wrong passwords answer 200 with the form', count(array_filter(array_slice($statuses, 0, 20), static fn (string $s) => $s === '200')) === 20, implode(',', $statuses));
$blocked = $request("$ENGINE/wp-login.php", 'log=admin&pwd=wrong-21');
$check('the twenty-first is 429', $statuses[20] === '429' && $blocked['status'] === '429', implode(',', $statuses));
$check('429 carries Retry-After within the window', (int) ($blocked['headers']['retry-after'] ?? 0) > 0 && (int) $blocked['headers']['retry-after'] <= 900, json_encode($blocked['headers']['retry-after'] ?? null));
$check('429 page explains the wait', str_contains($blocked['body'], 'Too many failed sign-in attempts'));
$right = $request("$ENGINE/wp-login.php", 'log=admin&pwd=password');
$check('a correct password is refused while throttled', $right['status'] === '429');
$token = $request("$ENGINE/wp-login.php?user_id=1&cove_login_token=0000000");
$check('the one-time link is throttled too', $token['status'] === '429');
$clearThrottle();
$after = $request("$ENGINE/wp-login.php", 'log=admin&pwd=password');
$check('after the window clears, sign-in works again', $after['status'] === '302' && str_contains($after['headers']['set-cookie'] ?? '', 'wordpress_logged_in_'), $after['status']);
// A successful sign-in does not reset the address's counter (one owned account must not
// launder guesses at another); the window lapses on its own.
$request("$ENGINE/wp-login.php", 'log=admin&pwd=wrong');
$ok = $request("$ENGINE/wp-login.php", 'log=admin&pwd=password');
$rows = trim((string) shell_exec('cd ' . escapeshellarg($REF_DIR) . ' && wp db query "SELECT option_value FROM wp_options WHERE option_name LIKE \'minn_login_throttle_%\'" --skip-column-names 2>/dev/null'));
$check('a successful sign-in still works under the limit and leaves the counter to lapse', $ok['status'] === '302' && preg_match('/^\d+:1$/', $rows) === 1, $ok['status'] . ' ' . $rows);
$clearThrottle();

// Failure pages, rendered directly.
$render = static function (string $method) use ($ROOT): string {
    return (string) shell_exec('php -r ' . escapeshellarg(
        "require '$ROOT/public/minn/src/Minn/Autoloader.php'; Minn\\Autoloader::register();"
        . "\$r = Minn\\Http\\Failure::$method(); echo \$r->status, '|', \$r->headers['Cache-Control'] ?? '', '|', \$r->body;"
    ));
};
$internal = $render('internal');
$check('internal failure page is a 500 with no detail', str_starts_with($internal, '500|no-store|') && str_contains($internal, 'Something went wrong') && !str_contains($internal, 'Stack'));
$db = $render('databaseUnavailable');
$check('database page is a 503 with the connection message', str_starts_with($db, '503|no-store|') && str_contains($db, 'Error establishing a database connection'));

// A stored name that holds "]]>" cannot close a feed's CDATA. The reference
// lets it (a display name or term written raw by an import, a plugin or SQL
// breaks out into the feed's XML); the engine splits it, which changes no byte
// unless one is there (contracts/front/probes.md "Hardening").
$wp = static fn (string $args): string => trim((string) shell_exec('cd ' . escapeshellarg($REF_DIR) . ' && wp ' . $args . ' 2>/dev/null'));
$wp('user delete $(wp user get zzcdatahard --field=ID 2>/dev/null) --yes');
$user = $wp('user create zzcdatahard zzcdatahard@example.com --role=author --porcelain');
$term = $wp("term create category 'Zz CDATA hardening' --slug=zz-cdata-hardening --porcelain");
$post = $wp("post create --post_title='Zz CDATA hardening' --post_status=publish --post_author={$user} --post_category={$term} --post_content=probe --porcelain");
register_shutdown_function(static function () use ($wp, $user, $term, $post): void {
    $wp("post delete {$post} --force");
    $wp("term delete category {$term}");
    $wp("user delete {$user} --yes");
});
$wp("db query \"UPDATE wp_users SET display_name='Zz ]]><evil>a</evil>' WHERE ID={$user}\"");
$wp("db query \"UPDATE wp_terms SET name='Zz ]]><evil>c</evil>' WHERE term_id={$term}\"");
$wp('cache flush');
// Atom carries the name as text and the term in an attribute, as the reference does; the rule is about CDATA.
foreach (['rss2' => '/feed/', 'rdf' => '/feed/rdf/'] as $kind => $path) {
    $body = $request($ENGINE . $path)['body'];
    $check("{$kind} feed: a stored ]]> cannot close CDATA", str_contains($body, 'Zz CDATA hardening') && !str_contains($body, ']]><evil>'), $kind);
}
$check('rss2 feed: the split keeps the name readable', str_contains($request($ENGINE . '/feed/')['body'], '<dc:creator><![CDATA[Zz ]]]]><![CDATA[><evil>a</evil>]]></dc:creator>'));

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
