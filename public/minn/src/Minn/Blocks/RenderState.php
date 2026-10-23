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
    /**
     * How many sources (parts, patterns, synced blocks, menus, posts' content)
     * one outermost render may open: distinct sources nest combinatorially
     * under the depth cap, and an author's content is rendered for every
     * view. The reference has no such budget; a page past it renders the
     * rest as a cycle renders, as nothing.
     */
    public const MAX_SOURCES = 10000;
    private int $sources = 0;
    /** Whether the theme's layout puts root padding in custom properties; render configuration, so reset() leaves it. */
    private bool $rootPaddingAware = true;
    /** Whether the theme writes block gaps (settings.spacing.blockGap); render configuration too. */
    private bool $blockGap = true;

    /** What interactivity_process_directives last answered, for the block rendering now. */
    private bool $directives = true;
    /** The state for code that renders with no request behind it: the command line and the unit suite. */
    private static ?self $offRequest = null;
    /** the element class claimed for the dynamic block being rendered, until its wrapper takes it */
    private ?string $pendingElements = null;
    /** the numbered style variation class claimed for the dynamic block being rendered, until its wrapper takes it */
    private ?string $pendingVariation = null;
    /** @var array<string, int> navigation labels used so far, for the reference's de-duplicated aria-labels */
    private array $labels = [];

    /** @var array<string, int> caption ids given so far, for the reference's de-duplicated ones */
    private array $captionIds = [];

    /** @var list<list<string>> the images of each content the_content is filtering, innermost last */
    private array $contentImages = [];
    /** @var list<string> each content the_content is filtering, as the engine rendered it, innermost last */
    private array $contentRendered = [];
    /** @var array<int, true> the posts a post-content block is rendering the content of */
    private array $contentPosts = [];
    /** @var list<string> the contexts content is rendered in, innermost last */
    private array $contexts = [];

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

    /** Whether the theme writes block gaps, which decides whether a layout's gap makes CSS. */
    public function blockGap(): bool
    {
        return $this->blockGap;
    }

    /** Records whether the active theme's spacing settings write block gaps. */
    public function useBlockGap(bool $writes): void
    {
        $this->blockGap = $writes;
    }

    /** The next per-request counter value. */
    public function nextId(): int
    {
        return ++$this->counter;
    }

    /** Adds to the page's count of content images (wp_increase_content_media_count) and returns the new count. */
    public function countImages(int $amount): int
    {
        return $this->images += $amount;
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

    /**
     * Runs rendering whose images do not count toward the page's loading
     * budget (an excerpt's): the reference fits no image while it makes one,
     * so whatever was seen meanwhile is given back.
     *
     * @template T
     * @param \Closure(): T $render
     * @return T
     */
    public function aside(\Closure $render): mixed
    {
        [$images, $claimed] = [$this->images, $this->priorityClaimed];
        try {
            return $render();
        } finally {
            [$this->images, $this->priorityClaimed] = [$images, $claimed];
        }
    }

    /**
     * $filter run while the_content filters content the engine rendered: the
     * images it already holds count as fitted out, so only what the filter's
     * other callbacks add is fitted (fittedImage()).
     */
    public function filteringContent(string $content, \Closure $filter): mixed
    {
        $this->contentImages[] = preg_match_all('/<img\s[^>]*>/i', $content, $m) > 0 ? $m[0] : [];
        $this->contentRendered[] = $content;
        try {
            return $filter();
        } finally {
            array_pop($this->contentImages);
            array_pop($this->contentRendered);
        }
    }

    /**
     * A post's content as a post-content block renders it, or '' while that
     * post's content is already being rendered further out: the reference
     * prints nothing there, so a post that shows its own content (directly,
     * or through an inheriting query loop) renders it once. Also '' once the
     * render's source budget is spent (MAX_SOURCES).
     *
     * @param \Closure(): string $render
     */
    public function postContent(int $id, \Closure $render): string
    {
        if (isset($this->contentPosts[$id]) || !$this->spend()) {
            return '';
        }
        $this->contentPosts[$id] = true;
        try {
            return $this->within('the_content', $render);
        } finally {
            unset($this->contentPosts[$id]);
        }
    }

    /**
     * Renders inside the context the content's images are fitted out for (a
     * template part's template_part_{area}, a post's the_content), the
     * innermost one standing.
     */
    public function within(string $context, \Closure $render): mixed
    {
        $this->contexts[] = $context;
        try {
            return $render();
        } finally {
            array_pop($this->contexts);
        }
    }

    /** The context content is rendered in now (within), or null outside any. */
    public function contentContext(): ?string
    {
        return $this->contexts === [] ? null : $this->contexts[array_key_last($this->contexts)];
    }

    /**
     * Block markup a the_content filter put before or after what the engine
     * rendered, rendered by $render (do_blocks, in its place on the_content).
     * The engine's own rendering is never parsed again, so block delimiters
     * in comment text or an embed's markup stay inert, as the reference parses
     * only the stored content; where a filter changed the rendering itself,
     * nothing is parsed.
     *
     * @param \Closure(string): string $render
     */
    public function renderAdded(string $content, \Closure $render): string
    {
        $rendered = end($this->contentRendered);
        $at = is_string($rendered) ? ($rendered === '' ? 0 : strpos($content, $rendered)) : false;
        if ($at === false) {
            return $content;
        }
        $parse = static fn (string $part): string => str_contains($part, '<!-- wp:') ? $render($part) : $part;
        return $parse(substr($content, 0, $at)) . $rendered . $parse(substr($content, $at + strlen($rendered)));
    }

    /**
     * The engine rendered the content being filtered in do_blocks's place
     * (Runtime::blockContentFilter): its images count as fitted out from
     * here on, as those of content rendered before the filter began do.
     */
    public function renderedInFilter(string $html): void
    {
        if ($this->contentImages !== []) {
            $this->contentImages[array_key_last($this->contentImages)] = preg_match_all('/<img\s[^>]*>/i', $html, $m) > 0 ? $m[0] : [];
            $this->contentRendered[array_key_last($this->contentRendered)] = $html;
        }
    }

    /** Whether an image tag is one the content being filtered held when the filter began. */
    public function fittedImage(string $tag): bool
    {
        $images = end($this->contentImages);
        return is_array($images) && in_array($tag, $images, true);
    }

    /** True once, for the image that gets fetchpriority="high". */
    public function claimPriority(): bool
    {
        if ($this->priorityClaimed) {
            return false;
        }
        return $this->priorityClaimed = true;
    }

    /** Whether no element has taken high fetch priority yet on this page. */
    public function priorityAvailable(): bool
    {
        return !$this->priorityClaimed;
    }

    /** Takes high fetch priority off the table for the rest of the page; a plugin prioritising its own element does this. */
    public function closePriority(): void
    {
        $this->priorityClaimed = true;
    }

    /** Puts high fetch priority back on offer, so the next large enough image takes it. */
    public function reopenPriority(): void
    {
        $this->priorityClaimed = false;
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

    /** A caption's id, or its text's: as given the first time on the page, then "id-N" for repeats (probe gallery-captions). */
    public function uniqueCaptionId(string $id): string
    {
        $count = $this->captionIds[$id] = ($this->captionIds[$id] ?? 0) + 1;
        return $count === 1 ? $id : $id . '-' . $count;
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

    /**
     * Whether the block rendering now has its Interactivity API directives
     * processed, as interactivity_process_directives answered for it.
     */
    public function processesDirectives(?bool $answer = null): bool
    {
        if ($answer !== null) {
            $this->directives = $answer;
        }
        return $this->directives;
    }

    /** Whether rendering is inside a navigation block. */
    public function inNavigation(): bool
    {
        return $this->navigation > 0;
    }

    /** Marks a nested source as being rendered; false when it is already open (a cycle) or the render's budget is spent. */
    public function enter(string $key): bool
    {
        if (isset($this->active[$key]) || !$this->spend()) {
            return false;
        }
        $this->active[$key] = true;
        return true;
    }

    /** Counts one more source opened, starting over at an outermost render; false past MAX_SOURCES. */
    private function spend(): bool
    {
        if ($this->active === [] && $this->contentPosts === []) {
            $this->sources = 0;
        }
        return ++$this->sources <= self::MAX_SOURCES;
    }

    /** Leaves a cycle-guarded key. */
    public function leave(string $key): void
    {
        unset($this->active[$key]);
    }

    /** Whether a key is open: a source being rendered, or the page template ("template"). */
    public function inside(string $key): bool
    {
        return isset($this->active[$key]);
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

    /** How deep the block tree is right now; zero outside any block render. */
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
        $this->pendingVariation = null;
        return $class;
    }

    /** Holds the numbered style variation class a dynamic block claimed before rendering; returns the one it replaces. */
    public function setPendingVariation(?string $class): ?string
    {
        $outer = $this->pendingVariation;
        $this->pendingVariation = $class;
        return $outer;
    }

    /** The numbered style variation class the dynamic block being rendered claimed, once: its wrapper takes it. */
    public function takePendingVariation(): ?string
    {
        $class = $this->pendingVariation;
        $this->pendingVariation = null;
        return $class;
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
        $this->blocks = [];
        $this->galleries = [];
        $this->elements = 0;
        $this->active = [];
        $this->sources = 0;
        $this->depth = 0;
        $this->navigation = 0;
        $this->pendingElements = null;
        $this->labels = [];
        $this->captionIds = [];
    }
}
