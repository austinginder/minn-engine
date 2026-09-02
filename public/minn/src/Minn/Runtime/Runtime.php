<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Auth\Capabilities;
use Minn\Content\Reader;
use Minn\Content\Site;
use Minn\Context;
use Minn\Db;
use Minn\Blocks\RenderState;
use Minn\Blocks\Renderer as BlockRenderer;
use Minn\Extension\SeamRunner;
use Minn\Http\Request;

/**
 * The WordPress runtime the engine offers plugin code: the procedural
 * facade under minn/wp-api/ plus the services it delegates to. One per
 * request; the facade reaches it through these statics.
 */
final class Runtime
{
    private static ?Hooks $hooks = null;
    private static ?Options $options = null;
    private static ?ObjectCache $cache = null;
    private static ?Shortcodes $shortcodes = null;
    private static ?Registry $registry = null;
    private static ?Interactivity $interactivity = null;
    private static ?ScriptModules $scriptModules = null;
    private static ?BlockTemplates $blockTemplates = null;
    private static ?self $current = null;
    private static bool $facadeLoaded = false;
    /** @var array<string, mixed> plugin-visible state the facade keeps between calls */
    private array $state = [];

    /** The database door this request answers through. */
    public readonly Db $db;
    /** The site's options. */
    public readonly Site $site;
    /** The request being answered, absent on the command line. */
    public readonly ?Request $request;
    /** Who is reading this request. */
    public readonly Reader $reader;
    /** The capability engine. */
    public readonly Capabilities $capabilities;
    /** The minn/ folder: the engine's own files. */
    public readonly string $engineDir;
    /** The site root with a trailing slash. */
    public readonly string $absPath;
    /** The WordPress release whose contracts the runtime speaks. */
    public readonly string $version;
    /** The extension seams this request registered, once the front has loaded them. */
    private ?SeamRunner $seams = null;
    /** The counters and collected styles of everything rendered for this request. */
    private ?RenderState $renderState = null;
    /** The block renderer this request renders through. */
    private ?BlockRenderer $blockRenderer = null;

    /**
     * The runtime for one request. Everything about the request itself
     * comes from the context; the fields below it are the same values,
     * kept as properties because plugin code reaches for them by name.
     */
    public function __construct(
        public readonly Context $context,
        public readonly bool $isAdmin = false,
    ) {
        $this->db = $context->db;
        $this->site = $context->site;
        $this->request = $context->request;
        $this->reader = $context->reader;
        $this->capabilities = $context->capabilities;
        $this->engineDir = $context->engineDir;
        $this->absPath = $context->absPath;
        $this->version = $context->version;
    }

    /** Holds the extension seams this request registered, so nothing static has to. */
    public function useSeams(SeamRunner $seams): void
    {
        $this->seams = $seams;
    }

    /** The extension seams, or null before the front has registered any (REST and the CLI never do). */
    public function seams(): ?SeamRunner
    {
        return $this->seams;
    }

    /** The render state for this request, made on first use: one set of counters for everything rendered. */
    public function renderState(): RenderState
    {
        return $this->renderState ??= new RenderState();
    }

    /** Makes a render state this request's, so a renderer that brought its own is the one the leaves read. */
    public function useRenderState(RenderState $state): void
    {
        $this->renderState = $state;
    }

    /** The block renderer for this request, made on first use over this request's own database door. */
    public function blockRenderer(): BlockRenderer
    {
        return $this->blockRenderer ??= BlockRenderer::forDb($this->db);
    }

    /**
     * Makes this request's runtime the one the facade sees and defines the
     * facade.
     *
     * Deliberately does NOT start the facade's registries empty. A process
     * answering a second request would then have a fresh hook table that no
     * plugin can fill again: plugin files register their hooks as they are
     * included, and an include happens once per process. Until a plugin's
     * registrations can be replayed, a second boot inherits the first's
     * registries on purpose, which is why a worker runtime is not yet
     * something the engine claims (see contracts/runtime.md).
     */
    public static function boot(self $runtime): self
    {
        self::$current = $runtime;
        self::$options = new Options($runtime->db);
        self::loadFacade($runtime->engineDir);
        _minn_bind_hook_globals();
        Constants::define($runtime);
        _minn_main_query();
        _minn_rewrite();
        $GLOBALS['wpdb'] = new \wpdb(defined('DB_USER') ? DB_USER : '', defined('DB_PASSWORD') ? DB_PASSWORD : '', defined('DB_NAME') ? DB_NAME : '', defined('DB_HOST') ? DB_HOST : '');
        $GLOBALS['wp_textdomain_registry'] = new \WP_Textdomain_Registry();
        // Plugin code reads the locale's names off the global directly
        // (WooCommerce's block settings want weekday_abbrev), so it is
        // bound with the runtime, not built on first use.
        $GLOBALS['wp_locale'] = new \WP_Locale();
        register_shutdown_function(static function (): void {
            self::hooks()->action('shutdown', []);
        });
        return $runtime;
    }

