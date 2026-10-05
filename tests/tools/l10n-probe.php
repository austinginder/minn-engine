<?php
/**
 * Translations: loading a text domain from a .mo or .l10n.php file, which
 * format wins, how domains merge and unload, plural forms (Polish, Arabic,
 * a broken expression), the gettext classes plugins build on, just-in-time
 * loading's neighbour (plugin text domains), and the files the domains read. The language files
 * are the fixtures in tests/fixtures/languages; anything copied into the
 * site's folders is removed again. Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$fx = dirname(__DIR__) . '/fixtures/languages';
$plain = static fn ($v) => is_string($v) ? str_replace([$fx, WP_LANG_DIR, WP_PLUGIN_DIR], ['{fx}', '{lang}', '{plugins}'], $v) : $v;
$deep = static function ($v) use (&$deep, $plain) {
    return is_array($v) ? array_map($deep, $v) : (is_object($v) ? get_class($v) : $plain($v));
};
add_filter('doing_it_wrong_trigger_error', '__return_false');
$spied = [];
foreach (['override_load_textdomain', 'pre_load_textdomain', 'load_textdomain_mofile', 'load_translation_file', 'translation_file_format', 'override_unload_textdomain', 'plugin_locale', 'load_script_translation_file', 'pre_load_script_translations', 'load_script_translations'] as $hook) {
    add_filter($hook, static function () use ($hook, &$spied, $deep) {
        $args = func_get_args();
        $spied[] = [$hook, $deep($args)];
        return $args[0] ?? null;
    }, 10, 9);
}
foreach (['load_textdomain', 'unload_textdomain', 'doing_it_wrong_run'] as $hook) {
    add_action($hook, static function () use ($hook, &$spied, $deep): void {
        $spied[] = [$hook, $deep(array_map(static fn ($a) => is_string($a) ? strip_tags($a) : $a, func_get_args()))];
    }, 10, 9);
}
$caught = static function () use (&$spied): array {
    $out = $spied;
    $spied = [];
    return $out;
};
$counts = [0, 1, 2, 3, 4, 5, 11, 12, 14, 21, 22, 25, 101, 102, 111, 112, 1000000];

// --- Loading: the .mo path is asked for; a .l10n.php beside it is what the reference reads.
$say('load_textdomain', [is_textdomain_loaded('minn-probe'), load_textdomain('minn-probe', "{$fx}/minn-probe-pl_PL.mo"), is_textdomain_loaded('minn-probe'), $caught()]);
$say('singular lookups', [
    __('Hello', 'minn-probe'),
    __('Only in the mo', 'minn-probe'),
    __('Only in the php', 'minn-probe'),
    __('Untranslated here', 'minn-probe'),
    __('Not in the file', 'minn-probe'),
    __('Post', 'minn-probe'),
    _x('Post', 'verb', 'minn-probe'),
    _x('Post', 'noun', 'minn-probe'),
    _x('Post', 'unknown', 'minn-probe'),
    __("Line\nbreak", 'minn-probe'),
    esc_html__('Hello', 'minn-probe'),
    __('Hello', 'minn-probe-not-loaded'),
]);
$say('plural lookups', array_map(static fn (int $n): array => [$n, sprintf(_n('%d comment', '%d comments', $n, 'minn-probe'), $n), sprintf(_nx('%d file', '%d files', $n, 'files', 'minn-probe'), $n), _n('%d dog', '%d dogs', $n, 'minn-probe')], $counts));

// --- The domain's translations object.
$translations = get_translations_for_domain('minn-probe');
$say('get_translations_for_domain', [
    get_class($translations),
    $translations->translate('Hello'),
    $translations->translate('Post', 'verb'),
    $translations->translate('Nope'),
    $translations->translate_plural('%d comment', '%d comments', 5),
    $translations->translate_plural('%d file', '%d files', 2, 'files'),
    $translations->translate_plural('%d dog', '%d dogs', 1),
]);
$unknown = get_translations_for_domain('minn-probe-unknown');
$say('get_translations_for_domain unknown', [get_class($unknown), $unknown->translate('Hello'), $unknown->translate_plural('a', 'b', 2), is_textdomain_loaded('minn-probe-unknown')]);
$caught();

// --- Two files for one domain: the first loaded answers first.
load_textdomain('minn-probe-merge', "{$fx}/minn-probe-extra-pl_PL.mo");
load_textdomain('minn-probe-merge', "{$fx}/minn-probe-pl_PL.mo");
$say('merged domain', [__('Hello', 'minn-probe-merge'), __('Extra', 'minn-probe-merge'), __('Only in the php', 'minn-probe-merge'), _x('Post', 'noun', 'minn-probe-merge'), sprintf(_n('%d comment', '%d comments', 5, 'minn-probe-merge'), 5), sprintf(_n('%d comment', '%d comments', 1, 'minn-probe-merge'), 1)]);
$caught();

// --- The format filter can insist on the .mo.
$preferMo = static fn () => 'mo';
add_filter('translation_file_format', $preferMo, 20);
load_textdomain('minn-probe-mo', "{$fx}/minn-probe-pl_PL.mo");
remove_filter('translation_file_format', $preferMo, 20);
$say('mo format', [__('Hello', 'minn-probe-mo'), __('Only in the mo', 'minn-probe-mo'), __('Only in the php', 'minn-probe-mo'), $caught()]);

// --- A big-endian .mo, an Arabic six-form domain, and a broken plural expression.
load_textdomain('minn-probe-be', "{$fx}/minn-probe-be-pl_PL.mo");
$say('big endian', [__('Hello', 'minn-probe-be'), _x('Post', 'noun', 'minn-probe-be'), sprintf(_n('%d comment', '%d comments', 22, 'minn-probe-be'), 22)]);
load_textdomain('minn-probe-ar', "{$fx}/minn-probe-ar-ar.mo");
$say('arabic plurals', array_map(static fn (int $n): string => sprintf(_n('%d item', '%d items', $n, 'minn-probe-ar'), $n), [0, 1, 2, 3, 10, 11, 99, 100, 101, 102, 103, 111]));
load_textdomain('minn-probe-bad', "{$fx}/minn-probe-bad-pl_PL.mo");
$say('broken plural expression', array_map(static fn (int $n): string => sprintf(_n('%d cat', '%d cats', $n, 'minn-probe-bad'), $n), [0, 1, 2, 5]));
$caught();

// --- A file that is not there.
$say('missing file', [load_textdomain('minn-probe-none', "{$fx}/nope.mo"), is_textdomain_loaded('minn-probe-none'), $caught()]);

// --- Unloading.
$say('unload_textdomain', [unload_textdomain('minn-probe'), is_textdomain_loaded('minn-probe'), __('Hello', 'minn-probe'), unload_textdomain('minn-probe'), $caught()]);
foreach (['minn-probe-merge', 'minn-probe-mo', 'minn-probe-be', 'minn-probe-ar', 'minn-probe-bad'] as $domain) {
    unload_textdomain($domain);
}
$caught();

// --- The MO class plugins extend.
$mo = new MO();
$imported = $mo->import_from_file("{$fx}/minn-probe-pl_PL.mo");
$keys = array_keys($mo->entries);
sort($keys);
$hello = $mo->entries['Hello'] ?? null;
$say('MO', [
    $imported,
    $keys,
    $mo->get_header('Plural-Forms'),
    $mo->headers['Language'] ?? null,
    $mo->translate('Hello'),
    $mo->translate('Post', 'noun'),
    $mo->translate_plural('%d comment', '%d comments', 5),
    $mo->get_plural_forms_count(),
    [$mo->select_plural_form(1), $mo->select_plural_form(3), $mo->select_plural_form(5)],
    $hello ? [get_class($hello), $hello->singular, $hello->plural, $hello->translations, $hello->context, $hello->is_plural] : null,
    $hello ? $hello->key() : null,
]);
$mo->add_entry(new Translation_Entry(['singular' => 'Added', 'translations' => ['Dodany']]));
$mo->add_entry(['singular' => 'Array added', 'context' => 'ctx', 'translations' => ['Z tablicy']]);
$say('MO add_entry', [$mo->translate('Added'), $mo->translate('Array added', 'ctx'), count($mo->entries)]);
$other = new MO();
$other->import_from_file("{$fx}/minn-probe-extra-pl_PL.mo");
$mo->merge_with($other);
$say('MO merge_with', [$mo->translate('Hello'), $mo->translate('Extra'), count($mo->entries)]);
$say('MO missing file', (new MO())->import_from_file("{$fx}/nope.mo"));
$noop = new NOOP_Translations();
$say('NOOP_Translations', [$noop->translate('Hello'), $noop->translate_plural('a', 'b', 1), $noop->translate_plural('a', 'b', 2), $noop->get_plural_forms_count()]);

// Just-in-time loading is not probed here: the reference reads the languages
// folder once per request, so a file copied in during the probe is never
// seen. tests/l10n.test.php stages the files before either stack starts.
$asPolish = static fn () => 'pl_PL';
add_filter('determine_locale', $asPolish);
add_filter('locale', $asPolish);

// --- A plugin's own text domain from its folder.
$pluginDir = WP_PLUGIN_DIR . '/minn-probe-l10n';
@mkdir($pluginDir . '/languages', 0777, true);
copy("{$fx}/minn-probe-pl_PL.mo", "{$pluginDir}/languages/minn-probe-plug-pl_PL.mo");
$say('load_plugin_textdomain', [load_plugin_textdomain('minn-probe-plug', false, 'minn-probe-l10n/languages'), __('Hello', 'minn-probe-plug'), is_textdomain_loaded('minn-probe-plug'), $caught()]);
unload_textdomain('minn-probe-plug');
$say('load_plugin_textdomain missing', [load_plugin_textdomain('minn-probe-plug2', false, 'minn-probe-l10n/languages'), $caught()]);

remove_filter('determine_locale', $asPolish);
remove_filter('locale', $asPolish);
foreach (glob("{$pluginDir}/languages/*") ?: [] as $file) {
    unlink($file);
}
@rmdir("{$pluginDir}/languages");
@rmdir($pluginDir);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
