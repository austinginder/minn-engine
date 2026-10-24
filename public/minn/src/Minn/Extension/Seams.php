<?php

declare(strict_types=1);

namespace Minn\Extension;

use Closure;
use Minn\Blocks\Block;
use Minn\Content\Reader;
use Minn\Content\Site;
use Minn\Content\PostRecord;
use Minn\Db;
use Minn\Front\AdminBar;
use Minn\Front\Resolution;
use Minn\Http;
use Minn\Http\Request;

/**
 * Where an extension can take part in a request: nine typed registrations,
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
    /** @var list<object> controllers whose #[Route] methods answer paths */
    private array $routes = [];
    /** The Minn bar for a signed-in editor on this request, or null. */
    private ?AdminBar $bar = null;

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
        $own = new self($this->db, $this->site, $this->request, $this->reader, $http);
        $own->bar = $this->bar;
        return $own;
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
        array_push($this->routes, ...$extension->routes);
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

    /**
     * Answers paths with controllers whose public methods carry #[Route]
     * attributes, as the engine's own do: each method takes the Request and
     * the captures it names and returns a Response. They are asked after the
     * engine's own routes and before the themed front, so an extension can
     * add pages but never take over one the engine answers.
     */
    public function routes(object ...$controllers): void
    {
        array_push($this->routes, ...$controllers);
    }

    /**
     * A whole HTML document an extension built, with the Minn bar a signed-in
     * editor sees on themed pages: its stylesheet before </head> and the bar
     * before </body>. Name the post the page shows and the bar's Edit opens
     * it. Anyone else gets the document unchanged.
     */
    public function frontBar(string $document, ?PostRecord $post = null): string
    {
        if ($this->bar === null) {
            return $document;
        }
        $head = stripos($document, '</head>');
        if ($head !== false) {
            $document = substr($document, 0, $head) . $this->bar->head() . substr($document, $head);
        }
        $markup = $this->bar->render($post === null ? Resolution::home() : Resolution::single($post));
        $body = strripos($document, '</body>');
        return $body === false ? $document . $markup : substr($document, 0, $body) . $markup . substr($document, $body);
    }

    /** The engine hands over this request's Minn bar before extensions register. Extensions have no reason to call this. */
    public function useFrontBar(?AdminBar $bar): void
    {
        $this->bar = $bar;
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
            $this->routes,
        );
    }
}
