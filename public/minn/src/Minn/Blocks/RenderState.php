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
    private static int $elements = 0;
    /** the element class claimed for the dynamic block being rendered, until its wrapper takes it */
    private static ?string $pendingElements = null;
    /** @var array<string, int> navigation labels used so far, for the reference's de-duplicated aria-labels */
    private static array $labels = [];
    /** @var list<string> element-style rules, in render order */
    private static array $elementRules = [];

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

    /** The next wp-elements-N class; the reference numbers these apart from the shared counter. */
    public static function nextElements(): int
    {
        return ++self::$elements;
    }

    /** A navigation's aria-label: the label itself the first time, then "label N" for repeats. */
    public static function uniqueLabel(string $label): string
    {
        $count = self::$labels[$label] = (self::$labels[$label] ?? 0) + 1;
        return $count === 1 ? $label : $label . ' ' . $count;
    }

    /** A dynamic block claims its element class before rendering, so a block that renders nothing still counts. */
    public static function setPendingElements(?string $class): ?string
    {
        $previous = self::$pendingElements;
        self::$pendingElements = $class;
        return $previous;
    }

    public static function takePendingElements(): ?string
    {
        $class = self::$pendingElements;
        self::$pendingElements = null;
        return $class;
    }

    public static function recordElementRule(string $css): void
    {
        self::$elementRules[] = $css;
    }

    /** @return list<string> */
    public static function elementRules(): array
    {
        return self::$elementRules;
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
        self::$elements = 0;
        self::$pendingElements = null;
        self::$elementRules = [];
        self::$labels = [];
    }
}
