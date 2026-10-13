<?php
/**
 * Every call the engine makes into a WordPress-named function: the release
 * gate's measure 15 (/dev-minn-engine-coverage), which must reach 0 before
 * an engine release (Austin, 2026-10-13). src/ does its work in Minn
 * classes; a call into a function of the reference's inventory
 * (contracts/api/functions.json) is the facade reached from underneath,
 * owed a Minn equivalent. The counting rule is the style suite's
 * ($wordpressCallCeiling): a name followed by "(", not a method, not a
 * definition, not `new`. The hook API (apply_filters, do_action and their
 * variants) is counted apart: it is how the engine speaks to plugins.
 *
 * Writes contracts/api/wordpress-calls.json: each function with its calls,
 * the files that make them and the reference file it lives in (its
 * family, the natural batch), and the totals.
 *
 *   php tests/tools/wordpress-calls.php            write the file and print the totals
 *   php tests/tools/wordpress-calls.php --check    exit 1 when the file on disk is stale
 *   php tests/tools/wordpress-calls.php --list     print the families, largest first, with their functions
 */

$root = dirname(__DIR__, 2);
$source = "{$root}/public/minn/src";
$inventory = json_decode((string) file_get_contents("{$root}/contracts/api/functions.json"), true);
$families = [];
foreach ($inventory as $name => $spec) {
    $families[strtolower($name)] = [(string) $name, (string) ($spec['file'] ?? '')];
}
const HOOK_API = ['apply_filters', 'apply_filters_ref_array', 'apply_filters_deprecated', 'do_action', 'do_action_ref_array', 'do_action_deprecated'];

$functions = [];
$hookCalls = 0;
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS));
$paths = [];
foreach ($files as $file) {
    if ($file->getExtension() === 'php') {
        $paths[] = $file->getPathname();
    }
}
sort($paths);
foreach ($paths as $path) {
    $relative = substr($path, strlen($source) + 1);
    $tokens = token_get_all((string) file_get_contents($path));
    $count = count($tokens);
    foreach ($tokens as $i => $token) {
        if (!is_array($token) || !in_array($token[0], [T_STRING, T_NAME_FULLY_QUALIFIED], true)) {
            continue;
        }
        $name = strtolower(ltrim($token[1], '\\'));
        $previous = $i - 1;
        while ($previous >= 0 && is_array($tokens[$previous]) && $tokens[$previous][0] === T_WHITESPACE) {
            $previous--;
        }
        $next = $i + 1;
        while ($next < $count && is_array($tokens[$next]) && $tokens[$next][0] === T_WHITESPACE) {
            $next++;
        }
        $before = $previous >= 0 ? $tokens[$previous] : null;
        if (($tokens[$next] ?? null) !== '(' || !isset($families[$name])) {
            continue;
        }
        if (is_array($before) && in_array($before[0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW], true)) {
            continue;
        }
        if (in_array($name, HOOK_API, true)) {
            $hookCalls++;
            continue;
        }
        [$display, $family] = $families[$name];
        $functions[$display] ??= ['calls' => 0, 'family' => $family, 'files' => []];
        $functions[$display]['calls']++;
        $functions[$display]['files'][$relative] = ($functions[$display]['files'][$relative] ?? 0) + 1;
    }
}
uksort($functions, static fn (string $a, string $b): int => [$functions[$b]['calls'], $a] <=> [$functions[$a]['calls'], $b]);

$byFamily = [];
$callerFiles = [];
foreach ($functions as $name => $row) {
    $byFamily[$row['family']] = ($byFamily[$row['family']] ?? 0) + $row['calls'];
    foreach ($row['files'] as $file => $n) {
        $callerFiles[$file] = ($callerFiles[$file] ?? 0) + $n;
    }
}
arsort($byFamily);
arsort($callerFiles);
$report = [
    'method' => 'tests/tools/wordpress-calls.php: calls from public/minn/src into functions of the reference inventory (the hook API counted apart), by function, with the files making them and the reference file each lives in',
    'summary' => ['calls' => array_sum(array_column($functions, 'calls')), 'functions' => count($functions), 'families' => count($byFamily), 'files' => count($callerFiles), 'hookApi' => $hookCalls],
    'families' => $byFamily,
    'functions' => $functions,
];
$encoded = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
$target = "{$root}/contracts/api/wordpress-calls.json";
if (in_array('--check', $argv, true)) {
    exit((string) @file_get_contents($target) === $encoded ? 0 : 1);
}
file_put_contents($target, $encoded);
$s = $report['summary'];
printf("%d calls to %d WordPress-named functions from %d engine files, in %d reference families; %d hook API calls besides\n", $s['calls'], $s['functions'], $s['files'], $s['families'], $s['hookApi']);
if (in_array('--list', $argv, true)) {
    foreach ($byFamily as $family => $calls) {
        $members = array_filter($functions, static fn (array $row): bool => $row['family'] === $family);
        echo "\n{$calls}  {$family}\n    ", implode(', ', array_map(static fn (string $name, array $row): string => "{$name} {$row['calls']}", array_keys($members), $members)), "\n";
    }
}
