<?php
/**
 * The release gate's repository scans (/dev-minn-engine-coverage, measures
 * 16, 17, 19 and 25), read from tracked files only:
 * - deadEndCalls: calls from engine code (src/Minn, bootstrap, the facade's
 *   own files) into a dead end (public/minn/data/deadend-symbols.json)
 *   that only a generated stub answers, so the call leans on nothing,
 *   leaving out the ones named below as on purpose;
 * - deadEndsByHand: real code the facade writes for a symbol in a dead-end
 *   area that no compared probe calls, by area ('different' is real by
 *   design and left out). compat-status counts such a symbol as a dead end,
 *   so its proof is never asked for and only this scan sees it: prove it,
 *   or let the stub answer;
 * - floor: the PHP floor docs/style.md names. Every engine file lints on
 *   that binary, and it lacks no function or class the engine names that
 *   the newest PHP has, unless the engine asks function_exists first;
 * - hygiene: tracked files holding a home path, a private key, a cloud key,
 *   or a local fact tests/local.json keeps out of the repository (site
 *   names, the local password, privateNames).
 *
 * php tests/tools/gate-scan.php            prints a summary
 * php tests/tools/gate-scan.php --json     prints the figures as JSON (scorecard.php reads it)
 * php tests/tools/gate-scan.php --list     names every hit
 */

$root = dirname(__DIR__, 2);
$asJson = in_array('--json', $argv, true);
$list = in_array('--list', $argv, true);

$tracked = array_filter(explode("\n", (string) shell_exec('git -C ' . escapeshellarg($root) . ' ls-files')));
$engineFiles = array_values(array_filter($tracked, static fn (string $path): bool => str_starts_with($path, 'public/minn/') && str_ends_with($path, '.php')));
$generated = static fn (string $path): bool => in_array($path, ['public/minn/wp-api/deadends.php', 'public/minn/wp-api/placeholders.php'], true) || str_starts_with($path, 'public/minn/wp-api/classes/deadends/');

$deadEnds = json_decode((string) file_get_contents("{$root}/public/minn/data/deadend-symbols.json"), true);
$deadFunctions = array_change_key_case($deadEnds['functions'] ?? []);
$deadClasses = array_change_key_case($deadEnds['classes'] ?? []);

/**
 * The global functions a file calls and defines, and the global classes it names.
 *
 * @return array{calls: list<array{string, int}>, defines: list<array{string, int}>, classes: list<array{string, int}>, defined: list<string>}
 */
