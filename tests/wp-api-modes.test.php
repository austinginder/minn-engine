<?php
/**
 * MINN_WP_API, Minn on its own: the test site served by `php -S` through a
 * router that defines the constant from a request header, so one run asks
 * each mode. A fixture must-use plugin (tests/fixtures/mu-plugins/
 * minn-test-wp-api.php) says where the site's plugins ran: under 'on'
 * everywhere; under 'auto' everywhere but public pages; under 'off' nowhere.
 * Public pages read the same in every mode, an extension route answers them
 * without the facade, Minn Admin's REST keeps answering, and the cron
 * option's events wait for a request that loads the code they belong to.
 *
 *   php tests/wp-api-modes.test.php
 */

declare(strict_types=1);

require __DIR__ . '/lib.php';

$pass = 0;
$fail = 0;
$check = static function (string $label, bool $ok, string $detail = '') use (&$pass, &$fail): void {
    if ($ok) {
        $pass++;
        echo "  ok   {$label}\n";
        return;
    }
    $fail++;
    echo "  FAIL {$label}" . ($detail !== '' ? " ({$detail})" : '') . "\n";
};

$site = minn_test_site_root();
$root = $site . '/public';
$entry = $root . '/index.php';
$content = $root . '/wp-content';
$wp = static fn (string $command): string => trim((string) shell_exec('cd ' . escapeshellarg($site . '/wp-reference') . " && wp {$command} 2>/dev/null"));
if (!is_file($entry)) {
    echo "SKIP: {$entry} is missing\n";
    exit(0);
}

echo "wp-api-modes suite: {$root}\n";
$dir = sys_get_temp_dir() . '/minn-wp-api-' . getmypid();
@mkdir($dir);
$router = "{$dir}/router.php";
// The router says whether the facade was loaded, in a header of its own, before the buffered page goes out.
file_put_contents($router, "<?php\nif (isset(\$_SERVER['HTTP_X_ZZ_WP_API'])) {\n    define('MINN_WP_API', \$_SERVER['HTTP_X_ZZ_WP_API']);\n}\nini_set('display_errors', '0');\n\$_SERVER['HTTPS'] = 'on';\n\$_SERVER['HTTP_HOST'] = 'minn.localhost';\n\$_SERVER['SERVER_NAME'] = 'minn.localhost';\n\$_SERVER['SERVER_PORT'] = '443';\n\$_SERVER['SCRIPT_NAME'] = '/index.php';\n\$_SERVER['PHP_SELF'] = '/index.php';\n\$_SERVER['SCRIPT_FILENAME'] = " . var_export($entry, true) . ";\nob_start();\nregister_shutdown_function(static function (): void {\n    if (!headers_sent()) {\n        header('X-Zz-Facade: ' . (function_exists('add_action') ? 'loaded' : 'absent'));\n    }\n});\nchdir(" . var_export($root, true) . ");\nrequire " . var_export($entry, true) . ";\n");
$probe = stream_socket_server('tcp://127.0.0.1:0');
$port = (int) substr((string) strrchr((string) stream_socket_get_name($probe, false), ':'), 1);
fclose($probe);
$server = proc_open([PHP_BINARY, '-S', "127.0.0.1:{$port}", $router], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, $root);

$fixture = __DIR__ . '/fixtures/mu-plugins/minn-test-wp-api.php';
$link = "{$content}/mu-plugins/minn-test-wp-api.php";
if (!is_link($link)) {
    symlink($fixture, $link);
}
$extensions = $wp('option get minn_active_extensions');
$wp("option update minn_active_extensions '[\"minn-block-visibility\",\"minn-test-seams\"]'");
register_shutdown_function(static function () use ($server, $dir, $link, $wp, $extensions): void {
    proc_terminate($server);
    @unlink($link);
    $wp('option update minn_active_extensions ' . escapeshellarg($extensions !== '' ? $extensions : '["minn-block-visibility"]'));
    $wp('cron event delete zz_wp_api_event');
    $wp('option delete zz_wp_api_event_ran');
    @unlink("{$dir}/router.php");
    @rmdir($dir);
});
usleep(700000);

/** @return array{status: int, headers: array<string, string>, body: string} */
$get = static function (string $mode, string $path, array $headers = [], ?string $post = null) use ($port): array {
    $lines = $mode === '' ? $headers : array_merge(["X-Zz-Wp-Api: {$mode}"], $headers);
    $http = ['ignore_errors' => true, 'timeout' => 30, 'follow_location' => 0, 'header' => implode("\r\n", $lines)];
    if ($post !== null) {
        $http += ['method' => 'POST', 'content' => $post];
        $http['header'] .= "\r\nContent-Type: application/x-www-form-urlencoded";
    }
    $body = (string) @file_get_contents("http://127.0.0.1:{$port}{$path}", false, stream_context_create(['http' => $http]));
    $status = 0;
    $seen = [];
    foreach ($http_response_header ?? [] as $line) {
        if (preg_match('#^HTTP/\S+ (\d+)#', $line, $m) === 1) {
            $status = (int) $m[1];
        } elseif (str_contains($line, ':')) {
            [$name, $value] = explode(':', $line, 2);
            $seen[strtolower(trim($name))] = trim($value);
        }
    }
    return ['status' => $status, 'headers' => $seen, 'body' => $body];
};
$marked = static fn (array $r): bool => str_contains($r['body'], '<!-- zz-wp-api footer -->');
$unmarked = static fn (array $r): string => str_replace("<!-- zz-wp-api footer -->\n", '', $r['body']);
// The bar's key is new on every request; nothing else in a page is.
$keyless = static fn (string $body): string => (string) preg_replace(['/"key":"[A-Za-z0-9]{20}"/', '/data-minn-bar="[A-Za-z0-9]{20}"/'], ['"key":"K"', 'data-minn-bar="K"'], $body);
$modes = ['on', 'auto', 'off'];

