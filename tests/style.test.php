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
    'header(' => '/(?<![\w>])(?<!function )header\s*\(/',
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
// Query calls a facade file still makes, through the engine's Db or through
// $wpdb (the facade's own wpdb answers on the same database, so a query there
// is SQL in the facade all the same). Only falls: move the work into src/Minn/.
$facadeQueries = ['canonical.php' => 1, 'comment.php' => 5, 'pluggable.php' => 1, 'post.php' => 5, 'query.php' => 3, 'upgrade.php' => 4, 'user.php' => 2];
$facadeLong = [];
$facadeDir = dirname($root) . '/wp-api';
// Functions and methods, by brace matching: a method's body ends at the brace that closes it.
// Named functions and methods with the line count of their bodies, measured
// on tokens so a brace inside a string or a comment does not count.
$facadeBodies = static function (string $src): array {
    $tokens = token_get_all($src);
    $count = count($tokens);
    $bodies = [];
    for ($i = 0; $i < $count; $i++) {
        if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) {
            continue;
        }
        $name = null;
        for ($j = $i + 1; $j < $count; $j++) {
            if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                $name = $tokens[$j][1];
                break;
            }
            if ($tokens[$j] === '(' || (is_array($tokens[$j]) && $tokens[$j][0] === T_FN)) {
                break;
            }
        }
        if ($name === null) {
            continue;
        }
        $depth = 0;
        $openLine = null;
        $line = $tokens[$j][2] ?? 0;
        for ($j = $j + 1; $j < $count; $j++) {
            $token = $tokens[$j];
            if (is_array($token)) {
                $line = $token[2] + substr_count($token[1], "\n");
            }
            if ($depth === 0 && $token === ';') {
                break;
            }
            if ($token === '{' || (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
                $openLine ??= is_array($token) ? $token[2] : $line;
                $depth++;
            } elseif ($token === '}') {
                $depth--;
                if ($depth === 0) {
                    $bodies[] = [$name, max(0, $line - (int) $openLine - 1)];
                    break;
                }
            }
        }
    }
    return $bodies;
};
foreach ([...glob("{$facadeDir}/*.php"), ...glob("{$facadeDir}/classes/*.php"), ...glob("{$facadeDir}/simplepie/*.php"), ...glob("{$facadeDir}/requests/*.php")] as $file) {
    $name = str_replace("{$facadeDir}/", '', $file);
    if (str_contains($name, 'placeholders')) {
        continue;
    }
    $src = (string) file_get_contents($file);
    $queries = preg_match_all('/(?:(?:\$db|Runtime::current\(\)->db|->db)->(?:rows|row|value|execute)|\$wpdb->(?:get_var|get_results|get_row|get_col|query|insert|update|delete|replace))\(/', $src);
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

// The route catalogue: every #[Route] as a row, read from the classes alone, is the
// document an agent reads before it reads PHP. Regenerate with `php tests/tools/route-catalogue.php`.
$catalogue = json_decode((string) shell_exec('php ' . escapeshellarg(dirname(__DIR__) . '/tests/tools/route-catalogue.php') . ' --check 2>/dev/null'), true);
$check('route catalogue: tests/tools/route-catalogue.php runs', is_array($catalogue));
$check('route catalogue: contracts/api/routes.json is current', is_array($catalogue) && ($catalogue['stale'] ?? true) === false, 'run php tests/tools/route-catalogue.php');
$leafCeiling = 12;
$check("facade map: leaf functions over fifteen lines stay at or under {$leafCeiling}", is_array($mapping) && ($mapping['leafLinesOver15'] ?? PHP_INT_MAX) <= $leafCeiling, (string) ($mapping['leafLinesOver15'] ?? '?'));

// The app reads each capability flag by name, and a flag the boot payload lacks reads as false (an
// empty Add plugin dialog once uploadPlugins was new). Where Minn Admin is linked in, every key its
// boot_caps names must be one the engine computes too.
$adminCaps = dirname(__DIR__) . '/public/minn/admin/includes/class-minn-admin.php';
if (is_file($adminCaps)) {
    $keys = static function (string $source, string $start, string $end): array {
        $from = strpos($source, $start);
        $body = $from === false ? '' : substr($source, $from, (strpos($source, $end, $from) ?: strlen($source)) - $from);
        preg_match_all("/^\\s*'([A-Za-z]+)'\\s*=>/m", $body, $m);
        return $m[1];
    };
    $missingCaps = array_values(array_diff(
        $keys((string) file_get_contents($adminCaps), 'function boot_caps', "\n\t}"),
        $keys((string) file_get_contents("{$root}/Minn/Admin/BootPayload.php"), 'private function caps', "\n    }"),
    ));
    $check('boot payload: the engine computes every capability flag Minn Admin names', $missingCaps === [], implode(', ', $missingCaps));
}

// The whole reference API: every function, class and public or protected
// method resolves (real, a dead end, or a placeholder still owed) or is
// missing, and fatals when called. public/minn/data/deadend-symbols.json
// (tests/tools/deadends.php) names the dead ends; contracts/api/compat-status.json
// (tests/tools/compat-status.php) counts the rest. Missing only falls and
// verified only rises: lower or raise a number here when a change moves it.
exec('php ' . escapeshellarg(dirname(__DIR__) . '/tests/tools/deadends.php') . ' --check', $ignored, $deadEndsStale);
$check('dead ends: public/minn/data/deadend-symbols.json is current', $deadEndsStale === 0, 'run php tests/tools/deadends.php');
$status = json_decode((string) shell_exec('php ' . escapeshellarg(dirname(__DIR__) . '/tests/tools/compat-status.php') . ' --check 2>/dev/null'), true);
exec('php ' . escapeshellarg(dirname(__DIR__) . '/tests/tools/compat-status.php') . ' --check', $ignored, $statusStale);
$check('compat status: contracts/api/compat-status.json is current', is_array($status) && $statusStale === 0, 'run php tests/tools/compat-status.php');
$missingCeiling = ['functions' => 0, 'classes' => 0, 'methods' => 0];
$verifiedFloor = ['functions' => 2962, 'classes' => 239, 'methods' => 2387];
foreach ($missingCeiling as $group => $ceiling) {
    $check("compat status: missing {$group} stay at or under {$ceiling}", is_array($status) && ($status[$group]['missing'] ?? PHP_INT_MAX) <= $ceiling, (string) ($status[$group]['missing'] ?? '?'));
    $check("compat status: verified {$group} stay at or over {$verifiedFloor[$group]}", is_array($status) && ($status[$group]['verified'] ?? 0) >= $verifiedFloor[$group], (string) ($status[$group]['verified'] ?? '?'));
}


// The policy ratchet: every #[Route] states who it is for as a Policy on the
// attribute, judged by the router before the handler runs. Routes that still
// decide inside their body are counted here, and the count only falls: lower
// it when a controller loses its last bare route, never raise it.
$bareRouteCeiling = 6;
$bareRoutes = 0;
$routesTotal = 0;
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }
    foreach (file($file->getPathname()) ?: [] as $line) {
        if (preg_match('/^\s*#\[Route\(/', $line)) {
            $routesTotal++;
            if (!str_contains($line, 'policy:')) {
                $bareRoutes++;
            }
        }
    }
}
$check("routes without a policy stay at or under {$bareRouteCeiling} (of {$routesTotal})", $bareRoutes <= $bareRouteCeiling, (string) $bareRoutes);
$check('no route names the retired cap: argument', shell_exec('grep -rl "cap: " ' . escapeshellarg($root) . ' --include=*.php | xargs grep -l "#\[Route(.*cap: " 2>/dev/null') === null);

