<?php
/**
 * Every WordPress name the engine uses: the release gate's measure 15
 * (/dev-minn-engine-coverage), which must reach 0 before an engine release
 * (Austin, 2026-10-13: Minn is a clean slate). src/ does its work in Minn
 * classes; a WordPress function or class used there is the facade reached
 * from underneath, owed a Minn version. Three counts:
 *
 * - functions: calls into a function of the reference's inventory
 *   (contracts/api/functions.json), counted as the style suite counts them
 *   ($wordpressCallCeiling): a name followed by "(", not a method, not a
 *   definition, not `new`; the hook API apart;
 * - hookApi: calls into the hook API by its WordPress names
 *   (apply_filters, do_action and their variants), which become
 *   Runtime::hooks() calls ($hookApiCeiling);
 * - classes: every place a class of the reference's inventory
 *   (contracts/api/classes.json) is named, fully qualified or through a
 *   `use` import (the import itself aside): `new`, static calls,
 *   `instanceof`, `catch`, parameter, property and return types
 *   ($wordpressClassCeiling).
 *
 * Writes contracts/api/wordpress-names.json: each function and class with
 * its count, the files using it and the reference file it lives in (its
 * family, the natural batch), and the totals.
 *
 *   php tests/tools/wordpress-names.php            write the file and print the totals
 *   php tests/tools/wordpress-names.php --check    exit 1 when the file on disk is stale
 *   php tests/tools/wordpress-names.php --list     print the families, largest first, with their names
 */

$root = dirname(__DIR__, 2);
$source = "{$root}/public/minn/src";
$inventory = static function (string $file): array {
    $names = [];
    foreach (json_decode((string) file_get_contents($file), true) as $name => $spec) {
        $names[strtolower((string) $name)] = [(string) $name, (string) ($spec['file'] ?? '')];
    }
    return $names;
};
$knownFunctions = $inventory("{$root}/contracts/api/functions.json");
$knownClasses = $inventory("{$root}/contracts/api/classes.json");
const HOOK_API = ['apply_filters', 'apply_filters_ref_array', 'apply_filters_deprecated', 'do_action', 'do_action_ref_array', 'do_action_deprecated'];

$paths = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS)) as $file) {
    if ($file->getExtension() === 'php') {
        $paths[] = $file->getPathname();
    }
}
sort($paths);

$functions = [];
$classes = [];
$hookCalls = 0;
/** One more use of a function or class, by the file using it. */
$tally = static function (array &$table, array $known, string $name, string $relative): void {
    [$display, $family] = $known[$name];
    $table[$display] ??= ['uses' => 0, 'family' => $family, 'files' => []];
    $table[$display]['uses']++;
    $table[$display]['files'][$relative] = ($table[$display]['files'][$relative] ?? 0) + 1;
};
foreach ($paths as $path) {
    $relative = substr($path, strlen($source) + 1);
    $tokens = array_values(array_filter(token_get_all((string) file_get_contents($path)), static fn ($t): bool => !is_array($t) || !in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)));
    $imports = [];
    $depth = 0;
    foreach ($tokens as $i => $token) {
        if ($token === '{') {
            $depth++;
        } elseif ($token === '}') {
            $depth--;
        }
        if (!is_array($token)) {
            continue;
        }
        $before = $tokens[$i - 1] ?? null;
        $beforeId = is_array($before) ? $before[0] : $before;
        // A global class imported at the top of the file is named by its short name below.
        if ($beforeId === T_USE && $depth === 0 && in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
            $name = strtolower(ltrim($token[1], '\\'));
            if (!str_contains($name, '\\') && isset($knownClasses[$name])) {
                $imports[$name] = true;
            }
            continue;
        }
        if (!in_array($token[0], [T_STRING, T_NAME_FULLY_QUALIFIED], true)) {
            continue;
        }
        $name = strtolower(ltrim($token[1], '\\'));
        $next = $tokens[$i + 1] ?? null;
        $member = in_array($beforeId, [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_CONST], true);
        if ($next === '(' && !$member && $beforeId !== T_NEW && isset($knownFunctions[$name])) {
            if (in_array($name, HOOK_API, true)) {
                $hookCalls++;
            } else {
                $tally($functions, $knownFunctions, $name, $relative);
            }
            continue;
        }
        // A class: written \WP_Name anywhere, or by an imported short name; never a member or a function call.
        $global = $token[0] === T_NAME_FULLY_QUALIFIED || isset($imports[$name]);
        if ($global && !$member && isset($knownClasses[$name]) && !($next === '(' && $beforeId !== T_NEW)) {
            $tally($classes, $knownClasses, $name, $relative);
        }
    }
}

/** A table ordered by uses, then by name, and its uses by family and by file. */
$summarize = static function (array $table): array {
    uksort($table, static fn (string $a, string $b): int => [$table[$b]['uses'], $a] <=> [$table[$a]['uses'], $b]);
    $families = [];
    $files = [];
    foreach ($table as $row) {
        $families[$row['family']] = ($families[$row['family']] ?? 0) + $row['uses'];
        foreach ($row['files'] as $file => $n) {
            $files[$file] = ($files[$file] ?? 0) + $n;
        }
    }
    arsort($families);
    return [$table, $families, $files];
};
[$functions, $functionFamilies, $functionFiles] = $summarize($functions);
[$classes, $classFamilies, $classFiles] = $summarize($classes);
$report = [
    'method' => 'tests/tools/wordpress-names.php: the functions and classes of the reference inventory that public/minn/src uses (calls; the hook API apart; classes named fully qualified or through an import), each with the files using it and the reference file it lives in',
    'summary' => [
        'functionCalls' => array_sum(array_column($functions, 'uses')),
        'functions' => count($functions),
        'hookApi' => $hookCalls,
        'classReferences' => array_sum(array_column($classes, 'uses')),
        'classes' => count($classes),
        'files' => count($functionFiles + $classFiles),
    ],
    'families' => ['functions' => $functionFamilies, 'classes' => $classFamilies],
    'functions' => $functions,
    'classes' => $classes,
];
$encoded = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
$target = "{$root}/contracts/api/wordpress-names.json";
if (in_array('--check', $argv, true)) {
    exit((string) @file_get_contents($target) === $encoded ? 0 : 1);
}
file_put_contents($target, $encoded);
$s = $report['summary'];
printf("%d calls to %d WordPress functions, %d hook API calls, %d references to %d WordPress classes, from %d engine files\n",
    $s['functionCalls'], $s['functions'], $s['hookApi'], $s['classReferences'], $s['classes'], $s['files']);
if (in_array('--list', $argv, true)) {
    foreach (['functions' => [$functionFamilies, $functions], 'classes' => [$classFamilies, $classes]] as $kind => [$families, $table]) {
        echo "\n== {$kind}\n";
        foreach ($families as $family => $uses) {
            $members = array_filter($table, static fn (array $row): bool => $row['family'] === $family);
            echo "{$uses}  {$family}\n    ", implode(', ', array_map(static fn (string $name, array $row): string => "{$name} {$row['uses']}", array_keys($members), $members)), "\n";
        }
    }
}
