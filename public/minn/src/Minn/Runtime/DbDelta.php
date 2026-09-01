<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Db;

/**
 * dbDelta as the reference does it: a CREATE TABLE statement creates the
 * table when it is missing, otherwise the columns and keys the statement
 * has and the table lacks are added. Nothing is ever dropped or altered.
 */
final readonly class DbDelta
{
    public function __construct(private Db $db)
    {
    }

    /** The CREATE TABLE statements in a batch, keyed by table name. @param list<string> $queries @return array<string, string> */
    public static function creates(array $queries): array
    {
        $creates = [];
        foreach ($queries as $query) {
            if (preg_match('|^\s*CREATE TABLE (?:IF NOT EXISTS\s+)?([^\s(]+)|i', $query, $m)) {
                $creates[trim($m[1], '`')] = $query;
            }
        }
        return $creates;
    }

    /**
     * Applies CREATE TABLE statements as dbDelta does; the statements run.
     *
     * @param array<string, string> $creates @return array<string, string> what was (or would be) done, by table or table.column
     */
    public function apply(array $creates, bool $execute): array
    {
        $done = [];
        $tables = $this->tables();
        foreach ($creates as $table => $query) {
            if (!in_array($table, $tables, true)) {
                if ($execute) {
                    $this->db->execute($query);
                }
                $done[$table] = "Created table {$table}";
                continue;
            }
            if (!preg_match('|\((.*)\)|s', $query, $body)) {
                continue;
            }
            [$columns, $indices] = self::definitions($body[1]);
            $existing = array_map('strtolower', array_keys($this->columns($table)));
            foreach ($columns as $name => $definition) {
                if (!in_array($name, $existing, true)) {
                    if ($execute) {
                        $this->db->execute("ALTER TABLE {$table} ADD COLUMN {$definition}");
                    }
                    $done[$table . '.' . $name] = "Added column {$table}.{$name}";
                }
            }
            $keys = $this->indexNames($table);
            foreach ($indices as $index) {
                $keyName = 'primary';
                if (!preg_match('/^PRIMARY\s+KEY/i', $index) && preg_match('/^(?:UNIQUE\s+|FULLTEXT\s+|SPATIAL\s+)?(?:KEY|INDEX)\s+`?([^\s`(]+)`?/i', $index, $km)) {
                    $keyName = strtolower($km[1]);
                }
                if (!in_array($keyName, $keys, true)) {
                    if ($execute) {
                        $this->db->execute("ALTER TABLE {$table} ADD " . $index);
                    }
                    $done[$table . '.' . $keyName] = "Added index {$table} {$index}";
                }
            }
        }
        return $done;
    }

    /** @return array{0: array<string, string>, 1: list<string>} column definitions by lowercase name, and the index lines */
    private static function definitions(string $body): array
    {
        $columns = [];
        $indices = [];
        foreach (explode("\n", $body) as $line) {
            $line = rtrim(trim($line), ',');
            if ($line === '') {
                continue;
            }
            $first = strtolower((string) strtok($line, ' '));
            if (in_array($first, ['primary', 'index', 'fulltext', 'unique', 'key', 'spatial'], true)) {
                $indices[] = $line;
            } else {
                $columns[strtolower(trim((string) strtok($line, ' '), '`'))] = $line;
            }
        }
        return [$columns, $indices];
    }

    /**
     * Every table in the database.
     *
     * @return list<string>
     */
    public function tables(): array
    {
        return array_map(static fn (array $r) => (string) reset($r), $this->db->rows('SHOW TABLES'));
    }

    /**
     * A table's columns with their definitions.
     *
     * @return array<string, array<string, mixed>> by column name
     */
    public function columns(string $table): array
    {
        $out = [];
        foreach ($this->db->rows("SHOW COLUMNS FROM {$table}") as $row) {
            $out[(string) $row['Field']] = $row;
        }
        return $out;
    }

    /**
     * A table's index names.
     *
     * @return list<string> lowercase key names
     */
    public function indexNames(string $table): array
    {
        return array_values(array_unique(array_map(static fn (array $r) => strtolower((string) $r['Key_name']), $this->db->rows("SHOW INDEX FROM {$table}"))));
    }

    /** Runs one DDL statement. */
    public function run(string $ddl): void
    {
        $this->db->execute($ddl);
    }
}
