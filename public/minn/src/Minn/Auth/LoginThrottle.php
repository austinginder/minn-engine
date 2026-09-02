<?php

declare(strict_types=1);

namespace Minn\Auth;

use Minn\Db;

/**
 * Failed sign-ins per address, so a password guesser meets a wall: twenty
 * failures in fifteen minutes and the address waits out the window. The
 * counters live in the options table (autoload off), one row per address,
 * as "window start:count"; a successful sign-in clears the row.
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
        [$start, $count] = $this->read($address);
        if ($start + self::WINDOW <= $now) {
            [$start, $count] = [$now, 0];
        }
        $this->write($address, $start, $count + 1);
        if (random_int(1, 50) === 1) {
            $this->prune($now);
        }
    }

    /** Forgets an address's failures. */
    public function clear(string $address): void
    {
        $this->db->execute("DELETE FROM {$this->db->table('options')} WHERE option_name = ?", [self::key($address)]);
    }

    /** @return array{0: int, 1: int} window start and failure count */
    private function read(string $address): array
    {
        $value = $this->db->option(self::key($address));
        if ($value === null || !preg_match('/^(\d+):(\d+)$/', $value, $m)) {
            return [0, 0];
        }
        return [(int) $m[1], (int) $m[2]];
    }

    private function write(string $address, int $start, int $count): void
    {
        $table = $this->db->table('options');
        $value = "{$start}:{$count}";
        // One statement: two first failures arriving together must not race a read-then-insert into a duplicate-key error.
        $this->db->execute(
            "INSERT INTO {$table} (option_name, option_value, autoload) VALUES (?, ?, 'off') ON DUPLICATE KEY UPDATE option_value = VALUES(option_value)",
            [self::key($address), $value],
        );
    }

    /** Rows whose window has passed are dead weight; drop them now and then. */
    private function prune(int $now): void
    {
        $rows = $this->db->rows("SELECT option_name, option_value FROM {$this->db->table('options')} WHERE option_name LIKE ?", [self::PREFIX . '%']);
        foreach ($rows as $row) {
            if (preg_match('/^(\d+):/', (string) $row['option_value'], $m) && (int) $m[1] + self::WINDOW <= $now) {
                $this->db->execute("DELETE FROM {$this->db->table('options')} WHERE option_name = ?", [$row['option_name']]);
            }
        }
    }

    private static function key(string $address): string
    {
        return self::PREFIX . md5($address);
    }
}
