<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Json;

/**
 * The part of the reference's interface the runtime does not answer: names in
 * data/api-names.json that no facade file defines. It is the whole input the
 * symbol gate needs, so it can be exported to JSON and carried to a machine
 * that holds plugin source but no engine, which is how the catalogue-wide
 * compatibility scan runs.
 */
final readonly class SymbolGap
{
    /**
     * @param array<string, true> $functions lower-cased function names the runtime lacks
     * @param array<string, true> $classes lower-cased class, interface, trait, and enum names it lacks
     * @param array<string, true> $known lower-cased function names in the reference's interface, lacking or not
     * @param array<string, true> $pluggable lower-cased pluggable functions the runtime defines after the plugins load
     */
    private function __construct(public array $functions, public array $classes, public array $known = [], public array $pluggable = [])
    {
    }

    /**
     * Reads the reference's interface and subtracts everything the loaded facade
     * defines. A plugin may define a name as it loads, so each call asks again,
     * but only about the names still lacking: a name once defined stays defined,
     * and asking about all four thousand for every plugin was a cost of its own.
     */
    public static function ofLoadedFacade(string $engineDir): self
    {
        static $last = null;
        if ($last instanceof self) {
            $functions = array_filter($last->functions, static fn (bool $on, string $name): bool => !function_exists($name), ARRAY_FILTER_USE_BOTH);
            $classes = array_filter($last->classes, static fn (bool $on, string $name): bool => !self::typeExists($name), ARRAY_FILTER_USE_BOTH);
            return $last = new self($functions, $classes, $last->known, $last->pluggable);
        }
        $decoded = Json::file($engineDir . '/data/api-names.json');
        $data = is_array($decoded) ? $decoded : [];
        $functions = [];
        $known = [];
        // The pluggable functions load after the plugins, so at gate time they
        // are provided without existing yet.
        $deferredFile = $engineDir . '/wp-api/pluggable.php';
        preg_match_all('/^function\s+(\w+)\s*\(/m', is_file($deferredFile) ? (string) file_get_contents($deferredFile) : '', $deferred);
        $pluggable = array_fill_keys(array_map(strtolower(...), $deferred[1]), true);
        foreach ($data['functions'] ?? [] as $name) {
            $known[strtolower($name)] = true;
            if (!function_exists($name) && !isset($pluggable[strtolower($name)])) {
                $functions[strtolower($name)] = true;
            }
        }
        $classes = [];
        foreach ($data['classes'] ?? [] as $name) {
            if (!self::typeExists($name)) {
                $classes[strtolower($name)] = true;
            }
        }
        return $last = new self($functions, $classes, $known, $pluggable);
    }

    /** Whether a class, interface, trait or enum of this name is defined. */
    private static function typeExists(string $name): bool
    {
        return class_exists($name) || interface_exists($name) || trait_exists($name) || enum_exists($name);
    }

    /**
     * The gap read from the engine's own files without loading them, for a
     * machine that has the engine but runs no site with it yet (preflight): a
     * name counts as defined when a facade file declares it.
     */
    public static function ofShippedFacade(string $engineDir): self
    {
        $definedFunctions = [];
        $definedClasses = [];
        $facade = $engineDir . '/wp-api';
        $files = is_dir($facade) ? new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($facade, \FilesystemIterator::SKIP_DOTS)) : [];
        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                [$functions, $classes] = self::declaredIn((string) file_get_contents($file->getPathname()));
                $definedFunctions += $functions;
                $definedClasses += $classes;
            }
        }
        $pluggable = self::declaredIn((string) @file_get_contents($facade . '/pluggable.php'))[0];
        $data = Json::file($engineDir . '/data/api-names.json', or: []);
        $known = array_fill_keys(array_map(strtolower(...), (array) ($data['functions'] ?? [])), true);
        $lacking = array_diff_key($known, $definedFunctions, $pluggable);
        $classes = array_diff_key(array_fill_keys(array_map(strtolower(...), (array) ($data['classes'] ?? [])), true), $definedClasses);
        return new self($lacking, $classes, $known, $pluggable);
    }

    /**
     * The global functions and the classes (with their namespace) one file
     * declares, lower-cased; a method, a closure and an anonymous class are
     * not declarations.
     *
     * @return array{0: array<string, true>, 1: array<string, true>}
     */
    private static function declaredIn(string $source): array
    {
        $tokens = token_get_all($source);
        $count = count($tokens);
        $significant = static function (int $from, int $step) use ($tokens, $count): ?array {
            for ($j = $from + $step; $j >= 0 && $j < $count; $j += $step) {
                if (!is_array($tokens[$j])) {
                    return [$tokens[$j], $tokens[$j]];
                }
                if (!in_array($tokens[$j][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    return $tokens[$j];
                }
            }
            return null;
        };
        [$functions, $classes, $namespace, $depth, $bodies, $pending] = [[], [], '', 0, [], false];
        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];
            if (!is_array($token)) {
                if ($token === '{') {
                    $depth++;
                    if ($pending) {
                        [$bodies[], $pending] = [$depth, false];
                    }
                } elseif ($token === '}') {
                    if ($bodies !== [] && end($bodies) === $depth) {
                        array_pop($bodies);
                    }
                    $depth--;
                }
                continue;
            }
            if ($token[0] === T_CURLY_OPEN || $token[0] === T_DOLLAR_OPEN_CURLY_BRACES) {
                $depth++;
            } elseif ($token[0] === T_NAMESPACE) {
                $next = $significant($i, 1);
                $namespace = $next !== null && in_array($next[0], [T_STRING, T_NAME_QUALIFIED], true) ? $next[1] : '';
            } elseif (in_array($token[0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)) {
                $prev = $significant($i, -1);
                if ($prev !== null && $prev[0] === T_DOUBLE_COLON) {
                    continue;
                }
                $pending = true;
                $next = $significant($i, 1);
                if (($prev === null || $prev[0] !== T_NEW) && $next !== null && $next[0] === T_STRING) {
                    $classes[strtolower(ltrim($namespace . '\\' . $next[1], '\\'))] = true;
                }
            } elseif ($token[0] === T_FUNCTION && $bodies === []) {
                $prev = $significant($i, -1);
                $next = $significant($i, 1);
                $next = $next !== null && $next[0] === '&' ? $significant(array_search($next, $tokens, true) ?: $i, 1) : $next;
                if (($prev === null || $prev[0] !== T_USE) && $next !== null && $next[0] === T_STRING) {
                    $functions[strtolower(ltrim($namespace . '\\' . $next[1], '\\'))] = true;
                }
            }
        }
        return [$functions, $classes];
    }

    /** The gap read from its JSON file. */
    public static function fromFile(string $path): self
    {
        $data = Json::file($path, or: null);
        $data = is_array($data) ? $data : [];
        return new self(
            array_fill_keys(array_map(strtolower(...), $data['functions'] ?? []), true),
            array_fill_keys(array_map(strtolower(...), $data['classes'] ?? []), true),
            array_fill_keys(array_map(strtolower(...), $data['known'] ?? []), true),
            array_fill_keys(array_map(strtolower(...), $data['pluggable'] ?? []), true),
        );
    }

    /** Whether the runtime lacks a function. */
    public function lacksFunction(string $name): bool
    {
        return isset($this->functions[strtolower($name)]);
    }

    /**
     * Whether the runtime already defines a function of the reference's
     * interface, so a plugin declaring it again without a guard would fail
     * to compile. The pluggable functions are not counted: the runtime
     * defines those after the plugins load, each only where no plugin did.
     */
    public function defines(string $name): bool
    {
        $lower = strtolower($name);
        return isset($this->known[$lower]) && !isset($this->functions[$lower]) && !isset($this->pluggable[$lower]);
    }

    /** Whether the runtime lacks a class. */
    public function lacksClass(string $name): bool
    {
        return isset($this->classes[strtolower($name)]);
    }

    /** The gap as JSON. */
    public function json(): string
    {
        return Json::encode([
            'functions' => array_keys($this->functions),
            'classes' => array_keys($this->classes),
            'known' => array_keys($this->known),
            'pluggable' => array_keys($this->pluggable),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
