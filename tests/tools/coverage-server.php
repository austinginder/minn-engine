<?php
/**
 * The router of the coverage web server (tests/tools/line-coverage.php
 * --parts=suites): Cove's web server, which loads no coverage driver, hands
 * the test site's requests here for the length of a measured run, and each
 * one is served by the engine in a phpdbg process of its own
 * (tests/tools/coverage-serve.php under tests/tools/coverage-run.php), its
 * answer passed back as it came. A file that exists in the site's webroot
 * and is not PHP is sent as it is, as Cove sends one.
 *
 * MINN_COVERAGE_DIR=<dir> PHP_CLI_SERVER_WORKERS=4 php -S 127.0.0.1:<port> -t <site>/public \
 *     -d enable_post_data_reading=0 tests/tools/coverage-server.php
 */

$path = rawurldecode((string) parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH));
$file = $_SERVER['DOCUMENT_ROOT'] . $path;
if ($path !== '/' && !str_contains($path, '..') && is_file($file) && !str_ends_with(strtolower($path), '.php')) {
    return false;
}

$dir = (string) getenv('MINN_COVERAGE_DIR');
$tmp = tempnam(sys_get_temp_dir(), 'minn-cov-');
file_put_contents("{$tmp}.request", json_encode([
    'method' => $_SERVER['REQUEST_METHOD'],
    'uri' => $_SERVER['REQUEST_URI'],
    'headers' => getallheaders(),
    'body' => base64_encode((string) file_get_contents('php://input')),
]));
$tools = __DIR__;
$phpdbg = (string) (getenv('MINN_COVERAGE_PHPDBG') ?: 'phpdbg');
shell_exec('MINN_COVERAGE_DIR=' . escapeshellarg($dir) . ' ' . escapeshellarg($phpdbg) . ' -qrr -d memory_limit=2G '
    . escapeshellarg("{$tools}/coverage-run.php") . ' ' . escapeshellarg("{$tools}/coverage-serve.php") . ' '
    . escapeshellarg("{$tmp}.request") . ' ' . escapeshellarg("{$tmp}.response") . ' >/dev/null 2>&1');
$answer = json_decode((string) @file_get_contents("{$tmp}.response"), true);
@unlink($tmp);
@unlink("{$tmp}.request");
@unlink("{$tmp}.response");
if (!is_array($answer)) {
    http_response_code(500);
    echo 'the coverage server got no answer from the engine';
    return true;
}
http_response_code((int) $answer['status']);
header_remove('X-Powered-By');
$seen = [];
foreach ((array) $answer['headers'] as $line) {
    $name = strtolower(trim((string) strstr((string) $line, ':', true)));
    header((string) $line, !isset($seen[$name]));
    $seen[$name] = true;
}
foreach ((array) $answer['cookies'] as $line) {
    header('Set-Cookie: ' . $line, false);
}
echo base64_decode((string) $answer['body']);
return true;
