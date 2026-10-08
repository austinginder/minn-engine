<?php
/**
 * Writes inert stubs for reference symbols the facade lacks, from the
 * inventory's signatures. A stub returns the neutral value of its documented
 * type, logs the call (Runtime\PlaceholderTrace) and does nothing else; it
 * exists so a plugin that references the symbol loads instead of fataling.
 *
 * Two kinds, kept apart: placeholders (wp-api/placeholders.php and
 * wp-api/classes/placeholders/) stand in for behaviour the engine still owes;
 * dead ends (wp-api/deadends.php and wp-api/classes/deadends/, the names
 * tests/tools/deadends.php sorts into wp-admin, the editors, the Customizer
 * and XML-RPC) are inert by design. Dead-end classes load after the facade's
 * own files, so they may extend classes those declare.
 *
 *   php tests/tools/stub-symbols.php <names.json>   placeholders (a {"functions": [], "classes": []} file)
 *   php tests/tools/stub-symbols.php --deadends     dead ends, from public/minn/data/deadend-symbols.json
 */

$root = dirname(__DIR__, 2);
$deadMode = in_array('--deadends', $argv, true);
$deadEnds = json_decode((string) file_get_contents($root . '/public/minn/data/deadend-symbols.json'), true) ?: ['functions' => [], 'classes' => []];
if ($deadMode) {
    $names = ['functions' => array_keys($deadEnds['functions']), 'classes' => array_keys($deadEnds['classes'])];
} else {
    $names = json_decode((string) file_get_contents($argv[1] ?? 'php://stdin'), true) ?: [];
    // A dead end is never a placeholder: the two lists stay apart.
    $names['functions'] = array_values(array_filter($names['functions'] ?? [], static fn (string $n): bool => !isset($deadEnds['functions'][$n])));
    $names['classes'] = array_values(array_filter($names['classes'] ?? [], static fn (string $n): bool => !isset($deadEnds['classes'][$n])));
}
$functions = json_decode((string) file_get_contents($root . '/contracts/api/functions.json'), true);
$classes = json_decode((string) file_get_contents($root . '/contracts/api/classes.json'), true);

$declared = ['functions' => [], 'classes' => []];
$declaredParents = [];
$declaredMethods = [];
// What the facade declares: its own files, and in dead-end mode the placeholders too (a dead end may extend one).
$scanned = array_merge(glob($root . '/public/minn/wp-api/*.php'), glob($root . '/public/minn/wp-api/classes/*.php'), glob($root . '/public/minn/wp-api/simplepie/*.php'), glob($root . '/public/minn/wp-api/requests/*.php'), $deadMode ? glob($root . '/public/minn/wp-api/classes/placeholders/*.php') : []);
foreach ($scanned as $file) {
    if (basename($file) === 'deadends.php' || (basename($file) === 'placeholders.php' && !$deadMode)) {
        continue;
    }
    $src = (string) file_get_contents($file);
    preg_match_all('/^function\s+(\w+)\s*\(/m', $src, $m);
    $declared['functions'] += array_fill_keys(array_map('strtolower', $m[1]), true);
    preg_match_all('/^(?:abstract\s+|final\s+)?(?:class|interface|trait)\s+(\w+)(?:\s+extends\s+(\w+))?/m', $src, $m, PREG_SET_ORDER);
    foreach ($m as $found) {
        $declared['classes'][strtolower($found[1])] = true;
        if (isset($found[2])) {
            $declaredParents[strtolower($found[1])] = strtolower($found[2]);
        }
        // The methods a hand-written class declares, so a placeholder child inherits rather than redeclares them.
        // An abstract method is not inherited: the placeholder must give it a body.
        if (preg_match('/^(?:abstract\s+|final\s+)?(?:class|interface|trait)\s+' . $found[1] . '\b(.*?)^}/ms', $src, $bodyMatch)) {
            preg_match_all('/^([^\n]*)\bfunction\s+(\w+)\s*\(/m', $bodyMatch[1], $fm, PREG_SET_ORDER);
            $concrete = array_filter($fm, static fn (array $line): bool => !str_contains($line[1], 'abstract'));
            $declaredMethods[strtolower($found[1])] = array_fill_keys(array_map(static fn (array $line): string => strtolower($line[2]), $concrete), true);
        }
    }
}
$inheritedMethods = static function (string $class) use (&$inheritedMethods, &$declaredParents, &$declaredMethods, $classes): array {
    $methods = [];
    $parent = $classes[$class]['extends'] ?? null;
    while ($parent !== null) {
        $lower = strtolower($parent);
        if (isset($declaredMethods[$lower])) {
            $methods += $declaredMethods[$lower];
            $parent = isset($declaredParents[$lower]) ? $declaredParents[$lower] : null;
        } else {
            $parent = $classes[$parent]['extends'] ?? null;
        }
    }
    return $methods;
};

