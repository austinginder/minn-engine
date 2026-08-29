<?php

declare(strict_types=1);

namespace Minn\Query;

/**
 * Literal quoting for the SQL fragments the query classes hand to plugins,
 * which embed them verbatim in their own statements.
 */
final class Sql
{
    /** A quoted string literal. */
    public static function quote(string $value): string
    {
        return "'" . self::escape($value) . "'";
    }

    /** The reference's escaping: backslash, quotes, NUL, newlines, and the substitute character. */
    public static function escape(string $value): string
    {
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
}
