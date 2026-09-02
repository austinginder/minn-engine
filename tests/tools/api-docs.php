<?php

declare(strict_types=1);

/**
 * The engine's own API, read from the classes themselves: every namespace
 * under src/Minn/, every class with its docblock, constructor, properties,
 * methods (public first, the internals marked by visibility), enum cases,
 * parameter notes, source line ranges, and which classes use which, as
 * Markdown a person or an agent can grep, plus one JSON file for tooling.
 * Signatures come from reflection, so the docs cannot drift from the code;
 * the style suite runs --check and fails when they have.
 *
 *   php tests/tools/api-docs.php            write docs/api/ and contracts/api/minn.json
 *   php tests/tools/api-docs.php --check    print a JSON summary and exit 1 when docs/api/ is stale
 */

$root = dirname(__DIR__, 2);
$src = "{$root}/public/minn/src/Minn";
$out = "{$root}/docs/api";
$checkOnly = in_array('--check', $argv, true);

if (!defined('ABSPATH')) {
    define('ABSPATH', "{$root}/tests/fixtures/");
}
if (!defined('MINN_ENGINE_DIR')) {
    define('MINN_ENGINE_DIR', "{$root}/public/minn");
}
require "{$src}/Autoloader.php";
Minn\Autoloader::register();

/** Every class name under src/Minn, from the file layout (PSR-4). @return list<string> */
function classNames(string $src): array
{
    $names = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $relative = substr($file->getPathname(), strlen($src) + 1, -4);
        $names[] = 'Minn\\' . str_replace('/', '\\', $relative);
    }
    sort($names);
    return $names;
}

/** A docblock's prose, without the comment furniture or the tags. */
function prose(string|false $doc): string
{
    if ($doc === false) {
        return '';
    }
    $lines = [];
    foreach (explode("\n", $doc) as $line) {
        $line = trim((string) preg_replace('#^/\*\*|\*/$|^\*\s?#', '', trim($line)));
        if (str_starts_with($line, '@')) {
            continue;
        }
        $lines[] = $line;
    }
    return trim(preg_replace('/\n{3,}/', "\n\n", implode("\n", $lines)) ?? '');
}

/** The shape annotations a signature cannot carry (array{...}, list<...>, key => value). @return list<string> */
function shapes(string|false $doc): array
{
    if ($doc === false) {
        return [];
    }
    preg_match_all('/@(param|return|var)\s+([^\n]+)/', $doc, $matches, PREG_SET_ORDER);
    $out = [];
    foreach ($matches as [$all, $tag, $rest]) {
        $rest = trim(preg_replace('/\s*\*\/$/', '', $rest) ?? '');
        if (preg_match('/array\{|list<|array<|Closure\(|callable\(/', $rest)) {
            $out[] = "@{$tag} {$rest}";
        }
    }
    return $out;
}

/**
 * What the docblock says about each parameter: its documented type and the
 * words after the name, keyed by parameter name.
 *
 * @return array<string, array{type: string, doc: string}>
 */
function paramNotes(string|false $doc): array
{
    if ($doc === false) {
        return [];
    }
    preg_match_all('/@param\s+(.+?)\s+\$(\w+)[ \t]*([^\n]*)/', $doc, $matches, PREG_SET_ORDER);
    $out = [];
    foreach ($matches as [, $type, $name, $rest]) {
        $out[$name] = ['type' => trim($type), 'doc' => trim(preg_replace('/\s*\*\/$/', '', $rest) ?? '')];
    }
    return $out;
}

/**
 * The parameters as the page shows them: name, type, default, and the
 * docblock's words for it.
 *
 * @return list<array{name: string, type: string, shape: string, default: ?string, doc: string}>
 */
