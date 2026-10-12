<?php
/**
 * SQL statements per page on both stacks: the benchmark pages fetched over
 * HTTP with the server's general log on, and the statements counted from
 * it for the connections on the test site's database alone (a crawl or a
 * suite on another database shares the server's counters, never this),
 * the fewest of several runs kept. Prepared statements count as they run
 * (Execute), as the server's Com_select counts them. Prints a table;
 * --json prints the figures, --write keeps them in contracts/query-count.json.
 *
 * php tests/tools/query-count.php [--runs=5] [--json]
 */

require __DIR__ . '/../lib.php';

const PAGES = ['/', '/hello-world/', '/category/uncategorized/', '/wp-json/wp/v2/posts', '/?s=hello'];

$options = getopt('', ['runs:', 'json', 'write']);
$runs = max(1, (int) ($options['runs'] ?? 5));
// The test site's database, as the engine connects to it (wp-config.php without its last line).
$config = str_replace("require_once ABSPATH . 'wp-settings.php';", '', (string) file_get_contents(minn_test_site_root() . '/public/wp-config.php'));
define('ABSPATH', minn_test_site_root() . '/public/');
eval('?>' . $config);
$db = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
// The server's general log, pointed at a file of this run's own while a page is fetched; put back as it was after.
$was = $db->query('SELECT @@general_log, @@general_log_file, @@log_output')->fetch_row();
$log = sys_get_temp_dir() . '/minn-query-count-' . getmypid() . '.log';
register_shutdown_function(static function () use ($db, $was, $log): void {
    $db->query('SET GLOBAL general_log = 0');
    $db->query("SET GLOBAL general_log_file = '" . $db->real_escape_string((string) $was[1]) . "'");
    $db->query("SET GLOBAL log_output = '" . $db->real_escape_string((string) $was[2]) . "'");
    $db->query('SET GLOBAL general_log = ' . (int) $was[0]);
    @unlink($log);
});
$own = (int) $db->thread_id;
/** The statements one fetch ran on the site's database: SELECTs, prepared runs, all statements. */
$measure = static function (string $url) use ($db, $log, $own): array {
    @unlink($log);
    $db->query("SET GLOBAL log_output = 'FILE'");
    $db->query("SET GLOBAL general_log_file = '" . $db->real_escape_string($log) . "'");
    $db->query('SET GLOBAL general_log = 1');
    minn_test_fetch($url);
    $db->query('SET GLOBAL general_log = 0');
    $onSite = [];
    $counts = ['Com_select' => 0, 'Com_stmt_execute' => 0, 'Questions' => 0];
    $statements = [];
    foreach (file($log, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        // "[date time]\t<thread> <Command>\t<argument>"; a statement may run over several lines.
        if (preg_match('/^(?:\S+\s+\S+)?\s+(\d+)\s+(Connect|Init DB|Query|Execute|Prepare|Quit|Close stmt)\t(.*)$/', $line, $m) !== 1) {
            continue;
        }
        [, $thread, $command, $argument] = $m;
        if ($command === 'Connect' && preg_match('/ on (\S+) using /', $argument, $named) === 1) {
            $onSite[$thread] = $named[1] === DB_NAME;
        } elseif ($command === 'Init DB') {
            $onSite[$thread] = trim($argument) === DB_NAME;
        } elseif (($command === 'Query' || $command === 'Execute') && (int) $thread !== $own) {
            $statements[] = [$thread, $command, $argument];
        }
    }
    foreach ($statements as [$thread, $command, $argument]) {
        if (empty($onSite[$thread])) {
            continue;
        }
        $counts['Questions']++;
        $counts['Com_stmt_execute'] += $command === 'Execute' ? 1 : 0;
        $counts['Com_select'] += preg_match('/^\s*(\(\s*)?SELECT\b/i', $argument) === 1 ? 1 : 0;
    }
    return $counts;
};

$stacks = ['engine' => minn_test_url(), 'reference' => minn_test_reference_url()];
$figures = [];
foreach (PAGES as $page) {
    foreach ($stacks as $stack => $base) {
        $best = null;
        for ($i = 0; $i < $runs; $i++) {
            $delta = $measure($base . $page);
            $best = $best === null || $delta['Com_select'] < $best['Com_select'] ? $delta : $best;
        }
        $figures[$page][$stack] = $best;
    }
}

if (isset($options['write'])) {
    file_put_contents(dirname(__DIR__, 2) . '/contracts/query-count.json', json_encode(['measured' => gmdate('Y-m-d'), 'runs' => $runs, 'pages' => $figures], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
}
if (isset($options['json'])) {
    echo json_encode($figures, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
    exit(0);
}
printf("%-28s %18s %18s %18s\n", 'page', 'SELECT eng/ref', 'executes eng', 'questions eng/ref');
foreach ($figures as $page => $row) {
    printf("%-28s %8d / %-8d %18d %8d / %-8d\n", $page, $row['engine']['Com_select'], $row['reference']['Com_select'], $row['engine']['Com_stmt_execute'], $row['engine']['Questions'], $row['reference']['Questions']);
}
