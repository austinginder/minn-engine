<?php

declare(strict_types=1);

namespace Minn\Runtime;

/**
 * A static read of what a plugin's PHP calls: global functions and classes
 * it uses but does not itself declare, minus the ones it guards with
 * function_exists() or class_exists(). Checked against what the runtime
 * provides, this decides whether a plugin loads at all. The read is cached
 * in the minn_runtime_symbols option keyed by the plugin folder's newest
 * modification time.
 */
final class Symbols
{
    private const MAX_FILES = 6000;
    private const SKIP_DIRS = ['node_modules', 'tests', 'test', '.git'];
    /** Bumped whenever the token reader changes, so every cached scan is made again. */
    private const READER = 3;

    /**
     * What a plugin folder needs that the runtime lacks, cached by mtime.
     *
     * @return array{functions: list<string>, classes: list<string>, redeclares: list<string>, files: int, truncated: bool}
     */
    public static function missing(string $dir, Options $options): array
    {
        $files = self::phpFiles($dir);
        $newest = 0;
        foreach ($files as $file) {
            $newest = max($newest, (int) @filemtime($file));
        }
        $cache = $options->get('minn_runtime_symbols');
        $cache = is_array($cache) ? $cache : [];
        $key = basename($dir);
        $entry = $cache[$key] ?? null;
        // A cached scan is only as good as the reader that made it; a reader change retires every entry.
        if (is_array($entry) && ($entry['reader'] ?? 0) === self::READER && ($entry['mtime'] ?? 0) === $newest && ($entry['count'] ?? -1) === count($files)) {
            $scan = $entry['scan'];
        } else {
            $scan = self::scan($files);
            $cache[$key] = ['reader' => self::READER, 'mtime' => $newest, 'count' => count($files), 'scan' => $scan];
            $options->update('minn_runtime_symbols', $cache, 'off');
        }
        return self::verdict($scan, count($files), SymbolGap::ofLoadedFacade(MINN_ENGINE_DIR));
    }

    /**
     * The functions one file declares in the global scope, unguarded, that the
     * loaded runtime already defines: including that file would not compile.
     *
     * @return list<string>
     */
    public static function redeclaresIn(string $file): array
    {
        return self::missingAgainst($file, SymbolGap::ofLoadedFacade(MINN_ENGINE_DIR))['redeclares'];
    }

    /**
     * The same read against an exported gap instead of the running engine, so a
     * folder can be judged with no database, no options, and no facade loaded.
     *
     * @return array{functions: list<string>, classes: list<string>, redeclares: list<string>, files: int, truncated: bool}
     */
    public static function missingAgainst(string $dir, SymbolGap $gap): array
    {
        $files = self::phpFiles($dir);
        return self::verdict(self::scan($files), count($files), $gap);
    }

    /**
     * Only the reference's own interface counts: a function or class the plugin
     * needs from WordPress. Its own integrations with other plugins and PHP
     * extensions are its business.
     *
     * @param array{calls: list<string>, classes: list<string>, declared: array<string, true>, declaredClasses: array<string, true>, guarded: array<string, true>, truncated: bool} $scan
     * @return array{functions: list<string>, classes: list<string>, redeclares: list<string>, files: int, truncated: bool}
     */
    private static function verdict(array $scan, int $files, SymbolGap $gap): array
    {
        $missingFunctions = [];
        foreach ($scan['calls'] as $name) {
            $lower = strtolower($name);
            // Only a declaration in the global scope answers a call: a method of
            // the same name is reached through an object and hides nothing.
            if ($gap->lacksFunction($name) && !isset($scan['declaredGlobal'][$lower]) && !isset($scan['guarded'][$lower])) {
                $missingFunctions[] = $name;
            }
        }
        $missingClasses = [];
        foreach ($scan['classes'] as $name) {
            $lower = strtolower($name);
            if ($gap->lacksClass($name) && !isset($scan['declaredClasses'][$lower]) && !isset($scan['guarded'][$lower])) {
                $missingClasses[] = $name;
            }
        }
        // A function the folder declares that the runtime already defines would
        // not compile (the reference lets a plugin redefine a pluggable because
        // pluggable.php loads after the plugins; the facade loads first).
        $redeclares = [];
        foreach (array_keys($scan['declaredGlobal'] ?? []) as $name) {
            if ($gap->defines($name) && !isset($scan['guarded'][$name])) {
                $redeclares[] = $name;
            }
        }
        sort($missingFunctions);
        sort($missingClasses);
        sort($redeclares);
        return ['functions' => $missingFunctions, 'classes' => $missingClasses, 'redeclares' => $redeclares, 'files' => $files, 'truncated' => $scan['truncated']];
    }