function params(ReflectionMethod $method): array
{
    $notes = paramNotes($method->getDocComment());
    $out = [];
    foreach ($method->getParameters() as $p) {
        $default = null;
        if ($p->isDefaultValueAvailable()) {
            $default = $p->isDefaultValueConstant() ? ($p->getDefaultValueConstantName() ?? 'null') : str_replace("\n", ' ', var_export($p->getDefaultValue(), true));
        }
        $note = $notes[$p->getName()] ?? ['type' => '', 'doc' => ''];
        $type = typeOf($p->getType());
        $out[] = [
            'name' => $p->getName(),
            'type' => $type,
            // The docblock's richer type, only when it says more than the signature.
            'shape' => $note['type'] !== '' && $note['type'] !== $type ? $note['type'] : '',
            'default' => $default,
            'doc' => $note['doc'],
        ];
    }
    return $out;
}

function visibility(ReflectionMethod|ReflectionProperty|ReflectionClassConstant $member): string
{
    return $member->isPrivate() ? 'private' : ($member->isProtected() ? 'protected' : 'public');
}

/**
 * The other Minn classes a file names: its use statements and any fully
 * qualified Minn\ name in the code, minus itself.
 *
 * @param list<string> $known
 * @return list<string>
 */
function usesOf(string $file, string $self, array $known): array
{
    $src = (string) file_get_contents($file);
    preg_match_all('/^use\s+(Minn\\\\[\w\\\\]+)(?:\s+as\s+\w+)?;/m', $src, $uses);
    preg_match_all('/\\\\?(Minn\\\\[A-Z][\w\\\\]+)/', $src, $inline);
    $namespace = substr($self, 0, (int) strrpos($self, '\\'));
    // Unqualified names resolve inside the file's own namespace.
    preg_match_all('/(?<![\w\\\\$>])([A-Z]\w+)(?=::|\s*\(|\s+\$|\s*\||\s*[,)]|\s*\{)/', $src, $bare);
    $found = [];
    foreach ([...$uses[1], ...$inline[1], ...array_map(static fn (string $n) => "{$namespace}\\{$n}", $bare[1])] as $name) {
        $name = ltrim($name, '\\');
        if ($name !== $self && in_array($name, $known, true)) {
            $found[$name] = true;
        }
    }
    $out = array_keys($found);
    sort($out);
    return $out;
}

function typeOf(?ReflectionType $type): string
{
    return $type === null ? 'mixed' : (string) $type;
}

function signature(ReflectionMethod|ReflectionFunction $method): string
{
    $params = [];
    foreach ($method->getParameters() as $p) {
        $part = ($p->getType() !== null ? typeOf($p->getType()) . ' ' : '') . ($p->isVariadic() ? '...' : '') . '$' . $p->getName();
        if ($p->isDefaultValueAvailable()) {
            $default = $p->getDefaultValue();
            $part .= ' = ' . ($p->isDefaultValueConstant() ? ($p->getDefaultValueConstantName() ?? 'null') : str_replace("\n", ' ', var_export($default, true)));
        }
        $params[] = $part;
    }
    $return = $method->hasReturnType() ? ': ' . typeOf($method->getReturnType()) : '';
    return $method->getName() . '(' . implode(', ', $params) . ')' . $return;
}

function kindOf(ReflectionClass $class): string
{
    if ($class->isEnum()) {
        return 'enum';
    }
    if ($class->isInterface()) {
        return 'interface';
    }
    $words = [];
    if ($class->isFinal()) {
        $words[] = 'final';
    }
    if ($class->isReadOnly()) {
        $words[] = 'readonly';
    }
    if ($class->isAbstract() && !$class->isInterface()) {
        $words[] = 'abstract';
    }
    $words[] = 'class';
    return implode(' ', $words);
}

