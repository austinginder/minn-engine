<?php
/**
 * SQL statements per page on both stacks: the benchmark pages fetched over
 * HTTP, the server's own counters read before and after each through a
 * connection of this script's own, the fewest of several runs kept (other
 * connections only ever add). Prints a table; --json prints the figures.
 *
 * php tests/tools/query-count.php [--runs=5] [--json]
 */

require __DIR__ . '/../lib.php';

const PAGES = ['/', '/hello-world/', '/category/uncategorized/', '/wp-json/wp/v2/posts', '/?s=hello'];
const COUNTERS = ['Com_select', 'Com_stmt_execute', 'Questions'];

$options = getopt('', ['runs:', 'json', 'write']);
$runs = max(1, (int) ($options['runs'] ?? 5));
// The test site's database, as the engine connects to it (wp-config.php without its last line).
$config = str_replace("require_once ABSPATH . 'wp-settings.php';", '', (string) file_get_contents(minn_test_site_root() . '/public/wp-config.php'));
define('ABSPATH', minn_test_site_root() . '/public/');
eval('?>' . $config);
$db = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
$counters = static function () use ($db): array {
    $out = [];
    foreach ($db->query("SHOW GLOBAL STATUS WHERE Variable_name IN ('" . implode("','", COUNTERS) . "')")->fetch_all() as [$name, $value]) {
        $out[$name] = (int) $value;
    }
    return $out;
};
// What reading the counters costs by itself, taken off every figure.
$a = $counters();
$b = $counters();
$overhead = array_map(static fn (string $name): int => $b[$name] - $a[$name], array_combine(COUNTERS, COUNTERS));

$stacks = ['engine' => minn_test_url(), 'reference' => minn_test_reference_url()];
$figures = [];
foreach (PAGES as $page) {
    foreach ($stacks as $stack => $base) {
        $best = null;
        for ($i = 0; $i < $runs; $i++) {
            $before = $counters();
            minn_test_fetch($base . $page);
            $after = $counters();
            $delta = [];
            foreach (COUNTERS as $name) {
                $delta[$name] = max(0, $after[$name] - $before[$name] - $overhead[$name]);
            }
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