$scan = static function (string $code): array {
    $tokens = array_values(array_filter(token_get_all($code), static fn ($t): bool => !is_array($t) || !in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)));
    $namespaced = false;
    $imports = [];
    $local = [];
    $calls = [];
    $defines = [];
    $classes = [];
    $defined = [];
    $depth = 0;
    $classDepths = [];
    $count = count($tokens);
    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];
        if (!is_array($token)) {
            if ($token === '{') {
                $depth++;
            } elseif ($token === '}') {
                $depth--;
                if ($classDepths !== [] && end($classDepths) === $depth) {
                    array_pop($classDepths);
                }
            }
            continue;
        }
        [$id, $text, $line] = $token;
        if ($id === T_CURLY_OPEN || $id === T_DOLLAR_OPEN_CURLY_BRACES) {
            $depth++;
            continue;
        }
        $previous = $tokens[$i - 1] ?? null;
        $previousId = is_array($previous) ? $previous[0] : $previous;
        $next = $tokens[$i + 1] ?? null;
        if ($id === T_NAMESPACE && is_array($next) && in_array($next[0], [T_STRING, T_NAME_QUALIFIED], true)) {
            $namespaced = true;
            continue;
        }
        if ($id === T_USE && $depth === 0 && is_array($next) && in_array($next[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
            $name = ltrim($next[1], '\\');
            $after = $tokens[$i + 2] ?? null;
            $alias = is_array($after) && $after[0] === T_AS && is_array($tokens[$i + 3] ?? null) ? $tokens[$i + 3][1] : substr((string) strrchr('\\' . $name, '\\'), 1);
            if (str_contains($name, '\\')) {
                $local[strtolower($alias)] = true;
            } else {
                $imports[strtolower($name)] = true;
                $classes[] = [$name, $line];
            }
            continue;
        }
        if (in_array($id, [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true) && $previousId !== T_DOUBLE_COLON && $previousId !== T_NEW && is_array($next) && $next[0] === T_STRING) {
            if (!$namespaced) {
                $defined[] = strtolower($next[1]);
            }
            // The body opens at the next "{", one level deeper than here.
            $classDepths[] = $depth;
            continue;
        }
        if (!in_array($id, [T_STRING, T_NAME_FULLY_QUALIFIED], true)) {
            continue;
        }
        $global = $id === T_NAME_FULLY_QUALIFIED ? substr($text, 1) : $text;
        if (str_contains($global, '\\')) {
            continue;
        }
        $lower = strtolower($global);
        $defining = $previousId === T_FUNCTION || ($previousId === '&' && ($tokens[$i - 2][0] ?? null) === T_FUNCTION);
        if ($defining) {
            if ($classDepths === []) {
                // A namespaced function is the engine's own; a global one is the facade's definition.
                $defined[] = $lower;
                if (!$namespaced) {
                    $defines[] = [$global, $line];
                }
            }
            continue;
        }
        if ($previousId === T_ATTRIBUTE || ($id === T_STRING && isset($local[$lower]))) {
            continue;
        }
        if ($next === '(' && !in_array($previousId, [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_NEW, T_CONST], true)) {
            $calls[] = [$global, $line];
            continue;
        }
        // A class named: new X, X::, instanceof X, extends / implements X, a catch or a type.
        $classUse = $previousId === T_NEW || $previousId === T_INSTANCEOF || $previousId === T_EXTENDS || $previousId === T_IMPLEMENTS || (is_array($next) && $next[0] === T_DOUBLE_COLON);
        if ($classUse && !in_array($lower, ['self', 'static', 'parent'], true) && (!$namespaced || $id === T_NAME_FULLY_QUALIFIED || isset($imports[$lower]))) {
            $classes[] = [$global, $line];
        }
    }
    return ['calls' => $calls, 'defines' => $defines, 'classes' => $classes, 'defined' => $defined];
};

// Measures 16 and 17. A dead end the facade writes by hand is real code in a dead-end area (counted,
// by area: either it earns a place, through "different" or a probe, or it goes); every other dead end
// is answered by a generated stub, and engine code calling one leans on nothing.
$scans = [];
$engineDefined = [];
$byHand = ['functions' => [], 'classes' => []];
foreach ($engineFiles as $path) {
    $code = (string) file_get_contents("{$root}/{$path}");
    $scans[$path] = $scan($code);
    $engineDefined = array_merge($engineDefined, $scans[$path]['defined']);
    if ($generated($path)) {
        continue;
    }
    foreach ($scans[$path]['defines'] as [$name, $line]) {
        if (isset($deadFunctions[strtolower($name)])) {
            $byHand['functions'][strtolower($name)] = "{$path}:{$line} {$name}()";
        }
    }
    if (str_starts_with($path, 'public/minn/wp-api/') && preg_match_all('/^(?:abstract\s+|final\s+|readonly\s+)*(?:class|interface|trait)\s+(\w+)/mi', $code, $m)) {
        foreach ($m[1] as $class) {
            if (isset($deadClasses[strtolower($class)])) {
                $byHand['classes'][strtolower($class)] = "{$path} {$class}";
            }
        }
    }
}
// Proven: called by a probe tests/api.test.php compares with the reference (a class: made, extended or called).
preg_match_all("/=> '([\\w-]+-probe\\.php)'/", (string) file_get_contents("{$root}/tests/api.test.php"), $named);
$probeText = implode("\n", array_map(static fn (string $file): string => (string) @file_get_contents("{$root}/tests/tools/{$file}"), array_unique($named[1])));
$proven = static fn (string $kind, string $name): bool => preg_match($kind === 'functions'
    ? '/(?<![\w$>:\\\\])' . preg_quote($name, '/') . '\s*\(/i'
    : '/\b(?:new|extends|instanceof)\s+\\\\?' . preg_quote($name, '/') . '\b|\b' . preg_quote($name, '/') . '::/i', $probeText) === 1;
$deadEndsByHand = [];
$provenByHand = 0;
foreach (['functions' => $deadFunctions, 'classes' => $deadClasses] as $kind => $areas) {
    foreach ($byHand[$kind] as $lower => $where) {
        if ($areas[$lower] === 'different') {
            continue;
        }
        if ($proven($kind, $lower)) {
            $provenByHand++;
            continue;
        }
        $deadEndsByHand[$areas[$lower]][] = $where;
    }
}

// Stubbed dead ends engine code calls on purpose, each with its reason; any other such call is owed.
$deliberate = [
    '_load_remote_block_patterns' => "the editor's remote patterns come from wordpress.org, which the engine never asks",
    '_load_remote_featured_patterns' => "the editor's remote patterns come from wordpress.org, which the engine never asks",
    '_register_remote_theme_patterns' => "the editor's remote patterns come from wordpress.org, which the engine never asks",
    'ixr_error' => 'only an XML-RPC server prints it, and Minn serves no XML-RPC',
    'wp_recovery_mode' => "omitted (Austin, D4): Minn's own pauses replace the emailed recovery link",
];
$deadEndCalls = [];
$calledFunctions = [];
$namedClasses = [];
foreach ($scans as $path => $found) {
    if ($generated($path)) {
        continue;
    }
    foreach ($found['calls'] as [$name, $line]) {
        $lower = strtolower($name);
        $calledFunctions[$lower][] = "{$path}:{$line}";
        if (isset($deadFunctions[$lower]) && !isset($byHand['functions'][$lower]) && !isset($deliberate[$lower])) {
            $deadEndCalls[] = "{$path}:{$line} {$name}()";
        }
    }
    foreach ($found['classes'] as [$name, $line]) {
        $lower = strtolower($name);
        $namedClasses[$lower][] = "{$path}:{$line}";
        if (isset($deadClasses[$lower]) && !isset($byHand['classes'][$lower]) && !isset($deliberate[$lower])) {
            $deadEndCalls[] = "{$path}:{$line} {$name}";
        }
    }
}

// Measure 19: the floor's binary lints every engine file, and lacks no function or class the engine
// names that the newest PHP has (a name both lack is an extension's or a host's, not the floor's).
$floorVersion = preg_match('/PHP (\d+\.\d+) minimum/', (string) file_get_contents("{$root}/docs/style.md"), $m) === 1 ? $m[1] : '';
$floorBinary = $floorVersion !== '' && is_executable("/opt/homebrew/opt/php@{$floorVersion}/bin/php") ? "/opt/homebrew/opt/php@{$floorVersion}/bin/php" : null;
$newestBinary = is_executable('/opt/homebrew/opt/php/bin/php') ? '/opt/homebrew/opt/php/bin/php' : null;
$floor = ['version' => $floorVersion, 'binary' => $floorBinary !== null && $newestBinary !== null, 'newest' => $newestBinary === null ? '' : trim((string) shell_exec(escapeshellarg($newestBinary) . ' -r "echo PHP_MAJOR_VERSION, \".\", PHP_MINOR_VERSION;"')), 'lintFailures' => [], 'unknown' => []];
if ($floor['binary']) {
    foreach ($engineFiles as $path) {
        exec(escapeshellarg($floorBinary) . ' -l ' . escapeshellarg("{$root}/{$path}") . ' 2>&1', $out, $code);
        if ($code !== 0) {
            $floor['lintFailures'][] = $path;
        }
        $out = [];
    }
    $defined = array_fill_keys($engineDefined, true);
    $question = json_encode([
        'functions' => array_values(array_filter(array_keys($calledFunctions), static fn (string $name): bool => !isset($defined[$name]))),
        'classes' => array_values(array_filter(array_keys($namedClasses), static fn (string $name): bool => !isset($defined[$name]))),
    ]);
    $probe = tempnam(sys_get_temp_dir(), 'minn-floor');
    file_put_contents($probe, '<?php $q = json_decode(stream_get_contents(STDIN), true); echo json_encode(['
        . '"functions" => array_values(array_filter($q["functions"], fn ($f) => !function_exists($f))),'
        . '"classes" => array_values(array_filter($q["classes"], fn ($c) => !class_exists($c) && !interface_exists($c) && !trait_exists($c) && !enum_exists($c)))]);');
    $lacks = static function (string $binary) use ($probe, $question): array {
        $process = proc_open([$binary, $probe], [0 => ['pipe', 'r'], 1 => ['pipe', 'w']], $pipes);
        fwrite($pipes[0], (string) $question);
        fclose($pipes[0]);
        $answer = json_decode((string) stream_get_contents($pipes[1]), true) ?? ['functions' => [], 'classes' => []];
        fclose($pipes[1]);
        proc_close($process);
        return $answer;
    };
    $floorLacks = $lacks($floorBinary);
    $newestLacks = $lacks($newestBinary);
    unlink($probe);
    // A name the engine only reaches behind function_exists / class_exists is guarded, not owed.
    $guarded = static function (string $name, array $sites) use ($root): bool {
        foreach ($sites as $site) {
            [$path] = explode(':', $site);
            if (!preg_match('/(?:function|class|interface)_exists\(\s*[\'"]\\\\?' . preg_quote($name, '/') . '[\'"]/i', (string) file_get_contents("{$root}/{$path}"))) {
                return false;
            }
        }
        return true;
    };
    foreach (array_diff($floorLacks['functions'], $newestLacks['functions']) as $name) {
        if (!$guarded($name, $calledFunctions[$name])) {
            $floor['unknown'][] = "{$name}() at " . $calledFunctions[$name][0];
        }
    }
    foreach (array_diff($floorLacks['classes'], $newestLacks['classes']) as $name) {
        if (!$guarded($name, $namedClasses[$name])) {
            $floor['unknown'][] = "{$name} at " . $namedClasses[$name][0];
        }
    }
}

// Measure 25: what a public repository must never hold.
$local = is_file("{$root}/tests/local.json") ? (json_decode((string) file_get_contents("{$root}/tests/local.json"), true) ?? []) : [];
$private = array_values(array_filter(array_unique(array_merge(
    [(string) ($local['dogfood'] ?? ''), (string) ($local['shop'] ?? ''), (string) ($local['adminPassword'] ?? '')],
    array_map('strval', (array) ($local['privateNames'] ?? [])),
)), static fn (string $fact): bool => strlen($fact) >= 5 && !in_array($fact, ['dogfood', 'shop-dogfood', 'password'], true)));
$patterns = [
    'a home path' => '#/(?:Users|home)/[a-z][a-z0-9._-]+/#',
    'a private key' => '/-----BEGIN (?:[A-Z]+ )?PRIVATE KEY-----/',
    'a cloud access key' => '/\b(?:AKIA[0-9A-Z]{16}|ghp_[A-Za-z0-9]{36}|xox[baprs]-[A-Za-z0-9-]{10,})\b/',
];
// Files that hold such a thing on purpose, each with its reason.
$allowed = ['tests/fixtures/mail/dkim-test.key' => 'a throwaway key the mail suite signs with'];
$hygiene = ['localFacts' => count($private), 'hits' => []];
foreach ($tracked as $path) {
    if (!is_file("{$root}/{$path}") || filesize("{$root}/{$path}") > 4 * 1048576) {
        continue;
    }
    $text = (string) file_get_contents("{$root}/{$path}");
    if (str_contains(substr($text, 0, 8000), "\0")) {
        continue;
    }
    foreach ($patterns as $label => $pattern) {
        if (!isset($allowed[$path]) && preg_match($pattern, $text, $hit, PREG_OFFSET_CAPTURE)) {
            $hygiene['hits'][] = "{$path}:" . (substr_count(substr($text, 0, $hit[0][1]), "\n") + 1) . " {$label}";
        }
    }
    foreach ($private as $index => $fact) {
        if (($at = stripos($text, $fact)) !== false) {
            $hygiene['hits'][] = "{$path}:" . (substr_count(substr($text, 0, $at), "\n") + 1) . ' local fact #' . ($index + 1);
        }
    }
}

$figures = [
    'deadEndCalls' => count($deadEndCalls),
    'deadEndsByHand' => array_sum(array_map('count', $deadEndsByHand)),
    'deadEndsByHandAreas' => array_map('count', $deadEndsByHand),
    'deadEndsByHandProven' => $provenByHand,
    'floor' => ['version' => $floor['version'], 'newest' => $floor['newest'], 'binary' => $floor['binary'], 'lintFailures' => count($floor['lintFailures']), 'unknown' => count($floor['unknown'])],
    'hygiene' => ['localFacts' => $hygiene['localFacts'], 'hits' => count($hygiene['hits'])],
];
if ($asJson) {
    echo json_encode($figures, JSON_UNESCAPED_SLASHES), "\n";
    exit(0);
}
printf("calls into stubbed dead ends, not on purpose: %d\nreal code in dead-end areas no probe proves: %d (%s; %d more proven)\nPHP %s floor: %s, %d files fail its lint, %d names it lacks that PHP %s has\nhygiene: %d hits (%d local facts checked)\n",
    $figures['deadEndCalls'], $figures['deadEndsByHand'], implode(', ', array_map(static fn (string $area, int $n): string => "{$area} {$n}", array_keys($figures['deadEndsByHandAreas']), $figures['deadEndsByHandAreas'])), $provenByHand, $floor['version'],
    $floor['binary'] ? 'binaries found' : 'NO BINARY', $figures['floor']['lintFailures'], $figures['floor']['unknown'], $floor['newest'],
    $figures['hygiene']['hits'], $hygiene['localFacts']);
if ($list) {
    foreach (['dead-end call' => $deadEndCalls, 'by hand' => array_merge(...array_values($deadEndsByHand)), 'lint' => $floor['lintFailures'], 'floor lacks' => $floor['unknown'], 'hygiene' => $hygiene['hits']] as $label => $hits) {
        foreach ($hits as $hit) {
            echo "  {$label}: {$hit}\n";
        }
    }
}
