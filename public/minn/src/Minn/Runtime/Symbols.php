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

    /**
     * @return array{functions: list<string>, classes: list<string>, files: int, truncated: bool}
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
        if (is_array($entry) && ($entry['mtime'] ?? 0) === $newest && ($entry['count'] ?? -1) === count($files)) {
            $scan = $entry['scan'];
        } else {
            $scan = self::scan($files);
            $cache[$key] = ['mtime' => $newest, 'count' => count($files), 'scan' => $scan];
            $options->update('minn_runtime_symbols', $cache, 'off');
        }
        // Only the reference's own interface counts: a function or class the
        // plugin needs from WordPress. Its own integrations with other plugins
        // and PHP extensions are its business.
        $interface = self::interfaceNames();
        $missingFunctions = [];
        foreach ($scan['calls'] as $name) {
            $lower = strtolower($name);
            if (isset($interface['functions'][$lower]) && !function_exists($name) && !isset($scan['declared'][$lower]) && !isset($scan['guarded'][$lower])) {
                $missingFunctions[] = $name;
            }
        }
        $missingClasses = [];
        foreach ($scan['classes'] as $name) {
            $lower = strtolower($name);
            if (isset($interface['classes'][$lower]) && !class_exists($name) && !interface_exists($name) && !trait_exists($name) && !enum_exists($name) && !isset($scan['declaredClasses'][$lower]) && !isset($scan['guarded'][$lower])) {
                $missingClasses[] = $name;
            }
        }
        sort($missingFunctions);
        sort($missingClasses);
        return ['functions' => $missingFunctions, 'classes' => $missingClasses, 'files' => count($files), 'truncated' => $scan['truncated']];
    }

    /** @return array{functions: array<string, true>, classes: array<string, true>} lower-cased names of the reference's interface */
    private static function interfaceNames(): array
    {
        static $names = null;
        if ($names === null) {
            $data = json_decode((string) file_get_contents(MINN_ENGINE_DIR . '/data/api-names.json'), true) ?: [];
            $names = [
                'functions' => array_fill_keys(array_map('strtolower', $data['functions'] ?? []), true),
                'classes' => array_fill_keys(array_map('strtolower', $data['classes'] ?? []), true),
            ];
        }
        return $names;
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
    private static function scan(array $files): array
    {
        $calls = [];
        $classes = [];
        $declared = [];
        $declaredClasses = [];
        $guarded = [];
        $truncated = count($files) > self::MAX_FILES;
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
            $namespaced = false;
            $count = count($tokens);
            $significant = static function (int $from, int $step) use ($tokens, $count): ?array {
                for ($j = $from + $step; $j >= 0 && $j < $count; $j += $step) {
                    $t = $tokens[$j];
                    if (is_array($t) && in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                        continue;
                    }
                    return is_array($t) ? $t : [null, $t];
                }
                return null;
            };
            for ($i = 0; $i < $count; $i++) {
                $token = $tokens[$i];
                if (!is_array($token)) {
                    continue;
                }
                [$id, $text] = $token;
                if ($id === T_NAMESPACE) {
                    $namespaced = true;
                    continue;
                }
                if ($id === T_FUNCTION) {
                    $next = $significant($i, 1);
                    if ($next !== null && $next[0] === T_STRING) {
                        $declared[strtolower($next[1])] = true;
                    }
                    continue;
                }
                if (in_array($id, [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)) {
                    $prev = $significant($i, -1);
                    if ($prev !== null && ($prev[0] === T_DOUBLE_COLON || $prev[0] === T_NEW)) {
                        continue;
                    }
                    $next = $significant($i, 1);
                    if ($next !== null && $next[0] === T_STRING) {
                        $declaredClasses[strtolower($next[1])] = true;
                        if ($namespaced) {
                            $declaredClasses['*ns*' . strtolower($next[1])] = true;
                        }
                    }
                    continue;
                }
                if ($id === T_STRING || $id === T_NAME_FULLY_QUALIFIED) {
                    $name = ltrim($text, '\\');
                    $prev = $significant($i, -1);
                    $next = $significant($i, 1);
                    $prevId = $prev[0] ?? null;
                    $prevText = $prev[1] ?? '';
                    if ($prevId === T_NEW || $prevId === T_EXTENDS || $prevId === T_IMPLEMENTS || $prevId === T_INSTANCEOF || ($next !== null && $next[0] === T_DOUBLE_COLON)) {
                        if (in_array(strtolower($name), ['self', 'static', 'parent', 'class'], true)) {
                            continue;
                        }
                        if ($namespaced && $id === T_STRING && !str_contains($text, '\\')) {
                            continue; // resolves inside the plugin's own namespace or a use statement
                        }
                        if ($prevId === T_IMPLEMENTS || ($prevText === ',' && $significant($i - 1, -1) !== null)) {
                            // an implements list may hold several names; each is a class reference
                        }
                        $classes[$name] = true;
                        continue;
                    }
                    if ($next !== null && $next[1] === '(' && $prevId !== T_OBJECT_OPERATOR && $prevId !== T_NULLSAFE_OBJECT_OPERATOR && $prevId !== T_DOUBLE_COLON && $prevId !== T_FUNCTION && $prevId !== T_NEW && $prevText !== '&') {
                        if (str_contains($text, '\\') && $id === T_STRING) {
                            continue;
                        }
                        $lower = strtolower($name);
                        if (in_array($lower, ['function_exists', 'class_exists', 'interface_exists', 'method_exists', 'is_callable', 'defined', 'trait_exists', 'enum_exists'], true)) {
                            $arg = $significant($i + 1, 1);
                            if ($arg !== null && $arg[0] === T_CONSTANT_ENCAPSED_STRING) {
                                $guarded[strtolower(trim($arg[1], '\'"'))] = true;
                            }
                            continue;
                        }
                        $calls[$name] = true;
                    }
                }
            }
        }
        foreach (array_keys($declaredClasses) as $k) {
            if (str_starts_with($k, '*ns*')) {
                unset($declaredClasses[$k]);
            }
        }
        return ['calls' => array_keys($calls), 'classes' => array_keys($classes), 'declared' => $declared, 'declaredClasses' => $declaredClasses, 'guarded' => $guarded, 'truncated' => $truncated];
    }
}