    /** @return list<string> */
    private static function phpFiles(string $dir): array
    {
        $out = [];
        if (is_file($dir)) {
            return [$dir];
        }
        $iterator = new \RecursiveIteratorIterator(new \RecursiveCallbackFilterIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            static fn (\SplFileInfo $f) => !($f->isDir() && in_array($f->getFilename(), self::SKIP_DIRS, true)),
        ));
        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
                $out[] = $file->getPathname();
                if (count($out) > self::MAX_FILES) {
                    break;
                }
            }
        }
        sort($out);
        return $out;
    }

    /**
     * @param list<string> $files
     * @return array{calls: list<string>, classes: list<string>, declared: array<string, true>, declaredClasses: array<string, true>, guarded: array<string, true>, truncated: bool}
     */
    /**
     * Every file's tokens into one table: the functions called, the classes
     * referenced, both minus what the folder declares itself or guards with
     * an existence check.
     *
     * @param list<string> $files
     * @return array{calls: list<string>, classes: list<string>, declared: array<string, true>, declaredClasses: array<string, true>, guarded: array<string, true>, truncated: bool}
     */
    private static function scan(array $files): array
    {
        $table = new SymbolTable();
        foreach (array_slice($files, 0, self::MAX_FILES) as $file) {
            $source = (string) @file_get_contents($file);
            if ($source === '') {
                continue;
            }
            try {
                $tokens = @token_get_all($source);
            } catch (\Throwable) {
                continue;
            }
            self::scanTokens($tokens, $table);
        }
        return $table->toArray(count($files) > self::MAX_FILES);
    }

    /** @param list<array{0: int, 1: string, 2: int}|string> $tokens */
    private static function scanTokens(array $tokens, SymbolTable $table): void
    {
        $namespaced = false;
        $count = count($tokens);
        // Brace depth, and the depths at which class-like bodies opened, so a
        // "function" inside one is known to be a method and not a declaration
        // in the global scope.
        $depth = 0;
        $bodies = [];
        $bodyPending = false;
        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];
            if (!is_array($token)) {
                if ($token === '{') {
                    $depth++;
                    if ($bodyPending) {
                        $bodies[] = $depth;
                        $bodyPending = false;
                    }
                } elseif ($token === '}') {
                    if ($bodies !== [] && end($bodies) === $depth) {
                        array_pop($bodies);
                    }
                    $depth--;
                }
                continue;
            }
            [$id, $text] = $token;
            if ($id === T_CURLY_OPEN || $id === T_DOLLAR_OPEN_CURLY_BRACES) {
                $depth++;
                continue;
            }
            if ($id === T_NAMESPACE) {
                $namespaced = true;
                continue;
            }
            if ($id === T_FUNCTION) {
                $prev = self::significant($tokens, $i, -1);
                $next = self::significant($tokens, $i, 1);
                if ($next !== null && $next[0] === T_STRING && ($prev === null || $prev[0] !== T_USE)) {
                    $table->declare($next[1]);
                    if ($bodies === []) {
                        $table->declareGlobal($next[1]);
                    }
                }
                continue;
            }
            if (in_array($id, [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)) {
                $prev = self::significant($tokens, $i, -1);
                if ($prev !== null && $prev[0] === T_DOUBLE_COLON) {
                    continue;
                }
                $bodyPending = true;
                if ($prev !== null && $prev[0] === T_NEW) {
                    continue;
                }
                $next = self::significant($tokens, $i, 1);
                if ($next !== null && $next[0] === T_STRING) {
                    $table->declareClass($next[1]);
                }
                continue;
            }
            if ($id === T_STRING || $id === T_NAME_FULLY_QUALIFIED) {
                self::noteName($tokens, $i, $namespaced, $table);
            }
        }
    }

    /**
     * A name token: a class reference when it follows new / extends /
     * implements / instanceof or precedes ::, a call when it precedes "(",
     * and a guard when the call is an existence check on a literal.
     *
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     */
    private static function noteName(array $tokens, int $i, bool $namespaced, SymbolTable $table): void
    {
        [$id, $text] = $tokens[$i];
        $name = ltrim($text, '\\');
        $prev = self::significant($tokens, $i, -1);
        $next = self::significant($tokens, $i, 1);
        $prevId = $prev[0] ?? null;
        $prevText = $prev[1] ?? '';
        if ($prevId === T_NEW || $prevId === T_EXTENDS || $prevId === T_IMPLEMENTS || $prevId === T_INSTANCEOF || ($next !== null && $next[0] === T_DOUBLE_COLON)) {
            if (in_array(strtolower($name), ['self', 'static', 'parent', 'class'], true)) {
                return;
            }
            if ($namespaced && $id === T_STRING && !str_contains($text, '\\')) {
                return; // resolves inside the plugin's own namespace or a use statement
            }
            $table->classRef($name);
            return;
        }
        if ($next !== null && $next[1] === '(' && $prevId !== T_OBJECT_OPERATOR && $prevId !== T_NULLSAFE_OBJECT_OPERATOR && $prevId !== T_DOUBLE_COLON && $prevId !== T_FUNCTION && $prevId !== T_NEW && $prevText !== '&') {
            if (str_contains($text, '\\') && $id === T_STRING) {
                return;
            }
            $lower = strtolower($name);
            if (in_array($lower, ['function_exists', 'class_exists', 'interface_exists', 'method_exists', 'is_callable', 'defined', 'trait_exists', 'enum_exists'], true)) {
                $arg = self::significant($tokens, $i + 1, 1);
                if ($arg !== null && $arg[0] === T_CONSTANT_ENCAPSED_STRING) {
                    $table->guard(trim($arg[1], '\'"'));
                }
                return;
            }
            $table->call($name);
        }
    }

    /** The nearest token in either direction that is not whitespace or a comment, as [id, text]. @param list<array|string> $tokens @return array{0: ?int, 1: string}|null */
    private static function significant(array $tokens, int $from, int $step): ?array
    {
        $count = count($tokens);
        for ($j = $from + $step; $j >= 0 && $j < $count; $j += $step) {
            $t = $tokens[$j];
            if (is_array($t) && in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            return is_array($t) ? $t : [null, $t];
        }
        return null;
    }
}
