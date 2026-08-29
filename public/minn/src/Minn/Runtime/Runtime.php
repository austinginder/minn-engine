<?php

declare(strict_types=1);

namespace Minn\Runtime;

/**
 * The WordPress runtime the engine offers plugin code: the procedural
 * facade under minn/wp-api/ plus the services it delegates to. One per
 * request; the facade reaches it through these statics.
 */
final class Runtime
{
    private static ?Hooks $hooks = null;
    private static bool $facadeLoaded = false;

    public static function hooks(): Hooks
    {
        return self::$hooks ??= new Hooks();
    }

    /** Defines the facade functions once; safe to call again. */
    public static function loadFacade(string $engineDir): void
    {
        if (self::$facadeLoaded) {
            return;
        }
        self::$facadeLoaded = true;
        foreach (glob($engineDir . '/wp-api/*.php') ?: [] as $file) {
            require_once $file;
        }
    }

    /** Fresh state, for suites. */
    public static function reset(): void
    {
        self::$hooks = new Hooks();
    }
}
