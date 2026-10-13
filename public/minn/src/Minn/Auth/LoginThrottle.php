<?php

declare(strict_types=1);

namespace Minn\Auth;

use Minn\Db;
use Minn\Runtime\Runtime;

/**
 * Failed sign-ins per address, so a password guesser meets a wall: twenty
 * failures in fifteen minutes and the address waits out the window. The
 * counters live in the options table (autoload off), one row per address,
 * as "window start:count"; a successful sign-in clears the row. An IPv6
 * address counts as its /64, the block one subscriber is handed, so
 * stepping through it buys no fresh tries; each failure is counted in one
 * statement, so failures arriving together are all counted.
 */
final readonly class LoginThrottle
{
    public const LIMIT = 20;
    public const WINDOW = 900;
    private const PREFIX = 'minn_login_throttle_';

    public function __construct(private Db $db)
    {
    }

    /** Seconds the address must wait, or null when it may try. */
    public function retryAfter(string $address, ?int $now = null): ?int
    {
        [$start, $count] = $this->read($address);
        $now ??= time();
        if ($count < self::LIMIT || $start + self::WINDOW <= $now) {
            return null;
        }
        return $start + self::WINDOW - $now;
    }

    /** Counts one failed sign-in from an address within the current window. */
    public function recordFailure(string $address, ?int $now = null): void
    {
        $now ??= time();
        $table = $this->db->table('options');
        // One statement: a window that has passed starts again at 1, a live one counts up, and two failures together are both counted.
        $this->db->execute(
            "INSERT INTO {$table} (option_name, option_value, autoload) VALUES (?, ?, 'off') ON DUPLICATE KEY UPDATE option_value = IF("
            . "CAST(SUBSTRING_INDEX(option_value, ':', 1) AS UNSIGNED) + ? <= ?, VALUES(option_value), "
            . "CONCAT(SUBSTRING_INDEX(option_value, ':', 1), ':', CAST(SUBSTRING_INDEX(option_value, ':', -1) AS UNSIGNED) + 1))",
            [self::key($address), "{$now}:1", self::WINDOW, $now],
        );
        Runtime::optionWritten(self::key($address));
        if (random_int(1, 50) === 1) {
            self::sweep($this->db, $now);
        }
    }

    /** Forgets an address's failures. */
    public function clear(string $address): void
    {
        $this->db->execute("DELETE FROM {$this->db->table('options')} WHERE option_name = ?", [self::key($address)]);
        Runtime::optionWritten(self::key($address));
    }

    /** @return array{0: int, 1: int} window start and failure count */
    private function read(string $address): array
    {
        // The row itself: another request may have written it a moment ago.
        $value = $this->db->storedOption(self::key($address));
        if ($value === null || !preg_match('/^(\d+):(\d+)$/', $value, $m)) {
            return [0, 0];
        }
        return [(int) $m[1], (int) $m[2]];
    }

    /**
     * Drops counters whose window has passed, at most a thousand in one
     * statement, however many addresses have failed; the number dropped.
     */
    public static function sweep(Db $db, int $now): int
    {
        return $db->execute(
            "DELETE FROM {$db->table('options')} WHERE option_name LIKE ? AND CAST(SUBSTRING_INDEX(option_value, ':', 1) AS UNSIGNED) + ? <= ? LIMIT 1000",
            [self::PREFIX . '%', self::WINDOW, $now],
        );
    }

    /**
     * The counter's option: the address's, or an IPv6 address's /64. An
     * address whose first 64 bits are zero (an IPv4 client a dual-stack
     * server shows as ::ffff:a.b.c.d) keeps its whole address, or every IPv4
     * visitor would share one counter.
     */
    private static function key(string $address): string
    {
        $packed = filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false ? @inet_pton($address) : false;
        $prefix = is_string($packed) ? substr($packed, 0, 8) : '';
        return self::PREFIX . md5($prefix !== '' && $prefix !== str_repeat("\0", 8) ? 'v6:' . bin2hex($prefix) : $address);
    }
}
