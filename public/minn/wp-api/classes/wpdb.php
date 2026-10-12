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
    protected $col_meta = [];
    public $queries;
    public $time_start = null;
    protected $last_fields = null;
    public $prefix = '';
    public $base_prefix;
    public $ready = true;
    public $blogid = 0;
    public $siteid = 0;
    public $tables = ['posts', 'comments', 'links', 'options', 'postmeta', 'terms', 'term_taxonomy', 'term_relationships', 'termmeta', 'commentmeta'];
    public $old_tables = ['categories', 'post2cat', 'link2cat'];
    public $global_tables = ['users', 'usermeta'];
    public $ms_global_tables = ['blogs', 'blogmeta', 'signups', 'site', 'sitemeta', 'registration_log'];
    public $old_ms_global_tables = ['sitecategories'];
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
        $this->init_charset();
        $this->set_prefix(Runtime::current()->db->prefix());
    }

    /** The connection's charset and collation, from DB_CHARSET and DB_COLLATE as determine_charset settles them. */
    public function init_charset()
    {
        $settled = $this->determine_charset(defined('DB_CHARSET') ? (string) DB_CHARSET : 'utf8', defined('DB_COLLATE') ? (string) DB_COLLATE : '');
        [$this->charset, $this->collate] = [$settled['charset'], $settled['collate']];
    }

    /** utf8 becomes utf8mb4 (the server has it), utf8mb4 gets its unicode collation, and the 520 one when the server has that (probe wpdb). */
    public function determine_charset($charset, $collate)
    {
        if (in_array($charset, ['utf8', 'utf8mb3'], true) && $this->has_cap('utf8mb4')) {
            $charset = 'utf8mb4';
        }
        if ($charset === 'utf8mb4') {
            $collate = !$collate || $collate === 'utf8_general_ci' ? 'utf8mb4_unicode_ci' : str_replace('utf8_', 'utf8mb4_', (string) $collate);
        }
        if ($collate === 'utf8mb4_unicode_ci' && $this->has_cap('utf8mb4_520')) {
            $collate = 'utf8mb4_unicode_520_ci';
        }
        return ['charset' => $charset, 'collate' => $collate];
    }

    /** The site this object answers for; its tables keep one site's prefix here. The old id. */
    public function set_blog_id($blog_id, $network_id = 0)
    {
        $old = $this->blogid;
        $this->blogid = (int) $blog_id;
        if (!empty($network_id)) {
            $this->siteid = (int) $network_id;
        }
        return $old;
    }

    /** Switches the connection to a database; ready stays true while it can. */
    public function select($db, $dbh = null)
    {
        $dbh ??= $this->dbh;
        if (!$dbh->select_db((string) $db)) {
            $this->ready = false;
        }
    }

    /** A DB_HOST read into host, port, socket and whether the host is IPv6 (Minn\Support\SqlTable::host); false when nothing parses. */
    public function parse_db_host($host)
    {
        return \Minn\Support\SqlTable::host((string) $host) ?? false;
    }

    public function set_prefix($prefix, $set_table_names = true)
    {
        $old = $this->prefix;
        $this->prefix = $prefix;
        $this->base_prefix = $prefix;
        // The network's tables are named only on a multisite install; on one site they stay empty (probe placeholders-a).
        if ($set_table_names) {
            foreach (array_merge($this->tables, $this->global_tables, is_multisite() ? $this->ms_global_tables : []) as $table) {
                $this->{$table} = $prefix . $table;
            }
        }
        return $old;
    }

    public function tables($scope = 'all', $prefix = true, $blog_id = 0)
    {
        $tables = match ($scope) {
            'blog' => $this->tables,
            'global' => array_merge($this->global_tables, is_multisite() ? $this->ms_global_tables : []),
            'ms_global' => $this->ms_global_tables,
            'old' => is_multisite() ? array_merge($this->old_tables, $this->old_ms_global_tables) : $this->old_tables,
            default => array_merge($this->global_tables, is_multisite() ? $this->ms_global_tables : [], $this->tables),
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

    /** Slashes added: deprecated since 3.6.0. */
    public function escape($data)
    {
        _deprecated_function(__METHOD__, '3.6.0', 'wpdb::prepare() or esc_sql()');
        return $this->weak_escaped($data);
    }

    /** Slashes added to a string: deprecated since 3.6.0. */
    public function _weak_escape($data)
    {
        _deprecated_function(__METHOD__, '3.6.0', 'wpdb::prepare() or esc_sql()');
        return $this->weak_escaped($data);
    }

    private function weak_escaped($data)
    {
        return is_array($data) ? array_map([$this, 'weak_escaped'], $data) : addslashes((string) $data);
    }

    /** An identifier in backticks, any backtick in it doubled. */
    public function quote_identifier($identifier)
    {
        return '`' . str_replace('`', '``', (string) $identifier) . '`';
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

    /**
     * A query with its placeholders filled (Minn\Runtime\Placeholders), every
     * literal % left as the placeholder escape that query() takes back out.
     * A query with no placeholder, or a count that does not match the
     * arguments, or an argument it cannot use, is reported through
     * _doing_it_wrong; too few arguments empty it (probe wpdb).
     */
    public function prepare($query, ...$args)
    {
        if ($query === null) {
            return null;
        }
        if (isset($args[0]) && is_array($args[0]) && count($args) === 1) {
            $args = array_values($args[0]);
        }
        $prepared = Placeholders::prepare((string) $query, $args, fn (string $value): string => $this->_real_escape($value));
        foreach ($prepared['unsupported'] as $type) {
            _doing_it_wrong('wpdb::prepare', sprintf(__('Unsupported value type (%s).'), $type), '4.8.2');
        }
        if ($prepared['placeholders'] === 0 && $args === []) {
            _doing_it_wrong('wpdb::prepare', sprintf(__('The query argument of %s must have a placeholder.'), 'wpdb::prepare()'), '3.9.0');
        } elseif ($prepared['placeholders'] !== count($args)) {
            _doing_it_wrong('wpdb::prepare', sprintf(__('The query does not contain the correct number of placeholders (%1$d) for the number of arguments passed (%2$d).'), $prepared['placeholders'], count($args)), '4.8.3');
        }
        return $prepared['query'] === null ? '' : $this->add_placeholder_escape($prepared['query']);
    }

    public function query($query)
    {
        $this->flush();
        $query = apply_filters('query', $this->remove_placeholder_escape((string) $query));
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
            // The engine's request-long row reads hear plugin writes too.
            \Minn\Db::wrote($query);
            if (preg_match('/^\s*(insert|replace)\s/i', $query)) {
                $this->insert_id = (int) $this->dbh->insert_id;
            }
            return $this->rows_affected;
        }
        if ($result instanceof \mysqli_result) {
            $this->col_info = $this->last_fields = $result->fetch_fields();
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

    /** Something about each column the last query returned (its name, table, type...), or about one by offset. */
    public function get_col_info($info_type = 'name', $col_offset = -1)
    {
        if (!$this->col_info) {
            return null;
        }
        if ((int) $col_offset === -1) {
            return array_map(static fn (object $column) => $column->{$info_type} ?? null, array_values((array) $this->col_info));
        }
        return $this->col_info[(int) $col_offset]->{$info_type} ?? null;
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
        if ($data === false || $data === []) {
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

    /**
     * The fields with their formats, column charsets and lengths. False, with
     * last_error naming the field, when a value would not fit its column
     * unchanged (probe wpdb).
     */
    protected function process_fields($table, $data, $format)
    {
        $data = $this->process_field_lengths($this->process_field_charsets($this->process_field_formats($data, $format), $table), $table);
        foreach ($this->strip_invalid_text($data) as $field => $value) {
            if ($value['value'] !== $data[$field]['value']) {
                $this->last_error = sprintf(__('WordPress database error: Processing the value for the following field failed: %s. The supplied value may be too long or contains invalid data.'), $field);
                return false;
            }
        }
        return $data;
    }

    /** Each field's value with its format: the next one given (the first once they run out), else the field's known type, else %s. */
    protected function process_field_formats($data, $format)
    {
        $formats = (array) $format;
        $given = $formats;
        $out = [];
        foreach ((array) $data as $field => $value) {
            $form = $given === [] ? ($this->field_types[$field] ?? '%s') : (array_shift($formats) ?? $given[0]);
            $out[$field] = ['value' => $value, 'format' => $form];
        }
        return $out;
    }

    /** Each field with its column's charset (false for a column that holds no text). */
    protected function process_field_charsets($data, $table)
    {
        foreach ((array) $data as $field => $value) {
            $data[$field]['charset'] = in_array($value['format'], ['%d', '%f'], true) ? false : $this->get_col_charset($table, $field);
        }
        return $data;
    }

    /** Each field with its column's length (get_col_length). */
    protected function process_field_lengths($data, $table)
    {
        foreach ((array) $data as $field => $value) {
            $data[$field]['length'] = in_array($value['format'], ['%d', '%f'], true) ? false : $this->get_col_length($table, $field);
        }
        return $data;
    }

    /** Each text value cut to what its column keeps: malformed bytes dropped, four-byte characters too for utf8, then cut to the column's length. */
    protected function strip_invalid_text($data)
    {
        foreach ((array) $data as $field => $value) {
            if (!is_string($value['value'] ?? null) || empty($value['charset'])) {
                continue;
            }
            $kept = \Minn\Support\Utf8::keptBy($value['value'], (string) $value['charset']);
            $length = $value['length'] ?? false;
            if (is_array($length)) {
                $kept = $length['type'] === 'byte' ? mb_strcut($kept, 0, (int) $length['length'], 'UTF-8') : mb_substr($kept, 0, (int) $length['length'], 'UTF-8');
            }
            $data[$field]['value'] = $kept;
        }
        return $data;
    }

    /** A query with what its table cannot hold dropped; an ASCII query, or one on a utf8mb4 table, comes back as it was. */
    protected function strip_invalid_text_from_query($query)
    {
        if ($this->check_ascii($query)) {
            return $query;
        }
        $table = $this->get_table_from_query($query);
        $charset = $table ? $this->get_table_charset($table) : false;
        return in_array($charset, ['utf8', 'utf8mb3'], true) ? \Minn\Support\Utf8::keptBy((string) $query, (string) $charset) : $query;
    }

    /** The table a query names (Minn\Support\SqlTable::of), false when none. */
    protected function get_table_from_query($query)
    {
        return \Minn\Support\SqlTable::of((string) $query) ?? false;
    }

    /** Whether text is plain ASCII. */
    protected function check_ascii($input_string)
    {
        return !preg_match('/[^\x00-\x7F]/', (string) $input_string);
    }

    /** Whether a query can run without charset checks: it is ASCII, or its table's text columns all use a binary or general collation. */
    protected function check_safe_collation($query)
    {
        if ($this->check_ascii($query)) {
            return true;
        }
        $table = $this->get_table_from_query($query);
        if (!$table) {
            return false;
        }
        foreach ($this->columns($table) as $column) {
            if ($column['collation'] !== '' && !preg_match('/^utf8(?:mb[34])?_(?:bin|general_ci)$/', $column['collation'])) {
                return false;
            }
        }
        return true;
    }

    /** The last query's column info, read again from what it returned. */
    protected function load_col_info()
    {
        $this->col_info ??= $this->last_fields;
    }

    /** A query noted in $queries with its time, caller, start and the data log_query_custom_data adds. */
    public function log_query($query, $query_time, $query_callstack, $query_start, $query_data)
    {
        $query_data = apply_filters('log_query_custom_data', $query_data, $query, $query_time, $query_callstack, $query_start);
        $this->queries[] = [$query, $query_time, $query_callstack, $query_start, $query_data];
    }

    /** The calls that led here, as wp_debug_backtrace_summary gives them. */
    public function get_caller()
    {
        return wp_debug_backtrace_summary(__CLASS__);
    }

    /** Null while the server is new enough; an error naming the version WordPress needs otherwise. */
    public function check_database_version()
    {
        $required = $GLOBALS['required_mysql_version'] ?? '5.5.5';
        return version_compare((string) $this->db_version(), $required, '<') ? new WP_Error('database_version', sprintf(__('<strong>Error:</strong> WordPress %1$s requires MySQL %2$s or higher'), $GLOBALS['wp_version'] ?? '', $required)) : null;
    }

    /** has_cap('collation'): deprecated since 3.5.0. */
    public function supports_collation()
    {
        _deprecated_function(__FUNCTION__, '3.5.0', "wpdb::has_cap( 'collation' )");
        return $this->has_cap('collation');
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

    /** The charset a table's text columns share (the connection's when it has none or the table is unknown). */
    protected function get_table_charset($table)
    {
        foreach ($this->columns($table) as $column) {
            if ($column['collation'] !== '') {
                return explode('_', $column['collation'])[0];
            }
        }
        return $this->charset;
    }

    /** A column's charset: false for one that holds no text, the table's for a column it does not have. */
    public function get_col_charset($table, $column)
    {
        $found = $this->columns($table)[strtolower((string) $column)] ?? null;
        if ($found === null) {
            return $this->get_table_charset($table);
        }
        return $found['collation'] === '' ? false : explode('_', $found['collation'])[0];
    }

    /** The most a text or binary column holds: characters for char and varchar, bytes for the binary, blob and text types; false for any other column. */
    public function get_col_length($table, $column)
    {
        return \Minn\Db::columnLength($this->columns($table)[strtolower((string) $column)]['type'] ?? '');
    }

    /** @return array<string, array{type: string, collation: string}> a table's columns by lower-cased name, read once */
    private function columns($table): array
    {
        $table = strtolower((string) $table);
        if (!isset($this->col_meta[$table])) {
            $this->col_meta[$table] = [];
            foreach ((array) $this->get_results('SHOW FULL COLUMNS FROM `' . str_replace('`', '', $table) . '`') as $row) {
                $this->col_meta[$table][strtolower((string) $row->Field)] = ['type' => (string) $row->Type, 'collation' => (string) ($row->Collation ?? '')];
            }
        }
        return $this->col_meta[$table];
    }

    /** A value cut to what one column keeps (strip_invalid_text). */
    public function strip_invalid_text_for_column($table, $column, $value)
    {
        $field = ['value' => $value, 'charset' => $this->get_col_charset($table, $column), 'length' => $this->get_col_length($table, $column)];
        return $this->strip_invalid_text([$column => $field])[$column]['value'];
    }

    /**
     * The session's SQL modes: the given ones, or the session's own when none
     * are, without the modes WordPress cannot run under
     * (incompatible_sql_modes filters the list).
     */
    public function set_sql_mode($modes = [])
    {
        $current = empty($modes) ? (string) $this->get_var('SELECT @@SESSION.sql_mode') : implode(',', (array) $modes);
        $incompatible = (array) apply_filters('incompatible_sql_modes', \Minn\Db::INCOMPATIBLE_MODES);
        \Minn\Db::current()->setModes(\Minn\Db::compatibleModes($current, array_map('strval', $incompatible)));
    }

    public function set_charset($dbh, $charset = null, $collate = null)
    {
    }

    public function timer_start()
    {
        $this->time_start = microtime(true);
        return true;
    }

    /** The seconds since timer_start. */
    public function timer_stop()
    {
        return microtime(true) - (float) ($this->time_start ?? microtime(true));
    }

    public function bail($message, $error_code = '500')
    {
        return false;
    }

    public function close()
    {
        return true;
    }

    /** A protected member read from outside (col_meta, table_charset...). */
    public function __get($name)
    {
        if ($name === 'col_info') {
            $this->load_col_info();
        }
        return property_exists($this, $name) ? $this->$name : null;
    }

    public function __set($name, $value)
    {
        $this->$name = $value;
    }

    public function __isset($name)
    {
        return isset($this->$name);
    }

    public function __unset($name)
    {
        unset($this->$name);
    }
}