    /** The booted runtime; throws when there is none. */
    public static function current(): self
    {
        if (self::$current === null) {
            throw new \LogicException('The WordPress runtime has not been booted for this request');
        }
        return self::$current;
    }

    /** Whether the runtime is up. */
    public static function booted(): bool
    {
        return self::$current !== null;
    }

    /** The hook registry. */
    public static function hooks(): Hooks
    {
        return self::$hooks ??= new Hooks();
    }

    /** The options store. */
    public static function options(): Options
    {
        return self::$options ??= new Options(self::current()->db);
    }

    /** Output an action's callbacks print, as a string. */
    /**
     * Runs an action and returns what it printed. Plugin callbacks may open
     * output buffers of their own during the action (a page post-processor
     * started in wp_head) or close one they think is theirs (the same plugin
     * in wp_footer); a sentinel buffer under the capture keeps the output
     * either way: extra buffers are flushed through their handlers into the
     * capture, and a capture closed early lands in the sentinel.
     */
    public static function capture(string $action, array $args = []): string
    {
        $base = ob_get_level();
        ob_start();
        ob_start();
        try {
            self::hooks()->action($action, $args);
        } finally {
            while (ob_get_level() > $base + 2) {
                ob_end_flush();
            }
            $out = ob_get_level() === $base + 2 ? (string) ob_get_clean() : '';
            $out = (ob_get_level() === $base + 1 ? (string) ob_get_clean() : '') . $out;
        }
        return $out;
    }

    /** The object cache. */
    public static function cache(): ObjectCache
    {
        return self::$cache ??= new ObjectCache();
    }

    /** The shortcode registry. */
    public static function shortcodes(): Shortcodes
    {
        return self::$shortcodes ??= new Shortcodes();
    }

    /** The script modules registry. */
    public static function scriptModules(): ScriptModules
    {
        return self::$scriptModules ??= new ScriptModules(
            static fn (string $src, string|false|null $version): string => \_minn_script_module_url($src, $version),
            static fn (string $id, array $data): array => (array) self::hooks()->filter('script_module_data_' . $id, [$data]),
        );
    }

    /** The interactivity API. */
    public static function interactivity(): Interactivity
    {
        return self::$interactivity ??= new Interactivity();
    }

    /** The registered block templates. */
    public static function blockTemplates(): BlockTemplates
    {
        return self::$blockTemplates ??= new BlockTemplates();
    }

    /** The post types, taxonomies, and statuses. */
    public static function registry(): Registry
    {
        return self::$registry ??= new Registry(self::current()->engineDir);
    }

    /** A fresh post query over the runtime's registry. */
    public static function postQuery(): PostQuery
    {
        return new PostQuery(self::current()->db, self::registry());
    }

    /** A per-request state value. */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->state[$key] ?? $default;
    }

    /** Sets a per-request state value. */
    public function set(string $key, mixed $value): void
    {
        $this->state[$key] = $value;
    }

    /** Whether the request is over HTTPS. */
    public function isSecure(): bool
    {
        return $this->context->isSecure();
    }

    /** wp-content under the site root. */
    public function contentDir(): string
    {
        return $this->context->contentDir();
    }

    /** Defines the facade functions once; safe to call again. */
    public static function loadFacade(string $engineDir): void
    {
        if (self::$facadeLoaded) {
            return;
        }
        self::$facadeLoaded = true;
        foreach (glob($engineDir . '/wp-api/classes/*.php') ?: [] as $file) {
            require_once $file;
        }
        // Generated placeholders extend real classes, so they load after them.
        foreach (glob($engineDir . '/wp-api/classes/placeholders/*.php') ?: [] as $file) {
            require_once $file;
        }
        foreach (glob($engineDir . '/wp-api/*.php') ?: [] as $file) {
            require_once $file;
        }
        // The reference's own registrations, after every function exists.
        foreach (glob($engineDir . '/wp-api/defaults/*.php') ?: [] as $file) {
            require_once $file;
        }
    }

    /** Fresh per-request state, for suites. */
    public static function reset(): void
    {
        self::$hooks = new Hooks();
        if (self::$facadeLoaded) {
            _minn_bind_hook_globals();
        }
        self::$cache = new ObjectCache();
        self::$shortcodes = new Shortcodes();
        self::$registry = null;
        self::$interactivity = new Interactivity();
        self::$scriptModules = null;
        self::$blockTemplates = null;
        self::$options = self::$current === null ? null : new Options(self::$current->db);
    }
}
