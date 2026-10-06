<?php

/**
 * Site Health, as far as plugins reach it outside its own screen: the
 * instance the reference builds while loading, with the memory limit PHP
 * started with (the WPMU DEV dashboard reports it), the cron timeouts, and
 * the weekly check scheduled. The tests themselves are placeholders: Minn
 * has no Site Health screen to run them on.
 */
#[AllowDynamicProperties]
class WP_Site_Health
{
    private static $instance = NULL;
    private $is_acceptable_mysql_version = NULL;
    private $is_recommended_mysql_version = NULL;
    public $is_mariadb = false;
    private $mysql_server_version = '';
    private $mysql_required_version = '5.5';
    private $mysql_recommended_version = '8.0';
    private $mariadb_recommended_version = '10.11';
    public $php_memory_limit = NULL;
    public $schedules = NULL;
    public $crons = NULL;
    public $last_missed_cron = NULL;
    public $last_late_cron = NULL;
    private $timeout_missed_cron = NULL;
    private $timeout_late_cron = NULL;

    /**
     * As the reference builds it while loading (captured): the memory limit
     * PHP started with, the cron timeouts (looser when page loads do not run
     * cron), its hooks, and the weekly check scheduled a day out when it is
     * not.
     */
    public function __construct()
    {
        $this->php_memory_limit = (string) ini_get('memory_limit');
        $cronOff = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
        $this->timeout_missed_cron = $cronOff ? -HOUR_IN_SECONDS : -5 * MINUTE_IN_SECONDS;
        $this->timeout_late_cron = $cronOff ? -15 * MINUTE_IN_SECONDS : 0;
        add_filter('admin_body_class', [$this, 'admin_body_class']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('site_health_tab_content', [$this, 'show_site_health_tab']);
        add_action('wp_site_health_scheduled_check', [$this, 'wp_cron_scheduled_check']);
        if (!wp_installing() && !wp_next_scheduled('wp_site_health_scheduled_check')) {
            wp_schedule_event(time() + DAY_IN_SECONDS, 'weekly', 'wp_site_health_scheduled_check');
        }
    }

    public function show_site_health_tab($tab)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Site_Health::show_site_health_tab');
        return null;
    }

    /** The one instance. */
    public static function get_instance()
    {
        return self::$instance ??= new self();
    }