// 1. A themed page: the site's plugins run under 'on' alone, and the page is otherwise the same in every mode.
$themed = [];
foreach (array_merge([''], $modes) as $mode) {
    $themed[$mode] = $get($mode, '/');
}
if ($themed['on']['status'] !== 200) {
    echo "SKIP: the test site did not answer under php -S ({$themed['on']['status']})\n";
    exit(0);
}
// The server's Date header ticks over between two requests now and then; everything else must match.
$undated = static fn (array $r): array => ['headers' => array_diff_key($r['headers'], ['date' => true])] + $r;
$check('no MINN_WP_API is on: the site\'s code runs on a public page', $marked($themed['']) && $undated($themed['']) == $undated($themed['on']));
$check('on: the must-use plugin runs on a themed page', $marked($themed['on']));
$check('auto: the must-use plugin does not run on a themed page', !$marked($themed['auto']));
$check('off: the must-use plugin does not run on a themed page', !$marked($themed['off']));
$check('a themed page is the same in every mode, the plugin\'s own output aside', $unmarked($themed['on']) === $themed['auto']['body'] && $themed['auto']['body'] === $themed['off']['body'] && $themed['on']['headers']['content-type'] === $themed['off']['headers']['content-type']);
$check('a themed page still draws through the facade in auto', ($themed['auto']['headers']['x-zz-facade'] ?? '') === 'loaded');
$odd = $get('banana', '/');
$check('a value the engine does not know boots as on', $marked($odd));

// 2. An extension route: the same page in every mode, and no facade under auto and off.
$routed = [];
foreach ($modes as $mode) {
    $routed[$mode] = $get($mode, '/seam-probe/hello-there/');
}
$check('an extension route answers in every mode', str_contains($routed['auto']['body'], 'data-name="hello-there">reader-0') && $routed['on']['body'] === $routed['auto']['body'] && $routed['auto']['body'] === $routed['off']['body'], substr($routed['auto']['body'], 0, 200));
$check('on: the extension route boots the facade, as always', ($routed['on']['headers']['x-zz-facade'] ?? '') === 'loaded');
$check('auto: the extension route answers with no facade loaded', ($routed['auto']['headers']['x-zz-facade'] ?? '') === 'absent');
$check('off: the extension route answers with no facade loaded', ($routed['off']['headers']['x-zz-facade'] ?? '') === 'absent');
$check('an engine route keeps its path in auto: the extension\'s shadow never answers', !str_contains($get('auto', '/minn-admin/login')['body'], 'seam-shadow'));
$missed = $get('auto', '/seam-probe/UPPER/');
$check('a path no extension route takes falls through to the themed front in auto', !str_contains($missed['body'], 'seam-route') && ($missed['headers']['x-zz-facade'] ?? '') === 'loaded');

// 3. Signed in: the Minn bar on both kinds of page, the same in every mode.
$mint = (array) json_decode($wp('eval-file ' . escapeshellarg(__DIR__ . '/tools/mint-session.php') . ' 1'), true);
$cookie = ['Cookie: ' . ($mint['cookie_name'] ?? '') . '=' . ($mint['cookie'] ?? '')];
$signed = [];
foreach ($modes as $mode) {
    $signed[$mode] = [$keyless($unmarked($get($mode, '/', $cookie))), $keyless($get($mode, '/seam-probe/hello-there/', $cookie)['body'])];
}
$check('signed in, the themed page carries the bar', str_contains($signed['auto'][0], 'minn-cornerbar'));
$check('signed in, the extension page carries the bar and the reader', str_contains($signed['auto'][1], 'minn-cornerbar') && str_contains($signed['auto'][1], 'reader-1'));
$check('signed in, both pages are the same in every mode', $signed['on'] === $signed['auto'] && $signed['auto'] === $signed['off']);

