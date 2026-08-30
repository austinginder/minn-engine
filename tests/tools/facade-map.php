<?php

declare(strict_types=1);

/**
 * Maps the WordPress facade onto the engine: for every function and method
 * in wp-api/, which Minn methods it calls, which other facade functions it
 * composes, and how long it is. Writes contracts/api/mappings.json, the
 * audit trail behind "the facade is a mapping layer".
 *
 *   php tests/tools/facade-map.php            write the file and print the summary
 *   php tests/tools/facade-map.php --leaves   list the leaf functions (no Minn call, no facade call) longest first
 *   php tests/tools/facade-map.php --check    print the summary as JSON and exit 1 when the file on disk is stale
 *
 * Kinds: minn (calls into src/Minn), composes (calls other facade functions
 * only), leaf (plain PHP, no calls into either), noop (a body of at most one
 * line). Static and best-effort: `X::m(`, `new X(`, `_minn_x()->m(` through
 * the helper's return class, and `$v = new X(` / `$v = X::make(` / `$v = _minn_x()`
 * followed by `$v->m(` inside the same body.
 */

$root = dirname(__DIR__, 2);
$facadeDir = "{$root}/public/minn/wp-api";
$leavesOnly = in_array('--leaves', $argv, true);
$checkOnly = in_array('--check', $argv, true);

$files = [...glob("{$facadeDir}/*.php"), ...glob("{$facadeDir}/classes/*.php")];
$files = array_filter($files, static fn (string $f) => !str_contains($f, 'placeholders'));

