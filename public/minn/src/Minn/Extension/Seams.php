<?php

declare(strict_types=1);

namespace Minn\Extension;

use Closure;
use Minn\Blocks\Block;
use Minn\Content\Reader;
use Minn\Content\Site;
use Minn\Db;
use Minn\Http;
use Minn\Http\Request;

/**
 * Where an extension can take part in a request: eight typed registrations,
 * the request they run in, and the outgoing HTTP its minn.json asks for.
 * This is the whole surface an extension sees. Each extension registers on
 * its own (forExtension), and what it registered joins the request's once
 * its register() returns (adopt). The engine calls what was registered
 * through SeamRunner, at the right moment and in registration order.
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

    /** Outgoing HTTP, bound to the hosts the extension's needs.http lists; a client that throws when it asked for none. */
    public readonly Http $http;

    public function __construct(
        public readonly Db $db,
        public readonly Site $site,
        public readonly Request $request,
        public readonly Reader $reader,
        ?Http $http = null,
    ) {
        $this->http = $http ?? Http::refused('Outgoing HTTP belongs to an extension, on the $minn its register() receives, once its minn.json asks for it under "needs": {"http": [...]}.');
    }

    /**
     * The seams one extension registers on: this request, and the outgoing
     * HTTP its manifest grants. A manifest without needs.http gets a client
     * that throws at its first request, naming the key to add.
     */
    public function forExtension(Manifest $manifest): self
    {
        $http = $manifest->http === null
            ? Http::refused("{$manifest->slug} did not ask for http: add \"needs\": {\"http\": [\"https://api.example.com/\"]} to its minn.json.")
            : new Http(within: $manifest->http);
        return new self($this->db, $this->site, $this->request, $this->reader, $http);
    }

    /**
     * Takes on what one extension registered, after its register() returned,
     * so an extension that throws half-way adds nothing. A closure the
     * engine hands seams to is handed that extension's own.
     */
    public function adopt(self $extension): void
    {
        foreach ($extension->blockGates as $gate) {
            $this->blockGates[] = static fn (Block $block): mixed => $gate($block, $extension);
        }
        foreach ($extension->blockFilters as $filter) {
            $this->blockFilters[] = static fn (Block $block, string $html): mixed => $filter($block, $html, $extension);
        }
        foreach ($extension->shortcodes as $tag => $render) {
            $this->shortcodes[$tag] = static fn (array $attributes, ?string $content): mixed => $render($attributes, $content, $extension);
        }
        foreach ($extension->head as $render) {
            $this->head[] = static fn (): mixed => $render($extension);
        }
        foreach ($extension->footer as $render) {
            $this->footer[] = static fn (): mixed => $render($extension);
        }
        array_push($this->contentFilters, ...$extension->contentFilters);
        array_push($this->bodyClasses, ...$extension->bodyClasses);
        array_push($this->documentFilters, ...$extension->documentFilters);
        $this->title = $extension->title ?? $this->title;
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

    /** Adds a body class. */
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
