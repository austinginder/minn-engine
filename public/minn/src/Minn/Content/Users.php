<?php

declare(strict_types=1);

namespace Minn\Content;

use Minn\Auth\Password;
use Minn\Auth\Roles;
use Minn\Db;
use Minn\Support\Kses;

final readonly class Users
{
    public function __construct(private Db $db)
    {
    }

    public function find(int $id): ?array
    {
        return $this->db->row("SELECT * FROM {$this->db->table('users')} WHERE ID = ? LIMIT 1", [$id]);
    }

    public function findByLogin(string $login): ?array
    {
        return $this->db->row("SELECT * FROM {$this->db->table('users')} WHERE user_login = ? LIMIT 1", [$login]);
    }

    public function findByEmail(string $email): ?array
    {
        return $this->db->row("SELECT * FROM {$this->db->table('users')} WHERE user_email = ? LIMIT 1", [$email]);
    }

    public function findBySlug(string $nicename): ?array
    {
        return $this->db->row("SELECT * FROM {$this->db->table('users')} WHERE user_nicename = ? LIMIT 1", [$nicename]);
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
        $login = $fields['login'];
        $id = $this->insert([
            'user_login' => $login,
            'user_pass' => Password::hash($fields['password']),
            'user_nicename' => $fields['nicename'] ?? $this->uniqueNicename($login),
            'user_email' => $fields['email'],
            'user_url' => Kses::url($fields['url'] ?? ''),
            'user_registered' => gmdate('Y-m-d H:i:s'),
            'user_activation_key' => '',
            'user_status' => 0,
            'display_name' => $fields['display_name'] !== '' ? $fields['display_name'] : $login,
        ]);
        $prefix = $this->db->prefix();
        $role = $fields['role'];
        $meta = [
            'nickname' => Kses::text($fields['nickname'] ?? $login),
            'first_name' => Kses::text($fields['first_name'] ?? ''),
            'last_name' => Kses::text($fields['last_name'] ?? ''),
            'description' => Kses::filter($fields['description'] ?? '', Kses::COMMENT),
            'rich_editing' => 'true',
            'syntax_highlighting' => 'true',
            'infinite_scrolling' => 'true',
            'comment_shortcuts' => 'false',
            'admin_color' => 'modern',
            'use_ssl' => '0',
            'show_admin_bar_front' => 'true',
            'locale' => (string) ($fields['locale'] ?? ''),
            "{$prefix}capabilities" => Roles::serializeSingle($role),
            "{$prefix}user_level" => (string) Roles::level($role),
        ];
        foreach ($meta as $key => $value) {
            $this->setMeta($id, $key, $value);
        }
        return $id;
    }

    /** @param array<string, mixed> $columns */
    public function insert(array $columns): int
    {
        $names = implode(', ', array_keys($columns));
        $this->db->execute("INSERT INTO {$this->db->table('users')} ({$names}) VALUES (?)", [array_values($columns)]);
        return $this->db->insertId();
    }

    /** @param array<string, mixed> $columns */
    public function update(int $id, array $columns): void
    {
        foreach ($columns as $column => $value) {
            $this->db->execute("UPDATE {$this->db->table('users')} SET {$column} = ? WHERE ID = ?", [$value, $id]);
        }
    }

    /** Removes the user and their meta; their posts are reassigned or removed first by the caller. */
    public function delete(int $id): void
    {
        $this->db->execute("DELETE FROM {$this->db->table('usermeta')} WHERE user_id = ?", [$id]);
        $this->db->execute("DELETE FROM {$this->db->table('users')} WHERE ID = ?", [$id]);
    }

    /** User ids in login order, optionally the first N. @return list<int> */
    public function ids(int $limit): array
    {
        return array_map(static fn (array $r) => (int) $r['ID'], $this->db->rows('SELECT ID FROM ' . $this->db->table('users') . ' ORDER BY user_login ASC' . ($limit > 0 ? ' LIMIT ' . $limit : '')));
    }

    public function deleteAllMeta(int $userId): void
    {
        $this->db->execute("DELETE FROM {$this->db->table('usermeta')} WHERE user_id = ?", [$userId]);
    }

    /** Stores a new password hash and clears any pending reset key. */
    public function setPassword(int $userId, string $hash): void
    {
        $this->db->execute("UPDATE {$this->db->table('users')} SET user_pass = ?, user_activation_key = '' WHERE ID = ?", [$hash, $userId]);
    }

    public function count(): int
    {
        return (int) $this->db->value("SELECT COUNT(*) FROM {$this->db->table('users')}");
    }

    /** @return list<array> the newest registrations after a site-local timestamp */
    public function registeredAfter(string $since, int $limit): array
    {
        return $this->db->rows(
            "SELECT ID, display_name, user_registered FROM {$this->db->table('users')}
             WHERE user_registered > ? ORDER BY user_registered DESC LIMIT ?",
            [$since, $limit],
        );
    }

    public function deleteMeta(int $userId, string $key): void
    {
        $this->db->execute("DELETE FROM {$this->db->table('usermeta')} WHERE user_id = ? AND meta_key = ?", [$userId, $key]);
    }

    /** One usermeta value, raw. Serialized blobs come back as stored. */
    public function meta(int $userId, string $key): ?string
    {
        $value = $this->db->value(
            "SELECT meta_value FROM {$this->db->table('usermeta')} WHERE user_id = ? AND meta_key = ? LIMIT 1",
            [$userId, $key],
        );
        return $value === null ? null : (string) $value;
    }

    /**
     * Replaces one usermeta row. The (user_id, meta_key) pair carries no
     * unique index on the stock schema, so this is a delete plus insert
     * rather than an upsert.
     */
    public function setMeta(int $userId, string $key, string $value): void
    {
        $table = $this->db->table('usermeta');
        $this->db->execute("DELETE FROM {$table} WHERE user_id = ? AND meta_key = ?", [$userId, $key]);
        $this->db->execute("INSERT INTO {$table} (user_id, meta_key, meta_value) VALUES (?, ?, ?)", [$userId, $key, $value]);
    }
}
