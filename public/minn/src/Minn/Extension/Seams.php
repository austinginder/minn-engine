<?php

declare(strict_types=1);

namespace Minn\Extension;

use Closure;
use Minn\Content\Reader;
use Minn\Content\Site;
use Minn\Db;
use Minn\Http\Request;

/**
 * Where an extension can take part in a request: eight typed registrations
 * and the request they run in. This is the whole surface an extension sees.
 * The engine calls what was registered through SeamRunner, at the right
 * moment and in registration order.
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
    /** @var Closure(string): string|null */
    private ?Closure $title = null;

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

    /** Replaces the document title; receives the engine's own. */
    public function title(Closure $filter): void
    {
        $this->title = $filter;
    }

    /** Rewrites the whole themed document before it is sent (what an output buffer did on the reference). */
    public function filterDocument(Closure $filter): void
    {
        $this->documentFilters[] = $filter;
    }

    /** What was registered, for the engine's runner. Extensions have no reason to call this. */
    public function registrations(): Registrations
    {
        return new Registrations(
            $this->blockGates,
            $this->blockFilters,
            $this->shortcodes,
            $this->contentFilters,
            $this->head,
            $this->footer,
            $this->bodyClasses,
            $this->documentFilters,
            $this->title,
        );
    }
}
