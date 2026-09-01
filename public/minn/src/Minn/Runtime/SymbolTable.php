<?php

declare(strict_types=1);

namespace Minn\Runtime;

/**
 * What a folder's PHP names, collected while its tokens are read: the
 * functions it calls, the classes it references, and what it declares or
 * guards itself, so the gate can subtract those before judging it.
 */
final class SymbolTable
{
    /** @var array<string, true> */
    private array $calls = [];
    /** @var array<string, true> */
    private array $classes = [];
    /** @var array<string, true> */
    private array $declared = [];
    /** @var array<string, true> */
    private array $declaredClasses = [];
    /** @var array<string, true> */
    private array $guarded = [];

    /** Notes a function called. */
    public function call(string $name): void
    {
        $this->calls[$name] = true;
    }

    /** Notes a class referenced. */
    public function classRef(string $name): void
    {
        $this->classes[$name] = true;
    }

    /** Notes a function the folder declares. */
    public function declare(string $function): void
    {
        $this->declared[strtolower($function)] = true;
    }

    /** Notes a class the folder declares. */
    public function declareClass(string $class): void
    {
        $this->declaredClasses[strtolower($class)] = true;
    }

    /** A name an existence check protects: function_exists, class_exists, defined, and the rest. */
    public function guard(string $name): void
    {
        $this->guarded[strtolower($name)] = true;
    }

    /**
     * The table as the gate reads it.
     *
     * @return array{calls: list<string>, classes: list<string>, declared: array<string, true>, declaredClasses: array<string, true>, guarded: array<string, true>, truncated: bool}
     */
    public function toArray(bool $truncated): array
    {
        return [
            'calls' => array_keys($this->calls),
            'classes' => array_keys($this->classes),
            'declared' => $this->declared,
            'declaredClasses' => $this->declaredClasses,
            'guarded' => $this->guarded,
            'truncated' => $truncated,
        ];
    }
}
