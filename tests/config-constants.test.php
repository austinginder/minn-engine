<?php
/**
 * wp-config.php's address constants: WP_HOME and WP_SITEURL, defined to
 * hosts other than the stored options, stand for those options on every
 * read, as on the reference. Each stack is served by `php -S` through a
 * router that defines the constants first (a staging copy or a local setup
 * does this in wp-config.php), and the addresses the REST API builds are
 * compared: the index's home and url, and a post's link and self link (its
 * stored guid stays as it was written).
 *
 *   php tests/config-constants.test.php
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
$stacks = ['engine' => [$site . '/public', $site . '/public/index.php'], 'reference' => [$site . '/wp-reference', $site . '/wp-reference/router.php']];
$servers = [];
$ports = [];
$dir = sys_get_temp_dir() . '/minn-config-constants-' . getmypid();
@mkdir($dir);
foreach ($stacks as $stack => [$root, $entry]) {
    if (!is_file($entry)) {
        echo "SKIP: {$entry} is missing\n";
        exit(0);
    }
    $router = "{$dir}/{$stack}.php";
    file_put_contents($router, "<?php\ndefine('WP_HOME', 'https://zz-home.example/');\ndefine('WP_SITEURL', 'https://zz-site.example/wp');\nini_set('display_errors', '0');\n\$_SERVER['HTTPS'] = 'on';\nchdir(" . var_export($root, true) . ");\n\$_SERVER['SCRIPT_FILENAME'] = " . var_export($entry, true) . ";\nrequire " . var_export($entry, true) . ";\n");
    $probe = stream_socket_server('tcp://127.0.0.1:0');
    $ports[$stack] = (int) substr((string) strrchr((string) stream_socket_get_name($probe, false), ':'), 1);
    fclose($probe);
    $servers[$stack] = proc_open([PHP_BINARY, '-S', "127.0.0.1:{$ports[$stack]}", $router], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, $root);
}
register_shutdown_function(static function () use (&$servers, $dir): void {
    foreach ($servers as $server) {
        proc_terminate($server);
    }
    array_map('unlink', glob("{$dir}/*.php") ?: []);
    @rmdir($dir);
});
usleep(700000);

$read = static function (string $stack, string $route) use ($ports): array {
    $body = @file_get_contents("http://127.0.0.1:{$ports[$stack]}/?rest_route=" . rawurlencode($route), false, stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 30]]));
    return (array) json_decode((string) $body, true);
};
$addresses = static function (string $stack) use ($read): array {
    $index = $read($stack, '/');
    $post = $read($stack, '/wp/v2/posts/1');
    return ['home' => $index['home'] ?? null, 'url' => $index['url'] ?? null, 'link' => $post['link'] ?? null, 'self' => $post['_links']['self'][0]['href'] ?? null, 'guid' => $post['guid']['rendered'] ?? null];
};
$engine = $addresses('engine');
$reference = $addresses('reference');
if ($reference['home'] === null) {
    echo "SKIP: the reference did not answer under php -S\n";
    exit(0);
}
foreach (['home' => 'the index\'s home', 'url' => 'the index\'s url', 'link' => 'a post\'s link', 'self' => 'a post\'s self link', 'guid' => 'a post\'s stored guid'] as $key => $label) {
    $check("{$label} matches the reference", $engine[$key] === $reference[$key], json_encode(['engine' => $engine[$key], 'reference' => $reference[$key]], JSON_UNESCAPED_SLASHES));
}
$check('the constants stand for the options', $reference['home'] === 'https://zz-home.example' && $reference['url'] === 'https://zz-site.example/wp', json_encode($reference, JSON_UNESCAPED_SLASHES));

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
