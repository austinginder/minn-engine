<?php
/** Scheduled events in the reference's cron option shape. Behaviour from contracts/fixtures/api/media.json. */

use Minn\Runtime\CronTable;
use Minn\Runtime\Runtime;

/** The events as stored, a list read before version 2 upgraded (and saved) first. */
function _get_cron_array()
{
    return Minn\Runtime\CronEvents::all();
}

/**
 * A cron list from before version 2 (no version, or another one) with each
 * event keyed by md5(serialize(args)) and marked version 2, saved as the
 * cron option. A version 2 list comes back as given, unsaved.
 */
function _upgrade_cron_array($cron)
{
    return Minn\Runtime\CronEvents::upgrade($cron);
}

function _set_cron_array($cron, $wp_error = false)
{
    return Minn\Runtime\CronEvents::store($cron, $wp_error);
}

/** @internal false or a WP_Error, as the caller asked */
function _minn_cron_refuse($wp_error, string $code, string $message)
{
    return $wp_error ? new WP_Error($code, $message) : false;
}

function wp_get_schedules()
{
    return Minn\Runtime\CronEvents::schedules();
}

function wp_schedule_single_event($timestamp, $hook, $args = [], $wp_error = false)
{
    return Minn\Runtime\CronEvents::scheduleSingle($timestamp, $hook, $args, $wp_error);
}

function wp_schedule_event($timestamp, $recurrence, $hook, $args = [], $wp_error = false)
{
    return Minn\Runtime\CronEvents::schedule($timestamp, $recurrence, $hook, $args, $wp_error);
}

function wp_reschedule_event($timestamp, $recurrence, $hook, $args = [], $wp_error = false)
{
    return Minn\Runtime\CronEvents::reschedule($timestamp, $recurrence, $hook, $args, $wp_error);
}

function wp_unschedule_event($timestamp, $hook, $args = [], $wp_error = false)
{
    return Minn\Runtime\CronEvents::unschedule($timestamp, $hook, $args, $wp_error);
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
    return Minn\Runtime\CronEvents::unscheduleHook($hook, $wp_error);
}

function wp_get_scheduled_event($hook, $args = [], $timestamp = null)
{
    return Minn\Runtime\CronEvents::event($hook, $args, $timestamp);
}

function wp_next_scheduled($hook, $args = [])
{
    return Minn\Runtime\CronEvents::next($hook, $args);
}

function wp_get_schedule($hook, $args = [])
{
    $event = wp_get_scheduled_event($hook, $args);
    return apply_filters('get_schedule', $event ? $event->schedule : false, $hook, $args);
}

function wp_get_ready_cron_jobs()
{
    return Minn\Runtime\CronEvents::ready();
}

/**
 * A loopback request that runs the due events (probe boot-defaults): with an
 * event due and no lock younger than WP_CRON_LOCK_TIMEOUT, the lock is taken
 * (the doing_cron transient, the request's key) and a non-blocking POST goes
 * to wp-cron.php?doing_wp_cron={key}, through cron_request. False when
 * nothing is due or the lock holds. The engine's own Cron serves the request.
 */
function spawn_cron($gmt_time = 0)
{
    $now = $gmt_time ? (float) $gmt_time : microtime(true);
    $lock = (float) get_transient('doing_cron');
    $due = array_keys(wp_get_ready_cron_jobs());
    if (wp_doing_cron() || ($lock > 0 && $lock + WP_CRON_LOCK_TIMEOUT > $now && $lock <= $now + 10 * MINUTE_IN_SECONDS) || $due === [] || $due[0] > $now) {
        return false;
    }
    $key = sprintf('%.22F', $now);
    set_transient('doing_cron', $key);
    $request = apply_filters('cron_request', ['url' => add_query_arg('doing_wp_cron', $key, site_url('wp-cron.php')), 'key' => $key, 'args' => ['timeout' => 0.01, 'blocking' => false, 'sslverify' => apply_filters('https_local_ssl_verify', false)]], $key);
    return !is_wp_error(wp_remote_post($request['url'], $request['args']));
}

function wp_cron()
{
    return _minn_run_cron();
}

/**
 * @internal runs every due event: single events are removed first,
 * recurring ones rescheduled first, then the hook fires. A reschedule or
 * removal that fails is logged and told to cron_reschedule_event_error or
 * cron_unschedule_event_error, as wp-cron.php tells them (probe
 * hook-inputs-2), and the hook fires anyway.
 */
function _minn_run_cron()
{
    return Runtime::booted() ? Minn\Runtime\CronEvents::run() : false;
}


/** @internal a due event's reschedule or removal that failed, logged and told to its action (cron_reschedule_event_error, cron_unschedule_event_error) */
function _minn_cron_failed($result, string $action, string $hook, array $event): void
{
    if (!is_wp_error($result)) {
        return;
    }
    error_log(sprintf('Cron %1$s event error for hook: %2$s, Error code: %3$s, Error message: %4$s, Data: %5$s', explode('_', $action)[1], $hook, $result->get_error_code(), $result->get_error_message(), wp_json_encode($event)));
    do_action($action, $result, $hook, $event);
}

/** The core, plugin and theme update checks forgotten, so the next check asks again (probe placeholder-helpers). */
function wp_clean_update_cache()
{
    foreach (['update_plugins', 'update_themes', 'update_core'] as $transient) {
        delete_site_transient($transient);
    }
}

/**
 * Minn's own core, plugin and theme checks, run when the stored answer is
 * older than twelve hours (a deliberate difference: the reference asks
 * wordpress.org; contracts/api/different.json).
 */
function _maybe_update_core()
{
    _minn_update_check_is_fresh('update_core') || wp_version_check();
}

function _maybe_update_plugins()
{
    _minn_update_check_is_fresh('update_plugins') || wp_update_plugins();
}

function _maybe_update_themes()
{
    _minn_update_check_is_fresh('update_themes') || wp_update_themes();
}

/** @internal whether an update transient was filled in the last twelve hours */
function _minn_update_check_is_fresh(string $transient): bool
{
    $stored = get_site_transient($transient);
    return is_object($stored) && isset($stored->last_checked) && time() - (int) $stored->last_checked < 12 * HOUR_IN_SECONDS;
}
