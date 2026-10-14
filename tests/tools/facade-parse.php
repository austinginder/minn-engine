<?php

declare(strict_types=1);

/**
 * The facade's functions and methods read from its tokens, for the tools
 * that measure and generate it (facade-logic.php, facade-gen.php): each
 * with its owner class, whether it is defined conditionally (inside an
 * if, as the pluggable functions are), its signature and return type as
 * written, its docblock, its body, how many statements the body holds at
 * its top level (a control structure counts as one), and the byte offsets
 * of the whole definition, so a tool can rewrite it in place.
 */

/** The facade folders the tools read, generated dead ends and placeholders apart. */
function facade_files(string $facadeDir): array
{
    $files = [];
    foreach (['*.php', 'defaults/*.php', 'classes/*.php', 'requests/*.php', 'simplepie/*.php', 'ai-client/*.php'] as $pattern) {
        foreach (glob("{$facadeDir}/{$pattern}") ?: [] as $file) {
            if (basename($file) !== 'deadends.php') {
                $files[] = $file;
            }
        }
    }
    sort($files);
    return $files;
}

/**
 * @return list<array{name: string, reference: bool, owner: ?string, conditional: bool, signature: string, returns: string, doc: string, body: string,
 *     statements: int, control: bool, lines: int, start: int, end: int, line: int, docStart: ?int}>
 */
