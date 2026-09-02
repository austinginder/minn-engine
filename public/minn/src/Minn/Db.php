<?php

declare(strict_types=1);

namespace Minn;

use mysqli;
use mysqli_stmt;

/**
 * The one door to the database. Every query is a prepared statement; the
 * placeholder types are derived from the PHP values, so callers pass plain
 * arrays and never spell out "issd". A parameter that is a list stands for
 * a list of values: its "?" becomes as many placeholders as the list is
 * long, so "post_type IN (?)" takes the types themselves.
 */
final class Db
{
    private static ?self $shared = null;

    public function __construct(
        private readonly mysqli $connection,
        private readonly string $prefix,
    ) {
    }

    /**
     * The connection the request being answered speaks through: its
     * runtime's own, so nothing deep in a render can reach a connection
     * belonging to another request. With no runtime (the command line, the
     * unit suite) it is the process's own connection.
     *
     * This is the one place in the core that asks the runtime anything; the
     * alternative is a database door threaded through every renderer, and
     * the seam is here until that threading is done.
     */
    public static function current(): self
    {
        return \Minn\Runtime\Runtime::booted() ? \Minn\Runtime\Runtime::current()->db : self::shared();
    }

    /** The one connection for this process, opened from wp-config's constants on first use. */
    public static function shared(): self
    {
        if (self::$shared === null) {
            $connection = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
            $connection->set_charset('utf8mb4');
            self::$shared = new self($connection, $GLOBALS['table_prefix'] ?? 'wp_');
        }
        return self::$shared;
    }

    /** The underlying mysqli handle, for the facade's wpdb. */
    public function connection(): mysqli
    {
        return $this->connection;
    }

    /** A value escaped for direct interpolation into SQL, for callers that build their own statements. */
    public function escape(string $value): string
    {
        return $this->connection()->real_escape_string($value);
    }

    /** The table prefix from wp-config. */
    public function prefix(): string
    {
        return $this->prefix;
    }

    /** `posts` becomes `wp_posts`; the prefix comes from wp-config.php. */
    public function table(string $name): string
    {
        return $this->prefix . $name;
    }

    /**
     * Every row of a prepared query, as associative arrays.
     *
     * @return list<array<string, mixed>>
     */
    public function rows(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * The first row of a prepared query, or null.
     *
     * @return array<string, mixed>|null
     */
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

    /**
     * Rewrites every "?" whose parameter is a list into that many
     * placeholders, and flattens the values to match, so "IN (?)" becomes
     * "IN (?, ?, ?)". An empty list becomes NULL, which nothing is IN, so a
     * caller that means "everything" when the list is empty says so itself.
     *
     * @param list<mixed> $params
     * @return array{0: string, 1: list<mixed>}
     */
    public static function expand(string $sql, array $params): array
    {
        if (array_filter($params, is_array(...)) === []) {
            return [$sql, array_values($params)];
        }
        $params = array_values($params);
        $out = '';
        $flat = [];
        $index = 0;
        foreach (self::placeholders($sql) as $piece) {
            if ($piece !== '?') {
                $out .= $piece;
                continue;
            }
            $param = $params[$index] ?? null;
            $index++;
            if (!is_array($param)) {
                $out .= '?';
                $flat[] = $param;
                continue;
            }
            $out .= $param === [] ? 'NULL' : implode(', ', array_fill(0, count($param), '?'));
            $flat = [...$flat, ...array_values($param)];
        }
        return [$out, $flat];
    }

    /**
     * The statement split into placeholders and everything between them. A
     * "?" inside a quoted string is text, not a placeholder.
     *
     * @return list<string>
     */
    private static function placeholders(string $sql): array
    {
        $pieces = [];
        $buffer = '';
        $quote = '';
        for ($i = 0, $length = strlen($sql); $i < $length; $i++) {
            $char = $sql[$i];
            if ($quote !== '') {
                $buffer .= $char;
                if ($char === '\\' && $i + 1 < $length) {
                    $buffer .= $sql[++$i];
                } elseif ($char === $quote) {
                    $quote = '';
                }
                continue;
            }
            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                $buffer .= $char;
                continue;
            }
            if ($char === '?') {
                $pieces[] = $buffer;
                $pieces[] = '?';
                $buffer = '';
                continue;
            }
            $buffer .= $char;
        }
        $pieces[] = $buffer;
        return $pieces;
    }

    /** The id the last INSERT produced. */
    public function insertId(): int
    {
        return (int) $this->connection->insert_id;
    }

    /** One option's raw value, or null when it is unset. */
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
        [$sql, $params] = self::expand($sql, $params);
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
