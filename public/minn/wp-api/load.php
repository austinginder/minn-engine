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
    return Runtime::current()->isAdmin;
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
    return null;
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
