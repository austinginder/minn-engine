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
    /** @var array<string, string> container class => declarations */
    private static array $containers = [];
    /** @var list<array{0: string, 1: string, 2: int}> block name, style, instance number */
    private static array $variations = [];
    /** @var list<int> */
    private static array $galleries = [];

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

    /** A container stylesheet this page needs: the class and its declarations. */
    public static function recordContainer(string $class, string $declarations): void
    {
        self::$containers[$class] = $declarations;
    }

    /** @return array<string, string> */
    public static function containers(): array
    {
        return self::$containers;
    }

    public static function recordVariation(string $blockName, string $style, int $instance): void
    {
        self::$variations[] = [$blockName, $style, $instance];
    }

    /** @return list<array{0: string, 1: string, 2: int}> */
    public static function variations(): array
    {
        return self::$variations;
    }

    public static function recordGallery(int $instance): void
    {
        self::$galleries[] = $instance;
    }

    /** @return list<int> */
    public static function galleries(): array
    {
        return self::$galleries;
    }

    public static function reset(): void
    {
        self::$counter = 0;
        self::$images = 0;
        self::$priorityClaimed = false;
        self::$containers = [];
        self::$variations = [];
        self::$galleries = [];
    }
}
