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
use Minn\Json;

/**
 * Where an extension can take part in a request: twelve typed registrations,
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
    /** @var array<string, array{metadata: array<string, mixed>, render: Closure}> blocks of the extension's own, by name */
    private array $blocks = [];
    /** @var list<string> stylesheets the editor's previews load */
    private array $editorStyles = [];
    /** The Minn bar for a signed-in editor on this request, or null. */
    private ?AdminBar $bar = null;
    /** The registering extension's folder, which its block.json paths are read from. */
    private string $dir = '';

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
        $own->dir = $manifest->dir;
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
        array_push($this->editorStyles, ...$extension->editorStyles);
        foreach ($extension->blocks as $name => $block) {
            $render = $block['render'];
            $this->blocks[$name] = ['metadata' => $block['metadata'], 'render' => static fn (array $attributes, string $content): mixed => $render($attributes, $content, $extension)];
        }
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
     * A block of the extension's own. Its block.json (a folder holding one,
     * or the file, relative to the extension's folder) is the schema editors
     * read: name, title, attributes with their types and defaults, parent.
     * $render draws the block from its attributes (defaults filled in) and
     * its rendered inner blocks. The engine registers it as a dynamic block,
     * so it renders on the front, in REST answers and in the editor's
     * previews, and Minn Admin edits its attributes with a form. A file
     * that is missing or names no block throws, which skips the extension.
     *
     * @param Closure(array<string, mixed>, string, Seams): string $render
     */
    public function block(string $metadata, Closure $render): void
    {
        $file = str_ends_with($metadata, '.json') ? $metadata : rtrim($metadata, '/') . '/block.json';
        $path = str_starts_with($file, '/') ? $file : "{$this->dir}/{$file}";
        $data = Json::file($path, or: null);
        $name = is_array($data) && is_string($data['name'] ?? null) ? $data['name'] : '';
        if (preg_match('#^[a-z0-9-]+/[a-z0-9-]+$#', $name) !== 1) {
            throw new \InvalidArgumentException("{$path} is not a block.json with a namespaced name");
        }
        $this->blocks[$name] = ['metadata' => $data, 'render' => $render];
    }

    /**
     * Stylesheets the editor's block previews load, after the theme's own:
     * what an extension's blocks are drawn against when the theme's
     * style.css is not all of it. URLs, absolute or from the site root.
     */
    public function editorStyles(string ...$hrefs): void
    {
        array_push($this->editorStyles, ...$hrefs);
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
            $this->blocks,
            $this->editorStyles,
        );
    }
}
