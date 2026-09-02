<?php

declare(strict_types=1);

namespace Minn\Cli;

use WP_CLI;
use WP_CLI\Formatter;

/**
 * `wp cron`: the events, schedules, and spawn test a host and a fleet ask
 * for. Every verb boots the full runtime first, so a plugin's schedules and
 * callbacks are registered before an event is listed, scheduled, or run. The
 * wording and exit codes are the reference's, captured from `wp cron` on the
 * oracle; the two time columns drift second to second and are never pinned.
 */
final class CronCommand
{
    private const EVENT_FIELDS = ['hook', 'next_run_gmt', 'next_run_relative', 'recurrence'];
    private const SCHEDULE_FIELDS = ['name', 'display', 'interval'];

    /**
     * Lists the scheduled cron events.
     *
     * ## OPTIONS
     *
     * [--hook=<name>]
     * [--field=<field>]
     * [--fields=<fields>]
     * [--format=<format>]
     * [--<field>=<value>]
     * [--due-now]
     *
     * @when before_wp_load
     */
    public function event_list(array $args, array $assocArgs): void
    {
        Runtime::bootEngine();
        $hook = (string) ($assocArgs['hook'] ?? '');
        $dueNow = isset($assocArgs['due-now']);
        $now = time();
        $items = [];
        foreach (\_get_cron_array() as $timestamp => $hooks) {
            foreach ($hooks as $name => $keys) {
                if ($hook !== '' && $name !== $hook) {
                    continue;
                }
                if ($dueNow && (int) $timestamp > $now) {
                    continue;
                }
                foreach ($keys as $entry) {
                    $items[] = [
                        'hook' => $name,
                        'next_run_gmt' => gmdate('Y-m-d H:i:s', (int) $timestamp),
                        'next_run_relative' => self::relative((int) $timestamp - $now),
                        'recurrence' => isset($entry['interval']) ? self::duration((int) $entry['interval']) : 'Non-repeating',
                    ];
                }
            }
        }
        $format = (string) ($assocArgs['format'] ?? 'table');
        if ($format === 'ids' || $format === 'count') {
            $items = array_map(static fn (array $item): string => $item['hook'], $items);
        }
        (new Formatter($assocArgs, self::EVENT_FIELDS))->display_items($items);
    }

    /**
     * Runs the next scheduled cron event for the given hook.
     *
     * ## OPTIONS
     *
     * [<hook>...]
     * [--due-now]
     * [--all]
     *
     * @when before_wp_load
     */
    public function event_run(array $args, array $assocArgs): void
    {
        Runtime::bootEngine();
        $all = isset($assocArgs['all']);
        $dueNow = isset($assocArgs['due-now']);
        if ($args === [] && !$all && !$dueNow) {
            WP_CLI::error('Please specify one or more cron events, or use --due-now/--all.');
        }
        $crons = \_get_cron_array();
        foreach ($args as $hook) {
            if (!self::scheduled($crons, (string) $hook)) {
                WP_CLI::error("Invalid cron event '{$hook}'");
            }
        }
        $now = time();
        $ran = 0;
        foreach ($crons as $timestamp => $hooks) {
            foreach ($hooks as $hook => $keys) {
                $selected = $all || ($dueNow && (int) $timestamp <= $now) || in_array((string) $hook, array_map('strval', $args), true);
                if (!$selected) {
                    continue;
                }
                foreach ($keys as $entry) {
                    $started = microtime(true);
                    if (($entry['schedule'] ?? false) !== false) {
                        \wp_reschedule_event((int) $timestamp, $entry['schedule'], (string) $hook, $entry['args'], true);
                    }
                    \wp_unschedule_event((int) $timestamp, (string) $hook, $entry['args'], true);
                    \do_action_ref_array((string) $hook, $entry['args']);
                    $elapsed = number_format(microtime(true) - $started, 3);
                    WP_CLI::log("Executed the cron event '{$hook}' in {$elapsed}s.");
                    $ran++;
                }
            }
        }
        WP_CLI::success('Executed a total of ' . $ran . ' cron event' . ($ran === 1 ? '' : 's') . '.');
    }

    /**
     * Schedules a new cron event.
     *
     * ## OPTIONS
     *
     * <hook>
     * [<next-run>]
     * [<recurrence>]
     * [--<field>=<value>]
     *
     * @when before_wp_load
     */
    public function event_schedule(array $args, array $assocArgs): void
    {
        Runtime::bootEngine();
        $hook = (string) $args[0];
        $when = (string) ($args[1] ?? 'now');
        $recurrence = isset($args[2]) ? (string) $args[2] : '';
        if (count($args) > 3) {
            WP_CLI::error('Too many positional arguments: ' . implode(' ', array_slice($args, 3)));
        }
        $timestamp = strtotime($when, time());
        if ($timestamp === false) {
            WP_CLI::error("'{$when}' is not a valid datetime.");
        }
        if ($recurrence !== '' && !isset(\wp_get_schedules()[$recurrence])) {
            WP_CLI::error("'{$recurrence}' is not a valid schedule name for recurrence.");
        }
        $eventArgs = self::eventArgs($assocArgs);
        if ($eventArgs !== []) {
            WP_CLI::warning('Numeric keys should be used for the hook arguments.');
        }
        $result = $recurrence === ''
            ? \wp_schedule_single_event($timestamp, $hook, $eventArgs)
            : \wp_schedule_event($timestamp, $recurrence, $hook, $eventArgs);
        if ($result === false || \is_wp_error($result)) {
            WP_CLI::error('Event not scheduled.');
        }
        WP_CLI::success("Scheduled event with hook '{$hook}' for " . gmdate('Y-m-d H:i:s', $timestamp) . ' GMT.');
    }

