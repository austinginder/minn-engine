<?php

declare(strict_types=1);

namespace Minn\Extension;

use Closure;
use ReflectionClass;
use ReflectionMethod;
use Minn\Blocks\Block;
use Minn\Content\Reader;
use Minn\Content\Site;
use Minn\Content\PostRecord;
use Minn\Content\UserRecord;
use Minn\Content\Users;
use Minn\Db;
use Minn\Front\AdminBar;
use Minn\Front\Resolution;
use Minn\Http;
use Minn\Http\Request;
use Minn\Http\Route;
use Minn\Json;

/**
 * Where an extension can take part in a request: thirteen typed registrations,
 * the request they run in (and who it speaks for), the accounts a sign-in
 * surface of its own may sign in and make, and the outgoing HTTP its
 * minn.json asks for.
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
    /** @var list<object> controllers whose #[Route] methods answer REST routes */
    private array $rest = [];
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
        array_push($this->rest, ...$extension->rest);
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
     * Answers REST routes (under /wp-json/, and as ?rest_route=) with
     * controllers whose public methods carry #[Route] attributes, as the
     * engine's own REST controllers do: the pattern leaves out /wp-json
     * ("/acme/v1/notes/{id:\d+}"), and the route's Policy is judged by the
     * engine's REST gate, so the sign-in cookie counts only with its
     * wp_rest nonce (X-WP-Nonce or _wpnonce) or an application password,
     * and a refusal is the engine's own (401 rest_not_logged_in, 403
     * rest_cookie_invalid_nonce, 403 for a missing capability). They are
     * asked after the engine's routes and before plugins' routes; user()
     * is the caller the gate judged. Each pattern names a namespace of the
     * extension's own: one under wp, wp-*, oembed or minn-admin (where
     * WordPress's and the engine's routes live) throws, which skips the
     * extension.
     */
    public function rest(object ...$controllers): void
    {
        foreach ($controllers as $controller) {
            foreach ((new ReflectionClass($controller))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                foreach ($method->getAttributes(Route::class) as $attribute) {
                    $pattern = $attribute->newInstance()->pattern;
                    if (preg_match('#^/[a-z0-9-]+/[^/]+#', $pattern) !== 1 || preg_match('#^/(?:wp|wp-[a-z0-9-]+|oembed|minn-admin)/#', $pattern) === 1) {
                        throw new \InvalidArgumentException("REST route {$pattern} is not in a namespace of the extension's own (/acme/v1/...)");
                    }
                }
            }
        }
        array_push($this->rest, ...$controllers);
    }

    /**
     * The signed-in user this request speaks for, or null for nobody. On a
     * REST route that is the caller the REST gate accepted (the cookie with
     * its nonce, or an application password); on a page it is the sign-in
     * cookie alone, so a page reads with it and never writes on it.
     */
    public function user(): ?UserRecord
    {
        return $this->reader->userId > 0 ? (new Users($this->db))->find($this->reader->userId) : null;
    }

    /**
     * Accounts for a sign-in surface of the extension's own, from its REST
     * routes: sign in and out, register with a chosen password, mail a
     * reset link, each as the engine's sign-in page does it (Accounts).
     */
    public function accounts(): Accounts
    {
        return new Accounts($this->db, $this->request, $this->reader);
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
            $this->rest,
        );
    }
}
