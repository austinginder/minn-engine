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

    public static function nextId(): int
    {
        return ++self::$counter;
    }

    public static function reset(): void
    {
        self::$counter = 0;
    }
}
