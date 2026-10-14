<?php

declare(strict_types=1);

/**
 * The facade's delegates, generated. Every top-level facade function whose
 * body is one statement (a return or a call into the engine) is recorded in
 * contracts/api/facade-delegates.json with that statement: the table is the
 * mapping, and the PHP is rendered from it in one canonical shape (the
 * function's docblock, signature and return type stay as written):
 *
 *     function name(<signature>)<return type>
 *     {
 *         <statement>
 *     }
 *
 * Pluggable (conditional) functions, class methods, and functions that hold
 * logic are not delegates; measure 29 (facade-logic.php) counts the logic.
 *
 *   php tests/tools/facade-gen.php --learn            record every delegate the facade has now (the table follows the code)
 *   php tests/tools/facade-gen.php --write            render every recorded delegate in place (the code follows the table)
 *   php tests/tools/facade-gen.php --set NAME 'STMT'   make NAME a delegate of STMT (its body replaced), record it, render it
 *   php tests/tools/facade-gen.php --check            exit 1 when a delegate is missing from the table, differs from it, or is not in its canonical shape
 */

require __DIR__ . '/facade-parse.php';

$root = dirname(__DIR__, 2);
$facadeDir = "{$root}/public/minn/wp-api";
$tablePath = "{$root}/contracts/api/facade-delegates.json";

/** The top-level files whose functions the table covers (classes are shells, not delegates). */
function delegate_files(string $facadeDir): array
{
    return array_values(array_filter(facade_files($facadeDir), static fn (string $file): bool => !preg_match('#/(classes|requests|simplepie|ai-client)/#', $file)));
}

/** A statement as the canonical body holds it: trimmed, its continuation lines kept as written. */
function canonical_statement(string $body): string
{
    return trim($body);
}

/** The canonical text of a delegate, from `function` to its closing brace and newline. */
function render_delegate(array $definition, string $statement): string
{
    $lines = explode("\n", $statement);
    $first = array_shift($lines);
    $rest = $lines === [] ? '' : "\n" . implode("\n", $lines);
    $returns = $definition['returns'] === '' ? '' : (str_starts_with($definition['returns'], ':') ? $definition['returns'] : ': ' . ltrim($definition['returns'], ': '));
    return 'function ' . ($definition['reference'] ? '&' : '') . $definition['name'] . '(' . $definition['signature'] . ')' . $returns . "\n{\n    " . $first . $rest . "\n}\n";
}

/** The definition's own text as the file holds it (from `function` to the newline after its brace). */
function definition_text(string $src, array $definition): string
{
    $end = $definition['end'];
    if (($src[$end] ?? '') === "\n") {
        $end++;
    }
    return substr($src, $definition['start'], $end - $definition['start']);
}

/** Every delegate the facade defines now, by name: file, definition, source. */
function current_delegates(string $facadeDir): array
{
    $found = [];
    foreach (delegate_files($facadeDir) as $file) {
        $src = (string) file_get_contents($file);
        foreach (facade_definitions($src) as $definition) {
            if ($definition['owner'] !== null || $definition['conditional'] || facade_is_logic($definition) || $definition['statements'] !== 1) {
                continue;
            }
            $found[$definition['name']] = ['file' => substr($file, strlen($facadeDir) + 1), 'definition' => $definition, 'statement' => canonical_statement($definition['body'])];
        }
    }
    ksort($found);
    return $found;
}

