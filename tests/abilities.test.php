<?php
/**
 * Abilities: the catalogue an agent reads before it asks a site to do
 * anything, and the running of one. Diffed against the reference, which
 * has served wp-abilities/v1 since core 6.9, so a client that speaks the
 * reference's abilities speaks the engine's.
 *
 *   php tests/abilities.test.php
 */

declare(strict_types=1);

require __DIR__ . '/lib.php';

$ROOT = dirname(__DIR__);
$ENGINE = rtrim((string) (getenv('MINN_TEST_URL') ?: 'https://minn.localhost'), '/');
$REF = minn_test_reference_url();

$pass = 0;
$fail = 0;
$check = static function (string $label, bool $ok, string $detail = '') use (&$pass, &$fail): void {
    if ($ok) {
        $pass++;
        echo "  ok   {$label}\n";
    } else {
        $fail++;
        echo "  FAIL {$label}" . ($detail === '' ? '' : ": {$detail}") . "\n";
    }
};

if (@file_get_contents($REF . '/?rest_route=/', false, stream_context_create(['http' => ['timeout' => 30, 'ignore_errors' => true]])) === false) {
    echo "SKIP: reference not running at {$REF}\n";
    exit(0);
}

$mint = static function (int $uid) use ($ROOT, $REF): array {
    $out = json_decode((string) shell_exec(
        'wp --path=' . escapeshellarg(minn_test_site_root() . '/wp-reference') . ' eval-file ' . escapeshellarg($ROOT . '/tests/tools/mint-session.php') . " {$uid} 2>/dev/null"
    ), true);
    if (!$out || empty($out['cookie'])) {
        echo "SKIP: could not mint a session\n";
        exit(0);
    }
    return $out;
};
$fetch = static function (string $base, string $route, ?array $session, string $method = 'GET', string $query = '') use ($REF): array {
    $headers = [];
    if ($session !== null) {
        $headers[] = 'Cookie: ' . $session['cookie_name'] . '=' . $session['cookie'] . '; wordpress_logged_in_' . md5($REF) . '=' . $session['cookie'];
        $headers[] = 'X-WP-Nonce: ' . $session['nonce'];
    }
    $url = $base . '/?rest_route=' . rawurlencode($route) . ($query === '' ? '' : '&' . $query);
    $context = stream_context_create([
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
        'http' => ['method' => $method, 'ignore_errors' => true, 'timeout' => 20, 'header' => implode("\r\n", $headers)],
    ]);
    $body = (string) @file_get_contents($url, false, $context);
    $status = 0;
    foreach ($http_response_header ?? [] as $line) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $line, $m)) {
            $status = (int) $m[1];
        }
    }
    return [$status, json_decode($body, true)];
};
// The two stacks answer on different hosts, so their links and the site's own
// address differ by definition; the catalogue is what is compared.
$strip = static function (mixed $value) use (&$strip, $ENGINE, $REF): mixed {
    if (is_string($value)) {
        return str_replace([$ENGINE, $REF], '{site}', $value);
    }
    if (!is_array($value)) {
        return $value;
    }
    unset($value['_links']);
    return array_map($strip, $value);
};
$same = static function (string $label, string $route, ?array $session, string $method = 'GET', string $query = '') use ($fetch, $check, $strip, $ENGINE, $REF): void {
    [$engineStatus, $engineBody] = $fetch($ENGINE, $route, $session, $method, $query);
    [$referenceStatus, $referenceBody] = $fetch($REF, $route, $session, $method, $query);
    $engine = [$engineStatus, $strip($engineBody)];
    $reference = [$referenceStatus, $strip($referenceBody)];
    $check($label, $engine === $reference, substr(json_encode(['engine' => $engine, 'reference' => $reference]), 0, 400));
};

$admin = $mint(1);
$author = $mint(3);

$same('the catalogue matches the reference', '/wp-abilities/v1/abilities', $admin);
$same('so does the category list', '/wp-abilities/v1/categories', $admin);
$same('an author reads the catalogue too', '/wp-abilities/v1/abilities', $author);
$same('reading it needs a session', '/wp-abilities/v1/abilities', null);
$same('one ability by name', '/wp-abilities/v1/abilities/core/get-site-info', $admin);
$same('one category by slug', '/wp-abilities/v1/categories/site', $admin);
$same('a name nobody registered is not found', '/wp-abilities/v1/abilities/core/nope', $admin);
$same('a category nobody registered is not found', '/wp-abilities/v1/categories/nope', $admin);
$same('the catalogue narrows to a category', '/wp-abilities/v1/abilities', $admin, 'GET', 'category=user');
$same('a read-only ability refuses POST', '/wp-abilities/v1/abilities/core/get-site-info/run', $admin, 'POST');
$same('running one an author may not run is refused', '/wp-abilities/v1/abilities/core/get-site-info/run', $author);
$same('running one takes the site facts', '/wp-abilities/v1/abilities/core/get-site-info/run', $admin, 'GET', 'input%5Bfields%5D%5B0%5D=name&input%5Bfields%5D%5B1%5D=url');
$same('running the environment ability', '/wp-abilities/v1/abilities/core/get-environment-info/run', $admin, 'GET', 'input%5Bfields%5D%5B0%5D=wp_version');
$same('a user reads their own profile', '/wp-abilities/v1/abilities/core/get-user-info/run', $author, 'GET', 'input%5Bfields%5D%5B0%5D=user_login&input%5Bfields%5D%5B1%5D=roles');

// The index has to list the namespace, since that is how a client finds any of this.
[, $index] = $fetch($ENGINE, '/', null);
$check('the index lists wp-abilities/v1', in_array('wp-abilities/v1', $index['namespaces'] ?? [], true), json_encode($index['namespaces'] ?? []));
$check('the index describes the abilities route', isset($index['routes']['/wp-abilities/v1/abilities']), '');

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
