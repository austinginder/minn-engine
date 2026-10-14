<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Http\Request;
use Minn\Support\Escape;
use Minn\Support\Url;

/**
 * The site's own addresses as WordPress builds them: the home and site
 * addresses, the admin's, wp-includes', the sign-in and sign-out pages' (each with its
 * scheme settled and its filter run), whether the request came over HTTPS,
 * the switch that forces the admin onto it, and the trailing slash the
 * permalink structure asks for.
 */
final class SiteUrls
{
    private static ?bool $adminForced = null;

    /** Whether this request came over HTTPS (is_ssl): the engine's request when there is one, else the server variables. */
    public static function secure(): bool
    {
        if (Runtime::current()->request !== null) {
            return Runtime::current()->isSecure();
        }
        $https = strtolower((string) (Request::serverVariable('HTTPS') ?? ''));
        return $https === 'on' || $https === '1' || (string) (Request::serverVariable('SERVER_PORT') ?? '') === '443';
    }

    /** Whether the admin is forced onto HTTPS (force_ssl_admin, FORCE_SSL_ADMIN); given a value, it sets the switch and answers what it was. */
    public static function forceSslAdmin(mixed $force = null): bool
    {
        self::$adminForced ??= defined('FORCE_SSL_ADMIN') && FORCE_SSL_ADMIN;
        if ($force === null) {
            return self::$adminForced;
        }
        [$previous, self::$adminForced] = [self::$adminForced, (bool) $force];
        return $previous;
    }

    /**
     * An address in a scheme (set_url_scheme): http, https or relative when
     * asked for; https for the admin, sign-in and RPC ones over HTTPS or with
     * the admin forced; otherwise the request's. Then set_url_scheme.
     */
    public static function withScheme(mixed $url, mixed $scheme = null): string
    {
        $chosen = match ($scheme) {
            'http', 'https', 'relative' => $scheme,
            'admin', 'login', 'login_post', 'rpc' => self::secure() || self::forceSslAdmin() ? 'https' : 'http',
            default => self::secure() ? 'https' : 'http',
        };
        return (string) Runtime::hooks()->filter('set_url_scheme', [Url::withScheme((string) $url, $chosen), $chosen, $url]);
    }

    /** The home address with a path (get_home_url, home_url): in the request's scheme unless http, https or relative is asked for. */
    public static function home(mixed $path = '', mixed $scheme = null, mixed $blogId = null): string
    {
        $home = (string) Runtime::options()->filtered('home');
        $settled = in_array($scheme, ['http', 'https', 'relative'], true) ? $scheme : (self::secure() ? 'https' : (parse_url($home, PHP_URL_SCHEME) ?: 'http'));
        return (string) Runtime::hooks()->filter('home_url', [self::joined(self::withScheme($home, $settled), $path), $path, $scheme, $blogId]);
    }

    /** The site (WordPress) address with a path (get_site_url, site_url). */
    public static function site(mixed $path = '', mixed $scheme = null, mixed $blogId = null): string
    {
        $site = self::withScheme((string) Runtime::options()->filtered('siteurl'), $scheme);
        return (string) Runtime::hooks()->filter('site_url', [self::joined($site, $path), $path, $scheme, $blogId]);
    }

    /** An address in the admin (get_admin_url, admin_url). */
    public static function admin(mixed $path = '', mixed $scheme = 'admin', mixed $blogId = null): string
    {
        return (string) Runtime::hooks()->filter('admin_url', [self::site('wp-admin/', $scheme, $blogId) . self::tail($path), $path, $blogId, $scheme]);
    }

    /** An address in the current admin (self_admin_url): the site's admin on a single site. */
    public static function selfAdmin(mixed $path = '', mixed $scheme = 'admin'): string
    {
        return (string) Runtime::hooks()->filter('self_admin_url', [self::admin($path, $scheme), $path, $scheme]);
    }

    /** An address in wp-content (content_url). */
    public static function content(mixed $path = ''): mixed
    {
        return Runtime::hooks()->filter('content_url', [self::joined(self::withScheme(WP_CONTENT_URL), $path), $path]);
    }

    /** The Customizer's address, a theme to preview named (wp_customize_url), escaped. */
    public static function customize(mixed $stylesheet = ''): string
    {
        $url = self::admin('customize.php');
        if ($stylesheet !== '') {
            $url .= '?theme=' . urlencode((string) $stylesheet);
        }
        return Escape::url($url);
    }

    /** An address in wp-includes (includes_url). */
    public static function includes(mixed $path = '', mixed $scheme = null): string
    {
        return (string) Runtime::hooks()->filter('includes_url', [self::site('/' . WPINC . '/', $scheme) . self::tail($path), $path, $scheme]);
    }

    /** The sign-in page (wp_login_url), told where to go after and whether to ask again, as login_url leaves it. */
    public static function login(mixed $redirect = '', mixed $forceReauth = false): string
    {
        $url = self::site('wp-login.php', 'login');
        if (!empty($redirect)) {
            $url = QueryArgs::add('redirect_to', urlencode((string) $redirect), $url);
        }
        if ($forceReauth) {
            $url = QueryArgs::add('reauth', '1', $url);
        }
        return (string) Runtime::hooks()->filter('login_url', [$url, $redirect, $forceReauth]);
    }

    /** The sign-out address (wp_logout_url), its nonce after the action and where to go after, as logout_url leaves it. */
    public static function logout(mixed $redirect = ''): string
    {
        $args = ['action' => 'logout'];
        if (!empty($redirect)) {
            $args['redirect_to'] = urlencode((string) $redirect);
        }
        $url = QueryArgs::withNonce(QueryArgs::add($args, self::site('wp-login.php', 'login')), 'log-out');
        return (string) Runtime::hooks()->filter('logout_url', [$url, $redirect]);
    }

    /**
     * An address ending as the permalink structure asks (user_trailingslashit):
     * with a slash when the rewrite object (or the structure) uses them,
     * without one otherwise; then user_trailingslashit.
     */
    public static function trailing(mixed $url, mixed $type = ''): string
    {
        $rewrite = $GLOBALS['wp_rewrite'] ?? null;
        $slashes = is_object($rewrite) && isset($rewrite->use_trailing_slashes)
            ? (bool) $rewrite->use_trailing_slashes
            : str_ends_with((string) Runtime::options()->filtered('permalink_structure'), '/');
        $shaped = $slashes ? Url::withTrailingSlash((string) $url) : Url::withoutTrailingSlash((string) $url);
        return (string) Runtime::hooks()->filter('user_trailingslashit', [$shaped, $type]);
    }

    /** A base address with a path after a slash, unless the path is empty, not text, or climbs (".."). */
    private static function joined(string $base, mixed $path): string
    {
        return $path !== '' && is_string($path) && !str_contains($path, '..') ? $base . '/' . ltrim($path, '/') : $base;
    }

    /** A path to append to an address that already ends in its folder's slash. */
    private static function tail(mixed $path): string
    {
        return $path !== '' && is_string($path) ? ltrim($path, '/') : '';
    }
}