$literal = static function ($value) use (&$literal): string {
    if (is_array($value) && isset($value['const']) && count($value) === 1) {
        return (string) $value['const'];
    }
    if (is_array($value)) {
        $items = [];
        $list = array_is_list($value);
        foreach ($value as $k => $v) {
            $items[] = ($list ? '' : var_export($k, true) . ' => ') . $literal($v);
        }
        return '[' . implode(', ', $items) . ']';
    }
    return var_export($value, true);
};
$params = static function (array $list) use ($literal): string {
    $out = [];
    foreach ($list as $p) {
        $out[] = (!empty($p['byRef']) ? '&' : '') . (!empty($p['variadic']) ? '...' : '') . '$' . $p['name'] . (array_key_exists('default', $p) ? ' = ' . $literal($p['default']) : '');
    }
    return implode(', ', $out);
};
$body = static function (?string $returns, string $name): string {
    $type = ltrim((string) $returns, '?');
    if ($returns === 'void' || str_starts_with($name, 'the_') || str_starts_with($name, 'do_') || str_starts_with($name, 'print_') || str_starts_with($name, 'display')) {
        return '';
    }
    if (str_starts_with((string) $returns, '?')) {
        return 'return null;';
    }
    return match ($type) {
        'array' => 'return [];',
        'bool' => 'return false;',
        'string' => 'return \'\';',
        'int', 'float' => 'return 0;',
        default => 'return null;',
    };
};

$fnOut = $deadMode
    ? "<?php\n// Generated by tests/tools/stub-symbols.php --deadends: inert stubs for wp-admin, the block and site editors, the Customizer and XML-RPC, which the engine answers by design with nothing. Do not edit; regenerate.\n"
    : "<?php\n// Generated by tests/tools/stub-symbols.php from the inventory: inert placeholders for symbols plugins reference but the engine does not implement. Do not edit; regenerate.\n";
$written = ['functions' => 0, 'classes' => 0];
foreach ($names['functions'] ?? [] as $name) {
    if (isset($declared['functions'][strtolower($name)]) || !isset($functions[$name])) {
        continue;
    }
    $spec = $functions[$name];
    $b = $body($spec['returns'] ?? null, $name);
    $fnOut .= "\nfunction {$name}(" . $params($spec['params'] ?? []) . ")\n{\n    \\Minn\\Runtime\\PlaceholderTrace::hit('{$name}');\n" . ($b === '' ? '' : "    {$b}\n") . "}\n";
    $written['functions']++;
}
file_put_contents($root . '/public/minn/wp-api/' . ($deadMode ? 'deadends.php' : 'placeholders.php'), $fnOut);

