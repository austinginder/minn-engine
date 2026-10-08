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

/** Drops an index and the numbered copies an old upgrade could leave (name_0 to name_24); true whatever was there. */
function drop_index($table, $index)
{
    global $wpdb;
    $wpdb->hide_errors();
    $wpdb->query("ALTER TABLE `{$table}` DROP INDEX `{$index}`");
    for ($i = 0; $i < 25; $i++) {
        $wpdb->query("ALTER TABLE `{$table}` DROP INDEX `{$index}_{$i}`");
    }
    $wpdb->show_errors();
    return true;
}

/** An index added afresh: dropped first (copies included), then added on the column of its name. */
function add_clean_index($table, $index)
{
    global $wpdb;
    drop_index($table, $index);
    $wpdb->query("ALTER TABLE `{$table}` ADD INDEX ( `{$index}` )");
    return true;
}

/** An option straight from the table, past the cache (home and the site address from their constants when defined, without a trailing slash). */
function __get_option($setting)
{
    global $wpdb;
    $constant = ['home' => 'WP_HOME', 'siteurl' => 'WP_SITEURL'][$setting] ?? null;
    if ($constant !== null && defined($constant)) {
        return untrailingslashit(constant($constant));
    }
    $option = $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $setting));
    if ($setting === 'home' && !$option) {
        return __get_option('siteurl');
    }
    return maybe_unserialize(in_array($setting, ['siteurl', 'home', 'category_base', 'tag_base'], true) ? untrailingslashit((string) $option) : $option);
}

/** Backslashes before quotes dropped, and runs of them made one. */
function deslash($content)
{
    return preg_replace(['/\\\\+\'/', '/\\\\+"/', '/\\\\+/'], ['\'', '"', '\\\\'], $content);
}

/** The core files' checksums for a version: Minn asks wordpress.org for nothing (Track H), so there are none to give. */
function get_core_checksums($version, $locale)
{
    return false;
}
