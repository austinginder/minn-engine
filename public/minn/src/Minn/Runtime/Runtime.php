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
use Minn\I18n\LocaleStack;
use Minn\I18n\TextDomains;

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
    private static ?TextDomains $textDomains = null;
    private static ?LocaleStack $locales = null;
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
    /** Who reads this request; settled again (identify()) once plugins have said who the user is. */
    public Reader $reader;
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

    /** The request's reader from now on: the user plugin code named through determine_current_user (Runtime\CurrentUser). */
    public function identify(Reader $reader): void
    {
        $this->reader = $reader;
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
        self::storeOptions($runtime->db);
        self::loadObjectCacheDropin($runtime);
        self::loadFacade($runtime->engineDir);
        _minn_bind_hook_globals();
        // Arguments given as a query string (or none) are parsed through wp_parse_str's filter, as the reference's wp_parse_args parses them.
        \Minn\Support\Lists::filterParsed(static fn (array $parsed): mixed => self::hooks()->filter('wp_parse_str', [$parsed]));
        self::registry()->shareObjects();
        Constants::define($runtime);
        // wp_installing() starts from WP_INSTALLING, as the reference's does.
        if (EarlyRequest::installing()) {
            $runtime->installingFlag(true);
        }
        if ($runtime->request !== null) {
            \_minn_script_globals($runtime->request->path);
        }
        // The server variables made whole as the reference's boot makes them, before any plugin reads one.
        \Minn\Http\Request::fixServerVars();
        \_minn_browser_globals();
        _minn_main_query();
        _minn_rewrite();
        \_minn_require_wp_db($runtime->contentDir());
        \_minn_start_object_cache();
        $GLOBALS['wp_textdomain_registry'] = Handoff::newTextdomainRegistry();
        $GLOBALS['wp_textdomain_registry']->init();
        // Plugin code reads the locale's names off the global directly
        // (WooCommerce's block settings want weekday_abbrev), so it is
        // bound with the runtime, not built on first use.
        $GLOBALS['wp_locale'] = Handoff::newLocale();
        \_minn_locale_switcher();
        // The theme folder registered as the reference registers it while loading.
        Themes::registerDirectory(Themes::root());
        // Core's block patterns are on for every theme before plugins and the theme load, so either may turn them off.
        ThemeSupports::add('core-block-patterns', []);
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

    /**
     * The options store for a connection, made as soon as the request has
     * one (Engine::answer) and kept by the boot that follows: the reads made
     * while routing come from the same autoloaded list as everything after,
     * and Db::option() reads through it from then on.
     */
    public static function storeOptions(Db $db): void
    {
        if (!self::$options instanceof Options || !self::$options->isFor($db)) {
            self::$options = new Options($db);
        }
        Db::readOptionsThrough(static fn (string $name): ?string => self::options()->raw($name));
    }

    /** Whether this request is installing (wp_installing()); false before a runtime boots. */
    public static function installing(): bool
    {
        return (bool) self::$current?->get('installing', false);
    }

    /** Whether this request is installing (wp_installing); given a value, it sets the flag and answers what it was. */
    public function installingFlag(mixed $isInstalling = null): bool
    {
        $current = (bool) $this->get('installing', false);
        if ($isInstalling !== null) {
            $this->set('installing', (bool) $isInstalling);
        }
        return $current;
    }

    /** Tells the option store about a row the engine wrote behind it; nothing to do when no store was made. */
    public static function optionWritten(string $name): void
    {
        self::$options?->forget($name);
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
        OutputFloor::hold();
        try {
            self::hooks()->action($action, $args);
        } finally {
            OutputFloor::release();
            while (ob_get_level() > $base + 2) {
                ob_end_flush();
            }
            $out = ob_get_level() === $base + 2 ? (string) ob_get_clean() : '';
            $out = (ob_get_level() === $base + 1 ? (string) ob_get_clean() : '') . $out;
        }
        return $out;
    }

    /**
     * The the_content defaults the engine's own rendering has already done:
     * blocks, texturize, paragraphs, block hooks, and the image attributes.
     * Shortcodes run in their place in the chain (do_shortcode at 11), so a
     * shortcode a plugin's filter adds before then runs as on the reference,
     * and block markup a filter adds before do_blocks is rendered then. What
     * the engine has not done (an attachment's own link, smilies, the capital
     * P, insecure home addresses) runs with the plugins' own callbacks.
     */
    private const CONTENT_DONE = [
        'apply_block_hooks_to_content_from_post_object' => 8,
        'do_blocks' => [9, '_minn_do_added_blocks'],
        'wptexturize' => 10,
        'wpautop' => 10,
        'shortcode_unautop' => 10,
        // The images are fitted out already; what is left of its work is each one through wp_content_img_tag.
        'wp_filter_content_tags' => [12, '_minn_content_img_tag'],
    ];

    /**
     * Classic content (no block markup) through the whole of the_content, as
     * the reference runs it: handed as written, core's defaults (embeds,
     * texturize, paragraphs, shortcodes, images) in their places, so a filter
     * before them is handed the stored text and what it adds is formatted
     * with the rest (runtime suite, "plain text a filter adds").
     */
    public static function classicContentFilter(string $content): string
    {
        // do_blocks makes classic text a block too, and rendering it uses the page's script registry.
        if ($content !== '') {
            AssetQueue::used();
        }
        return (string) RenderState::current()->filteringContent($content, static fn () => self::hooks()->filter('the_content', [$content]));
    }

    /**
     * Block content through the_content as the reference runs it: handed as
     * written, so a filter before do_blocks is handed the stored block
     * markup; the engine's renderer in do_blocks's place, rendering what those
     * filters added with the rest and never parsing its own output again
     * (comment text a comments block prints stays text); then the rest of
     * the_content, the defaults the renderer has done left out. $rendered,
     * when given, takes the rendering at once (the engine's extension seams).
     *
     * @param \Closure(string): string|null $rendered
     */
    public static function blockContentFilter(string $content, ?\Closure $rendered = null): string
    {
        $render = static function ($markup) use ($rendered): string {
            $html = \Minn\Content\Blocks::render((string) $markup);
            $html = $rendered === null ? $html : $rendered($html);
            RenderState::current()->renderedInFilter($html);
            return $html;
        };
        // The post's block hooks run in their place (probe post-block-hooks); the rest of the defaults are done.
        $done = array_diff_key(['do_blocks' => [9, $render]] + self::CONTENT_DONE, ['apply_block_hooks_to_content_from_post_object' => true]);
        return (string) RenderState::current()->filteringContent($content, static fn () => self::hooks()->filterWithout('the_content', [$content], $done));
    }

    /** Content the engine rendered itself, through the_content for everything else hooked there. */
    public static function contentFilter(string $content): string
    {
        return (string) RenderState::current()->filteringContent($content, static fn () => self::hooks()->filterWithout('the_content', [$content], self::CONTENT_DONE));
    }

    /** The object cache. */
    public static function cache(): ObjectCache
    {
        return self::$cache ??= new ObjectCache();
    }

    /** The text domains loaded for this request. */
    public static function textDomains(): TextDomains
    {
        return self::$textDomains ??= new TextDomains();
    }

    /** The locales this request switched into. */
    public static function locales(): LocaleStack
    {
        return self::$locales ??= new LocaleStack();
    }

    /** The shortcode registry. */
    public static function shortcodes(): Shortcodes
    {
        return self::$shortcodes ??= new Shortcodes();
    }

    /** The script modules registry. */
    public static function scriptModules(): ScriptModules
    {
        return self::$scriptModules ??= self::newScriptModules();
    }

    /** A script modules registry of its own, as each new WP_Script_Modules keeps (probe script-modules-printing). */
    public static function newScriptModules(): ScriptModules
    {
        return new ScriptModules(
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
        return new PostQuery(self::current()->db);
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

    /** A per-request state value, made once by the given callable the first time it is asked for (a facade class's single instance). */
    public function remember(string $key, callable $make): mixed
    {
        return $this->state[$key] ??= $make();
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

    /** Whether the facade's functions and classes are defined yet. */
    public static function facadeLoaded(): bool
    {
        return self::$facadeLoaded;
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
            // The pluggable functions wait for the plugins (loadPluggables()).
            if (basename($file) !== 'pluggable.php') {
                require_once $file;
            }
        }
        // Generated dead ends (wp-admin, the editors, the Customizer, XML-RPC) may extend any facade class, so they come last.
        foreach (glob($engineDir . '/wp-api/classes/deadends/*.php') ?: [] as $file) {
            require_once $file;
        }
        // The reference's own registrations, after every function exists.
        foreach (glob($engineDir . '/wp-api/defaults/*.php') ?: [] as $file) {
            require_once $file;
        }
    }

    /**
     * A site's object-cache.php drop-in, before the facade defines its own
     * cache functions: the drop-in's functions and WP_Object_Cache win, and
     * the facade fills in only what it left out. Loaded once per process,
     * unless enable_loading_object_cache_dropin (asked of the hooks added
     * before boot, as the reference asks it whether or not there is a file)
     * says no.
     */
    private static function loadObjectCacheDropin(self $runtime): void
    {
        if (self::$facadeLoaded || !EarlyFilters::apply('enable_loading_object_cache_dropin', true)) {
            return;
        }
        $file = $runtime->contentDir() . '/object-cache.php';
        if (!is_file($file)) {
            return;
        }
        if (!defined('WP_CONTENT_DIR')) {
            define('WP_CONTENT_DIR', $runtime->contentDir());
        }
        require_once $file;
        $GLOBALS['_wp_using_ext_object_cache'] = true;
    }

    /**
     * The pluggable functions, once the plugins have had their chance to
     * define their own: each is defined only where no plugin did. The plugin
     * loader calls this before plugins_loaded; a boot that loads no plugins
     * calls it straight away.
     */
    public static function loadPluggables(): void
    {
        require_once self::current()->engineDir . '/wp-api/pluggable.php';
    }

    /** Fresh per-request state, for suites. */
    public static function reset(): void
    {
        self::$hooks = new Hooks();
        if (self::$facadeLoaded) {
            _minn_bind_hook_globals();
        }
        self::$cache = new ObjectCache();
        \Minn\Content\RowCache::flush();
        self::$shortcodes = new Shortcodes();
        self::$registry = null;
        self::$interactivity = new Interactivity();
        self::$scriptModules = null;
        self::$blockTemplates = null;
        self::$textDomains = null;
        self::$locales = null;
        self::$options = self::$current === null ? null : new Options(self::$current->db);
        unset($GLOBALS['wp_meta_keys']);
    }
}