$model = [];
foreach (classNames($src) as $name) {
    if (!class_exists($name) && !interface_exists($name) && !enum_exists($name)) {
        continue;
    }
    $class = new ReflectionClass($name);
    $entry = [
        'name' => $name,
        'short' => $class->getShortName(),
        'namespace' => $class->getNamespaceName(),
        'kind' => kindOf($class),
        'file' => str_replace("{$root}/", '', (string) $class->getFileName()),
        'lines' => $class->getEndLine() - $class->getStartLine() + 1,
        'doc' => prose($class->getDocComment()),
        'implements' => $class->isEnum() ? [] : $class->getInterfaceNames(),
        'extends' => $class->getParentClass() !== false ? $class->getParentClass()->getName() : null,
        'uses' => [],
        'usedBy' => [],
        'constructor' => null,
        'cases' => [],
        'constants' => [],
        'properties' => [],
        'methods' => [],
    ];
    if ($class->isEnum()) {
        foreach ((new ReflectionEnum($name))->getCases() as $case) {
            $entry['cases'][] = ['name' => $case->getName(), 'value' => $case instanceof ReflectionEnumBackedCase ? $case->getBackingValue() : null, 'doc' => prose($case->getDocComment())];
        }
    }
    foreach ($class->getReflectionConstants() as $constant) {
        if ($constant->getDeclaringClass()->getName() !== $name || $constant->isEnumCase()) {
            continue;
        }
        $entry['constants'][] = ['name' => $constant->getName(), 'value' => str_replace("\n", ' ', var_export($constant->getValue(), true)), 'doc' => prose($constant->getDocComment()), 'visibility' => visibility($constant)];
    }
    $constructor = $class->getConstructor();
    if ($constructor !== null && $constructor->getDeclaringClass()->getName() === $name) {
        $entry['constructor'] = [
            'signature' => signature($constructor),
            'doc' => prose($constructor->getDocComment()),
            'shapes' => shapes($constructor->getDocComment()),
            'params' => params($constructor),
            'visibility' => visibility($constructor),
            'line' => $constructor->getStartLine(),
            'end' => $constructor->getEndLine(),
        ];
    }
    foreach ($class->getProperties() as $property) {
        if ($property->getDeclaringClass()->getName() !== $name || ($class->isEnum() && in_array($property->getName(), ['name', 'value'], true))) {
            continue;
        }
        $entry['properties'][] = [
            'name' => $property->getName(),
            'type' => typeOf($property->getType()),
            'readonly' => $property->isReadOnly(),
            'static' => $property->isStatic(),
            'doc' => prose($property->getDocComment()),
            'visibility' => visibility($property),
            'promoted' => $property->isPromoted(),
        ];
    }
    foreach ($class->getMethods() as $method) {
        if ($method->getDeclaringClass()->getName() !== $name || $method->isConstructor()) {
            continue;
        }
        if ($class->isEnum() && in_array($method->getName(), ['cases', 'from', 'tryFrom'], true)) {
            continue;
        }
        $routes = [];
        foreach ($method->getAttributes(Minn\Http\Route::class) as $attribute) {
            $route = $attribute->newInstance();
            $routes[] = $route->method->value . ' ' . $route->pattern . ($route->policy !== null ? ' (' . $route->policy->describe() . ')' : '');
        }
        $entry['methods'][] = [
            'routes' => $routes,
            'name' => $method->getName(),
            'static' => $method->isStatic(),
            'visibility' => visibility($method),
            'signature' => signature($method),
            'doc' => prose($method->getDocComment()),
            'shapes' => shapes($method->getDocComment()),
            'params' => params($method),
            'line' => $method->getStartLine(),
            'end' => $method->getEndLine(),
        ];
    }
    // Public members first, in declaration order within each visibility.
    usort($entry['methods'], static fn (array $a, array $b) => [$a['visibility'] !== 'public', $a['line']] <=> [$b['visibility'] !== 'public', $b['line']]);
    $model[] = $entry;
}

// The graph: which classes each file names, and the reverse.
$known = array_column($model, 'name');
$usedBy = [];
foreach ($model as &$entry) {
    $entry['uses'] = usesOf("{$root}/{$entry['file']}", $entry['name'], $known);
    foreach ($entry['uses'] as $used) {
        $usedBy[$used][] = $entry['name'];
    }
}
unset($entry);
foreach ($model as &$entry) {
    $entry['usedBy'] = $usedBy[$entry['name']] ?? [];
    sort($entry['usedBy']);
}
unset($entry);
$publicMethods = static fn (array $e): array => array_values(array_filter($e['methods'], static fn (array $m) => $m['visibility'] === 'public'));
$internals = static fn (array $e): array => array_values(array_filter($e['methods'], static fn (array $m) => $m['visibility'] !== 'public'));

// ---- render
$byNamespace = [];
foreach ($model as $entry) {
    $byNamespace[$entry['namespace']][] = $entry;
}
ksort($byNamespace);

