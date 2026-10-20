<?php
/**
 * Text the site's utf8mb4 tables cannot hold (bytes that are not UTF-8):
 * the database door runs no query carrying it, a read included, and the
 * option functions say they stored nothing; four-byte UTF-8 is fine. Each
 * refusal's error is read right after it. Same protocol as api-probe.php;
 * the probe's options (zz_invalid_*) go at the start and the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
global $wpdb;
$sweep = static function () use ($wpdb): void {
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'zz\\_invalid\\_%'");
    wp_cache_flush();
};
$sweep();
$bad = "Caf\xE9";
$quiet = $wpdb->suppress_errors(true);
$refused = static fn ($result): array => [$result, $wpdb->last_error];
$say('wpdb::insert of a value that is not UTF-8', $refused($wpdb->insert($wpdb->options, ['option_name' => 'zz_invalid_a', 'option_value' => $bad, 'autoload' => 'off'])));
$say('wpdb::query of an insert holding it', $refused($wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')", 'zz_invalid_b', $bad))));
$say('a select holding it', $refused($wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", 'zz_invalid_' . "\xE9"))));
$say('add_option of it', add_option('zz_invalid_c', $bad, '', false));
$wpdb->insert($wpdb->options, ['option_name' => 'zz_invalid_d', 'option_value' => 'ok', 'autoload' => 'off']);
$say('wpdb::update to it', $refused($wpdb->update($wpdb->options, ['option_value' => $bad], ['option_name' => 'zz_invalid_d'])));
$say('update_option to it', update_option('zz_invalid_d', $bad));
$say('a transient of it', set_transient('zz_invalid_e', $bad, 60));
$say('four-byte UTF-8 is stored', add_option('zz_invalid_f', "ok \u{1F600}", '', false));
// A transient's timeout is stored before its value is refused; its time is left out.
$say('what was stored', array_map(static fn (array $row): array => str_contains($row['option_name'], '_timeout_') ? ['option_name' => $row['option_name'], 'option_value' => '{time}'] : $row, $wpdb->get_results("SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE '%zz\\_invalid\\_%' ORDER BY option_name", ARRAY_A)));
$wpdb->suppress_errors($quiet);
$sweep();
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
