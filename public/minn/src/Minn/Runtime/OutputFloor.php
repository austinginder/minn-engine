<?php

declare(strict_types=1);

namespace Minn\Runtime;

/**
 * The output buffers the engine reads its pages from. The reference prints
 * as it goes, so a plugin that ends every buffer mid-page
 * (wp_ob_end_flush_all) only sends what it has early; the engine builds the
 * page in its own buffers and sends it whole, so ending those would send
 * part of the page out ahead of its headers and lose it from the rest.
 * Each capture holds its level while it is open, and such a plugin ends
 * only the buffers above the innermost one (suite output-flush).
 */
final class OutputFloor
{
    /** @var list<int> the levels of the captures open now, innermost last */
    private static array $levels = [];

    /** Marks the buffer just opened as one the engine reads. */
    public static function hold(): void
    {
        self::$levels[] = ob_get_level();
    }

    /** Lets the innermost capture go. */
    public static function release(): void
    {
        array_pop(self::$levels);
    }

    /** The level buffers may be ended down to: the innermost capture's, or none. */
    public static function level(): int
    {
        return self::$levels === [] ? 0 : self::$levels[array_key_last(self::$levels)];
    }
}
