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

    public function __construct(private readonly Db $db)
    {
    }

    /** The stored value, or null when the option does not exist. */
    /** Every autoloaded option as stored. @return array<string, string> */
    public function autoloaded(): array
    {
        $out = [];
        foreach ($this->db->rows("SELECT option_name, option_value FROM {$this->db->table('options')} WHERE autoload IN ('yes', 'on', 'auto', 'auto-on')") as $row) {
            $out[(string) $row['option_name']] = (string) $row['option_value'];
        }
        return $out;
    }

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

    public function exists(string $name): bool
    {
        return $this->get($name) !== null || (!isset($this->missing[$name]) && array_key_exists($name, $this->cache));
    }

    public function add(string $name, mixed $value, string $autoload = 'auto'): bool
    {
        if (array_key_exists($name, $this->cache) || $this->db->option($name) !== null) {
            return false;
        }
        $value = $value ?? '';
        $this->db->execute("INSERT INTO {$this->db->table('options')} (option_name, option_value, autoload) VALUES (?, ?, ?)", [$name, self::toStorage($value), $autoload]);
        unset($this->missing[$name]);
        $this->cache[$name] = $value;
        return true;
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
    public function setAutoload(string $name, bool $on): bool
    {
        $current = $this->db->value("SELECT autoload FROM {$this->db->table('options')} WHERE option_name = ? LIMIT 1", [$name]);
        $wanted = $on ? 'on' : 'off';
        if ($current === null || in_array((string) $current, $on ? ['on', 'yes'] : ['off', 'no'], true)) {
            return false;
        }
        $this->db->execute("UPDATE {$this->db->table('options')} SET autoload = ? WHERE option_name = ?", [$wanted, $name]);
        return true;
    }

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

    public function forget(string $name): void
    {
        unset($this->cache[$name], $this->missing[$name]);
    }

    /** What the reference stores: arrays serialized, scalars as their string form. */
    public static function toStorage(mixed $value): string
    {
        if (is_array($value) || is_object($value)) {
            return Serialized::encode(is_object($value) ? (array) $value : $value);
        }
        if ($value === false || $value === null) {
            return '';
        }
        return (string) $value;
    }

    public static function fromStorage(string $raw): mixed
    {
        if ($raw !== '' && preg_match('/^[aObis]:|^N;/', $raw)) {
            $decoded = Serialized::decode($raw);
            if ($decoded !== Serialized::INVALID) {
                return $decoded;
            }
        }
        return $raw;
    }
}
