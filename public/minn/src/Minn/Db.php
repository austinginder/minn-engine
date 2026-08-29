<?php

declare(strict_types=1);

namespace Minn;

use mysqli;
use mysqli_stmt;

/**
 * The one door to the database. Every query is a prepared statement; the
 * placeholder types are derived from the PHP values, so callers pass plain
 * arrays and never spell out "issd".
 */
final class Db
{
    private static ?self $shared = null;

    public function __construct(
        private readonly mysqli $connection,
        private readonly string $prefix,
    ) {
    }

    public static function shared(): self
    {
        if (self::$shared === null) {
            $connection = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
            $connection->set_charset('utf8mb4');
            self::$shared = new self($connection, $GLOBALS['table_prefix'] ?? 'wp_');
        }
        return self::$shared;
    }

    public function connection(): mysqli
    {
        return $this->connection;
    }

    /** A value escaped for direct interpolation into SQL, for callers that build their own statements. */
    public function escape(string $value): string
    {
        return $this->connection()->real_escape_string($value);
    }

    public function prefix(): string
    {
        return $this->prefix;
    }

    /** `posts` becomes `wp_posts`; the prefix comes from wp-config.php. */
    public function table(string $name): string
    {
        return $this->prefix . $name;
    }

    /** @return list<array<string, mixed>> */
    public function rows(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /** @return array<string, mixed>|null */
    public function row(string $sql, array $params = []): ?array
    {
        return $this->run($sql, $params)->get_result()->fetch_assoc();
    }

    /** The first column of the first row, or null when there is no row. */
    public function value(string $sql, array $params = []): int|float|string|null
    {
        $row = $this->run($sql, $params)->get_result()->fetch_row();
        return $row === null ? null : $row[0];
    }

    /** Runs a write and returns the affected row count. */
    public function execute(string $sql, array $params = []): int
    {
        return (int) $this->run($sql, $params)->affected_rows;
    }

    public function insertId(): int
    {
        return (int) $this->connection->insert_id;
    }

    public function option(string $name): ?string
    {
        $value = $this->value(
            "SELECT option_value FROM {$this->table('options')} WHERE option_name = ? LIMIT 1",
            [$name],
        );
        return $value === null ? null : (string) $value;
    }

    private function run(string $sql, array $params): mysqli_stmt
    {
        $statement = $this->connection->prepare($sql);
        if ($params !== []) {
            $types = '';
            foreach ($params as $param) {
                $types .= match (true) {
                    is_int($param), is_bool($param) => 'i',
                    is_float($param) => 'd',
                    default => 's',
                };
            }
            $statement->bind_param($types, ...array_values($params));
        }
        $statement->execute();
        return $statement;
    }
}
