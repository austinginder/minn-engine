<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Http\Method;
use Minn\Http\Request;

/**
 * How much of the WordPress runtime a request gets, as the site declares it
 * in `wp-config.php`:
 *
 *     define('MINN_WP_API', 'on');    // the default: every request, as WordPress boots
 *     define('MINN_WP_API', 'auto');  // the site's plugins run everywhere but public pages
 *     define('MINN_WP_API', 'off');   // the site's plugins never run
 *
 * A public page is a GET or HEAD that is not REST, the admin (`/wp-admin/`,
 * `/minn-admin/`) or one of WordPress's entry scripts (`wp-login.php`,
 * `wp-cron.php`, `wp-comments-post.php`, `xmlrpc.php` and the rest). Under
 * `auto` and `off` such a page loads no plugin or must-use plugin; an
 * extension route answers it without the runtime at all, and a themed page
 * still boots the facade the renderer draws through, with the theme's
 * functions.php (the theme is drawn as it is, its stylesheet among it). Minn
 * Admin's own plugin file is the admin's server half, so it loads on the
 * admin and REST in every mode. The drop-ins (`advanced-cache.php`,
 * `object-cache.php`) are the host's and load wherever the facade does.
 * WP-CLI is not a web request and always loads the plugins.
 */
enum WpApiMode: string
{
    case On = 'on';
    case Auto = 'auto';
    case Off = 'off';

    /** WordPress's entry scripts at the webroot: each is a surface of its own, never a public page. */
    private const SCRIPT = '#^/(wp-[a-z-]+|xmlrpc)\.php$#';

    /** What wp-config.php declared: on when nothing was, or something the engine does not know. */
    public static function configured(): self
    {
        if (!defined('MINN_WP_API')) {
            return self::On;
        }
        $value = constant('MINN_WP_API');
        if (is_bool($value)) {
            return $value ? self::On : self::Off;
        }
        $mode = self::tryFrom(strtolower(trim(is_scalar($value) ? (string) $value : '')));
        if ($mode === null) {
            error_log("Minn Engine: MINN_WP_API is 'on', 'auto' or 'off'; anything else boots as 'on'.");
        }
        return $mode ?? self::On;
    }

    /** Whether the site's plugins and must-use plugins load for this request. */
    public function loadsPlugins(Request $request): bool
    {
        return match ($this) {
            self::On => true,
            self::Auto => !self::publicPage($request),
            self::Off => false,
        };
    }

    /** Whether Minn Admin's plugin file loads for this request (when it is active). */
    public function loadsMinnAdmin(Request $request): bool
    {
        return $this === self::On || !self::publicPage($request);
    }

    /** Whether an extension route may answer this request before the runtime boots. */
    public function sparesRuntime(Request $request): bool
    {
        return $this !== self::On && self::publicPage($request);
    }

    /** A GET or HEAD for the site itself: not the admin, REST or an entry script. */
    public static function publicPage(Request $request): bool
    {
        if (!in_array($request->method, [Method::Get, Method::Head], true)) {
            return false;
        }
        $path = $request->path;
        if (str_starts_with($path, '/wp-json') || array_key_exists('rest_route', $request->query)) {
            return false;
        }
        foreach (['/wp-admin', '/minn-admin'] as $surface) {
            if ($path === $surface || str_starts_with($path, $surface . '/')) {
                return false;
            }
        }
        return preg_match(self::SCRIPT, $path) !== 1;
    }
}
