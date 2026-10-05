<?php

declare(strict_types=1);

namespace Minn\Runtime;

/**
 * The constants plugin code expects: the fixed set from data/constants.json
 * (captured from the reference) and the per-site ones computed here. Nothing
 * already defined is touched, so wp-config.php keeps the last word.
 */
final class Constants
{
    /** Defines the constants the reference defines at boot. */
    public static function define(Runtime $runtime): void
    {
        $abs = rtrim($runtime->absPath, '/') . '/';
        $siteUrl = rtrim((string) ($runtime->site->option('siteurl') ?? ''), '/');
        $fixed = json_decode((string) file_get_contents($runtime->engineDir . '/data/constants.json'), true) ?: [];
        $computed = [
            'WP_CONTENT_DIR' => $abs . 'wp-content',
            'WP_CONTENT_URL' => $siteUrl . '/wp-content',
            'WP_PLUGIN_DIR' => $abs . 'wp-content/plugins',
            'WP_PLUGIN_URL' => $siteUrl . '/wp-content/plugins',
            'WPMU_PLUGIN_DIR' => $abs . 'wp-content/mu-plugins',
            'WPMU_PLUGIN_URL' => $siteUrl . '/wp-content/mu-plugins',
            'WP_LANG_DIR' => $abs . 'wp-content/languages',
            'FORCE_SSL_ADMIN' => str_starts_with($siteUrl, 'https://'),
            'WP_START_TIMESTAMP' => microtime(true),
            'WP_DEBUG' => false,
            'WP_DEBUG_LOG' => false,
            'WP_DEBUG_DISPLAY' => true,
            'WP_CACHE' => false,
            'SCRIPT_DEBUG' => false,
            'SHORTINIT' => false,
            'WP_DEVELOPMENT_MODE' => '',
            'WP_DEFAULT_THEME' => 'twentytwentyfive',
            'WP_MEMORY_LIMIT' => '40M',
            'WP_MAX_MEMORY_LIMIT' => '256M',
            'COOKIEHASH' => md5($siteUrl),
            'COOKIEPATH' => rtrim((string) (parse_url((string) ($runtime->site->option('home') ?? ''), PHP_URL_PATH) ?: ''), '/') . '/',
            'SITECOOKIEPATH' => rtrim((string) (parse_url($siteUrl, PHP_URL_PATH) ?: ''), '/') . '/',
            'COOKIE_DOMAIN' => false,
            'WP_ADMIN' => $runtime->isAdmin,
            'MINN_RUNTIME' => true,
        ];
        // The reference's wp-cron.php declares itself before anything loads, and a plugin reads it at plugins_loaded.
        if ($runtime->request?->path === '/wp-cron.php') {
            $computed['DOING_CRON'] = true;
        }
        foreach ($fixed + $computed as $name => $value) {
            if (!defined($name)) {
                define($name, $value);
            }
        }
        foreach (['ADMIN_COOKIEPATH' => SITECOOKIEPATH . 'wp-admin', 'PLUGINS_COOKIE_PATH' => rtrim((string) (parse_url(WP_PLUGIN_URL, PHP_URL_PATH) ?: ''), '/'), 'USER_COOKIE' => 'wordpressuser_' . COOKIEHASH, 'PASS_COOKIE' => 'wordpresspass_' . COOKIEHASH, 'AUTH_COOKIE' => 'wordpress_' . COOKIEHASH, 'SECURE_AUTH_COOKIE' => 'wordpress_sec_' . COOKIEHASH, 'LOGGED_IN_COOKIE' => 'wordpress_logged_in_' . COOKIEHASH, 'TEST_COOKIE' => 'wordpress_test_cookie', 'RECOVERY_MODE_COOKIE' => 'wordpress_rec_' . COOKIEHASH] as $name => $value) {
            if (!defined($name)) {
                define($name, $value);
            }
        }
        if (!defined('object')) {
            define('object', 'OBJECT');
        }
        $GLOBALS['wp_version'] = $runtime->version;
        // Whether an object-cache drop-in took over; it exists, unset, from the start (WP-CLI reads it).
        if (!array_key_exists('_wp_using_ext_object_cache', $GLOBALS)) {
            $GLOBALS['_wp_using_ext_object_cache'] = null;
        }
        $GLOBALS['table_prefix'] = $runtime->db->prefix();
    }
}
