<?php
/** Scheduled events in the reference's cron option shape. Behaviour from contracts/fixtures/api/media.json. */

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
        return $wp_error ? new WP_Error('invalid_timestamp', 'Event timestamp must be a valid Unix timestamp.') : false;
    }
    $event = (object) ['hook' => $hook, 'timestamp' => (int) $timestamp, 'schedule' => false, 'args' => array_values((array) $args)];
    $pre = apply_filters('pre_schedule_event', null, $event, $wp_error);
    if ($pre !== null) {
        return $pre;
    }
    $crons = _get_cron_array();
    $key = md5(serialize($event->args));
    $duplicate = false;
    $min = $event->timestamp - 10 * MINUTE_IN_SECONDS;
    $max = $event->timestamp + 10 * MINUTE_IN_SECONDS;
    foreach ($crons as $ts => $hooks) {
        if ($ts < $min) {
            continue;
        }
        if ($ts > $max) {
            break;
        }
        if (isset($hooks[$event->hook][$key])) {
            $duplicate = true;
            break;
        }
    }
    if ($duplicate) {
        return $wp_error ? new WP_Error('duplicate_event', 'A duplicate event already exists.') : false;
    }
    $event = apply_filters('schedule_event', $event);
    if (!$event) {
        return $wp_error ? new WP_Error('schedule_event_false', 'A plugin disallowed this event.') : false;
    }
    $crons[$event->timestamp][$event->hook][$key] = ['schedule' => $event->schedule, 'args' => $event->args];
    uksort($crons, 'strnatcasecmp');
    return _set_cron_array($crons, $wp_error);
}

function wp_schedule_event($timestamp, $recurrence, $hook, $args = [], $wp_error = false)
{
    if (!is_numeric($timestamp) || $timestamp <= 0) {
        return $wp_error ? new WP_Error('invalid_timestamp', 'Event timestamp must be a valid Unix timestamp.') : false;
    }
    $schedules = wp_get_schedules();
    if (!isset($schedules[$recurrence])) {
        return $wp_error ? new WP_Error('invalid_schedule', 'Event schedule does not exist.') : false;
    }
    $event = (object) ['hook' => $hook, 'timestamp' => (int) $timestamp, 'schedule' => $recurrence, 'args' => array_values((array) $args), 'interval' => $schedules[$recurrence]['interval']];
    $pre = apply_filters('pre_schedule_event', null, $event, $wp_error);
    if ($pre !== null) {
        return $pre;
    }
    $event = apply_filters('schedule_event', $event);
    if (!$event) {
        return $wp_error ? new WP_Error('schedule_event_false', 'A plugin disallowed this event.') : false;
    }
    $key = md5(serialize($event->args));
    $crons = _get_cron_array();
    $crons[$event->timestamp][$event->hook][$key] = ['schedule' => $event->schedule, 'args' => $event->args, 'interval' => $event->interval];
    uksort($crons, 'strnatcasecmp');
    return _set_cron_array($crons, $wp_error);
}

function wp_reschedule_event($timestamp, $recurrence, $hook, $args = [], $wp_error = false)
{
    if (!is_numeric($timestamp) || $timestamp <= 0) {
        return $wp_error ? new WP_Error('invalid_timestamp', 'Event timestamp must be a valid Unix timestamp.') : false;
    }
    $schedules = wp_get_schedules();
    $interval = 0;
    if (isset($schedules[$recurrence])) {
        $interval = $schedules[$recurrence]['interval'];
    }
    if ($interval === 0) {
        $scheduled = wp_get_scheduled_event($hook, $args, $timestamp);
        if ($scheduled && isset($scheduled->interval)) {
            $interval = $scheduled->interval;
        }
    }
    $event = (object) ['hook' => $hook, 'timestamp' => (int) $timestamp, 'schedule' => $recurrence, 'args' => array_values((array) $args), 'interval' => $interval];
    $pre = apply_filters('pre_reschedule_event', null, $event, $wp_error);
    if ($pre !== null) {
        return $pre;
    }
    if ($interval === 0) {
        return $wp_error ? new WP_Error('invalid_schedule', 'Event schedule does not exist.') : false;
    }
    $now = time();
    if ($timestamp >= $now) {
        $timestamp = $now + $interval;
    } else {
        $timestamp = $now + ($interval - (($now - $timestamp) % $interval));
    }
    return wp_schedule_event($timestamp, $recurrence, $hook, $args, $wp_error);
}