    public function enqueue_scripts()
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Site_Health::enqueue_scripts');
        return null;
    }

    private function perform_test($callback)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Site_Health::perform_test');
        return null;
    }

    private function prepare_sql_data()
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Site_Health::prepare_sql_data');
        return null;
    }

    public function check_wp_version_check_exists()
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Site_Health::check_wp_version_check_exists');
        return null;
    }

    public function get_test_wordpress_version()
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Site_Health::get_test_wordpress_version');
        return null;
    }

    public function get_test_plugin_version()
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Site_Health::get_test_plugin_version');
        return null;
    }

    public function get_test_theme_version()
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Site_Health::get_test_theme_version');
        return null;
    }

    public function get_test_php_version()
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Site_Health::get_test_php_version');
        return null;
    }

    private function test_php_extension_availability($extension_name = NULL, $function_name = NULL, $constant_name = NULL, $class_name = NULL)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Site_Health::test_php_extension_availability');
        return null;
    }

    public function get_test_php_extensions()
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Site_Health::get_test_php_extensions');
        return null;
    }

    public function get_test_php_default_timezone()
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Site_Health::get_test_php_default_timezone');
        return null;
    }

    public function get_test_php_sessions()
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Site_Health::get_test_php_sessions');
        return null;
    }

    public function get_test_sql_server()
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Site_Health::get_test_sql_server');
        return null;
    }

    public function get_test_dotorg_communication()
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Site_Health::get_test_dotorg_communication');
        return null;
    }

    public function get_test_is_in_debug_mode()
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Site_Health::get_test_is_in_debug_mode');
        return null;
    }

    public function get_test_https_status()
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Site_Health::get_test_https_status');
        return null;
    }

    public function get_test_ssl_support()
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Site_Health::get_test_ssl_support');
        return null;
    }

    public function get_test_scheduled_events()
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Site_Health::get_test_scheduled_events');
        return null;
    }

    public function get_test_background_updates()
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Site_Health::get_test_background_updates');
        return null;
    }

    public function get_test_plugin_theme_auto_updates()
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Site_Health::get_test_plugin_theme_auto_updates');
        return null;
    }

    public function get_test_available_updates_disk_space()
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Site_Health::get_test_available_updates_disk_space');
        return null;
    }

    public function get_test_insecure_registration()
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Site_Health::get_test_insecure_registration');
        return null;
    }

    public function get_test_update_temp_backup_writable()
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Site_Health::get_test_update_temp_backup_writable');
        return null;
    }

    public function get_test_loopback_requests()
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Site_Health::get_test_loopback_requests');
        return null;
    }

    public function get_test_http_requests()
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Site_Health::get_test_http_requests');
        return null;
    }

    public function get_test_rest_availability()
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Site_Health::get_test_rest_availability');
        return null;
    }

    public function get_test_file_uploads()
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Site_Health::get_test_file_uploads');
        return null;
    }

    public function get_test_authorization_header()
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Site_Health::get_test_authorization_header');
        return null;
    }

    public function get_test_page_cache()
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Site_Health::get_test_page_cache');
        return null;
    }

    public function get_test_persistent_object_cache()
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Site_Health::get_test_persistent_object_cache');
        return null;
    }

    public function get_autoloaded_options_size()
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Site_Health::get_autoloaded_options_size');
        return null;
    }

    public function get_test_autoloaded_options()
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Site_Health::get_test_autoloaded_options');
        return null;
    }

    public function get_test_search_engine_visibility()
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Site_Health::get_test_search_engine_visibility');
        return null;
    }

    public function get_test_opcode_cache()
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Site_Health::get_test_opcode_cache');
        return [];
    }

    public static function get_tests()
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Site_Health::get_tests');
        return null;
    }

    /** The Site Health screen's body class; any other screen's classes pass through. */
    public function admin_body_class($body_class)
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        return $screen !== null && ($screen->id ?? '') === 'site-health' ? $body_class . ' site-health' : $body_class;
    }

    private function wp_schedule_test_init()
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Site_Health::wp_schedule_test_init');
        return null;
    }

    private function get_cron_tasks()
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Site_Health::get_cron_tasks');
        return null;
    }

    public function has_missed_cron()
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Site_Health::has_missed_cron');
        return null;
    }

    public function has_late_cron()
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Site_Health::has_late_cron');
        return null;
    }

    public function detect_plugin_theme_auto_update_issues()
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Site_Health::detect_plugin_theme_auto_update_issues');
        return null;
    }

    public function can_perform_loopback()
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Site_Health::can_perform_loopback');
        return null;
    }

    public function maybe_create_scheduled_event()
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Site_Health::maybe_create_scheduled_event');
        return null;
    }

    public function wp_cron_scheduled_check()
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Site_Health::wp_cron_scheduled_check');
        return null;
    }

    public function is_development_environment()
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Site_Health::is_development_environment');
        return null;
    }

    public function get_page_cache_headers()
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Site_Health::get_page_cache_headers');
        return [];
    }

    private function check_for_page_caching()
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Site_Health::check_for_page_caching');
        return null;
    }

    private function get_page_cache_detail()
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Site_Health::get_page_cache_detail');
        return null;
    }

    private function get_good_response_time_threshold()
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Site_Health::get_good_response_time_threshold');
        return null;
    }

    public function should_suggest_persistent_object_cache()
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Site_Health::should_suggest_persistent_object_cache');
        return null;
    }

    private function available_object_cache_services()
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Site_Health::available_object_cache_services');
        return null;
    }
}

