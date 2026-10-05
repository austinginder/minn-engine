<?php

use Minn\Runtime\Placeholders;
use Minn\Runtime\Runtime;

/**
 * The database object plugin code holds as $wpdb: table names by property,
 * prepare() with the reference's placeholders, and the read/write helpers,
 * all over the engine's one mysqli connection.
 */
#[AllowDynamicProperties]
class wpdb
{
    public $show_errors = false;
    public $suppress_errors = false;
    public $last_error = '';
    public $num_queries = 0;
    public $num_rows = 0;
    public $rows_affected = 0;
    public $insert_id = 0;
    public $last_query;
    public $last_result;
    public $col_info;
    public $queries = [];
    public $prefix = '';
    public $base_prefix;
    public $ready = true;
    public $blogid = 0;
    public $siteid = 0;
    public $tables = ['posts', 'comments', 'links', 'options', 'postmeta', 'terms', 'term_taxonomy', 'term_relationships', 'termmeta', 'commentmeta'];
    public $old_tables = ['categories', 'post2cat', 'link2cat'];
    public $global_tables = ['users', 'usermeta'];
    public $ms_global_tables = ['blogs', 'blogmeta', 'signups', 'site', 'sitemeta', 'sitecategories', 'registration_log'];
    public $comments;
    public $commentmeta;
    public $links;
    public $options;
    public $postmeta;
    public $posts;
    public $terms;
    public $term_relationships;
    public $term_taxonomy;
    public $termmeta;
    public $usermeta;
    public $users;
    public $blogs;
    public $blogmeta;
    public $registration_log;
    public $signups;
    public $site;
    public $sitecategories;
    public $sitemeta;
    public $field_types = [];
    public $charset;
    public $collate;
    public $dbuser;
    public $dbpassword;
    public $dbname;
    public $dbhost;
    public $dbh;
    public $func_call;
    public $is_mysql = true;
    private $checkCurrentQuery = true;

    public function __construct($dbuser, $dbpassword, $dbname, $dbhost)
    {
        $this->dbuser = $dbuser;
        $this->dbpassword = $dbpassword;
        $this->dbname = $dbname;
        $this->dbhost = $dbhost;
        $this->dbh = Runtime::current()->db->connection();
        $this->charset = defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4';
        $this->collate = defined('DB_COLLATE') ? DB_COLLATE : '';
        $this->set_prefix(Runtime::current()->db->prefix());
    }

    public function set_prefix($prefix, $set_table_names = true)
    {
        $old = $this->prefix;
        $this->prefix = $prefix;
        $this->base_prefix = $prefix;
        if ($set_table_names) {
            foreach (array_merge($this->tables, $this->global_tables, $this->ms_global_tables) as $table) {
                $this->{$table} = $prefix . $table;
            }
        }
        return $old;
    }

    public function tables($scope = 'all', $prefix = true, $blog_id = 0)
    {
        $tables = match ($scope) {
            'blog' => $this->tables,
            'global' => array_merge($this->global_tables, $this->ms_global_tables),
            'ms_global' => $this->ms_global_tables,
            'old' => $this->old_tables,
            default => array_merge($this->tables, $this->global_tables),
        };
        if (!$prefix) {
            return $tables;
        }
        $out = [];
        foreach ($tables as $table) {
            $out[$table] = $this->prefix . $table;
        }
        return $out;
    }

    public function get_blog_prefix($blog_id = null)
    {
        return $this->prefix;
    }

    public function _real_escape($data)
    {
        return $this->dbh->real_escape_string((string) $data);
    }

    public function _escape($data)
    {
        if (is_array($data)) {
            foreach ($data as $k => $v) {
                $data[$k] = is_array($v) ? $this->_escape($v) : $this->_real_escape($v);
            }
            return $data;
        }
        return $this->_real_escape($data);
    }

    public function escape($data)
    {
        return $this->_escape($data);
    }

    public function escape_by_ref(&$data)
    {
        if (!is_float($data)) {
            $data = $this->_real_escape($data);
        }
    }

    public function esc_like($text)
    {
        return addcslashes((string) $text, '_%\\');
    }

    public function remove_placeholder_escape($query)
    {
        return str_replace($this->placeholder_escape(), '%', (string) $query);
    }

    public function placeholder_escape()
    {
        static $placeholder = null;
        return $placeholder ??= '{' . hash('sha256', 'minn-placeholder-' . (defined('AUTH_SALT') ? AUTH_SALT : '')) . '}';
    }

