<?php

declare(strict_types=1);

namespace Minn\Blocks;

use Minn\Runtime\Runtime;

/**
 * Per-request rendering state, owned by the renderer. The reference numbers
 * galleries, style variations, and search inputs from ONE counter that runs
 * across every block rendered in the request (a list response keeps
 * counting from post to post), so the counter lives here, not on any block.
 * Leaf helpers with no renderer in hand reach it through current().
 */
final class RenderState
{
    private int $counter = 0;
    private int $images = 0;
    private bool $priorityClaimed = false;
    /** @var array<string, string> container class => declarations */
    private array $containers = [];
    /** @var list<array{0: string, 1: string, 2: int}> block name, style, instance number */
    private array $variations = [];
    /** @var array<string, true> */
    private array $blocks = [];
    /** @var list<int> */
    private array $galleries = [];
    private int $elements = 0;
    /** @var array<string, true> parts, patterns, menus, and synced blocks being rendered right now */
    private array $active = [];
    private int $depth = 0;
    private int $navigation = 0;
    public const MAX_DEPTH = 64;
    /** Whether the theme's layout puts root padding in custom properties; render configuration, so reset() leaves it. */
    private bool $rootPaddingAware = true;
    /** The state for code that renders with no request behind it: the command line and the unit suite. */
    private static ?self $offRequest = null;
    /** the element class claimed for the dynamic block being rendered, until its wrapper takes it */
    private ?string $pendingElements = null;
    /** @var array<string, int> navigation labels used so far, for the reference's de-duplicated aria-labels */
    private array $labels = [];
    /** @var list<string> element-style rules, in render order */
    private array $elementRules = [];

    /**
     * The state of the request being rendered, which its runtime holds, so
     * a request cannot count on from the one before it. Rendering with no
     * runtime (the command line, the unit suite) shares one off-request
     * state instead, since the counters have to survive between calls.
     */
    public static function current(): self
    {
        return Runtime::booted() ? Runtime::current()->renderState() : self::$offRequest ??= new self();
    }

    /** Makes this the state current() answers with, for the renderer that brought its own. */
    public function adopt(): void
    {
        if (Runtime::booted()) {
            Runtime::current()->useRenderState($this);
            return;
        }
        self::$offRequest = $this;
    }

    /** Whether the theme puts root padding in custom properties, which decides the constrained layout's classes. */
    public function rootPaddingAware(): bool
    {
        return $this->rootPaddingAware;
    }

    /** Records what the active theme's layout settings say about root padding. */
    public function useRootPadding(bool $aware): void
    {
        $this->rootPaddingAware = $aware;
    }

    /** The next per-request counter value. */
    public function nextId(): int
    {
        return ++$this->counter;
    }

    /** Content images seen so far in this page, for the loading rules. */
    public function nextImage(): int
    {
        return ++$this->images;
    }

    /**
     * Images a plugin's block filter removed from the page give their budget
     * back, so the next image still counts as if the hidden ones never rendered.
     */
    public function refundImages(int $count, bool $priority): void
    {
        $this->images = max(0, $this->images - $count);
        if ($priority) {
            $this->priorityClaimed = false;
        }
    }

    /** True once, for the image that gets fetchpriority="high". */
    public function claimPriority(): bool
    {
        if ($this->priorityClaimed) {
            return false;
        }
        return $this->priorityClaimed = true;
    }

    /** A container stylesheet this page needs: the class and its declarations. */
    public function recordContainer(string $class, string $declarations): void
    {
        $this->containers[$class] = $declarations;
    }

    /**
     * The layout containers rendering discovered.
     *
     * @return array<string, string>
     */
    public function containers(): array
    {
        return $this->containers;
    }

    /** Every block name the page rendered; the stylesheet prints block styles for these only. */
    public function recordBlock(string $blockName): void
    {
        $this->blocks[$blockName] = true;
    }

    /**
     * The block names rendering met.
     *
     * @return array<string, true>
     */
    public function blocks(): array
    {
        return $this->blocks;
    }

    /** Notes a style variation instance for the stylesheet. */
    public function recordVariation(string $blockName, string $style, int $instance): void
    {
        $this->variations[] = [$blockName, $style, $instance];
    }

    /**
     * The style variations rendering met.
     *
     * @return list<array{0: string, 1: string, 2: int}>
     */
    public function variations(): array
    {
        return $this->variations;
    }

    /** The next wp-elements-N class; the reference numbers these apart from the shared counter. */
    public function nextElements(): int
    {
        return ++$this->elements;
    }

    /** A navigation's aria-label: the label itself the first time, then "label N" for repeats. */
    public function uniqueLabel(string $label): string
    {
        $count = $this->labels[$label] = ($this->labels[$label] ?? 0) + 1;
        return $count === 1 ? $label : $label . ' ' . $count;
    }

    /**
     * A page list renders plain on its own and takes the navigation block's
     * item classes, submenu toggles, and interactivity only while it sits
     * inside one, so the navigation block marks the span it owns.
     */
    public function enterNavigation(): void
    {
        $this->navigation++;
    }

    /** Leaves a navigation block. */
    public function leaveNavigation(): void
    {
        $this->navigation = max(0, $this->navigation - 1);
    }

    /** Whether rendering is inside a navigation block. */
    public function inNavigation(): bool
    {
        return $this->navigation > 0;
    }

    /** Marks a nested source as being rendered; false when it is already open (a cycle). */
    public function enter(string $key): bool
    {
        if (isset($this->active[$key])) {
            return false;
        }
        $this->active[$key] = true;
        return true;
    }

    /** Leaves a cycle-guarded key. */
    public function leave(string $key): void
    {
        unset($this->active[$key]);
    }

    /** True while the block tree is shallower than the cap; deeper blocks render as nothing. */
    public function descend(): bool
    {
        if ($this->depth >= self::MAX_DEPTH) {
            return false;
        }
        $this->depth++;
        return true;
    }

    /** Leaves one nesting level. */
    public function ascend(): void
    {
        $this->depth--;
    }

    /** How deep the block tree is right now; zero outside a page render. */
    public function depth(): int
    {
        return $this->depth;
    }

    /** A dynamic block claims its element class before rendering, so a block that renders nothing still counts. */
    public function setPendingElements(?string $class): ?string
    {
        $previous = $this->pendingElements;
        $this->pendingElements = $class;
        return $previous;
    }

    /** The pending elements class, cleared. */
    public function takePendingElements(): ?string
    {
        $class = $this->pendingElements;
        $this->pendingElements = null;
        return $class;
    }

    /** Adds a per-elements CSS rule. */
    public function recordElementRule(string $css): void
    {
        $this->elementRules[] = $css;
    }

    /**
     * The per-elements CSS rules rendering produced.
     *
     * @return list<string>
     */
    public function elementRules(): array
    {
        return $this->elementRules;
    }

    /** Notes a gallery instance. */
    public function recordGallery(int $instance): void
    {
        $this->galleries[] = $instance;
    }

    /**
     * The gallery instances rendering met.
     *
     * @return list<int>
     */
    public function galleries(): array
    {
        return $this->galleries;
    }

    /** Clears every per-request counter. */
    public function reset(): void
    {
        $this->counter = 0;
        $this->images = 0;
        $this->priorityClaimed = false;
        $this->containers = [];
        $this->variations = [];
        $this->blocks = [];
        $this->galleries = [];
        $this->elements = 0;
        $this->active = [];
        $this->depth = 0;
        $this->navigation = 0;
        $this->pendingElements = null;
        $this->elementRules = [];
        $this->labels = [];
    }
}
