<?php

declare(strict_types=1);

/**
 * Writes site/minn-site/content/stats.json: the check and suite counts the
 * marketing front page quotes (the class count it reads from the theme's
 * content/api.json on its own), and the run suite by suite for the page's
 * checks dialog: each suite's checks, the first sentence of its header, and
 * what it is checked against. Suites are counted from run-all.sh; checks are
 * summed from the "N passed, M failed" line every suite prints, so feed it
 * the log of a full green run.
 *
 * A suite that fails a check, or ends without its totals line, refuses the
 * write. Name it with --known when the run carries a red that is already
 * understood: it is listed apart in the file and its checks are left out of
 * the total.
 *
 *   ./tests/run-all.sh | tee /tmp/run-all.log
 *   php tests/tools/site-stats.php /tmp/run-all.log
 *   php tests/tools/site-stats.php /tmp/run-all.log --known=requests --known="browser: admin views (dogfood)"
 *   php tests/tools/site-stats.php --checks=17000    when no log is at hand (no breakdown)
 */

// The day a run is measured on is the machine's day, not UTC's.
if (preg_match('#zoneinfo/(.+)$#', (string) @readlink('/etc/localtime'), $zone) === 1) {
    date_default_timezone_set($zone[1]);
}

$root = dirname(__DIR__, 2);
$target = "{$root}/site/minn-site/content/stats.json";
$options = ['known' => []];
$log = null;
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--(checks|known)=(.*)$/s', $arg, $m) === 1) {
        $m[1] === 'known' ? $options['known'][] = $m[2] : $options['checks'] = $m[2];
    } else {
        $log = $arg;
    }
}
$known = $options['known'];

$runAll = (string) file_get_contents("{$root}/tests/run-all.sh");
$files = [];
if (preg_match('/^for suite in ([^;]+);/m', $runAll, $m) === 1) {
    foreach (preg_split('/\s+/', trim($m[1])) as $name) {
        $files[$name] = "tests/{$name}.test.php";
    }
}
preg_match_all('/=== (browser: .+?) ===\\\\n"\n[^\n]*?node (browser\/[\w-]+\.test\.js)/', $runAll, $browser, PREG_SET_ORDER);
foreach ($browser as [, $name, $file]) {
    $files[$name] = "tests/{$file}";
}
$suites = count($files);

/**
 * A suite file's header comment as one line per paragraph: the leading
 * docblock, or the leading run of // lines in a browser suite.
 *
 * @return list<string>
 */
function suite_header(string $path): array
{
    $source = (string) @file_get_contents($path);
    if (preg_match('#^(?:<\?php\s+(?:declare\([^)]*\);\s*)?)?/\*\*(.*?)\*/#s', $source, $m) === 1) {
        $lines = preg_replace('/^\s*\* ?/', '', explode("\n", $m[1]));
    } elseif (preg_match('#^(?:\s*//[^\n]*\n)+#', $source, $m) === 1) {
        $lines = preg_replace('#^\s*// ?#', '', explode("\n", rtrim($m[0])));
    } else {
        return [];
    }
    $paragraphs = preg_split('/\n\s*\n/', trim(implode("\n", $lines)));
    return array_values(array_filter(array_map(static fn (string $p): string => trim((string) preg_replace('/\s+/', ' ', $p)), $paragraphs)));
}

/** What a suite checks against, read from its header: a suite that says it is compared with the reference is WordPress's. */
function suite_kind(string $name, array $header): string
{
    $text = implode(' ', $header);
    $text = (string) preg_replace(['/\bno\s+(?:oracle|reference)\b/i', '/\bwithout\b[^.;:]*?\ban?\s+(?:oracle|reference)\b/i', '/\bthe reference does not\b/i'], '', $text);
    return match (true) {
        $name === 'unit' => 'unit',
        $name === 'style' => 'rules',
        preg_match('/\b(?:parity|reference|oracle|parked|cross-stack|both stacks|engine vs|Ref:)|\b(?:against|through) (?:real )?WordPress\b(?![\'’])/i', $text) === 1 => 'wordpress',
        default => 'engine',
    };
}

