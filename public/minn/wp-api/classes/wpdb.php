<?php

use Minn\Runtime\WpdbFields;
use Minn\Runtime\WpdbReads;
use Minn\Runtime\WpdbSetup;

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
    protected $table_charset = [];
    protected $check_current_query = true;
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
        WpdbSetup::construct($this, $dbuser, $dbpassword, $dbname, $dbhost);
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

    /** The site this object answers for, its tables named from the base prefix (Minn\Runtime\WpdbSetup::blog). The old id. */
    public function set_blog_id($blog_id, $network_id = 0)
    {
        return WpdbSetup::blog($this, $blog_id, $network_id);
    }

    /** Switches the connection to a database; one it cannot select leaves ready false (Minn\Runtime\WpdbSetup::select). */
    public function select($db, $dbh = null)
    {
        WpdbSetup::select($this, $db, $dbh);
    }

    /** A DB_HOST read into host, port, socket and whether the host is IPv6 (Minn\Support\SqlTable::host); false when nothing parses. */
    public function parse_db_host($host)
    {
        return \Minn\Support\SqlTable::host((string) $host) ?? false;
    }

    /** The table prefix and the tables named from it (Minn\Runtime\WpdbSetup::prefix): the base prefix it had, or invalid_db_prefix. */
    public function set_prefix($prefix, $set_table_names = true)
    {
        return WpdbSetup::prefix($this, $prefix, $set_table_names);
    }

    /** A scope's tables, prefixed unless asked not to (Minn\Runtime\WpdbSetup::tables). */
    public function tables($scope = 'all', $prefix = true, $blog_id = 0)
    {
        return WpdbSetup::tables($this, $scope, $prefix);
    }

    public function get_blog_prefix($blog_id = null)
    {
        return $this->prefix;
    }

    /** A value escaped for a query, its literal % as the placeholder escape that query() takes back out (probe audit-lows). */
    public function _real_escape($data)
    {
        return $this->add_placeholder_escape($this->dbh->real_escape_string((string) $data));
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
        // New for every request, so text that names it cannot come back as a %.
        static $placeholder = null;
        return $placeholder ??= '{' . bin2hex(random_bytes(32)) . '}';
    }

    public function add_placeholder_escape($query)
    {
        return str_replace('%', $this->placeholder_escape(), (string) $query);
    }

    /** A query with its placeholders filled and its notices reported (Minn\Runtime\WpdbReads::prepare). */
    public function prepare($query, ...$args)
    {
        return WpdbReads::prepare($this, $query, $args);
    }

    /** Runs a query (Minn\Runtime\WpdbReads::query): rows read or written, true, or false. */
    public function query($query)
    {
        return WpdbReads::query($this, $query);
    }

    /** One value of a query or of the last result; null for an empty one (Minn\Runtime\WpdbReads::value). */
    public function get_var($query = null, $x = 0, $y = 0)
    {
        return WpdbReads::value($this, $query, $x, $y);
    }

    /** One row of a query; null with no query (Minn\Runtime\WpdbReads::row). */
    public function get_row($query = null, $output = OBJECT, $y = 0)
    {
        return WpdbReads::row($this, $query, $output, $y);
    }

    /** One column of a query or of the last result, read through get_var (Minn\Runtime\WpdbReads::column). */
    public function get_col($query = null, $x = 0)
    {
        return WpdbReads::column($this, $query, $x);
    }

    /** The rows of a query in an output shape; null with no query (Minn\Runtime\WpdbReads::rows). */
    public function get_results($query = null, $output = OBJECT)
    {
        return WpdbReads::rows($this, $query, $output);
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

    /** An INSERT or REPLACE of processed fields, prepared once (Minn\Runtime\WpdbFields::insert). */
    public function _insert_replace_helper($table, $data, $format = null, $type = 'INSERT')
    {
        return WpdbFields::insert($this, $table, $data, $format, $type);
    }

    /** Updates the rows a where list matches, prepared once (Minn\Runtime\WpdbFields::update). */
    public function update($table, $data, $where, $format = null, $where_format = null)
    {
        return WpdbFields::update($this, $table, $data, $where, $format, $where_format);
    }

    /** Deletes the rows a where list matches, prepared once (Minn\Runtime\WpdbFields::delete). */
    public function delete($table, $where, $where_format = null)
    {
        return WpdbFields::delete($this, $table, $where, $where_format);
    }

    /** The fields with their formats, column charsets and lengths, or false naming each that would not fit (Minn\Runtime\WpdbFields::process). */
    protected function process_fields($table, $data, $format)
    {
        return WpdbFields::process($this, $table, $data, $format);
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

    /** Each field with its column's charset (false for a column that holds no text), or false for a table that cannot be read. */
    protected function process_field_charsets($data, $table)
    {
        return WpdbFields::charsets($this, $data, $table);
    }

    /** Each field with its column's length (get_col_length), or false for a table that cannot be read. */
    protected function process_field_lengths($data, $table)
    {
        return WpdbFields::lengths($this, $data, $table);
    }

    /** Each text value cut to what its column keeps (Minn\Runtime\WpdbFields::strip). */
    protected function strip_invalid_text($data)
    {
        return WpdbFields::strip($this, $data);
    }

    /** A query with what its table, or the connection, cannot hold dropped (Minn\Runtime\WpdbFields::stripQuery). */
    protected function strip_invalid_text_from_query($query)
    {
        return WpdbFields::stripQuery($this, $query);
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

    /** Whether a query can run without the text check (Minn\Runtime\WpdbFields::safeCollation). */
    protected function check_safe_collation($query)
    {
        return WpdbFields::safeCollation($this, $query);
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

    /** Reports a database error: $EZSQL_ERROR, the log and, while shown, the page (Minn\Runtime\WpdbReads::printError). */
    public function print_error($str = '')
    {
        echo WpdbReads::printError($this, $str);
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

    /** Takes the engine's connection and sets its names (Minn\Runtime\WpdbSetup::connect). */
    public function db_connect($allow_bail = true)
    {
        return WpdbSetup::connect($this);
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

    /** The charset a table's text columns share, or the charset failure (Minn\Runtime\WpdbFields::tableCharset). */
    protected function get_table_charset($table)
    {
        return WpdbFields::tableCharset($this, $table);
    }

    /** A column's charset: false for one that holds no text, the table's for a column it does not have (Minn\Runtime\WpdbFields::columnCharset). */
    public function get_col_charset($table, $column)
    {
        return WpdbFields::columnCharset($this, $table, $column);
    }

    /** The most a text or binary column holds (Minn\Runtime\WpdbFields::columnLength). */
    public function get_col_length($table, $column)
    {
        return WpdbFields::columnLength($this, $table, $column);
    }

    /** A value cut to what one column keeps (strip_invalid_text). */
    public function strip_invalid_text_for_column($table, $column, $value)
    {
        $field = ['value' => $value, 'charset' => $this->get_col_charset($table, $column), 'length' => $this->get_col_length($table, $column)];
        return $this->strip_invalid_text([$column => $field])[$column]['value'];
    }

    /** The session's SQL modes, without the incompatible ones (Minn\Runtime\WpdbSetup::sqlMode). */
    public function set_sql_mode($modes = [])
    {
        WpdbSetup::sqlMode($this, $modes);
    }

    /** A connection's charset and names (Minn\Runtime\WpdbSetup::charset). */
    public function set_charset($dbh, $charset = null, $collate = null)
    {
        WpdbSetup::charset($this, $dbh, $charset, $collate);
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
