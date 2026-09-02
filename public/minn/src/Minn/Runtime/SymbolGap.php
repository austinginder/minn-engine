<?php

declare(strict_types=1);

namespace Minn\Runtime;

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
     */
    private function __construct(public array $functions, public array $classes, public array $known = [])
    {
    }

    /**
     * Reads the reference's interface and subtracts everything the loaded facade
     * defines. Recomputed on every call because a plugin may define a name as it
     * loads; only the file read is cached.
     */
    public static function ofLoadedFacade(string $engineDir): self
    {
        static $data = null;
        if ($data === null) {
            $decoded = json_decode((string) file_get_contents($engineDir . '/data/api-names.json'), true);
            $data = is_array($decoded) ? $decoded : [];
        }
        $functions = [];
        $known = [];
        foreach ($data['functions'] ?? [] as $name) {
            $known[strtolower($name)] = true;
            if (!function_exists($name)) {
                $functions[strtolower($name)] = true;
            }
        }
        $classes = [];
        foreach ($data['classes'] ?? [] as $name) {
            if (!class_exists($name) && !interface_exists($name) && !trait_exists($name) && !enum_exists($name)) {
                $classes[strtolower($name)] = true;
            }
        }
        return new self($functions, $classes, $known);
    }

    /** The gap read from its JSON file. */
    public static function fromFile(string $path): self
    {
        $data = json_decode((string) file_get_contents($path), true);
        $data = is_array($data) ? $data : [];
        return new self(
            array_fill_keys(array_map(strtolower(...), $data['functions'] ?? []), true),
            array_fill_keys(array_map(strtolower(...), $data['classes'] ?? []), true),
            array_fill_keys(array_map(strtolower(...), $data['known'] ?? []), true),
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
     * to compile. On the reference the pluggable functions load after the
     * plugins and a plugin's own definition wins; here the facade is loaded
     * first, so the gate reports the collision instead of the fatal.
     */
    public function defines(string $name): bool
    {
        $lower = strtolower($name);
        return isset($this->known[$lower]) && !isset($this->functions[$lower]);
    }

    /** Whether the runtime lacks a class. */
    public function lacksClass(string $name): bool
    {
        return isset($this->classes[strtolower($name)]);
    }

    /** The gap as JSON. */
    public function json(): string
    {
        return (string) json_encode([
            'functions' => array_keys($this->functions),
            'classes' => array_keys($this->classes),
            'known' => array_keys($this->known),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
