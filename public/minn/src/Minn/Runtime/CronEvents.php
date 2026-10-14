<?php

declare(strict_types=1);

namespace Minn\Runtime;

use JsonException;
use Minn\Json;

/**
 * Scheduled events as WordPress keeps them in the cron option: the list
 * read (an old one upgraded) and stored, the schedules, single and
 * recurring events added (a near duplicate refused), rescheduled, removed
 * one by one, by hook or by arguments, looked up, and the due ones run;
 * each step with the filters that may answer first.
 */
final class CronEvents
{
    /**
     * The scheduled events by timestamp (_get_cron_array), an old list
     * upgraded first; empty when there is none.
     *
     * @return array<int|string, mixed>
     */
    public static function all(): array
    {
        $cron = Runtime::options()->filtered('cron');
        if (!is_array($cron)) {
            return [];
        }
        if (!isset($cron['version'])) {
            $cron = self::upgrade($cron);
        }
        unset($cron['version']);
        ksort($cron);
        return $cron;
    }

    /** The events stored (_set_cron_array): whether they were, or the error saying they could not be when asked for one. */
    public static function store(mixed $cron, mixed $wpError = false): mixed
    {
        if (!is_array($cron)) {
            $cron = [];
        }
        $cron['version'] = 2;
        $result = OptionWrites::update('cron', $cron);
        if ($wpError && !$result) {
            return Handoff::newError('could_not_set', 'The cron event list could not be saved.');
        }
        return $result;
    }

    /**
     * The schedules events recur on (wp_get_schedules): hourly, twice daily,
     * daily and weekly, after the ones cron_schedules adds.
     *
     * @return array<string, mixed>
     */
    public static function schedules(): array
    {
        $schedules = [
            'hourly' => ['interval' => 3600, 'display' => 'Once Hourly'],
            'twicedaily' => ['interval' => 12 * 3600, 'display' => 'Twice Daily'],
            'daily' => ['interval' => 86400, 'display' => 'Once Daily'],
            'weekly' => ['interval' => 7 * 86400, 'display' => 'Once Weekly'],
        ];
        return array_merge((array) Runtime::hooks()->filter('cron_schedules', [[]]), $schedules);
    }

    /** An event run once (wp_schedule_single_event): refused near a duplicate (ten minutes either side) or when a plugin says no. */
    public static function scheduleSingle(mixed $timestamp, mixed $hook, mixed $args = [], mixed $wpError = false): mixed
    {
        if (!is_numeric($timestamp) || $timestamp <= 0) {
            return self::refuse($wpError, 'invalid_timestamp', 'Event timestamp must be a valid Unix timestamp.');
        }
        $event = (object) ['hook' => $hook, 'timestamp' => (int) $timestamp, 'schedule' => false, 'args' => array_values((array) $args)];
        $pre = Runtime::hooks()->filter('pre_schedule_event', [null, $event, $wpError]);
        if ($pre !== null) {
            return $pre;
        }
        $crons = self::all();
        if (CronTable::hasNear($crons, $event->timestamp, (string) $event->hook, CronTable::key($event->args), 600)) {
            return self::refuse($wpError, 'duplicate_event', 'A duplicate event already exists.');
        }
        $event = Runtime::hooks()->filter('schedule_event', [$event]);
        if (!$event) {
            return self::refuse($wpError, 'schedule_event_false', 'A plugin disallowed this event.');
        }
        return self::store(CronTable::insert($crons, $event->timestamp, (string) $event->hook, CronTable::key($event->args), ['schedule' => $event->schedule, 'args' => $event->args]), $wpError);
    }

