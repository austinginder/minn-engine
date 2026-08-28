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
