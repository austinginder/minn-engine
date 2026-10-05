<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Db;
use Minn\Support\Serialized;

/**
 * Options as plugin code sees them: PHP values, decoded from the stored
 * blob by the engine's own reader, cached for the request so a value written
 * and read again in one request keeps its PHP type (an int stays an int,
 * false stays false) exactly as the reference shows.
 */
final class Options
{
    /** @var array<string, mixed> */
    private array $cache = [];
    /** @var array<string, true> */
    private array $missing = [];

    /**
     * The options the engine keeps for itself: the symbol-gate cache a plugin
     * could otherwise rewrite to pass its own gate, the recovery strikes, the
     * cron lock, and the sign-in throttle rows. The facade refuses to write
     * them; the engine writes them through this class directly.
     */
    public const GUARDED = ['minn_runtime_symbols', 'minn_recovery_strikes', 'minn_cron_lock'];
    private const GUARDED_PREFIX = 'minn_login_throttle_';

    /** The autoload column values that load an option with every request, in the reference's order. */
    public const AUTOLOAD_VALUES = ['yes', 'on', 'auto-on', 'auto'];

    public function __construct(private readonly Db $db)
    {
    }

    /** Whether plugin code is refused a write to this option. */
    public static function guarded(string $name): bool
    {
        return in_array($name, self::GUARDED, true) || str_starts_with($name, self::GUARDED_PREFIX);
    }

    /** Every autoloaded option as stored. @return array<string, string> */
    public function autoloaded(): array
    {
        $out = [];
        $in = implode(', ', array_fill(0, count(self::AUTOLOAD_VALUES), '?'));
        foreach ($this->db->rows("SELECT option_name, option_value FROM {$this->db->table('options')} WHERE autoload IN ({$in})", self::AUTOLOAD_VALUES) as $row) {
            $out[(string) $row['option_name']] = (string) $row['option_value'];
        }
        return $out;
    }

    /** An option's value, decoded, or null when unset. */
    public function get(string $name): mixed
    {
        if (array_key_exists($name, $this->cache)) {
            return $this->cache[$name];
        }
        if (isset($this->missing[$name])) {
            return null;
        }
        $raw = $this->db->option($name);
        if ($raw === null) {
            $this->missing[$name] = true;
            return null;
        }
        return $this->cache[$name] = self::fromStorage($raw);
    }

    /** Whether an option exists. */
    public function exists(string $name): bool
    {
        return $this->get($name) !== null || (!isset($this->missing[$name]) && array_key_exists($name, $this->cache));
    }

    /** Adds an option only when it is unset. */
    public function add(string $name, mixed $value, string $autoload = 'auto'): bool
    {
        if (array_key_exists($name, $this->cache) || $this->db->option($name) !== null) {
            return false;
        }
        $value = $value ?? '';
        $written = $this->upsert($name, self::toStorage($value), $autoload);
        unset($this->missing[$name]);
        $this->cache[$name] = $value;
        return $written;
    }

    /**
     * Writes an option row whether or not one exists, as the reference does
     * for every add: two requests that both find a transient missing and
     * both add it must not fail on the unique name, so the second write lands
     * over the first. True when the row was inserted or changed, false when
     * the same value was already there.
     */
    public function upsert(string $name, string $stored, string $autoload): bool
    {
        $affected = $this->db->execute(
            "INSERT INTO {$this->db->table('options')} (option_name, option_value, autoload) VALUES (?, ?, ?)"
            . ' ON DUPLICATE KEY UPDATE option_value = VALUES(option_value), autoload = VALUES(autoload)',
            [$name, $stored, $autoload],
        );
        return $affected > 0;
    }

    /** False when the value is unchanged, as the reference reports. */
    public function update(string $name, mixed $value, ?string $autoload = null): bool
    {
        $value = $value ?? '';
        $old = $this->get($name);
        if ($old === null && !array_key_exists($name, $this->cache)) {
            return $this->add($name, $value, $autoload ?? 'auto');
        }
        if ($old === $value || self::toStorage($old) === self::toStorage($value)) {
            return false;
        }
        $this->db->execute("UPDATE {$this->db->table('options')} SET option_value = ? WHERE option_name = ?", [self::toStorage($value), $name]);
        $this->cache[$name] = $value;
        return true;
    }

    /** Flips the autoload column; false when the option is missing or already so. */
    public function markAutoload(string $name): bool
    {
        return $this->switchAutoload($name, 'on', ['on', 'yes']);
    }

    /** Stops an option loading on every request; false when it already did not, or is unset. */
    public function unmarkAutoload(string $name): bool
    {
        return $this->switchAutoload($name, 'off', ['off', 'no']);
    }

    /** @param list<string> $already the stored values that already mean $wanted */
    private function switchAutoload(string $name, string $wanted, array $already): bool
    {
        $current = $this->db->value("SELECT autoload FROM {$this->db->table('options')} WHERE option_name = ? LIMIT 1", [$name]);
        if ($current === null || in_array((string) $current, $already, true)) {
            return false;
        }
        $this->db->execute("UPDATE {$this->db->table('options')} SET autoload = ? WHERE option_name = ?", [$wanted, $name]);
        return true;
    }

    /** Removes an option. */
    public function delete(string $name): bool
    {
        $existed = $this->get($name) !== null;
        unset($this->cache[$name]);
        $this->missing[$name] = true;
        if (!$existed) {
            return false;
        }
        $this->db->execute("DELETE FROM {$this->db->table('options')} WHERE option_name = ?", [$name]);
        return true;
    }

    /**
     * The names of transients whose expiry has passed. The timeout row is the
     * one that knows, so the sweep reads those and hands back the bare names.
     *
     * @return list<string>
     */
    public function expiredTransientNames(int $now, string $prefix = '_transient_timeout_'): array
    {
        $rows = $this->db->rows(
            "SELECT option_name, option_value FROM {$this->db->table('options')} WHERE option_name LIKE ?",
            [$prefix . '%'],
        );
        $out = [];
        foreach ($rows as $row) {
            if ((int) $row['option_value'] < $now) {
                $out[] = substr((string) $row['option_name'], strlen($prefix));
            }
        }
        return $out;
    }

    /** Drops an option from the cache. */
    public function forget(string $name): void
    {
        unset($this->cache[$name], $this->missing[$name]);
    }

    /** What the reference stores: arrays and objects serialized, scalars as their string form. */
    public static function toStorage(mixed $value): string
    {
        if (is_array($value) || is_object($value)) {
            return Serialized::encode($value);
        }
        if ($value === false || $value === null) {
            return '';
        }
        return (string) $value;
    }

    /** A stored option value decoded the way the reference reads it. */
    public static function fromStorage(string $raw): mixed
    {
        if ($raw !== '' && preg_match('/^[aObis]:|^N;/', $raw)) {
            $decoded = Serialized::decode($raw, StoredObjects::reviver());
            if ($decoded !== Serialized::INVALID) {
                return $decoded;
            }
        }
        return $raw;
    }
}
