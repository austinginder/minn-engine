<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Throwable;

/**
 * Loads the site's plugins into the runtime the way the reference does:
 * mu-plugins first, then active_plugins in stored order, each file
 * included once. A plugin loads only when the static symbol read finds
 * nothing the runtime lacks; otherwise it is reported and skipped so the
 * site keeps rendering. The lifecycle actions fire between the phases.
 */
final class Plugins
{
    /** Plugin files the engine answers itself; never loaded as code. */
    private const NATIVE = ['minn-admin/minn-admin.php'];

    /** @var list<string> plugin files (relative) loaded as code this request */
    private static array $loaded = [];
    /** @var array<string, array{functions: list<string>, classes: list<string>, files: int, truncated: bool, error?: string}> */
    private static array $skipped = [];

    public static function load(Runtime $runtime): void
    {
        $content = $runtime->contentDir();
        foreach (glob($content . '/mu-plugins/*.php') ?: [] as $file) {
            self::includeFile($file, basename($file), $runtime);
        }
        $hooks = Runtime::hooks();
        $hooks->action('muplugins_loaded', []);
        $active = $runtime->options()->get('active_plugins');
        foreach (is_array($active) ? $active : [] as $plugin) {
            $plugin = (string) $plugin;
            if (in_array($plugin, self::NATIVE, true) || !preg_match('#^[A-Za-z0-9_.-]+(/[A-Za-z0-9_.-]+)?\.php$#', $plugin)) {
                continue;
            }
            $file = $content . '/plugins/' . $plugin;
            if (!is_file($file)) {
                continue;
            }
            self::includeFile($file, $plugin, $runtime);
        }
        $hooks->action('plugins_loaded', []);
        $hooks->action('sanitize_comment_cookies', []);
        $hooks->action('setup_theme', []);
        $hooks->action('after_setup_theme', []);
        $hooks->action('init', []);
        $hooks->action('wp_loaded', []);
    }

    /** @return list<string> */
    public static function loaded(): array
    {
        return self::$loaded;
    }

    /** @return array<string, array<string, mixed>> plugin file => why it did not load */
    public static function skipped(): array
    {
        return self::$skipped;
    }

    /** True when the named plugin file is running as code this request. */
    public static function isLoaded(string $plugin): bool
    {
        return in_array($plugin, self::$loaded, true);
    }

    private static function includeFile(string $file, string $name, Runtime $runtime): void
    {
        $dir = str_contains($name, '/') ? dirname($file) : $file;
        $missing = Symbols::missing($dir, Runtime::options());
        if ($missing['functions'] !== [] || $missing['classes'] !== [] || $missing['truncated']) {
            self::$skipped[$name] = $missing;
            return;
        }
        try {
            self::registerRealpath($file, $name, $runtime);
            self::isolatedInclude($file);
            self::$loaded[] = $name;
        } catch (Throwable $e) {
            self::$skipped[$name] = ['functions' => [], 'classes' => [], 'files' => $missing['files'], 'truncated' => false, 'error' => $e->getMessage()];
            error_log("Minn Engine: plugin {$name} failed while loading: " . $e->getMessage());
        }
    }

    /**
     * A symlinked plugin folder resolves __FILE__ to its real location; the
     * map from that location back to the plugins directory keeps
     * plugin_basename() and plugins_url() truthful (the reference keeps the
     * same map).
     */
    private static function registerRealpath(string $file, string $name, Runtime $runtime): void
    {
        if (!str_contains($name, '/')) {
            return;
        }
        $real = realpath(dirname($file));
        $expected = $runtime->contentDir() . '/plugins/' . dirname($name);
        if ($real !== false && $real !== $expected) {
            $map = $runtime->get('plugin_realpaths', []);
            $map[$real] = $expected;
            $runtime->set('plugin_realpaths', $map);
        }
    }

    /** The include gets a clean local scope, as wp-settings.php gives every plugin file. */
    private static function isolatedInclude(string $file): void
    {
        (static function () use ($file): void {
            include_once $file;
        })();
    }
}
