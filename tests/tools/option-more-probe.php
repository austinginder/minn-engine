<?php
/**
 * option.php's remaining functions (probe option-more): several options read
 * at once, autoload set for several, the autoload value an option is stored
 * with (the given one, or what wp_default_autoload_value and the size rule
 * decide), the site-option cache primers and core site options (a single
 * site), and the per-user settings (user-settings and its time) set, read,
 * deleted. The probe's options are removed and the admin's settings put back.
 * Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$options = ['zz_go_a', 'zz_go_b', 'zz_go_c', 'zz_go_site', 'zz_go_big'];
$settings = [get_user_option('user-settings', 1), get_user_option('user-settings-time', 1)];
register_shutdown_function(static function () use ($options, $settings): void {
    foreach ($options as $option) {
        delete_option($option);
    }
    foreach (['user-settings', 'user-settings-time'] as $i => $key) {
        $settings[$i] === false ? delete_user_option(1, $key) : update_user_option(1, $key, $settings[$i]);
    }
});
global $wpdb;
$stored = static fn (string $option) => $wpdb->get_var($wpdb->prepare("SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", $option));

add_option('zz_go_a', ['x' => 1, 'y' => [2, 3]]);
add_option('zz_go_b', 'plain', '', 'off');
add_option('zz_go_c', '', '', true);
$say('options read together', [
    get_options(['zz_go_a', 'zz_go_b', 'zz_go_c', 'zz_go_missing']),
    get_options([]),
    get_options(['zz_go_b', 'zz_go_b']),
]);
$say('autoload stored', array_map($stored, ['zz_go_a', 'zz_go_b', 'zz_go_c']));
$say('autoload set for several', [
    wp_set_options_autoload(['zz_go_a', 'zz_go_b', 'zz_go_missing'], 'off'),
    array_map($stored, ['zz_go_a', 'zz_go_b']),
    wp_set_options_autoload(['zz_go_a', 'zz_go_b'], true),
    array_map($stored, ['zz_go_a', 'zz_go_b']),
    wp_set_options_autoload(['zz_go_a'], 'yes'),
    wp_set_options_autoload(['zz_go_a'], 'no'),
    $stored('zz_go_a'),
    wp_set_options_autoload([], 'on'),
]);

$decide = static fn ($autoload) => wp_determine_option_autoload_value('zz_go_x', 'value', 'value', $autoload);
$say('autoload decided', array_map(static fn ($autoload) => [$autoload, $decide($autoload)], ['yes', 'no', true, false, 'on', 'off', null, 'auto', 'auto-on', 'auto-off', 'junk', 1, 0, '']));
$heard = [];
foreach ([true, false, null, 'on'] as $answer) {
    $default = static function ($autoload, $option, $value, $serialized) use ($answer, &$heard) {
        $heard[] = [$autoload, $option, $value, $serialized];
        return $answer;
    };
    add_filter('wp_default_autoload_value', $default, 5, 4);
    $decided[] = [$answer, wp_determine_option_autoload_value('zz_go_y', ['v'], 'a:1:{i:0;s:1:"v";}', null), wp_determine_option_autoload_value('zz_go_y', 'v', 'v', 'on')];
    remove_filter('wp_default_autoload_value', $default, 5);
}
$say('autoload decided by filter', [$decided, $heard]);
$big = str_repeat('x', 150001);
$say('autoload by size', [
    wp_filter_default_autoload_value_via_option_size(null, 'zz_go_x', 'small', 'small'),
    wp_filter_default_autoload_value_via_option_size(true, 'zz_go_x', 'small', 'small'),
    wp_filter_default_autoload_value_via_option_size(null, 'zz_go_x', $big, $big),
    wp_filter_default_autoload_value_via_option_size(true, 'zz_go_x', $big, $big),
    wp_filter_default_autoload_value_via_option_size(null, 'zz_go_x', str_repeat('x', 150000), str_repeat('x', 150000)),
    wp_determine_option_autoload_value('zz_go_x', $big, $big, null),
    has_filter('wp_default_autoload_value', 'wp_filter_default_autoload_value_via_option_size'),
]);
$max = static fn () => 10;
add_filter('wp_max_autoloaded_option_size', $max);
$say('autoload by size, a smaller limit', [wp_filter_default_autoload_value_via_option_size(null, 'zz_go_x', 'twelve chars', 'twelve chars'), wp_filter_default_autoload_value_via_option_size(null, 'zz_go_x', 'ten chars!', 'ten chars!')]);
remove_filter('wp_max_autoloaded_option_size', $max);
add_option('zz_go_big', $big);
$say('a big option is stored without autoload', $stored('zz_go_big'));

// The primers fill the options cache; the engine keeps option reads in its own store rather than in
// wp_cache's options group, so what is compared is what they return and what reads back after.
add_site_option('zz_go_site', 'site value');
$say('site option caches primed', [
    wp_prime_site_option_caches(['zz_go_site', 'zz_go_nope']),
    wp_prime_network_option_caches(null, ['zz_go_site']),
    wp_prime_network_option_caches(5, ['zz_go_site']),
    wp_load_core_site_options(),
    wp_load_core_site_options(1),
    get_site_option('zz_go_site'),
    get_site_option('zz_go_nope', 'fallback'),
]);

wp_set_current_user(1);
delete_user_option(1, 'user-settings');
delete_user_option(1, 'user-settings-time');
$time = static fn () => ($t = get_user_option('user-settings-time', 1)) === false ? false : (is_numeric($t) && abs((int) $t - time()) < 60);
$steps = [];
$steps[] = ['set', set_user_setting('zz_a', 'one'), get_user_option('user-settings', 1), $time()];
$steps[] = ['set another', set_user_setting('zz_b', 'two words'), get_user_option('user-settings', 1)];
$steps[] = ['read', get_all_user_settings(), get_user_setting('zz_a'), get_user_setting('zz_missing', 'fallback')];
$steps[] = ['set a bad name', set_user_setting('bad name!', 'v-a_l.ue~'), get_user_option('user-settings', 1)];
$steps[] = ['delete one', delete_user_setting('zz_a'), get_user_option('user-settings', 1)];
$steps[] = ['delete a missing one', delete_user_setting('zz_never'), get_user_option('user-settings', 1)];
$steps[] = ['delete several', delete_user_setting(['zz_b', 'badname']), get_user_option('user-settings', 1)];
$steps[] = ['set all', wp_set_all_user_settings(['zz_c' => 'three', 'Mixed Key' => 'v v', 7 => 'num']), get_user_option('user-settings', 1)];
$steps[] = ['set all, nothing', wp_set_all_user_settings([]), get_user_option('user-settings', 1)];
$steps[] = ['delete all', delete_all_user_settings(), get_user_option('user-settings', 1), $time()];
wp_set_current_user(0);
$steps[] = ['a visitor', set_user_setting('zz_v', 'x'), wp_set_all_user_settings(['zz_v' => 'x']), delete_user_setting('zz_v')];
$say('user settings', $steps);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
