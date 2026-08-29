<?php
/** dbDelta: creates a table from its CREATE statement, or adds the columns and keys the statement has and the table lacks. */

use Minn\Runtime\Runtime;
use Minn\Runtime\DbDelta;

function dbDelta($queries = '', $execute = true)
{
    if (is_string($queries)) {
        $queries = array_filter(array_map('trim', preg_split('/;\s*$/m', $queries)), static fn ($q) => $q !== '');
    }
    $queries = apply_filters('dbdelta_queries', array_values((array) $queries));
    $creates = apply_filters('dbdelta_create_queries', DbDelta::creates($queries));
    return _minn_db_delta()->apply($creates, (bool) $execute);
}

/** @internal */
function _minn_db_delta(): DbDelta
{
    return new DbDelta(Runtime::current()->db);
}

function maybe_create_table($table_name, $create_ddl)
{
    $delta = _minn_db_delta();
    if (in_array($table_name, $delta->tables(), true)) {
        return true;
    }
    $delta->run((string) $create_ddl);
    return in_array($table_name, $delta->tables(), true);
}

function maybe_add_column($table_name, $column_name, $create_ddl)
{
    $delta = _minn_db_delta();
    if (isset($delta->columns((string) $table_name)[$column_name])) {
        return true;
    }
    $delta->run((string) $create_ddl);
    return isset($delta->columns((string) $table_name)[$column_name]);
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
