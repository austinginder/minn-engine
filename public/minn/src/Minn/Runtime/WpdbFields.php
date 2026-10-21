<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\I18n\Gettext;
use Minn\Support\Utf8;

/**
 * The database object's writers and the charset work behind them (probe
 * db-mail-rewrite-doubts): an insert, replace, update or delete prepares one
 * statement holding every value (a null written as NULL in it), and an
 * insert or replace starts with insert_id at 0; the fields are checked
 * against their columns, every field that would not fit named in the error;
 * a table's columns are kept as the server's rows; a table that cannot be
 * read gives the charset failure; text in a charset other than UTF-8,
 * latin1 or binary is converted by the server; and check_safe_collation
 * passes ASCII, the statements that read no rows, latin1 tables and tables
 * whose text columns use only a binary or general UTF-8 collation.
 */
final class WpdbFields
{
    private const SAFE_COLLATION = '/^utf8(?:mb[34])?_(?:bin|general_ci)$/i';

    /** Inserts or replaces a row from processed fields (wpdb::_insert_replace_helper): rows written, or false. */
    public static function insert(object $db, mixed $table, mixed $data, mixed $format, mixed $type): int|bool
    {
        $db->insert_id = 0;
        $type = strtoupper((string) $type);
        $fields = in_array($type, ['INSERT', 'REPLACE'], true) ? self::process($db, $table, $data, $format) : false;
        if ($fields === false || $fields === []) {
            return false;
        }
        [$values, $marks] = self::marks($fields);
        $columns = implode(', ', array_map(static fn ($field): string => "`{$field}`", array_keys($fields)));
        return self::send($db, "{$type} INTO `{$table}` ({$columns}) VALUES (" . implode(', ', $marks) . ')', $values);
    }

    /** Updates the rows a where list matches (wpdb::update): rows written, or false. */
    public static function update(object $db, mixed $table, mixed $data, mixed $where, mixed $format, mixed $whereFormat): int|bool
    {
        if (!is_array($data) || !is_array($where)) {
            return false;
        }
        $fields = self::process($db, $table, $data, $format);
        $conditions = $fields === false ? false : self::process($db, $table, $where, $whereFormat);
        if ($fields === false || $conditions === false) {
            return false;
        }
        [$values, $marks] = self::marks($fields);
        $sets = array_map(static fn ($field, string $mark): string => "`{$field}` = {$mark}", array_keys($fields), $marks);
        [$whereValues, $whereSql] = self::conditions($conditions);
        return self::send($db, "UPDATE `{$table}` SET " . implode(', ', $sets) . " WHERE {$whereSql}", [...$values, ...$whereValues]);
    }

    /** Deletes the rows a where list matches (wpdb::delete): rows deleted, or false. */
    public static function delete(object $db, mixed $table, mixed $where, mixed $whereFormat): int|bool
    {
        if (!is_array($where)) {
            return false;
        }
        $conditions = self::process($db, $table, $where, $whereFormat);
        if ($conditions === false) {
            return false;
        }
        [$values, $whereSql] = self::conditions($conditions);
        return self::send($db, "DELETE FROM `{$table}` WHERE {$whereSql}", $values);
    }

    /**
     * The fields with their formats, column charsets and lengths, or false:
     * when a column cannot be read, or when a value would not fit unchanged,
     * last_error naming every such field (wpdb::process_fields).
     */
    public static function process(object $db, mixed $table, mixed $data, mixed $format): array|false
    {
        return (function () use ($db, $table, $data, $format): array|false {
            $fields = $this->process_field_formats($data, $format);
            $fields = $fields === false ? false : $this->process_field_charsets($fields, $table);
            $fields = $fields === false ? false : $this->process_field_lengths($fields, $table);
            if ($fields === false) {
                return false;
            }
            $kept = $this->strip_invalid_text($fields);
            $failed = Records::isError($kept) ? array_keys($fields) : array_keys(array_filter($fields, static fn (array $field, $name): bool => $kept[$name]['value'] !== $field['value'], ARRAY_FILTER_USE_BOTH));
            if ($failed === []) {
                return $fields;
            }
            $db->last_error = count($failed) === 1
                ? sprintf(Gettext::text('WordPress database error: Processing the value for the following field failed: %s. The supplied value may be too long or contains invalid data.'), reset($failed))
                : sprintf(Gettext::text('WordPress database error: Processing the values for the following fields failed: %s. The supplied values may be too long or contain invalid data.'), implode(', ', $failed));
            return false;
        })->call($db);
    }

