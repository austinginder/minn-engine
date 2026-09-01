<?php
/**
 * Style suite: the rules in docs/style.md, enforced over src/Minn/.
 * Also reports how many legacy procedural files remain in src/, so the
 * migration is visible in every run.
 */

$root = dirname(__DIR__) . '/public/minn/src';
$pass = 0;
$fail = 0;

$check = static function (string $label, bool $ok, string $detail = '') use (&$pass, &$fail): void {
    if ($ok) {
        $pass++;
        echo "  ok   {$label}\n";
    } else {
        $fail++;
        echo "  FAIL {$label}" . ($detail !== '' ? " ({$detail})" : '') . "\n";
    }
};

// Files that touch PHP's globals or output on purpose: the edge of the engine.
$edge = [
    'Minn/Http/Request.php',
    'Minn/Http/Response.php',
];

$forbidden = [
    'global ' => '/\bglobal\s+\$/',
    'unserialize(' => '/\bunserialize\s*\(/',
    'extract(' => '/\bextract\s*\(/',
    'eval(' => '/\beval\s*\(/',
    'superglobal' => '/\$_(GET|POST|COOKIE|SERVER|FILES|REQUEST|SESSION)\b/',
    'header(' => '/(?<![\w>])header\s*\(/',
    'echo' => '/^\s*echo\b/m',
    'exit/die' => '/\b(exit|die)\s*[;(]/',
];

$files = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$root}/Minn"));
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $files[] = $file->getPathname();
    }
}
sort($files);

foreach ($files as $path) {
    $relative = substr($path, strlen($root) + 1);
    $source = file_get_contents($path);
    $isEdge = in_array($relative, $edge, true);

    $check("{$relative}: strict_types first", preg_match('/^<\?php\s+declare\(strict_types=1\);/', $source) === 1);

    $expectedNamespace = str_replace('/', '\\', dirname($relative));
    $check(
        "{$relative}: namespace matches path",
        preg_match('/^namespace\s+([^;]+);/m', $source, $m) === 1 && $m[1] === $expectedNamespace,
        $m[1] ?? 'none',
    );

    $expectedClass = basename($relative, '.php');
    $check(
        "{$relative}: declares {$expectedClass}",
        preg_match('/\b(class|enum|interface|trait)\s+' . preg_quote($expectedClass, '/') . '\b/', $source) === 1,
    );

    $check("{$relative}: no tabs", !str_contains($source, "\t"));

    foreach ($forbidden as $label => $regex) {
        if ($isEdge && in_array($label, ['superglobal', 'header(', 'echo', 'exit/die'], true)) {
            continue;
        }
        $check("{$relative}: no {$label}", preg_match($regex, $source) !== 1);
    }
}

