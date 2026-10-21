<?php

declare(strict_types=1);

namespace Minn\Admin;

use Closure;
use Minn\I18n\Gettext;
use Minn\Runtime\CronEvents;
use Minn\Runtime\Runtime;

/**
 * The Site Health screen's tests as the reference lists them (probe
 * blocks-rest-widget-doubts): the direct ones each by its label and the
 * name of the check that runs it, the asynchronous ones by their REST
 * route, with the check to run in their place when the screen runs them
 * directly; through site_status_tests. And the weekly scheduled check.
 */
final class SiteHealthTests
{
    /** The direct tests: name => [label, check, and true for one the scheduled check skips]. */
    private const DIRECT = [
        'wordpress_version' => ['WordPress Version', 'wordpress_version'],
        'plugin_version' => ['Plugin Versions', 'plugin_version'],
        'theme_version' => ['Theme Versions', 'theme_version'],
        'php_version' => ['PHP Version', 'php_version'],
        'php_extensions' => ['PHP Extensions', 'php_extensions'],
        'php_default_timezone' => ['PHP Default Timezone', 'php_default_timezone'],
        'php_sessions' => ['PHP Sessions', 'php_sessions'],
        'sql_server' => ['Database Server version', 'sql_server'],
        'ssl_support' => ['Secure communication', 'ssl_support'],
        'scheduled_events' => ['Scheduled events', 'scheduled_events'],
        'http_requests' => ['HTTP Requests', 'http_requests'],
        'rest_availability' => ['REST API availability', 'rest_availability', true],
        'debug_enabled' => ['Debugging enabled', 'is_in_debug_mode'],
        'file_uploads' => ['File uploads', 'file_uploads'],
        'plugin_theme_auto_updates' => ['Plugin and theme auto-updates', 'plugin_theme_auto_updates'],
        'update_temp_backup_writable' => ['Plugin and theme temporary backup directory access', 'update_temp_backup_writable'],
        'available_updates_disk_space' => ['Available disk space', 'available_updates_disk_space'],
        'autoloaded_options' => ['Autoloaded options', 'autoloaded_options'],
        'insecure_registration' => ['Open Registration with privileged default role', 'insecure_registration'],
        'search_engine_visibility' => ['Search Engine Visibility', 'search_engine_visibility'],
        'opcode_cache' => ['Opcode cache', 'opcode_cache'],
        'persistent_object_cache' => ['Persistent object cache', 'persistent_object_cache'],
    ];

    /** The asynchronous tests: name => [label, route slug]. */
    private const ASYNC = [
        'dotorg_communication' => ['Communication with WordPress.org', 'dotorg-communication'],
        'background_updates' => ['Background updates', 'background-updates'],
        'loopback_requests' => ['Loopback request', 'loopback-requests'],
        'https_status' => ['HTTPS status', 'https-status'],
        'authorization_header' => ['Authorization header', 'authorization-header'],
        'page_cache' => ['Page cache', 'page-cache'],
    ];

    /**
     * Every test, direct and asynchronous, through site_status_tests.
     *
     * @param object $health the Site Health instance whose checks run the asynchronous tests directly
     * @param Closure(string): string $route a test's REST address from its route slug
     * @return mixed what the filter makes of them
     */
    public static function all(object $health, Closure $route): mixed
    {
        $direct = [];
        foreach (self::DIRECT as $name => $test) {
            $direct[$name] = ['label' => Gettext::text($test[0]), 'test' => $test[1]] + (isset($test[2]) ? ['skip_cron' => true] : []);
        }
        $async = [];
        foreach (self::ASYNC as $name => [$label, $slug]) {
            $async[$name] = ['label' => Gettext::text($label), 'test' => $route($slug), 'has_rest' => true]
                + ($name === 'authorization_header'
                    ? ['headers' => ['Authorization' => 'Basic ' . base64_encode('user:pwd')], 'skip_cron' => true]
                    : ['async_direct_test' => [$health, 'get_test_' . $name]]);
        }
        return Runtime::hooks()->filter('site_status_tests', [['direct' => $direct, 'async' => $async]]);
    }

    /** The weekly scheduled check, scheduled a day from now unless it is already. */
    public static function scheduleWeeklyCheck(): void
    {
        if (CronEvents::next('wp_site_health_scheduled_check') === false) {
            CronEvents::schedule(time() + 86400, 'weekly', 'wp_site_health_scheduled_check');
        }
    }
}