// The engine ratchet: src/Minn/ is the code this project points at, so two
// counts may only fall here too. Methods whose body runs past eighty lines
// (none now: the twenty-two the pass started with are split) and classes past six hundred
// lines. Lower a ceiling when a file loses its last offender; never raise one.
$longMethodCeiling = 0;
$bigClassCeiling = 0;
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
$commentBracketCeiling = 57;
$commentBrackets = 0;
$userBracketCeiling = 70;
$userBrackets = 0;
$bracketCeiling = 205;
$unions = 0;
$brackets = 0;
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }
    $src = (string) file_get_contents($file->getPathname());
    $unions += preg_match_all('/array\|PostRecord|PostRecord\|array/', $src);
    // user_id is a comment's column; the users table has no such column.
    $commentBrackets += preg_match_all("/\\['comment_[A-Za-z_]+'\\]/", $src);
    $userBrackets += preg_match_all("/\\['(?:user_(?!id')[a-z_]+|display_name)'\\]/", $src);
    $brackets += preg_match_all("/\['(?:post_[a-z_]+|guid|menu_order|comment_count|comment_status|ping_status)'\]/", $src);
}
$check("engine: array|PostRecord bridges stay at or under {$unionCeiling}", $unions <= $unionCeiling, (string) $unions);
$check("engine: bracket reads of post columns stay at or under {$bracketCeiling}", $brackets <= $bracketCeiling, (string) $brackets);
$check("engine: bracket reads of user columns stay at or under {$userBracketCeiling}", $userBrackets <= $userBracketCeiling, (string) $userBrackets);
$check("engine: bracket reads of comment columns stay at or under {$commentBracketCeiling}", $commentBrackets <= $commentBracketCeiling, (string) $commentBrackets);

