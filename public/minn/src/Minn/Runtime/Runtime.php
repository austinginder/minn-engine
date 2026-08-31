<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Auth\Capabilities;
use Minn\Content\Reader;
use Minn\Content\Site;
use Minn\Db;
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

    public function __construct(
        public readonly Db $db,
        public readonly Site $site,
        public readonly ?Request $request,
        public readonly Reader $reader,
        public readonly Capabilities $capabilities,
        public readonly string $engineDir,
        public readonly string $absPath,
        public readonly string $version,
        public readonly bool $isAdmin = false,
    ) {
    }

    /** Makes this request's runtime the one the facade sees and defines the facade. */
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
        register_shutdown_function(static function (): void {
            self::hooks()->action('shutdown', []);
        });
        return $runtime;
    }

    public static function current(): self
    {
        if (self::$current === null) {
            throw new \LogicException('The WordPress runtime has not been booted for this request');
        }
        return self::$current;
    }

    public static function booted(): bool
    {
        return self::$current !== null;
    }

    public static function hooks(): Hooks
    {
        return self::$hooks ??= new Hooks();
    }

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

    public static function cache(): ObjectCache
    {
        return self::$cache ??= new ObjectCache();
    }

    public static function shortcodes(): Shortcodes
    {
        return self::$shortcodes ??= new Shortcodes();
    }

    public static function scriptModules(): ScriptModules
    {
        return self::$scriptModules ??= new ScriptModules(
            static fn (string $src, string|false|null $version): string => \_minn_script_module_url($src, $version),
            static fn (string $id, array $data): array => (array) self::hooks()->filter('script_module_data_' . $id, [$data]),
        );
    }

    public static function interactivity(): Interactivity
    {
        return self::$interactivity ??= new Interactivity();
    }

    public static function blockTemplates(): BlockTemplates
    {
        return self::$blockTemplates ??= new BlockTemplates();
    }

    public static function registry(): Registry
    {
        return self::$registry ??= new Registry(self::current()->engineDir);
    }

    public static function postQuery(): PostQuery
    {
        return new PostQuery(self::current()->db, self::registry());
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->state[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->state[$key] = $value;
    }

    public function isSecure(): bool
    {
        return $this->request?->secure ?? false;
    }

    public function contentDir(): string
    {
        return rtrim($this->absPath, '/') . '/wp-content';
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
        self::$options = self::$current === null ? null : new Options(self::$current->db);
    }
}
