<?php
/**
 * wp-config.php's constants, each stack served by `php -S` through a router
 * that defines them first (as a staging copy, a local setup, or a broken
 * database does). WP_HOME and WP_SITEURL, set to hosts other than the stored
 * options, stand for those options on every read, as on the reference: the
 * index's home and url, a post's link and self link (its stored guid stays as
 * written). And with DB_HOST unreachable, a db-error.php drop-in answers in
 * the stack's place: its own output, under the status it set (200 when it set
 * none), with the headers it sent. Without one the engine keeps its own 503
 * page, a difference on purpose (the reference's is a 500).
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
foreach (['none' => null, 'plain' => '<?php echo "ZZ drop-in says the database is away";', 'status' => '<?php header("HTTP/1.1 503 Service Unavailable"); header("Retry-After: 30"); echo "ZZ drop-in with its own status";'] as $case => $dropin) {
    @mkdir("{$dir}/{$case}");
    if ($dropin !== null) {
        file_put_contents("{$dir}/{$case}/db-error.php", $dropin . "\n");
    }
}
foreach ($stacks as $stack => [$root, $entry]) {
    if (!is_file($entry)) {
        echo "SKIP: {$entry} is missing\n";
        exit(0);
    }
    $router = "{$dir}/{$stack}.php";
    // ?zzoutage=<case> serves the stack with its database away and the case's folder as wp-content.
    file_put_contents($router, "<?php\nif (isset(\$_GET['zzoutage'])) {\n    define('DB_HOST', '127.0.0.1:1');\n    define('WP_CONTENT_DIR', " . var_export($dir, true) . " . '/' . preg_replace('/[^a-z]/', '', (string) \$_GET['zzoutage']));\n}\ndefine('WP_HOME', 'https://zz-home.example/');\ndefine('WP_SITEURL', 'https://zz-site.example/wp');\nini_set('display_errors', '0');\n\$_SERVER['HTTPS'] = 'on';\nchdir(" . var_export($root, true) . ");\n\$_SERVER['SCRIPT_FILENAME'] = " . var_export($entry, true) . ";\nrequire " . var_export($entry, true) . ";\n");
    $probe = stream_socket_server('tcp://127.0.0.1:0');
    $ports[$stack] = (int) substr((string) strrchr((string) stream_socket_get_name($probe, false), ':'), 1);
    fclose($probe);
    $servers[$stack] = proc_open([PHP_BINARY, '-S', "127.0.0.1:{$ports[$stack]}", $router], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, $root);
}
register_shutdown_function(static function () use (&$servers, $dir): void {
    foreach ($servers as $server) {
        proc_terminate($server);
    }
    // The stacks may log into a case's folder (debug.log): everything under the run's folder goes.
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
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

// The database away.
$outage = static function (string $stack, string $case) use ($ports): array {
    $context = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 30]]);
    $body = (string) @file_get_contents("http://127.0.0.1:{$ports[$stack]}/?zzoutage={$case}", false, $context);
    $status = 0;
    $retry = null;
    foreach ($http_response_header ?? [] as $line) {
        if (preg_match('#^HTTP/\S+ (\d+)#', $line, $m) === 1) {
            $status = (int) $m[1];
        }
        if (stripos($line, 'Retry-After:') === 0) {
            $retry = trim(substr($line, 12));
        }
    }
    return ['status' => $status, 'retry' => $retry, 'body' => $body];
};
foreach (['plain' => 'a db-error.php drop-in that only prints', 'status' => 'a db-error.php drop-in that sets its own status'] as $case => $label) {
    $engineSide = $outage('engine', $case);
    $referenceSide = $outage('reference', $case);
    $check("the database away, {$label}: what it answers matches the reference", $engineSide === $referenceSide, json_encode(['engine' => $engineSide, 'reference' => $referenceSide], JSON_UNESCAPED_SLASHES));
}
$own = $outage('engine', 'none');
$check('the database away without a drop-in: the engine\'s own 503 page', $own['status'] === 503 && $own['retry'] === '60' && str_contains($own['body'], 'Error establishing a database connection'), json_encode(['status' => $own['status'], 'retry' => $own['retry']]));

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