// 4. The surfaces behind the pages: the site's code under on and auto, not under off; Minn Admin's REST in all three.
foreach ($modes as $mode) {
    $runs = $mode !== 'off';
    $ajax = $get($mode, '/wp-admin/admin-ajax.php', [], 'action=zz_wp_api');
    $check("{$mode}: admin-ajax " . ($runs ? 'runs' : 'does not run') . ' the plugin\'s action', ($ajax['body'] === 'zz-wp-api ajax') === $runs, $ajax['body']);
    $rest = $get($mode, '/wp-json/zz-wp-api/v1/ping');
    $check("{$mode}: the plugin's REST route " . ($runs ? 'answers' : 'is not there'), $runs ? $rest['body'] === '"pong"' : $rest['status'] === 404, "{$rest['status']} {$rest['body']}");
    $posted = $get($mode, '/', [], 'zz=1');
    $check("{$mode}: a POST to a public page " . ($runs ? 'runs' : 'does not run') . ' the site\'s plugins', $marked($posted) === $runs);
    $overview = $get($mode, '/wp-json/minn-admin/v1/overview', array_merge($cookie, ['X-WP-Nonce: ' . ($mint['nonce'] ?? '')]));
    $check("{$mode}: Minn Admin's REST answers its signed-in caller", $overview['status'] === 200, (string) $overview['status']);
}

// 5. Scheduled events: a public page under auto leaves the cron option's events for a request that loads their code.
// The reference's WP-CLI would spawn wp-cron.php on the live test site (where the fixture is linked too), which would
// run the event first; these calls keep it from spawning.
$wp = static fn (string $command): string => trim((string) shell_exec('cd ' . escapeshellarg($site . '/wp-reference') . ' && wp --exec=' . escapeshellarg("define('DISABLE_WP_CRON', true);") . " {$command} 2>/dev/null"));
$wp('option delete zz_wp_api_event_ran');
$wp('cron event schedule zz_wp_api_event now');
$get('auto', '/');
usleep(500000);
$left = [$wp('option get zz_wp_api_event_ran'), $wp("eval 'echo wp_next_scheduled(\"zz_wp_api_event\") ? \"yes\" : \"no\";'")];
$check('auto: a public page leaves a due plugin event scheduled', $left === ['', 'yes'], json_encode($left));
$get('auto', '/wp-cron.php');
$ran = '';
for ($i = 0; $i < 10 && $ran === ''; $i++) {
    usleep(300000);
    $ran = $wp('option get zz_wp_api_event_ran');
}
$check('auto: wp-cron.php runs it, with the plugin loaded', $ran !== '' && $wp("eval 'echo wp_next_scheduled(\"zz_wp_api_event\") ? \"yes\" : \"no\";'") === 'no');

// 5b. Minn Admin's System view: under auto with plugins active the runtime row warns, since none of them decides who
// reads a public page (security sweep 2026-11-12, finding 2.9); under on it passes.
$statusOf = static function (mixed $node) use (&$statusOf): ?string {
    if (!is_array($node)) {
        return null;
    }
    if (($node['key'] ?? null) === 'wp_api' && isset($node['status'])) {
        return (string) $node['status'];
    }
    foreach ($node as $child) {
        $found = $statusOf($child);
        if ($found !== null) {
            return $found;
        }
    }
    return null;
};
$rows = [];
foreach (['on', 'auto'] as $mode) {
    $rows[$mode] = $statusOf(json_decode($get($mode, '/wp-json/minn-admin/v1/system', array_merge($cookie, ['X-WP-Nonce: ' . ($mint['nonce'] ?? '')]))['body'], true));
}
$check('the System view warns under auto while plugins are active, and passes under on', $rows === ['on' => 'pass', 'auto' => 'warn'], json_encode($rows));

// 6. Minn Admin's maintenance mode holds every mode: a visitor meets the 503 on a themed page, an extension page and
// REST, where the reference holds all three; a signed-in editor still reaches the extension page (security sweep
// 2026-11-12: the spared lane and extension routes answered, and the engine took Minn Admin's REST hold off).
$wp('option update minn_admin_maintenance 1');
register_shutdown_function(static fn () => $wp('option delete minn_admin_maintenance'));
foreach ($modes as $mode) {
    $held = [$get($mode, '/')['status'], $get($mode, '/seam-probe/hello-there/')['status'], $get($mode, '/wp-json/wp/v2/posts?per_page=1')['status']];
    $check("{$mode}: under maintenance a visitor meets the hold on a themed page, an extension page and REST", $held === [503, 503, 503], json_encode($held));
    $editor = $get($mode, '/seam-probe/hello-there/', $cookie);
    $check("{$mode}: under maintenance a signed-in editor still reaches the extension page", $editor['status'] === 200 && str_contains($editor['body'], 'reader-1'), (string) $editor['status']);
}
// Fix-delta review: the option means nothing without Minn Admin, so with the plugin inactive an extension page
// answers as always (no hold, and none to fall back on).
$wp('plugin deactivate minn-admin');
$inactive = $get('auto', '/seam-probe/hello-there/');
$wp('plugin activate minn-admin');
$check('with Minn Admin inactive, the maintenance option holds nothing back', $inactive['status'] === 200 && str_contains($inactive['body'], 'hello-there'), (string) $inactive['status']);
$wp('option delete minn_admin_maintenance');

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
