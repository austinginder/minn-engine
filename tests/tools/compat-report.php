<?php
/**
 * Turns a catalogue gate scan into a work queue. Reads the per-component
 * verdicts from compat-scan.php plus the wp.org install counts, then answers
 * two questions: how much of the popular catalogue the runtime already loads,
 * and which missing symbol to write next.
 *
 * The order is a greedy set cover, not a frequency count: the symbol that
 * appears in the most components is often not the one that finishes any of
 * them. Each step picks the symbol that turns the most components green once
 * everything above it exists.
 *
 * php tests/tools/compat-report.php <report.ndjson> [installs.json] [--queue=40]
 */
declare(strict_types=1);

$reportPath = $argv[1] ?? '';
$installsPath = null;
$queueLength = 40;
foreach (array_slice($argv, 2) as $argument) {
    if (str_starts_with($argument, '--queue=')) {
        $queueLength = (int) substr($argument, 8);
        continue;
    }
    $installsPath = $argument;
}

/** @var array<string, array{state: string, functions?: list<string>, classes?: list<string>, truncated?: bool}> $rows */
$rows = [];
foreach (file($reportPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    $decoded = json_decode($line, true);
    if (is_array($decoded) && isset($decoded['slug'])) {
        $rows[$decoded['slug']] = $decoded;
        continue;
    }
    if (is_array($decoded)) {
        foreach ($decoded as $slug => $row) {
            $rows[$slug] = $row;
        }
    }
}

$installs = [];
if ($installsPath !== null && is_file($installsPath)) {
    foreach (json_decode((string) file_get_contents($installsPath), true) ?: [] as $row) {
        $installs[$row['slug']] = (int) ($row['installs'] ?? 0);
    }
}
$reach = static fn (string $slug): int => $installs[$slug] ?? 0;

// A component's blockers, as one flat set. A class and a function never
// collide because the class names are capitalised in the reference.
$blockers = [];
$loading = [];
$truncated = [];
foreach ($rows as $slug => $row) {
    if (($row['state'] ?? '') === 'loads') {
        $loading[$slug] = true;
        continue;
    }
    if ($row['truncated'] ?? false) {
        $truncated[$slug] = true;
        continue;
    }
    $names = array_merge($row['functions'] ?? [], $row['classes'] ?? []);
    if ($names !== []) {
        $blockers[$slug] = array_fill_keys($names, true);
    }
}

$total = count($rows);
$totalReach = array_sum(array_map($reach, array_keys($rows)));
$loadingReach = array_sum(array_map($reach, array_keys($loading)));
printf("%d components scanned\n", $total);
printf("  loads now       %4d (%.1f%%)   %s installs\n", count($loading), $total ? count($loading) / $total * 100 : 0, number_format($loadingReach));
printf("  gate-skipped    %4d (%.1f%%)\n", count($blockers), $total ? count($blockers) / $total * 100 : 0);
if ($truncated !== []) {
    printf("  too large       %4d\n", count($truncated));
}
printf("  installs covered %.1f%%\n\n", $totalReach ? $loadingReach / $totalReach * 100 : 0);

// How deep the remaining work is: a component two symbols short is a very
// different proposition from one thirty short.
$depths = [];
foreach ($blockers as $slug => $names) {
    $depths[] = count($names);
}
sort($depths);
$within = static fn (int $n): int => count(array_filter($depths, static fn (int $d): bool => $d <= $n));
printf("Of the %d skipped: %d are 1 symbol short, %d are <=3, %d are <=5, %d are <=10\n\n", count($depths), $within(1), $within(3), $within(5), $within(10));

// The greedy cover.
$remaining = $blockers;
$written = [];
printf("%-4s %-44s %7s %7s %14s\n", '#', 'symbol', 'frees', 'total', 'installs freed');
printf("%s\n", str_repeat('-', 80));
$cumulative = count($loading);
$cumulativeReach = $loadingReach;
for ($step = 1; $step <= $queueLength && $remaining !== []; $step++) {
    // Score every candidate by the components it completes on its own.
    $frees = [];
    $freesReach = [];
    $appears = [];
    foreach ($remaining as $slug => $names) {
        foreach (array_keys($names) as $name) {
            $appears[$name] = ($appears[$name] ?? 0) + 1;
            if (count($names) === 1) {
                $frees[$name] = ($frees[$name] ?? 0) + 1;
                $freesReach[$name] = ($freesReach[$name] ?? 0) + $reach($slug);
            }
        }
    }
    // Nothing completes a component yet, so take the widest-reaching symbol:
    // it is the one that moves the most components closer to done.
    if ($frees === []) {
        $weight = [];
        foreach ($appears as $name => $count) {
            $weight[$name] = 0;
            foreach ($remaining as $slug => $names) {
                if (isset($names[$name])) {
                    $weight[$name] += $reach($slug);
                }
            }
        }
        arsort($weight);
        $pick = (string) array_key_first($weight);
        $freed = 0;
        $freedReach = 0;
    } else {
        // Ties on component count go to the one with more installs behind it.
        uksort($frees, static function (string $a, string $b) use ($frees, $freesReach, $appears): int {
            return [$frees[$b], $freesReach[$b], $appears[$b]] <=> [$frees[$a], $freesReach[$a], $appears[$a]];
        });
        $pick = (string) array_key_first($frees);
        $freed = $frees[$pick];
        $freedReach = $freesReach[$pick];
    }
    $written[$pick] = true;
    foreach ($remaining as $slug => $names) {
        unset($names[$pick]);
        if ($names === []) {
            unset($remaining[$slug]);
            continue;
        }
        $remaining[$slug] = $names;
    }
    $cumulative += $freed;
    $cumulativeReach += $freedReach;
    printf(
        "%-4d %-44s %7s %7d %14s\n",
        $step,
        $pick,
        $freed > 0 ? '+' . $freed : '.',
        $cumulative,
        $freedReach > 0 ? number_format($freedReach) : '.',
    );
}
printf("\nAfter those %d symbols: %d of %d load (%.1f%%), %d still skipped\n", count($written), $cumulative, $total, $total ? $cumulative / $total * 100 : 0, count($remaining));
