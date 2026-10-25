<?php

declare(strict_types=1);

/**
 * Minn on its own: an extension's REST routes and its own tables. The
 * routes (Seams::rest) are judged by the engine's REST gate, as the
 * engine's routes are: the sign-in cookie counts only with its nonce, a
 * bad nonce is refused, a policy's capability holds, a namespace the engine
 * keeps is refused, and Seams::user() is the caller the gate accepted. The tables
 * (minn.json "tables") are created under the site's prefix on the first
 * request, grown when the version changes and never dropped or changed;
 * a declaration that names a table outside the extension's own skips it.
 * Drives a copy of tests/fixtures/extensions/minn-test-records on the test site.
 *
 *   php tests/extension-records.test.php
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
/** @return array{0: int, 1: mixed} status and decoded body */
$call = static function (string $method, string $route, array $headers = [], ?array $body = null) use ($ENGINE): array {
    $url = str_starts_with($route, '?') ? "{$ENGINE}/{$route}" : "{$ENGINE}/wp-json{$route}";
    $args = ['-sk', '-o', '-', '-w', "\n%{http_code}", '-X', $method, $url];
    foreach ($headers as $header) {
        array_push($args, '-H', $header);
    }
    if ($body !== null) {
        array_push($args, '-H', 'Content-Type: application/json', '--data-binary', json_encode($body));
    }
    $out = (string) shell_exec('/usr/bin/curl ' . implode(' ', array_map('escapeshellarg', $args)));
    $cut = strrpos($out, "\n");
    return [(int) substr($out, $cut + 1), json_decode(substr($out, 0, $cut), true)];
};
$mint = static function (int $user) use ($site): array {
    $minted = (array) json_decode((string) shell_exec('wp --path=' . escapeshellarg($site . '/wp-reference') . ' eval-file ' . escapeshellarg(__DIR__ . '/tools/mint-session.php') . " {$user} 2>/dev/null"), true);
    return ['cookie' => 'Cookie: wordpress_logged_in_' . md5(minn_test_url()) . '=' . ($minted['cookie'] ?? ''), 'nonce' => 'X-WP-Nonce: ' . ($minted['nonce'] ?? ''), 'raw' => (string) ($minted['nonce'] ?? '')];
};

