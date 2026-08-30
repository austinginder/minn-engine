<?php

declare(strict_types=1);

namespace Minn\Runtime;

/**
 * The cron option's shape, operated on as data: timestamp => hook => key =>
 * entry, kept in natural timestamp order. The key is the reference's own
 * (a hash of the serialized argument list), so both stacks read one table.
 */
final class CronTable
{
    /** @param array<int, array<string, array<string, array<string, mixed>>>> $crons */
    public static function key(array $args): string
    {
        return md5(serialize(array_values($args)));
    }

    /** Whether the same hook and arguments are already scheduled within the window around the timestamp. */
    public static function hasNear(array $crons, int $timestamp, string $hook, string $key, int $window): bool
    {
        foreach ($crons as $at => $hooks) {
            if ($at < $timestamp - $window) {
                continue;
            }
            if ($at > $timestamp + $window) {
                break;
            }
            if (isset($hooks[$hook][$key])) {
                return true;
            }
        }
        return false;
    }

    public static function insert(array $crons, int $timestamp, string $hook, string $key, array $entry): array
    {
        $crons[$timestamp][$hook][$key] = $entry;
        uksort($crons, 'strnatcasecmp');
        return $crons;
    }

    public static function remove(array $crons, int $timestamp, string $hook, string $key): array
    {
        unset($crons[$timestamp][$hook][$key]);
        if (empty($crons[$timestamp][$hook])) {
            unset($crons[$timestamp][$hook]);
        }
        if (empty($crons[$timestamp])) {
            unset($crons[$timestamp]);
        }
        return $crons;
    }

    /** @return array{0: array, 1: int} the table without the hook, and how many entries went */
    public static function removeHook(array $crons, string $hook): array
    {
        $removed = 0;
        foreach (array_keys($crons) as $timestamp) {
            $removed += count($crons[$timestamp][$hook] ?? []);
            unset($crons[$timestamp][$hook]);
            if (empty($crons[$timestamp])) {
                unset($crons[$timestamp]);
            }
        }
        return [$crons, $removed];
    }

    /** @return list<int> every timestamp the hook and arguments are scheduled at */
    public static function timestampsFor(array $crons, string $hook, string $key): array
    {
        $found = [];
        foreach ($crons as $timestamp => $hooks) {
            if (isset($hooks[$hook][$key])) {
                $found[] = (int) $timestamp;
            }
        }
        return $found;
    }

    /** The entry for the hook and arguments at a timestamp, or at the next one when no timestamp is given, as [timestamp, entry]. */
    public static function find(array $crons, string $hook, string $key, ?int $timestamp): ?array
    {
        if ($timestamp === null) {
            $timestamps = self::timestampsFor($crons, $hook, $key);
            $timestamp = $timestamps[0] ?? null;
        }
        return $timestamp !== null && isset($crons[$timestamp][$hook][$key]) ? [$timestamp, $crons[$timestamp][$hook][$key]] : null;
    }

    /** The next run of a recurring event: one interval from now, aligned to the original timestamp when it is in the past. */
    public static function nextRun(int $timestamp, int $interval, int $now): int
    {
        return $timestamp >= $now ? $now + $interval : $now + ($interval - (($now - $timestamp) % $interval));
    }

    /** The timestamps at or before now, in order. */
    public static function due(array $crons, int $now): array
    {
        $due = [];
        foreach ($crons as $timestamp => $hooks) {
            if ($timestamp > $now) {
                break;
            }
            $due[$timestamp] = $hooks;
        }
        return $due;
    }
}
