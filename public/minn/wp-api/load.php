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

/** Whether the site's locale writes right to left. */
function is_rtl()
{
    $locale = $GLOBALS['wp_locale'] ?? null;
    return $locale instanceof WP_Locale && $locale->is_rtl();
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

/** Starts the page timer timer_stop reads ($timestart). */
function timer_start()
{
    $GLOBALS['timestart'] = microtime(true);
    return true;
}

/** Seconds since timer_start (or since the request began), formatted, and echoed when asked. */
function timer_stop($display = 0, $precision = 3)
{
    $start = $GLOBALS['timestart'] ?? (defined('WP_START_TIMESTAMP') ? WP_START_TIMESTAMP : microtime(true));
    $total = function_exists('number_format_i18n') ? number_format_i18n(microtime(true) - $start, $precision) : number_format(microtime(true) - $start, $precision);
    if ($display) {
        echo $total;
    }
    return $total;
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

/**
 * Raises PHP's memory limit for a heavy task, never lowering it: to what
 * {$context}_memory_limit asks when that is unlimited or above both the
 * most WordPress allows and the current limit, else to the most WordPress
 * allows when that is higher. The new limit, or false when it stayed.
 */
function wp_raise_memory_limit($context = 'admin')
{
    $current = wp_convert_hr_to_bytes(ini_get('memory_limit'));
    if (!wp_is_ini_value_changeable('memory_limit') || $current === -1) {
        return false;
    }
    $max = WP_MAX_MEMORY_LIMIT;
    $wanted = apply_filters("{$context}_memory_limit", $max);
    $wantedBytes = wp_convert_hr_to_bytes($wanted);
    $maxBytes = wp_convert_hr_to_bytes($max);
    $limit = match (true) {
        $wantedBytes === -1 || ($wantedBytes > $maxBytes && $wantedBytes > $current) => $wanted,
        $maxBytes === -1 || $maxBytes > $current => $max,
        default => false,
    };
    return $limit !== false && ini_set('memory_limit', (string) $limit) !== false ? $limit : false;
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

/**
 * The server variables some hosts leave out or get wrong, set as the
 * reference sets them: an empty software name and request URI first, the
 * request URI built from IIS's headers or the script and path info, a
 * php.cgi script file and path info corrected, PHP_SELF from the request
 * URI, and Basic credentials from the Authorization header.
 */
function wp_fix_server_vars()
{
    $_SERVER = array_merge(['SERVER_SOFTWARE' => '', 'REQUEST_URI' => ''], $_SERVER);
    if (empty($_SERVER['REQUEST_URI']) || (PHP_SAPI !== 'cgi-fcgi' && preg_match('/^Microsoft-IIS\//', $_SERVER['SERVER_SOFTWARE']))) {
        $_SERVER['REQUEST_URI'] = $_SERVER['HTTP_X_ORIGINAL_URL'] ?? $_SERVER['HTTP_X_REWRITE_URL'] ?? _minn_request_uri_from_parts();
    }
    if (isset($_SERVER['SCRIPT_FILENAME']) && str_ends_with($_SERVER['SCRIPT_FILENAME'], 'php.cgi')) {
        $_SERVER['SCRIPT_FILENAME'] = $_SERVER['PATH_TRANSLATED'] ?? $_SERVER['SCRIPT_FILENAME'];
    }
    if (isset($_SERVER['SCRIPT_NAME']) && str_contains($_SERVER['SCRIPT_NAME'], 'php.cgi')) {
        unset($_SERVER['PATH_INFO']);
    }
    if (empty($_SERVER['PHP_SELF'])) {
        $_SERVER['PHP_SELF'] = preg_replace('/(\?.*)?$/', '', $_SERVER['REQUEST_URI']);
    }
    $GLOBALS['PHP_SELF'] = $_SERVER['PHP_SELF'];
    wp_populate_basic_auth_from_authorization_header();
}

/** @internal a request URI from the script name, the path info (or its original) and the query string */
function _minn_request_uri_from_parts(): string
{
    if (!isset($_SERVER['PATH_INFO']) && isset($_SERVER['ORIG_PATH_INFO'])) {
        $_SERVER['PATH_INFO'] = $_SERVER['ORIG_PATH_INFO'];
    }
    $uri = '';
    if (isset($_SERVER['PATH_INFO'])) {
        $uri = $_SERVER['PATH_INFO'] === ($_SERVER['SCRIPT_NAME'] ?? null) ? $_SERVER['PATH_INFO'] : ($_SERVER['SCRIPT_NAME'] ?? '') . $_SERVER['PATH_INFO'];
    }
    return $uri . (empty($_SERVER['QUERY_STRING']) ? '' : '?' . $_SERVER['QUERY_STRING']);
}

/** Basic credentials from an Authorization header into PHP_AUTH_USER and PHP_AUTH_PW, when PHP did not set them. */
function wp_populate_basic_auth_from_authorization_header()
{
    if (isset($_SERVER['PHP_AUTH_USER']) || isset($_SERVER['PHP_AUTH_PW'])) {
        return;
    }
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (!is_string($header) || !str_starts_with(strtolower($header), 'basic ')) {
        return;
    }
    $decoded = base64_decode(substr($header, 6), true);
    if (is_string($decoded) && str_contains($decoded, ':')) {
        [$_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']] = explode(':', $decoded, 2);
    }
}