    public function add_placeholder_escape($query)
    {
        return str_replace('%', $this->placeholder_escape(), (string) $query);
    }

    public function prepare($query, ...$args)
    {
        if ($query === null) {
            return null;
        }
        if (isset($args[0]) && is_array($args[0]) && count($args) === 1) {
            $args = array_values($args[0]);
        }
        $filled = Placeholders::fill((string) $query, $args, fn (string $value): string => $this->_real_escape($value));
        return $filled === null ? '' : $this->remove_placeholder_escape($filled);
    }

    public function query($query)
    {
        $this->flush();
        $query = apply_filters('query', (string) $query);
        $this->last_query = $query;
        $this->num_queries++;
        try {
            $result = $this->dbh->query($query);
        } catch (\mysqli_sql_exception $e) {
            $this->last_error = $e->getMessage();
            $this->print_error();
            return false;
        }
        if ($result === false) {
            $this->last_error = $this->dbh->error;
            $this->print_error();
            return false;
        }
        if (preg_match('/^\s*(create|alter|truncate|drop)\s/i', $query)) {
            return true;
        }
        if (preg_match('/^\s*(insert|delete|update|replace)\s/i', $query)) {
            $this->rows_affected = (int) $this->dbh->affected_rows;
            if (preg_match('/^\s*(insert|replace)\s/i', $query)) {
                $this->insert_id = (int) $this->dbh->insert_id;
            }
            return $this->rows_affected;
        }
        if ($result instanceof \mysqli_result) {
            $this->last_result = [];
            while ($row = $result->fetch_object()) {
                $this->last_result[] = $row;
            }
            $this->num_rows = count($this->last_result);
            $result->free();
            return $this->num_rows;
        }
        return true;
    }

    public function get_var($query = null, $x = 0, $y = 0)
    {
        if ($query !== null) {
            $this->query($query);
        }
        if (!empty($this->last_result[$y])) {
            $values = array_values(get_object_vars($this->last_result[$y]));
            return $values[$x] ?? null;
        }
        return null;
    }

    public function get_row($query = null, $output = OBJECT, $y = 0)
    {
        if ($query !== null) {
            $this->query($query);
        }
        if (!isset($this->last_result[$y])) {
            return null;
        }
        $row = $this->last_result[$y];
        return match ($output) {
            ARRAY_A => get_object_vars($row),
            ARRAY_N => array_values(get_object_vars($row)),
            default => $row,
        };
    }

    public function get_col($query = null, $x = 0)
    {
        if ($query !== null) {
            $this->query($query);
        }
        $out = [];
        foreach ((array) $this->last_result as $row) {
            $values = array_values(get_object_vars($row));
            $out[] = $values[$x] ?? null;
        }
        return $out;
    }

    public function get_results($query = null, $output = OBJECT)
    {
        if ($query !== null) {
            $this->query($query);
        }
        return \Minn\Support\Lists::shapeRows((array) $this->last_result, (string) $output);
    }

    public function get_col_info($info_type = 'name', $col_offset = -1)
    {
        return [];
    }

    public function insert($table, $data, $format = null)
    {
        return $this->_insert_replace_helper($table, $data, $format, 'INSERT');
    }

    public function replace($table, $data, $format = null)
    {
        return $this->_insert_replace_helper($table, $data, $format, 'REPLACE');
    }

    private function _insert_replace_helper($table, $data, $format, $type)
    {
        $data = $this->process_fields($table, $data, $format);
        if ($data === false) {
            return false;
        }
        $fields = [];
        $values = [];
        foreach ($data as $field => $value) {
            $fields[] = "`{$field}`";
            $values[] = $value['value'] === null ? 'NULL' : $this->prepare($value['format'], $value['value']);
        }
        $this->check_current_query = false;
        $sql = "{$type} INTO `{$table}` (" . implode(', ', $fields) . ') VALUES (' . implode(', ', $values) . ')';
        return $this->query($sql);
    }

    public function update($table, $data, $where, $format = null, $where_format = null)
    {
        if (!is_array($data) || !is_array($where)) {
            return false;
        }
        $data = $this->process_fields($table, $data, $format);
        $where = $this->process_fields($table, $where, $where_format);
        if ($data === false || $where === false) {
            return false;
        }
        $sets = [];
        foreach ($data as $field => $value) {
            $sets[] = "`{$field}` = " . ($value['value'] === null ? 'NULL' : $this->prepare($value['format'], $value['value']));
        }
        $conditions = [];
        foreach ($where as $field => $value) {
            $conditions[] = $value['value'] === null ? "`{$field}` IS NULL" : "`{$field}` = " . $this->prepare($value['format'], $value['value']);
        }
        return $this->query("UPDATE `{$table}` SET " . implode(', ', $sets) . ' WHERE ' . implode(' AND ', $conditions));
    }

