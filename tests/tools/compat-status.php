<?php

declare(strict_types=1);

/**
 * How far the engine is from the reference's whole PHP API, in the two
 * numbers that matter: resolved (the symbol exists, so nothing fatals) and
 * verified (a probe in tests/tools/*-probe.php calls it, so its behaviour is
 * checked against the reference by tests/api.test.php). Every reference
 * function, class and public or protected method is one of:
 *
 * - real: the facade defines it by hand;
 * - deadend: wp-admin, the editors, the Customizer or XML-RPC, answered by a
 *   generated stub, a hand-written class's DeadEndCalls, or real code
 *   (public/minn/data/deadend-symbols.json);
 * - placeholder: a generated stub for behaviour the engine still owes;
 * - missing: nothing, so calling it fatals.
 *
 * Writes contracts/api/compat-status.json.
 *
 *   php tests/tools/compat-status.php           write the file and print the summary
 *   php tests/tools/compat-status.php --check   print the summary as JSON; exit 1 when the file is stale
 *   php tests/tools/compat-status.php --list=missing|placeholder [--kind=functions|classes|methods]
 */

$root = dirname(__DIR__, 2);
$facade = "{$root}/public/minn/wp-api";
$functions = json_decode((string) file_get_contents("{$root}/contracts/api/functions.json"), true);
$classes = json_decode((string) file_get_contents("{$root}/contracts/api/classes.json"), true);
$deadEnds = json_decode((string) file_get_contents("{$root}/public/minn/data/deadend-symbols.json"), true);
$options = getopt('', ['check', 'list:', 'kind:']);

// What the facade defines, and where.
$defined = ['functions' => [], 'classes' => []];
$methods = [];
$parents = [];
$catchAll = [];
$files = [...glob("{$facade}/*.php"), ...glob("{$facade}/*/*.php"), ...glob("{$facade}/classes/*/*.php")];
foreach ($files as $file) {
    $src = (string) file_get_contents($file);
    $kind = str_contains($file, 'deadends') ? 'deadend' : (str_contains($file, 'placeholders') ? 'placeholder' : 'real');
    preg_match_all('/^function\s+&?(\w+)\s*\(/m', $src, $m);
    foreach ($m[1] as $name) {
        $defined['functions'][strtolower($name)] = $kind;
    }
    preg_match_all('/^(?:#\[[^\]]*\]\s*)?(?:abstract\s+|final\s+)?(?:class|interface|trait)\s+(\w+)(?:\s+extends\s+(\w+))?[^{]*\{(.*?)^\}/ms', $src, $found, PREG_SET_ORDER);
    foreach ($found as $class) {
        $lower = strtolower($class[1]);
        $defined['classes'][$lower] = $kind;
        if (($class[2] ?? '') !== '') {
            $parents[$lower] = strtolower($class[2]);
        }
        preg_match_all('/function\s+&?(\w+)\s*\(/', $class[3], $mm);
        $methods[$lower] = array_fill_keys(array_map('strtolower', $mm[1]), true);
        if (str_contains($class[3], 'DeadEndCalls') || isset($methods[$lower]['__call'])) {
            $catchAll[$lower] = true;
        }
    }
}
$has = static function (string $class, string $method) use ($methods, $parents, $catchAll): bool {
    for ($c = strtolower($class), $n = 0; $c !== null && $n < 12; $c = $parents[$c] ?? null, $n++) {
        if (isset($methods[$c][strtolower($method)]) || isset($catchAll[$c])) {
            return true;
        }
    }
    return false;
};

// What the probes call: a name followed by "(" (or "new Name", "Name::").
$probed = [];
foreach (glob("{$root}/tests/tools/*-probe.php") as $probe) {
    preg_match_all('/(?<![\w$>])([A-Za-z_]\w*)\s*(?:\(|::)|new\s+\\\\?([A-Za-z_]\w*)/', (string) file_get_contents($probe), $calls, PREG_SET_ORDER);
    foreach ($calls as $call) {
        $probed[strtolower(($call[2] ?? '') !== '' ? $call[2] : $call[1])] = true;
    }
}

$status = ['functions' => [], 'classes' => [], 'methods' => []];
foreach ($functions as $name => $spec) {
    $kind = $defined['functions'][strtolower($name)] ?? 'missing';
    $status['functions'][$name] = isset($deadEnds['functions'][$name]) && $kind !== 'missing' ? 'deadend' : $kind;
}
foreach ($classes as $name => $spec) {
    if (str_contains($name, '\\')) {
        continue;
    }
    $kind = $defined['classes'][strtolower($name)] ?? 'missing';
    $status['classes'][$name] = isset($deadEnds['classes'][$name]) && $kind !== 'missing' ? 'deadend' : $kind;
    foreach ((array) ($spec['methods'] ?? []) as $method => $m) {
        if (($m['visibility'] ?? 'public') === 'private') {
            continue;
        }
        $methodKind = $kind === 'missing' ? 'missing' : ($has($name, $method) ? $status['classes'][$name] : 'missing');
        $status['methods']["{$name}::{$method}"] = $methodKind;
    }
}

if (isset($options['list'])) {
    foreach (isset($options['kind']) ? [$options['kind']] : ['functions', 'classes', 'methods'] as $group) {
        foreach ($status[$group] as $name => $kind) {
            if ($kind === $options['list']) {
                echo "{$group}\t{$name}\t" . ($functions[$name]['file'] ?? $classes[explode('::', $name)[0]]['file'] ?? '') . "\n";
            }
        }
    }
    exit(0);
}

$summary = [];
foreach ($status as $group => $entries) {
    $counts = ['total' => count($entries), 'real' => 0, 'deadend' => 0, 'placeholder' => 0, 'missing' => 0, 'verified' => 0];
    foreach ($entries as $name => $kind) {
        $counts[$kind]++;
        $symbol = strtolower($group === 'methods' ? explode('::', $name)[0] : $name);
        if ($kind === 'real' && isset($probed[$symbol])) {
            $counts['verified']++;
        }
    }
    $counts['resolved'] = $counts['total'] - $counts['missing'];
    $summary[$group] = $counts;
}
$report = ['method' => 'tests/tools/compat-status.php: every reference function, class and public or protected method, by what the facade has for it; verified = real and named in a probe (for a method, its class)', 'summary' => $summary];
$target = "{$root}/contracts/api/compat-status.json";
$encoded = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
if (isset($options['check'])) {
    echo json_encode($summary), "\n";
    exit((string) @file_get_contents($target) === $encoded ? 0 : 1);
}
file_put_contents($target, $encoded);
foreach ($summary as $group => $c) {
    printf("%-9s %5d: %5d resolved (%d real, %d dead end, %d placeholder), %d missing; %d real verified by a probe\n", $group, $c['total'], $c['resolved'], $c['real'], $c['deadend'], $c['placeholder'], $c['missing'], $c['verified']);
}
echo "wrote contracts/api/compat-status.json\n";
