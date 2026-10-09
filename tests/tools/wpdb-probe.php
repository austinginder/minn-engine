<?php
/**
 * The database class (probe wpdb): $wpdb's prepare (each placeholder, numbered
 * arguments, a list, the wrong number), escaping and identifiers, reads in
 * every output shape, insert/replace/update/delete with formats, the
 * counters and column info, the table a query names, table names by scope,
 * the host parser, charsets and capabilities, and the error switches.
 * Everything written goes to a TEMPORARY table, gone with the connection.
 * Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
global $wpdb;
$escape = $wpdb->placeholder_escape();
$mask = static function ($value) use (&$mask, $escape) {
    return is_array($value) ? array_map($mask, $value) : (is_string($value) ? str_replace($escape, '{escape}', $value) : $value);
};
$wrong = [];
add_action('doing_it_wrong_run', static function ($function, $message) use (&$wrong): void {
    $wrong[] = [$function, strip_tags((string) $message)];
}, 10, 2);
add_filter('doing_it_wrong_trigger_error', '__return_false');
add_action('deprecated_function_run', static function ($function, $replacement, $version) use (&$wrong): void {
    $wrong[] = ['deprecated', $function, $replacement, $version];
}, 10, 3);
add_filter('deprecated_function_trigger_error', '__return_false');
$heard = static function (Closure $call) use (&$wrong) {
    $wrong = [];
    $out = $call();
    return [$out, $wrong];
};
// Protected methods are part of the class's surface: called through reflection on both stacks.
$inside = static fn (string $method, ...$args) => (new ReflectionMethod($wpdb, $method))->invoke($wpdb, ...$args);
$say('the class', [get_class($wpdb) === wpdb::class, is_string($wpdb->prefix), $wpdb->charset, $wpdb->collate, $wpdb->get_charset_collate(), $wpdb->field_types, $wpdb->queries === null ? null : gettype($wpdb->queries)]);

$say('prepare', $mask([
    $wpdb->prepare('SELECT %s, %d, %f, %F', "it's", '12abc', '1.5', 2),
    $wpdb->prepare('SELECT %1$s, %2$d, %1$s', 'a', 7),
    $wpdb->prepare('SELECT %s, %s', ['x', 'y']),
    $wpdb->prepare('LIKE %s', '%' . $wpdb->esc_like('50%_off') . '%'),
    $wpdb->prepare('SELECT 100%%, %s', 'v'),
    $wpdb->prepare('SELECT %5s|%-5s|%05d', 'ab', 'cd', 42),
    $wpdb->prepare('SELECT * FROM %i WHERE %i = %s', 'zz table', 'col`name', 'v'),
    $wpdb->prepare("SELECT '%s'", 'quoted'),
    $wpdb->prepare('SELECT %s', null),
    $wpdb->prepare('SELECT %d', true),
    $wpdb->prepare('SELECT %s', 'a\\b'),
]));
$say('prepare done wrong', $mask([
    $heard(static fn () => $wpdb->prepare('SELECT 1')),
    $heard(static fn () => $wpdb->prepare('SELECT %s, %s', 'only one')),
    $heard(static fn () => $wpdb->prepare('SELECT %s', 'one', 'two')),
    $heard(static fn () => $wpdb->prepare('SELECT %s', ['nested' => ['x']])),
    $heard(static fn () => $wpdb->prepare('SELECT %x', 'v')),
    $heard(static fn () => $wpdb->prepare(null, 'v')),
    $heard(static fn () => $wpdb->prepare('SELECT %1$s, %2$d, %1$s', 'a', 7)),
    $heard(static fn () => $wpdb->prepare('SELECT %s', new stdClass())),
]));
$say('escaping', $mask([
    $wpdb->esc_like('a_b%c\\d'),
    $wpdb->_escape("it's \"q\" \\ x"),
    $wpdb->_escape(['a' => "o'k", 'b' => ['c' => "n'est"]]),
    $wpdb->_real_escape("x'y"),
    $wpdb->_real_escape(5),
    $heard(static fn () => $wpdb->escape("x'y")),
    $heard(static fn () => $wpdb->_weak_escape("x'y")),
    (static function () use ($wpdb) {
        $value = "r'ef";
        $wpdb->escape_by_ref($value);
        return $value;
    })(),
    $wpdb->quote_identifier('zz`table'),
    $wpdb->add_placeholder_escape('100%'),
    $wpdb->remove_placeholder_escape($wpdb->add_placeholder_escape('50% and %s')),
    strlen($escape) > 60 && str_starts_with($escape, '{') && str_ends_with($escape, '}'),
]));

$wpdb->suppress_errors(true);
$wpdb->query('DROP TEMPORARY TABLE IF EXISTS zz_wpdb_probe');
$made = $wpdb->query('CREATE TEMPORARY TABLE zz_wpdb_probe (id bigint unsigned NOT NULL AUTO_INCREMENT, name varchar(20) NOT NULL, n int DEFAULT NULL, f float DEFAULT NULL, note text, PRIMARY KEY (id), UNIQUE KEY name (name)) DEFAULT CHARSET=utf8mb4');
$t = 'zz_wpdb_probe';
$writes = [$made];
$writes[] = [$wpdb->insert($t, ['name' => 'one', 'n' => 1, 'f' => 1.5, 'note' => "it's"]), $wpdb->insert_id, $wpdb->rows_affected];
$writes[] = [$wpdb->insert($t, ['name' => 'two', 'n' => '2x', 'note' => null], ['%s', '%d', '%s']), $wpdb->insert_id];
$writes[] = [$wpdb->insert($t, ['name' => 'three', 'n' => 3], '%s'), $wpdb->insert_id];
$writes[] = [$wpdb->insert($t, ['name' => 'one', 'n' => 9]), $wpdb->last_error !== ''];
$writes[] = [$wpdb->replace($t, ['id' => 1, 'name' => 'uno', 'n' => 11]), $wpdb->rows_affected];
$writes[] = [$wpdb->update($t, ['n' => 22, 'note' => 'updated'], ['name' => 'two']), $wpdb->rows_affected];
$writes[] = [$wpdb->update($t, ['n' => 22], ['name' => 'two']), $wpdb->rows_affected];
$writes[] = [$wpdb->update($t, ['n' => 5], ['name' => 'nobody']), $wpdb->rows_affected];
$writes[] = [$wpdb->update($t, ['note' => 'by null'], ['note' => null])];
$writes[] = [$wpdb->delete($t, ['name' => 'three']), $wpdb->rows_affected];
$writes[] = [$wpdb->delete($t, ['n' => '22'], ['%d']), $wpdb->rows_affected];
$writes[] = [$wpdb->insert($t, ['name' => 'too long for twenty chars here', 'n' => 4]), $wpdb->last_error];
$writes[] = [$wpdb->insert($t, ['name' => "bad \xC3 byte", 'n' => 4]), $wpdb->last_error, $wpdb->get_var("SELECT name FROM $t WHERE n = 4")];
$wpdb->delete($t, ['n' => 4]);
$writes[] = [$wpdb->insert($t, []), $wpdb->update($t, [], ['id' => 1]), $wpdb->delete($t, [])];
$wpdb->insert($t, ['name' => 'four', 'n' => 4, 'f' => 0.25]);
$wpdb->insert($t, ['name' => 'five', 'n' => null]);
$say('writes', $writes);

$say('reads', [
    $wpdb->get_var("SELECT name FROM $t ORDER BY id"),
    $wpdb->get_var("SELECT name, n FROM $t ORDER BY id", 1, 1),
    $wpdb->get_var("SELECT name FROM $t WHERE id = 0"),
    $wpdb->get_var(),
    $wpdb->get_row("SELECT * FROM $t ORDER BY id"),
    $wpdb->get_row("SELECT * FROM $t ORDER BY id", ARRAY_A, 1),
    $wpdb->get_row("SELECT * FROM $t ORDER BY id", ARRAY_N),
    $wpdb->get_row("SELECT * FROM $t WHERE id = 0"),
    $wpdb->get_row("SELECT * FROM $t ORDER BY id", OBJECT, 10),
    $wpdb->get_col("SELECT name FROM $t ORDER BY id"),
    $wpdb->get_col("SELECT name, n FROM $t ORDER BY id", 1),
    $wpdb->get_results("SELECT id, name FROM $t ORDER BY id"),
    $wpdb->get_results("SELECT id, name FROM $t ORDER BY id", ARRAY_A),
    $wpdb->get_results("SELECT id, name FROM $t ORDER BY id", ARRAY_N),
    $wpdb->get_results("SELECT name, n FROM $t ORDER BY id", OBJECT_K),
    $wpdb->get_results("SELECT * FROM $t WHERE id = 0"),
    $wpdb->get_results("SELECT * FROM $t WHERE id = 0", ARRAY_A),
]);
$counters = [];
$counters[] = [$wpdb->query("SELECT * FROM $t"), $wpdb->num_rows, count($wpdb->last_result)];
$counters[] = [$wpdb->query("UPDATE $t SET n = n + 1 WHERE n IS NOT NULL"), $wpdb->rows_affected];
$counters[] = [$wpdb->query("INSERT INTO $t (name) VALUES ('six')"), $wpdb->insert_id, $wpdb->last_query];
$counters[] = [$wpdb->query("SELECT * FROM zz_wpdb_no_such_table"), $wpdb->last_error !== '', $wpdb->num_rows];
$counters[] = [$wpdb->query('SHOW TABLES LIKE ' . $wpdb->prepare('%s', $t)), $wpdb->num_rows];
$wpdb->get_results("SELECT id, name, n, f, note FROM $t LIMIT 1");
$counters[] = [$wpdb->get_col_info('name'), $wpdb->get_col_info('name', 1), $wpdb->get_col_info('max_length', 0), $wpdb->get_col_info('type', 2), $wpdb->get_col_info('table')];
$wpdb->flush();
$counters[] = [$wpdb->last_result, $wpdb->last_query, $wpdb->last_error, $wpdb->col_info ?? null, $wpdb->num_rows];
$say('counters and columns', $counters);
$wpdb->query("DROP TEMPORARY TABLE IF EXISTS $t");
$wpdb->suppress_errors(false);

$say('the table a query names', array_map(static fn ($q) => $inside('get_table_from_query', $q), [
    "SELECT * FROM {$wpdb->posts} WHERE 1",
    "SELECT * FROM `{$wpdb->posts}` p JOIN {$wpdb->postmeta} m",
    "INSERT INTO {$wpdb->options} (a) VALUES (1)",
    "REPLACE INTO `x`.`y_table` VALUES (1)",
    "UPDATE LOW_PRIORITY {$wpdb->users} SET a = 1",
    "DELETE FROM {$wpdb->comments} WHERE 1",
    "SHOW COLUMNS FROM {$wpdb->terms}",
    "SHOW TABLE STATUS LIKE 'wp\\_%'",
    "DESCRIBE {$wpdb->links}",
    "ALTER TABLE {$wpdb->termmeta} ADD x int",
    "CREATE TABLE IF NOT EXISTS zz_new (id int)",
    "DROP TABLE zz_gone",
    "TRUNCATE TABLE zz_trunc",
    "SELECT 1",
    "  select *  from   zz_spaced   where 1",
    "(SELECT * FROM zz_paren)",
    "SELECT * FROM zz_a, zz_b",
    "SELECT COUNT(*) FROM zz_count WHERE id IN (1,2)",
    "SELECT * FROM (SELECT * FROM zz_inner) AS t",
    "SELECT a FROM zz_where WHERE b = (SELECT c FROM zz_sub)",
    "EXPLAIN SELECT * FROM zz_explain",
    "EXPLAIN EXTENDED SELECT * FROM zz_extended",
    "INSERT IGNORE INTO zz_ignore VALUES (1)",
    "INSERT LOW_PRIORITY INTO zz_low VALUES (1)",
    "INSERT zz_nointo VALUES (1)",
    "REPLACE DELAYED INTO zz_delayed VALUES (1)",
    "UPDATE IGNORE zz_upd_ignore SET a = 1",
    "DELETE LOW_PRIORITY QUICK IGNORE FROM zz_del_all WHERE 1",
    "DELETE zz_alias FROM zz_del_join zz_alias JOIN zz_other",
    "SHOW FULL TABLES LIKE 'zz\\_full%'",
    "SHOW TABLES LIKE \"zz_double\"",
    "SHOW TABLE STATUS WHERE Name = 'zz_status'",
    "SHOW TABLES WHERE Name = \"zz_tables_where\"",
    "SHOW TABLES",
    "SHOW INDEX FROM zz_index",
    "SHOW KEYS IN zz_keys",
    "SHOW FULL COLUMNS FROM zz_fullcols",
    "SHOW CREATE TABLE zz_create",
    "DESC zz_desc",
    "CREATE TEMPORARY TABLE zz_temp (id int)",
    "DROP TEMPORARY TABLE IF EXISTS zz_droptemp",
    "LOCK TABLE zz_lock WRITE",
    "OPTIMIZE TABLE zz_opt",
    "REPAIR TABLE zz_repair",
    "CHECK TABLE zz_check",
    "ANALYZE TABLE zz_analyze",
    "RENAME TABLE zz_from TO zz_to",
    "LOAD DATA INFILE 'x' INTO TABLE zz_load",
    "SET NAMES utf8mb4",
    "SELECT * FROM zz-dash",
    "SELECT * FROM zz_\u{e9}t\u{e9}",
    "select * from `zz back`",
    "SELECT * FROM zz_db.zz_tbl",
    "",
]));
$say('internals', [
    $inside('process_field_formats', ['name' => 'a', 'n' => 3, 'f' => 1.5, 'x' => ['value' => 'v', 'format' => '%d']], ['%s', '%d']),
    $inside('process_field_formats', ['a' => 1, 'b' => 2], '%d'),
    $inside('process_field_formats', ['a' => 1, 'b' => 'two', 'c' => 3.5], null),
    $inside('process_field_charsets', ['post_title' => ['value' => 'x', 'format' => '%s'], 'ID' => ['value' => 1, 'format' => '%d']], $wpdb->posts),
    $inside('process_field_lengths', ['post_title' => ['value' => 'x', 'format' => '%s', 'charset' => 'utf8mb4'], 'post_status' => ['value' => 'publish', 'format' => '%s', 'charset' => 'utf8mb4']], $wpdb->posts),
    $inside('check_safe_collation', "SELECT * FROM {$wpdb->posts}"),
    $inside('check_safe_collation', 'SELECT 1'),
    $inside('check_safe_collation', "SELECT * FROM {$wpdb->posts} WHERE post_title = 'caf\u{e9}'"),
    $inside('strip_invalid_text', ['post_title' => ['value' => "ok \xC3 bad", 'charset' => 'utf8mb4', 'length' => false], 'n' => ['value' => 5, 'charset' => false, 'length' => false]]),
    is_string($inside('get_caller')),
    (static function () use ($wpdb) {
        $old = $wpdb->set_blog_id(1);
        return [$old, $wpdb->blogid, $wpdb->prefix, $wpdb->set_blog_id($old)];
    })(),
    (static function () use ($wpdb) {
        $was = [$wpdb->charset, $wpdb->collate];
        $wpdb->init_charset();
        $now = [$wpdb->charset, $wpdb->collate];
        [$wpdb->charset, $wpdb->collate] = $was;
        return $now;
    })(),
    (static function () use ($wpdb) {
        $was = $wpdb->queries;
        $wpdb->log_query('SELECT 1', 0.5, 'caller', 100.0, ['zz' => 'data']);
        $out = [$was === null ? null : count($was), is_array($wpdb->queries) ? end($wpdb->queries) : $wpdb->queries];
        $wpdb->queries = $was;
        return $out;
    })(),
    (static function () use ($wpdb, $inside) {
        $wpdb->get_results("SELECT ID, post_title FROM {$wpdb->posts} LIMIT 1");
        $wpdb->col_info = null;
        $inside('load_col_info');
        return is_array($wpdb->col_info) ? array_map(static fn ($c) => $c->name, $wpdb->col_info) : $wpdb->col_info;
    })(),
    $wpdb->select(DB_NAME),
    $wpdb->ready,
]);
$say('table names', [
    array_keys($wpdb->tables('all', false)),
    array_values(array_slice($wpdb->tables('blog'), 0, 3)),
    array_keys($wpdb->tables('global')),
    $wpdb->tables('ms_global'),
    $wpdb->tables('old', false),
    $wpdb->get_blog_prefix(),
    $wpdb->get_blog_prefix(1),
    $wpdb->get_blog_prefix(0),
]);
$say('hosts parsed', array_map([$wpdb, 'parse_db_host'], ['localhost', 'localhost:3307', '127.0.0.1:3306', '[::1]:3306', '[::1]', 'localhost:/tmp/mysql.sock', '/tmp/mysql.sock', 'db.example.com:x', ':3306', '']));
$say('charsets', [
    $wpdb->determine_charset('utf8', 'utf8_general_ci'),
    $wpdb->determine_charset('utf8mb4', ''),
    $wpdb->determine_charset('latin1', 'latin1_swedish_ci'),
    $wpdb->determine_charset('utf8', ''),
    $wpdb->get_charset_collate() !== '',
    $inside('get_table_charset', $wpdb->posts),
    $wpdb->get_col_charset($wpdb->posts, 'post_title'),
    $wpdb->get_col_charset($wpdb->posts, 'ID'),
    $wpdb->get_col_charset($wpdb->posts, 'nope'),
    $wpdb->get_col_length($wpdb->posts, 'post_title'),
    $wpdb->get_col_length($wpdb->posts, 'post_status'),
    $wpdb->get_col_length($wpdb->posts, 'ID'),
    $inside('check_ascii', 'plain'),
    $inside('check_ascii', "caf\u{e9}"),
    $wpdb->strip_invalid_text_for_column($wpdb->posts, 'post_title', "ok \u{1F600} text"),
    $inside('strip_invalid_text_from_query', "SELECT 'ok'"),
]);
$say('capabilities', [
    array_map([$wpdb, 'has_cap'], ['collation', 'group_concat', 'subqueries', 'set_charset', 'utf8mb4', 'utf8mb4_520', 'identifier_placeholders', 'nothing']),
    (bool) preg_match('/^\d+\.\d+/', (string) $wpdb->db_version()),
    $wpdb->db_server_info() !== '',
    $heard(static fn () => $wpdb->supports_collation()),
    $wpdb->check_database_version(),
    $wpdb->check_connection(false),
]);
$say('error switches', [
    $wpdb->show_errors(), $wpdb->show_errors(false), $wpdb->hide_errors(), $wpdb->hide_errors(),
    $wpdb->suppress_errors(true), $wpdb->suppress_errors(false), $wpdb->suppress_errors(),
    $wpdb->timer_start(), is_float($wpdb->timer_stop()),
    isset($wpdb->col_meta), isset($wpdb->zz_nothing),
]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
