<?php

declare(strict_types=1);

/**
 * Replays the engine's git history and measures, at every commit, how much
 * of the WordPress interface Minn covers. Writes
 * site/minn-site/content/coverage.json for the theme's /coverage/ page.
 *
 *   php tests/tools/coverage-history.php [site root]
 *   (site root: a Cove site whose plugins to weigh by; default shop-dogfood)
 *
 * The yardstick is today's reference inventory (contracts/api/functions.json,
 * classes.json, hooks.json), held fixed so every commit is measured against
 * the same WordPress. At each commit the facade (public/minn/wp-api) is
 * tokenized: a function or method with a body that does something is
 * implemented, a body that only returns a constant (null, false, '', [], 0,
 * an argument) is constant, the generated stubs are placeholders, and the
 * rest is missing. Hooks count when the engine fires the name literally. The
 * plugin numbers replay the symbol gate against that commit's facade with
 * the site's cached scan (minn_runtime_symbols), so they read "today's
 * plugins against the engine as it was": a plugin or theme passes when every
 * reference function it calls is defined by gate time (the defaults load
 * later; pluggable.php counts as provided) and every reference class it uses
 * exists. Redeclarations are left out: the gate only refuses one in a
 * plugin's main file, which the folder scan does not record. Must-use files
 * are not gated. Only committed work counts.
 */

$root = dirname(__DIR__, 2);
$site = $argv[1] ?? '~/Cove/Sites/shop-dogfood.localhost';
$out = "{$root}/site/minn-site/content/coverage.json";

$git = static function (string ...$args) use ($root): string {
    $cmd = 'git -C ' . escapeshellarg($root) . ' ' . implode(' ', array_map('escapeshellarg', $args));
    $result = shell_exec($cmd);
    if (!is_string($result)) {
        fwrite(STDERR, "git failed: {$cmd}\n");
        exit(1);
    }
    return $result;
};

/** Blob contents through one `git cat-file --batch`. */
final class Blobs
{
    /** @var resource */
    private $process;
    /** @var array<int, resource> */
    private array $pipes = [];

