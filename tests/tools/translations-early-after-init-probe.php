<?php
/**
 * The early translations asked for after init (probe
 * translations-early-after-init): a first call to wp_load_translations_early()
 * once init has run does nothing, and marks it done, so a call with init's
 * count set aside does nothing either. Init's count is put back.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
global $wp_actions, $wp_locale;
$init = $wp_actions['init'] ?? null;
$call = static function (): array {
    global $wp_locale;
    $before = $wp_locale;
    $fired = 0;
    $listen = static function (...$args) use (&$fired) {
        $fired++;
        return $args[0] ?? null;
    };
    add_filter('all', $listen);
    $returned = wp_load_translations_early();
    remove_filter('all', $listen);
    return ['returned' => $returned, 'hooks fired' => $fired, 'locale object' => $wp_locale === $before ? 'the same' : 'new'];
};

$wp_actions['init'] = $init ?? 1;
$say('the first call, after init', $call());
unset($wp_actions['init']);
$say('then as before init', $call());

if ($init === null) {
    unset($wp_actions['init']);
} else {
    $wp_actions['init'] = $init;
}

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
