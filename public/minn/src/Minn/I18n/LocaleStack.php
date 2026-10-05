<?php

declare(strict_types=1);

namespace Minn\I18n;

/**
 * The locales a request switched into, innermost last. Empty means the
 * request speaks its own locale.
 */
final class LocaleStack
{
    /** @var list<string> */
    private array $stack = [];

    /** Enters a locale. */
    public function push(string $locale): void
    {
        $this->stack[] = $locale;
    }

    /** Leaves the innermost locale; the one now in force, or null when none is switched. */
    public function pop(): ?string
    {
        array_pop($this->stack);
        return $this->current();
    }

    /** Leaves every switched locale. */
    public function clear(): void
    {
        $this->stack = [];
    }

    /** The innermost switched locale, or null. */
    public function current(): ?string
    {
        return $this->stack === [] ? null : $this->stack[count($this->stack) - 1];
    }

    /** Whether any locale is switched in. */
    public function switched(): bool
    {
        return $this->stack !== [];
    }
}
