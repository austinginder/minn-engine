<?php
/**
 * Environment predicates and the version global. Constants come from
 * Minn\Runtime\Constants at boot.
 */

use Minn\Http\MediaType;
use Minn\Runtime\{DebugMode, Runtime, SiteUrls};

function is_admin()
{
    return Runtime::current()->isAdmin;
}

function is_network_admin()
{
    return _minn_in_admin('network', 'WP_NETWORK_ADMIN');
}

function is_user_admin()
{
    return _minn_in_admin('user', 'WP_USER_ADMIN');
}

/** Whether the screen being drawn is in the site admin, or WP_BLOG_ADMIN says so (probe load-remaining); Minn draws no admin screen of its own. */
function is_blog_admin()
{
    return _minn_in_admin('site', 'WP_BLOG_ADMIN');
}

/** @internal The current screen's answer for an admin, else the constant's, else no. */
function _minn_in_admin(string $admin, string $constant): bool
{
    if (isset($GLOBALS['current_screen']) && is_object($GLOBALS['current_screen']) && method_exists($GLOBALS['current_screen'], 'in_admin')) {
        return (bool) $GLOBALS['current_screen']->in_admin($admin);
    }
    return defined($constant) && (bool) constant($constant);
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

/** Whether the request asks for or sends JSON, by its Accept and Content-Type headers in $_SERVER (probe load-remaining). */
function wp_is_json_request()
{
    if (isset($_SERVER['HTTP_ACCEPT']) && wp_is_json_media_type((string) $_SERVER['HTTP_ACCEPT'])) {
        return true;
    }
    return isset($_SERVER['CONTENT_TYPE']) && wp_is_json_media_type((string) $_SERVER['CONTENT_TYPE']);
}

/** Whether the request asks for or sends one of the feed and oEmbed XML types, as written, in $_SERVER (probe load-remaining). */
function wp_is_xml_request()
{
    $types = ['text/xml', 'application/rss+xml', 'application/atom+xml', 'application/rdf+xml', 'text/xml+oembed', 'application/xml+oembed'];
    foreach (['HTTP_ACCEPT', 'CONTENT_TYPE'] as $header) {
        foreach ($types as $type) {
            if (isset($_SERVER[$header]) && str_contains((string) $_SERVER[$header], $type)) {
                return true;
            }
        }
    }
    return false;
}

/** Whether the request came over HTTPS; with no request (the command line), what $_SERVER says, as WP-CLI sets it for an https site. */
function is_ssl()
{
    return SiteUrls::secure();
}

/** Whether the site's locale writes right to left. */
function is_rtl()
{
    return Minn\I18n\SiteLanguage::isRtl();
}

function is_customize_preview()
{
    return false;
}

/** Whether the current theme is a block theme: the booted one's answer, or, for a stylesheet a filter swapped in, that theme's own. */
function wp_is_block_theme()
{
    $runtime = Runtime::current();
    $theme = $runtime->get('theme');
    $stylesheet = get_stylesheet();
    if ($runtime->get('block_theme') === null || $theme instanceof Minn\Theme\Theme && $stylesheet === $theme->slug) {
        return true;
    }
    $known = (array) $runtime->get('block_theme_by_stylesheet', []);
    $known[$stylesheet] ??= wp_get_theme($stylesheet)->is_block_theme();
    $runtime->set('block_theme_by_stylesheet', $known);
    return $known[$stylesheet];
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
    return Minn\Runtime\VersionCheck::wordpress($required);
}

function is_php_version_compatible($required)
{
    return Minn\Runtime\VersionCheck::php($required);
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

/**
 * PHP's error settings from the debug constants (Runtime\DebugMode), unless
 * enable_wp_debug_mode_checks says no; errors never display for XML-RPC,
 * REST, ms-files, an install, ajax, or a request that asks for or sends JSON
 * (probe debug-mode; the engine's boot does the same in Http\Failure).
 */
function wp_debug_mode()
{
    if (!apply_filters('enable_wp_debug_mode_checks', true)) {
        return;
    }
    DebugMode::apply(DebugMode::settings(DebugMode::defined(), WP_CONTENT_DIR));
    if (defined('XMLRPC_REQUEST') || defined('REST_REQUEST') || defined('MS_FILES_REQUEST') || wp_installing() || wp_doing_ajax() || wp_is_json_request()) {
        ini_set('display_errors', '0');
    }
}

/**
 * The translations an early screen needs, before the options are read. It
 * runs once per process: a first call after init only marks it done. Before
 * init it loads the default text domain from the WPLANG constant's file (the
 * languages folder first, then wp-includes/languages; nothing when neither
 * has it), then builds a fresh WP_Locale (probes translations-early*).
 */
function wp_load_translations_early()
{
    global $wp_locale;
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;
    if (did_action('init')) {
        return;
    }
    $locale = defined('WPLANG') ? (string) WPLANG : '';
    foreach ($locale === '' ? [] : [WP_LANG_DIR, ABSPATH . WPINC . '/languages'] as $folder) {
        if (is_file("{$folder}/{$locale}.mo")) {
            load_textdomain('default', "{$folder}/{$locale}.mo", $locale);
            break;
        }
    }
    $wp_locale = new WP_Locale();
}

/** The protocol the request came over: HTTP/1.1, 2, 2.0 or 3 as the server says, anything else HTTP/1.0 (probe functions-remaining). */
function wp_get_server_protocol()
{
    return Minn\Runtime\RequestHeaders::protocol();
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

/** Whether ini_set may change a setting here: it allows changes at run time; an unknown setting may not (probe load-remaining). */
function wp_is_ini_value_changeable($setting)
{
    static $all = null;
    $all ??= function_exists('ini_get_all') ? (ini_get_all() ?: []) : false;
    if ($all === false) {
        return true;
    }
    $access = $all[(string) $setting]['access'] ?? null;
    return $access !== null && (($access & INI_ALL) === INI_ALL || ($access & INI_ALL) === INI_USER);
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
    $wanted = match ((string) $context) {
        'admin' => apply_filters('admin_memory_limit', $max),
        'image' => apply_filters('image_memory_limit', $max),
        'cron' => apply_filters('cron_memory_limit', $max),
        default => apply_filters("{$context}_memory_limit", $max),
    };
    $wantedBytes = wp_convert_hr_to_bytes($wanted);
    $maxBytes = wp_convert_hr_to_bytes($max);
    $limit = match (true) {
        $wantedBytes === -1 || ($wantedBytes > $maxBytes && $wantedBytes > $current) => $wanted,
        $maxBytes === -1 || $maxBytes > $current => $max,
        default => false,
    };
    return $limit !== false && ini_set('memory_limit', (string) $limit) !== false ? $limit : false;
}

/** A JSONP request names a callback in `_jsonp` that passes wp_check_jsonp_callback (empty does), while rest_jsonp_enabled allows it. */
function wp_is_jsonp_request()
{
    return isset($_GET['_jsonp']) && wp_check_jsonp_callback($_GET['_jsonp']) && apply_filters('rest_jsonp_enabled', true);
}

/** Stops the request with a 500 when PHP is older than the engine needs (8.3) or the mysqli extension is missing; nothing otherwise. */
function wp_check_php_mysql_versions()
{
    if (version_compare(PHP_VERSION, '8.3', '<')) {
        $problem = sprintf('Your server is running PHP version %1$s but Minn requires at least %2$s.', PHP_VERSION, '8.3');
    } elseif (!extension_loaded('mysqli') && !file_exists(WP_CONTENT_DIR . '/db.php')) {
        $problem = 'Your PHP installation appears to be missing the MySQL extension which is required by Minn.';
    } else {
        return;
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    echo $problem;
    exit;
}

/** Sends a request for exactly /favicon.ico away with an empty icon; any other path passes. */
function wp_favicon_request()
{
    if (($_SERVER['REQUEST_URI'] ?? '') === '/favicon.ico') {
        if (!headers_sent()) {
            header('Content-Type: image/vnd.microsoft.icon');
        }
        exit;
    }
}

/**
 * While maintenance mode holds, the request stops: wp-content/maintenance.php
 * when the site has one, else a 503 "Briefly unavailable" through wp_die,
 * with Retry-After: 600.
 */
function wp_maintenance()
{
    if (!wp_is_maintenance_mode()) {
        return;
    }
    if (file_exists(WP_CONTENT_DIR . '/maintenance.php')) {
        require_once WP_CONTENT_DIR . '/maintenance.php';
        exit;
    }
    if (!headers_sent()) {
        header('Retry-After: 600');
    }
    wp_die(__('Briefly unavailable for scheduled maintenance. Check back in a minute.'), __('Maintenance'), 503);
}

/** An installed site (or one installing) passes; otherwise the request goes to the installer. */
function wp_not_installed()
{
    if (is_blog_installed() || wp_installing()) {
        return;
    }
    nocache_headers();
    wp_redirect(wp_guess_url() . '/wp-admin/install.php');
    exit;
}

/**
 * The file editor's scrape check, on a request carrying wp_scrape_key and
 * wp_scrape_nonce: nothing unless both are there, the key (sanitized) is
 * not empty, and a scrape_key_{key} transient exists. A nonce other than
 * the transient's prints scrape_nonce_failure between the result markers
 * and stops; the right one has the result printed when the request ends.
 */
function wp_start_scraping_edited_file_errors()
{
    if (!isset($_REQUEST['wp_scrape_key'], $_REQUEST['wp_scrape_nonce'])) {
        return;
    }
    $key = sanitize_key(wp_unslash($_REQUEST['wp_scrape_key']));
    $nonce = wp_unslash($_REQUEST['wp_scrape_nonce']);
    if ($key === '' || empty($nonce)) {
        return;
    }
    $expected = get_transient('scrape_key_' . $key);
    if ($expected === false) {
        return;
    }
    if ($expected !== $nonce) {
        echo "###### wp_scraping_result_start:{$key} ######";
        echo wp_json_encode(['code' => 'scrape_nonce_failure', 'message' => __('Scrape key check failed. Please try again.')]);
        echo "###### wp_scraping_result_end:{$key} ######";
        exit;
    }
    register_shutdown_function('wp_finalize_scraping_edited_file_errors', $key);
}

/** Prints the scrape's result between its markers (the key as given): the last error when it was fatal, else true. */
function wp_finalize_scraping_edited_file_errors($scrape_key)
{
    $error = error_get_last();
    $fatal = is_array($error) && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR], true);
    echo "\n###### wp_scraping_result_start:{$scrape_key} ######\n";
    echo wp_json_encode($fatal ? $error : true);
    echo "\n###### wp_scraping_result_end:{$scrape_key} ######\n";
}

/** Whether the fatal error handler is on: unless WP_DISABLE_FATAL_ERROR_HANDLER says otherwise, as wp_fatal_error_handler_enabled answers (as given). */
function wp_is_fatal_error_handler_enabled()
{
    $enabled = !defined('WP_DISABLE_FATAL_ERROR_HANDLER') || !WP_DISABLE_FATAL_ERROR_HANDLER;
    return apply_filters('wp_fatal_error_handler_enabled', $enabled);
}

/** An extension's error in a sentence: its type by the E_* constant's name (the value itself when none has it), line, file and message, each in <code>, none escaped. */
function wp_get_extension_error_description($error)
{
    $names = [];
    foreach (get_defined_constants(true)['Core'] ?? [] as $name => $value) {
        if (str_starts_with($name, 'E_')) {
            $names[$value] = $name;
        }
    }
    $type = $names[$error['type']] ?? $error['type'];
    /* translators: 1: Error type, 2: Error line number, 3: Error file name, 4: Error message. */
    return sprintf(__('An error of type %1$s was caused in line %2$s of the file %3$s. Error message: %4$s'), "<code>{$type}</code>", "<code>{$error['line']}</code>", "<code>{$error['file']}</code>", "<code>{$error['message']}</code>");
}

function wp_clone($input_object)
{
    return clone $input_object;
}

/** The engine keeps no recovery-mode session: its own recovery pauses the failing plugin instead. */
function wp_is_recovery_mode()
{
    return Minn\Runtime\Recovery::inMode();
}

/** The recovery-mode object, one for the request (probe recovery). Minn keeps no recovery session, so its methods answer nothing (Austin, D4). */
function wp_recovery_mode()
{
    static $mode = null;
    return $mode ??= new WP_Recovery_Mode();
}

/** The paused plugins, one storage for the request, read from the engine's own pauses (probe recovery). */
function wp_paused_plugins()
{
    static $storage = null;
    return $storage ??= new WP_Paused_Extensions_Storage('plugin');
}

/** The paused themes, one storage for the request, read from the engine's own pauses (probe recovery). */
function wp_paused_themes()
{
    static $storage = null;
    return $storage ??= new WP_Paused_Extensions_Storage('theme');
}

/** The plugin files given, less the ones the engine has paused, keys kept (probe recovery). */
function wp_skip_paused_plugins(array $plugins)
{
    if (wp_paused_plugins()->get_all() === []) {
        return $plugins;
    }
    return array_filter($plugins, static function ($plugin) {
        $file = plugin_basename((string) $plugin);
        return wp_paused_plugins()->get($file) === null && (!str_contains($file, '/') || wp_paused_plugins()->get(dirname($file)) === null);
    });
}

/** The theme folders given, less the themes the engine has paused, keys kept (probe recovery). */
function wp_skip_paused_themes(array $themes)
{
    if (wp_paused_themes()->get_all() === []) {
        return $themes;
    }
    return array_filter($themes, static fn ($theme) => wp_paused_themes()->get(basename((string) $theme)) === null);
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
    wp_set_wpdb_vars();
}

/** @internal starts the object cache (a drop-in's or the engine's) with the groups the reference declares global and runtime-only */
/**
 * @internal the browser and server globals the reference sets at boot
 * ($is_chrome, $is_IE, $is_apache, …; Runtime\BrowserGlobals). An Internet
 * Explorer running Chrome Frame counts as Chrome where
 * use_google_chrome_frame says so (in the admin by default).
 */
function _minn_browser_globals(): void
{
    $agent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    $flags = Minn\Runtime\BrowserGlobals::detect($agent, (string) ($_SERVER['SERVER_SOFTWARE'] ?? ''));
    if ($flags['is_IE'] && str_contains($agent, 'chromeframe')) {
        $flags['is_chrome'] = (bool) apply_filters('use_google_chrome_frame', is_admin());
        $flags['is_winIE'] = !$flags['is_chrome'];
        $flags['is_IE'] = $flags['is_macIE'] || $flags['is_winIE'];
    }
    foreach ($flags as $name => $value) {
        $GLOBALS[$name] = $value;
    }
}

function _minn_start_object_cache(): void
{
    wp_cache_init();
    wp_cache_add_global_groups(['blog-details', 'blog-id-cache', 'blog-lookup', 'blog_meta', 'global-posts', 'image_editor', 'network-queries', 'networks', 'rss', 'site-details', 'site-options', 'site-queries', 'site-transient', 'sites', 'theme_files', 'translation_files', 'user-queries', 'user_meta', 'useremail', 'userlogins', 'users', 'userslugs']);
    wp_cache_add_non_persistent_groups(['counts', 'plugins', 'theme_json']);
    // The reference declares theme lookups request-only as soon as it builds a theme, which every boot does.
    wp_cache_add_non_persistent_groups('themes');
    // A drop-in holds the options group too, where option reads are kept.
    if (wp_using_ext_object_cache()) {
        Minn\Runtime\OptionsGroup::useExternal(
            static function (string $key, ?bool &$found = null) {
                return wp_cache_get($key, 'options', false, $found);
            },
            static fn (string $key, $value) => wp_cache_set($key, $value, 'options'),
            static fn (string $key) => wp_cache_delete($key, 'options'),
        );
    }
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

/** Whether this is the login screen: the script running is wp-login.php's, or the request is for it. */
function is_login()
{
    $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    $request = Minn\Runtime\Runtime::booted() ? Minn\Runtime\Runtime::current()->request : null;
    return ($script !== '' && stripos(wp_login_url(), $script) !== false) || ($request !== null && str_ends_with($request->path, '/wp-login.php'));
}

/** Whether this request is one recovery mode protects: the login screen, the admin outside Ajax, or a protected Ajax action; else is_protected_endpoint. */
function is_protected_endpoint()
{
    if (($GLOBALS['pagenow'] ?? '') === 'wp-login.php' || is_login() || (is_admin() && !wp_doing_ajax()) || is_protected_ajax_action()) {
        return true;
    }
    return (bool) apply_filters('is_protected_endpoint', false);
}

/** Whether this Ajax request is for an action that could fix a fatal error (wp_protected_ajax_actions). */
function is_protected_ajax_action()
{
    if (!wp_doing_ajax() || !isset($_REQUEST['action'])) {
        return false;
    }
    $actions = (array) apply_filters('wp_protected_ajax_actions', ['edit-theme-plugin-file', 'heartbeat', 'install-plugin', 'install-theme', 'search-plugins', 'search-install-plugins', 'update-plugin', 'update-theme', 'activate-plugin']);
    return in_array($_REQUEST['action'], $actions, true);
}

/** FORCE_SSL_ADMIN settled from the site address when wp-config left it out; the old FORCE_SSL_LOGIN turns it on. */
function wp_ssl_constants()
{
    if (!defined('FORCE_SSL_ADMIN')) {
        define('FORCE_SSL_ADMIN', parse_url((string) get_option('siteurl'), PHP_URL_SCHEME) === 'https');
    }
    force_ssl_admin(FORCE_SSL_ADMIN);
    if (defined('FORCE_SSL_LOGIN') && FORCE_SSL_LOGIN) {
        force_ssl_admin(true);
    }
}

/** The database is always connected here. */
function require_wp_db()
{
}

/** The database's table names and field types are set when it connects. */
/** The integer columns wpdb formats as %d when a caller gives no format (probe wpdb). */
function wp_set_wpdb_vars()
{
    global $wpdb;
    $wpdb->field_types = array_fill_keys(['post_author', 'post_parent', 'menu_order', 'term_id', 'term_group', 'term_taxonomy_id', 'parent', 'count', 'object_id', 'term_order', 'ID', 'comment_ID', 'comment_post_ID', 'comment_parent', 'user_id', 'link_id', 'link_owner', 'link_rating', 'option_id', 'blog_id', 'meta_id', 'post_id', 'user_status', 'umeta_id', 'comment_karma', 'comment_count', 'active', 'cat_id', 'deleted', 'lang_id', 'mature', 'public', 'site_id', 'spam'], '%d');
}

/** The object cache is always running here. */
function wp_start_object_cache()
{
}

/** The request globals slashed, and the request rebuilt from GET and POST. */
function wp_magic_quotes()
{
    $_GET = add_magic_quotes($_GET);
    $_POST = add_magic_quotes($_POST);
    $_COOKIE = add_magic_quotes($_COOKIE);
    $_SERVER = add_magic_quotes($_SERVER);
    $_REQUEST = array_merge($_GET, $_POST);
}

// Load helpers (probe load-helpers).

/** Whether a media type (or any in a comma-separated list) is JSON: application/json or application/{something}+json, case and parameters aside. */
function wp_is_json_media_type($media_type)
{
    return MediaType::json((string) $media_type);
}

/** Seconds since the request started ($timestart), as a float. */
function timer_float()
{
    return microtime(true) - (float) ($GLOBALS['timestart'] ?? microtime(true));
}

/** The multibyte functions' encoding set to the site's charset. */
function wp_set_internal_encoding()
{
    if (function_exists('mb_internal_encoding')) {
        mb_internal_encoding((string) (get_option('blog_charset') ?: 'UTF-8'));
    }
}

/** The language folder constants, defined when they are not yet. */
function wp_set_lang_dir()
{
    if (!defined('WP_LANG_DIR')) {
        define('WP_LANG_DIR', WP_CONTENT_DIR . '/languages');
    }
    if (!defined('LANGDIR')) {
        define('LANGDIR', 'wp-content/languages');
    }
}

/** The active theme's folder, after a child theme's own when there is one. */
function wp_get_active_and_valid_themes()
{
    $themes = [];
    if (get_stylesheet_directory() !== get_template_directory()) {
        $themes[] = get_stylesheet_directory();
    }
    $themes[] = get_template_directory();
    return $themes;
}

/** The end of a request: the shutdown action, then the object cache closed. */
function shutdown_action_hook()
{
    do_action('shutdown');
    wp_cache_close();
}

// The engine makes and looks up this layer's records through Minn\Runtime\Handoff, never by their class names.
Minn\Runtime\Handoff::bind([
    'newError' => static fn (...$args) => new WP_Error(...$args),
    'newPost' => static fn ($post) => new WP_Post($post),
    'postInstance' => static fn ($id) => WP_Post::get_instance($id),
    'newTerm' => static fn ($term) => new WP_Term($term),
    'newComment' => static fn ($comment) => new WP_Comment($comment),
    'commentInstance' => static fn ($id) => WP_Comment::get_instance($id),
    'newUser' => static fn (...$args) => new WP_User(...$args),
]);

// The queries, made through Minn\Runtime\Handoff like the records.
Minn\Runtime\Handoff::bind([
    'newQuery' => static fn (...$args) => new WP_Query(...$args),
    'newCommentQuery' => static fn (...$args) => new WP_Comment_Query(...$args),
    'newUserQuery' => static fn (...$args) => new WP_User_Query(...$args),
    'newTermQuery' => static fn (...$args) => new WP_Term_Query(...$args),
    'newMetaQuery' => static fn (...$args) => new WP_Meta_Query(...$args),
    'newDateQuery' => static fn (...$args) => new WP_Date_Query(...$args),
]);

// REST requests, responses and the controllers a filter is handed, made through Minn\Runtime\Handoff.
Minn\Runtime\Handoff::bind([
    'newRestRequest' => static fn (...$args) => new WP_REST_Request(...$args),
    'restRequestFromUrl' => static fn ($url) => WP_REST_Request::from_url($url),
    'newRestResponse' => static fn (...$args) => new WP_REST_Response(...$args),
    'restController' => static fn (string $kind, ...$args) => match ($kind) {
        'menus' => new WP_REST_Menus_Controller(...$args),
        'menu-items' => new WP_REST_Menu_Items_Controller(...$args),
        'menu-locations' => new WP_REST_Menu_Locations_Controller(...$args),
    },
]);

// Themes, made through Minn\Runtime\Handoff.
Minn\Runtime\Handoff::bind(['newTheme' => static fn (...$args) => new WP_Theme(...$args)]);

// A registered type's or taxonomy's object, made empty through Minn\Runtime\Handoff for the registry to fill.
Minn\Runtime\Handoff::bind([
    'blankPostType' => static fn () => (new ReflectionClass(WP_Post_Type::class))->newInstanceWithoutConstructor(),
    'blankTaxonomy' => static fn () => (new ReflectionClass(WP_Taxonomy::class))->newInstanceWithoutConstructor(),
]);

// Sessions, the locale and the upgraders, made through Minn\Runtime\Handoff.
Minn\Runtime\Handoff::bind([
    'sessionTokens' => static fn ($user_id) => WP_Session_Tokens::get_instance($user_id),
    'newLocale' => static fn () => new WP_Locale(),
    'newTextdomainRegistry' => static fn () => new WP_Textdomain_Registry(),
    'newUpgrader' => static fn ($kind, $skin) => $kind === 'theme' ? new Theme_Upgrader($skin) : new Plugin_Upgrader($skin),
    'newUpgraderSkin' => static fn (...$args) => new WP_Ajax_Upgrader_Skin(...$args),
]);

// Blocks and their registries, theme.json layers and the sitemap stylesheet, made and found through Minn\Runtime\Handoff.
Minn\Runtime\Handoff::bind([
    'newBlock' => static fn (...$args) => new WP_Block(...$args),
    'newBlockList' => static fn (...$args) => new WP_Block_List(...$args),
    'newBlockTemplate' => static fn (...$args) => new WP_Block_Template(...$args),
    'newTagProcessor' => static fn (...$args) => new WP_HTML_Tag_Processor(...$args),
    'newThemeJsonData' => static fn (...$args) => new WP_Theme_JSON_Data(...$args),
    'newSitemapStylesheet' => static fn (...$args) => new WP_Sitemaps_Stylesheet(...$args),
    'blockTypes' => static fn () => WP_Block_Type_Registry::get_instance(),
    'blockPatterns' => static fn () => WP_Block_Patterns_Registry::get_instance(),
    'blockStyles' => static fn () => WP_Block_Styles_Registry::get_instance(),
    'blockBindings' => static fn () => WP_Block_Bindings_Registry::get_instance(),
    'abilities' => static fn () => WP_Abilities_Registry::get_instance(),
    'abilityCategories' => static fn () => WP_Ability_Categories_Registry::get_instance(),
]);

// The site's roles object, handed over through Minn\Runtime\Handoff.
Minn\Runtime\Handoff::bind(['roles' => static fn () => wp_roles()]);

// The content filters, set again by the names plugins remove them by, through Minn\Runtime\Handoff.
Minn\Runtime\Handoff::bind(['resetKses' => static function (): void {
    kses_remove_filters();
    kses_init_filters();
}]);

// A theme template runs in this layer's globals, so the engine asks for it through Minn\Runtime\Handoff.
Minn\Runtime\Handoff::bind(['loadTemplate' => static fn (...$args) => load_template(...$args)]);

// The translation controller, handed over through Minn\Runtime\Handoff.
Minn\Runtime\Handoff::bind(['translationController' => static fn () => WP_Translation_Controller::get_instance()]);

// The rewrite object, the widget factory, the sitemap server and the menu walker, handed over through Minn\Runtime\Handoff.
Minn\Runtime\Handoff::bind([
    'rewrite' => static fn () => _minn_rewrite(),
    'widgetFactory' => static fn () => _minn_widget_factory(),
    'sitemaps' => static fn () => wp_sitemaps_get_server(),
    'newMenuWalker' => static fn () => new Walker_Nav_Menu(),
]);
