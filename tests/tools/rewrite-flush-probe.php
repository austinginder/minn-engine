<?php
/**
 * The stored rewrite rules as the reference keeps them (probe
 * rewrite-flush): wp_rewrite_rules answering the stored option, and
 * making and storing it when it is empty; a soft and a hard flush (the
 * writes to the option, in order, and flush_rewrite_rules_hard); the
 * stored rules' form ($matches[N]); and what $wp_rewrite->matches is left
 * as. The stored option is restored at the end, exactly as it was. Same
 * protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
global $wp_rewrite;
$original = get_option('rewrite_rules');
$shape = static fn ($rules) => is_array($rules) ? [count($rules), array_key_first($rules), reset($rules), array_key_last($rules), end($rules)] : $rules;

$given = static fn () => ['zz-given/?$' => 'index.php?zz=1'];
add_filter('pre_option_rewrite_rules', $given);
$say('the stored rules answer', $shape($wp_rewrite->wp_rewrite_rules()));
remove_filter('pre_option_rewrite_rules', $given);

$writes = [];
$watch = static function ($value, $old) use (&$writes, $shape) {
    $writes[] = $shape($value);
    return $value;
};
$hard = static function ($hard) use (&$writes) {
    $writes[] = ['flush_rewrite_rules_hard', $hard];
    return $hard;
};
add_filter('pre_update_option_rewrite_rules', $watch, 10, 2);
add_filter('flush_rewrite_rules_hard', $hard);

update_option('rewrite_rules', '');
$writes = [];
$rules = $wp_rewrite->wp_rewrite_rules();
$say('empty, so made and stored', [$shape($rules), $writes, $wp_rewrite->matches]);

$writes = [];
$wp_rewrite->matches = '';
$wp_rewrite->flush_rules(false);
$say('a soft flush', [$writes, $wp_rewrite->matches, $shape(get_option('rewrite_rules'))]);

$writes = [];
flush_rewrite_rules();
$say('a hard flush', [$writes, $shape(get_option('rewrite_rules'))]);

remove_filter('pre_update_option_rewrite_rules', $watch, 10);
remove_filter('flush_rewrite_rules_hard', $hard);
update_option('rewrite_rules', $original);
$wp_rewrite->matches = '';
$say('restored', get_option('rewrite_rules') === $original);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
