<?php

declare(strict_types=1);

/**
 * Measure 29 of the release gate (tracked): the logic still in the WordPress facade.
 * The facade (public/minn/wp-api/) keeps every public symbol WordPress has,
 * but its functions and methods should only hand over to the engine:
 * behaviour belongs in src/Minn. A function or method holds logic when its
 * body has more than one statement or any control structure; one statement
 * (a return or a call) is a delegate. Generated dead ends and placeholders
 * are not counted, and neither is a definition whose docblock says why it
 * stays (`@minn-facade-logic <reason>`: code that must run in the layer's
 * own scope). Writes contracts/api/facade-logic.json.
 *
 *   php tests/tools/facade-logic.php           write the file and print the summary
 *   php tests/tools/facade-logic.php --list    also list the files with the most logic
 *   php tests/tools/facade-logic.php --check   exit 1 when the file on disk is stale
 */

require __DIR__ . '/facade-parse.php';

$root = dirname(__DIR__, 2);
$facadeDir = "{$root}/public/minn/wp-api";
$target = "{$root}/contracts/api/facade-logic.json";

$summary = ['functions' => 0, 'methods' => 0, 'delegates' => 0, 'logicFunctions' => 0, 'logicMethods' => 0, 'logicLines' => 0, 'kept' => 0];
$byFile = [];
$logic = [];
$kept = [];
foreach (facade_files($facadeDir) as $file) {
    $relative = substr($file, strlen($facadeDir) + 1);
    foreach (facade_definitions((string) file_get_contents($file)) as $definition) {
        $isMethod = $definition['owner'] !== null;
        $summary[$isMethod ? 'methods' : 'functions']++;
        $name = ($isMethod ? $definition['owner'] . '::' : '') . $definition['name'];
        if (!facade_is_logic($definition)) {
            $summary['delegates']++;
            continue;
        }
        $reason = facade_kept($definition);
        if ($reason !== null) {
            $summary['kept']++;
            $kept[$name] = ['file' => $relative, 'reason' => $reason];
            continue;
        }
        $summary[$isMethod ? 'logicMethods' : 'logicFunctions']++;
        $summary['logicLines'] += $definition['lines'];
        $byFile[$relative] ??= ['logic' => 0, 'lines' => 0];
        $byFile[$relative]['logic']++;
        $byFile[$relative]['lines'] += $definition['lines'];
        $logic[$name] = ['file' => $relative, 'line' => $definition['line'], 'statements' => $definition['statements'], 'lines' => $definition['lines']];
    }
}
uasort($byFile, static fn (array $a, array $b): int => $b['lines'] <=> $a['lines']);
ksort($logic);
ksort($kept);

$report = [
    'method' => 'tests/tools/facade-logic.php: functions and methods in public/minn/wp-api (generated dead ends and placeholders apart) whose body holds more than one statement or a control structure, by tokens; lines are the body\'s non-blank, non-comment lines; a docblock tag @minn-facade-logic <reason> keeps one out of the count and lists it under "kept"',
    'summary' => $summary,
    'byFile' => $byFile,
    'logic' => $logic,
    'kept' => $kept,
];
$json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

if (in_array('--check', $argv, true)) {
    exit(is_file($target) && (string) file_get_contents($target) === $json ? 0 : 1);
}
file_put_contents($target, $json);
printf("%d logic functions and %d logic methods in the facade, %d lines; %d delegates; %d kept on purpose\n", $summary['logicFunctions'], $summary['logicMethods'], $summary['logicLines'], $summary['delegates'], $summary['kept']);
if (in_array('--list', $argv, true)) {
    foreach (array_slice($byFile, 0, 25, true) as $file => $row) {
        printf("%6d lines in %4d  %s\n", $row['lines'], $row['logic'], $file);
    }
}
