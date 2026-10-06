<?php

declare(strict_types=1);

namespace Minn\I18n;

/**
 * The locales a request switched into, innermost last, each with the user
 * whose locale it is (false when a locale was named outright). Empty means
 * the request speaks its own locale.
 */
final class LocaleStack
{
    /** @var list<array{0: string, 1: int|false}> */
    private array $stack = [];

    /** Enters a locale, a user's or one named outright. */
    public function push(string $locale, int|false $userId = false): void
    {
        $this->stack[] = [$locale, $userId];
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
        return $this->stack === [] ? null : $this->stack[count($this->stack) - 1][0];
    }

    /** The user whose locale is innermost, or false (none switched, or a locale named outright). */
    public function currentUser(): int|false
    {
        return $this->stack === [] ? false : $this->stack[count($this->stack) - 1][1];
    }

    /** Whether any locale is switched in. */
    public function switched(): bool
    {
        return $this->stack !== [];
    }
}
