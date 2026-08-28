<?php

declare(strict_types=1);

namespace Minn\Content;

use Minn\Db;

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

    /** @param array<string, mixed> $columns */
    public function insert(array $columns): int
    {
        $names = implode(', ', array_keys($columns));
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $this->db->execute("INSERT INTO {$this->db->table('users')} ({$names}) VALUES ({$placeholders})", array_values($columns));
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
