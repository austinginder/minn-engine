<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Db;

/** The four meta tables behind get_metadata and friends: reads by object, and the row-level writes the update and delete rules need. */
final readonly class Meta
{
    private const TABLES = ['post' => ['postmeta', 'post_id', 'meta_id'], 'user' => ['usermeta', 'user_id', 'umeta_id'], 'term' => ['termmeta', 'term_id', 'meta_id'], 'comment' => ['commentmeta', 'comment_id', 'meta_id']];

    public function __construct(private Db $db)
    {
    }

    /** The table, object column, and row id column of a meta type. @return array{0: string, 1: string, 2: string} */
    private function spec(string $type): array
    {
        if (isset(self::TABLES[$type])) {
            [$table, $column, $id] = self::TABLES[$type];
            return [$this->db->table($table), $column, $id];
        }
        return [(string) MetaTypes::table($type), "{$type}_id", 'meta_id'];
    }

    /** Whether an object type has a meta table. */
    public static function knows(string $type): bool
    {
        return isset(self::TABLES[$type]) || MetaTypes::table($type) !== null;
    }

    /**
     * The meta clauses hidden in flat query vars (meta_key, meta_value, the
     * compare and type variants), merged ahead of an explicit meta_query,
     * the reference's precedence for WP_Meta_Query::parse_query_vars.
     *
     * @param array<string, mixed> $queryVars
     * @return list<mixed>
     */
    public static function clausesFromQueryVars(array $queryVars): array
    {
        $clause = [];
        foreach (['key' => 'meta_key', 'compare' => 'meta_compare', 'type' => 'meta_type', 'compare_key' => 'meta_compare_key', 'type_key' => 'meta_type_key'] as $to => $from) {
            if (isset($queryVars[$from]) && $queryVars[$from] !== '') {
                $clause[$to] = $queryVars[$from];
            }
        }
        if (isset($queryVars['meta_value']) && (!is_array($queryVars['meta_value']) || $queryVars['meta_value'] !== [])) {
            $clause['value'] = $queryVars['meta_value'];
        }
        $clauses = [];
        if (isset($clause['key']) || isset($clause['value'])) {
            $clauses[] = $clause;
        }
        if (!empty($queryVars['meta_query']) && is_array($queryVars['meta_query'])) {
            $clauses = array_merge($clauses, $queryVars['meta_query']);
        }
        return $clauses;
    }

    /** Every row of an object's meta, values as stored, grouped by key in id order. @return array<string, list<string>> */
    public function all(string $type, int $objectId): array
    {
        [$table, $column, $id] = $this->spec($type);
        $out = [];
        foreach ($this->db->rows("SELECT meta_key, meta_value FROM {$table} WHERE {$column} = ? ORDER BY {$id} ASC", [$objectId]) as $row) {
            $out[(string) $row['meta_key']][] = (string) $row['meta_value'];
        }
        return $out;
    }

    /**
     * update_meta_cache: each object's meta as all() gives it, the cached
     * ones from the object cache and the rest read in one query and cached;
     * an object with none has an empty list.
     *
     * @param list<int> $objectIds
     * @return array<int, array<string, list<string>>>
     */
    public function prime(string $type, array $objectIds): array
    {
        $cache = [];
        $missing = [];
        foreach ($objectIds as $objectId) {
            $cached = \wp_cache_get($objectId, "{$type}_meta", false, $found);
            if ($found) {
                $cache[$objectId] = $cached;
            } else {
                $missing[$objectId] = [];
            }
        }
        if ($missing !== []) {
            [$table, $column, $id] = $this->spec($type);
            $in = implode(', ', array_fill(0, count($missing), '?'));
            foreach ($this->db->rows("SELECT {$column} AS object_id, meta_key, meta_value FROM {$table} WHERE {$column} IN ({$in}) ORDER BY {$id} ASC", array_keys($missing)) as $row) {
                $missing[(int) $row['object_id']][(string) $row['meta_key']][] = (string) $row['meta_value'];
            }
            foreach ($missing as $objectId => $meta) {
                \wp_cache_set($objectId, $meta, "{$type}_meta");
                $cache[$objectId] = $meta;
            }
        }
        return $cache;
    }

    /** Every row of an object's meta in id order, for removing them one by one. @return list<array{meta_id: int, meta_key: string, meta_value: string}> */
    public function rowsOf(string $type, int $objectId): array
    {
        [$table, $column, $id] = $this->spec($type);
        return array_map(
            static fn (array $r): array => ['meta_id' => (int) $r['meta_id'], 'meta_key' => (string) $r['meta_key'], 'meta_value' => (string) $r['meta_value']],
            $this->db->rows("SELECT {$id} AS meta_id, meta_key, meta_value FROM {$table} WHERE {$column} = ? ORDER BY {$id} ASC", [$objectId]),
        );
    }

    /** One meta row by its id, under the table's own column names, values as stored; null when there is none. @return array<string, string>|null */
    public function byId(string $type, int $metaId): ?array
    {
        [$table, $column, $id] = $this->spec($type);
        $row = $this->db->row("SELECT {$id}, {$column}, meta_key, meta_value FROM {$table} WHERE {$id} = ? LIMIT 1", [$metaId]);
        return $row === null ? null : array_map('strval', $row);
    }

    /** The column holding the object's id, and the one holding the row's: post_id and meta_id, user_id and umeta_id. @return array{0: string, 1: string} */
    public function columns(string $type): array
    {
        [, $column, $id] = $this->spec($type);
        return [$column, $id];
    }

    /** Sets one row's key and stored value. */
    public function rewrite(string $type, int $metaId, string $key, string $stored): void
    {
        [$table, , $id] = $this->spec($type);
        $this->db->execute("UPDATE {$table} SET meta_key = ?, meta_value = ? WHERE {$id} = ?", [$key, $stored, $metaId]);
    }

    /** Inserts a meta row and returns its id. */
    public function add(string $type, int $objectId, string $key, string $stored): int
    {
        [$table, $column] = $this->spec($type);
        $this->db->execute("INSERT INTO {$table} ({$column}, meta_key, meta_value) VALUES (?, ?, ?)", [$objectId, $key, $stored]);
        return $this->db->insertId();
    }

    /** The rows of one key on one object, in id order. @return list<array{meta_id: int, meta_value: string}> */
    public function matching(string $type, int $objectId, string $key): array
    {
        [$table, $column, $id] = $this->spec($type);
        return array_map(static fn (array $r) => ['meta_id' => (int) $r['meta_id'], 'meta_value' => (string) $r['meta_value']], $this->db->rows("SELECT {$id} AS meta_id, meta_value FROM {$table} WHERE {$column} = ? AND meta_key = ? ORDER BY {$id} ASC", [$objectId, $key]));
    }

    /** @param list<int> $ids */
    /**
     * Which of a key's rows an update touches: none when no previous value was
     * named and the first row already holds the value, all of them when no
     * previous value was named, otherwise only the rows holding it.
     *
     * @param list<array<string, mixed>> $rows
     * @return list<int>
     */
    public static function idsToUpdate(array $rows, string $stored, ?string $previous): array
    {
        if ($previous === null && $stored === (string) ($rows[0]['meta_value'] ?? '')) {
            return [];
        }
        $ids = [];
        foreach ($rows as $row) {
            if ($previous === null || $previous === (string) $row['meta_value']) {
                $ids[] = (int) $row['meta_id'];
            }
        }
        return $ids;
    }

    /** Sets the value of the given meta rows. */
    public function updateRows(string $type, array $ids, string $stored): void
    {
        if ($ids === []) {
            return;
        }
        [$table, , $id] = $this->spec($type);
        $this->db->execute("UPDATE {$table} SET meta_value = ? WHERE {$id} IN (" . implode(',', array_map('intval', $ids)) . ')', [$stored]);
    }

    /** The rows a delete would take: by key, for one object or all, optionally only a stored value. @return list<array{meta_id: int, object_id: int}> */
    public function find(string $type, ?int $objectId, string $key, ?string $stored): array
    {
        [$table, $column, $id] = $this->spec($type);
        $sql = "SELECT {$id} AS meta_id, {$column} AS object_id FROM {$table} WHERE meta_key = ?";
        $params = [$key];
        if ($objectId !== null) {
            $sql .= " AND {$column} = ?";
            $params[] = $objectId;
        }
        if ($stored !== null) {
            $sql .= ' AND meta_value = ?';
            $params[] = $stored;
        }
        return array_map(static fn (array $r) => ['meta_id' => (int) $r['meta_id'], 'object_id' => (int) $r['object_id']], $this->db->rows($sql, $params));
    }

    /**
     * Deletes the given meta rows.
     *
     * @param list<int> $ids
     */
    public function deleteRows(string $type, array $ids): void
    {
        if ($ids === []) {
            return;
        }
        [$table, , $id] = $this->spec($type);
        $this->db->execute("DELETE FROM {$table} WHERE {$id} IN (" . implode(',', array_map('intval', $ids)) . ')');
    }
}
