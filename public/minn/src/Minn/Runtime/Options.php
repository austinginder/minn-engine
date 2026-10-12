<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Db;
use Minn\Support\Serialized;

/**
 * Options as plugin code sees them, kept where the reference keeps them:
 * wp_cache's options group (OptionsGroup) holds the autoloaded list, the
 * names known unset, and a key per option not autoloaded, so a plugin that
 * reads, writes or drops those keys is heard. get_option() and
 * wp_load_alloptions() pass the list through pre_wp_load_alloptions and
 * alloptions. A value written this request is held as it was written (an
 * int stays an int, true stays true), arrays and objects as stored. Each
 * stored text is decoded once while it stays the same, except one holding
 * an object, decoded afresh on every read as the reference does: a plugin
 * that changes the object it was handed and saves it must find the stored
 * copy still different, or its change is never written.
 */
final class Options
{
    private const ALL = 'alloptions';
    private const MISSING = 'notoptions';

    private readonly OptionsGroup $group;
    /** @var array<string, array{0: mixed, 1: mixed}> each option's held value and what it decodes to */
    private array $decoded = [];

    /**
     * The options the engine keeps for itself: the symbol-gate cache a plugin
     * could otherwise rewrite to pass its own gate, the recovery strikes, the
     * cron lock, and the sign-in throttle rows. The facade refuses to write
     * them; the engine writes them through this class directly.
     */
    public const GUARDED = ['minn_runtime_symbols', 'minn_recovery_strikes', 'minn_cron_lock'];
    private const GUARDED_PREFIX = 'minn_login_throttle_';

    /**
     * The engine's own caches, read on demand and written with autoload off
     * (Site::setOption). Rows an older engine wrote autoloaded are set right
     * the first time a loaded list holds them.
     */
    public const ON_DEMAND = ['minn_admin_changelog', 'minn_changelog', 'minn_release', 'minn_plugin_info', 'minn_updates', '_transient_minn_admin_uploads_size', '_transient_timeout_minn_admin_uploads_size', 'minn_admin_uploads_size_lock'];

    /** The autoload column values that load an option with every request, in the reference's order. */
    public const AUTOLOAD_VALUES = ['yes', 'on', 'auto-on', 'auto'];

    /**
     * Options not autoloaded that the engine itself reads on every request
     * (the symbol survey, recovery's paused plugins, the site language, the
     * HTTPS migration flag): read together as a request begins, as one read
     * rather than one each.
     */
    public const EVERY_REQUEST = ['minn_runtime_symbols', Recovery::PLUGINS_OPTION, 'WPLANG', 'https_migration_required'];

    public function __construct(private readonly Db $db)
    {
        $this->group = new OptionsGroup();
    }

    /** Whether this store reads through the given connection. */
    public function isFor(Db $db): bool
    {
        return $this->db === $db;
    }

    /** Whether plugin code is refused a write to this option. */
    public static function guarded(string $name): bool
    {
        return in_array($name, self::GUARDED, true) || str_starts_with($name, self::GUARDED_PREFIX);
    }

    /**
     * The autoloaded list as wp_load_alloptions() gives it:
     * pre_wp_load_alloptions may answer first (told whether the cache was
     * forced), and then alloptions is not heard; otherwise the list the
     * group holds, through alloptions, which may add or change entries
     * without the group keeping them.
     */
    public function autoloaded(bool $force = false): mixed
    {
        $pre = \apply_filters('pre_wp_load_alloptions', null, $force);
        if ($pre !== null) {
            return $pre;
        }
        return \apply_filters('alloptions', $this->listed());
    }

    /**
     * The autoloaded options, kept in the group under alloptions: read in one
     * query when the group has no list, each as stored except one written
     * since. @return array<string, mixed>
     */
    private function listed(): array
    {
        $list = $this->group->get(self::ALL, $found);
        if ($found && is_array($list)) {
            return $list;
        }
        $list = [];
        $in = implode(', ', array_fill(0, count(self::AUTOLOAD_VALUES), '?'));
        foreach ($this->db->rows("SELECT option_name, option_value FROM {$this->db->table('options')} WHERE autoload IN ({$in})", self::AUTOLOAD_VALUES) as $row) {
            $list[(string) $row['option_name']] = (string) $row['option_value'];
        }
        $stray = array_intersect_key($list, array_flip(self::ON_DEMAND));
        if ($stray !== []) {
            $names = array_map('strval', array_keys($stray));
            $this->db->execute("UPDATE {$this->db->table('options')} SET autoload = 'off' WHERE option_name IN (" . implode(', ', array_fill(0, count($names), '?')) . ')', $names);
            $list = array_diff_key($list, $stray);
        }
        $this->group->set(self::ALL, $list);
        return $list;
    }

    /** @return array<string, true> the names a read found unset, kept in the group under notoptions */
    private function missing(): array
    {
        $missing = $this->group->get(self::MISSING, $found);
        if ($found && is_array($missing)) {
            return $missing;
        }
        $this->group->set(self::MISSING, []);
        return [];
    }

