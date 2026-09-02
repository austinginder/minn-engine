<?php
/**
 * Plugin Name: Minn cron probe
 * Description: A WordPress plugin, unchanged, that schedules events the way plugins do and records every firing in an option. The cron suite switches it on and reads the record on both stacks.
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

add_filter('cron_schedules', static function ($schedules) {
    $schedules['minn_five_minutes'] = ['interval' => 300, 'display' => 'Every Five Minutes'];
    return $schedules;
});

add_action('init', static function () {
    if (!wp_next_scheduled('minn_test_cron_tick')) {
        wp_schedule_event(time(), 'minn_five_minutes', 'minn_test_cron_tick');
    }
});

function minn_test_cron_record($event, $args = [])
{
    $log = get_option('minn_test_cron_log');
    $log = is_array($log) ? $log : [];
    $log[] = ['event' => $event, 'args' => $args, 'at' => time()];
    update_option('minn_test_cron_log', $log, false);
}

add_action('minn_test_cron_tick', static function () {
    minn_test_cron_record('tick');
});

add_action('minn_test_cron_once', static function ($a = null, $b = null) {
    minn_test_cron_record('once', [$a, $b]);
}, 10, 2);