    public function delete($table, $where, $where_format = null)
    {
        if (!is_array($where)) {
            return false;
        }
        $where = $this->process_fields($table, $where, $where_format);
        if ($where === false) {
            return false;
        }
        $conditions = [];
        foreach ($where as $field => $value) {
            $conditions[] = $value['value'] === null ? "`{$field}` IS NULL" : "`{$field}` = " . $this->prepare($value['format'], $value['value']);
        }
        return $this->query("DELETE FROM `{$table}` WHERE " . implode(' AND ', $conditions));
    }

    protected function process_fields($table, $data, $format)
    {
        $formats = (array) $format;
        $original = $formats;
        $out = [];
        foreach ((array) $data as $field => $value) {
            if (is_array($value) && isset($value['value']) && isset($value['format'])) {
                $out[$field] = $value;
                continue;
            }
            $out[$field] = ['value' => $value, 'format' => $this->next_format($formats, $original, $value)];
        }
        return $out;
    }

    /** The next given format (a single one repeats for every field), else one read from the value's type. */
    private function next_format(array &$formats, array $original, $value): string
    {
        if ($formats === []) {
            return is_int($value) ? '%d' : (is_float($value) ? '%f' : '%s');
        }
        $format = (string) array_shift($formats);
        if ($formats === [] && count($original) === 1) {
            $formats = $original;
        }
        return $format;
    }

    public function flush()
    {
        $this->last_result = [];
        $this->col_info = null;
        $this->last_query = null;
        $this->rows_affected = 0;
        $this->num_rows = 0;
        $this->last_error = '';
    }

    public function print_error($str = '')
    {
        if ($str === '') {
            $str = $this->last_error;
        }
        if (!$this->suppress_errors && $str !== '') {
            error_log("WordPress database error {$str} for query {$this->last_query}");
        }
        if ($this->show_errors) {
            echo '<div id="error"><p class="wpdberror"><strong>WordPress database error:</strong> [' . esc_html($str) . "]<br /><code>" . esc_html((string) $this->last_query) . '</code></p></div>';
        }
        return false;
    }

    public function show_errors($show = true)
    {
        $errors = $this->show_errors;
        $this->show_errors = (bool) $show;
        return $errors;
    }

    public function hide_errors()
    {
        $show = $this->show_errors;
        $this->show_errors = false;
        return $show;
    }

    public function suppress_errors($suppress = true)
    {
        $errors = $this->suppress_errors;
        $this->suppress_errors = (bool) $suppress;
        return $errors;
    }

    public function check_connection($allow_bail = true)
    {
        return true;
    }

    public function db_connect($allow_bail = true)
    {
        return true;
    }

    public function db_version()
    {
        return preg_replace('/[^0-9.].*/', '', $this->dbh->server_info);
    }

    public function db_server_info()
    {
        return $this->dbh->server_info;
    }

    public function get_charset_collate()
    {
        $charset_collate = '';
        if ($this->charset) {
            $charset_collate = "DEFAULT CHARACTER SET {$this->charset}";
        }
        if ($this->collate) {
            $charset_collate .= " COLLATE {$this->collate}";
        }
        return $charset_collate;
    }

    public function has_cap($db_cap)
    {
        return in_array(strtolower((string) $db_cap), ['collation', 'group_concat', 'subqueries', 'set_charset', 'utf8mb4', 'utf8mb4_520', 'identifier_placeholders'], true);
    }

    public function get_table_charset($table)
    {
        return $this->charset;
    }

    public function get_col_charset($table, $column)
    {
        return $this->charset;
    }

    public function get_col_length($table, $column)
    {
        return false;
    }

    public function strip_invalid_text_for_column($table, $column, $value)
    {
        return $value;
    }

    public function set_sql_mode($modes = [])
    {
    }

    public function set_charset($dbh, $charset = null, $collate = null)
    {
    }

    public function timer_start()
    {
        return true;
    }

    public function timer_stop()
    {
        return 0.0;
    }

    public function bail($message, $error_code = '500')
    {
        return false;
    }

    public function close()
    {
        return true;
    }

    public function __get($name)
    {
        if ($name === 'col_info') {
            return $this->col_info;
        }
        return null;
    }
}
