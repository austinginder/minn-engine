<?php
/**
 * The rest of l10n.php (probe l10n-remaining): translate and its context
 * form (their filters heard, and a filter that translates), _ex and
 * esc_attr_x, the no-op plurals and their translation, the list separator
 * and word count type, text domains loaded just in time and from a child
 * theme or a must-use plugin (none of which have files here), and script
 * translations read from a JSON file (with their filters). The site is in
 * English. The probe's file is removed at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$dir = wp_get_upload_dir()['basedir'] . '/zz-l10n';
register_shutdown_function(static function () use ($dir): void {
    foreach (glob($dir . '/*.json') ?: [] as $file) {
        if (str_contains($file, 'zz-l10n')) {
            unlink($file);
        }
    }
    if (is_dir($dir)) {
        rmdir($dir);
    }
});
$echoed = static function (Closure $call): string {
    ob_start();
    $call();
    return (string) ob_get_clean();
};

$heard = [];
foreach (['gettext', 'gettext_zz-domain', 'gettext_with_context', 'gettext_with_context_zz-domain', 'ngettext', 'ngettext_zz-domain', 'ngettext_with_context'] as $hook) {
    add_filter($hook, $listeners[$hook] = static function (...$args) use ($hook, &$heard) {
        $heard[] = [$hook, ...$args];
        return $args[0];
    }, 10, 5);
}
$plain = [translate('Hello', 'zz-domain'), translate('Hello'), translate_with_gettext_context('Post', 'noun', 'zz-domain'), translate_with_gettext_context('Post', 'verb')];
foreach ($listeners as $hook => $listener) {
    remove_filter($hook, $listener, 10);
}
$say('translate and its filters', [$plain, $heard]);

add_filter('gettext_zz-domain', $swap = static fn ($translation, $text) => $text === 'Hello' ? 'Bonjour' : $translation, 10, 2);
add_filter('gettext_with_context_zz-domain', $swapContext = static fn ($translation, $text, $context) => $context === 'noun' ? 'Article' : $translation, 10, 3);
add_filter('ngettext_zz-domain', $swapPlural = static fn ($translation, $single, $plural, $number) => $number === 1 ? 'un fichier' : "{$number} fichiers", 10, 4);
$translated = [
    translate('Hello', 'zz-domain'), translate_with_gettext_context('Post', 'noun', 'zz-domain'), translate_with_gettext_context('Post', 'verb', 'zz-domain'),
    $echoed(static fn () => _ex('Post', 'noun', 'zz-domain')), $echoed(static fn () => _ex('<b>Post</b>', 'verb', 'zz-domain')),
    esc_attr_x('Post "quoted" <b>', 'noun', 'zz-domain'), esc_attr_x('Post "quoted" <b>', 'verb', 'zz-domain'), esc_attr_x('it\'s & co', 'x'),
];
$nooped = [_n_noop('%s file', '%s files'), _n_noop('%s item', '%s items', 'zz-domain'), _nx_noop('%s post', '%s posts', 'noun'), _nx_noop('%s post', '%s posts', 'noun', 'zz-domain')];
$translated[] = $nooped;
$translated[] = array_map(static fn ($pair) => translate_nooped_plural(...$pair), [
    [$nooped[0], 1], [$nooped[0], 2], [$nooped[0], 0], [$nooped[1], 1], [$nooped[1], 5], [$nooped[1], 5, 'other-domain'], [$nooped[2], 1], [$nooped[2], 3], [$nooped[3], 3], [['singular' => 'a', 'plural' => 'b', 'context' => null, 'domain' => null], 1],
]);
remove_filter('gettext_zz-domain', $swap, 10);
remove_filter('gettext_with_context_zz-domain', $swapContext, 10);
remove_filter('ngettext_zz-domain', $swapPlural, 10);
$say('translations through filters', $translated);

$say('separator and word count', [wp_get_list_item_separator(), wp_get_word_count_type()]);

$domains = [
    _load_textdomain_just_in_time('zz-missing-domain'), _load_textdomain_just_in_time('default'), is_textdomain_loaded('zz-missing-domain'),
    load_child_theme_textdomain('zz-child-domain'), load_child_theme_textdomain('zz-child-domain', get_stylesheet_directory() . '/zz-languages'), is_textdomain_loaded('zz-child-domain'),
    load_muplugin_textdomain('zz-mu-domain'), load_muplugin_textdomain('zz-mu-domain', 'zz-folder'), is_textdomain_loaded('zz-mu-domain'),
];
$heard = [];
add_filter('load_textdomain_mofile', $mofile = static function ($file, $domain) use (&$heard) {
    $heard[] = ['load_textdomain_mofile', str_replace([WP_CONTENT_DIR, get_stylesheet_directory()], ['{content}', '{theme}'], (string) $file), $domain];
    return $file;
}, 10, 2);
add_filter('override_load_textdomain', $override = static function ($override, $domain, $file) use (&$heard) {
    $heard[] = ['override_load_textdomain', $override, $domain];
    return $override;
}, 10, 3);
load_child_theme_textdomain('zz-heard-child');
load_muplugin_textdomain('zz-heard-mu', 'zz-folder');
remove_filter('load_textdomain_mofile', $mofile, 10);
remove_filter('override_load_textdomain', $override, 10);
$domains[] = $heard;
$say('text domains', $domains);

wp_mkdir_p($dir);
$json = '{"translation-revision-date":"2026-01-01","domain":"messages","locale_data":{"messages":{"":{"domain":"messages","lang":"fr"},"Hello":["Bonjour"]}}}';
file_put_contents("{$dir}/zz-l10n-fr.json", $json);
$scripts = [load_script_translations("{$dir}/zz-l10n-fr.json", 'zz-handle', 'zz-domain'), load_script_translations("{$dir}/zz-l10n-missing.json", 'zz-handle', 'zz-domain'), load_script_translations(false, 'zz-handle', 'zz-domain'), load_script_translations('', 'zz-handle', 'zz-domain')];
$heard = [];
add_filter('pre_load_script_translations', $pre = static function ($translations, $file, $handle, $domain) use (&$heard, $dir) {
    $heard[] = ['pre_load_script_translations', $translations, str_replace($dir, '{dir}', (string) $file), $handle, $domain];
    return $handle === 'zz-short' ? '{"short":true}' : $translations;
}, 10, 4);
add_filter('load_script_translation_file', $fileFilter = static function ($file, $handle, $domain) use (&$heard, $dir) {
    $heard[] = ['load_script_translation_file', str_replace($dir, '{dir}', (string) $file), $handle, $domain];
    return $handle === 'zz-moved' ? $dir . '/zz-l10n-fr.json' : $file;
}, 10, 3);
add_filter('load_script_translations', $loaded = static function ($translations, $file, $handle, $domain) use (&$heard, $dir) {
    $heard[] = ['load_script_translations', strlen((string) $translations), str_replace($dir, '{dir}', (string) $file), $handle, $domain];
    return $translations;
}, 10, 4);
$scripts[] = load_script_translations("{$dir}/zz-l10n-fr.json", 'zz-handle', 'zz-domain');
$scripts[] = load_script_translations("{$dir}/zz-l10n-fr.json", 'zz-short', 'zz-domain');
$scripts[] = load_script_translations("{$dir}/zz-l10n-nowhere.json", 'zz-moved', 'zz-domain');
remove_filter('pre_load_script_translations', $pre, 10);
remove_filter('load_script_translation_file', $fileFilter, 10);
remove_filter('load_script_translations', $loaded, 10);
$scripts[] = $heard;
$say('script translations', $scripts);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
