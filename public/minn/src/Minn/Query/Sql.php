<?php

declare(strict_types=1);

namespace Minn\Query;

/**
 * Literal quoting for the SQL fragments the query classes hand to plugins,
 * which embed them verbatim in their own statements.
 */
final class Sql
{
    /** The open connection's own escaping, once there is one. */
    private static ?\Closure $connection = null;

    /**
     * Escapes through the site's connection from now on, so the quoting
     * follows the character set it speaks (wpdb sets DB_CHARSET on it; gbk,
     * big5, sjis, cp932 and gb18030 have lead bytes the fixed table would
     * let close a quote). Under utf8mb4 the two agree byte for byte.
     */
    public static function through(\Closure $escape): void
    {
        self::$connection = $escape;
    }

    /** A quoted string literal. */
    public static function quote(string $value): string
    {
        return "'" . self::escape($value) . "'";
    }

    /** The reference's escaping: the connection's once one is open, else backslash, quotes, NUL, newlines, and the substitute character. */
    public static function escape(string $value): string
    {
        if (self::$connection !== null) {
            return (string) (self::$connection)($value);
        }
        return strtr($value, ["\\" => "\\\\", "'" => "\\'", '"' => '\\"', "\0" => "\\0", "\n" => "\\n", "\r" => "\\r", "\x1a" => "\\Z"]);
    }

    /** A LIKE operand with its wildcards escaped; quote() afterwards. */
    public static function like(string $value): string
    {
        return addcslashes($value, '_%\\');
    }

    /** A comma list of quoted literals. */
    public static function list(array $values): string
    {
        return implode(',', array_map(static fn ($v) => self::quote((string) $v), $values));
    }

    /**
     * Clauses joined as the reference joins them at every depth (probe
     * query-clauses): wrapped in parentheses, each clause and the relation
     * on its own line, indented two spaces a level.
     *
     * @param list<string> $chunks
     */
    public static function group(array $chunks, string $relation, int $depth): string
    {
        if ($chunks === []) {
            return '';
        }
        $indent = str_repeat('  ', $depth);
        return '( ' . "\n  " . $indent . implode(' ' . "\n  " . $indent . $relation . ' ' . "\n  " . $indent, $chunks) . "\n" . $indent . ')';
    }
}
