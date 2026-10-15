<?php
/**
 * Drop-in probe for a page request: reports, at shutdown, what existed when the page cache's early
 * file loaded and whether its top-level variable became a global; which of the reference's earliest
 * functions a page cache may call were there, and what the ones about the request answered (WP
 * Rocket's early file asks is_admin() and is_ssl()).
 */
$minn_probe_top_level = 'set at the top of the file';
$minn_probe_early = ['is_admin', 'is_ssl', 'wp_doing_ajax', 'wp_doing_cron', 'is_multisite', 'get_current_blog_id', 'absint', 'is_wp_error', 'wp_installing', 'wp_is_json_request', 'wp_is_xml_request', 'wp_get_server_protocol', 'wp_get_environment_type', 'wp_using_ext_object_cache', 'wp_convert_hr_to_bytes', 'is_login', 'plugin_basename', 'register_activation_hook', 'wp_get_mu_plugins', 'wp_cookie_constants', 'timer_start', 'wp_is_file_mod_allowed', 'wp_is_maintenance_mode', '_wp_utf8_codepoint_count'];
$GLOBALS['minn_probe_advanced_cache'] = [
    'add_action' => function_exists('add_action'),
    'get_option' => function_exists('get_option'),
    'wpdb' => isset($GLOBALS['wpdb']),
    'object cache' => isset($GLOBALS['wp_object_cache']),
    'early constants missing' => array_values(array_filter(['WPINC', 'KB_IN_BYTES', 'MB_IN_BYTES', 'GB_IN_BYTES', 'YB_IN_BYTES', 'WP_START_TIMESTAMP', 'WP_MEMORY_LIMIT', 'WP_MAX_MEMORY_LIMIT', 'WP_CONTENT_DIR', 'WP_DEVELOPMENT_MODE', 'SCRIPT_DEBUG', 'MEDIA_TRASH', 'SHORTINIT', 'WP_FEATURE_BETTER_PASSWORDS', 'MINUTE_IN_SECONDS', 'HOUR_IN_SECONDS', 'DAY_IN_SECONDS', 'WEEK_IN_SECONDS', 'MONTH_IN_SECONDS', 'YEAR_IN_SECONDS', 'WP_USE_THEMES', 'WP_DEBUG', 'WP_CACHE'], static fn (string $name): bool => !defined($name))),
    'early functions missing' => array_values(array_filter($minn_probe_early, static fn (string $name): bool => !function_exists($name))),
    'answers' => [
        'is_admin' => is_admin(),
        'is_ssl' => is_ssl(),
        'wp_doing_ajax' => wp_doing_ajax(),
        'wp_doing_cron' => wp_doing_cron(),
        'is_multisite' => is_multisite(),
        'get_current_blog_id' => get_current_blog_id(),
        'absint' => absint('-5'),
        'is_wp_error' => is_wp_error(null),
        'wp_installing' => wp_installing(),
        'wp_is_json_request' => wp_is_json_request(),
        'wp_get_server_protocol' => wp_get_server_protocol(),
        'wp_convert_hr_to_bytes' => wp_convert_hr_to_bytes('2M'),
    ],
];
register_shutdown_function(static function (): void {
    fwrite(STDERR, 'MINN-PROBE ' . json_encode($GLOBALS['minn_probe_advanced_cache'] + ['top-level variable is global' => isset($GLOBALS['minn_probe_top_level'])]) . "\n");
});