/** The header's first sentence for a reader: no asides in brackets, no dash inside it, at most about two lines. */
function suite_about(array $header): string
{
    $first = preg_split('/(?<=[.!?])\s+(?=[A-Z`])/', $header[0] ?? '', 2)[0] ?? '';
    $first = trim((string) preg_replace(['/\s*\([^()]*\)/', '/\s+—\s+/'], ['', ': '], $first));
    if (mb_strlen($first) > 240) {
        $first = rtrim((string) preg_replace('/\s+\S*$/u', '', mb_substr($first, 0, 240)), ' ,;:') . '…';
    }
    return $first;
}

$previous = is_file($target) ? json_decode((string) file_get_contents($target), true) : null;
$checks = is_array($previous) ? (int) ($previous['checks'] ?? 0) : 0;
$bySuite = [];
$knownRed = [];
if (isset($options['checks'])) {
    $checks = (int) $options['checks'];
} elseif ($log !== null) {
    $sections = [];
    $current = null;
    foreach (preg_split('/\R/', (string) @file_get_contents($log)) as $line) {
        if (preg_match('/^=== (.+) ===$/', $line, $m) === 1) {
            $current = $m[1];
            $sections[$current] = ['passed' => 0, 'failed' => 0, 'reported' => false, 'flagged' => false];
        } elseif ($current !== null && preg_match('/^\s*(\d+) passed, (\d+) failed/', $line, $m) === 1) {
            $sections[$current]['passed'] += (int) $m[1];
            $sections[$current]['failed'] += (int) $m[2];
            $sections[$current]['reported'] = true;
        } elseif ($current !== null && preg_match('/^\s*FAIL\b/', $line) === 1) {
            $sections[$current]['flagged'] = true;
        }
    }
    $red = [];
    $checks = 0;
    foreach ($files as $name => $file) {
        $run = $sections[$name] ?? null;
        $green = $run !== null && $run['reported'] && $run['failed'] === 0 && !$run['flagged'];
        if (!$green) {
            $why = $run === null ? 'not in the log' : (!$run['reported'] ? 'no totals line' : ($run['failed'] > 0 ? "{$run['failed']} failed" : 'a FAIL line'));
            in_array($name, $known, true) ? $knownRed[] = ['name' => $name, 'file' => $file] : $red[] = "{$name} ({$why})";
            continue;
        }
        $header = suite_header("{$root}/{$file}");
        $bySuite[] = ['name' => $name, 'file' => $file, 'checks' => $run['passed'], 'kind' => suite_kind($name, $header), 'about' => suite_about($header)];
        $checks += $run['passed'];
    }
    if ($red !== []) {
        fwrite(STDERR, "not green in {$log}: " . implode(', ', $red) . "\nquote a green run, or name a known red with --known=<suite>\n");
        exit(1);
    }
}
if ($suites === 0 || $checks === 0) {
    fwrite(STDERR, "nothing to write: pass a run-all log or --checks=N\n");
    exit(1);
}

$stats = ['checks' => $checks, 'suites' => $suites, 'measured' => date('Y-m-d', $log !== null && !isset($options['checks']) ? (int) filemtime($log) : time())];
if ($bySuite !== []) {
    $stats['bySuite'] = $bySuite;
    $stats['known'] = $knownRed;
}
if (!is_dir(dirname($target))) {
    fwrite(STDERR, "the theme is not checked out at site/minn-site\n");
    exit(1);
}
file_put_contents($target, json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
echo "wrote site/minn-site/content/stats.json: {$checks} checks across {$suites} suites" . ($knownRed === [] ? '' : ' (' . count($knownRed) . ' known red, not counted)') . "\n";
