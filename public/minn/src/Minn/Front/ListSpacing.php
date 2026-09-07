<?php

declare(strict_types=1);

namespace Minn\Front;

/**
 * How a page list is spaced: the reference's "preserve" keeps newlines and
 * one tab per level, "discard" prints the items on one line. The link
 * wrappers ride along so the list has one place to read them.
 */
final readonly class ListSpacing
{
    private function __construct(private bool $preserve, public string $linkBefore, public string $linkAfter)
    {
    }

    /** Newlines and tabs kept, the reference's default. */
    public static function preserved(string $linkBefore = '', string $linkAfter = ''): self
    {
        return new self(true, $linkBefore, $linkAfter);
    }

    /** Everything on one line, the reference's "discard". */
    public static function discarded(string $linkBefore = '', string $linkAfter = ''): self
    {
        return new self(false, $linkBefore, $linkAfter);
    }

    /** A newline, or nothing when spacing is discarded. */
    public function newline(): string
    {
        return $this->preserve ? "\n" : '';
    }

    /** One tab of indent, or nothing when spacing is discarded. */
    public function tab(): string
    {
        return $this->preserve ? "\t" : '';
    }
}
