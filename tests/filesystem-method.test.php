<?php

declare(strict_types=1);

/**
 * get_filesystem_method on a host shaped like Kinsta, compared with the
 * reference WordPress on the same site: PHP served over a web SAPI with
 * getmyuid() disabled and a root-owned auto_prepend_file, so the first
 * included file belongs to neither the site nor its code. The reference
 * answers direct there and so must the engine; before the fix the engine
 * compared against that prepend's owner, answered ftpext, and every plugin
 * and theme update stopped with no reason given. A plain server (no
 * prepend, getmyuid present) is the control. Both stacks are served by
 * `php -S` from a scratch folder; nothing is written to the site.
 *
 *   php tests/filesystem-method.test.php
 */

require_once __DIR__ . '/lib.php';

$pass = 0;
$fail = 0;
$check = static function (bool $ok, string $label, string $detail = '') use (&$pass, &$fail): void {
    if ($ok) {
        $pass++;
        echo "  ok   $label\n";
    } else {
        $fail++;
        echo "  FAIL $label" . ($detail === '' ? '' : ": $detail") . "\n";
    }
};

// An empty file root owns stands in for the host's prepend: empty, so it prints nothing.
$prepend = null;
foreach (array_merge(glob('/etc/*') ?: [], glob('/private/etc/*') ?: []) as $candidate) {
    if (is_file($candidate) && is_readable($candidate) && filesize($candidate) === 0 && fileowner($candidate) === 0) {
        $prepend = $candidate;
        break;
    }
}
if ($prepend === null) {
    echo "SKIP: no empty root-owned file to serve as the prepend\n";
    exit(0);
}
$siteRoot = minn_test_site_root();
if (!is_file($siteRoot . '/wp-reference/wp-load.php')) {
    echo "SKIP: no reference WordPress at {$siteRoot}/wp-reference\n";
    exit(0);
}

$docroot = sys_get_temp_dir() . '/minn-filesystem-method-' . getmypid();
@mkdir($docroot, 0755, true);
$host = (string) parse_url(minn_test_url(), PHP_URL_HOST);
// What each stack is asked: whether the host's shape held, then the method (default folder, and relaxed).
$ask = <<<'PHP'
$answer = static function (): array {
    remove_all_filters('filesystem_method');
    $first = get_included_files()[0] ?? '';
    return [
        'getmyuid' => function_exists('getmyuid'),
        'first_owner' => $first === '' ? null : fileowner($first),
        'method' => get_filesystem_method(),
        'relaxed' => get_filesystem_method([], false, true),
    ];
};
PHP;
$serverVars = '$_SERVER["HTTP_HOST"] = ' . var_export($host, true) . '; $_SERVER["SERVER_NAME"] = $_SERVER["HTTP_HOST"]; $_SERVER["HTTPS"] = "on";';
file_put_contents("{$docroot}/reference.php", "<?php\n{$serverVars}\ndefine('WP_USE_THEMES', false);\nrequire " . var_export($siteRoot . '/wp-reference/wp-load.php', true) . ";\nrequire_once ABSPATH . 'wp-admin/includes/file.php';\n{$ask}\nwhile (ob_get_level() > 0) { ob_end_clean(); }\necho json_encode(\$answer());\n");
file_put_contents("{$docroot}/engine.php", "<?php\n{$serverVars}\nob_start();\nrequire " . var_export(__DIR__ . '/tools/engine-runtime.php', true) . ";\n{$ask}\nwhile (ob_get_level() > 0) { ob_end_clean(); }\necho json_encode(\$answer());\n");

/** Serves the scratch folder with the given ini settings and asks both stacks; [reference, engine]. */
$serve = static function (array $ini) use ($docroot): array {
    $socket = stream_socket_server('tcp://127.0.0.1:0');
    $port = (int) substr((string) stream_socket_get_name($socket, false), strrpos((string) stream_socket_get_name($socket, false), ':') + 1);
    fclose($socket);
    $command = [PHP_BINARY];
    foreach ($ini as $key => $value) {
        $command[] = '-d';
        $command[] = "{$key}={$value}";
    }
    array_push($command, '-S', "127.0.0.1:{$port}", '-t', $docroot);
    $server = proc_open($command, [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
    $answers = [];
    try {
        for ($i = 0; $i < 50 && @fsockopen('127.0.0.1', $port) === false; $i++) {
            usleep(100000);
        }
        foreach (['reference', 'engine'] as $stack) {
            [$headers, $body] = minn_test_fetch("http://127.0.0.1:{$port}/{$stack}.php", 60);
            $decoded = json_decode($body, true);
            $answers[] = is_array($decoded) ? $decoded : ['status' => $headers['status'], 'error' => substr($body, 0, 300)];
        }
    } finally {
        proc_terminate($server);
        proc_close($server);
    }
    return $answers;
};

echo "A plain server (the control)\n";
[$reference, $engine] = $serve([]);
$check(($reference['getmyuid'] ?? null) === true && ($engine['getmyuid'] ?? null) === true, 'getmyuid() is there on both stacks', json_encode([$reference, $engine]));
$check(($reference['method'] ?? null) === 'direct', 'the reference answers direct', json_encode($reference));
$check(($engine['method'] ?? null) === ($reference['method'] ?? false) && ($engine['relaxed'] ?? null) === ($reference['relaxed'] ?? false), 'the engine answers as the reference', json_encode([$reference, $engine]));

echo "getmyuid() disabled and a root-owned prepend (Kinsta)\n";
[$reference, $engine] = $serve(['disable_functions' => 'getmyuid', 'auto_prepend_file' => $prepend]);
$check(($reference['getmyuid'] ?? null) === false && ($engine['getmyuid'] ?? null) === false, 'getmyuid() is gone on both stacks', json_encode([$reference, $engine]));
$check(($reference['first_owner'] ?? null) === 0 && ($engine['first_owner'] ?? null) === 0, 'the first included file is the root-owned prepend', json_encode([$reference, $engine]));
$check(($reference['method'] ?? null) === 'direct', 'the reference answers direct', json_encode($reference));
$check(($engine['method'] ?? null) === ($reference['method'] ?? false), 'the engine answers as the reference', json_encode([$reference, $engine]));
$check(($engine['relaxed'] ?? null) === ($reference['relaxed'] ?? false), 'relaxed ownership answers as the reference', json_encode([$reference, $engine]));

array_map('unlink', glob("{$docroot}/*") ?: []);
@rmdir($docroot);

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