$slug = static fn (string $namespace): string => strtolower(str_replace('\\', '-', substr($namespace, strlen('Minn\\')) ?: 'minn'));
$md = static fn (string $text): string => str_replace(["\r"], '', $text);

$files = [];
$index = "# Minn API\n\n"
    . "The engine's own classes under `public/minn/src/Minn/`, one page per namespace, read from the code by "
    . "`php tests/tools/api-docs.php`. Signatures are reflection, prose is the docblocks. Regenerate after any change to "
    . "`src/Minn/`; the style suite fails when this folder is stale. The WordPress-facing facade (`wp-api/`) is not here: "
    . "its map is `contracts/api/mappings.json`.\n\n"
    . "| Namespace | Classes | What lives there |\n|---|---|---|\n";
$blurbs = [
    'Minn' => 'the front door, the autoloader, the one database door, the REST error',
    'Minn\\Admin' => 'the minn-admin/v1 namespace and serving the Minn Admin app',
    'Minn\\Auth' => 'passwords, sessions, cookies, nonces, roles and capabilities',
    'Minn\\Blocks' => 'the block parser and renderer',
    'Minn\\Blocks\\Dynamic' => 'dynamic core blocks that render from data',
    'Minn\\Blocks\\Dynamic\\Theme' => 'the template blocks a block theme composes with',
    'Minn\\Cli' => 'the wp verbs the engine answers itself',
    'Minn\\Content' => 'the repositories and records: posts, users, terms, comments, and the render pipeline',
    'Minn\\Cron' => 'scheduled publishing',
    'Minn\\Extension' => 'the extension contract and its seams',
    'Minn\\Front' => 'URL resolution, permalinks, feeds, sitemaps and the public page',
    'Minn\\Html' => 'the HTML tag processor',
    'Minn\\Http' => 'request, response, routing, and the outgoing client',
    'Minn\\Login' => '/wp-login.php and the sign-in surface',
    'Minn\\Mail' => 'sending mail and the notices the engine sends',
    'Minn\\Media' => 'uploads, image sizes and attachment metadata',
    'Minn\\Query' => 'shared SQL fragments',
    'Minn\\Rest' => 'the wp/v2 surface: shapes and controllers',
    'Minn\\Runtime' => 'the WordPress runtime plugins load against',
    'Minn\\Support' => 'escaping, serialized readers, small helpers',
    'Minn\\Theme' => 'the block-theme reader, templates, global styles and the page renderer',
];
foreach ($byNamespace as $namespace => $entries) {
    $file = $slug($namespace) . '.md';
    $index .= "| [`{$namespace}`]({$file}) | " . count($entries) . " | " . ($blurbs[$namespace] ?? '') . " |\n";
    $page = "# `{$namespace}`\n\n" . ($blurbs[$namespace] ?? '') . "\n\n";
    $page .= "| Class | Kind | Lines | Summary |\n|---|---|---|---|\n";
    foreach ($entries as $e) {
        $first = trim(explode("\n", $e['doc'])[0] ?? '');
        $page .= "| [`{$e['short']}`](#" . strtolower($e['short']) . ") | {$e['kind']} | {$e['lines']} | " . $md(str_replace('|', '\\|', $first)) . " |\n";
    }
    foreach ($entries as $e) {
        $page .= "\n## {$e['short']}\n\n`{$e['kind']} {$e['name']}` · `{$e['file']}`" . ($e['implements'] !== [] ? ' · implements `' . implode('`, `', $e['implements']) . '`' : '') . "\n\n";
        if ($e['doc'] !== '') {
            $page .= $md($e['doc']) . "\n\n";
        }
        if ($e['cases'] !== []) {
            $page .= "Cases: " . implode(', ', array_map(static fn (array $c) => '`' . $c['name'] . '`' . ($c['value'] !== null ? ' = `' . var_export($c['value'], true) . '`' : ''), $e['cases'])) . "\n\n";
        }
        if ($e['constants'] !== []) {
            foreach ($e['constants'] as $c) {
                $page .= "- const `{$c['name']}` = `{$c['value']}`" . ($c['doc'] !== '' ? ' — ' . $md($c['doc']) : '') . "\n";
            }
            $page .= "\n";
        }
        if ($e['usedBy'] !== []) {
            $page .= "Used by: " . implode(', ', array_map(static fn (string $n) => "`{$n}`", $e['usedBy'])) . "\n\n";
        }
        if ($e['constructor'] !== null && $e['constructor']['visibility'] === 'public') {
            $page .= "```php\n" . $e['constructor']['signature'] . "\n```\n";
            if ($e['constructor']['doc'] !== '') {
                $page .= $md($e['constructor']['doc']) . "\n";
            }
            foreach ($e['constructor']['shapes'] as $shape) {
                $page .= "- `{$shape}`\n";
            }
            $page .= "\n";
        }
        if ($e['properties'] !== []) {
            foreach ($e['properties'] as $p) {
                if ($p['visibility'] !== 'public') {
                    continue;
                }
                $page .= "- " . ($p['readonly'] ? 'readonly ' : '') . ($p['static'] ? 'static ' : '') . "`{$p['type']} \${$p['name']}`" . ($p['doc'] !== '' ? ' — ' . $md($p['doc']) : '') . "\n";
            }
            $page .= "\n";
        }
        foreach ($publicMethods($e) as $m) {
            $page .= "### " . ($m['static'] ? 'static ' : '') . "`{$m['signature']}`\n\n";
            foreach ($m['routes'] as $route) {
                $page .= "Route: `{$route}`\n\n";
            }
            if ($m['doc'] !== '') {
                $page .= $md($m['doc']) . "\n\n";
            }
            foreach ($m['shapes'] as $shape) {
                $page .= "- `{$shape}`\n";
            }
            if ($m['shapes'] !== []) {
                $page .= "\n";
            }
        }
        if ($internals($e) !== []) {
            $page .= "Internals: " . implode(', ', array_map(static fn (array $m) => "`{$m['name']}()` ({$m['visibility']}, line {$m['line']})", $internals($e))) . "\n\n";
        }
    }
    $files[$file] = $page;
}
$files['README.md'] = $index;

