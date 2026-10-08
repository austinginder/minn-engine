<?php
/**
 * What the site-option and site-transient functions tell plugins on a single
 * site (probe site-options): each call's hooks in order, actions and filters
 * told apart, their arguments in words, and its answer. Options and
 * transients named zz_site_* only; they are removed first and at the end.
 * Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$names = ['zz_site_opt', 'zz_site_new', '_site_transient_zz_site_t', '_site_transient_timeout_zz_site_t', '_site_transient_zz_site_x', '_site_transient_timeout_zz_site_x'];
$sweep = static function () use ($names): void {
    foreach ($names as $name) {
        delete_option($name);
    }
};
$sweep();
register_shutdown_function($sweep);

$heard = [];
$listening = false;
add_action('all', static function (string $name) use (&$heard, &$listening): void {
    if (!$listening || !str_contains($name, 'zz_site') && !in_array($name, ['add_option', 'added_option', 'update_option', 'updated_option', 'delete_option', 'deleted_option', 'add_site_option', 'update_site_option', 'delete_site_option', 'set_site_transient', 'deleted_site_transient', 'pre_site_option', 'pre_option', 'pre_update_option'], true)) {
        return;
    }
    $args = array_map(static fn ($v) => is_int($v) && $v > 1000000000 ? 'time' : (is_object($v) ? 'object' : $v), array_slice(func_get_args(), 1));
    if (!str_contains($name, 'zz_site') && !str_contains(json_encode($args), 'zz_site')) {
        return;
    }
    $heard[] = [$name, $args, doing_action($name) && did_action($name) > 0 ? 'action' : 'filter'];
}, PHP_INT_MIN);
$ask = static function (string $label, callable $call) use ($say, &$heard, &$listening): void {
    $heard = [];
    $listening = true;
    $answer = $call();
    $listening = false;
    $say($label, ['answer' => is_object($answer) ? (array) $answer : $answer, 'heard' => $heard]);
};

$ask('get_site_option, missing', static fn () => get_site_option('zz_site_opt'));
$ask('get_site_option, missing, with a default', static fn () => get_site_option('zz_site_opt', 'fallback'));
$ask('add_site_option', static fn () => add_site_option('zz_site_opt', 'one'));
$ask('add_site_option, again', static fn () => add_site_option('zz_site_opt', 'two'));
$ask('get_site_option', static fn () => get_site_option('zz_site_opt'));
$ask('update_site_option, a new value', static fn () => update_site_option('zz_site_opt', 'three'));
$ask('update_site_option, the same value', static fn () => update_site_option('zz_site_opt', 'three'));
$ask('update_site_option, an option not there', static fn () => update_site_option('zz_site_new', 'fresh'));
$ask('delete_site_option', static fn () => delete_site_option('zz_site_opt'));
$ask('delete_site_option, again', static fn () => delete_site_option('zz_site_opt'));
$ask('get_site_transient, missing', static fn () => get_site_transient('zz_site_t'));
$ask('set_site_transient, new, no expiry', static fn () => set_site_transient('zz_site_t', ['a' => 1]));
$ask('set_site_transient, again', static fn () => set_site_transient('zz_site_t', ['a' => 2]));
$ask('get_site_transient', static fn () => get_site_transient('zz_site_t'));
$ask('set_site_transient, an object', static fn () => set_site_transient('zz_site_t', (object) ['b' => 1]));
$ask('delete_site_transient', static fn () => delete_site_transient('zz_site_t'));
$ask('delete_site_transient, again', static fn () => delete_site_transient('zz_site_t'));
$ask('set_site_transient, with an expiry', static fn () => set_site_transient('zz_site_x', 'soon', 3600));
$ask('set_site_transient, with an expiry, again', static fn () => set_site_transient('zz_site_x', 'later', 3600));
$ask('get_site_transient, with an expiry', static fn () => get_site_transient('zz_site_x'));
$ask('delete_site_transient, with an expiry', static fn () => delete_site_transient('zz_site_x'));

// The update transients are read with no timeout looked for.
$timeouts = [];
$watch = static function (string $name) use (&$timeouts): void {
    if (str_contains($name, '_site_transient_timeout_update_')) {
        $timeouts[] = $name;
    }
};
add_action('all', $watch, PHP_INT_MIN);
foreach (['update_core', 'update_plugins', 'update_themes'] as $transient) {
    get_site_transient($transient);
}
remove_action('all', $watch, PHP_INT_MIN);
$say('the update transients look for no timeout', $timeouts);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
