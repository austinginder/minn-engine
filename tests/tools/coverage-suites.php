<?php
/**
 * The suites part of tests/tools/line-coverage.php: the full run's suites,
 * with the test site served by the engine under phpdbg. Returns the lines
 * they ran, as the tool's other parts do.
 *
 * Cove's web server (Caddy, admin API on localhost:2019) is pointed at a
 * coverage server for the test site's host and put back afterwards, even
 * when the run is interrupted; should that ever fail, the original route
 * is left in the temp directory and `cove reload` restores it as well.
 */

return static function (string $root, string $dir, string $phpdbg, array $options, Closure $collect): array {
    $site = minn_test_site_root();
    $public = (string) realpath("{$site}/public");
    $host = (string) parse_url(minn_test_url(), PHP_URL_HOST);
    $admin = 'http://localhost:2019';

    preg_match('/for suite in ([a-z0-9 -]+); do/', (string) file_get_contents("{$root}/tests/run-all.sh"), $listed);
    $suites = isset($options['suites']) ? array_filter(explode(',', (string) $options['suites'])) : array_values(array_diff(preg_split('/\s+/', trim($listed[1] ?? '')), ['unit', 'api']));

    // WP-CLI, unpacked once and booted past its SAPI check.
    $realWp = '/opt/homebrew/bin/wp';
    $wpcli = sys_get_temp_dir() . '/minn-line-coverage-wp-cli';
    if (!is_file("{$wpcli}/boot.php")) {
        @mkdir($wpcli, 0755, true);
        copy((string) realpath($realWp), "{$wpcli}/wp-cli.phar");
        (new Phar("{$wpcli}/wp-cli.phar"))->extractTo("{$wpcli}/src", null, true);
        file_put_contents("{$wpcli}/boot.php", "<?php\ndefine('WP_CLI_ROOT', " . var_export("{$wpcli}/src/vendor/wp-cli/wp-cli", true) . ");\nrequire_once WP_CLI_ROOT . '/php/wp-cli.php';\n");
    }
    $bin = "{$dir}-bin";
    @mkdir($bin, 0755, true);
    copy("{$root}/tests/tools/coverage-wp.sh", "{$bin}/wp");
    chmod("{$bin}/wp", 0755);
    // The engine's own command (bin/minn: install, eject, preflight) runs under phpdbg too.
    copy("{$root}/tests/tools/coverage-php.sh", "{$bin}/php");
    chmod("{$bin}/php", 0755);

    // The coverage server, on a free port.
    $probe = stream_socket_server('tcp://127.0.0.1:0');
    $port = (int) substr((string) strrchr((string) stream_socket_get_name($probe, false), ':'), 1);
    fclose($probe);
    $env = ['MINN_COVERAGE_DIR' => $dir, 'MINN_COVERAGE_PHPDBG' => $phpdbg, 'PHP_CLI_SERVER_WORKERS' => '4'] + getenv();
    $server = proc_open([PHP_BINARY, '-d', 'enable_post_data_reading=0', '-S', "127.0.0.1:{$port}", '-t', $public, "{$root}/tests/tools/coverage-server.php"], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, $root, $env);
    for ($i = 0; $i < 50 && @fsockopen('127.0.0.1', $port) === false; $i++) {
        usleep(100000);
    }

    // Point the test site's host at it: its PHP routes become one proxy, its guards stay.
    $call = static function (string $method, string $path, ?string $body = null) use ($admin): string {
        $context = stream_context_create(['http' => ['method' => $method, 'header' => "Content-Type: application/json\r\n", 'content' => (string) $body, 'ignore_errors' => true, 'timeout' => 30]]);
        return (string) @file_get_contents($admin . $path, false, $context);
    };
    $servers = (array) json_decode($call('GET', '/config/apps/http/servers'), true);
    $found = null;
    foreach ($servers as $name => $config) {
        foreach ((array) ($config['routes'] ?? []) as $index => $route) {
            foreach ((array) ($route['match'] ?? []) as $match) {
                if (in_array($host, (array) ($match['host'] ?? []), true)) {
                    $found = [$name, $index, $route];
                }
            }
        }
    }
    if ($found === null) {
        proc_terminate($server);
        throw new RuntimeException("Cove's web server has no route for {$host}");
    }
    [$name, $index, $original] = $found;
    $saved = sys_get_temp_dir() . "/minn-line-coverage-route-{$host}.json";
    file_put_contents($saved, json_encode($original, JSON_UNESCAPED_SLASHES));
    $inner = (array) ($original['handle'][0]['routes'] ?? []);
    $guards = array_values(array_filter($inner, static fn (array $r): bool => isset($r['group']) || (($r['handle'][0]['handler'] ?? '') === 'vars')));
    $swapped = $original;
    $swapped['@id'] = 'minn-line-coverage';
    $swapped['handle'][0]['routes'] = [...$guards, ['handle' => [['handler' => 'reverse_proxy', 'upstreams' => [['dial' => "127.0.0.1:{$port}"]]]]]];
    $restored = false;
    $restore = static function () use (&$restored, $call, $original, $server, $saved, $port, $bin): void {
        if ($restored) {
            return;
        }
        $restored = true;
        $call('PATCH', '/id/minn-line-coverage', json_encode($original, JSON_UNESCAPED_SLASHES));
        // The server's workers are its children: end them with it.
        proc_terminate($server);
        shell_exec('pkill -f ' . escapeshellarg("127.0.0.1:{$port}") . ' 2>/dev/null');
        @unlink($saved);
        @unlink("{$bin}/wp");
        @unlink("{$bin}/php");
        @rmdir($bin);
        fwrite(STDERR, "Cove's web server put back\n");
    };
    register_shutdown_function($restore);
    if (function_exists('pcntl_signal')) {
        pcntl_async_signals(true);
        foreach ([SIGINT, SIGTERM, SIGHUP] as $signal) {
            pcntl_signal($signal, static function () use ($restore): void {
                $restore();
                exit(130);
            });
        }
    }
    $answer = $call('PATCH', "/config/apps/http/servers/{$name}/routes/{$index}", json_encode($swapped, JSON_UNESCAPED_SLASHES));
    if (trim($answer) !== '') {
        $restore();
        throw new RuntimeException("Cove's web server refused the coverage route: {$answer}");
    }

    $suiteEnv = [
        'PATH' => "{$bin}:" . getenv('PATH'),
        'MINN_COVERAGE_DIR' => $dir,
        'MINN_COVERAGE_PHPDBG' => $phpdbg,
        'MINN_COVERAGE_RUNNER' => "{$root}/tests/tools/coverage-run.php",
        'MINN_COVERAGE_WPCLI' => "{$wpcli}/boot.php",
        'MINN_COVERAGE_SITE_PUBLIC' => $public,
        'MINN_COVERAGE_REAL_WP' => $realWp,
        'MINN_COVERAGE_REAL_PHP' => PHP_BINARY,
        'MINN_TEST_KEEP_THEME' => '1',
        'MINN_TEST_KEEP_LOCALE' => '1',
    ] + getenv();
    try {
        foreach ($suites as $i => $suite) {
            $started = microtime(true);
            // The suite itself runs under plain php: phpdbg skips shutdown functions on exit(), and the suites clean up there.
            $process = proc_open([PHP_BINARY, "{$root}/tests/{$suite}.test.php"], [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, "{$root}/tests", $suiteEnv);
            $out = (string) stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            proc_close($process);
            preg_match('/(\d+) passed, (\d+) failed/', $out, $totals);
            fprintf(STDERR, "suites %d/%d %s: %s (%ds)\n", $i + 1, count($suites), $suite, $totals[0] ?? 'no totals', (int) (microtime(true) - $started));
        }
    } finally {
        $restore();
    }
    return $collect();
};
