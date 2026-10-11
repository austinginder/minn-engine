<?php
/**
 * Captures the reference's debug mode as data for
 * contracts/fixtures/debug-mode.json: what its boot leaves PHP's error
 * settings as, for each combination of debug constants and request kinds.
 * A constant can't change inside one process, so each case is its own
 * request to the reference served by `php -S` with this file as the router.
 * The router boots the reference without wp-config.php's debug lines (it
 * reads the database settings and keys from that file), defines the case's
 * constants, starts PHP's settings from distinct values, and reports them
 * after the boot. The unit suite replays the result against
 * Runtime\DebugMode. Run it from anywhere:
 *
 *   php tests/tools/debug-mode-capture.php [path/to/wp-reference]
 */

if (PHP_SAPI === 'cli-server') {
    $reference = rtrim((string) getenv('MINN_DEBUG_REFERENCE'), '/') . '/';
    error_reporting(1);
    ini_set('display_errors', 'stderr');
    ini_set('log_errors', '0');
    ini_set('error_log', '/baseline.log');
    $config = (string) file_get_contents($reference . 'wp-config.php');
    preg_match_all("/define\(\s*'((?:DB_|AUTH|SECURE_AUTH|LOGGED_IN|NONCE)\w*)',\s*'([^']*)'\s*\)/", $config, $found, PREG_SET_ORDER);
    foreach ($found as [, $name, $value]) {
        define($name, $value);
    }
    preg_match("/table_prefix\s*=\s*'([^']+)'/", $config, $prefix);
    $table_prefix = $prefix[1];
    define('ABSPATH', $reference);
    foreach ((array) json_decode((string) ($_GET['constants'] ?? '{}'), true) as $name => $value) {
        define($name, $value);
    }
    require ABSPATH . 'wp-settings.php';
    $settings = ['error_reporting' => error_reporting(), 'display_errors' => ini_get('display_errors'), 'log_errors' => ini_get('log_errors'), 'error_log' => str_replace(ABSPATH, '{root}/', (string) ini_get('error_log'))];
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    echo json_encode($settings, JSON_UNESCAPED_SLASHES);
    return true;
}

$reference = rtrim($argv[1] ?? getenv('HOME') . '/Cove/Sites/minn.localhost/wp-reference', '/');
$cases = [
    'none' => [[], []],
    'debug' => [['WP_DEBUG' => true], []],
    'debug "1"' => [['WP_DEBUG' => '1'], []],
    'debug "false" (a string, so on)' => [['WP_DEBUG' => 'false'], []],
    'debug 0' => [['WP_DEBUG' => 0], []],
    'debug "", log true' => [['WP_DEBUG' => '', 'WP_DEBUG_LOG' => true], []],
    'debug, display true' => [['WP_DEBUG' => true, 'WP_DEBUG_DISPLAY' => true], []],
    'debug, display false' => [['WP_DEBUG' => true, 'WP_DEBUG_DISPLAY' => false], []],
    'debug, display null' => [['WP_DEBUG' => true, 'WP_DEBUG_DISPLAY' => null], []],
    'debug, display 0' => [['WP_DEBUG' => true, 'WP_DEBUG_DISPLAY' => 0], []],
    'debug, display "0"' => [['WP_DEBUG' => true, 'WP_DEBUG_DISPLAY' => '0'], []],
    'debug, display ""' => [['WP_DEBUG' => true, 'WP_DEBUG_DISPLAY' => ''], []],
    'debug, display "false"' => [['WP_DEBUG' => true, 'WP_DEBUG_DISPLAY' => 'false'], []],
    'debug, display 2' => [['WP_DEBUG' => true, 'WP_DEBUG_DISPLAY' => 2], []],
    'debug, log true' => [['WP_DEBUG' => true, 'WP_DEBUG_LOG' => true], []],
    'debug, log 1' => [['WP_DEBUG' => true, 'WP_DEBUG_LOG' => 1], []],
    'debug, log 1.0' => [['WP_DEBUG' => true, 'WP_DEBUG_LOG' => 1.0], []],
    'debug, log 2' => [['WP_DEBUG' => true, 'WP_DEBUG_LOG' => 2], []],
    'debug, log "1"' => [['WP_DEBUG' => true, 'WP_DEBUG_LOG' => '1'], []],
    'debug, log "true"' => [['WP_DEBUG' => true, 'WP_DEBUG_LOG' => 'true'], []],
    'debug, log "TRUE"' => [['WP_DEBUG' => true, 'WP_DEBUG_LOG' => 'TRUE'], []],
    'debug, log " true"' => [['WP_DEBUG' => true, 'WP_DEBUG_LOG' => ' true'], []],
    'debug, log a path' => [['WP_DEBUG' => true, 'WP_DEBUG_LOG' => '/zz/minn-debug.log'], []],
    'debug, log a relative path' => [['WP_DEBUG' => true, 'WP_DEBUG_LOG' => 'zz-debug.log'], []],
    'debug, log ""' => [['WP_DEBUG' => true, 'WP_DEBUG_LOG' => ''], []],
    'debug, log "0"' => [['WP_DEBUG' => true, 'WP_DEBUG_LOG' => '0'], []],
    'debug, log "false" (a path)' => [['WP_DEBUG' => true, 'WP_DEBUG_LOG' => 'false'], []],
    'debug, log false' => [['WP_DEBUG' => true, 'WP_DEBUG_LOG' => false], []],
    'debug, log 0' => [['WP_DEBUG' => true, 'WP_DEBUG_LOG' => 0], []],
    'debug, log true, another content folder' => [['WP_DEBUG' => true, 'WP_DEBUG_LOG' => true, 'WP_CONTENT_DIR' => '/zz/content'], []],
    'debug, display false, log true' => [['WP_DEBUG' => true, 'WP_DEBUG_DISPLAY' => false, 'WP_DEBUG_LOG' => true], []],
    'no debug, display true, log true' => [['WP_DEBUG' => false, 'WP_DEBUG_DISPLAY' => true, 'WP_DEBUG_LOG' => true], []],
    'debug, ajax' => [['WP_DEBUG' => true, 'DOING_AJAX' => true], []],
    'debug, ajax false' => [['WP_DEBUG' => true, 'DOING_AJAX' => false], []],
    'debug, display true, ajax' => [['WP_DEBUG' => true, 'WP_DEBUG_DISPLAY' => true, 'DOING_AJAX' => true], []],
    'debug, display null, ajax' => [['WP_DEBUG' => true, 'WP_DEBUG_DISPLAY' => null, 'DOING_AJAX' => true], []],
    'debug, ajax, log true' => [['WP_DEBUG' => true, 'DOING_AJAX' => true, 'WP_DEBUG_LOG' => true], []],
    'no debug, ajax' => [['DOING_AJAX' => true], []],
    'debug, XML-RPC' => [['WP_DEBUG' => true, 'XMLRPC_REQUEST' => true], []],
    'debug, XML-RPC false' => [['WP_DEBUG' => true, 'XMLRPC_REQUEST' => false], []],
    'debug, REST' => [['WP_DEBUG' => true, 'REST_REQUEST' => true], []],
    'debug, REST false' => [['WP_DEBUG' => true, 'REST_REQUEST' => false], []],
    'debug, ms-files' => [['WP_DEBUG' => true, 'MS_FILES_REQUEST' => true], []],
    'debug, ms-files false' => [['WP_DEBUG' => true, 'MS_FILES_REQUEST' => false], []],
    'debug, installing' => [['WP_DEBUG' => true, 'WP_INSTALLING' => true], []],
    'debug, installing false' => [['WP_DEBUG' => true, 'WP_INSTALLING' => false], []],
    'debug, asks for JSON' => [['WP_DEBUG' => true], ['HTTP_ACCEPT' => 'application/json']],
    'debug, asks for HTML or JSON' => [['WP_DEBUG' => true], ['HTTP_ACCEPT' => 'text/html, application/json']],
    'debug, asks for a JSON vendor type' => [['WP_DEBUG' => true], ['HTTP_ACCEPT' => 'application/vnd.api+json']],
    'debug, sends JSON' => [['WP_DEBUG' => true], ['CONTENT_TYPE' => 'application/json']],
    'debug, sends JSON with a charset' => [['WP_DEBUG' => true], ['CONTENT_TYPE' => 'application/json; charset=utf-8']],
    'debug, asks for XML' => [['WP_DEBUG' => true], ['HTTP_ACCEPT' => 'text/xml']],
    'debug, sends XML' => [['WP_DEBUG' => true], ['CONTENT_TYPE' => 'text/xml']],
    'debug, a JSONP request' => [['WP_DEBUG' => true], ['_jsonp' => 'callback']],
    'debug, a REST route in the query' => [['WP_DEBUG' => true], ['rest_route' => '/']],
];

