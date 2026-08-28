<?php

declare(strict_types=1);

namespace Minn\Extension;

use Closure;
use Minn\Blocks\Block;
use Minn\Blocks\Renderer;
use Minn\Content\Reader;
use Minn\Content\Site;
use Minn\Db;
use Minn\Http\Request;

/**
 * Where an extension can take part in a request. Each seam is a typed
 * registration; the engine calls them at the right moment and in the
 * order they were registered.
 */
final class Seams
{
    /** @var list<Closure(Block, Seams): ?bool> null to leave a block alone, false to drop it */
    private array $blockGates = [];
    /** @var list<Closure(Block, string, Seams): string> */
    private array $blockFilters = [];
    /** @var array<string, Closure(array<string, string>, ?string, Seams): string> */
    private array $shortcodes = [];
    /** @var list<Closure(string, array): string> */
    private array $contentFilters = [];
    /** @var list<Closure(Seams): string> */
    private array $head = [];
    /** @var list<Closure(Seams): string> */
    private array $footer = [];
    /** @var list<string> */
    private array $bodyClasses = [];
    /** @var list<Closure(string): string> */
    private array $documentFilters = [];

    public function __construct(
        public readonly Db $db,
        public readonly Site $site,
        public readonly Request $request,
        public readonly Reader $reader,
    ) {
    }

    /** Decides whether a block renders at all; the first gate to answer false wins. */
    public function gateBlocks(Closure $gate): void
    {
        $this->blockGates[] = $gate;
    }

    /** Rewrites a block's rendered markup. */
    public function filterBlocks(Closure $filter): void
    {
        $this->blockFilters[] = $filter;
    }

    /** Renders [tag attr="…"]content[/tag] wherever it appears in post content. */
    public function shortcode(string $tag, Closure $render): void
    {
        $this->shortcodes[$tag] = $render;
    }

    /** Rewrites rendered post content (after blocks and shortcodes). */
    public function filterContent(Closure $filter): void
    {
        $this->contentFilters[] = $filter;
    }

    /** Markup for the document head (a style, a meta tag, a script). */
    public function head(Closure $render): void
    {
        $this->head[] = $render;
    }

    /** Markup before </body>. */
    public function footer(Closure $render): void
    {
        $this->footer[] = $render;
    }

    public function bodyClass(string $class): void
    {
        $this->bodyClasses[] = $class;
    }

    /** Rewrites the whole themed document before it is sent (what an output buffer did on the reference). */
    public function filterDocument(Closure $filter): void
    {
        $this->documentFilters[] = $filter;
    }

    public function applyDocumentFilters(string $html): string
    {
        foreach ($this->documentFilters as $filter) {
            $html = $filter($html);
        }
        return $html;
    }

    /** The engine's side. */
    public function allowsBlock(Block $block): bool
    {
        foreach ($this->blockGates as $gate) {
            if ($gate($block, $this) === false) {
                return false;
            }
        }
        return true;
    }

    public function applyBlockFilters(Block $block, string $html): string
    {
        foreach ($this->blockFilters as $filter) {
            $html = $filter($block, $html, $this);
        }
        return $html;
    }

    /** @return array<string, Closure> */
    public function shortcodes(): array
    {
        return $this->shortcodes;
    }

    public function applyContentFilters(string $html, array $post): string
    {
        foreach ($this->contentFilters as $filter) {
            $html = $filter($html, $post);
        }
        return $html;
    }

    public function renderHead(): string
    {
        return implode('', array_map(fn (Closure $r) => $r($this), $this->head));
    }

    public function renderFooter(): string
    {
        return implode('', array_map(fn (Closure $r) => $r($this), $this->footer));
    }

    /** @return list<string> */
    public function bodyClasses(): array
    {
        return $this->bodyClasses;
    }

    public function hasShortcodes(): bool
    {
        return $this->shortcodes !== [];
    }
}
