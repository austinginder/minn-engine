<?php

declare(strict_types=1);

namespace Minn\Blocks;

/**
 * Per-request rendering state. The reference numbers galleries, style
 * variations, and search inputs from ONE counter that runs across every
 * block rendered in the request (a list response keeps counting from post
 * to post), so the counter lives here, not on any block.
 */
final class RenderState
{
    private static int $counter = 0;
    private static int $images = 0;
    private static bool $priorityClaimed = false;

    public static function nextId(): int
    {
        return ++self::$counter;
    }

    /** Content images seen so far in this page, for the loading rules. */
    public static function nextImage(): int
    {
        return ++self::$images;
    }

    /** True once, for the image that gets fetchpriority="high". */
    public static function claimPriority(): bool
    {
        if (self::$priorityClaimed) {
            return false;
        }
        return self::$priorityClaimed = true;
    }

    public static function reset(): void
    {
        self::$counter = 0;
        self::$images = 0;
        self::$priorityClaimed = false;
    }
}
