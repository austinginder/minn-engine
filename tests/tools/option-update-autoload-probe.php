<?php
/**
 * The autoload flag update_option stores (probe option-update-autoload):
 * one given is settled as add_option settles one (true, yes and on store
 * "on"; false, no and off "off"; anything else is left to the site through
 * wp_default_autoload_value and the size limit); with none given, a flag the
 * site settled (auto, auto-on, auto-off) is settled again and any other is
 * kept; an unchanged value writes nothing, its flag included. Recorded for
 * each case: the flag stored after, and the autoload filters heard. The
 * probe's option is removed at the end. Same protocol as api-probe.php.
 */

global $wpdb;
$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$name = 'zz_update_autoload';
register_shutdown_function(static fn () => delete_option($name));
$heard = [];
$watch = static function ($hook) use (&$heard) {
    if (in_array($hook, ['wp_default_autoload_value', 'wp_max_autoloaded_option_size'], true)) {
        $heard[] = $hook;
    }
};
$run = static function (string $stored, $given, string $value = 'b', ?callable $filter = null) use ($wpdb, $name, $watch, &$heard): array {
    delete_option($name);
    if ($wpdb->insert($wpdb->options, ['option_name' => $name, 'option_value' => 'a', 'autoload' => $stored]) !== 1) {
        fwrite(STDERR, "option-update-autoload: the option could not be made\n");
        exit(1);
    }
    wp_cache_delete('alloptions', 'options');
    wp_cache_delete('notoptions', 'options');
    wp_cache_delete($name, 'options');
    if ($filter) {
        add_filter('wp_default_autoload_value', $filter, 10, 4);
    }
    $heard = [];
    add_filter('all', $watch);
    $updated = $given === 'omit' ? update_option($name, $value) : update_option($name, $value, $given);
    remove_filter('all', $watch);
    if ($filter) {
        remove_filter('wp_default_autoload_value', $filter, 10);
    }
    $flag = (string) $wpdb->get_var($wpdb->prepare("SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", $name));
    $listed = array_key_exists($name, wp_load_alloptions());
    delete_option($name);
    return ['updated' => $updated, 'stored' => $flag, 'autoloaded' => $listed, 'heard' => $heard];
};
foreach (['on', 'off', 'yes', 'no', 'auto', 'auto-on', 'auto-off', ''] as $stored) {
    foreach (['none given' => 'omit', 'null' => null, 'true' => true, 'false' => false, "'yes'" => 'yes', "'no'" => 'no', "'on'" => 'on', "'off'" => 'off', "'auto'" => 'auto', "'auto-on'" => 'auto-on', "'auto-off'" => 'auto-off', 'another word' => 'junk'] as $label => $given) {
        $say("stored '{$stored}', {$label}", $run($stored, $given));
    }
}
$say("stored 'auto', a value past the size limit", $run('auto', 'omit', str_repeat('x', 200000)));
$say("stored 'on', a value past the size limit", $run('on', 'omit', str_repeat('x', 200000)));
$say("stored 'auto', the default filter says true", $run('auto', 'omit', 'b', static fn () => true));
$say("stored 'auto', the default filter says false", $run('auto', 'omit', 'b', static fn () => false));
$say("stored 'auto', the same value", $run('auto', 'omit', 'a'));
$say("stored 'auto-on', the same value, false given", $run('auto-on', false, 'a'));
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), "\n";
