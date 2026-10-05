<?php
/**
 * Translations that depend on files being in place before the request
 * starts: just-in-time loading from the languages folder, the deferred
 * load_plugin_textdomain, theme text domains, the core domain, and locale
 * switching. tests/l10n.test.php stages the files, runs this on both stacks,
 * and diffs the transcripts. Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
add_filter('doing_it_wrong_trigger_error', '__return_false');
$plain = static fn ($v) => is_string($v) ? str_replace([WP_LANG_DIR, WP_PLUGIN_DIR, get_theme_root()], ['{lang}', '{plugins}', '{themes}'], $v) : $v;
$heard = [];
foreach (['load_textdomain', 'unload_textdomain', 'change_locale'] as $hook) {
    add_action($hook, static function () use ($hook, &$heard, $plain): void {
        $args = func_get_args();
        // Only the probe's own domains and the core domain: each stack loads others at boot.
        if ($hook === 'change_locale' || $args[0] === 'default' || str_starts_with((string) $args[0], 'minn-probe')) {
            $heard[] = [$hook, array_map($plain, $args)];
        }
    }, 10, 9);
}
$caught = static function () use (&$heard): array {
    $out = $heard;
    $heard = [];
    return $out;
};

// --- In English nothing loads on its own.
$say('english', [determine_locale(), __('Hello', 'minn-probe-jit'), is_textdomain_loaded('minn-probe-jit'), $caught()]);

// --- In Polish a domain used first is found in the languages folder.
$asPolish = static fn () => 'pl_PL';
add_filter('determine_locale', $asPolish);
$say('just in time plugin', [is_textdomain_loaded('minn-probe-jit'), __('Hello', 'minn-probe-jit'), is_textdomain_loaded('minn-probe-jit'), $caught()]);
$say('just in time theme', [__('Hello', 'minn-probe-theme'), is_textdomain_loaded('minn-probe-theme'), $caught()]);
$say('just in time nothing there', [__('Hello', 'minn-probe-nothing'), is_textdomain_loaded('minn-probe-nothing'), $caught()]);
$say('just in time once', [__('Hello', 'minn-probe-nothing'), $caught()]);

// --- load_plugin_textdomain with no file in the plugin's folder defers to the languages folder.
$say('load_plugin_textdomain deferred', [load_plugin_textdomain('minn-probe-lpt', false, 'minn-probe-l10n-missing'), is_textdomain_loaded('minn-probe-lpt'), __('Hello', 'minn-probe-lpt'), is_textdomain_loaded('minn-probe-lpt'), $caught()]);

// --- A theme's own folder: files there are named by locale alone.
$themeFolder = WP_PLUGIN_DIR . '/minn-probe-l10n-theme';
$say('load_theme_textdomain', [load_theme_textdomain('minn-probe-tt', $themeFolder), is_textdomain_loaded('minn-probe-tt'), __('Hello', 'minn-probe-tt'), $caught()]);
$say('load_theme_textdomain missing', [load_theme_textdomain('minn-probe-tt2', WP_PLUGIN_DIR . '/minn-probe-nowhere'), is_textdomain_loaded('minn-probe-tt2'), __('Hello', 'minn-probe-tt2'), $caught()]);

// --- A folder inside the themes root names its files by locale alone.
$say('load_theme_textdomain in the themes root', [load_theme_textdomain('minn-probe-tr', get_theme_root() . '/minn-probe-l10n-theme'), is_textdomain_loaded('minn-probe-tr'), __('Hello', 'minn-probe-tr'), $caught()]);

// --- A domain that really was loaded, unloaded for good.
$say('unload loaded for good', [__('Hello', 'minn-probe-close'), unload_textdomain('minn-probe-close'), __('Hello', 'minn-probe-close'), is_textdomain_loaded('minn-probe-close'), $caught()]);

// --- Unloading for good, and unloading to reload.
$say('unload for good', [unload_textdomain('minn-probe-jit'), __('Hello', 'minn-probe-jit'), is_textdomain_loaded('minn-probe-jit'), $caught()]);
$say('unload reloadable', [unload_textdomain('minn-probe-theme', true), __('Hello', 'minn-probe-theme'), is_textdomain_loaded('minn-probe-theme'), $caught()]);
remove_filter('determine_locale', $asPolish);

// --- The core domain and the installed languages.
$say('get_available_languages', get_available_languages());
$say('load_default_textdomain', [__('Hello'), load_default_textdomain('pl_PL'), __('Hello'), is_textdomain_loaded('default'), $caught()]);
unload_textdomain('default', true);
$caught();

// --- Switching locale.
$say('switch_to_locale missing', [switch_to_locale('xx_XX'), is_locale_switched(), $caught()]);
$say('switch_to_locale', [switch_to_locale('pl_PL'), is_locale_switched(), determine_locale(), get_locale(), __('Hello'), __('Hello', 'minn-probe-lpt'), $caught()]);
$say('switch_to_locale same', [switch_to_locale('pl_PL'), $caught()]);
$say('restore_previous_locale', [restore_previous_locale(), is_locale_switched(), determine_locale(), __('Hello'), __('Hello', 'minn-probe-lpt'), $caught()]);
$say('restore_previous_locale again', [restore_previous_locale(), $caught()]);
$say('restore_current_locale', [switch_to_locale('pl_PL'), restore_current_locale(), is_locale_switched(), determine_locale(), $caught()]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
