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
        Constants::define($runtime);
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

    public static function cache(): ObjectCache
    {
        return self::$cache ??= new ObjectCache();
    }

    public static function shortcodes(): Shortcodes
    {
        return self::$shortcodes ??= new Shortcodes();
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
        self::$cache = new ObjectCache();
        self::$shortcodes = new Shortcodes();
        self::$options = self::$current === null ? null : new Options(self::$current->db);
    }
}
