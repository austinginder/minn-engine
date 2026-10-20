<?php
/**
 * The charset a table comes to and what text a query on it may carry
 * (wpdb's get_table_charset and its check before a query): a binary or blob
 * column makes the table binary and nothing is refused; a table without
 * text has none; latin1 beside other charsets does not count, utf8 beside
 * utf8mb4 is utf8, any other mixture ascii; latin1 holds any byte, utf8mb4
 * well-formed UTF-8, utf8 no four-byte character, ascii nothing past ASCII,
 * another charset what it can encode. A Wordfence-style IP column
 * (BINARY(16)) is written and found by its bytes. Same protocol as
 * api-probe.php; the probe's tables (zz_charset_*) are dropped after.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
global $wpdb;
$quiet = $wpdb->suppress_errors(true);
$charset = new ReflectionMethod($wpdb, 'get_table_charset');
$tables = [
    'binary' => 'id INT, ip BINARY(16), note VARCHAR(20)',
    'varbinary' => 'id INT, ip VARBINARY(16), note VARCHAR(20)',
    'blob' => 'id INT, b BLOB, note VARCHAR(20)',
    'numbers' => 'id INT, n BIGINT',
    'latin1' => 'id INT, note VARCHAR(20) CHARACTER SET latin1',
    'utf8' => 'id INT, note VARCHAR(20) CHARACTER SET utf8mb3',
    'utf8mb4' => 'id INT, note TEXT',
    'ascii' => 'id INT, note VARCHAR(20) CHARACTER SET ascii',
    'cp1251' => 'id INT, note VARCHAR(20) CHARACTER SET cp1251',
    'latin1 and utf8mb4' => 'id INT, a VARCHAR(20) CHARACTER SET latin1, b VARCHAR(20) CHARACTER SET utf8mb4',
    'latin1 and utf8' => 'id INT, a VARCHAR(20) CHARACTER SET latin1, b VARCHAR(20) CHARACTER SET utf8mb3',
    'utf8 and utf8mb4' => 'id INT, a VARCHAR(20) CHARACTER SET utf8mb3, b VARCHAR(20) CHARACTER SET utf8mb4',
    'latin1 and latin2' => 'id INT, a VARCHAR(20) CHARACTER SET latin1, b VARCHAR(20) CHARACTER SET latin2',
    'utf8mb4 and ascii' => 'id INT, a VARCHAR(20) CHARACTER SET utf8mb4, b VARCHAR(20) CHARACTER SET ascii',
    'cp1251 and utf8mb4' => 'id INT, a VARCHAR(20) CHARACTER SET cp1251, b VARCHAR(20) CHARACTER SET utf8mb4',
    'latin1 and a blob' => 'id INT, a VARCHAR(20) CHARACTER SET latin1, b BLOB',
];
$texts = ['malformed' => "\xFF", 'four-byte' => "\xF0\x9F\x98\x80", 'e acute' => "\xC3\xA9", 'cyrillic' => "\xD0\x96"];
$n = 0;
foreach ($tables as $label => $columns) {
    $table = $wpdb->prefix . 'zz_charset_' . ++$n;
    $wpdb->query("DROP TABLE IF EXISTS {$table}");
    $wpdb->query("CREATE TABLE {$table} ({$columns}) DEFAULT CHARSET=utf8mb4");
    try {
        $said = $charset->invoke($wpdb, $table);
        $runs = [];
        foreach ($texts as $name => $text) {
            $runs[$name] = $wpdb->query("INSERT INTO {$table} (id) VALUES (1) /* {$text} */");
        }
        $say("a table of {$label}: its charset, and which texts a query runs with", [is_wp_error($said) ? $said->get_error_code() : $said, $runs]);
    } finally {
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
}
$table = $wpdb->prefix . 'zz_charset_ip';
$wpdb->query("DROP TABLE IF EXISTS {$table}");
$wpdb->query("CREATE TABLE {$table} (id INT AUTO_INCREMENT PRIMARY KEY, IP BINARY(16), note VARCHAR(20)) DEFAULT CHARSET=utf8mb4");
try {
    $ip = str_repeat("\0", 10) . "\xFF\xFF\x7F\x00\x00\x01";
    $say('an address of bytes: written by a query', $wpdb->query($wpdb->prepare("INSERT INTO {$table} (IP, note) VALUES (%s, %s)", $ip, 'a')));
    $say('found by its bytes', $wpdb->get_var($wpdb->prepare("SELECT note FROM {$table} WHERE IP = %s", $ip)));
    $say('changed by a query', $wpdb->query($wpdb->prepare("UPDATE {$table} SET note = %s WHERE IP = %s", 'b', $ip)));
    $say('written by wpdb::insert', $wpdb->insert($table, ['IP' => $ip, 'note' => 'c']));
    $say('changed by wpdb::update', $wpdb->update($table, ['note' => 'd'], ['IP' => $ip]));
    $say('a note that is not UTF-8, by a query on the binary table', $wpdb->query($wpdb->prepare("INSERT INTO {$table} (IP, note) VALUES (%s, %s)", 'y', "a\xFFb")));
    $say('the same note by wpdb::insert (its column checked)', $wpdb->insert($table, ['IP' => 'x', 'note' => "a\xFFb"]));
    $say('removed by wpdb::delete', $wpdb->delete($table, ['IP' => $ip]));
    $say('a query naming no table, holding it', $wpdb->get_var("SELECT '\xFF'") === null);
} finally {
    $wpdb->query("DROP TABLE IF EXISTS {$table}");
}
$table = $wpdb->prefix . 'zz_charset_latin1';
$wpdb->query("DROP TABLE IF EXISTS {$table}");
$wpdb->query("CREATE TABLE {$table} (id INT AUTO_INCREMENT PRIMARY KEY, note VARCHAR(20)) DEFAULT CHARSET=latin1");
try {
    $say('a latin1 byte by wpdb::insert into a latin1 table', $wpdb->insert($table, ['note' => "a\xE9b"]));
    $say('kept as written', bin2hex((string) $wpdb->get_var("SELECT note FROM {$table}")));
} finally {
    $wpdb->query("DROP TABLE IF EXISTS {$table}");
}
$wpdb->suppress_errors($quiet);
echo json_encode($log);
