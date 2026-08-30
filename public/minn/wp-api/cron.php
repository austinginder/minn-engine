<?php
/** Scheduled events in the reference's cron option shape. Behaviour from contracts/fixtures/api/media.json. */

use Minn\Runtime\CronTable;
use Minn\Runtime\Runtime;

function _get_cron_array()
{
    $cron = get_option('cron');
    if (!is_array($cron)) {
        return [];
    }
    unset($cron['version']);
    ksort($cron);
    return $cron;
}

function _set_cron_array($cron, $wp_error = false)
{
    if (!is_array($cron)) {
        $cron = [];
    }
    $cron['version'] = 2;
    $result = update_option('cron', $cron);
    if ($wp_error && !$result) {
        return new WP_Error('could_not_set', 'The cron event list could not be saved.');
    }
    return $result;
}

/** @internal false or a WP_Error, as the caller asked */
function _minn_cron_refuse($wp_error, string $code, string $message)
{
    return $wp_error ? new WP_Error($code, $message) : false;
}

function wp_get_schedules()
{
    $schedules = [
        'hourly' => ['interval' => HOUR_IN_SECONDS, 'display' => 'Once Hourly'],
        'twicedaily' => ['interval' => 12 * HOUR_IN_SECONDS, 'display' => 'Twice Daily'],
        'daily' => ['interval' => DAY_IN_SECONDS, 'display' => 'Once Daily'],
        'weekly' => ['interval' => WEEK_IN_SECONDS, 'display' => 'Once Weekly'],
    ];
    return array_merge(apply_filters('cron_schedules', []), $schedules);
}

function wp_schedule_single_event($timestamp, $hook, $args = [], $wp_error = false)
{
    if (!is_numeric($timestamp) || $timestamp <= 0) {
        return _minn_cron_refuse($wp_error, 'invalid_timestamp', 'Event timestamp must be a valid Unix timestamp.');
    }
    $event = (object) ['hook' => $hook, 'timestamp' => (int) $timestamp, 'schedule' => false, 'args' => array_values((array) $args)];
    $pre = apply_filters('pre_schedule_event', null, $event, $wp_error);
    if ($pre !== null) {
        return $pre;
    }
    $crons = _get_cron_array();
    if (CronTable::hasNear($crons, $event->timestamp, (string) $event->hook, CronTable::key($event->args), 10 * MINUTE_IN_SECONDS)) {
        return _minn_cron_refuse($wp_error, 'duplicate_event', 'A duplicate event already exists.');
    }
    $event = apply_filters('schedule_event', $event);
    if (!$event) {
        return _minn_cron_refuse($wp_error, 'schedule_event_false', 'A plugin disallowed this event.');
    }
    return _set_cron_array(CronTable::insert($crons, $event->timestamp, (string) $event->hook, CronTable::key($event->args), ['schedule' => $event->schedule, 'args' => $event->args]), $wp_error);
}

function wp_schedule_event($timestamp, $recurrence, $hook, $args = [], $wp_error = false)
{
    if (!is_numeric($timestamp) || $timestamp <= 0) {
        return _minn_cron_refuse($wp_error, 'invalid_timestamp', 'Event timestamp must be a valid Unix timestamp.');
    }
    $schedules = wp_get_schedules();
    if (!isset($schedules[$recurrence])) {
        return _minn_cron_refuse($wp_error, 'invalid_schedule', 'Event schedule does not exist.');
    }
    $event = (object) ['hook' => $hook, 'timestamp' => (int) $timestamp, 'schedule' => $recurrence, 'args' => array_values((array) $args), 'interval' => $schedules[$recurrence]['interval']];
    $pre = apply_filters('pre_schedule_event', null, $event, $wp_error);
    if ($pre !== null) {
        return $pre;
    }
    $event = apply_filters('schedule_event', $event);
    if (!$event) {
        return _minn_cron_refuse($wp_error, 'schedule_event_false', 'A plugin disallowed this event.');
    }
    $entry = ['schedule' => $event->schedule, 'args' => $event->args, 'interval' => $event->interval];
    return _set_cron_array(CronTable::insert(_get_cron_array(), $event->timestamp, (string) $event->hook, CronTable::key($event->args), $entry), $wp_error);
}

function wp_reschedule_event($timestamp, $recurrence, $hook, $args = [], $wp_error = false)
{
    if (!is_numeric($timestamp) || $timestamp <= 0) {
        return _minn_cron_refuse($wp_error, 'invalid_timestamp', 'Event timestamp must be a valid Unix timestamp.');
    }
    $interval = (int) (wp_get_schedules()[$recurrence]['interval'] ?? 0);
    if ($interval === 0) {
        $scheduled = wp_get_scheduled_event($hook, $args, $timestamp);
        $interval = $scheduled && isset($scheduled->interval) ? (int) $scheduled->interval : 0;
    }
    $event = (object) ['hook' => $hook, 'timestamp' => (int) $timestamp, 'schedule' => $recurrence, 'args' => array_values((array) $args), 'interval' => $interval];
    $pre = apply_filters('pre_reschedule_event', null, $event, $wp_error);
    if ($pre !== null) {
        return $pre;
    }
    if ($interval === 0) {
        return _minn_cron_refuse($wp_error, 'invalid_schedule', 'Event schedule does not exist.');
    }
    return wp_schedule_event(CronTable::nextRun((int) $timestamp, $interval, time()), $recurrence, $hook, $args, $wp_error);
}

