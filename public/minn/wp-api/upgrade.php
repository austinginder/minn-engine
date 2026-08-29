<?php
/** dbDelta: creates a table from its CREATE statement, or adds the columns and keys the statement has and the table lacks. */

use Minn\Runtime\Runtime;

function dbDelta($queries = '', $execute = true)
{
    $db = Runtime::current()->db;
    if (is_string($queries)) {
        $queries = array_filter(array_map('trim', preg_split('/;\s*$/m', $queries)), static fn ($q) => $q !== '');
    }
    $queries = apply_filters('dbdelta_queries', array_values((array) $queries));
    $for_update = [];
    $creates = [];
    foreach ($queries as $query) {
        if (preg_match('|^\s*CREATE TABLE (?:IF NOT EXISTS\s+)?([^\s(]+)|i', $query, $m)) {
            $creates[trim($m[1], '`')] = $query;
        }
    }
    $creates = apply_filters('dbdelta_create_queries', $creates);
    $tables = array_map(static fn (array $r) => (string) reset($r), $db->rows('SHOW TABLES'));
    foreach ($creates as $table => $query) {
        if (!in_array($table, $tables, true)) {
            if ($execute) {
                $db->execute($query);
            }
            $for_update[$table] = "Created table {$table}";
            continue;
        }
        if (!preg_match('|\((.*)\)|s', $query, $body)) {
            continue;
        }
        $lines = array_filter(array_map('trim', explode("\n", $body[1])), static fn ($l) => $l !== '');
        $cols = [];
        $indices = [];
        foreach ($lines as $line) {
            $line = rtrim($line, ',');
            $first = strtolower(strtok($line, ' '));
            if (in_array($first, ['primary', 'index', 'fulltext', 'unique', 'key', 'spatial'], true)) {
                $indices[] = $line;
            } elseif ($line !== '') {
                $name = strtolower(trim(strtok($line, ' '), '`'));
                $cols[$name] = $line;
            }
        }
        $existing = [];
        foreach ($db->rows("SHOW COLUMNS FROM {$table}") as $row) {
            $existing[strtolower((string) $row['Field'])] = $row;
        }
        foreach ($cols as $name => $definition) {
            if (!isset($existing[$name])) {
                if ($execute) {
                    $db->execute("ALTER TABLE {$table} ADD COLUMN {$definition}");
                }
                $for_update[$table . '.' . $name] = "Added column {$table}.{$name}";
            }
        }
        $existingKeys = [];
        foreach ($db->rows("SHOW INDEX FROM {$table}") as $row) {
            $existingKeys[strtolower((string) $row['Key_name'])] = true;
        }
        foreach ($indices as $index) {
            $keyName = 'primary';
            if (!preg_match('/^PRIMARY\s+KEY/i', $index)) {
                if (preg_match('/^(?:UNIQUE\s+|FULLTEXT\s+|SPATIAL\s+)?(?:KEY|INDEX)\s+`?([^\s`(]+)`?/i', $index, $km)) {
                    $keyName = strtolower($km[1]);
                }
            }
            if (!isset($existingKeys[$keyName])) {
                if ($execute) {
                    $db->execute("ALTER TABLE {$table} ADD " . $index);
                }
                $for_update[$table . '.' . $keyName] = "Added index {$table} {$index}";
            }
        }
    }
    return $for_update;
}

function maybe_create_table($table_name, $create_ddl)
{
    $db = Runtime::current()->db;
    foreach ($db->rows('SHOW TABLES') as $row) {
        if ((string) reset($row) === $table_name) {
            return true;
        }
    }
    $db->execute($create_ddl);
    foreach ($db->rows('SHOW TABLES') as $row) {
        if ((string) reset($row) === $table_name) {
            return true;
        }
    }
    return false;
}

function maybe_add_column($table_name, $column_name, $create_ddl)
{
    $db = Runtime::current()->db;
    foreach ($db->rows("SHOW COLUMNS FROM {$table_name}") as $row) {
        if ((string) $row['Field'] === $column_name) {
            return true;
        }
    }
    $db->execute($create_ddl);
    foreach ($db->rows("SHOW COLUMNS FROM {$table_name}") as $row) {
        if ((string) $row['Field'] === $column_name) {
            return true;
        }
    }
    return false;
}

function wp_get_db_schema($scope = 'all', $blog_id = null)
{
    return '';
}

function wp_install_defaults($user_id)
{
}

function wp_upgrade()
{
}

function upgrade_all()
{
}

function make_db_current_silent($tables = 'all')
{
}
