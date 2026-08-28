<?php

declare(strict_types=1);

namespace Minn\Cli;

use Minn\Support\SearchReplace;
use WP_CLI;
use WP_CLI\Formatter;

/**
 * `wp search-replace`: walks every string column, including serialized
 * PHP arrays, and reports replacements the way the reference does.
 */
final class SearchReplaceCommand
{
    private const SKIP_TYPES = '/^(tinyint|smallint|mediumint|int|bigint|float|double|decimal|bit|date|time|datetime|timestamp|year)/i';

    /**
     * Searches/replaces strings in the database.
     *
     * ## OPTIONS
     *
     * <old>
     * : A string to search for.
     *
     * <new>
     * : Replace instances of the first string with this new string.
     *
     * [<table>...]
     * : Restrict the replacement to these tables.
     *
     * [--dry-run]
     * : Report without writing.
     *
     * [--all-tables]
     * : Search every table in the database, not only those with the site prefix.
     *
     * [--report-changed-only]
     * : Only print tables/columns that changed.
     *
     * [--format=<format>]
     * : Render output in a particular format.
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     * ---
     *
     * @when before_wp_load
     */
    public function __invoke(array $args, array $assocArgs): void
    {
        $old = (string) ($args[0] ?? '');
        $new = (string) ($args[1] ?? '');
        if ($old === $new) {
            WP_CLI::warning("Replacement value '{$new}' is identical to search value '{$old}'. Skipping operation.");
            return;
        }
        $runtime = Runtime::boot();
        $tables = array_slice($args, 2);
        if ($tables === []) {
            $tables = self::listTables($runtime, isset($assocArgs['all-tables']));
        }
        $dry = isset($assocArgs['dry-run']);
        $changedOnly = isset($assocArgs['report-changed-only']);
        $rows = [];
        $total = 0;
        foreach ($tables as $table) {
            foreach (self::stringColumns($runtime, $table) as $column) {
                $count = self::replaceColumn($runtime, $table, $column, $old, $new, $dry);
                $total += $count;
                if ($count > 0 || !$changedOnly) {
                    $rows[] = ['Table' => $table, 'Column' => $column, 'Replacements' => $count, 'Type' => 'PHP'];
                }
            }
        }
        if ($rows !== []) {
            (new Formatter($assocArgs, ['Table', 'Column', 'Replacements', 'Type']))->display_items($rows);
        }
        $word = $total === 1 ? 'replacement' : 'replacements';
        if ($dry) {
            WP_CLI::success("{$total} {$word} to be made.");
        } else {
            WP_CLI::success("Made {$total} {$word}.");
        }
    }

    /** @return list<string> */
    private static function listTables(Runtime $runtime, bool $all): array
    {
        $found = [];
        $prefix = $runtime->db->prefix();
        foreach ($runtime->db->rows('SHOW TABLES') as $row) {
            $name = (string) array_values($row)[0];
            if ($all || str_starts_with($name, $prefix)) {
                $found[] = $name;
            }
        }
        return $found;
    }

    /** @return list<string> */
    private static function stringColumns(Runtime $runtime, string $table): array
    {
        $columns = [];
        foreach ($runtime->db->rows('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '`') as $row) {
            $field = (string) $row['Field'];
            $type = (string) $row['Type'];
            if ($field === 'guid' || preg_match(self::SKIP_TYPES, $type) === 1) {
                continue;
            }
            $columns[] = $field;
        }
        return $columns;
    }

    private static function replaceColumn(Runtime $runtime, string $table, string $column, string $old, string $new, bool $dry): int
    {
        $safeTable = '`' . str_replace('`', '``', $table) . '`';
        $safeColumn = '`' . str_replace('`', '``', $column) . '`';
        $keys = [];
        foreach ($runtime->db->rows('SHOW KEYS FROM ' . $safeTable . " WHERE Key_name = 'PRIMARY'") as $row) {
            $keys[] = (string) $row['Column_name'];
        }
        if ($keys === []) {
            return 0;
        }
        $select = $safeColumn;
        foreach ($keys as $key) {
            $select .= ', `' . str_replace('`', '``', $key) . '`';
        }
        $like = '%' . addcslashes($old, '%_\\') . '%';
        $hits = $runtime->db->rows("SELECT {$select} FROM {$safeTable} WHERE {$safeColumn} LIKE ?", [$like]);
        $total = 0;
        foreach ($hits as $hit) {
            [$replaced, $count] = SearchReplace::in((string) $hit[$column], $old, $new);
            if ($count === 0) {
                continue;
            }
            $total += $count;
            if ($dry) {
                continue;
            }
            $set = "{$safeColumn} = ?";
            $params = [$replaced];
            $where = [];
            foreach ($keys as $key) {
                $where[] = '`' . str_replace('`', '``', $key) . '` = ?';
                $params[] = $hit[$key];
            }
            $runtime->db->execute("UPDATE {$safeTable} SET {$set} WHERE " . implode(' AND ', $where), $params);
        }
        return $total;
    }
}