/** @return list<array{name: string, owner: ?string, body: string, lines: int, file: string}> */
function bodies(string $src, string $file): array
{
    preg_match_all('/^([ \t]*)(?:(?:public|protected|private|static|final|abstract)\s+)*function\s+&?(\w+)\s*\(/m', $src, $heads, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
    preg_match_all('/^(?:final\s+|abstract\s+|readonly\s+)*class\s+(\w+)/m', $src, $classes, PREG_OFFSET_CAPTURE);
    $out = [];
    foreach ($heads as $head) {
        $at = $head[0][1];
        $open = strpos($src, '{', $at + strlen($head[0][0]));
        $semi = strpos($src, ';', $at + strlen($head[0][0]));
        if ($open === false || ($semi !== false && $semi < $open)) {
            continue;
        }
        $depth = 0;
        for ($pos = $open, $len = strlen($src); $pos < $len; $pos++) {
            $depth += match ($src[$pos]) { '{' => 1, '}' => -1, default => 0 };
            if ($depth === 0) {
                break;
            }
        }
        $body = substr($src, $open, $pos - $open + 1);
        $owner = null;
        if ($head[1][0] !== '') {
            foreach ($classes[1] as $class) {
                if ($class[1] < $at) {
                    $owner = $class[0];
                }
            }
        }
        $out[] = ['name' => $head[2][0], 'owner' => $owner, 'body' => $body, 'lines' => max(0, substr_count($body, "\n") - 1), 'file' => $file];
    }
    return $out;
}

function aliases(string $src): array
{
    preg_match_all('/^\s*use\s+([\w\\\\]+)(?:\s+as\s+(\w+))?;/m', $src, $uses, PREG_SET_ORDER);
    $map = [];
    foreach ($uses as $use) {
        $full = ltrim($use[1], '\\');
        $map[$use[2] ?? substr($full, (int) strrpos($full, '\\') + 1)] = $full;
    }
    return $map;
}

function resolve(string $name, array $aliases): ?string
{
    $name = ltrim($name, '\\');
    if (str_starts_with($name, 'Minn\\')) {
        return $name;
    }
    $head = explode('\\', $name)[0];
    if (isset($aliases[$head])) {
        $full = $aliases[$head] . substr($name, strlen($head));
        return str_starts_with($full, 'Minn\\') ? $full : null;
    }
    return null;
}

$facadeFunctions = [];
$parsed = [];
foreach ($files as $file) {
    $src = (string) file_get_contents($file);
    $relative = substr($file, strlen($facadeDir) + 1);
    $parsed[$relative] = ['aliases' => aliases($src), 'bodies' => bodies($src, $relative)];
    foreach ($parsed[$relative]['bodies'] as $body) {
        if ($body['owner'] === null) {
            $facadeFunctions[$body['name']] = true;
        }
    }
}

// Helper return classes: _minn_x() { return new Comments(...) } or a declared return type.
$helperClass = [];
foreach ($parsed as $relative => $unit) {
    foreach ($unit['bodies'] as $body) {
        if ($body['owner'] !== null || !str_starts_with($body['name'], '_minn_')) {
            continue;
        }
        if (preg_match('/return\s+new\s+\\\\?([\w\\\\]+)\s*\(/', $body['body'], $m) && ($class = resolve($m[1], $unit['aliases'])) !== null) {
            $helperClass[$body['name']] = $class;
        } elseif (preg_match('/return\s+\\\\?([\w\\\\]+)::(\w+)\s*\(/', $body['body'], $m) && ($class = resolve($m[1], $unit['aliases'])) !== null) {
            $helperClass[$body['name']] = $class;
        }
    }
}

$functions = [];
$summary = ['minn' => 0, 'composes' => 0, 'leaf' => 0, 'noop' => 0];
$leaves = [];
foreach ($parsed as $relative => $unit) {
    foreach ($unit['bodies'] as $body) {
        $text = $body['body'];
        $minn = [];
        preg_match_all('/(?<![\w$>])(\\\\?[A-Z][\w\\\\]*)::(\w+)\s*\(/', $text, $calls, PREG_SET_ORDER);
        foreach ($calls as $call) {
            $class = resolve($call[1], $unit['aliases']);
            if ($class !== null) {
                $minn[] = "{$class}::{$call[2]}";
            }
        }
        preg_match_all('/new\s+(\\\\?[A-Z][\w\\\\]*)\s*\(/', $text, $news, PREG_SET_ORDER);
        $vars = [];
        foreach ($news as $new) {
            $class = resolve($new[1], $unit['aliases']);
            if ($class !== null) {
                $minn[] = "{$class}::__construct";
            }
        }
        preg_match_all('/\$(\w+)\s*=\s*(?:new\s+(\\\\?[A-Z][\w\\\\]*)\s*\(|(\\\\?[A-Z][\w\\\\]*)::\w+\s*\(|(_minn_\w+)\s*\()/', $text, $assigns, PREG_SET_ORDER);
        foreach ($assigns as $assign) {
            $class = null;
            if ($assign[2] !== '') {
                $class = resolve($assign[2], $unit['aliases']);
            } elseif (($assign[3] ?? '') !== '') {
                $class = resolve($assign[3], $unit['aliases']);
            } elseif (($assign[4] ?? '') !== '') {
                $class = $helperClass[$assign[4]] ?? null;
            }
            if ($class !== null) {
                $vars[$assign[1]] = $class;
            }
        }
        preg_match_all('/(_minn_\w+)\s*\(\)\s*->\s*(\w+)\s*\(/', $text, $chains, PREG_SET_ORDER);
        foreach ($chains as $chain) {
            if (isset($helperClass[$chain[1]])) {
                $minn[] = "{$helperClass[$chain[1]]}::{$chain[2]}";
            }
        }
        preg_match_all('/\$(\w+)\s*->\s*(\w+)\s*\(/', $text, $methods, PREG_SET_ORDER);
        foreach ($methods as $method) {
            if (isset($vars[$method[1]])) {
                $minn[] = "{$vars[$method[1]]}::{$method[2]}";
            }
        }
        preg_match_all('/Runtime::current\(\)\s*->\s*(\w+)(?:\s*->\s*(\w+))?/', $text, $runtime, PREG_SET_ORDER);
        foreach ($runtime as $r) {
            $minn[] = 'Minn\\Runtime\\Runtime::' . $r[1] . (($r[2] ?? '') !== '' ? '->' . $r[2] : '');
        }
        $minn = array_values(array_unique($minn));
        sort($minn);
        preg_match_all('/(?<![\w$>:\\\\])([a-z_][a-z0-9_]*)\s*\(/', $text, $plain);
        $composes = array_values(array_unique(array_filter($plain[1], static fn (string $fn) => isset($facadeFunctions[$fn]) && $fn !== $body['name'])));
        sort($composes);
        $kind = $body['lines'] <= 1 ? 'noop' : ($minn !== [] ? 'minn' : ($composes !== [] ? 'composes' : 'leaf'));
        $summary[$kind]++;
        $key = $body['owner'] === null ? $body['name'] : "{$body['owner']}::{$body['name']}";
        $functions[$key] = ['file' => $relative, 'lines' => $body['lines'], 'kind' => $kind, 'minn' => $minn, 'composes' => $composes];
        if ($kind === 'leaf') {
            $leaves[] = [$key, $body['lines'], $relative];
        }
    }
}
ksort($functions);
usort($leaves, static fn (array $a, array $b) => $b[1] <=> $a[1]);

if ($leavesOnly) {
    foreach ($leaves as [$key, $lines, $file]) {
        printf("%4d  %-60s %s\n", $lines, $key, $file);
    }
    exit(0);
}

$minnMethods = [];
foreach ($functions as $entry) {
    foreach ($entry['minn'] as $method) {
        $minnMethods[$method] = true;
    }
}
$report = [
    'generated' => gmdate('Y-m-d'),
    'method' => 'static scan of public/minn/wp-api by tests/tools/facade-map.php; kinds: minn (calls src/Minn), composes (calls other facade functions only), leaf (plain PHP), noop (at most one line)',
    'summary' => $summary + ['functions' => count($functions), 'minnMethods' => count($minnMethods), 'leafLinesOver15' => count(array_filter($leaves, static fn (array $l) => $l[1] > 15))],
    'functions' => $functions,
];
$encoded = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
if ($checkOnly) {
    $onDisk = json_decode((string) @file_get_contents("{$root}/contracts/api/mappings.json"), true);
    $fresh = ['functions' => $report['functions'], 'summary' => $report['summary']];
    $stale = !is_array($onDisk) || ['functions' => $onDisk['functions'] ?? null, 'summary' => $onDisk['summary'] ?? null] !== $fresh;
    echo json_encode($report['summary'] + ['stale' => $stale]), "\n";
    exit($stale ? 1 : 0);
}
file_put_contents("{$root}/contracts/api/mappings.json", $encoded);
printf("%d functions: %d minn, %d composes, %d leaf, %d noop; %d distinct Minn methods; %d leaves over 15 lines\n", count($functions), $summary['minn'], $summary['composes'], $summary['leaf'], $summary['noop'], count($minnMethods), $report['summary']['leafLinesOver15']);
echo "wrote contracts/api/mappings.json\n";