function write_table(string $path, array $functions): void
{
    ksort($functions);
    $table = [
        'method' => 'tests/tools/facade-gen.php: every top-level, unconditional facade function whose body is one statement, with that statement; the PHP is rendered from this table (php tests/tools/facade-gen.php --write) and the style suite checks the two agree',
        'summary' => ['delegates' => count($functions)],
        'functions' => $functions,
    ];
    file_put_contents($path, json_encode($table, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
}

function read_table(string $path): array
{
    $table = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
    return is_array($table['functions'] ?? null) ? $table['functions'] : [];
}

/** Renders the recorded delegates of one file in place; returns how many changed. */
function render_file(string $facadeDir, string $relative, array $table): int
{
    $file = "{$facadeDir}/{$relative}";
    $src = (string) file_get_contents($file);
    $changed = 0;
    // Back to front, so earlier offsets stay right.
    $definitions = array_reverse(facade_definitions($src));
    foreach ($definitions as $definition) {
        $entry = $table[$definition['name']] ?? null;
        if ($definition['owner'] !== null || $definition['conditional'] || $entry === null || $entry['file'] !== $relative) {
            continue;
        }
        $old = definition_text($src, $definition);
        $new = render_delegate($definition, $entry['statement']);
        if ($old !== $new) {
            $src = substr($src, 0, $definition['start']) . $new . substr($src, $definition['start'] + strlen($old));
            $changed++;
        }
    }
    if ($changed > 0) {
        file_put_contents($file, $src);
    }
    return $changed;
}

$mode = $argv[1] ?? '--check';

if ($mode === '--learn') {
    $functions = [];
    foreach (current_delegates($facadeDir) as $name => $found) {
        $functions[$name] = ['file' => $found['file'], 'statement' => $found['statement']];
    }
    write_table($tablePath, $functions);
    printf("recorded %d delegates in contracts/api/facade-delegates.json\n", count($functions));
    exit(0);
}

if ($mode === '--write') {
    $table = read_table($tablePath);
    $files = array_unique(array_column($table, 'file'));
    $changed = 0;
    foreach ($files as $relative) {
        $changed += render_file($facadeDir, $relative, $table);
    }
    printf("rendered %d delegates (%d recorded)\n", $changed, count($table));
    exit(0);
}

if ($mode === '--set') {
    [$name, $statement] = [$argv[2] ?? '', trim($argv[3] ?? '')];
    if ($name === '' || $statement === '' || !str_ends_with($statement, ';')) {
        fwrite(STDERR, "usage: facade-gen.php --set NAME 'STATEMENT;'\n");
        exit(2);
    }
    $owner = null;
    foreach (delegate_files($facadeDir) as $file) {
        foreach (facade_definitions((string) file_get_contents($file)) as $definition) {
            if ($definition['owner'] === null && !$definition['conditional'] && $definition['name'] === $name) {
                $owner = substr($file, strlen($facadeDir) + 1);
            }
        }
    }
    if ($owner === null) {
        fwrite(STDERR, "no top-level, unconditional facade function {$name}\n");
        exit(1);
    }
    $table = read_table($tablePath);
    $table[$name] = ['file' => $owner, 'statement' => $statement];
    write_table($tablePath, $table);
    render_file($facadeDir, $owner, $table);
    $check = shell_exec('php -l ' . escapeshellarg("{$facadeDir}/{$owner}") . ' 2>&1');
    if (!str_contains((string) $check, 'No syntax errors')) {
        fwrite(STDERR, (string) $check);
        exit(1);
    }
    printf("%s now delegates: %s\n", $name, $statement);
    exit(0);
}

// --check: the table and the code agree, every delegate is recorded, and each is in its canonical shape.
$table = read_table($tablePath);
$current = current_delegates($facadeDir);
$problems = [];
foreach ($current as $name => $found) {
    if (!isset($table[$name])) {
        $problems[] = "{$name} ({$found['file']}) is a delegate the table does not record: run --learn";
        continue;
    }
    if ($table[$name]['statement'] !== $found['statement'] || $table[$name]['file'] !== $found['file']) {
        $problems[] = "{$name} ({$found['file']}) differs from the table: render with --write or record with --learn";
        continue;
    }
    $src = (string) file_get_contents("{$facadeDir}/{$found['file']}");
    if (definition_text($src, $found['definition']) !== render_delegate($found['definition'], $found['statement'])) {
        $problems[] = "{$name} ({$found['file']}) is not in its canonical shape: run --write";
    }
}
foreach ($table as $name => $entry) {
    if (!isset($current[$name])) {
        $problems[] = "{$name} ({$entry['file']}) is recorded but is no longer a one-statement delegate there";
    }
}
echo json_encode(['delegates' => count($current), 'recorded' => count($table), 'problems' => array_slice($problems, 0, 20), 'problemCount' => count($problems)], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
exit($problems === [] ? 0 : 1);