function wp_unschedule_event($timestamp, $hook, $args = [], $wp_error = false)
{
    if (!is_numeric($timestamp) || $timestamp <= 0) {
        return _minn_cron_refuse($wp_error, 'invalid_timestamp', 'Event timestamp must be a valid Unix timestamp.');
    }
    $pre = apply_filters('pre_unschedule_event', null, $timestamp, $hook, $args, $wp_error);
    if ($pre !== null) {
        return $pre;
    }
    return _set_cron_array(CronTable::remove(_get_cron_array(), (int) $timestamp, (string) $hook, CronTable::key((array) $args)), $wp_error);
}

function wp_clear_scheduled_hook($hook, $args = [], $wp_error = false)
{
    if (!is_array($args)) {
        $args = array_slice(func_get_args(), 1);
        $wp_error = false;
    }
    $pre = apply_filters('pre_clear_scheduled_hook', null, $hook, $args, $wp_error);
    if ($pre !== null) {
        return $pre;
    }
    $results = [];
    foreach (CronTable::timestampsFor(_get_cron_array(), (string) $hook, CronTable::key($args)) as $timestamp) {
        $results[] = wp_unschedule_event($timestamp, $hook, $args, $wp_error);
    }
    $errors = array_filter($results, 'is_wp_error');
    if ($errors === []) {
        return count(array_filter($results));
    }
    $error = new WP_Error();
    foreach ($errors as $e) {
        $error->merge_from($e);
    }
    return $error;
}

function wp_unschedule_hook($hook, $wp_error = false)
{
    $pre = apply_filters('pre_unschedule_hook', null, $hook, $wp_error);
    if ($pre !== null) {
        return $pre;
    }
    [$crons, $removed] = CronTable::removeHook(_get_cron_array(), (string) $hook);
    if ($removed === 0) {
        return 0;
    }
    $set = _set_cron_array($crons, $wp_error);
    return $set === true ? $removed : $set;
}

function wp_get_scheduled_event($hook, $args = [], $timestamp = null)
{
    $pre = apply_filters('pre_get_scheduled_event', null, $hook, $args, $timestamp);
    if ($pre !== null) {
        return $pre;
    }
    $found = CronTable::find(_get_cron_array(), (string) $hook, CronTable::key((array) $args), $timestamp === null ? null : (int) $timestamp);
    if ($found === null) {
        return false;
    }
    [$at, $entry] = $found;
    $event = (object) ['hook' => $hook, 'timestamp' => $at, 'schedule' => $entry['schedule'], 'args' => $entry['args']];
    if (isset($entry['interval'])) {
        $event->interval = $entry['interval'];
    }
    return $event;
}

function wp_next_scheduled($hook, $args = [])
{
    $event = wp_get_scheduled_event($hook, $args);
    return $event ? $event->timestamp : false;
}

function wp_get_schedule($hook, $args = [])
{
    $event = wp_get_scheduled_event($hook, $args);
    return apply_filters('get_schedule', $event ? $event->schedule : false, $hook, $args);
}

function wp_get_ready_cron_jobs()
{
    $pre = apply_filters('pre_get_ready_cron_jobs', null);
    if ($pre !== null) {
        return $pre;
    }
    return CronTable::due(_get_cron_array(), time());
}

function spawn_cron($gmt_time = 0)
{
    return wp_cron() !== false;
}

function wp_cron()
{
    return _minn_run_cron();
}

/** @internal runs every due event: single events are removed first, recurring ones rescheduled first, then the hook fires */
function _minn_run_cron()
{
    if (!Runtime::booted()) {
        return false;
    }
    $crons = wp_get_ready_cron_jobs();
    if ($crons === []) {
        return 0;
    }
    $ran = 0;
    foreach ($crons as $timestamp => $cronhooks) {
        foreach ($cronhooks as $hook => $keys) {
            foreach ($keys as $key => $v) {
                $schedule = $v['schedule'];
                if ($schedule) {
                    wp_reschedule_event($timestamp, $schedule, $hook, $v['args'], true);
                }
                wp_unschedule_event($timestamp, $hook, $v['args'], true);
                do_action_ref_array($hook, $v['args']);
                $ran++;
            }
        }
    }
    return $ran;
}

function wp_schedule_update_checks()
{
}
