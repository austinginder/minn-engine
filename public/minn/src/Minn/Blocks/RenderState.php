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
    /** @var array<string, true> */
    private static array $blocks = [];
    /** @var list<int> */
    private static array $galleries = [];
    private static int $elements = 0;
    /** @var array<string, true> parts, patterns, menus, and synced blocks being rendered right now */
    private static array $active = [];
    private static int $depth = 0;
    private static int $navigation = 0;
    public const MAX_DEPTH = 64;
    /** the element class claimed for the dynamic block being rendered, until its wrapper takes it */
    private static ?string $pendingElements = null;
    /** @var array<string, int> navigation labels used so far, for the reference's de-duplicated aria-labels */
    private static array $labels = [];
    /** @var list<string> element-style rules, in render order */
    private static array $elementRules = [];

    /** The next per-request counter value. */
    public static function nextId(): int
    {
        return ++self::$counter;
    }

    /** Content images seen so far in this page, for the loading rules. */
    public static function nextImage(): int
    {
        return ++self::$images;
    }

    /**
     * Images a plugin's block filter removed from the page give their budget
     * back, so the next image still counts as if the hidden ones never rendered.
     */
    public static function refundImages(int $count, bool $priority): void
    {
        self::$images = max(0, self::$images - $count);
        if ($priority) {
            self::$priorityClaimed = false;
        }
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

    /**
     * The layout containers rendering discovered.
     *
     * @return array<string, string>
     */
    public static function containers(): array
    {
        return self::$containers;
    }

    /** Every block name the page rendered; the stylesheet prints block styles for these only. */
    public static function recordBlock(string $blockName): void
    {
        self::$blocks[$blockName] = true;
    }

    /**
     * The block names rendering met.
     *
     * @return array<string, true>
     */
    public static function blocks(): array
    {
        return self::$blocks;
    }

    /** Notes a style variation instance for the stylesheet. */
    public static function recordVariation(string $blockName, string $style, int $instance): void
    {
        self::$variations[] = [$blockName, $style, $instance];
    }

    /**
     * The style variations rendering met.
     *
     * @return list<array{0: string, 1: string, 2: int}>
     */
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

    /**
     * A page list renders plain on its own and takes the navigation block's
     * item classes, submenu toggles, and interactivity only while it sits
     * inside one, so the navigation block marks the span it owns.
     */
    public static function enterNavigation(): void
    {
        self::$navigation++;
    }

    /** Leaves a navigation block. */
    public static function leaveNavigation(): void
    {
        self::$navigation = max(0, self::$navigation - 1);
    }

    /** Whether rendering is inside a navigation block. */
    public static function inNavigation(): bool
    {
        return self::$navigation > 0;
    }

    /** Marks a nested source as being rendered; false when it is already open (a cycle). */
    public static function enter(string $key): bool
    {
        if (isset(self::$active[$key])) {
            return false;
        }
        self::$active[$key] = true;
        return true;
    }

    /** Leaves a cycle-guarded key. */
    public static function leave(string $key): void
    {
        unset(self::$active[$key]);
    }

    /** True while the block tree is shallower than the cap; deeper blocks render as nothing. */
    public static function descend(): bool
    {
        if (self::$depth >= self::MAX_DEPTH) {
            return false;
        }
        self::$depth++;
        return true;
    }

    /** Leaves one nesting level. */
    public static function ascend(): void
    {
        self::$depth--;
    }

    /** How deep the block tree is right now; zero outside a page render. */
    public static function depth(): int
    {
        return self::$depth;
    }

    /** A dynamic block claims its element class before rendering, so a block that renders nothing still counts. */
    public static function setPendingElements(?string $class): ?string
    {
        $previous = self::$pendingElements;
        self::$pendingElements = $class;
        return $previous;
    }

    /** The pending elements class, cleared. */
    public static function takePendingElements(): ?string
    {
        $class = self::$pendingElements;
        self::$pendingElements = null;
        return $class;
    }

    /** Adds a per-elements CSS rule. */
    public static function recordElementRule(string $css): void
    {
        self::$elementRules[] = $css;
    }

    /**
     * The per-elements CSS rules rendering produced.
     *
     * @return list<string>
     */
    public static function elementRules(): array
    {
        return self::$elementRules;
    }

    /** Notes a gallery instance. */
    public static function recordGallery(int $instance): void
    {
        self::$galleries[] = $instance;
    }

    /**
     * The gallery instances rendering met.
     *
     * @return list<int>
     */
    public static function galleries(): array
    {
        return self::$galleries;
    }

    /** Clears every per-request counter. */
    public static function reset(): void
    {
        self::$counter = 0;
        self::$images = 0;
        self::$priorityClaimed = false;
        self::$containers = [];
        self::$variations = [];
        self::$blocks = [];
        self::$galleries = [];
        self::$elements = 0;
        self::$active = [];
        self::$depth = 0;
        self::$navigation = 0;
        self::$pendingElements = null;
        self::$elementRules = [];
        self::$labels = [];
    }
}