    /** Each field with its column's charset, or false when the table cannot be read (wpdb::process_field_charsets). */
    public static function charsets(object $db, mixed $data, mixed $table): array|false
    {
        foreach ((array) $data as $field => $value) {
            $charset = in_array($value['format'], ['%d', '%f'], true) ? false : $db->get_col_charset($table, $field);
            if (Records::isError($charset)) {
                return false;
            }
            $data[$field]['charset'] = $charset;
        }
        return $data;
    }

    /** Each field with its column's length, or false when the table cannot be read (wpdb::process_field_lengths). */
    public static function lengths(object $db, mixed $data, mixed $table): array|false
    {
        foreach ((array) $data as $field => $value) {
            $length = in_array($value['format'], ['%d', '%f'], true) ? false : $db->get_col_length($table, $field);
            if (Records::isError($length)) {
                return false;
            }
            $data[$field]['length'] = $length;
        }
        return $data;
    }

    /**
     * Each text value cut to what its column keeps (wpdb::strip_invalid_text):
     * malformed bytes dropped for UTF-8, four-byte characters too for utf8,
     * latin1 and binary kept, then cut to the column's length; text in any
     * other charset goes through the server's conversion, in one query.
     */
    public static function strip(object $db, mixed $data): mixed
    {
        $data = (array) $data;
        $server = [];
        foreach ($data as $field => $value) {
            if (!is_string($value['value'] ?? null) || empty($value['charset'])) {
                continue;
            }
            $charset = (string) $value['charset'];
            if (!in_array($charset, ['utf8', 'utf8mb3', 'utf8mb4', 'latin1', 'binary'], true) && preg_match('/[^\x00-\x7F]/', $value['value'])) {
                $server[$field] = $value;
                continue;
            }
            $data[$field]['value'] = self::cut(Utf8::keptBy($value['value'], $charset), $charset, $value['length'] ?? false);
        }
        return $server === [] ? $data : self::convert($db, $data, $server);
    }

    /** A query with what its table (the connection, when it names none) cannot hold dropped (wpdb::strip_invalid_text_from_query). */
    public static function stripQuery(object $db, mixed $query): mixed
    {
        $query = (string) $query;
        if (!preg_match('/[^\x00-\x7F]/', $query)) {
            return $query;
        }
        $table = \Minn\Support\SqlTable::of($query);
        $charset = $table !== null ? self::charsetOf($db, $table) : $db->charset;
        if (Records::isError($charset)) {
            return $charset;
        }
        $kept = (function () use ($query, $charset): mixed {
            return $this->strip_invalid_text([['value' => $query, 'charset' => $charset, 'ascii' => false, 'length' => false]]);
        })->call($db);
        return Records::isError($kept) ? $kept : $kept[0]['value'];
    }

    /** Whether a query can skip the text check (wpdb::check_safe_collation). */
    public static function safeCollation(object $db, mixed $query): bool
    {
        $query = (string) $query;
        if (!preg_match('/[^\x00-\x7F]/', $query) || preg_match('/^\s*(?:SHOW|DESCRIBE|DESC|EXPLAIN|CREATE)\s/i', $query)) {
            return true;
        }
        $table = \Minn\Support\SqlTable::of($query);
        $charset = $table === null ? false : self::charsetOf($db, $table);
        if ($table === null || Records::isError($charset)) {
            return false;
        }
        if ($charset === 'latin1') {
            return true;
        }
        foreach (self::columns($db, $table) as $column) {
            $collation = (string) ($column->Collation ?? '');
            if ($collation !== '' && !preg_match(self::SAFE_COLLATION, $collation)) {
                return false;
            }
        }
        return true;
    }