function wp_unschedule_event($timestamp, $hook, $args = [], $wp_error = false)
{
    if (!is_numeric($timestamp) || $timestamp <= 0) {
        return $wp_error ? new WP_Error('invalid_timestamp', 'Event timestamp must be a valid Unix timestamp.') : false;
    }
    $pre = apply_filters('pre_unschedule_event', null, $timestamp, $hook, $args, $wp_error);
    if ($pre !== null) {
        return $pre;
    }
    $crons = _get_cron_array();
    $key = md5(serialize(array_values((array) $args)));
    unset($crons[(int) $timestamp][$hook][$key]);
    if (empty($crons[(int) $timestamp][$hook])) {
        unset($crons[(int) $timestamp][$hook]);
    }
    if (empty($crons[(int) $timestamp])) {
        unset($crons[(int) $timestamp]);
    }
    return _set_cron_array($crons, $wp_error);
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
    $crons = _get_cron_array();
    $key = md5(serialize(array_values((array) $args)));
    $results = [];
    foreach ($crons as $timestamp => $cron) {
        if (isset($cron[$hook][$key])) {
            $results[] = wp_unschedule_event($timestamp, $hook, $args, $wp_error);
        }
    }
    $errors = array_filter($results, 'is_wp_error');
    if ($errors !== []) {
        $error = new WP_Error();
        foreach ($errors as $e) {
            $error->merge_from($e);
        }
        return $error;
    }
    return count(array_filter($results));
}

function wp_unschedule_hook($hook, $wp_error = false)
{
    $pre = apply_filters('pre_unschedule_hook', null, $hook, $wp_error);
    if ($pre !== null) {
        return $pre;
    }
    $crons = _get_cron_array();
    $results = [];
    foreach ($crons as $timestamp => $args) {
        if (!empty($crons[$timestamp][$hook])) {
            $results[] = count($crons[$timestamp][$hook]);
        }
        unset($crons[$timestamp][$hook]);
        if (empty($crons[$timestamp])) {
            unset($crons[$timestamp]);
        }
    }
    if ($results === []) {
        return 0;
    }
    $set = _set_cron_array($crons, $wp_error);
    if ($set === true) {
        return array_sum($results);
    }
    return $set;
}

function wp_get_scheduled_event($hook, $args = [], $timestamp = null)
{
    $pre = apply_filters('pre_get_scheduled_event', null, $hook, $args, $timestamp);
    if ($pre !== null) {
        return $pre;
    }
    $crons = _get_cron_array();
    $key = md5(serialize(array_values((array) $args)));
    if ($timestamp === null) {
        $next = false;
        foreach ($crons as $ts => $cron) {
            if (isset($cron[$hook][$key])) {
                $next = $ts;
                break;
            }
        }
        if ($next === false) {
            return false;
        }
        $timestamp = $next;
    } elseif (!isset($crons[(int) $timestamp][$hook][$key])) {
        return false;
    }
    $timestamp = (int) $timestamp;
    $event = (object) ['hook' => $hook, 'timestamp' => $timestamp, 'schedule' => $crons[$timestamp][$hook][$key]['schedule'], 'args' => $crons[$timestamp][$hook][$key]['args']];
    if (isset($crons[$timestamp][$hook][$key]['interval'])) {
        $event->interval = $crons[$timestamp][$hook][$key]['interval'];
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
    $crons = _get_cron_array();
    $now = time();
    $results = [];
    foreach ($crons as $timestamp => $cronhooks) {
        if ($timestamp > $now) {
            break;
        }
        $results[$timestamp] = $cronhooks;
    }
    return $results;
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