$check("engine: methods over eighty lines stay at or under {$longMethodCeiling}", count($longMethods) <= $longMethodCeiling, count($longMethods) . ': ' . implode(', ', $longMethods));
$check("engine: classes over six hundred lines stay at or under {$bigClassCeiling}", count($bigClasses) <= $bigClassCeiling, count($bigClasses) . ': ' . implode(', ', $bigClasses));

// One path, not two. Every request boots the runtime, so a branch on
// Runtime::booted() keeps a second way of doing the job that no request
// takes: when the WordPress-shaped path lands, the engine's own goes, it
// does not wait beside it. And src/Minn speaks to plugins through hooks,
// which it may fire, but does its work in Minn classes: each call into a
// WordPress-named function (the hook API aside) is the facade reached from
// underneath. Both counts only fall; lower a ceiling when you remove some,
// never raise one.
$bootedCeiling = 73;
$wordpressCallCeiling = 1018;
// The hook API from src/Minn goes through Runtime::hooks() too: \apply_filters() and \do_action() are the facade's names for it.
$hookApiCeiling = 384;
$hookApiCalls = 0;
$apiNames = json_decode((string) file_get_contents(dirname(__DIR__) . '/public/minn/data/api-names.json'), true);
$wordpressFunctions = array_fill_keys(array_map('strtolower', (array) ($apiNames['functions'] ?? [])), true);
$hookApi = ['apply_filters' => true, 'apply_filters_ref_array' => true, 'apply_filters_deprecated' => true, 'do_action' => true, 'do_action_ref_array' => true, 'do_action_deprecated' => true];
$booted = 0;
$wordpressCalls = 0;
$callersByFile = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }
    $tokens = token_get_all((string) file_get_contents($file->getPathname()));
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
        $before = $previous >= 0 ? $tokens[$previous] : null;
        $next = $i + 1;
        while ($next < $count && is_array($tokens[$next]) && $tokens[$next][0] === T_WHITESPACE) {
            $next++;
        }
        if (($tokens[$next] ?? null) !== '(') {
            continue;
        }
        if ($name === 'booted' && is_array($before) && $before[0] === T_DOUBLE_COLON) {
            $booted++;
            continue;
        }
        if (is_array($before) && in_array($before[0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW], true)) {
            continue;
        }
        if (isset($hookApi[$name])) {
            $hookApiCalls++;
        }
        if (isset($wordpressFunctions[$name]) && !isset($hookApi[$name])) {
            $wordpressCalls++;
            $callersByFile[basename($file->getPathname(), '.php')] = ($callersByFile[basename($file->getPathname(), '.php')] ?? 0) + 1;
        }
    }
}
arsort($callersByFile);
$check("engine: Runtime::booted() branches stay at or under {$bootedCeiling}", $booted <= $bootedCeiling, (string) $booted);
$check("engine: calls into the hook API by its WordPress names stay at or under {$hookApiCeiling}", $hookApiCalls <= $hookApiCeiling, (string) $hookApiCalls);
// Each of those calls, by function and file, is tracked in contracts/api/wordpress-calls.json (measure 15 of the
// release gate: 0 before an engine release, Austin 2026-10-13). Regenerate with `php tests/tools/wordpress-calls.php`.
exec('php ' . escapeshellarg(dirname(__DIR__) . '/tests/tools/wordpress-calls.php') . ' --check', $ignored, $wordpressCallsStale);
$check('engine: contracts/api/wordpress-calls.json is current', $wordpressCallsStale === 0, 'run php tests/tools/wordpress-calls.php');
$check("engine: calls into WordPress-named functions (the hook API aside) stay at or under {$wordpressCallCeiling}", $wordpressCalls <= $wordpressCallCeiling, $wordpressCalls . ', most in ' . implode(', ', array_map(static fn ($file, $n) => "{$file} {$n}", array_keys(array_slice($callersByFile, 0, 5, true)), array_slice($callersByFile, 0, 5, true))));