function facade_definitions(string $src): array
{
    $tokens = token_get_all($src);
    $count = count($tokens);
    // Byte offset of every token.
    $offsets = [];
    $at = 0;
    foreach ($tokens as $i => $token) {
        $offsets[$i] = $at;
        $at += strlen(is_array($token) ? $token[1] : $token);
    }
    $text = static fn (int $from, int $to): string => substr($src, $offsets[$from], ($to < $count ? $offsets[$to] : strlen($src)) - $offsets[$from]);
    $out = [];
    $depth = 0;
    $classStack = [];
    $pendingClass = null;
    $lastDoc = null;
    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];
        if (is_array($token) && $token[0] === T_DOC_COMMENT) {
            $lastDoc = $i;
            continue;
        }
        if (is_array($token) && in_array($token[0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)) {
            // `X::class` and anonymous classes are not declarations of a named class.
            $prev = $i - 1;
            while ($prev >= 0 && is_array($tokens[$prev]) && $tokens[$prev][0] === T_WHITESPACE) {
                $prev--;
            }
            if ($prev >= 0 && is_array($tokens[$prev]) && $tokens[$prev][0] === T_DOUBLE_COLON) {
                continue;
            }
            for ($j = $i + 1; $j < $count && (!is_array($tokens[$j]) || $tokens[$j][0] === T_WHITESPACE); $j++) {
            }
            if (is_array($tokens[$j] ?? null) && $tokens[$j][0] === T_STRING) {
                $pendingClass = $tokens[$j][1];
            }
            continue;
        }
        if ($token === '{' || (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
            $depth++;
            if ($pendingClass !== null && $token === '{') {
                $classStack[] = [$pendingClass, $depth];
                $pendingClass = null;
            }
            continue;
        }
        if ($token === '}') {
            if ($classStack !== [] && end($classStack)[1] === $depth) {
                array_pop($classStack);
            }
            $depth--;
            continue;
        }
        if (!is_array($token) || $token[0] !== T_FUNCTION) {
            continue;
        }
        // A named function or method: `function name(` or `function &name(`; closures have none.
        for ($j = $i + 1; $j < $count && (!is_array($tokens[$j]) ? $tokens[$j] === '&' : $tokens[$j][0] === T_WHITESPACE || $tokens[$j][0] === T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG || $tokens[$j][0] === T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG); $j++) {
        }
        if (!is_array($tokens[$j] ?? null) || $tokens[$j][0] !== T_STRING) {
            continue;
        }
        $name = $tokens[$j][1];
        $reference = str_contains($text($i + 1, $j), '&');
        $owner = $classStack !== [] ? end($classStack)[0] : null;
        $ownerDepth = $classStack !== [] ? end($classStack)[1] : 0;
        // Where the definition starts: its modifiers (and docblock) before `function`.
        $start = $i;
        for ($k = $i - 1; $k >= 0; $k--) {
            $t = $tokens[$k];
            if (is_array($t) && in_array($t[0], [T_WHITESPACE, T_PUBLIC, T_PROTECTED, T_PRIVATE, T_STATIC, T_FINAL, T_ABSTRACT], true)) {
                if ($t[0] !== T_WHITESPACE) {
                    $start = $k;
                }
                continue;
            }
            break;
        }
        // The signature between the parentheses, then the return type, then the body or `;`.
        for ($p = $j; $p < $count && $tokens[$p] !== '('; $p++) {
        }
        $paren = 0;
        for ($q = $p; $q < $count; $q++) {
            if ($tokens[$q] === '(') {
                $paren++;
            } elseif ($tokens[$q] === ')') {
                $paren--;
                if ($paren === 0) {
                    break;
                }
            }
        }
        $signature = $text($p + 1, $q);
        for ($b = $q + 1; $b < $count && $tokens[$b] !== '{' && $tokens[$b] !== ';'; $b++) {
        }
        $returns = trim($text($q + 1, $b));
        if (($tokens[$b] ?? null) !== '{') {
            $i = $b;
            continue;
        }
        $braces = 0;
        for ($e = $b; $e < $count; $e++) {
            $t = $tokens[$e];
            if ($t === '{' || (is_array($t) && in_array($t[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
                $braces++;
            } elseif ($t === '}') {
                $braces--;
                if ($braces === 0) {
                    break;
                }
            }
        }
        // Statements at the body's top level: a `;` outside any bracket, or a control structure.
        $statements = 0;
        $control = false;
        $nest = 0;
        for ($s = $b + 1; $s < $e; $s++) {
            $t = $tokens[$s];
            if ($t === '(' || $t === '[' || $t === '{' || (is_array($t) && in_array($t[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
                $nest++;
            } elseif ($t === ')' || $t === ']' || $t === '}') {
                $nest--;
            } elseif ($nest === 0 && $t === ';') {
                $statements++;
            } elseif ($nest === 0 && is_array($t) && in_array($t[0], [T_IF, T_FOREACH, T_FOR, T_WHILE, T_DO, T_SWITCH, T_TRY], true)) {
                $statements++;
                $control = true;
            }
        }
        $body = $text($b + 1, $e);
        $lines = 0;
        foreach (explode("\n", $body) as $line) {
            $l = trim($line);
            if ($l !== '' && !str_starts_with($l, '//') && !str_starts_with($l, '*') && !str_starts_with($l, '/*') && !str_starts_with($l, '#')) {
                $lines++;
            }
        }
        $conditional = $depth > $ownerDepth;
        $docStart = null;
        if ($lastDoc !== null) {
            $between = $text($lastDoc + 1, $start);
            if (trim($between) === '') {
                $docStart = $lastDoc;
            }
        }
        $out[] = [
            'name' => $name,
            'reference' => $reference,
            'owner' => $owner,
            'conditional' => $conditional,
            'signature' => $signature,
            'returns' => $returns,
            'doc' => $docStart === null ? '' : $tokens[$docStart][1],
            'body' => $body,
            'statements' => $statements,
            'control' => $control,
            'lines' => $lines,
            'start' => $offsets[$start],
            'end' => ($e + 1 < $count ? $offsets[$e + 1] : strlen($src)),
            'line' => $tokens[$i][2],
            'docStart' => $docStart === null ? null : $offsets[$docStart],
        ];
        $lastDoc = null;
        $i = $e;
    }
    return $out;
}

/** Whether a definition holds logic: more than one statement, or any control structure. */
function facade_is_logic(array $definition): bool
{
    return $definition['statements'] > 1 || $definition['control'];
}

/** Whether a definition's docblock keeps it in the facade on purpose (`@minn-facade-logic <reason>`). */
function facade_kept(array $definition): ?string
{
    return preg_match('/@minn-facade-logic\s+([^\n*]+)/', $definition['doc'], $m) === 1 ? trim($m[1]) : null;
}
