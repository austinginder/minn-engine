<?php
/**
 * Environment predicates and the version global. Constants come from
 * Minn\Runtime\Constants at boot.
 */

use Minn\Runtime\Runtime;

function is_admin()
{
    return Runtime::current()->isAdmin;
}

function is_network_admin()
{
    return false;
}

function is_user_admin()
{
    return false;
}

function is_blog_admin()
{
    // The reference answers from the admin screen being drawn; Minn draws none, and admin-ajax.php has none either.
    return false;
}

function is_multisite()
{
    return false;
}

function is_main_site($site_id = null, $network_id = null)
{
    return true;
}

function is_main_network($network_id = null)
{
    return true;
}

function get_current_blog_id()
{
    return 1;
}

function get_current_network_id()
{
    return 1;
}

function wp_installing($is_installing = null)
{
    $runtime = Runtime::current();
    $current = (bool) $runtime->get('installing', false);
    if ($is_installing !== null) {
        $runtime->set('installing', (bool) $is_installing);
    }
    return $current;
}

function wp_doing_ajax()
{
    return apply_filters('wp_doing_ajax', defined('DOING_AJAX') && DOING_AJAX);
}

function wp_doing_cron()
{
    return apply_filters('wp_doing_cron', defined('DOING_CRON') && DOING_CRON);
}

function wp_is_json_request()
{
    $accept = (string) (Runtime::current()->request?->header('accept') ?? '');
    $type = (string) (Runtime::current()->request?->header('content-type') ?? '');
    return str_contains($accept, 'application/json') || str_contains($type, 'application/json');
}

function wp_is_xml_request()
{
    $accept = (string) (Runtime::current()->request?->header('accept') ?? '');
    foreach (['text/xml', 'application/rss+xml', 'application/atom+xml', 'application/rdf+xml', 'application/rss+xml', 'application/xml'] as $type) {
        if (str_contains($accept, $type)) {
            return true;
        }
    }
    return false;
}

function is_ssl()
{
    return Runtime::current()->isSecure();
}

function is_rtl()
{
    return false;
}

function is_customize_preview()
{
    return false;
}

function wp_is_block_theme()
{
    return Runtime::current()->get('block_theme', true);
}

function wp_get_environment_type()
{
    $type = defined('WP_ENVIRONMENT_TYPE') ? WP_ENVIRONMENT_TYPE : (getenv('WP_ENVIRONMENT_TYPE') ?: 'production');
    return in_array($type, ['local', 'development', 'staging', 'production'], true) ? $type : 'production';
}

function wp_get_development_mode()
{
    $mode = defined('WP_DEVELOPMENT_MODE') ? WP_DEVELOPMENT_MODE : '';
    return in_array($mode, ['core', 'plugin', 'theme', 'all'], true) ? $mode : '';
}

function wp_is_development_mode($mode)
{
    $current = wp_get_development_mode();
    return $current !== '' && ($current === 'all' || $current === $mode);
}

function is_wp_version_compatible($required)
{
    return empty($required) || version_compare($GLOBALS['wp_version'], $required, '>=');
}

function is_php_version_compatible($required)
{
    return empty($required) || version_compare(PHP_VERSION, $required, '>=');
}

function absint($maybeint)
{
    return abs((int) $maybeint);
}

function wp_using_ext_object_cache($using = null)
{
    global $_wp_using_ext_object_cache;
    $current = $_wp_using_ext_object_cache;
    if ($using !== null) {
        $_wp_using_ext_object_cache = $using;
    }
    return $current;
}

function wp_debug_mode()
{
}

function wp_get_server_protocol()
{
    return 'HTTP/1.1';
}

function timer_start()
{
    return true;
}

function timer_stop($display = 0, $precision = 3)
{
    return number_format(microtime(true) - (defined('WP_START_TIMESTAMP') ? WP_START_TIMESTAMP : microtime(true)), $precision);
}

function wp_convert_hr_to_bytes($value)
{
    $value = strtolower(trim((string) $value));
    $bytes = (int) $value;
    if (str_contains($value, 'g')) {
        $bytes *= GB_IN_BYTES;
    } elseif (str_contains($value, 'm')) {
        $bytes *= MB_IN_BYTES;
    } elseif (str_contains($value, 'k')) {
        $bytes *= KB_IN_BYTES;
    }
    return min($bytes, PHP_INT_MAX);
}

function wp_is_ini_value_changeable($setting)
{
    return true;
}

function wp_raise_memory_limit($context = 'admin')
{
    return false;
}

/** A JSONP request names its callback in `_jsonp`; the value is not checked here. */
function wp_is_jsonp_request()
{
    return isset($_GET['_jsonp']);
}

function wp_clone($input_object)
{
    return clone $input_object;
}

/** The engine keeps no recovery-mode session: its own recovery pauses the failing plugin instead. */
function wp_is_recovery_mode()
{
    return false;
}

/**
 * @internal the database object: a db.php drop-in may set $wpdb itself (Query
 * Monitor's subclass, a replication class); otherwise the facade's wpdb.
 */
function _minn_require_wp_db(string $contentDir): void
{
    global $wpdb;
    if (is_file($contentDir . '/db.php')) {
        require_once $contentDir . '/db.php';
    }
    if (!isset($wpdb)) {
        $wpdb = new wpdb(defined('DB_USER') ? DB_USER : '', defined('DB_PASSWORD') ? DB_PASSWORD : '', defined('DB_NAME') ? DB_NAME : '', defined('DB_HOST') ? DB_HOST : '');
    }
}

/** @internal starts the object cache (a drop-in's or the engine's) with the groups the reference declares global and runtime-only */
function _minn_start_object_cache(): void
{
    wp_cache_init();
    wp_cache_add_global_groups(['blog-details', 'blog-id-cache', 'blog-lookup', 'blog_meta', 'global-posts', 'image_editor', 'network-queries', 'networks', 'rss', 'site-details', 'site-options', 'site-queries', 'site-transient', 'sites', 'theme_files', 'translation_files', 'user-queries', 'user_meta', 'useremail', 'userlogins', 'users', 'userslugs']);
    wp_cache_add_non_persistent_groups(['counts', 'plugins', 'theme_json']);
    // The reference declares theme lookups request-only as soon as it builds a theme, which every boot does.
    wp_cache_add_non_persistent_groups('themes');
}

/**
 * @internal The script the reference's web server would have run for a
 * request, where plugins look for it: $pagenow, and for admin-ajax.php the
 * server's script variables, which the engine's own index.php would
 * otherwise fill (WooCommerce exempts the endpoint from its admin guard by
 * SCRIPT_FILENAME). wp-login.php and wp-cron.php are left unnamed for now:
 * hide-login plugins and the CaptainCore helper take over a sign-in when
 * $pagenow says wp-login.php, which wants its own round trip first. A
 * plugin that sets $pagenow first keeps its value.
 */
function _minn_script_globals(string $path): void
{
    if ($path === '/wp-login.php' || $path === '/wp-cron.php') {
        return;
    }
    if ($path !== '/wp-admin/admin-ajax.php') {
        $GLOBALS['pagenow'] ??= 'index.php';
        return;
    }
    $GLOBALS['pagenow'] ??= 'admin-ajax.php';
    $_SERVER['SCRIPT_FILENAME'] = ABSPATH . 'wp-admin/admin-ajax.php';
    $_SERVER['SCRIPT_NAME'] = '/wp-admin/admin-ajax.php';
    $_SERVER['PHP_SELF'] = '/wp-admin/admin-ajax.php';
}
