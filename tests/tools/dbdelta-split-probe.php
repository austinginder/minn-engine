<?php
/**
 * How dbDelta() cuts a string of queries, as the reference does it (probe
 * dbdelta-split): Elementor Pro joins three CREATE TABLE statements with
 * nothing between one's semicolon and the next; others end each on its own
 * line, add a trailing semicolon, or leave blank queries between. Asked
 * with $execute false, so it only says what it would do; nothing is written.
 */

global $wpdb;
$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
$t = $wpdb->prefix . 'zz_probe_split_';
$table = static fn (string $name): string => "CREATE TABLE {$t}{$name} (\n  id bigint(20) unsigned NOT NULL auto_increment,\n  label varchar(60) NULL,\n  PRIMARY KEY  (id)\n)";
$mask = static fn (array $out): array => array_values(array_map(static fn ($line) => str_replace($t, '{t}', (string) $line), $out));
$say('joined with nothing between', $mask(dbDelta($table('a') . ';' . $table('b') . ';' . $table('c') . ';', false)));
$say('one per line', $mask(dbDelta($table('a') . ";\n" . $table('b') . ";\n", false)));
$say('no trailing semicolon', $mask(dbDelta($table('a') . ';' . $table('b'), false)));
$say('blank queries between', $mask(dbDelta($table('a') . ';;  ;' . $table('b') . ';', false)));
$say('as a list', $mask(dbDelta([$table('a'), $table('b')], false)));
$say('a semicolon inside a default', $mask(dbDelta("CREATE TABLE {$t}d (\n  id bigint(20) unsigned NOT NULL auto_increment,\n  sep varchar(5) NOT NULL default ';',\n  PRIMARY KEY  (id)\n);", false)));
$say('an existing table, a column added', $mask(dbDelta("CREATE TABLE {$wpdb->prefix}options (\n  option_id bigint(20) unsigned NOT NULL auto_increment,\n  zz_probe_extra varchar(10) NULL,\n  PRIMARY KEY  (option_id)\n);" . $table('e') . ';', false)));

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