$probe = stream_socket_server('tcp://127.0.0.1:0');
$port = (int) substr(strrchr((string) stream_socket_get_name($probe, false), ':'), 1);
fclose($probe);
$server = proc_open([PHP_BINARY, '-S', "127.0.0.1:{$port}", __FILE__], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, $reference, ['MINN_DEBUG_REFERENCE' => $reference] + getenv());
usleep(500000);
$rows = [];
foreach ($cases as $label => [$constants, $request]) {
    $query = ['constants' => json_encode($constants)];
    $headers = [];
    foreach ($request as $name => $value) {
        if ($name === 'HTTP_ACCEPT') {
            $headers[] = "Accept: {$value}";
        } elseif ($name === 'CONTENT_TYPE') {
            $headers[] = "Content-Type: {$value}";
        } else {
            $query[$name] = $value;
        }
    }
    $context = stream_context_create(['http' => ['header' => implode("\r\n", $headers), 'ignore_errors' => true, 'timeout' => 30]]);
    $body = (string) file_get_contents("http://127.0.0.1:{$port}/?" . http_build_query($query), false, $context);
    $settings = json_decode($body, true);
    if (!is_array($settings)) {
        proc_terminate($server);
        fwrite(STDERR, "{$label}: the reference answered " . substr($body, 0, 200) . "\n");
        exit(1);
    }
    $rows[] = ['label' => $label, 'constants' => (object) $constants, 'request' => (object) $request, 'settings' => $settings];
}
proc_terminate($server);
$out = [
    'about' => 'What the reference\'s boot leaves PHP\'s error settings as (wp_debug_mode()), for each combination of debug constants and request kinds. Each case starts from error_reporting 1, display_errors stderr, log_errors 0 and error_log /baseline.log; {root} is the WordPress folder. Captured by tests/tools/debug-mode-capture.php; replayed by tests/unit/debug-mode.php.',
    'baseline' => ['error_reporting' => 1, 'display_errors' => 'stderr', 'log_errors' => '0', 'error_log' => '/baseline.log'],
    'cases' => $rows,
];
file_put_contents(dirname(__DIR__, 2) . '/contracts/fixtures/debug-mode.json', json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
echo count($rows), " cases written to contracts/fixtures/debug-mode.json\n";