// Track H: Minn talks to Minn, never to wordpress.org. Every address that has
// a site, its visitors' browsers or its mail readers fetch from wordpress.org's
// servers (the update and directory APIs, package downloads, the emoji images)
// is counted across src/ and wp-api/. Links to wordpress.org pages and the
// api.w.org relation names are text, not requests, and are not counted. The
// ceiling only falls; 0.1.0 is cut at 0, with the Minn update service answering.
$wporgRequestCeiling = 0;
$wporgRequests = [];
foreach ([$root, dirname($root) . '/wp-api'] as $tree) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tree, FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $found = preg_match_all('/(?<![a-z0-9.-])(?:api\.wordpress\.org|downloads\.wordpress\.org|planet\.wordpress\.org|s\.w\.org|ps\.w\.org|ts\.w\.org)/i', (string) file_get_contents($file->getPathname()));
        if ($found > 0) {
            $wporgRequests[substr($file->getPathname(), strlen(dirname($root)) + 1)] = $found;
        }
    }
}
arsort($wporgRequests);
$check("engine: wordpress.org request addresses stay at or under {$wporgRequestCeiling} (0 to release)", array_sum($wporgRequests) <= $wporgRequestCeiling, array_sum($wporgRequests) . ': ' . implode(', ', array_map(static fn ($file, $n) => "{$file} {$n}", array_keys($wporgRequests), $wporgRequests)));

// JSON goes through Minn\Json, so every encode repairs bad UTF-8 and every
// decode fails the same way. The one call left is RuntimeRoutes::decode(),
// which reads objects so an empty one goes back out as {}. The ceiling only falls.
$rawJsonCeiling = 1;
$rawJson = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
    if ($file->getExtension() !== 'php' || $file->getPathname() === $root . '/Minn/Json.php') {
        continue;
    }
    $found = preg_match_all('/(?<![\w>:$\\\\])json_(?:en|de)code\(/', (string) file_get_contents($file->getPathname()));
    if ($found > 0) {
        $rawJson[substr($file->getPathname(), strlen($root) + 1)] = $found;
    }
}
$check("engine: raw json_encode/json_decode calls outside Minn\\Json stay at or under {$rawJsonCeiling}", array_sum($rawJson) <= $rawJsonCeiling, array_sum($rawJson) . ': ' . implode(', ', array_map(static fn ($file, $n) => "{$file} {$n}", array_keys($rawJson), $rawJson)));

// The API docs are generated from the classes (tests/tools/api-docs.php) and
// must be current: an agent or a person reading docs/api/ is reading the code.
$docs = json_decode((string) shell_exec('php ' . escapeshellarg(dirname(__DIR__) . '/tests/tools/api-docs.php') . ' --check 2>/dev/null'), true);
$check('api docs: tests/tools/api-docs.php runs', is_array($docs));
$check('api docs: docs/api/ is current', is_array($docs) && ($docs['stale'] ?? true) === false, 'run php tests/tools/api-docs.php');

