<?php

declare(strict_types=1);

namespace Minn\Cli;

use Minn\Support\Serialized;
use WP_CLI;

/**
 * Options, read and written straight to the options table. Serialized
 * values are decoded by the engine's own reader, and arrays written back
 * in the stored form.
 */
final class OptionCommand
{
    /**
     * Gets the value for an option.
     *
     * ## OPTIONS
     *
     * <key>
     * : Key for the option.
     *
     * [--format=<format>]
     * : Get value in a particular format.
     * ---
     * default: var_export
     * options:
     *   - var_export
     *   - json
     *   - yaml
     * ---
     *
     * @when before_wp_load
     */
    public function get(array $args, array $assocArgs): void
    {
        [$key] = $args;
        $stored = Runtime::boot()->site->option($key);
        if ($stored === null) {
            WP_CLI::error("Could not get '{$key}' option. Does it exist?");
        }
        WP_CLI::print_value(self::decode($stored), $assocArgs);
    }

    /**
     * Adds a new option value.
     *
     * ## OPTIONS
     *
     * <key>
     * : The name of the option to add.
     *
     * [<value>]
     * : The value of the option to add. If omitted, the value is read from STDIN.
     *
     * [--format=<format>]
     * : The serialization format for the value.
     * ---
     * default: plaintext
     * options:
     *   - plaintext
     *   - json
     * ---
     *
     * [--autoload=<autoload>]
     * : Should this option be automatically loaded.
     * ---
     * options:
     *   - 'on'
     *   - 'off'
     * ---
     *
     * @when before_wp_load
     */
    public function add(array $args, array $assocArgs): void
    {
        $runtime = Runtime::boot();
        $key = $args[0];
        $value = self::readValue($args, $assocArgs);
        if ($runtime->site->option($key) !== null) {
            WP_CLI::error("Could not add option '{$key}'. Does it already exist?");
        }
        $autoload = ($assocArgs['autoload'] ?? 'on') === 'off' ? 'off' : 'on';
        $runtime->db->execute(
            "INSERT INTO {$runtime->db->table('options')} (option_name, option_value, autoload) VALUES (?, ?, ?)",
            [$key, self::encode($value), $autoload],
        );
        WP_CLI::success("Added '{$key}' option.");
    }

    /**
     * Updates an option value.
     *
     * ## OPTIONS
     *
     * <key>
     * : The name of the option to update.
     *
     * [<value>]
     * : The new value. If omitted, the value is read from STDIN.
     *
     * [--autoload=<autoload>]
     * : Requires WP 4.2. Should this option be automatically loaded.
     * ---
     * options:
     *   - 'on'
     *   - 'off'
     * ---
     *
     * [--format=<format>]
     * : The serialization format for the value.
     * ---
     * default: plaintext
     * options:
     *   - plaintext
     *   - json
     * ---
     *
     * @when before_wp_load
     */
    public function update(array $args, array $assocArgs): void
    {
        $runtime = Runtime::boot();
        $key = $args[0];
        $value = self::readValue($args, $assocArgs);
        $encoded = self::encode($value);
        $stored = $runtime->site->option($key);
        if ($stored !== null && $stored === $encoded && !isset($assocArgs['autoload'])) {
            WP_CLI::success("Value passed for '{$key}' option is unchanged.");
            return;
        }
        if ($stored === null) {
            // A first write through update autoloads on demand ("auto"), as the reference does.
            $autoload = ($assocArgs['autoload'] ?? 'auto') === 'off' ? 'off' : (($assocArgs['autoload'] ?? '') === 'on' ? 'on' : 'auto');
            $runtime->db->execute(
                "INSERT INTO {$runtime->db->table('options')} (option_name, option_value, autoload) VALUES (?, ?, ?)",
                [$key, $encoded, $autoload],
            );
        } elseif (isset($assocArgs['autoload'])) {
            $runtime->db->execute(
                "UPDATE {$runtime->db->table('options')} SET option_value = ?, autoload = ? WHERE option_name = ?",
                [$encoded, $assocArgs['autoload'] === 'off' ? 'off' : 'on', $key],
            );
        } else {
            $runtime->site->setOption($key, $encoded);
        }
        WP_CLI::success("Updated '{$key}' option.");
    }

    /**
     * Deletes an option.
     *
     * ## OPTIONS
     *
     * <key>...
     * : Key for the option.
     *
     * @when before_wp_load
     */
    public function delete(array $args, array $assocArgs): void
    {
        $runtime = Runtime::boot();
        foreach ($args as $key) {
            $deleted = $runtime->db->execute("DELETE FROM {$runtime->db->table('options')} WHERE option_name = ?", [$key]);
            if ($deleted > 0) {
                WP_CLI::success("Deleted '{$key}' option.");
            } else {
                WP_CLI::warning("Could not delete '{$key}' option. Does it exist?");
            }
        }
    }

    /** The value argument, or STDIN when omitted; JSON decoded when asked. */
    private static function readValue(array $args, array $assocArgs): mixed
    {
        $raw = $args[1] ?? null;
        if ($raw === null) {
            $raw = rtrim((string) stream_get_contents(STDIN), "\n");
        }
        if (($assocArgs['format'] ?? 'plaintext') === 'json') {
            $decoded = json_decode($raw, true);
            if ($decoded === null && trim($raw) !== 'null') {
                WP_CLI::error('Invalid JSON: ' . $raw);
            }
            return $decoded;
        }
        return $raw;
    }

    /** A stored value as PHP data: serialized blobs decoded, anything else as the string it is. */
    private static function decode(string $stored): mixed
    {
        $decoded = Serialized::decode($stored);
        return $decoded === Serialized::INVALID ? $stored : $decoded;
    }

    private static function encode(mixed $value): string
    {
        return is_array($value) || is_bool($value) || is_int($value) || is_float($value) || $value === null
            ? (is_string($value) ? $value : serialize($value))
            : (string) $value;
    }
}