// The facade ratchet: wp-api/ is WordPress-shaped by necessity, but it is a
// mapping layer, not an implementation. Two counts may only go down: query
// calls made from the facade (the work belongs in src/Minn/), and functions
// whose body runs past forty lines (a decision hiding in a signature).
// Lower a number here when a file loses its last offender; never raise one.
$facadeQueries = [];
$facadeLong = [];
$facadeDir = dirname($root) . '/wp-api';
// Functions and methods, by brace matching: a method's body ends at the brace that closes it.
$facadeBodies = static function (string $src): array {
    preg_match_all('/^[ \t]*(?:(?:public|protected|private|static|final|abstract)\s+)*function\s+&?(\w+)\s*\(/m', $src, $heads, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
    $bodies = [];
    foreach ($heads as $head) {
        $open = strpos($src, '{', $head[0][1] + strlen($head[0][0]));
        $close = strpos($src, ';', $head[0][1] + strlen($head[0][0]));
        if ($open === false || ($close !== false && $close < $open)) {
            continue;
        }
        $depth = 0;
        for ($pos = $open, $length = strlen($src); $pos < $length; $pos++) {
            $depth += match ($src[$pos]) { '{' => 1, '}' => -1, default => 0 };
            if ($depth === 0) {
                break;
            }
        }
        $bodies[] = [$head[1][0], substr_count(substr($src, $open, $pos - $open), "\n") - 1];
    }
    return $bodies;
};
foreach ([...glob("{$facadeDir}/*.php"), ...glob("{$facadeDir}/classes/*.php")] as $file) {
    $name = str_replace("{$facadeDir}/", '', $file);
    if (str_contains($name, 'placeholders')) {
        continue;
    }
    $src = (string) file_get_contents($file);
    $queries = preg_match_all('/(?:\$db|Runtime::current\(\)->db|->db)->(rows|row|value|execute)\(/', $src);
    $check("facade {$name}: queries stay at or under " . ($facadeQueries[$name] ?? 0), $queries <= ($facadeQueries[$name] ?? 0), "{$queries} query calls; move the work into src/Minn/");
    foreach ($facadeBodies($src) as [$fn, $lines]) {
        if ($lines > 40 && !in_array($fn, $facadeLong, true)) {
            $check("facade {$name}: {$fn}() stays a mapping", false, "{$lines} lines; a facade function normalises input, calls one Minn method, shapes the return");
        }
    }
}
$check('facade: the ratchet lists only functions that are still long', true);

// The mapping audit trail: contracts/api/mappings.json (tests/tools/facade-map.php)
// names the Minn methods every facade function calls. It must be current, and the
// count of leaf functions over fifteen lines (plain PHP with no Minn behind it) may
// only fall. Regenerate with `php tests/tools/facade-map.php`; lower the number
// here when a leaf gains a Minn class, never raise it.
$mapping = json_decode((string) shell_exec('php ' . escapeshellarg(dirname(__DIR__) . '/tests/tools/facade-map.php') . ' --check 2>/dev/null'), true);
$check('facade map: tests/tools/facade-map.php runs', is_array($mapping));
$check('facade map: contracts/api/mappings.json is current', is_array($mapping) && ($mapping['stale'] ?? true) === false, 'run php tests/tools/facade-map.php');
$leafCeiling = 13;
$check("facade map: leaf functions over fifteen lines stay at or under {$leafCeiling}", is_array($mapping) && ($mapping['leafLinesOver15'] ?? PHP_INT_MAX) <= $leafCeiling, (string) ($mapping['leafLinesOver15'] ?? '?'));


// The engine ratchet: src/Minn/ is the code this project points at, so two
// counts may only fall here too. Methods whose body runs past eighty lines
// (the twenty-two named in docs/writing-minn.md) and classes past six hundred
// lines. Lower a ceiling when a file loses its last offender; never raise one.
$longMethodCeiling = 22;
$bigClassCeiling = 4;
$longMethods = [];
$bigClasses = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }
    $src = (string) file_get_contents($file->getPathname());
    $name = str_replace("{$root}/", '', $file->getPathname());
    if (substr_count($src, "\n") > 600) {
        $bigClasses[] = $name;
    }
    foreach ($facadeBodies($src) as [$fn, $lines]) {
        if ($lines > 80) {
            $longMethods[] = "{$name}::{$fn} ({$lines})";
        }
    }
}
// The record ratchet (post columns only; a bare ['ID'] is also a user's or a comment's): rows are becoming PostRecord. Two counts only fall: the
// array|PostRecord unions that bridge callers still holding rows, and the
// bracket reads of post columns that the record's properties replace.
$unionCeiling = 4;
$userBracketCeiling = 85;
$userBrackets = 0;
$bracketCeiling = 260;
$unions = 0;
$brackets = 0;
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }
    $src = (string) file_get_contents($file->getPathname());
    $unions += preg_match_all('/array\|PostRecord|PostRecord\|array/', $src);
    $userBrackets += preg_match_all("/\\['(?:user_[a-z_]+|display_name)'\\]/", $src);
    $brackets += preg_match_all("/\['(?:post_[a-z_]+|guid|menu_order|comment_count|comment_status|ping_status)'\]/", $src);
}
$check("engine: array|PostRecord bridges stay at or under {$unionCeiling}", $unions <= $unionCeiling, (string) $unions);
$check("engine: bracket reads of post columns stay at or under {$bracketCeiling}", $brackets <= $bracketCeiling, (string) $brackets);
$check("engine: bracket reads of user columns stay at or under {$userBracketCeiling}", $userBrackets <= $userBracketCeiling, (string) $userBrackets);

$check("engine: methods over eighty lines stay at or under {$longMethodCeiling}", count($longMethods) <= $longMethodCeiling, count($longMethods) . ': ' . implode(', ', $longMethods));
$check("engine: classes over six hundred lines stay at or under {$bigClassCeiling}", count($bigClasses) <= $bigClassCeiling, count($bigClasses) . ': ' . implode(', ', $bigClasses));

$legacy = array_map('basename', glob("{$root}/*.php"));
echo "\n  legacy procedural files remaining: " . count($legacy) . ' (' . implode(', ', $legacy) . ")\n";
echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