echo "extension-records suite: {$ENGINE}\n";
$fixture = __DIR__ . '/fixtures/extensions/minn-test-records';
$copy = "{$site}/public/wp-content/plugins/minn-test-records";
$table = 'wp_minn_test_records_notes';
$manifest = (array) json_decode((string) file_get_contents("{$fixture}/minn.json"), true);
$write = static function (array $changes) use ($copy, $manifest): void {
    file_put_contents("{$copy}/minn.json", json_encode(array_replace($manifest, $changes), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
};
$columns = static fn (): array => array_filter(explode("\n", $wp("db query \"SHOW COLUMNS FROM {$table}\" --skip-column-names")), static fn (string $l): bool => $l !== '');
$columnNames = static fn (): array => array_map(static fn (string $l): string => (string) strtok($l, "\t"), $columns());
$recorded = static fn (): array => (array) json_decode($wp('option get minn_extension_tables') ?: '{}', true);
$sweep = static function () use ($wp, $copy, $table, $recorded): void {
    $wp("db query \"DROP TABLE IF EXISTS {$table}\"");
    $rest = $recorded();
    unset($rest['minn-test-records']);
    $rest === [] ? $wp('option delete minn_extension_tables') : $wp('option update minn_extension_tables ' . escapeshellarg(json_encode($rest)));
    exec('rm -rf ' . escapeshellarg($copy));
};
$sweep();
exec('cp -R ' . escapeshellarg($fixture) . ' ' . escapeshellarg($copy));
$active = $wp('option get minn_active_extensions') ?: '[]';
$wp('option update minn_active_extensions ' . escapeshellarg(json_encode([...(array) json_decode($active, true), 'minn-test-records'])));
register_shutdown_function(static function () use ($wp, $active, $sweep): void {
    $wp('option update minn_active_extensions ' . escapeshellarg($active));
    $sweep();
});

// 1. The first request creates the table and records it.
[$status, $who] = $call('GET', '/minn-test/v1/whoami');
$check('an extension REST route answers under /wp-json/', $status === 200 && $who === ['id' => 0, 'login' => ''], json_encode([$status, $who]));
$check('its table was created under the site prefix on that first request', in_array('user_id', $columnNames(), true) && in_array('body', $columnNames(), true), implode(',', $columnNames()));
$check('the table was made with the options table\'s collation', $wp("db query \"SELECT TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$table}'\" --skip-column-names") === $wp("db query \"SELECT TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'wp_options'\" --skip-column-names"));
$check('what was applied is recorded by slug: the version and a digest of the declaration', preg_match('/^0\.1\.0:[0-9a-f]{16}$/', (string) ($recorded()['minn-test-records'] ?? '')) === 1, json_encode($recorded()));

// 2. The REST gate judges the extension's routes as it judges the engine's.
$editor = $mint(2);
[, $who] = $call('GET', '/minn-test/v1/whoami', [$editor['cookie']]);
$check('a sign-in cookie without its nonce is nobody', ($who['id'] ?? null) === 0, json_encode($who));
[, $who] = $call('GET', '/minn-test/v1/whoami', [$editor['cookie'], $editor['nonce']]);
$check('with its nonce, user() is the signed-in caller', ($who['id'] ?? null) === 2 && ($who['login'] ?? '') === 'editor', json_encode($who));
[, $who] = $call('GET', '?rest_route=/minn-test/v1/whoami&_wpnonce=' . $editor['raw'], [$editor['cookie']]);
$check('the ?rest_route= form and a _wpnonce query count the same', ($who['id'] ?? null) === 2, json_encode($who));
[$status, $error] = $call('GET', '/minn-test/v1/whoami', [$editor['cookie'], 'X-WP-Nonce: 0123456789']);
$check('a nonce that does not verify is refused, even on a public route', $status === 403 && ($error['code'] ?? '') === 'rest_cookie_invalid_nonce', json_encode([$status, $error]));
[$status, $error] = $call('GET', '/minn-test/v1/notes');
$check('a signed-in route refuses nobody with the engine\'s 401', $status === 401 && ($error['code'] ?? '') === 'rest_not_logged_in', json_encode([$status, $error]));
[$status] = $call('GET', '/minn-test/v1/notes', [$editor['cookie']]);
$check('and refuses a cookie without its nonce the same way', $status === 401);
[$status, $made] = $call('POST', '/minn-test/v1/notes', [$editor['cookie'], $editor['nonce']], ['body' => 'zz first note']);
$check('a write lands in the extension\'s table for the caller', $status === 201 && ($made['body'] ?? '') === 'zz first note' && (int) ($made['id'] ?? 0) > 0, json_encode([$status, $made]));
[$status, $error] = $call('POST', '/minn-test/v1/notes', [$editor['cookie'], $editor['nonce']], ['body' => '']);
$check('a RestError the route throws is the answer', $status === 400 && ($error['code'] ?? '') === 'minn_test_body', json_encode([$status, $error]));
[, $notes] = $call('GET', '/minn-test/v1/notes', [$editor['cookie'], $editor['nonce']]);
$check('the caller reads back what it wrote', is_array($notes) && array_column($notes, 'body') === ['zz first note'], json_encode($notes));
$author = $mint(3);
[, $theirs] = $call('GET', '/minn-test/v1/notes', [$author['cookie'], $author['nonce']]);
$check('another user reads only their own rows', $theirs === [], json_encode($theirs));
[$status, $error] = $call('GET', '/minn-test/v1/admins', [$editor['cookie'], $editor['nonce']]);
$check('a capability the caller lacks is a 403', $status === 403 && ($error['code'] ?? '') === 'rest_forbidden', json_encode([$status, $error]));
$admin = $mint(1);
[$status] = $call('GET', '/minn-test/v1/admins', [$admin['cookie'], $admin['nonce']]);
$check('and one the caller holds lets the route answer', $status === 200);
[, $index] = $call('GET', '/');
$check('the REST index lists the extension\'s namespace', in_array('minn-test/v1', (array) ($index['namespaces'] ?? []), true), json_encode($index['namespaces'] ?? null));

// 3. A new version grows the table; nothing is dropped or applied twice.
$lines = $manifest['tables']['minn_test_records_notes'];
$grown = array_values(array_filter($lines, static fn (string $l): bool => !str_starts_with($l, 'body ')));
array_splice($grown, 2, 0, ['pinned tinyint(1) NOT NULL DEFAULT 0']);
$grown[] = 'KEY pinned (pinned)';
$write(['version' => '0.2.0', 'tables' => ['minn_test_records_notes' => $grown]]);
$call('GET', '/minn-test/v1/whoami');
$names = $columnNames();
$check('a later version\'s new column and key are added', in_array('pinned', $names, true) && str_contains($wp("db query \"SHOW INDEX FROM {$table}\" --skip-column-names"), 'pinned'), implode(',', $names));
$check('a column the new declaration leaves out stays, and so do its rows', in_array('body', $names, true) && $wp("db query \"SELECT body FROM {$table}\" --skip-column-names") === 'zz first note');
$check('the record moves to the new version', str_starts_with((string) ($recorded()['minn-test-records'] ?? ''), '0.2.0:'), json_encode($recorded()));
$wp("db query \"ALTER TABLE {$table} DROP INDEX pinned, DROP COLUMN pinned\"");
$call('GET', '/minn-test/v1/whoami');
$check('with nothing changed, a request applies nothing', !in_array('pinned', $columnNames(), true));
$write(['version' => '0.2.1', 'tables' => ['minn_test_records_notes' => $grown]]);
$call('GET', '/minn-test/v1/whoami');
$check('the next version applies the declaration again', in_array('pinned', $columnNames(), true));

// 4. A declaration outside the extension's own names skips it whole.
$before = $wp('db query "SHOW COLUMNS FROM wp_posts" --skip-column-names');
$write(['version' => '0.3.0', 'tables' => ['posts' => ['zz_hijack text']]]);
[$status, $error] = $call('GET', '/minn-test/v1/whoami');
$check('an extension that declares another table is skipped: its routes do not answer', $status === 404 && ($error['code'] ?? '') === 'rest_no_route', json_encode([$status, $error]));
$check('and the site\'s own table is untouched', $wp('db query "SHOW COLUMNS FROM wp_posts" --skip-column-names') === $before);
$check('nothing is recorded for a declaration that was refused', str_starts_with((string) ($recorded()['minn-test-records'] ?? ''), '0.2.1:'), json_encode($recorded()));

$write(['extension' => 'Minn\\Ext\\TestRecords\\ShadowExtension']);
[$status, $types] = $call('GET', '/wp/v2/types');
$check('a REST route in a namespace the engine keeps is refused: wp/v2/types answers as before', $status === 200 && !isset($types['shadow']) && isset($types['post']), json_encode([$status, array_keys((array) $types)]));
[$status] = $call('GET', '/minn-test/v1/whoami');
$check('and the extension that asked for it is skipped whole', $status === 404);

// 5. Switched off, the routes stop and the table stays.
$write([]);
$wp('option update minn_active_extensions ' . escapeshellarg($active));
[$status] = $call('GET', '/minn-test/v1/whoami');
$check('an inactive extension\'s REST routes do not answer', $status === 404);
$check('its table is never dropped', in_array('body', $columnNames(), true));

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
