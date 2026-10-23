<?php
/**
 * Translation files by locale (probe translation-locales): the translation
 * controller with files for one text domain loaded for two locales (and
 * one for both, and one for none, which takes the current locale), each
 * lookup answered only from the files loaded for its locale, in the order
 * they were loaded for it; which locale is current; what is loaded where;
 * a file unloaded for one locale staying for the other; and __() after
 * load_textdomain for each locale. Its files are written to a folder of
 * its own and removed; the controller's locale is put back. Same protocol
 * as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$dir = sys_get_temp_dir() . '/zz-translation-locales-' . getmypid();
@mkdir($dir);
$files = [
    'de' => ['Hello' => 'Hallo', 'Only German' => 'Nur Deutsch'],
    'fr' => ['Hello' => 'Bonjour', 'Only French' => 'Seulement français'],
    'de2' => ['Hello' => 'Servus'],
];
foreach ($files as $name => $messages) {
    file_put_contents("{$dir}/{$name}.l10n.php", '<?php return ' . var_export(['messages' => $messages], true) . ';');
}
$controller = WP_Translation_Controller::get_instance();
$before = $controller->get_locale();
$say('the controller starts in', $before);
$t = static fn (string $text, ?string $locale = null) => $controller->translate($text, '', 'zz-locales', $locale);

$say('loaded for de_DE and fr_FR', [$controller->load_file("{$dir}/de.l10n.php", 'zz-locales', 'de_DE'), $controller->load_file("{$dir}/fr.l10n.php", 'zz-locales', 'fr_FR')]);
$say('looked up by locale', [$t('Hello', 'de_DE'), $t('Hello', 'fr_FR'), $t('Only French', 'de_DE'), $t('Only German', 'fr_FR'), $t('Hello', 'es_ES'), $t('Hello')]);
$controller->set_locale('fr_FR');
$say('the current locale set to fr_FR', [$controller->get_locale(), $t('Hello'), $t('Only German'), $controller->get_entries('zz-locales')]);
$say('loaded where', [$controller->is_textdomain_loaded('zz-locales', 'de_DE'), $controller->is_textdomain_loaded('zz-locales', 'fr_FR'), $controller->is_textdomain_loaded('zz-locales', 'es_ES'), $controller->is_textdomain_loaded('zz-locales')]);
$say('a second de_DE file comes after the first', [$controller->load_file("{$dir}/de2.l10n.php", 'zz-locales', 'de_DE'), $t('Hello', 'de_DE'), $t('Only German', 'de_DE')]);
$say('the German file also loaded for fr_FR comes after the French one', [$controller->load_file("{$dir}/de.l10n.php", 'zz-locales', 'fr_FR'), $t('Hello', 'fr_FR'), $t('Only German', 'fr_FR')]);
$say('the French file unloaded for fr_FR', [$controller->unload_file("{$dir}/fr.l10n.php", 'zz-locales', 'fr_FR'), $t('Hello', 'fr_FR'), $controller->is_textdomain_loaded('zz-locales', 'fr_FR'), $t('Hello', 'de_DE')]);
$say('the German file unloaded with no locale (the first it was loaded for)', [$controller->unload_file("{$dir}/de.l10n.php", 'zz-locales'), $t('Hello', 'fr_FR'), $t('Hello', 'de_DE'), $controller->is_textdomain_loaded('zz-locales', 'fr_FR')]);
$controller->set_locale('de_DE');
$say('loaded with no locale takes the current one', [$controller->load_file("{$dir}/fr.l10n.php", 'zz-locales'), $t('Only French', 'de_DE'), $t('Only French', 'fr_FR')]);
$say('the domain unloaded for de_DE', [$controller->unload_textdomain('zz-locales', 'de_DE'), $t('Hello', 'de_DE'), $controller->is_textdomain_loaded('zz-locales', 'de_DE')]);
$say('the domain unloaded everywhere', [$controller->unload_textdomain('zz-locales'), $controller->unload_textdomain('zz-locales')]);

// Through the global functions: each load_textdomain makes its locale the one __() answers in.
$say('load_textdomain for de_DE, then __()', [load_textdomain('zz-locales-global', "{$dir}/de.mo", 'de_DE'), __('Hello', 'zz-locales-global')]);
$say('load_textdomain for fr_FR, then __()', [load_textdomain('zz-locales-global', "{$dir}/fr.mo", 'fr_FR'), __('Hello', 'zz-locales-global'), $controller->get_locale()]);
unload_textdomain('zz-locales-global');

$controller->set_locale($before);
foreach (glob("{$dir}/*") ?: [] as $file) {
    unlink($file);
}
rmdir($dir);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