    /**
     * An option's held value, or null when it is unset: from the list given,
     * else not at all when it is known unset, else its own key, else its row
     * (kept under its own key, or noted unset).
     *
     * @param array<string, mixed> $list
     */
    private function lookup(string $name, array $list): mixed
    {
        if (isset($list[$name])) {
            return $list[$name];
        }
        $missing = $this->missing();
        if (isset($missing[$name])) {
            return null;
        }
        // A key holding false counts as nothing kept, as on the reference.
        $value = $this->group->get($name, $found);
        if ($found && $value !== false) {
            return $value;
        }
        $value = $this->db->storedOption($name);
        if ($value === null) {
            $missing[$name] = true;
            $this->group->set(self::MISSING, $missing);
            return null;
        }
        $this->group->set($name, $value);
        return $value;
    }

    /** A held value decoded the way the reference reads it, once while it stays the same (never one holding an object). */
    private function decode(string $name, mixed $held): mixed
    {
        if (!is_string($held)) {
            return $held;
        }
        if (isset($this->decoded[$name]) && $this->decoded[$name][0] === $held) {
            return $this->decoded[$name][1];
        }
        $value = self::fromStorage($held);
        if (!self::holdsObject($held)) {
            $this->decoded[$name] = [$held, $value];
        }
        return $value;
    }

    /**
     * An option as get_option() reads it, through the filters plugins hook:
     * pre_option_{name}, then pre_option, may answer first; then the
     * autoloaded list is read through its filters on every read; an unset
     * option is its default (false when none is given) through
     * default_option_{name}, which is told whether one was given; a set one
     * goes through option_{name}.
     */
    public function filtered(string $name, mixed ...$default): mixed
    {
        $name = trim($name);
        $fallback = $default === [] ? false : $default[0];
        if ($name === '') {
            return false;
        }
        $pre = \apply_filters("pre_option_{$name}", false, $name, $fallback);
        $pre = \apply_filters('pre_option', $pre, $name, $fallback);
        if ($pre !== false) {
            return $pre;
        }
        // While installing, each read is the row as stored, past the list and the group.
        if (Runtime::installing()) {
            $held = $this->db->storedOption($name);
        } else {
            $list = $this->autoloaded();
            $held = $this->lookup($name, is_array($list) ? $list : []);
        }
        if ($held === null) {
            return \apply_filters("default_option_{$name}", $fallback, $name, $default !== []);
        }
        return \apply_filters("option_{$name}", $this->decode($name, $held), $name);
    }

    /**
     * An option's stored text, as Db::option() gives it, or null when it is
     * unset: from the list or the group, a value written this request in
     * its stored form (true as "1", a list serialized).
     */
    public function raw(string $name): ?string
    {
        $held = Runtime::installing() ? $this->db->storedOption($name) : $this->lookup($name, $this->listed());
        return $held === null ? null : (is_string($held) ? $held : self::toStorage($held));
    }

    /** An option's value, decoded, or null when unset; home and siteurl answer to wp-config.php's constants. */
    public function get(string $name): mixed
    {
        $configured = Db::configured($name);
        if ($configured !== null) {
            return $configured;
        }
        $held = Runtime::installing() ? $this->db->storedOption($name) : $this->lookup($name, $this->listed());
        return $held === null ? null : $this->decode($name, $held);
    }

    /**
     * Reads several options at once (wp_prime_option_caches): the ones not
     * held yet in one query, each kept under its own key or noted unset, as
     * a read of each one alone would leave them.
     *
     * @param list<string> $names
     */
    public function prime(array $names): void
    {
        if (Runtime::installing()) {
            return;
        }
        $list = $this->listed();
        $missing = $this->missing();
        $wanted = [];
        foreach (array_unique(array_map('strval', $names)) as $name) {
            if ($name === '' || isset($list[$name]) || isset($missing[$name])) {
                continue;
            }
            $held = $this->group->get($name, $found);
            if (!$found || $held === false) {
                $wanted[] = $name;
            }
        }
        if ($wanted === []) {
            return;
        }
        $rows = $this->db->rows("SELECT option_name, option_value FROM {$this->db->table('options')} WHERE option_name IN (" . implode(', ', array_fill(0, count($wanted), '?')) . ')', $wanted);
        $read = array_column($rows, 'option_value', 'option_name');
        foreach ($wanted as $name) {
            if (isset($read[$name])) {
                $this->group->set($name, (string) $read[$name]);
            } else {
                $missing[$name] = true;
            }
        }
        $this->group->set(self::MISSING, $missing);
    }

    /** Whether an option exists. */
    public function exists(string $name): bool
    {
        return $this->get($name) !== null;
    }

    /** Whether a read this request already found the option unset (the reference's notoptions). */
    public function knownMissing(string $name): bool
    {
        return isset($this->missing()[$name]);
    }

