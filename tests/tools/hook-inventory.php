<?php
/**
 * Hook inventory: every hook name the reference fires, with kind and
 * argument count, found by scanning for the firing calls, and the names of
 * the functions and methods that fire each one (so a hook fired only from
 * symbols Minn leaves behind can count as a dead end). Names are interface;
 * nothing else is read. A dynamic name keeps its fixed text and marks the
 * rest {*}: "pre_option_{$option}" and 'pre_option_' . $option are both
 * pre_option_{*}. Run: php tests/tools/hook-inventory.php
 */

$root = dirname(__DIR__, 2) . '/wp-reference/';
$out = dirname(__DIR__, 2) . '/contracts/api/hooks.json';

/**
 * The named functions and methods of a file, as [start, end, name] byte
 * ranges: "Class::method", "function", closures folded into whatever
 * encloses them.
 *
 * @return list<array{0: int, 1: int, 2: string}>
 */
function minn_hook_scopes(string $src): array
{
    $tokens = token_get_all($src);
    $offset = 0;
    $depth = 0;
    $class = null;
    $classDepth = null;
    $pendingClass = false;
    $pendingFunction = null;
    $open = [];
    $scopes = [];
    $previous = null;
    foreach ($tokens as $token) {
        $text = is_array($token) ? $token[1] : $token;
        $id = is_array($token) ? $token[0] : null;
        if (in_array($id, [T_CLASS, T_TRAIT, T_INTERFACE, T_ENUM], true) && $previous !== T_NEW && $previous !== T_DOUBLE_COLON) {
            $pendingClass = true;
        } elseif ($id === T_STRING && $pendingClass) {
            $class = $text;
            $pendingClass = false;
        } elseif ($id === T_FUNCTION) {
            $pendingFunction = '';
        } elseif ($id === T_STRING && $pendingFunction === '') {
            $pendingFunction = $text;
        } elseif ($text === '(' && $pendingFunction === '') {
            $pendingFunction = null; // a closure
        } elseif ($text === ';' && $pendingFunction !== null) {
            $pendingFunction = null; // an abstract or interface method
        }
        if ($text === '{' || $id === T_CURLY_OPEN || $id === T_DOLLAR_OPEN_CURLY_BRACES) {
            $depth++;
            if ($class !== null && $classDepth === null && !$pendingClass && $pendingFunction === null) {
                $classDepth = $depth;
            } elseif (is_string($pendingFunction) && $pendingFunction !== '') {
                $open[] = [$offset, $depth, $classDepth !== null ? "{$class}::{$pendingFunction}" : $pendingFunction];
                $pendingFunction = null;
            }
        } elseif ($text === '}') {
            if ($open !== [] && end($open)[1] === $depth) {
                [$start, , $name] = array_pop($open);
                $scopes[] = [$start, $offset, $name];
            }
            if ($classDepth === $depth) {
                $class = null;
                $classDepth = null;
            }
            $depth--;
        }
        if ($id !== T_WHITESPACE && $id !== T_COMMENT && $id !== T_DOC_COMMENT) {
            $previous = $id ?? $text;
        }
        $offset += strlen($text);
    }
    return $scopes;
}

/** The innermost named function or method around a byte offset, or null at the file's top level. */
function minn_hook_scope_at(array $scopes, int $at): ?string
{
    $found = null;
    $width = PHP_INT_MAX;
    foreach ($scopes as [$start, $end, $name]) {
        if ($at > $start && $at < $end && $end - $start < $width) {
            $found = $name;
            $width = $end - $start;
        }
    }
    return $found;
}

$hooks = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
foreach ($it as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $rel = substr($file->getPathname(), strlen($root));
    if (!str_starts_with($rel, 'wp-includes/') && !str_starts_with($rel, 'wp-admin/') && !in_array($rel, ['wp-settings.php', 'wp-load.php', 'wp-login.php', 'wp-cron.php', 'wp-comments-post.php', 'xmlrpc.php', 'wp-signup.php', 'wp-activate.php', 'wp-mail.php', 'wp-trackback.php'], true)) {
        continue;
    }
    // Comments blanked to spaces of the same length: a docblock's usage example fires nothing.
    $src = '';
    foreach (token_get_all((string) file_get_contents($file->getPathname())) as $token) {
        $src .= is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true) ? preg_replace('/[^\n]/', ' ', $token[1]) : (is_array($token) ? $token[1] : $token);
    }
    // The name: a quoted string ending the argument, or a quoted prefix joined to a variable.
    if (!preg_match_all('/\b(do_action|apply_filters|do_action_ref_array|apply_filters_ref_array|do_action_deprecated|apply_filters_deprecated)\s*\(\s*(["\'])((?:[^"\'\\\\]|\\\\.)*)\2\s*(,|\)|\.\s*\$)/', $src, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
        continue;
    }
    $scopes = minn_hook_scopes($src);
    foreach ($m as $hit) {
        $fn = $hit[1][0];
        $name = $hit[3][0];
        $joined = str_starts_with($hit[4][0], '.');
        $dynamic = $joined || ($hit[2][0] === '"' && str_contains($name, '{$'));
        if ($hit[2][0] === '"') {
            $name = (string) preg_replace('/\{\$[^}]+\}/', '{*}', $name);
        }
        if ($joined) {
            $name .= '{*}';
        }
        $kind = str_starts_with($fn, 'do_action') ? 'action' : 'filter';
        // Count the arguments after the name up to the matching paren.
        $pos = $hit[0][1] + strlen($hit[0][0]);
        $depth = $hit[4][0] === ')' ? 0 : 1;
        $args = $hit[4][0] === ')' ? 0 : ($joined ? 0 : 1);
        $i = $pos;
        $inStr = null;
        while ($depth > 0 && $i < strlen($src)) {
            $ch = $src[$i];
            if ($inStr !== null) {
                if ($ch === '\\') { $i++; } elseif ($ch === $inStr) { $inStr = null; }
            } elseif ($ch === '"' || $ch === "'") {
                $inStr = $ch;
            } elseif ($ch === '(' || $ch === '[') {
                $depth++;
            } elseif ($ch === ')' || $ch === ']') {
                $depth--;
            } elseif ($ch === ',' && $depth === 1) {
                $args++;
            }
            $i++;
        }
        if (str_ends_with($fn, '_ref_array')) {
            $args = null; // an array of args; count unknown statically
        }
        $row = $hooks[$name] ?? ['kind' => $kind, 'args' => $args, 'files' => [], 'in' => []];
        if (!in_array($rel, $row['files'], true)) {
            $row['files'][] = $rel;
        }
        $scope = minn_hook_scope_at($scopes, $hit[0][1]) ?? $rel;
        if (!in_array($scope, $row['in'], true)) {
            $row['in'][] = $scope;
        }
        if ($args !== null && ($row['args'] === null || $args > $row['args'])) {
            $row['args'] = $args;
        }
        if (str_contains($fn, 'deprecated')) {
            $row['deprecated'] = true;
        }
        if ($dynamic) {
            $row['dynamic'] = true;
        }
        $hooks[$name] = $row;
    }
}
ksort($hooks);
foreach ($hooks as &$row) {
    sort($row['files']);
    sort($row['in']);
}
file_put_contents($out, json_encode($hooks, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
printf("%d hooks (%d dynamic)\n", count($hooks), count(array_filter($hooks, static fn (array $row): bool => !empty($row['dynamic']))));
