<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Support\FileHeaders;

use Minn\Content\Site;
use Minn\Http\Failure;
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
    /**
     * Minn Admin loads as code for its adapters (surfaces, licenses,
     * connectors, custom CSS, the plugin routes the engine has no
     * controller for), but the engine owns the shell, the front bar, the
     * sign-in flow, and maintenance: those hooks come off right after the
     * include so the two never print twice or disagree.
     */
    private const MINN_ADMIN = 'minn-admin/minn-admin.php';
    private const MINN_ADMIN_HOOKS = [
        ['template_redirect', ['Minn_Admin', 'maybe_render_app'], 0],
        ['template_redirect', ['Minn_Admin', 'maybe_maintenance_mode'], 1],
        ['rest_authentication_errors', ['Minn_Admin', 'maintenance_rest'], 20],
        ['login_redirect', ['Minn_Admin', 'login_redirect'], 20],
        ['show_admin_bar', ['Minn_Admin', 'enforce_toolbar_policy'], 99],
        ['show_admin_bar', ['Minn_Admin_Bar', 'suppress_core_bar'], 100],
        ['wp_enqueue_scripts', ['Minn_Admin_Bar', 'enqueue'], 10],
        ['wp_footer', ['Minn_Admin_Bar', 'render'], 10],
        ['body_class', ['Minn_Admin_Bar', 'body_class'], 10],
    ];

    /** @var list<string> plugin files (relative) loaded as code this request */
    private static array $loaded = [];
    /** @var array<string, array{functions: list<string>, classes: list<string>, files: int, truncated: bool, error?: string}> */
    private static array $skipped = [];

    /**
     * Loads the active plugins as code and fires the boot hooks. Recovery
     * is armed for exactly this window: a failure here is one every
     * visitor would hit, so it may be recorded against its extension.
     */
    public static function load(Runtime $runtime): void
    {
        Failure::armRecovery();
        try {
            self::boot($runtime);
        } catch (\Minn\Login\ServeLogin $signal) {
            // A plugin asked for the sign-in page mid-boot; that is not a failure.
            Failure::disarmRecovery();
            throw $signal;
        }
        // A throw leaves recovery armed on purpose: the failure that ends the
        // boot is the one the engine's catch records against its extension.
        Failure::disarmRecovery();
    }

    private static function boot(Runtime $runtime): void
    {
        $content = $runtime->contentDir();
        $hooks = Runtime::hooks();
        foreach (glob($content . '/mu-plugins/*.php') ?: [] as $file) {
            self::includeFile($file, basename($file), $runtime);
            $hooks->action('mu_plugin_loaded', [$file]);
        }
        $hooks->action('muplugins_loaded', []);
        $active = $runtime->options()->get('active_plugins');
        // An extension that killed a request is paused until someone lets
        // it back in; loading it again would only kill this one too.
        $paused = (new Recovery(new Site($runtime->db), $content))->pausedPlugins();
        foreach (is_array($active) ? $active : [] as $plugin) {
            $plugin = (string) $plugin;
            if (!preg_match('#^[A-Za-z0-9_.-]+(/[A-Za-z0-9_.-]+)?\.php$#', $plugin)) {
                continue;
            }
            if (isset($paused[$plugin])) {
                self::$skipped[$plugin] = ['functions' => [], 'classes' => [], 'files' => 0, 'truncated' => false, 'error' => 'paused after a fatal error; resume it with wp minn recovery resume'];
                continue;
            }
            $file = $content . '/plugins/' . $plugin;
            if (!is_file($file)) {
                continue;
            }
            self::includeFile($file, $plugin, $runtime);
            // Each file is announced as it lands (Jetpack schedules its whole configuration from this one).
            $hooks->action('plugin_loaded', [$file]);
            if ($plugin === self::MINN_ADMIN && self::isLoaded($plugin)) {
                foreach (self::MINN_ADMIN_HOOKS as [$hook, $callback, $priority]) {
                    $hooks->remove($hook, $callback, $priority);
                }
            }
        }
        $hooks->action('plugins_loaded', []);
        $hooks->action('sanitize_comment_cookies', []);
        $hooks->action('setup_theme', []);
        // The core domain loads here, before the theme, as the reference loads it.
        \load_default_textdomain();
        self::loadThemeFunctions($runtime);
        $hooks->action('after_setup_theme', []);
        $hooks->action('init', []);
        $hooks->action('wp_loaded', []);
    }

    /**
     * The active theme's functions.php, child first then parent, between
     * setup_theme and after_setup_theme as the reference loads them. Each
     * file goes through the same symbol gate as a plugin folder and is
     * reported under "theme:{slug}" when it cannot load. Templates never
     * run as PHP; a block theme's functions.php only registers hooks.
     */
    private static function loadThemeFunctions(Runtime $runtime): void
    {
        $options = $runtime->options();
        $stylesheet = (string) ($options->get('stylesheet') ?? '');
        $template = (string) ($options->get('template') ?? $stylesheet);
        $themes = $runtime->contentDir() . '/themes';
        $slugs = $stylesheet === $template ? [$stylesheet] : [$stylesheet, $template];
        foreach ($slugs as $slug) {
            if (!preg_match('/^[A-Za-z0-9._-]+$/', $slug)) {
                continue;
            }
            self::rememberThemeDomain("{$themes}/{$slug}", $slug);
            if (is_file("{$themes}/{$slug}/functions.php")) {
                self::includeFile("{$themes}/{$slug}/functions.php", "theme:{$slug}", $runtime);
            }
        }
    }

    /**
     * An active theme's own text domain, named in its stylesheet header
     * (the slug when it names none), reads its folder ("Domain Path", or
     * "/languages") with files named by locale alone.
     */
    private static function rememberThemeDomain(string $folder, string $slug): void
    {
        $headers = FileHeaders::values("{$folder}/style.css", ['Text Domain', 'Domain Path']);
        $domain = $headers['Text Domain'] !== '' ? $headers['Text Domain'] : $slug;
        $path = $headers['Domain Path'] !== '' ? '/' . trim($headers['Domain Path'], '/') : '/languages';
        Runtime::textDomains()->rememberThemeFolder($domain, $folder . $path);
    }

    /**
     * The plugins that loaded.
     *
     * @return list<string>
     */
    public static function loaded(): array
    {
        return self::$loaded;
    }

    /**
     * The plugins the symbol gate refused, with what they lacked.
     *
     * @return array<string, array<string, mixed>> plugin file => why it did not load
     */
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
        $dir = str_contains($name, '/') || str_starts_with($name, 'theme:') ? dirname($file) : $file;
        $missing = Symbols::missing($dir, Runtime::options());
        if ($missing['functions'] !== [] || $missing['classes'] !== [] || $missing['truncated']) {
            self::$skipped[$name] = $missing;
            return;
        }
        // A redeclaration somewhere in the folder may sit behind a conditional
        // include the static read cannot follow (a polyfill); only the main
        // file is certain to be compiled, so only its collisions refuse the load.
        if ($missing['redeclares'] !== [] && ($collisions = Symbols::redeclaresIn($file)) !== []) {
            self::$skipped[$name] = $missing + ['error' => 'declares ' . implode(', ', $collisions) . ', which the runtime already defines (a pluggable override the engine cannot host yet)'];
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