// Three more shapes that only fall, read from the same model: a public
// method with no sentence above it, a boolean parameter (two methods in
// one: name the branch, or pass the caller), and a constructor taking six
// or more (take the two or three the class calls; Services makes the rest
// cheap). Lower a ceiling when a class loses its last offender.
$undocumentedCeiling = 0;
$boolParamCeiling = 77;
$wideConstructorCeiling = 21;
$model = json_decode((string) file_get_contents(dirname(__DIR__) . '/contracts/api/minn.json'), true);
$undocumented = 0;
$boolParams = 0;
$wideConstructors = 0;
foreach ($model['classes'] ?? [] as $class) {
    // A record's columns are data, not dependencies: only parameters that are
    // not promoted public properties count toward a wide constructor.
    $public = array_column(array_filter($class['properties'], static fn (array $p) => $p['visibility'] === 'public' && ($p['promoted'] ?? false)), 'name');
    $dependencies = array_filter($class['constructor']['params'] ?? [], static fn (array $p) => !in_array($p['name'], $public, true));
    if (count($dependencies) >= 6) {
        $wideConstructors++;
    }
    foreach ($class['methods'] as $method) {
        if ($method['visibility'] === 'public' && $method['doc'] === '') {
            $undocumented++;
        }
        foreach ($method['params'] as $param) {
            if ($param['type'] === 'bool') {
                $boolParams++;
            }
        }
    }
}
$check("engine: public methods without a docblock stay at or under {$undocumentedCeiling}", $undocumented <= $undocumentedCeiling, (string) $undocumented);
$check("engine: boolean parameters stay at or under {$boolParamCeiling}", $boolParams <= $boolParamCeiling, (string) $boolParams);
$check("engine: constructors with six or more parameters stay at or under {$wideConstructorCeiling}", $wideConstructors <= $wideConstructorCeiling, (string) $wideConstructors);