    /**
     * The charset a table's text columns share, kept for the request
     * (wpdb::get_table_charset); pre_get_table_charset may answer first, and
     * a table the server cannot describe is the charset failure.
     */
    public static function tableCharset(object $db, mixed $table): mixed
    {
        $pre = Runtime::hooks()->filter('pre_get_table_charset', [null, $table]);
        if ($pre !== null) {
            return $pre;
        }
        $key = strtolower((string) $table);
        $known = (fn () => $this->table_charset[$key] ?? null)->call($db);
        if ($known !== null) {
            return $known;
        }
        $columns = self::columns($db, $table);
        if ($columns === []) {
            return self::unreadable();
        }
        $charset = self::sharedCharset($columns);
        (function () use ($key, $charset): void {
            $this->table_charset[$key] = $charset;
        })->call($db);
        return $charset;
    }

    /** A column's charset: false for one that holds no text, the table's for a column it does not have (wpdb::get_col_charset). */
    public static function columnCharset(object $db, mixed $table, mixed $column): mixed
    {
        $pre = Runtime::hooks()->filter('pre_get_col_charset', [null, $table, $column]);
        if ($pre !== null) {
            return $pre;
        }
        // The table's charset is asked the first time a table is looked at, then kept.
        $key = strtolower((string) $table);
        $tableCharset = (fn () => $this->table_charset[$key] ?? null)->call($db) ?? self::charsetOf($db, $table);
        if (Records::isError($tableCharset)) {
            return $tableCharset;
        }
        $found = self::columns($db, $table)[strtolower((string) $column)] ?? null;
        if ($found === null) {
            return $tableCharset;
        }
        $collation = (string) ($found->Collation ?? '');
        return $collation === '' ? false : explode('_', $collation)[0];
    }

    /** The most a text or binary column holds, false for any other column, the charset failure for a table that cannot be read (wpdb::get_col_length). */
    public static function columnLength(object $db, mixed $table, mixed $column): mixed
    {
        $columns = self::columns($db, $table);
        if ($columns === []) {
            return self::unreadable();
        }
        return \Minn\Db::columnLength((string) ($columns[strtolower((string) $column)]->Type ?? ''));
    }

    /** The charset a query's text is held to: its table's, the connection's when it names none or its table cannot be read. */
    public static function queryCharset(object $db, string $query): string|false
    {
        $table = \Minn\Support\SqlTable::of($query);
        $charset = $table !== null ? self::charsetOf($db, $table) : $db->charset;
        return is_string($charset) || $charset === false ? $charset : (string) $db->charset;
    }

    /**
     * A table's columns, the server's SHOW FULL COLUMNS rows by lower-cased
     * name, kept in col_meta; empty for a table the server cannot describe.
     *
     * @return array<string, object>
     */
    private static function columns(object $db, mixed $table): array
    {
        $key = strtolower((string) $table);
        $known = (fn () => $this->col_meta[$key] ?? null)->call($db);
        if (is_array($known)) {
            return $known;
        }
        $columns = [];
        foreach ((array) $db->get_results('SHOW FULL COLUMNS FROM `' . str_replace('`', '', (string) $table) . '`') as $row) {
            $columns[strtolower((string) $row->Field)] = $row;
        }
        if ($columns !== []) {
            (function () use ($key, $columns): void {
                $this->col_meta[$key] = $columns;
            })->call($db);
        }
        return $columns;
    }

    /**
     * The charset a table's columns come to (probe invalid-text): binary when
     * one holds bytes, false when none holds text, else their one charset;
     * latin1 beside others does not count, utf8 beside utf8mb4 is utf8, and
     * any other mixture is ascii.
     *
     * @param array<string, object> $columns
     */
    private static function sharedCharset(array $columns): string|false
    {
        $charsets = [];
        foreach ($columns as $column) {
            if (preg_match('/^(?:var)?binary\b|blob$/', (string) $column->Type)) {
                return 'binary';
            }
            $collation = (string) ($column->Collation ?? '');
            if ($collation !== '') {
                $charset = explode('_', $collation)[0];
                $charsets[$charset === 'utf8mb3' ? 'utf8' : $charset] = true;
            }
        }
        if (count($charsets) > 1) {
            unset($charsets['latin1']);
        }
        $charsets = array_keys($charsets);
        return match (true) {
            $charsets === [] => false,
            count($charsets) === 1 => $charsets[0],
            array_diff($charsets, ['utf8', 'utf8mb4']) === [] => 'utf8',
            default => 'ascii',
        };
    }