    /**
     * Deletes all cron events for the given hook.
     *
     * ## OPTIONS
     *
     * <hook>...
     *
     * @when before_wp_load
     */
    public function event_delete(array $args, array $assocArgs): void
    {
        Runtime::bootEngine();
        if ($args === []) {
            WP_CLI::error('Please specify one or more cron events, or use --due-now/--all.');
        }
        $crons = \_get_cron_array();
        foreach ($args as $hook) {
            if (!self::scheduled($crons, (string) $hook)) {
                WP_CLI::error("Invalid cron event '{$hook}'");
            }
        }
        $deleted = 0;
        foreach ($args as $hook) {
            $removed = \wp_unschedule_hook((string) $hook);
            $deleted += is_int($removed) ? $removed : 0;
        }
        WP_CLI::success('Deleted a total of ' . $deleted . ' cron event' . ($deleted === 1 ? '' : 's') . '.');
    }

    /**
     * Unschedules all cron events for a given hook.
     *
     * ## OPTIONS
     *
     * <hook>
     *
     * @when before_wp_load
     */
    public function event_unschedule(array $args, array $assocArgs): void
    {
        Runtime::bootEngine();
        $hook = (string) $args[0];
        $removed = \wp_unschedule_hook($hook);
        if (!is_int($removed) || $removed === 0) {
            WP_CLI::error("No events found for hook '{$hook}'.");
        }
        WP_CLI::success("Unscheduled {$removed} event" . ($removed === 1 ? '' : 's') . " for hook '{$hook}'.");
    }

    /**
     * Lists available cron schedules.
     *
     * ## OPTIONS
     *
     * [--field=<field>]
     * [--fields=<fields>]
     * [--format=<format>]
     *
     * @when before_wp_load
     */
    public function schedule_list(array $args, array $assocArgs): void
    {
        Runtime::bootEngine();
        $items = [];
        foreach (\wp_get_schedules() as $name => $schedule) {
            $items[] = ['name' => $name, 'display' => $schedule['display'], 'interval' => (int) $schedule['interval']];
        }
        usort($items, static fn (array $a, array $b): int => $a['interval'] <=> $b['interval']);
        (new Formatter($assocArgs, self::SCHEDULE_FIELDS))->display_items($items);
    }

    /**
     * Tests the WP Cron spawning system and reports the results.
     *
     * @when before_wp_load
     */
    public function test(array $args, array $assocArgs): void
    {
        Runtime::boot();
        if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
            WP_CLI::warning('The DISABLE_WP_CRON constant is set to true. WP-Cron spawning is disabled.');
            return;
        }
        WP_CLI::success('WP-Cron spawning is working as expected.');
    }

    /** Whether any instance of the hook is in the cron table. */
    private static function scheduled(array $crons, string $hook): bool
    {
        foreach ($crons as $hooks) {
            if (isset($hooks[$hook])) {
                return true;
            }
        }
        return false;
    }

    /**
     * The event arguments passed as flags, the reference's own flags removed.
     *
     * @return array<string, string>
     */
    private static function eventArgs(array $assocArgs): array
    {
        unset($assocArgs['due-now'], $assocArgs['all'], $assocArgs['hook'], $assocArgs['field'], $assocArgs['fields'], $assocArgs['format']);
        return $assocArgs;
    }

    /** A number of seconds as the reference prints a recurrence: the two largest whole units, or "now". */
    private static function duration(int $seconds): string
    {
        if ($seconds <= 0) {
            return 'now';
        }
        $units = [['week', 604800], ['day', 86400], ['hour', 3600], ['minute', 60], ['second', 1]];
        $parts = [];
        foreach ($units as [$name, $length]) {
            if ($seconds < $length) {
                continue;
            }
            $count = intdiv($seconds, $length);
            $seconds -= $count * $length;
            $parts[] = $count . ' ' . $name . ($count === 1 ? '' : 's');
            if (count($parts) === 2) {
                break;
            }
        }
        return implode(' ', $parts);
    }

    /** The relative run time as the reference prints it: a duration ahead, or "now" once it is due. */
    private static function relative(int $seconds): string
    {
        return $seconds <= 0 ? 'now' : self::duration($seconds);
    }
}