// Classes go into one file in dependency order: a placeholder's parent or
// interface that the facade lacks becomes a placeholder too.
$wanted = [];
$queue = array_values($names['classes'] ?? []);
while ($queue !== []) {
    $name = array_shift($queue);
    if (isset($wanted[$name]) || isset($declared['classes'][strtolower($name)]) || !isset($classes[$name]) || str_contains($name, '\\')) {
        continue;
    }
    $wanted[$name] = true;
    foreach (array_merge((array) ($classes[$name]['extends'] ?? []), (array) ($classes[$name]['implements'] ?? [])) as $dep) {
        if (!isset($declared['classes'][strtolower($dep)]) && !class_exists($dep, false) && !interface_exists($dep, false) && isset($classes[$dep])) {
            $queue[] = $dep;
        }
    }
}
$ordered = [];
$place = static function (string $name) use (&$place, &$ordered, $classes, $wanted, $declared): void {
    if (isset($ordered[$name]) || !isset($wanted[$name])) {
        return;
    }
    foreach (array_merge((array) ($classes[$name]['extends'] ?? []), (array) ($classes[$name]['implements'] ?? [])) as $dep) {
        $place($dep);
    }
    $ordered[$name] = true;
};
foreach (array_keys($wanted) as $name) {
    $place($name);
}
$classOut = $deadMode
    ? "<?php\n// Generated by tests/tools/stub-symbols.php --deadends: inert classes for wp-admin, the editors, the Customizer and XML-RPC, loaded after the facade's own files. Parents come first. Do not edit; regenerate.\n"
    : "<?php\n// Generated by tests/tools/stub-symbols.php from the inventory: inert placeholder classes so plugins that reference them load. Parents come first. Do not edit; regenerate.\n";
// The classes the reference lets plugins hang properties on (captured by reflection).
$dynamic = array_flip(json_decode((string) file_get_contents($root . '/public/minn/data/dynamic-properties.json'), true)['classes'] ?? []);
foreach (array_keys($ordered) as $name) {
    $spec = $classes[$name];
    $kind = $spec['kind'] ?? 'class';
    $keyword = $kind === 'interface' ? 'interface' : ($kind === 'trait' ? 'trait' : (($kind === 'abstract' ? 'abstract ' : '') . 'class'));
    $out = "\n" . (isset($dynamic[$name]) ? "#[AllowDynamicProperties]\n" : '') . "{$keyword} {$name}";
    if (!empty($spec['extends'])) {
        $out .= ' extends ' . $spec['extends'];
    }
    if (!empty($spec['implements'])) {
        $out .= ' implements ' . implode(', ', (array) $spec['implements']);
    }
    $out .= "\n{\n";
    foreach ($spec['constants'] ?? [] as $const => $value) {
        $out .= "    const {$const} = " . $literal(is_array($value) && array_key_exists('value', $value) ? $value['value'] : $value) . ";\n";
    }
    foreach ($spec['properties'] ?? [] as $prop => $p) {
        $out .= '    ' . ($p['visibility'] ?? 'public') . (!empty($p['static']) ? ' static' : '') . ' $' . $prop . (array_key_exists('default', $p) ? ' = ' . $literal($p['default']) : '') . ";\n";
    }
    $inherited = $inheritedMethods($name);
    foreach ($spec['methods'] ?? [] as $method => $m) {
        if (isset($inherited[strtolower($method)])) {
            continue;
        }
        $b = $kind === 'interface' ? null : $body($m['returns'] ?? null, $method);
        $out .= "\n    " . ($m['visibility'] ?? 'public') . (!empty($m['static']) ? ' static' : '') . " function {$method}(" . $params($m['params'] ?? []) . ')' . ($b === null ? ";\n" : "\n    {\n        \\Minn\\Runtime\\PlaceholderTrace::hit('{$name}::{$method}');\n" . ($b === '' ? '' : "        {$b}\n") . "    }\n");
    }
    $classOut .= $out . "}\n";
    $written['classes']++;
}
$classDir = $root . '/public/minn/wp-api/classes/' . ($deadMode ? 'deadends' : 'placeholders');
@mkdir($classDir);
file_put_contents($classDir . '/' . ($deadMode ? 'DeadEnds.php' : 'Placeholders.php'), $classOut);
echo json_encode($written), "\n";
