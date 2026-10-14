<?php

declare(strict_types=1);

/**
 * The facade review: a verdict for every facade definition that holds
 * logic (measure 29, facade-logic.php), recorded in
 * contracts/api/facade-review.json by file and name, each with a hash of
 * the body it was given for, so a changed body asks to be reviewed again.
 *
 *   delegate  the engine already does this (`to` names the Minn method): the body becomes one call
 *   move      engine behaviour written in the facade (`to` names the Minn class it belongs in)
 *   keep      it stays, for a `reason`: scope (runs in the facade's or the caller's scope: globals,
 *             templates loaded into it), shape (turns Minn results into WordPress's shapes: WP_Error,
 *             WP_Post, echo or return, output modes), compose (built only from the API's own public
 *             functions, an older name or a fallback, where calling them by name is the point: a plugin
 *             or a drop-in may replace them), contract (a class's own `$this->` calls, the points
 *             plugins override), library (a bundled library's small internals the engine has no use
 *             for, its own way of doing the job being elsewhere, kept for code that calls them)
 *   remove    nothing needs it, or it repeats another definition; the `why` says which (a dead end or
 *             an omission is Austin's call, so a remove verdict is a proposal)
 *
 * A `doubt` (one line) names behaviour the review found suspect, for a probe to settle.
 *
 * Gates on a delegate or move: subclass (a class plugins extend: the subclass-parity probe first),
 * pluggable (plugins replace it), unprobed (no compared probe calls it: a probe first), globals (it
 * reads or writes globals plugins can see), refs (arguments or results by reference).
 *
 *   php tests/tools/facade-review.php                 the summary: reviewed, by verdict, what is left, by group
 *   php tests/tools/facade-review.php --next[=N]      the files with the most unreviewed lines
 *   php tests/tools/facade-review.php --show FILE     each definition holding logic in FILE, its probes and its verdict (--bodies prints them)
 *   php tests/tools/facade-review.php --merge PATH    record the verdicts in PATH ({file: {name: {verdict, ...}}}), all or none
 *   php tests/tools/facade-review.php --validate PATH the same checks, and every definition of each file named judged; writes nothing
 *   php tests/tools/facade-review.php --check         exit 1 on a verdict that is stale, malformed, or for a definition no longer holding logic
 *   php tests/tools/facade-review.php --json          the summary as JSON
 */

require __DIR__ . '/facade-parse.php';

$root = dirname(__DIR__, 2);
$facadeDir = "{$root}/public/minn/wp-api";
$ledgerPath = "{$root}/contracts/api/facade-review.json";

const VERDICTS = ['delegate', 'move', 'keep', 'remove'];
const KEEP_REASONS = ['scope', 'shape', 'compose', 'contract', 'library'];
const GATES = ['subclass', 'pluggable', 'unprobed', 'globals', 'refs'];

/** A body's fingerprint: its text with whitespace runs made one space. */
function body_hash(string $body): string
{
    return substr(sha1((string) preg_replace('/\s+/', ' ', trim($body))), 0, 12);
}

/** Every definition holding logic, by file then name (Owner::method for a method). */
function logic_definitions(string $facadeDir): array
{
    $found = [];
    foreach (facade_files($facadeDir) as $file) {
        $relative = substr($file, strlen($facadeDir) + 1);
        foreach (facade_definitions((string) file_get_contents($file)) as $definition) {
            if (!facade_is_logic($definition) || facade_kept($definition) !== null) {
                continue;
            }
            $name = ($definition['owner'] !== null ? $definition['owner'] . '::' : '') . $definition['name'];
            $found[$relative][$name] = $definition + ['hash' => body_hash($definition['body'])];
        }
    }
    return $found;
}

function read_ledger(string $path): array
{
    $ledger = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
    return is_array($ledger['verdicts'] ?? null) ? $ledger['verdicts'] : [];
}

