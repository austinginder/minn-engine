<?php
/**
 * The media player's scripts and styles as the reference registers them
 * (wp_default_scripts, wp_default_styles): MediaElement.js's handles, their
 * sources, versions, dependencies and footer group; mejsL10n in the language
 * determine_locale names, its strings translated; _wpmejsSettings through
 * mejs_settings and the shortcode library filters; and the hooks a fresh
 * registry asks on the way. Extras are compared by what they hold (the
 * engine keeps empty before and after lists), the site address by its host
 * (WP-CLI forces the reference's scheme). Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$site = static fn (string $text): string => (string) preg_replace('#https?://' . preg_quote((string) parse_url(home_url(), PHP_URL_HOST), '#') . '#', '{site}', $text);
$extra = static fn (array $extra): array => array_map(static fn ($v) => is_array($v) ? array_map($site, array_values(array_filter($v))) : $site((string) $v), array_filter($extra));
foreach (['scripts' => wp_scripts(), 'styles' => wp_styles()] as $kind => $registry) {
    foreach ($kind === 'scripts' ? ['mediaelement-core', 'mediaelement-migrate', 'mediaelement', 'mediaelement-vimeo', 'wp-mediaelement'] : ['mediaelement', 'wp-mediaelement'] as $handle) {
        $item = $registry->registered[$handle] ?? null;
        $say("{$kind}: {$handle}", $item === null ? null : ['src' => $item->src, 'deps' => $item->deps, 'ver' => $item->ver, 'extra' => $extra((array) $item->extra), 'group' => $item->args]);
    }
}
foreach (['pt_BR', 'de_CH_informal', 'es'] as $locale) {
    $heard = [];
    $listen = function () use (&$heard) {
        $hook = current_filter();
        if (preg_match('/^(pre_determine_locale|determine_locale|mejs_settings|wp_(audio|video)_shortcode_library)$/', $hook)) {
            $heard[] = $hook;
        }
        return func_num_args() > 0 ? func_get_arg(0) : null;
    };
    $pick = static fn () => $locale;
    $settings = static fn (array $s): array => $s + ['zzPlayer' => 'set'];
    add_filter('all', $listen);
    add_filter('pre_determine_locale', $pick);
    add_filter('mejs_settings', $settings);
    $registry = new WP_Scripts();
    remove_filter('mejs_settings', $settings);
    remove_filter('pre_determine_locale', $pick);
    remove_filter('all', $listen);
    preg_match('/"language":"([^"]*)"/', (string) end($registry->registered['mediaelement-core']->extra['before']), $language);
    $say("a fresh registry in {$locale}: the player's language, its settings and the hooks asked", [$language[1] ?? null, $site((string) ($registry->registered['mediaelement']->extra['data'] ?? '')), $heard]);
}
echo json_encode($log);