    /** A recurring event (wp_schedule_event): refused for an unknown schedule or when a plugin says no. */
    public static function schedule(mixed $timestamp, mixed $recurrence, mixed $hook, mixed $args = [], mixed $wpError = false): mixed
    {
        if (!is_numeric($timestamp) || $timestamp <= 0) {
            return self::refuse($wpError, 'invalid_timestamp', 'Event timestamp must be a valid Unix timestamp.');
        }
        $schedules = self::schedules();
        if (!isset($schedules[$recurrence])) {
            return self::refuse($wpError, 'invalid_schedule', 'Event schedule does not exist.');
        }
        $event = (object) ['hook' => $hook, 'timestamp' => (int) $timestamp, 'schedule' => $recurrence, 'args' => array_values((array) $args), 'interval' => $schedules[$recurrence]['interval']];
        $pre = Runtime::hooks()->filter('pre_schedule_event', [null, $event, $wpError]);
        if ($pre !== null) {
            return $pre;
        }
        $event = Runtime::hooks()->filter('schedule_event', [$event]);
        if (!$event) {
            return self::refuse($wpError, 'schedule_event_false', 'A plugin disallowed this event.');
        }
        $entry = ['schedule' => $event->schedule, 'args' => $event->args, 'interval' => $event->interval];
        return self::store(CronTable::insert(self::all(), $event->timestamp, (string) $event->hook, CronTable::key($event->args), $entry), $wpError);
    }

    /**
     * A recurring event moved to its next run (wp_reschedule_event): the
     * interval its schedule names, else the one the event carries.
     */
    public static function reschedule(mixed $timestamp, mixed $recurrence, mixed $hook, mixed $args = [], mixed $wpError = false): mixed
    {
        if (!is_numeric($timestamp) || $timestamp <= 0) {
            return self::refuse($wpError, 'invalid_timestamp', 'Event timestamp must be a valid Unix timestamp.');
        }
        $interval = (int) (self::schedules()[$recurrence]['interval'] ?? 0);
        if ($interval === 0) {
            $scheduled = self::event($hook, $args, $timestamp);
            $interval = $scheduled && isset($scheduled->interval) ? (int) $scheduled->interval : 0;
        }
        $event = (object) ['hook' => $hook, 'timestamp' => (int) $timestamp, 'schedule' => $recurrence, 'args' => array_values((array) $args), 'interval' => $interval];
        $pre = Runtime::hooks()->filter('pre_reschedule_event', [null, $event, $wpError]);
        if ($pre !== null) {
            return $pre;
        }
        if ($interval === 0) {
            return self::refuse($wpError, 'invalid_schedule', 'Event schedule does not exist.');
        }
        return self::schedule(CronTable::nextRun((int) $timestamp, $interval, time()), $recurrence, $hook, $args, $wpError);
    }

    /** One event removed (wp_unschedule_event). */
    public static function unschedule(mixed $timestamp, mixed $hook, mixed $args = [], mixed $wpError = false): mixed
    {
        if (!is_numeric($timestamp) || $timestamp <= 0) {
            return self::refuse($wpError, 'invalid_timestamp', 'Event timestamp must be a valid Unix timestamp.');
        }
        $pre = Runtime::hooks()->filter('pre_unschedule_event', [null, $timestamp, $hook, $args, $wpError]);
        if ($pre !== null) {
            return $pre;
        }
        return self::store(CronTable::remove(self::all(), (int) $timestamp, (string) $hook, CronTable::key((array) $args)), $wpError);
    }

    /** Every event of a hook removed, whatever its arguments (wp_unschedule_hook): how many, 0 for none. */
    public static function unscheduleHook(mixed $hook, mixed $wpError = false): mixed
    {
        $pre = Runtime::hooks()->filter('pre_unschedule_hook', [null, $hook, $wpError]);
        if ($pre !== null) {
            return $pre;
        }
        [$crons, $removed] = CronTable::removeHook(self::all(), (string) $hook);
        if ($removed === 0) {
            return 0;
        }
        $set = self::store($crons, $wpError);
        return $set === true ? $removed : $set;
    }