function write_ledger(string $path, array $verdicts): void
{
    ksort($verdicts);
    foreach ($verdicts as &$names) {
        ksort($names);
    }
    unset($names);
    $ledger = [
        'method' => 'tests/tools/facade-review.php: a verdict for each facade definition holding logic (facade-logic.php), by file and name; body is a hash of the body the verdict was given for',
        'verdicts' => $verdicts,
    ];
    file_put_contents($path, json_encode($ledger, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
}

/** What is wrong with one verdict, or null. */
function verdict_problem(array $entry): ?string
{
    $verdict = $entry['verdict'] ?? null;
    if (!in_array($verdict, VERDICTS, true)) {
        return 'verdict must be one of ' . implode(', ', VERDICTS);
    }
    if (trim((string) ($entry['why'] ?? '')) === '' || str_contains((string) $entry['why'], "\n")) {
        return 'why must be one line';
    }
    if ($verdict === 'keep' && !in_array($entry['reason'] ?? null, KEEP_REASONS, true)) {
        return 'keep needs a reason: ' . implode(', ', KEEP_REASONS);
    }
    if (in_array($verdict, ['delegate', 'move'], true) && trim((string) ($entry['to'] ?? '')) === '') {
        return "{$verdict} needs a to";
    }
    if (array_diff((array) ($entry['gates'] ?? []), GATES) !== []) {
        return 'gates must be among ' . implode(', ', GATES);
    }
    if (isset($entry['doubt']) && (trim((string) $entry['doubt']) === '' || str_contains((string) $entry['doubt'], "\n"))) {
        return 'a doubt is one line';
    }
    $known = ['verdict', 'reason', 'to', 'gates', 'why', 'doubt', 'body'];
    if (array_diff(array_keys($entry), $known) !== []) {
        return 'unknown keys: ' . implode(', ', array_diff(array_keys($entry), $known));
    }
    return null;
}

/** The registered probes' source, by file name (tests/api.test.php's map). */
function registered_probes(string $root): array
{
    preg_match_all("/=> '([a-z0-9-]+-probe\\.php)'/", (string) file_get_contents("{$root}/tests/api.test.php"), $m);
    $probes = [];
    foreach (array_unique($m[1]) as $probe) {
        $path = "{$root}/tests/tools/{$probe}";
        if (is_file($path)) {
            $probes[$probe] = (string) file_get_contents($path);
        }
    }
    return $probes;
}

/** How many registered probes call a function by name, or a method on its class. */
function probes_calling(array $probes, string $name): int
{
    [$owner, $method] = str_contains($name, '::') ? explode('::', $name, 2) : [null, $name];
    $calls = 0;
    foreach ($probes as $source) {
        if ($owner === null) {
            $calls += preg_match('/(?<![\w$>:\\\\])(?<!function )' . preg_quote($method, '/') . '\s*\(/i', $source) === 1 ? 1 : 0;
        } else {
            $calls += preg_match('/\b' . preg_quote($owner, '/') . '\b/', $source) === 1 && preg_match('/(->|::)' . preg_quote($method, '/') . '\s*\(/i', $source) === 1 ? 1 : 0;
        }
    }
    return $calls;
}

function group_of(string $file): string
{
    return str_contains($file, '/') ? explode('/', $file)[0] : 'functions';
}

$mode = $argv[1] ?? '--summary';
$definitions = logic_definitions($facadeDir);
$verdicts = read_ledger($ledgerPath);

if ($mode === '--merge' || $mode === '--validate') {
    $proposals = json_decode((string) @file_get_contents($argv[2] ?? ''), true);
    if (!is_array($proposals)) {
        fwrite(STDERR, "usage: facade-review.php {$mode} PATH (a JSON object {file: {name: verdict}})\n");
        exit(2);
    }
    $problems = [];
    $merged = 0;
    foreach ($proposals as $file => $names) {
        foreach ((array) $names as $name => $entry) {
            $definition = $definitions[$file][$name] ?? null;
            $problem = $definition === null ? 'no definition holding logic by that name in that file' : (is_array($entry) ? verdict_problem($entry) : 'not an object');
            if ($problem !== null) {
                $problems[] = "{$file} {$name}: {$problem}";
                continue;
            }
            $entry['body'] = $definition['hash'];
            $verdicts[$file][$name] = array_filter($entry, static fn (mixed $value): bool => $value !== [] && $value !== '' && $value !== null);
            $merged++;
        }
        // Every definition of a file the proposals name is judged, here or before.
        foreach (array_keys($definitions[$file] ?? []) as $name) {
            $entry = $verdicts[$file][$name] ?? null;
            if ($entry === null || ($entry['body'] ?? '') !== $definitions[$file][$name]['hash']) {
                $problems[] = "{$file} {$name}: no verdict";
            }
        }
    }
    if ($problems !== []) {
        fwrite(STDERR, implode("\n", $problems) . "\nnothing recorded\n");
        exit(1);
    }
    if ($mode === '--validate') {
        printf("%d verdicts valid, every definition of the %d files judged; nothing recorded\n", $merged, count($proposals));
        exit(0);
    }
    write_ledger($ledgerPath, $verdicts);
    printf("recorded %d verdicts\n", $merged);
    exit(0);
}

if ($mode === '--show') {
    $file = $argv[2] ?? '';
    if (!isset($definitions[$file])) {
        fwrite(STDERR, "no definitions holding logic in {$file}\n");
        exit(1);
    }
    $probes = registered_probes($root);
    $bodies = in_array('--bodies', $argv, true);
    foreach ($definitions[$file] as $name => $definition) {
        $entry = $verdicts[$file][$name] ?? null;
        $status = $entry === null ? 'unreviewed' : (($entry['body'] ?? '') !== $definition['hash'] ? 'STALE ' : '') . $entry['verdict'] . (isset($entry['reason']) ? ":{$entry['reason']}" : '') . (isset($entry['to']) ? " -> {$entry['to']}" : '');
        printf("%-48s line %5d  %3d lines  %2d probes%s  %s\n", $name, $definition['line'], $definition['lines'], probes_calling($probes, $name), $definition['conditional'] ? '  conditional' : '', $status);
        if ($bodies) {
            echo '    (' . $definition['signature'] . ')' . ($definition['returns'] !== '' ? ' ' . $definition['returns'] : '') . "\n";
            echo preg_replace('/^/m', '    ', rtrim($definition['body'], "\n ")) . "\n\n";
        }
    }
    exit(0);
}

// The summary, --next, --check and --json read the same tally.
$tally = ['doubts' => 0, 'definitions' => 0, 'lines' => 0, 'reviewed' => 0, 'reviewedLines' => 0, 'stale' => 0, 'orphans' => 0, 'malformed' => 0, 'verdicts' => array_fill_keys(VERDICTS, 0), 'keep' => array_fill_keys(KEEP_REASONS, 0), 'gates' => array_fill_keys(GATES, 0)];
$groups = [];
$files = [];
$problems = [];
foreach ($definitions as $file => $names) {
    $group = group_of($file);
    $groups[$group] ??= ['definitions' => 0, 'reviewed' => 0, 'lines' => 0, 'unreviewedLines' => 0];
    foreach ($names as $name => $definition) {
        $tally['definitions']++;
        $tally['lines'] += $definition['lines'];
        $groups[$group]['definitions']++;
        $groups[$group]['lines'] += $definition['lines'];
        $entry = $verdicts[$file][$name] ?? null;
        if ($entry !== null && ($entry['body'] ?? '') !== $definition['hash']) {
            $tally['stale']++;
            $problems[] = "{$file} {$name}: its body changed since the verdict; review it again";
            $entry = null;
        }
        if ($entry === null) {
            $groups[$group]['unreviewedLines'] += $definition['lines'];
            $files[$file] = ($files[$file] ?? 0) + $definition['lines'];
            continue;
        }
        $problem = verdict_problem($entry);
        if ($problem !== null) {
            $tally['malformed']++;
            $problems[] = "{$file} {$name}: {$problem}";
        }
        $tally['reviewed']++;
        $tally['reviewedLines'] += $definition['lines'];
        $groups[$group]['reviewed']++;
        $tally['verdicts'][$entry['verdict']] = ($tally['verdicts'][$entry['verdict']] ?? 0) + 1;
        if (($entry['verdict'] ?? '') === 'keep' && isset($entry['reason'])) {
            $tally['keep'][$entry['reason']] = ($tally['keep'][$entry['reason']] ?? 0) + 1;
        }
        $tally['doubts'] += isset($entry['doubt']) ? 1 : 0;
        foreach ((array) ($entry['gates'] ?? []) as $gate) {
            $tally['gates'][$gate] = ($tally['gates'][$gate] ?? 0) + 1;
        }
    }
}
foreach ($verdicts as $file => $names) {
    foreach (array_keys((array) $names) as $name) {
        if (!isset($definitions[$file][$name])) {
            $tally['orphans']++;
            $problems[] = "{$file} {$name}: no longer holds logic there; remove the verdict";
        }
    }
}
$tally['unreviewed'] = $tally['definitions'] - $tally['reviewed'];

if ($mode === '--check') {
    echo json_encode(['unreviewed' => $tally['unreviewed'], 'stale' => $tally['stale'], 'orphans' => $tally['orphans'], 'malformed' => $tally['malformed'], 'problems' => array_slice($problems, 0, 20)], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
    exit($tally['stale'] + $tally['orphans'] + $tally['malformed'] === 0 ? 0 : 1);
}
if ($mode === '--json') {
    echo json_encode(['summary' => $tally, 'groups' => $groups], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
    exit(0);
}
if (str_starts_with($mode, '--next')) {
    arsort($files);
    $count = str_contains($mode, '=') ? (int) substr($mode, 7) : 10;
    foreach (array_slice($files, 0, $count, true) as $file => $lines) {
        printf("%6d unreviewed lines  %s\n", $lines, $file);
    }
    exit(0);
}
printf("%d of %d definitions holding logic reviewed (%d of %d lines); %d unreviewed, %d stale, %d orphaned, %d malformed\n",
    $tally['reviewed'], $tally['definitions'], $tally['reviewedLines'], $tally['lines'], $tally['unreviewed'], $tally['stale'], $tally['orphans'], $tally['malformed']);
printf("verdicts: %s; kept for %s; gates %s; %d doubts\n",
    implode(', ', array_map(static fn (string $v): string => "{$tally['verdicts'][$v]} {$v}", VERDICTS)),
    implode(', ', array_map(static fn (string $r): string => "{$r} {$tally['keep'][$r]}", KEEP_REASONS)),
    implode(', ', array_map(static fn (string $g): string => "{$g} {$tally['gates'][$g]}", GATES)), $tally['doubts']);
foreach ($groups as $group => $row) {
    printf("  %-10s %4d of %4d reviewed, %6d lines left\n", $group, $row['reviewed'], $row['definitions'], $row['unreviewedLines']);
}
exit(0);