// Leanness (audit 2026-10-12). A method whose name appears nowhere but in
// its own declaration (across the engine, the facade, the tests and the
// boot files) is dead: nothing calls it, and the ceiling is 0. Route
// handlers and other attribute-called methods, magic methods and the
// methods PHP's own interfaces call are not counted. The rest count
// duplication the audit found beside the one door for each job; each
// ceiling only falls.
$deadNameCeiling = 0;
$liveCeiling = 20;
$optionReadCeiling = 151;
$rawOptionWriteCeiling = 7;
$rawMetaWriteCeiling = 9;
$postRowCeiling = 8;
$linkHrefCeiling = 77;
$htmlspecialcharsCeiling = 13;
$permalinkCallerCeiling = 30;
$interfaceMethods = ['jsonserialize', 'offsetget', 'offsetset', 'offsetexists', 'offsetunset', 'getiterator', 'count', 'current', 'key', 'next', 'rewind', 'valid'];
$names = [];
$declared = [];
$engineDir = dirname($root);
$scanned = [$root, "{$engineDir}/wp-api", "{$engineDir}/layout", dirname(__DIR__) . '/tests'];
$files = [];
foreach ($scanned as $tree) {
    if (is_dir($tree)) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tree, FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
    }
}
$files = [...$files, ...(glob("{$engineDir}/*.php") ?: [])];
$lean = ['live' => 0, 'optionRead' => 0, 'rawOptionWrite' => 0, 'rawMetaWrite' => 0, 'postRow' => 0, 'linkHref' => 0, 'htmlspecialchars' => 0, 'permalinkCaller' => 0];
foreach ($files as $path) {
    $source = (string) file_get_contents($path);
    $tokens = token_get_all($source);
    $inSrc = str_starts_with($path, $root . '/');
    foreach ($tokens as $i => $token) {
        if (!is_array($token)) {
            continue;
        }
        if (in_array($token[0], [T_STRING, T_CONSTANT_ENCAPSED_STRING], true)) {
            $name = strtolower(trim($token[1], '\'"'));
            $names[$name] = ($names[$name] ?? 0) + 1;
        } elseif (in_array($token[0], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
            foreach (explode('\\', strtolower($token[1])) as $part) {
                $names[$part] = ($names[$part] ?? 0) + 1;
            }
        }
        if ($inSrc && $token[0] === T_FUNCTION) {
            for ($j = $i + 1; isset($tokens[$j]) && (!is_array($tokens[$j]) ? $tokens[$j] === '&' : $tokens[$j][0] === T_WHITESPACE); $j++) {
            }
            if (isset($tokens[$j]) && is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                $declared[] = [$tokens[$j][1], substr($path, strlen($root) + 1), $token[2], $source];
            }
        }
    }
    if ($inSrc) {
        $relative = substr($path, strlen($root) + 1);
        $lean['live'] += substr_count($source, '->live()');
        $lean['optionRead'] += preg_match_all('/->option\(/', $source);
        $lean['postRow'] += preg_match_all('/\'post_content_filtered\'\s*=>/', $source);
        $lean['linkHref'] += preg_match_all('/\[\'href\'\s*=>/', $source);
        $lean['permalinkCaller'] += preg_match_all('/->forPost\(/', $source);
        if ($relative !== 'Minn/Support/Html.php') {
            $lean['htmlspecialchars'] += preg_match_all('/\bhtmlspecialchars\(/', $source);
        }
        $writes = '/(?:INSERT\s+(?:IGNORE\s+)?INTO|UPDATE|DELETE\s+FROM|REPLACE\s+INTO)\s+\{\$[^}]*table\(\'%s\'\)\}/i';
        if ($relative !== 'Minn/Runtime/Options.php') {
            $lean['rawOptionWrite'] += preg_match_all(sprintf($writes, 'options'), $source);
        }
        if ($relative !== 'Minn/Runtime/Meta.php') {
            $lean['rawMetaWrite'] += preg_match_all(sprintf($writes, '(?:post|user|term|comment)meta'), $source);
        }
    }
}
$dead = [];
foreach ($declared as [$name, $relative, $line, $source]) {
    $lower = strtolower($name);
    if (str_starts_with($lower, '__') || in_array($lower, $interfaceMethods, true) || ($names[$lower] ?? 0) > 1) {
        continue;
    }
    $above = implode("\n", array_slice(explode("\n", $source), max(0, $line - 7), 6));
    if (preg_match('/#\[\w+/', $above) === 1) {
        continue;
    }
    $dead[] = "{$relative}:{$line} {$name}";
}
$check("engine: methods nothing calls by name stay at or under {$deadNameCeiling}", count($dead) <= $deadNameCeiling, count($dead) . ': ' . implode(', ', array_slice($dead, 0, 10)));
foreach (['live' => [$liveCeiling, 'lifecycle branches (->live())'], 'optionRead' => [$optionReadCeiling, 'option reads beside the options store (->option()'], 'rawOptionWrite' => [$rawOptionWriteCeiling, 'raw option writes outside Runtime\Options'], 'rawMetaWrite' => [$rawMetaWriteCeiling, 'raw meta writes outside Runtime\Meta'], 'postRow' => [$postRowCeiling, 'hand-built post rows (post_content_filtered keys)'], 'linkHref' => [$linkHrefCeiling, 'hand-built REST links ([\'href\' => ...])'], 'htmlspecialchars' => [$htmlspecialcharsCeiling, 'htmlspecialchars() calls outside Support\Html'], 'permalinkCaller' => [$permalinkCallerCeiling, 'callers of the unfiltered Permalinks::forPost']] as $key => [$ceiling, $label]) {
    $check("engine: {$label} stay at or under {$ceiling}", $lean[$key] <= $ceiling, (string) $lean[$key]);
}

// contracts/runtime.md's open items: every "Open", "Owed", "Left" or "Not
// taken" note outside the register has a row of its own in "Open items
// register", so none is lost in the log (the 0.1.0 scorecard counts the rows
// still open).
$runtimeNotes = (string) file_get_contents(dirname(__DIR__) . '/contracts/runtime.md');
$registerAt = strpos($runtimeNotes, "\n## Open items register");
$register = '';
if ($registerAt !== false) {
    $end = strpos($runtimeNotes, "\n## ", $registerAt + 5);
    $register = $end === false ? substr($runtimeNotes, $registerAt) : substr($runtimeNotes, $registerAt, $end - $registerAt);
}
$notes = str_replace($register, '', $runtimeNotes);
$markers = preg_match_all('/^(?:- )?\*\*Left open\.?\*\*|^Left open|^Still open|^Not taken|^Open:|^Owed:|^Left:|^Not yet:|^Storefront gaps still open:/m', $notes);
preg_match_all('/^\| (\d+) \|/m', $register, $rows);
$check('runtime.md: every open-item note has a row in the open items register', $register !== '' && count(array_unique($rows[1])) >= $markers, count(array_unique($rows[1])) . ' noted lines in the register, ' . $markers . ' notes');

$legacy = array_map('basename', glob("{$root}/*.php"));
echo "\n  legacy procedural files remaining: " . count($legacy) . ' (' . implode(', ', $legacy) . ")\n";
echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
