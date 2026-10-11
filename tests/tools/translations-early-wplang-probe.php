<?php
/**
 * The translations an early screen loads for a WPLANG constant (probe
 * translations-early-wplang): wp_load_translations_early() as before init,
 * with WPLANG zz_ZZ and an empty zz_ZZ.mo in both the languages folder and
 * wp-includes/languages. The languages folder's file is loaded, once, into
 * the default text domain; the hooks around the load are recorded with their
 * arguments. A second call, with only the wp-includes file left, does
 * nothing. The files go again; init's count, the locale object and the
 * default text domain are put back.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
global $wp_actions, $wp_locale, $l10n;
$root = static fn ($value) => is_string($value) ? str_replace(rtrim(ABSPATH, '/'), '{root}', $value) : (is_object($value) ? get_class($value) : $value);
$folder = ABSPATH . WPINC . '/languages';
$files = [WP_LANG_DIR . '/zz_ZZ.mo', $folder . '/zz_ZZ.mo'];
$madeFolder = !is_dir($folder);
$tidy = static function () use ($files, $folder, $madeFolder): void {
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    if ($madeFolder && is_dir($folder)) {
        rmdir($folder);
    }
};
register_shutdown_function($tidy);
$init = $wp_actions['init'] ?? null;
$locale = $wp_locale;
$default = $l10n['default'] ?? null;
$call = static function () use ($root): array {
    global $wp_locale;
    $before = $wp_locale;
    $hooks = [];
    $listen = static function (...$args) use (&$hooks, $root) {
        $hook = current_filter();
        if (!str_contains($hook, 'gettext')) {
            $hooks[] = [$hook, ...array_map($root, array_slice($args, 1))];
        }
        return $args[0] ?? null;
    };
    add_filter('all', $listen);
    $returned = wp_load_translations_early();
    remove_filter('all', $listen);
    return ['returned' => $returned, 'hooks besides gettext' => $hooks, 'locale object' => $wp_locale === $before ? 'the same' : get_class($wp_locale) . ', new', 'default text domain loaded' => is_textdomain_loaded('default')];
};

if ($madeFolder) {
    mkdir($folder);
}
foreach ($files as $file) {
    touch($file);
}
define('WPLANG', 'zz_ZZ');
unset($wp_actions['init']);
unload_textdomain('default');
$say('before init, a file in both folders', $call());
unlink($files[0]);
unload_textdomain('default');
$say('a second call, the wp-includes file left', $call());

$tidy();
unload_textdomain('default');
if ($default !== null) {
    $l10n['default'] = $default;
}
$wp_locale = $locale;
if ($init !== null) {
    $wp_actions['init'] = $init;
}

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
