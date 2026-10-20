<?php

declare(strict_types=1);

namespace Minn\Content;

use Minn\Auth\Password;
use Minn\Auth\Roles;
use Minn\Db;
use Minn\Runtime\Handoff;
use Minn\Runtime\MetaData;
use Minn\Runtime\Options;
use Minn\Support\Kses;
use Minn\Support\Slashes;

final readonly class Users
{
    public function __construct(private Db $db)
    {
    }

    /** @param array<string, mixed>|null $row */
    private static function record(?array $row): ?UserRecord
    {
        return $row === null ? null : UserRecord::fromRow($row);
    }

    /** A user's row read elsewhere (cache_users), kept so find() answers from it. @param array<string, mixed> $row */
    public static function keep(array $row): void
    {
        RowCache::put('users', (int) ($row['ID'] ?? 0), self::record($row));
    }

    /** The user with this id, or null. */
    public function find(int $id): ?UserRecord
    {
        return RowCache::remember('users', $id, fn (): ?UserRecord => self::record($this->db->row("SELECT * FROM {$this->db->table('users')} WHERE ID = ? LIMIT 1", [$id])));
    }

    /** The user with this login, or null. */
    public function findByLogin(string $login): ?UserRecord
    {
        return self::record($this->db->row("SELECT * FROM {$this->db->table('users')} WHERE user_login = ? LIMIT 1", [$login]));
    }

    /** The user with this email, or null. */
    public function findByEmail(string $email): ?UserRecord
    {
        return self::record($this->db->row("SELECT * FROM {$this->db->table('users')} WHERE user_email = ? LIMIT 1", [$email]));
    }

    /** The user with this nicename, or null. */
    public function findBySlug(string $nicename): ?UserRecord
    {
        return self::record($this->db->row("SELECT * FROM {$this->db->table('users')} WHERE user_nicename = ? LIMIT 1", [$nicename]));
    }

    /** The unique-nicename rule: sanitized login, -2, -3 on collision. */
    public function uniqueNicename(string $base, int $skipId = 0): string
    {
        $slug = Slug::sanitize($base);
        $try = $slug;
        $n = 1;
        while ($this->db->value("SELECT ID FROM {$this->db->table('users')} WHERE user_nicename = ? AND ID != ? LIMIT 1", [$try, $skipId]) !== null) {
            $n++;
            $try = "{$slug}-{$n}";
        }
        return $try;
    }

    /**
     * Inserts a user and the 14 default meta rows the reference writes.
     *
     * @param array{
     *   login: string,
     *   email: string,
     *   password: string,
     *   role: string,
     *   display_name: string,
     *   url?: string,
     *   nicename?: string,
     *   nickname?: string,
     *   first_name?: string,
     *   last_name?: string,
     *   description?: string,
     *   locale?: string,
     * } $fields
     */
    public function createAccount(array $fields): int
    {
        $id = $this->db->transaction(fn (): int => $this->writeAccount($fields));
        $this->refreshCount();
        return $id;
    }

    /**
     * The display name an account gets when none is given, as the reference
     * picks it: first and last name, whichever of the two there is, or the
     * login. The nickname plays no part.
     */
    public static function defaultDisplayName(string $first, string $last, string $login): string
    {
        $name = trim($first . ' ' . $last);
        return $name !== '' ? $name : $login;
    }

    /**
     * The user_count option, which the reference keeps current as accounts
     * come and go. A site without the row is left without it.
     */
    private function refreshCount(): void
    {
        $this->db->execute("UPDATE {$this->db->table('options')} SET option_value = ? WHERE option_name = 'user_count'", [(string) $this->count()]);
        \Minn\Runtime\Runtime::optionWritten('user_count');
    }

    /**
     * The account's row and the fourteen meta rows the reference gives a new
     * user, written as one unit by createAccount().
     *
     * @param array<string, mixed> $fields
     */
    private function writeAccount(array $fields): int
    {
        $id = $this->insertAccount($fields);
        $prefix = $this->db->prefix();
        $role = $fields['role'];
        $meta = self::profileMeta($fields) + [
            "{$prefix}capabilities" => Roles::serializeSingle($role),
            "{$prefix}user_level" => (string) Roles::level($role),
        ];
        foreach ($meta as $key => $value) {
            $this->setMeta($id, $key, $value);
        }
        return $id;
    }

    /** A new account's row alone: the caller adds its meta and role, and keeps the count. @param array<string, mixed> $fields */
    public function insertAccount(array $fields): int
    {
        $login = $fields['login'];
        return $this->insert([
            'user_login' => $login,
            'user_pass' => Password::hash($fields['password']),
            'user_nicename' => $fields['nicename'] ?? $this->uniqueNicename($login),
            'user_email' => $fields['email'],
            'user_url' => Kses::url($fields['url'] ?? ''),
            'user_registered' => gmdate('Y-m-d H:i:s'),
            'user_activation_key' => '',
            'user_status' => 0,
            'display_name' => $fields['display_name'] !== '' ? $fields['display_name'] : self::defaultDisplayName(Kses::text($fields['first_name'] ?? ''), Kses::text($fields['last_name'] ?? ''), $login),
        ]);
    }

    /**
     * The twelve profile meta rows the reference gives a new account, in its
     * order (the role's two follow).
     *
     * @param array<string, mixed> $fields
     * @return array<string, string>
     */
    public static function profileMeta(array $fields): array
    {
        return [
            'nickname' => Kses::text($fields['nickname'] ?? $fields['login']),
            'first_name' => Kses::text($fields['first_name'] ?? ''),
            'last_name' => Kses::text($fields['last_name'] ?? ''),
            'description' => Kses::comment($fields['description'] ?? ''),
            'rich_editing' => 'true',
            'syntax_highlighting' => 'true',
            'infinite_scrolling' => 'true',
            'comment_shortcuts' => 'false',
            'admin_color' => 'modern',
            'use_ssl' => '0',
            'show_admin_bar_front' => 'true',
            'locale' => (string) ($fields['locale'] ?? ''),
        ];
    }

    /** Recounts the accounts into user_count, quietly. */
    public function recount(): void
    {
        $this->refreshCount();
    }

    /** Removes the account's row alone: the caller removes its meta and keeps the count. */
    public function deleteRow(int $id): void
    {
        $this->db->execute("DELETE FROM {$this->db->table('users')} WHERE ID = ?", [$id]);
    }

    /**
     * Inserts a users row from column => value pairs and returns the new id.
     *
     * @param array<string, mixed> $columns
     */
    public function insert(array $columns): int
    {
        $names = implode(', ', array_keys($columns));
        $this->db->execute("INSERT INTO {$this->db->table('users')} ({$names}) VALUES (?)", [array_values($columns)]);
        return $this->db->insertId();
    }

    /**
     * Sets the given columns on one user, in one write as the reference
     * makes it (tests/tools/side-effects.php).
     *
     * @param array<string, mixed> $columns
     */
    public function update(int $id, array $columns): void
    {
        if ($columns === []) {
            return;
        }
        $set = implode(', ', array_map(static fn (string $column): string => "{$column} = ?", array_keys($columns)));
        $this->db->execute("UPDATE {$this->db->table('users')} SET {$set} WHERE ID = ?", [...array_values($columns), $id]);
    }

    /** Removes the user and their meta; their posts are reassigned or removed first by the caller. */
    public function delete(int $id): void
    {
        $this->db->execute("DELETE FROM {$this->db->table('usermeta')} WHERE user_id = ?", [$id]);
        $this->db->execute("DELETE FROM {$this->db->table('users')} WHERE ID = ?", [$id]);
        $this->refreshCount();
    }

    /** User ids in login order, optionally the first N. @return list<int> */
    public function ids(int $limit): array
    {
        return array_map(static fn (array $r) => (int) $r['ID'], $this->db->rows('SELECT ID FROM ' . $this->db->table('users') . ' ORDER BY user_login ASC' . ($limit > 0 ? ' LIMIT ' . $limit : '')));
    }

    /** Stores a new password hash and clears any pending reset key. */
    public function setPassword(int $userId, string $hash): void
    {
        $this->db->execute("UPDATE {$this->db->table('users')} SET user_pass = ?, user_activation_key = '' WHERE ID = ?", [$hash, $userId]);
    }

    /**
     * A pending password reset dropped: a good sign-in (wp_signon, the
     * sign-in page) ends it, so a link mailed earlier no longer opens the
     * reset form (probe sweep-1020b, login suite).
     */
    public function clearResetKey(int $userId): void
    {
        $this->db->execute("UPDATE {$this->db->table('users')} SET user_activation_key = '' WHERE ID = ? AND user_activation_key != ''", [$userId]);
    }

    /** How many users the site has. */
    public function count(): int
    {
        return (int) $this->db->value("SELECT COUNT(*) FROM {$this->db->table('users')}");
    }

    /**
     * The newest users registered since a site-local time, up to the limit.
     *
     * @return list<array> the newest registrations after a site-local timestamp
     */
    public function registeredAfter(string $since, int $limit): array
    {
        return UserRecord::fromRows($this->db->rows(
            "SELECT ID, display_name, user_registered FROM {$this->db->table('users')}
             WHERE user_registered > ? ORDER BY user_registered DESC LIMIT ?",
            [$since, $limit],
        ));
    }

    /** Removes one meta key from a user: through the meta API once the runtime is up (delete_user_meta's hooks), else directly. */
    public function deleteMeta(int $userId, string $key): void
    {
        if (Handoff::bound('cacheDelete')) {
            MetaData::delete('user', $userId, $key);
            return;
        }
        $this->db->execute("DELETE FROM {$this->db->table('usermeta')} WHERE user_id = ? AND meta_key = ?", [$userId, $key]);
    }

    /**
     * Every user with a capabilities row under the key given, as the old
     * get_users_of_blog listed them: id twice, login, display name, email
     * and the stored capabilities, in id order.
     *
     * @return list<array<string, string>>
     */
    public function withCapabilities(string $capabilitiesKey): array
    {
        return array_map(static fn (array $row): array => array_map('strval', $row), $this->db->rows(
            "SELECT m.user_id, m.user_id AS ID, u.user_login, u.display_name, u.user_email, m.meta_value FROM {$this->db->table('users')} u JOIN {$this->db->table('usermeta')} m ON u.ID = m.user_id WHERE m.meta_key = ? ORDER BY m.user_id",
            [$capabilitiesKey],
        ));
    }

    /** The ids of users whose capabilities row names none of the roles (a pattern of their names). @return list<string> */
    public function withoutRole(string $capabilitiesKey, string $rolePattern): array
    {
        return array_map(static fn (array $row): string => (string) $row['user_id'], $this->db->rows(
            "SELECT user_id FROM {$this->db->table('usermeta')} WHERE meta_key = ? AND meta_value NOT REGEXP ?",
            [$capabilitiesKey, $rolePattern],
        ));
    }

    /**
     * One usermeta value, raw: serialized blobs come back serialized. Once the
     * runtime is up it is read as get_user_meta reads it, through its filter
     * and the cache (probe user-meta-writes); before it, from the table.
     */
    public function meta(int $userId, string $key): ?string
    {
        if (Handoff::bound('cacheGet')) {
            $values = MetaData::raw('user', $userId, $key);
            return is_array($values) && $values !== [] ? Options::toStorage($values[0]) : null;
        }
        $value = $this->db->value(
            "SELECT meta_value FROM {$this->db->table('usermeta')} WHERE user_id = ? AND meta_key = ? LIMIT 1",
            [$userId, $key],
        );
        return $value === null ? null : (string) $value;
    }

    /**
     * Replaces one usermeta row. The (user_id, meta_key) pair carries no
     * unique index on the stock schema, so this is a delete plus insert
     * rather than an upsert. The value is the stored form: a string that
     * reads as serialized is revived on the way through the meta API, so a
     * caller with text from outside passes it through Options::toStorage.
     */
    public function setMeta(int $userId, string $key, string $value): void
    {
        // Once the runtime is up the value is written as update_user_meta writes it, its filters and actions heard
        // and the cache let go (probe user-meta-writes): the session store, application passwords and Minn Admin's
        // own settings write here. Before it, directly.
        if (Handoff::bound('cacheDelete')) {
            MetaData::update('user', $userId, $key, Slashes::add(Options::fromStorage($value)));
            return;
        }
        $table = $this->db->table('usermeta');
        $this->db->execute("DELETE FROM {$table} WHERE user_id = ? AND meta_key = ?", [$userId, $key]);
        $this->db->execute("INSERT INTO {$table} (user_id, meta_key, meta_value) VALUES (?, ?, ?)", [$userId, $key, $value]);
    }
}
