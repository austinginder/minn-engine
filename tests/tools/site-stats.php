<?php

declare(strict_types=1);

/**
 * Writes site/minn-site/content/stats.json: the check and suite counts the
 * marketing front page quotes (the class count it reads from the theme's
 * content/api.json on its own). Suites are counted from run-all.sh; checks are
 * summed from the "N passed, M failed" line every suite prints, so feed it the
 * log of a full green run.
 *
 *   ./tests/run-all.sh | tee /tmp/run-all.log
 *   php tests/tools/site-stats.php /tmp/run-all.log
 *   php tests/tools/site-stats.php --checks=17000    when no log is at hand
 */

$root = dirname(__DIR__, 2);
$target = "{$root}/site/minn-site/content/stats.json";
$options = getopt('', ['checks:'], $rest);
$log = $argv[$rest] ?? null;

$runAll = (string) file_get_contents("{$root}/tests/run-all.sh");
$suites = preg_match('/^for suite in ([^;]+);/m', $runAll, $m) === 1 ? count(preg_split('/\s+/', trim($m[1]))) : 0;
$suites += preg_match_all('/^\s*(?:\S+=\S+\s+)*node browser\/\S+\.test\.js/m', $runAll);

$previous = is_file($target) ? json_decode((string) file_get_contents($target), true) : null;
$checks = is_array($previous) ? (int) ($previous['checks'] ?? 0) : 0;
if (isset($options['checks'])) {
    $checks = (int) $options['checks'];
} elseif ($log !== null) {
    $text = (string) @file_get_contents($log);
    preg_match_all('/^\s*(\d+) passed, (\d+) failed/m', $text, $totals, PREG_SET_ORDER);
    $failed = array_sum(array_map(static fn (array $row): int => (int) $row[2], $totals));
    if ($totals === [] || $failed > 0) {
        fwrite(STDERR, $totals === [] ? "no suite totals in {$log}\n" : "{$failed} checks failed in {$log}: quote a green run\n");
        exit(1);
    }
    $checks = array_sum(array_map(static fn (array $row): int => (int) $row[1], $totals));
}
if ($suites === 0 || $checks === 0) {
    fwrite(STDERR, "nothing to write: pass a run-all log or --checks=N\n");
    exit(1);
}

$stats = ['checks' => $checks, 'suites' => $suites, 'measured' => date('Y-m-d')];
if (!is_dir(dirname($target))) {
    fwrite(STDERR, "the theme is not checked out at site/minn-site\n");
    exit(1);
}
file_put_contents($target, json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
echo "wrote site/minn-site/content/stats.json: {$checks} checks across {$suites} suites\n";