    /**
     * Writes a new option. add_option has already decided it is new (its
     * value is still the default); a row that is there anyway is written
     * over, as the reference's add does, and false comes back when it
     * already held this value. An autoloaded one joins the list.
     */
    public function add(string $name, mixed $value, string $autoload = 'auto'): bool
    {
        $value = $value ?? '';
        $written = $this->upsert($name, self::toStorage($value), $autoload);
        $held = $this->remember($name, $value);
        // While installing, writes leave the group as it was (a later read may find it stale), as on the reference.
        if (Runtime::installing()) {
            return $written;
        }
        if (in_array($autoload, self::AUTOLOAD_VALUES, true)) {
            $list = $this->listed();
            $list[$name] = $held;
            $this->group->set(self::ALL, $list);
        } else {
            $this->group->set($name, $held);
        }
        $this->found($name);
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
        if ($old === null) {
            return $this->add($name, $value, $autoload ?? 'auto');
        }
        if ($old === $value || self::toStorage($old) === self::toStorage($value)) {
            return false;
        }
        $this->db->execute("UPDATE {$this->db->table('options')} SET option_value = ? WHERE option_name = ?", [self::toStorage($value), $name]);
        $held = $this->remember($name, $value);
        if (Runtime::installing()) {
            return true;
        }
        $list = $this->listed();
        if (array_key_exists($name, $list)) {
            $list[$name] = $held;
            $this->group->set(self::ALL, $list);
        } else {
            $this->group->set($name, $held);
        }
        $this->found($name);
        return true;
    }

    /**
     * What the group holds for a value just written: scalars as given,
     * arrays and objects as stored. Its decoded form is the value itself,
     * so a string that only looks serialized reads back as that string.
     */
    private function remember(string $name, mixed $value): mixed
    {
        $held = is_array($value) || is_object($value) || (is_string($value) && Serialized::looksSerialized($value)) ? self::toStorage($value) : $value;
        unset($this->decoded[$name]);
        if (!is_object($value) && !(is_array($value) && self::holdsObject($held))) {
            $this->decoded[$name] = [$held, $value];
        }
        return $held;
    }

    /** Takes a name off the list of those known unset. */
    private function found(string $name): void
    {
        $missing = $this->missing();
        if (isset($missing[$name])) {
            unset($missing[$name]);
            $this->group->set(self::MISSING, $missing);
        }
    }

    /** Whether a stored value has an object record in it (a string that only mentions one decodes afresh too, which is harmless). */
    private static function holdsObject(string $stored): bool
    {
        return preg_match('/(?:^|[;{}])O:\d+:"/', $stored) === 1;
    }

    /** Flips the autoload column; false when the option is missing or already so. The list is read again, the option's own key dropped. */
    public function markAutoload(string $name): bool
    {
        if (!$this->switchAutoload($name, 'on', ['on', 'yes'])) {
            return false;
        }
        $this->group->delete(self::ALL);
        $this->group->delete($name);
        return true;
    }

    /** Stops an option loading on every request; false when it already did not, or is unset. It leaves the list. */
    public function unmarkAutoload(string $name): bool
    {
        if (!$this->switchAutoload($name, 'off', ['off', 'no'])) {
            return false;
        }
        $list = $this->listed();
        if (array_key_exists($name, $list)) {
            unset($list[$name]);
            $this->group->set(self::ALL, $list);
        }
        return true;
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

    /** Removes an option: out of the list or its own key, and known unset. */
    public function delete(string $name): bool
    {
        if ($this->get($name) === null) {
            return false;
        }
        $this->db->execute("DELETE FROM {$this->db->table('options')} WHERE option_name = ?", [$name]);
        unset($this->decoded[$name]);
        if (Runtime::installing()) {
            return true;
        }
        $list = $this->listed();
        if (array_key_exists($name, $list)) {
            unset($list[$name]);
            $this->group->set(self::ALL, $list);
        } else {
            $this->group->delete($name);
        }
        $missing = $this->missing();
        $missing[$name] = true;
        $this->group->set(self::MISSING, $missing);
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

    /**
     * Brings the group back in step with an option the engine wrote behind
     * the store (its own rows, the CLI): the row is read again, into the list
     * when it autoloads, and nothing else written this request is lost.
     */
    public function forget(string $name): void
    {
        unset($this->decoded[$name]);
        $this->group->delete($name);
        $row = $this->db->row("SELECT option_value, autoload FROM {$this->db->table('options')} WHERE option_name = ? LIMIT 1", [$name]);
        $list = $this->group->get(self::ALL, $found);
        if ($found && is_array($list)) {
            unset($list[$name]);
            if ($row !== null && in_array((string) $row['autoload'], self::AUTOLOAD_VALUES, true)) {
                $list[$name] = (string) $row['option_value'];
            }
            $this->group->set(self::ALL, $list);
        }
        $missing = $this->group->get(self::MISSING, $found);
        if ($found && is_array($missing) && isset($missing[$name])) {
            unset($missing[$name]);
            $this->group->set(self::MISSING, $missing);
        }
    }

    /**
     * What the reference stores (maybe_serialize): arrays and objects
     * serialized, a string that looks serialized serialized again so it
     * reads back as that string, other scalars as their string form.
     */
    public static function toStorage(mixed $value): string
    {
        if (is_array($value) || is_object($value) || (is_string($value) && Serialized::looksSerialized($value))) {
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