    /** A table's charset through the object's own get_table_charset, which a subclass may answer. */
    private static function charsetOf(object $db, string $table): mixed
    {
        return (fn () => $this->get_table_charset($table))->call($db);
    }

    /** The table charset failure. */
    private static function unreadable(): mixed
    {
        return Handoff::newError('wpdb_get_table_charset_failure', Gettext::text('Could not retrieve table charset.'));
    }

    /** A value cut to a column's length: bytes for latin1 (one character each) and the byte types, characters otherwise. */
    private static function cut(string $value, string $charset, mixed $length): string
    {
        if (!is_array($length)) {
            return $value;
        }
        $max = (int) $length['length'];
        return match (true) {
            $charset === 'latin1' => substr($value, 0, $max),
            $length['type'] === 'byte' => mb_strcut($value, 0, $max, 'UTF-8'),
            default => mb_substr($value, 0, $max, 'UTF-8'),
        };
    }

    /**
     * Text in charsets PHP does not judge here, converted by the server to
     * the charset and back, cut to each column's length on the way.
     *
     * @param array<array-key, array<string, mixed>> $data
     * @param array<array-key, array<string, mixed>> $server
     */
    private static function convert(object $db, array $data, array $server): mixed
    {
        $parts = [];
        $names = [];
        foreach ($server as $field => $value) {
            $charset = preg_replace('/[^a-z0-9_]/i', '', (string) $value['charset']);
            $text = "CONVERT('" . $db->_real_escape($value['value']) . "' USING {$charset})";
            $length = $value['length'] ?? false;
            if (is_array($length) && $length['type'] === 'byte') {
                $text = "CONVERT(LEFT(CAST({$text} AS BINARY), " . (int) $length['length'] . ") USING {$charset})";
            } elseif (is_array($length)) {
                $text = "LEFT({$text}, " . (int) $length['length'] . ')';
            }
            $names[$field] = 'v' . count($parts);
            $parts[] = "CONVERT({$text} USING utf8mb4) AS {$names[$field]}";
        }
        (function (): void {
            $this->check_current_query = false;
        })->call($db);
        $row = $db->get_row('SELECT ' . implode(', ', $parts), 'ARRAY_A');
        if (!is_array($row)) {
            return Handoff::newError('wpdb_strip_invalid_text_failure', Gettext::text('Could not strip invalid text.'));
        }
        foreach ($names as $field => $name) {
            $data[$field]['value'] = (string) $row[$name];
        }
        return $data;
    }

    /**
     * The values a field list sends and the placeholder (or NULL) each takes in the statement.
     *
     * @param array<array-key, array<string, mixed>> $fields
     * @return array{0: list<mixed>, 1: list<string>}
     */
    private static function marks(array $fields): array
    {
        $values = [];
        $marks = [];
        foreach ($fields as $field) {
            if ($field['value'] === null) {
                $marks[] = 'NULL';
                continue;
            }
            $marks[] = (string) $field['format'];
            $values[] = $field['value'];
        }
        return [$values, $marks];
    }

    /**
     * A where list as SQL: each field equal to its placeholder, or IS NULL.
     *
     * @param array<array-key, array<string, mixed>> $conditions
     * @return array{0: list<mixed>, 1: string}
     */
    private static function conditions(array $conditions): array
    {
        $values = [];
        $sql = [];
        foreach ($conditions as $field => $condition) {
            if ($condition['value'] === null) {
                $sql[] = "`{$field}` IS NULL";
                continue;
            }
            $sql[] = "`{$field}` = {$condition['format']}";
            $values[] = $condition['value'];
        }
        return [$values, implode(' AND ', $sql)];
    }

    /** Sends a writer's one prepared statement, its values already checked. */
    private static function send(object $db, string $sql, array $values): int|bool
    {
        (function (): void {
            $this->check_current_query = false;
        })->call($db);
        return $db->query($db->prepare($sql, $values));
    }
}
