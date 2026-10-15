<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Http\Request;

/**
 * The request as WordPress's earliest functions see it, before the runtime
 * is up: a page cache's advanced-cache.php runs then, as the reference runs
 * it, ahead of the database, the options and the plugins. What the
 * reference knows at that point comes from the request and from the
 * constants its entry files define first (WP_ADMIN for an admin page,
 * DOING_AJAX for admin-ajax.php, DOING_CRON for wp-cron.php), which the
 * engine defines from the same rules it boots by (declare()).
 */
final class EarlyRequest
{
    /** Defines the request's kind as the reference's entry file would have before anything loaded. */
    public static function declare(Request $request): void
    {
        self::markThemed($request);
        $constants = [
            'WP_ADMIN' => AjaxController::claims($request) || AdminActionController::claims($request),
            'DOING_AJAX' => AjaxController::doing($request),
            'DOING_CRON' => $request->path === '/wp-cron.php',
        ];
        foreach ($constants as $name => $value) {
            if ($value && !defined($name)) {
                define($name, true);
            }
        }
    }

    /**
     * WordPress's index.php says its requests use themes before any plugin
     * loads; its own scripts (sign-in, the admin, cron, XML-RPC) do not, and
     * neither does Minn Admin (probe load-remaining).
     */
    public static function markThemed(Request $request): void
    {
        if (\defined('WP_USE_THEMES') || preg_match('#^/(?:wp-admin(?:/|$)|minn-admin(?:/|$)|wp-(?:login|cron|signup|activate|trackback|comments-post|mail|links-opml)\.php|xmlrpc\.php)#', $request->path) === 1) {
            return;
        }
        \define('WP_USE_THEMES', true);
    }

    /** Whether this is an admin request (is_admin): what the entry file said. */
    public static function isAdmin(): bool
    {
        return defined('WP_ADMIN') && (bool) constant('WP_ADMIN');
    }

    /** Whether the request came over HTTPS (is_ssl), as the server reports it. */
    public static function secure(): bool
    {
        $https = strtolower((string) (Request::serverVariable('HTTPS') ?? ''));
        return $https === 'on' || $https === '1' || (string) (Request::serverVariable('SERVER_PORT') ?? '') === '443';
    }

    /** Whether WordPress is installing (wp_installing): what WP_INSTALLING says. */
    public static function installing(): bool
    {
        return defined('WP_INSTALLING') && (bool) constant('WP_INSTALLING');
    }
}