    public function __construct(string $root)
    {
        $process = proc_open(['git', '-C', $root, 'cat-file', '--batch'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w']], $this->pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('could not start git cat-file');
        }
        $this->process = $process;
    }

    public function read(string $sha): string
    {
        fwrite($this->pipes[0], $sha . "\n");
        fflush($this->pipes[0]);
        $header = explode(' ', trim((string) fgets($this->pipes[1])));
        $size = (int) ($header[2] ?? 0);
        $data = '';
        while (strlen($data) < $size) {
            $chunk = fread($this->pipes[1], $size - strlen($data));
            if ($chunk === false || $chunk === '') {
                break;
            }
            $data .= $chunk;
        }
        fgets($this->pipes[1]);
        return $data;
    }
}

/**
 * What one PHP file declares and fires: global functions and class methods
 * with the kind of body each has, classes with their parents, hook names
 * fired by literal, and its code / comment / blank line counts.
 */
function analyse_php(string $src): array
{
    try {
        $tokens = token_get_all($src, TOKEN_PARSE);
    } catch (\ParseError) {
        $tokens = @token_get_all($src);
    }
    $functions = [];
    $classes = [];
    $namespace = '';
    $stack = [];          // one entry per open brace: ['class', name] | ['fn', name, class|null, start] | ['other']
    $pending = null;      // a declaration waiting for its opening brace
    $count = count($tokens);
    $prev = null;         // previous significant token
    for ($i = 0; $i < $count; $i++) {
        $t = $tokens[$i];
        $id = is_array($t) ? $t[0] : $t;
        if (in_array($id, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }
        if ($id === T_NAMESPACE && $stack === []) {
            $name = '';
            for ($j = $i + 1; $j < $count; $j++) {
                $u = $tokens[$j];
                if (is_array($u) && in_array($u[0], [T_STRING, T_NAME_QUALIFIED], true)) {
                    $name .= $u[1];
                } elseif (!is_array($u) || $u[0] !== T_WHITESPACE) {
                    break;
                }
            }
            $namespace = $name;
        } elseif (in_array($id, [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true) && $prev !== T_DOUBLE_COLON) {
            $name = next_name($tokens, $i + 1);
            if ($name !== null) {
                $extends = null;
                for ($j = $i + 1; $j < $count && $tokens[$j] !== '{'; $j++) {
                    if (is_array($tokens[$j]) && $tokens[$j][0] === T_EXTENDS) {
                        $extends = next_name($tokens, $j + 1);
                        break;
                    }
                }
                $full = ltrim($namespace . '\\' . $name, '\\');
                $pending = ['class', $full];
                $classes[strtolower($full)] = ['name' => $full, 'extends' => $extends === null ? null : strtolower(resolve_name($extends, $namespace)), 'interface' => $id === T_INTERFACE, 'methods' => []];
            }
        } elseif ($id === T_FUNCTION) {
            $name = next_name($tokens, $i + 1, true);
            if ($name !== null) {
                $owner = null;
                for ($k = count($stack) - 1; $k >= 0; $k--) {
                    if ($stack[$k][0] === 'class' || $stack[$k][0] === 'fn') {
                        $owner = $stack[$k][0] === 'class' ? $stack[$k][1] : null;
                        break;
                    }
                }
                $full = $owner === null ? ltrim($namespace . '\\' . $name, '\\') : $name;
                // abstract and interface methods end at ';' before any '{'
                $j = $i + 1;
                $depth = 0;
                for (; $j < $count; $j++) {
                    $u = $tokens[$j];
                    if ($u === '(') {
                        $depth++;
                    } elseif ($u === ')') {
                        $depth--;
                    } elseif ($depth === 0 && ($u === '{' || $u === ';')) {
                        break;
                    }
                }
                if (($tokens[$j] ?? ';') === ';') {
                    if ($owner !== null) {
                        $classes[strtolower($owner)]['methods'][strtolower($name)] = 'implemented';
                    }
                    $i = $j;
                    $prev = ';';
                    continue;
                }
                $pending = ['fn', $full, $owner];
            }
        } elseif ($id === '{' || $id === T_CURLY_OPEN || $id === T_DOLLAR_OPEN_CURLY_BRACES) {
            if ($pending !== null && $id === '{') {
                $stack[] = $pending[0] === 'fn' ? ['fn', $pending[1], $pending[2], $i] : ['class', $pending[1]];
                $pending = null;
            } else {
                $stack[] = ['other'];
            }
        } elseif ($id === '}') {
            $top = array_pop($stack);
            if ($top !== null && $top[0] === 'fn') {
                $kind = body_kind(array_slice($tokens, $top[3] + 1, $i - $top[3] - 1));
                if ($top[2] === null) {
                    $functions[strtolower($top[1])] = $kind;
                } else {
                    $classes[strtolower($top[2])]['methods'][strtolower($top[1])] = $kind;
                }
            }
        } elseif ($id === ';' && $pending !== null && $pending[0] === 'class') {
            $pending = null;
        }
        $prev = $id;
    }

    $hooks = [];
    if (preg_match_all("/(?:apply_filters|apply_filters_ref_array|apply_filters_deprecated|do_action|do_action_ref_array|do_action_deprecated)\\(\\s*'([^'\$]+)'/", $src, $m)) {
        $hooks = $m[1];
    }
    if (preg_match_all("/->(?:action|filter|filterWithout|actionRefArray|filterRefArray)\\(\\s*'([^'\$]+)'/", $src, $m)) {
        $hooks = array_merge($hooks, $m[1]);
    }
    return ['functions' => $functions, 'classes' => $classes, 'hooks' => array_values(array_unique($hooks)), 'routes' => substr_count($src, '#[Route(')] + count_php($tokens, $src);
}

/** The next declared name after $from (skipping whitespace and a by-reference &), or null for a closure. */
function next_name(array $tokens, int $from, bool $function = false): ?string
{
    for ($j = $from, $n = count($tokens); $j < $n; $j++) {
        $u = $tokens[$j];
        if (is_array($u) && in_array($u[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }
        if ($function && ($u === '&' || (is_array($u) && in_array($u[0], [T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG, T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG], true)))) {
            continue;
        }
        if (is_array($u) && in_array($u[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
            return $u[1];
        }
        return null;
    }
    return null;
}

function resolve_name(string $name, string $namespace): string
{
    if (str_starts_with($name, '\\')) {
        return ltrim($name, '\\');
    }
    return $namespace === '' ? $name : $namespace . '\\' . $name;
}

/** 'constant' when the body only returns a constant or an argument, else 'implemented'. */
function body_kind(array $tokens): string
{
    $text = '';
    foreach ($tokens as $t) {
        if (is_array($t) && in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }
        $text .= is_array($t) ? $t[1] : $t;
    }
    $text = trim($text);
    return preg_match('/^(return\s+(null|false|true|\'\'|""|\[\]|0|-1|array\(\)|\$\w+)\s*;|return;|)$/i', $text) === 1 ? 'constant' : 'implemented';
}

/** Code lines (anything but comments and blanks), comment-only lines, blank lines. */
function count_php(array $tokens, string $src): array
{
    $lines = substr_count($src, "\n") + (str_ends_with($src, "\n") || $src === '' ? 0 : 1);
    $code = [];
    $comment = [];
    $line = 1;
    foreach ($tokens as $t) {
        $text = is_array($t) ? $t[1] : $t;
        $start = is_array($t) ? $t[2] : $line;
        $span = substr_count($text, "\n");
        if (is_array($t) && in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true)) {
            for ($l = $start; $l <= $start + $span - (str_ends_with($text, "\n") ? 1 : 0); $l++) {
                $comment[$l] = true;
            }
        } elseif (!is_array($t) || $t[0] !== T_WHITESPACE) {
            if (!is_array($t) || $t[0] !== T_INLINE_HTML || trim($text) !== '') {
                for ($l = $start; $l <= $start + $span; $l++) {
                    $code[$l] = true;
                }
            }
        }
        $line = $start + $span;
    }
    $codeLines = count($code);
    $commentLines = count(array_diff_key($comment, $code));
    return ['code' => $codeLines, 'comment' => $commentLines, 'blank' => max(0, $lines - $codeLines - $commentLines)];
}

function nonblank(string $text): int
{
    return count(array_filter(explode("\n", $text), static fn ($l) => trim($l) !== ''));
}

/** Which part of the repository a path belongs to, or null when it is not counted. */
function kind_of(string $path): ?string
{
    if (preg_match('#\.(png|jpe?g|gif|webp|woff2?|ttf|ico|zip|sql|mo|po)$#i', $path) || str_contains($path, '/vendor/')) {
        return null;
    }
    return match (true) {
        str_starts_with($path, 'public/minn/wp-api/') && str_ends_with($path, '.php') => 'facade',
        (str_starts_with($path, 'public/minn/') || str_starts_with($path, 'src/') || preg_match('#^public/[^/]+\.php$#', $path) === 1) && str_ends_with($path, '.php') => 'engine',
        str_starts_with($path, 'public/minn/assets/') && preg_match('#\.(js|css)$#', $path) === 1 => 'assets',
        str_starts_with($path, 'tests/') && preg_match('#\.(php|js|sh|mjs)$#', $path) === 1 => 'tests',
        str_starts_with($path, 'contracts/') && str_ends_with($path, '.md') => 'contracts',
        str_starts_with($path, 'extensions/') && preg_match('#\.(php|js|css)$#', $path) === 1 => 'extensions',
        (str_starts_with($path, 'docs/') && !str_starts_with($path, 'docs/api/') && str_ends_with($path, '.md')) || in_array($path, ['readme.md', 'README.md', 'CLAUDE.md'], true) => 'docs',
        default => null,
    };
}

// ---- the yardstick: today's reference inventory -----------------------------------------
$json = static fn (string $path) => json_decode((string) file_get_contents("{$root}/{$path}"), true);
$refFunctions = [];
foreach ($json('contracts/api/functions.json') as $name => $info) {
    $refFunctions[strtolower($name)] = ['name' => $name, 'file' => (string) ($info['file'] ?? '')];
}
$refClasses = [];
foreach ($json('contracts/api/classes.json') as $name => $info) {
    $refClasses[strtolower($name)] = ['name' => $name, 'file' => (string) ($info['file'] ?? ''), 'methods' => array_map('strtolower', array_keys($info['methods'] ?? []))];
}
$refHooks = [];
foreach ($json('contracts/api/hooks.json') as $name => $info) {
    if ($name === '' || str_contains((string) $name, '{') || str_contains((string) $name, '$')) {
        continue;
    }
    $files = (array) ($info['files'] ?? []);
    $refHooks[(string) $name] = array_filter($files, static fn ($f) => !str_starts_with($f, 'wp-admin')) === [] ? 'wp-admin' : 'wp-includes';
}
$apiNames = $json('public/minn/data/api-names.json');
$gateFunctions = array_fill_keys(array_map('strtolower', $apiNames['functions']), true);
$gateClasses = array_fill_keys(array_map('strtolower', $apiNames['classes']), true);
$area = static fn (string $file): string => str_starts_with($file, 'wp-admin') ? 'wp-admin' : 'wp-includes';

// ---- the plugins to weigh by: the site's cached symbol scan ------------------------------
$scan = json_decode((string) shell_exec('cd ' . escapeshellarg("{$site}/public") . ' && wp option get minn_runtime_symbols --format=json 2>/dev/null'), true) ?: [];
if ($scan === []) {
    fwrite(STDERR, "no minn_runtime_symbols on {$site}: run tests/tools/runtime-report.php there first\n");
    exit(1);
}
$plugins = [];
$callers = [];   // reference function => how many folders call it
foreach ($scan as $slug => $info) {
    $s = $info['scan'] ?? [];
    $declared = array_change_key_case((array) ($s['declaredGlobal'] ?? []));
    $declaredClasses = array_change_key_case((array) ($s['declaredClasses'] ?? []));
    $guarded = array_change_key_case((array) ($s['guarded'] ?? []));
    $calls = array_values(array_unique(array_map('strtolower', (array) ($s['calls'] ?? []))));
    $needF = array_values(array_filter($calls, static fn ($n) => isset($gateFunctions[$n]) && !isset($declared[$n]) && !isset($guarded[$n])));
    $needC = array_values(array_filter(array_unique(array_map('strtolower', (array) ($s['classes'] ?? []))), static fn ($n) => isset($gateClasses[$n]) && !isset($declaredClasses[$n]) && !isset($guarded[$n])));
    $kind = str_ends_with((string) $slug, '.php') ? 'mu-plugin' : (is_dir("{$site}/public/wp-content/themes/{$slug}") ? 'theme' : 'plugin');
    $plugins[] = ['slug' => (string) $slug, 'kind' => $kind, 'functions' => $needF, 'classes' => $needC];
    foreach ($calls as $call) {
        if (isset($refFunctions[$call])) {
            $callers[$call] = ($callers[$call] ?? 0) + 1;
        }
    }
}
$folderKinds = array_count_values(array_column($plugins, 'kind'));

// ---- the commits -----------------------------------------------------------------------
$log = $git('log', '--reverse', '--first-parent', '--format=%x01%H%x00%ct%x00%ai%x00%s', '--numstat', 'HEAD');
$commits = [];
$types = ['NEW' => '📦', 'IMPROVE' => '👌', 'FIX' => '🐛', 'SECURITY' => '🔒', 'DOC' => '📖', 'TEST' => '🤖', 'RELEASE' => '🚀'];
foreach (array_slice(explode("\x01", $log), 1) as $chunk) {
    [$head, $rest] = array_pad(explode("\n", $chunk, 2), 2, '');
    [$sha, $ct, $ai, $subject] = explode("\x00", $head);
    $added = $deleted = 0;
    foreach (explode("\n", trim($rest)) as $line) {
        $parts = explode("\t", $line);
        if (count($parts) === 3 && $parts[0] !== '-') {
            $path = preg_replace('/\{[^{}]*=> ([^{}]*)\}/', '$1', $parts[2]);
            $path = str_replace('//', '/', (string) $path);
            if (kind_of(trim((string) preg_replace('/^.* => /', '', $path))) !== null) {
                $added += (int) $parts[0];
                $deleted += (int) $parts[1];
            }
        }
    }
    $type = 'OTHER';
    foreach ($types as $key => $emoji) {
        if (str_starts_with($subject, $emoji) || preg_match('/^\W*' . $key . ':/u', $subject)) {
            $type = $key;
            break;
        }
    }
    $commits[] = ['sha' => $sha, 't' => (int) $ct, 'ai' => $ai, 'subject' => $subject, 'type' => $type, 'added' => $added, 'deleted' => $deleted];
}

$blobs = new Blobs($root);
$cache = [];
$measure = static function (string $sha, string $path) use (&$cache, $blobs): array {
    $probe = preg_match('#^tests/tools/[a-z0-9-]+-probe\.php$#', $path) === 1;
    $key = $sha . ':' . ($probe ? 'probe' : (str_ends_with($path, '.php') ? 'php' : 'text'));
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    $text = $blobs->read($sha);
    if (str_ends_with($path, '.php')) {
        $m = analyse_php($text);
        if ($probe) {
            preg_match_all('/(?<![\w>$:])([a-z_][a-z0-9_]*)\s*\(/', $text, $pm);
            $m['probeCalls'] = array_values(array_unique(array_map('strtolower', $pm[1])));
        }
    } elseif (str_starts_with($path, 'contracts/fixtures/api/') && str_ends_with($path, '.json')) {
        $data = json_decode($text, true);
        $m = ['rows' => is_array($data) && array_is_list($data) ? count($data) : 1];
    } else {
        $m = [];
    }
    $m['lines'] = nonblank($text);
    return $cache[$key] = $m;
};

$born = [];      // "group:item" => commit index it first appeared in
$rows = [];
$last = [];
foreach ($commits as $i => $c) {
    $tree = [];
    foreach (explode("\n", $git('ls-tree', '-r', $c['sha'])) as $line) {
        if ($line === '' || !str_contains($line, "\t")) {
            continue;
        }
        [$meta, $path] = explode("\t", $line, 2);
        $meta = explode(' ', $meta);
        if (($meta[1] ?? '') === 'blob') {
            $tree[$path] = $meta[2];
        }
    }
    $functions = [];         // lower name => kind
    $atGate = [];            // defined when the gate runs: not the defaults (loaded later) nor pluggable.php (defined after the plugins)
    $classes = [];           // lower name => class info
    $placeholderClasses = [];
    $pluggable = [];
    $hooks = [];
    $lines = ['engine' => 0, 'facade' => 0, 'assets' => 0, 'tests' => 0, 'contracts' => 0, 'docs' => 0, 'extensions' => 0];
    $engine = ['code' => 0, 'comment' => 0, 'blank' => 0];
    $suites = [];
    $probes = [];
    $probeRows = 0;
    $fixtures = 0;
    $probed = [];
    $routes = 0;
    foreach ($tree as $path => $sha) {
        $kind = kind_of($path);
        $isFixture = str_starts_with($path, 'contracts/fixtures/') && str_ends_with($path, '.json');
        if ($isFixture) {
            $fixtures++;
        }
        if ($kind === null && !(str_starts_with($path, 'contracts/fixtures/api/') && str_ends_with($path, '.json'))) {
            continue;
        }
        $m = $measure($sha, $path);
        if (isset($m['rows'])) {
            $probeRows += $m['rows'];
        }
        if ($kind === null) {
            continue;
        }
        if ($kind === 'engine' || $kind === 'facade') {
            $lines[$kind] += $m['code'];
            if ($kind === 'engine') {
                foreach ($engine as $k => $_) {
                    $engine[$k] += $m[$k];
                }
            }
            foreach ($m['hooks'] as $h) {
                $hooks[$h] = true;
            }
            $routes += $m['routes'];
        } else {
            $lines[$kind] += $m['lines'];
        }
        if ($kind === 'facade') {
            $placeholder = str_ends_with($path, 'wp-api/placeholders.php');
            $placeholderClassFile = str_contains($path, 'wp-api/classes/placeholders/');
            $late = str_contains($path, 'wp-api/defaults/') || str_ends_with($path, 'wp-api/pluggable.php');
            foreach ($m['functions'] as $name => $k) {
                $functions[$name] = $placeholder ? 'placeholder' : ($functions[$name] ?? $k);
                if (!$late) {
                    $atGate[$name] = true;
                }
            }
            if (str_ends_with($path, 'wp-api/pluggable.php')) {
                $pluggable = array_keys($m['functions']);
            }
            foreach ($m['classes'] as $name => $info) {
                if ($placeholderClassFile) {
                    $placeholderClasses[$name] = true;
                    $info['methods'] = array_map(static fn () => 'placeholder', $info['methods']);
                }
                $classes[$name] = $info;
            }
        }
        if (preg_match('#^tests/(?:browser/)?([a-z0-9-]+)\.test\.(?:php|js)$#', $path, $s)) {
            $suites[(str_contains($path, '/browser/') ? 'browser/' : '') . $s[1]] = true;
        }
        if (preg_match('#^tests/tools/([a-z0-9-]+)-probe\.php$#', $path, $s)) {
            $probes[$s[1]] = true;
            foreach ($m['probeCalls'] ?? [] as $name) {
                $probed[$name] = true;
            }
        }
    }

    // functions, by area
    $f = ['wp-includes' => ['implemented' => 0, 'constant' => 0, 'placeholder' => 0], 'wp-admin' => ['implemented' => 0, 'constant' => 0, 'placeholder' => 0]];
    $probedAll = 0;   // implemented reference functions a probe calls, so their answers are compared with the reference's
    foreach ($refFunctions as $lower => $info) {
        $k = $functions[$lower] ?? null;
        if ($k !== null) {
            $f[$area($info['file'])][$k]++;
            if ($k === 'implemented' && isset($probed[$lower])) {
                $probedAll++;
            }
        }
    }
    // classes and their own methods (a method answers when the class or a parent in the facade declares it)
    $methodOf = static function (string $class, string $method) use ($classes): ?string {
        for ($seen = 0, $c = $class; $c !== null && isset($classes[$c]) && $seen < 12; $seen++, $c = $classes[$c]['extends']) {
            if (isset($classes[$c]['methods'][$method])) {
                return $classes[$c]['methods'][$method];
            }
        }
        return null;
    };
    $cl = ['real' => 0, 'placeholder' => 0];
    $me = ['implemented' => 0, 'constant' => 0, 'placeholder' => 0];
    $realClasses = [];
    foreach ($refClasses as $lower => $info) {
        if (!isset($classes[$lower])) {
            continue;
        }
        if (isset($placeholderClasses[$lower])) {
            $cl['placeholder']++;
        } else {
            $cl['real']++;
            $realClasses[] = $info['name'];
        }
        foreach ($info['methods'] as $method) {
            $k = $methodOf($lower, $method);
            if ($k !== null) {
                $me[$k]++;
            }
        }
    }
    // hooks
    $hk = ['wp-includes' => 0, 'wp-admin' => 0];
    foreach ($refHooks as $name => $bucket) {
        if (isset($hooks[$name])) {
            $hk[$bucket]++;
        }
    }
    // what the plugins call, and which would pass the gate
    $used = ['implemented' => 0, 'constant' => 0, 'placeholder' => 0];
    $uses = ['implemented' => 0, 'constant' => 0, 'placeholder' => 0];
    $verified = $verifiedUses = 0;
    foreach ($callers as $name => $n) {
        $k = $functions[$name] ?? null;
        if ($k !== null) {
            $used[$k]++;
            $uses[$k] += $n;
        }
        if (isset($probed[$name])) {
            $verified++;
            $verifiedUses += $n;
        }
    }
    $loading = [];
    $provided = $atGate + array_fill_keys($pluggable ?? [], true);
    $refused = [];
    foreach ($plugins as $p) {
        if ($p['kind'] === 'mu-plugin') {
            continue;   // must-use files are not gated
        }
        $why = array_merge(
            array_map(static fn ($n) => "calls {$n}", array_filter($p['functions'], static fn ($n) => !isset($provided[$n]))),
            array_map(static fn ($n) => "uses {$n}", array_filter($p['classes'], static fn ($n) => !isset($classes[$n]))),
        );
        if ($why === []) {
            $loading[] = $p['slug'];
        } else {
            $refused[$p['slug']] = $why;
        }
    }

    foreach (['class' => $realClasses, 'suite' => array_keys($suites), 'probe' => array_keys($probes), 'plugin' => $loading] as $group => $items) {
        foreach ($items as $item) {
            $born["{$group}:{$item}"] ??= $i;
        }
    }
    $rows[] = [
        'functions' => $f, 'probedAll' => $probedAll, 'classes' => $cl, 'methods' => $me, 'hooks' => $hk,
        'used' => $used, 'uses' => $uses, 'verified' => $verified, 'verifiedUses' => $verifiedUses,
        'loading' => count($loading), 'lines' => $lines, 'engine' => $engine,
        'suites' => count($suites), 'probes' => count($probes), 'probeRows' => $probeRows, 'fixtures' => $fixtures, 'routes' => $routes,
        'loadingSet' => $loading, 'realClasses' => $realClasses, 'suiteSet' => array_keys($suites), 'probeSet' => array_keys($probes),
    ];
    $last = ['refused' => $refused, 'functions' => $functions, 'classes' => $classes, 'placeholderClasses' => $placeholderClasses, 'methodOf' => $methodOf, 'probed' => $probed, 'hooks' => $hooks];
}

// ---- detail at HEAD: every reference file, every gap ---------------------------------------
$byFile = [];
$entry = static fn () => ['functions' => ['implemented' => [], 'constant' => [], 'placeholder' => [], 'missing' => []], 'methods' => ['implemented' => 0, 'constant' => 0, 'placeholder' => 0, 'missing' => 0], 'classes' => []];
foreach ($refFunctions as $lower => $info) {
    $file = $info['file'] ?: '(unknown)';
    $byFile[$file] ??= $entry();
    $byFile[$file]['functions'][$last['functions'][$lower] ?? 'missing'][] = $info['name'];
}
foreach ($refClasses as $lower => $info) {
    $file = $info['file'] ?: '(unknown)';
    $byFile[$file] ??= $entry();
    $state = !isset($last['classes'][$lower]) ? 'missing' : (isset($last['placeholderClasses'][$lower]) ? 'placeholder' : 'real');
    $counts = ['implemented' => 0, 'constant' => 0, 'placeholder' => 0, 'missing' => 0];
    foreach ($info['methods'] as $method) {
        $k = $state === 'missing' ? null : ($last['methodOf'])($lower, $method);
        $counts[$k ?? 'missing']++;
        $byFile[$file]['methods'][$k ?? 'missing']++;
    }
    $byFile[$file]['classes'][] = ['name' => $info['name'], 'state' => $state, 'methods' => $counts];
}
ksort($byFile);
$files = [];
foreach ($byFile as $file => $e) {
    foreach ($e['functions'] as &$names) {
        sort($names, SORT_STRING | SORT_FLAG_CASE);
    }
    unset($names);
    $e['functions']['implemented'] = count($e['functions']['implemented']);   // the page names only what is not there yet
    $files[] = ['file' => $file, 'area' => $area($file), 'functions' => $e['functions'], 'methods' => $e['methods'], 'classes' => $e['classes']];
}

$gaps = [];
foreach ($callers as $name => $n) {
    $k = $last['functions'][$name] ?? 'missing';
    if ($k === 'placeholder' || $k === 'missing') {
        $gaps[] = ['name' => $refFunctions[$name]['name'], 'plugins' => $n, 'kind' => $k, 'file' => $refFunctions[$name]['file']];
    }
}
usort($gaps, static fn ($a, $b) => [$b['plugins'], $a['name']] <=> [$a['plugins'], $b['name']]);
$unverified = [];
foreach ($callers as $name => $n) {
    if (($last['functions'][$name] ?? '') === 'implemented' && !isset($last['probed'][$name])) {
        $unverified[] = ['name' => $refFunctions[$name]['name'], 'plugins' => $n];
    }
}
usort($unverified, static fn ($a, $b) => [$b['plugins'], $a['name']] <=> [$a['plugins'], $b['name']]);

// ---- weeks (Monday to Sunday, in the committer's time) that have commits ----------------------
$tzOf = static function (string $ai): int {
    $off = substr($ai, -5);
    return ($off[0] === '-' ? -1 : 1) * ((int) substr($off, 1, 2) * 60 + (int) substr($off, 3, 2));
};
$weeks = [];
foreach ($commits as $i => $c) {
    $local = $c['t'] + $tzOf($c['ai']) * 60;
    $monday = gmdate('Y-m-d', $local - ((int) gmdate('N', $local) - 1) * 86400);
    $weeks[$monday] ??= ['start' => $monday, 'first' => $i, 'last' => $i];
    $weeks[$monday]['last'] = $i;
}
$weeks = array_values($weeks);
$weekOf = static function (int $index) use ($weeks): int {
    foreach ($weeks as $w => $week) {
        if ($index <= $week['last']) {
            return $w;
        }
    }
    return count($weeks) - 1;
};

$arrivals = [];
foreach ($born as $key => $index) {
    [$group, $item] = explode(':', $key, 2);
    $headSet = match ($group) {
        'class' => end($rows)['realClasses'],
        'suite' => end($rows)['suiteSet'],
        'probe' => end($rows)['probeSet'],
        'plugin' => end($rows)['loadingSet'],
    };
    if (!in_array($item, $headSet, true)) {
        continue;   // gone by HEAD
    }
    $arrivals[$group][] = ['name' => $item, 'sha' => substr($commits[$index]['sha'], 0, 7), 't' => $commits[$index]['t'], 'i' => $index, 'week' => $weekOf($index)];
}
foreach ($arrivals as &$list) {
    usort($list, static fn ($a, $b) => [$a['i'], strtolower($a['name'])] <=> [$b['i'], strtolower($b['name'])]);
}
unset($list);
// the site's folders stay unnamed: some are private; the page only counts them
$arrivals['plugin'] = array_map(static function (array $a) use ($plugins): array {
    $kind = 'plugin';
    foreach ($plugins as $p) {
        if ($p['slug'] === $a['name']) {
            $kind = $p['kind'];
        }
    }
    unset($a['name']);
    return ['kind' => $kind] + $a;
}, $arrivals['plugin'] ?? []);

$hours = array_fill(0, 24, 0);
foreach ($commits as $c) {
    $hours[(int) substr($c['ai'], 11, 2)]++;
}

$totals = static fn (array $kinds): int => array_sum($kinds);
$head = end($commits);
$headRow = end($rows);
$data = [
    'generated' => gmdate('Y-m-d\TH:i:s\Z'),
    'tool' => 'php tests/tools/coverage-history.php',
    'head' => ['sha' => substr($head['sha'], 0, 7), 't' => $head['t'], 'subject' => $head['subject']],
    'reference' => [
        'functions' => ['all' => count($refFunctions), 'wp-includes' => count(array_filter($refFunctions, static fn ($f) => $area($f['file']) === 'wp-includes')), 'wp-admin' => count(array_filter($refFunctions, static fn ($f) => $area($f['file']) === 'wp-admin'))],
        'classes' => count($refClasses),
        'methods' => array_sum(array_map(static fn ($c) => count($c['methods']), $refClasses)),
        'hooks' => array_count_values($refHooks),
    ],
    'site' => [
        'folders' => count($plugins),
        'gated' => count(array_filter($plugins, static fn ($p) => $p['kind'] !== 'mu-plugin')),
        'kinds' => $folderKinds,
        'functions' => count($callers),
        'uses' => array_sum($callers),
    ],
    'totals' => ['added' => array_sum(array_column($commits, 'added')), 'deleted' => array_sum(array_column($commits, 'deleted'))],
    'commitTypes' => array_map(static fn ($k, $e) => ['key' => $k, 'emoji' => $e, 'count' => count(array_filter($commits, static fn ($c) => $c['type'] === $k))], array_keys($types), array_values($types)),
    'hours' => $hours,
    'weeks' => array_map(static fn ($w) => $w + ['commits' => $w['last'] - $w['first'] + 1, 'firstT' => $commits[$w['first']]['t'], 'lastT' => $commits[$w['last']]['t']], $weeks),
    'commits' => array_map(static function (array $c, array $r) use ($tzOf): array {
        return [
            'sha' => substr($c['sha'], 0, 7), 't' => $c['t'], 'tz' => $tzOf($c['ai']), 'subject' => $c['subject'], 'type' => $c['type'],
            'added' => $c['added'], 'deleted' => $c['deleted'],
            'functions' => $r['functions'], 'probedAll' => $r['probedAll'], 'classes' => $r['classes'], 'methods' => $r['methods'], 'hooks' => $r['hooks'],
            'used' => $r['used'], 'uses' => $r['uses'], 'verified' => $r['verified'], 'verifiedUses' => $r['verifiedUses'], 'loading' => $r['loading'],
            'lines' => $r['lines'], 'engine' => $r['engine'], 'suites' => $r['suites'], 'probes' => $r['probes'], 'probeRows' => $r['probeRows'], 'fixtures' => $r['fixtures'], 'routes' => $r['routes'],
        ];
    }, $commits, $rows),
    'arrivals' => $arrivals,
    'files' => $files,
    'gaps' => $gaps,
    'unverified' => array_slice($unverified, 0, 40),
];
@mkdir(dirname($out), 0755, true);
file_put_contents($out, json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");

$fn = $headRow['functions'];
$pct = static fn (int $a, int $b): string => $b === 0 ? '-' : sprintf('%.1f%%', 100 * $a / $b);
printf("%d commits, %d weeks -> %s (%d KB)\n", count($commits), count($weeks), substr($out, strlen($root) + 1), (int) (filesize($out) / 1024));
foreach (['wp-includes', 'wp-admin'] as $a) {
    printf("  functions %-11s implemented %d of %d (%s), constant %d, placeholder %d\n", $a, $fn[$a]['implemented'], $data['reference']['functions'][$a], $pct($fn[$a]['implemented'], $data['reference']['functions'][$a]), $fn[$a]['constant'], $fn[$a]['placeholder']);
}
printf("  classes real %d, placeholder %d of %d; methods implemented %d of %d\n", $headRow['classes']['real'], $headRow['classes']['placeholder'], count($refClasses), $headRow['methods']['implemented'], $data['reference']['methods']);
printf("  hooks wp-includes %d of %d, wp-admin %d of %d\n", $headRow['hooks']['wp-includes'], $data['reference']['hooks']['wp-includes'] ?? 0, $headRow['hooks']['wp-admin'], $data['reference']['hooks']['wp-admin'] ?? 0);
printf("  %s: %d folders call %d functions (%d uses): implemented %s, by use %s, verified by use %s; %d of %d pass the gate\n", basename($site), count($plugins), count($callers), array_sum($callers), $pct($headRow['used']['implemented'], count($callers)), $pct($headRow['uses']['implemented'], array_sum($callers)), $pct($headRow['verifiedUses'], array_sum($callers)), $headRow['loading'], $data['site']['gated']);
printf("  suites %d, probes %d, probe rows %d, fixtures %d, engine code %d lines, facade %d\n", $headRow['suites'], $headRow['probes'], $headRow['probeRows'], $headRow['fixtures'], $headRow['lines']['engine'], $headRow['lines']['facade']);
foreach ($last['refused'] as $slug => $why) {
    fwrite(STDERR, "  refused at HEAD: {$slug}: " . implode(', ', array_slice($why, 0, 6)) . "\n");
}
