<?php

declare(strict_types=1);

namespace Minn\Support;

/**
 * The table a SQL statement is about, as wpdb::get_table_from_query reports
 * it, and a database host string read into its parts (probe wpdb). A name is
 * letters, digits, $ _ . - and backticks (dropped), plus two-byte UTF-8.
 */
final class SqlTable
{
    private const NAME = '((?:[0-9a-zA-Z$_.`-]|[\xC2-\xDF][\x80-\xBF])+)';

    /** What leads to the table name in each statement the reference reads, tried in order. */
    private const LEADS = [
        'EXPLAIN(?:\s+EXTENDED)?\s+SELECT.*?\s+FROM',
        'SELECT.*?\s+FROM',
        'INSERT(?:\s+(?:LOW_PRIORITY|DELAYED|HIGH_PRIORITY))?(?:\s+IGNORE)?(?:\s+INTO)?',
        'REPLACE(?:\s+(?:LOW_PRIORITY|DELAYED))?(?:\s+INTO)?',
        'UPDATE(?:\s+LOW_PRIORITY)?(?:\s+IGNORE)?',
        'DELETE(?:\s+(?:LOW_PRIORITY|QUICK|IGNORE))*(?:.+?FROM)?',
        '(?:DESCRIBE|DESC|EXPLAIN)',
        'SHOW(?:\s+FULL)?\s+(?:COLUMNS|FIELDS|INDEX|INDEXES)\s+FROM',
        'SHOW\s+CREATE\s+TABLE',
        'CREATE(?:\s+TEMPORARY)?\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?',
        'DROP\s+TABLE(?:\s+IF\s+EXISTS)?',
        '(?:ALTER|TRUNCATE|OPTIMIZE|REPAIR|CHECK|ANALYZE|RENAME)(?:\s+TABLE)?',
        'LOCK\s+TABLES?',
        'LOAD\s+DATA.*?INTO\s+TABLE',
    ];

    /** The table a statement names (backticks dropped), or null when it names none the reference recognises. */
    public static function of(string $query): ?string
    {
        $query = ltrim($query, "\r\n\t (");
        $quoted = '\s*("|\')((?:[0-9a-zA-Z$_.\\\\-]|[\xC2-\xDF][\x80-\xBF])+)%?\1';
        if (preg_match('/^SHOW\s+(?:TABLE\s+STATUS|(?:FULL\s+)?TABLES)\s+.*?WHERE\s+Name\s*=' . $quoted . '/is', $query, $m) === 1) {
            return $m[2];
        }
        if (preg_match('/^SHOW\s+(?:TABLE\s+STATUS|(?:FULL\s+)?TABLES)\s+(?:WHERE\s+Name\s+)?LIKE' . $quoted . '/is', $query, $m) === 1) {
            return str_replace('\\_', '_', $m[2]);
        }
        foreach (self::LEADS as $lead) {
            if (preg_match('/^' . $lead . '\s+' . self::NAME . '/is', $query, $m) === 1 && !preg_match('/^(?:SELECT|TABLE|TEMPORARY|IF)$/i', $m[1])) {
                return str_replace('`', '', $m[1]);
            }
        }
        return null;
    }

    /**
     * A DB_HOST read as host, port, socket and whether the host is IPv6: a
     * socket after ":/", an IPv6 address in brackets (more than one colon),
     * a port only when it is digits. Null when nothing parses.
     *
     * @return array{0: string, 1: ?int, 2: ?string, 3: bool}|null
     */
    public static function host(string $host): ?array
    {
        $socket = null;
        $at = strpos($host, ':/');
        if ($at !== false) {
            [$host, $socket] = [substr($host, 0, $at), substr($host, $at + 1)];
        }
        $ipv6 = substr_count($host, ':') > 1;
        $pattern = $ipv6 ? '#^(?:\[)?(?P<host>[0-9a-fA-F:]+)(?:\]:(?P<port>\d+))?#' : '#^(?P<host>[^:/]*)(?::(?P<port>\d+))?#';
        if (preg_match($pattern, $host, $m) !== 1) {
            return null;
        }
        return [$m['host'] ?? '', !empty($m['port']) ? (int) $m['port'] : null, $socket, $ipv6];
    }
}
