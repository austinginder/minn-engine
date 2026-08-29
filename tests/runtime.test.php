<?php
/**
 * Runtime suite: switches the fixture plugin tests/fixtures/runtime/
 * minn-test-plugin on through active_plugins (as a site owner would), then
 * reads the marks it leaves on real engine pages: lifecycle order, wp_head
 * output, enqueued assets, body_class, the_content, wp_footer. Restores
 * the option on exit.
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

$base = rtrim((string) $site->option('home'), '/');
$plugin = 'minn-test-plugin/minn-test-plugin.php';
$before = get_option('active_plugins');
$before = is_array($before) ? $before : [];
register_shutdown_function(static function () use ($before): void {
    update_option('active_plugins', $before);
});
update_option('active_plugins', array_values(array_unique(array_merge($before, [$plugin]))));

[$headers, $html] = minn_test_fetch($base . '/hello-world/');
$check('page renders', ($headers['status'] ?? 0) === 200, (string) ($headers['status'] ?? 'no status'));
$check('lifecycle order in wp_head', str_contains($html, '<meta name="minn-test-plugin" content="plugins_loaded,init,wp_loaded,template_redirect,wp_head">'), substr((string) strstr($html, 'minn-test-plugin" content='), 0, 120));
$check('wp_head mark precedes the theme stylesheet', strpos($html, 'name="minn-test-plugin"') < strpos($html, '-style-css'));
$check('enqueued style printed', preg_match("#<link rel='stylesheet' id='minn-test-plugin-css' href='" . preg_quote($base, '#') . "/wp-content/plugins/minn-test-plugin/probe\\.css\\?ver=1\\.0\\.0' media='all' />#", $html) === 1);
$check('footer script printed in the footer', preg_match('#<script id="minn-test-plugin-js" src="' . preg_quote($base, '#') . '/wp-content/plugins/minn-test-plugin/probe\.js\?ver=1\.0\.0"></script>#', $html) === 1 && strpos($html, 'id="minn-test-plugin-js"') > strpos($html, '</main>'), substr((string) strstr($html, 'minn-test-plugin-js"'), 0, 160));
$check('localized data before the script', str_contains($html, '<script id="minn-test-plugin-js-extra">' . "\n" . 'var minnTestPlugin = ' . json_encode(['home' => $base . '/', 'admin' => '']) . ';'), substr((string) strstr($html, 'minn-test-plugin-js-extra'), 0, 160));
$check('body_class filter', preg_match('/<body class="[^"]*\bminn-test-plugin-body\b/', $html) === 1);
$check('the_content filter on the post', str_contains($html, '<p class="minn-test-plugin-content">' . htmlspecialchars((string) get_option('blogname'), ENT_QUOTES) . '</p>'));
$check('wp_footer output', str_contains($html, '<!-- minn-test-plugin footer: Twenty Twenty-Five -->'));
$check('footer output before </body>', strpos($html, 'minn-test-plugin footer') < strpos($html, '</body>'));

[, $home] = minn_test_fetch($base . '/');
$check('home page carries the marks too', str_contains($home, 'minn-test-plugin-body') && str_contains($home, 'minn-test-plugin footer'));

[, $feed] = minn_test_fetch($base . '/feed/');
$check('feed content runs the_content', str_contains($feed, 'minn-test-plugin-content'));

// The plugin's REST routes: engine and reference answer from the same code on the same database.
$reference = 'http://127.0.0.1:8123';
$referenceUp = @file_get_contents($reference . '/?rest_route=/', false, stream_context_create(['http' => ['timeout' => 3, 'ignore_errors' => true]])) !== false;
[$h, $engineEcho] = minn_test_fetch($base . '/wp-json/minn-test/v1/echo?word=hi&n=2');
$check('plugin route answers on the engine', ($h['status'] ?? 0) === 200 && json_decode($engineEcho, true) === ['echo' => 'hi', 'n' => 2, 'user' => 0, 'title' => 'Hello world!'], substr($engineEcho, 0, 200));
[$h, $missing] = minn_test_fetch($base . '/wp-json/minn-test/v1/echo');
$check('missing required parameter is refused as the reference refuses it', ($h['status'] ?? 0) === 400 && (json_decode($missing, true)['code'] ?? '') === 'rest_missing_callback_param', substr($missing, 0, 200));
[$h, $item] = minn_test_fetch($base . '/wp-json/minn-test/v1/items/1');
$check('url parameter route', ($h['status'] ?? 0) === 200 && json_decode($item, true) === ['id' => 1, 'exists' => true], substr($item, 0, 200));
[$h, $denied] = minn_test_fetch($base . '/?rest_route=/minn-test/v1/echo&_method=POST');
[, $index] = minn_test_fetch($base . '/wp-json/');
$indexData = json_decode($index, true);
$check('index lists the plugin namespace and route', in_array('minn-test/v1', $indexData['namespaces'] ?? [], true) && isset($indexData['routes']['/minn-test/v1/echo']), substr($index, 0, 120));
if ($referenceUp) {
    foreach (['/minn-test/v1/echo?word=hi&n=2', '/minn-test/v1/echo', '/minn-test/v1/echo?word=hi&n=9', '/minn-test/v1/items/1', '/minn-test/v1/items/999999', '/minn-test/v1/nope', '/minn-test/v1'] as $path) {
        [$eh, $eb] = minn_test_fetch($base . '/wp-json' . $path);
        [$rh, $rb] = minn_test_fetch($reference . '/?rest_route=' . rawurlencode(strtok($path, '?')) . (str_contains($path, '?') ? '&' . substr($path, strpos($path, '?') + 1) : ''));
        // Hosts are masked after decoding: escaped slashes in the raw JSON would hide them from a string replace.
        $normalise = static function (string $body) use ($reference, $base) {
            $data = json_decode($body, true);
            array_walk_recursive($data, static function (&$v) use ($reference, $base): void {
                if (is_string($v)) {
                    $v = str_replace([$reference, $base], '{home}', $v);
                }
            });
            return $data;
        };
        $diff = minn_test_diff($normalise($eb), $normalise($rb));
        $check("reference agrees on {$path}", ($eh['status'] ?? 0) === ($rh['status'] ?? 1) && $diff === null, ($eh['status'] ?? 0) . ' vs ' . ($rh['status'] ?? 1) . ' ' . ($diff ?? ''));
    }
} else {
    echo "  --  reference not running on :8123; REST parity rows skipped\n";
}

update_option('active_plugins', $before);
[, $off] = minn_test_fetch($base . '/hello-world/');
$check('deactivated: no marks', !str_contains($off, 'minn-test-plugin'));

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
