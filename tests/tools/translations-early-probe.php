<?php
/**
 * The translations an early screen loads, in English (probe
 * translations-early): wp_load_translations_early() as before init (init's
 * count set aside), with no WPLANG. Its hooks are recorded in order, with the
 * strings it translates, and whether the locale object is new. It runs once
 * per process, so a second call does nothing. Init's count and the locale
 * object are put back. The probes translations-early-wplang and
 * translations-early-after-init each take a first call of their own.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
global $wp_actions, $wp_locale;
$init = $wp_actions['init'] ?? null;
$locale = $wp_locale;
$call = static function (): array {
    global $wp_locale;
    $before = $wp_locale;
    $hooks = [];
    $listen = static function (...$args) use (&$hooks) {
        $hook = current_filter();
        $hooks[] = in_array($hook, ['gettext', 'gettext_with_context'], true) ? [$hook, ...array_slice($args, 1)] : $hook;
        return $args[0] ?? null;
    };
    add_filter('all', $listen);
    $returned = wp_load_translations_early();
    remove_filter('all', $listen);
    return ['returned' => $returned, 'hooks' => $hooks, 'locale object' => $wp_locale === $before ? 'the same' : get_class($wp_locale) . ', new', 'default text domain loaded' => is_textdomain_loaded('default')];
};

unset($wp_actions['init']);
$say('before init, the first call', $call());
$say('a second call', $call());

$wp_locale = $locale;
if ($init !== null) {
    $wp_actions['init'] = $init;
}

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