$json = json_encode(['generated' => 'php tests/tools/api-docs.php', 'classes' => $model], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";

$stale = !is_dir($out);
foreach ($files as $name => $content) {
    if (!is_file("{$out}/{$name}") || file_get_contents("{$out}/{$name}") !== $content) {
        $stale = true;
    }
}
foreach (glob("{$out}/*.md") ?: [] as $existing) {
    if (!isset($files[basename($existing)])) {
        $stale = true;
    }
}
if (!is_file("{$root}/contracts/api/minn.json") || file_get_contents("{$root}/contracts/api/minn.json") !== $json) {
    $stale = true;
}
if (is_dir("{$root}/site/minn-site/content") && (!is_file("{$root}/site/minn-site/content/api.json") || file_get_contents("{$root}/site/minn-site/content/api.json") !== $json)) {
    $stale = true;
}

$summary = ['classes' => count($model), 'namespaces' => count($byNamespace), 'methods' => array_sum(array_map(static fn (array $e) => count($publicMethods($e)), $model)), 'internals' => array_sum(array_map(static fn (array $e) => count($internals($e)), $model)), 'stale' => $stale];
if ($checkOnly) {
    echo json_encode($summary), "\n";
    exit($stale ? 1 : 0);
}
if (!is_dir($out)) {
    mkdir($out, 0755, true);
}
foreach (glob("{$out}/*.md") ?: [] as $existing) {
    if (!isset($files[basename($existing)])) {
        unlink($existing);
    }
}
foreach ($files as $name => $content) {
    file_put_contents("{$out}/{$name}", $content);
}
file_put_contents("{$root}/contracts/api/minn.json", $json);
// The marketing site paints /api/ from its own copy, the way it paints /code-size/.
if (is_dir("{$root}/site/minn-site/content")) {
    file_put_contents("{$root}/site/minn-site/content/api.json", $json);
}
echo "docs/api: {$summary['classes']} classes, {$summary['methods']} public methods, {$summary['internals']} internals, {$summary['namespaces']} namespaces\n";