    /** An event by hook and arguments, the next one or the one at a time (wp_get_scheduled_event), or false. */
    public static function event(mixed $hook, mixed $args = [], mixed $timestamp = null): mixed
    {
        $pre = Runtime::hooks()->filter('pre_get_scheduled_event', [null, $hook, $args, $timestamp]);
        if ($pre !== null) {
            return $pre;
        }
        $found = CronTable::find(self::all(), (string) $hook, CronTable::key((array) $args), $timestamp === null ? null : (int) $timestamp);
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

    /** When an event next runs (wp_next_scheduled), as wp_next_scheduled leaves it; false for none. */
    public static function next(mixed $hook, mixed $args = []): mixed
    {
        $event = self::event($hook, $args);
        return $event ? Runtime::hooks()->filter('wp_next_scheduled', [$event->timestamp, $event, $hook, $args]) : false;
    }

    /**
     * The events due now (wp_get_ready_cron_jobs).
     *
     * @return array<int|string, mixed>
     */
    public static function ready(): mixed
    {
        $pre = Runtime::hooks()->filter('pre_get_ready_cron_jobs', [null]);
        if ($pre !== null) {
            return $pre;
        }
        return CronTable::due(self::all(), time());
    }

    /**
     * The due events run (wp_cron): each recurring one rescheduled first,
     * each removed, then its hook fired with its arguments; how many ran.
     * A failure to reschedule or remove is logged and told as
     * cron_reschedule_event_error or cron_unschedule_event_error.
     */
    public static function run(): mixed
    {
        $crons = self::ready();
        if ($crons === []) {
            return 0;
        }
        $ran = 0;
        foreach ($crons as $timestamp => $cronhooks) {
            foreach ($cronhooks as $hook => $keys) {
                foreach ($keys as $v) {
                    $schedule = $v['schedule'];
                    if ($schedule) {
                        self::failed(self::reschedule($timestamp, $schedule, $hook, $v['args'], true), 'cron_reschedule_event_error', (string) $hook, $v);
                    }
                    self::failed(self::unschedule($timestamp, $hook, $v['args'], true), 'cron_unschedule_event_error', (string) $hook, $v);
                    Runtime::hooks()->actionRefArray((string) $hook, $v['args']);
                    $ran++;
                }
            }
        }
        return $ran;
    }

    /** A refusal: the error when one was asked for, else false. */
    private static function refuse(mixed $wpError, string $code, string $message): mixed
    {
        return $wpError ? Handoff::newError($code, $message) : false;
    }

    /** A failed reschedule or removal logged and told to its action. */
    private static function failed(mixed $result, string $action, string $hook, array $event): void
    {
        if (!Records::isError($result)) {
            return;
        }
        try {
            $json = Json::encode($event, Json::ESCAPED);
        } catch (JsonException) {
            $json = false;
        }
        error_log(sprintf('Cron %1$s event error for hook: %2$s, Error code: %3$s, Error message: %4$s, Data: %5$s', explode('_', $action)[1], $hook, $result->get_error_code(), $result->get_error_message(), $json));
        Runtime::hooks()->action($action, [$result, $hook, $event]);
    }

    /**
     * An old event list keyed by hook alone, keyed again by its arguments'
     * hash and stored (_upgrade_cron_array).
     *
     * @return array<int|string, mixed>
     */
    public static function upgrade(mixed $cron): array
    {
        if (isset($cron['version']) && $cron['version'] === 2) {
            return $cron;
        }
        $upgraded = [];
        foreach ((array) $cron as $timestamp => $hooks) {
            if ($timestamp === 'version') {
                $upgraded['version'] = 2;
                continue;
            }
            foreach ((array) $hooks as $hook => $event) {
                $upgraded[$timestamp][$hook][md5(serialize($event['args'] ?? null))] = $event;
            }
        }
        $upgraded['version'] = 2;
        OptionWrites::update('cron', $upgraded);
        return $upgraded;
    }
}
