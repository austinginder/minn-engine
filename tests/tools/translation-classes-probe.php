<?php
/**
 * The translation classes (probe translation-classes): a .mo and a .l10n.php
 * file the probe writes, read through WP_Translation_File (headers, entries,
 * plurals, export, transform) and loaded, translated and unloaded through
 * WP_Translation_Controller. Then the gettext classes in memory (Translations,
 * Gettext_Translations, their plural rules and merges), $wp_locale's names
 * and separators, and $wp_textdomain_registry. The probe's folder is removed
 * and the controller left as it was. Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$dir = rtrim(get_temp_dir(), '/') . '/zz-translation-probe-' . getmypid();
register_shutdown_function(static function () use ($dir): void {
    WP_Translation_Controller::get_instance()->unload_textdomain('zz-probe', 'de_DE');
    WP_Translation_Controller::get_instance()->unload_textdomain('zz-probe-php', 'de_DE');
    foreach (glob($dir . '/*') ?: [] as $file) {
        unlink($file);
    }
    if (is_dir($dir)) {
        rmdir($dir);
    }
});
wp_mkdir_p($dir);
// Protected methods are part of a class's surface: called through reflection on both stacks.
$inside = static fn (object $object, string $method, ...$args) => (new ReflectionMethod($object, $method))->invoke($object, ...$args);
$mask = static function ($value) use (&$mask, $dir) {
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    return is_string($value) ? str_replace($dir, '{dir}', $value) : $value;
};

// A .mo file, made with the gettext classes.
$mo = new MO();
$mo->set_headers(['Project-Id-Version' => 'Zz Probe', 'Language' => 'de_DE', 'Plural-Forms' => 'nplurals=2; plural=(n != 1);', 'Content-Type' => 'text/plain; charset=UTF-8']);
$mo->add_entry(new Translation_Entry(['singular' => 'Hello', 'translations' => ['Hallo']]));
$mo->add_entry(new Translation_Entry(['singular' => 'Post', 'context' => 'noun', 'translations' => ['Beitrag']]));
$mo->add_entry(new Translation_Entry(['singular' => '%d apple', 'plural' => '%d apples', 'translations' => ['%d Apfel', '%d Äpfel']]));
$mo->add_entry(new Translation_Entry(['singular' => 'Untranslated', 'translations' => []]));
$moFile = "$dir/zz-probe-de_DE.mo";
$exported = $mo->export_to_file($moFile);
file_put_contents("$dir/zz-probe-php-de_DE.l10n.php", "<?php\nreturn " . var_export(['project-id-version' => 'Zz PHP', 'plural-forms' => 'nplurals=3; plural=(n==1 ? 0 : n>=2 && n<=4 ? 1 : 2);', 'messages' => ['Hello' => 'Ahoj', 'Post' . "\4" . 'noun' => 'Příspěvek', '%d apple' => '%d jablko' . "\0" . '%d jablka' . "\0" . '%d jablek']], true) . ";\n");
file_put_contents("$dir/zz-broken-de_DE.mo", 'not a mo file');

$files = [];
foreach (['mo' => $moFile, 'php' => "$dir/zz-probe-php-de_DE.l10n.php", 'broken' => "$dir/zz-broken-de_DE.mo", 'missing' => "$dir/zz-none.mo", 'other extension' => "$dir/zz.txt"] as $label => $path) {
    $file = WP_Translation_File::create($path);
    $files[$label] = $file === false ? false : $mask([
        get_class($file), $file->get_file(), $file->error(), $file->headers(), $file->entries(),
        $file->translate('Hello'), $file->translate('Post' . "\4" . 'noun'), $file->translate('Nope'),
        array_map([$file, 'get_plural_form'], [0, 1, 2, 5, 11]),
    ]);
}
$say('files read', [$exported, $files]);
$moAsPhp = WP_Translation_File::transform($moFile, 'php');
$say('files transformed', [
    is_string($moAsPhp) && str_starts_with($moAsPhp, '<?php'),
    is_string($moAsPhp) ? eval('?>' . $moAsPhp) : $moAsPhp,
    WP_Translation_File::transform($moFile, 'nonsense'),
    WP_Translation_File::transform("$dir/zz-none.mo", 'php'),
    strlen((string) WP_Translation_File::transform("$dir/zz-probe-php-de_DE.l10n.php", 'mo')) > 20,
]);

$controller = WP_Translation_Controller::get_instance();
$locale = $controller->get_locale();
$say('the controller', $mask([
    $controller === WP_Translation_Controller::get_instance(),
    $locale,
    $controller->load_file($moFile, 'zz-probe', 'de_DE'),
    $controller->load_file($moFile, 'zz-probe', 'de_DE'),
    $controller->load_file("$dir/zz-broken-de_DE.mo", 'zz-probe', 'de_DE'),
    $controller->load_file("$dir/zz-probe-php-de_DE.l10n.php", 'zz-probe-php', 'de_DE'),
    $controller->is_textdomain_loaded('zz-probe', 'de_DE'),
    $controller->is_textdomain_loaded('zz-probe', 'fr_FR'),
    $controller->is_textdomain_loaded('zz-probe'),
    $controller->translate('Hello', '', 'zz-probe', 'de_DE'),
    $controller->translate('Post', 'noun', 'zz-probe', 'de_DE'),
    $controller->translate('Post', '', 'zz-probe', 'de_DE'),
    $controller->translate('Untranslated', '', 'zz-probe', 'de_DE'),
    $controller->translate_plural(['%d apple', '%d apples'], 1, '', 'zz-probe', 'de_DE'),
    $controller->translate_plural(['%d apple', '%d apples'], 3, '', 'zz-probe', 'de_DE'),
    array_map(static fn ($n) => $controller->translate_plural(['%d apple', '%d apples'], $n, '', 'zz-probe-php', 'de_DE'), [1, 3, 7]),
    $controller->get_headers('zz-probe', 'de_DE'),
    $controller->get_entries('zz-probe', 'de_DE'),
    $controller->has_translation('Hello', 'zz-probe', 'de_DE'),
    $controller->has_translation('Nope', 'zz-probe', 'de_DE'),
    array_map(static fn ($f) => [get_class($f), basename($f->get_file())], $inside($controller, 'get_files', 'zz-probe', 'de_DE')),
    $inside($controller, 'locate_translation', 'Hello', 'zz-probe', 'de_DE') === false ? false : array_keys((array) $inside($controller, 'locate_translation', 'Hello', 'zz-probe', 'de_DE')),
    $inside($controller, 'locate_translation', 'Nope', 'zz-probe', 'de_DE'),
    $controller->set_locale('de_DE'),
    $controller->get_locale(),
    $controller->translate('Hello', '', 'zz-probe'),
    $controller->set_locale($locale),
    $controller->unload_file($moFile, 'zz-probe', 'de_DE'),
    $controller->is_textdomain_loaded('zz-probe', 'de_DE'),
    $controller->unload_file("$dir/zz-none.mo", 'zz-probe', 'de_DE'),
    $controller->unload_textdomain('zz-probe-php', 'de_DE'),
    $controller->unload_textdomain('zz-probe-php', 'de_DE'),
    $controller->translate('Hello', '', 'zz-probe-php', 'de_DE'),
]));
$say('headers normalized', array_map(static fn ($h) => $inside($controller, 'normalize_header', $h), ['project-id-version', 'plural-forms', 'X-Generator', 'po-revision-date', 'language']));
$moFileObject = WP_Translation_File::create($moFile);
$say('file internals', $mask([
    $inside($moFileObject, 'get_plural_expression_from_header', 'nplurals=2; plural=(n != 1);'),
    $inside($moFileObject, 'get_plural_expression_from_header', 'nonsense'),
    array_map($inside($moFileObject, 'make_plural_form_function', 'n != 1'), [0, 1, 2]),
    $inside($moFileObject, 'detect_endian_and_validate_file', substr((string) file_get_contents($moFile), 0, 4)),
    $inside($moFileObject, 'detect_endian_and_validate_file', 'zzzz'),
    (static function () use ($moFile, $inside, $dir) {
        $fresh = WP_Translation_File::create($moFile);
        $inside($fresh, 'import', WP_Translation_File::create("$dir/zz-probe-php-de_DE.l10n.php"));
        return [$fresh->headers(), count($fresh->entries()), $fresh->translate('Hello')];
    })(),
]));

// The gettext classes, in memory.
$t = new Translations();
$entry = new Translation_Entry(['singular' => 'One', 'translations' => ['Eins']]);
$t->add_entry($entry);
$t->add_entry(['singular' => 'Two', 'translations' => ['Zwei']]);
$t->add_entry(new Translation_Entry(['singular' => '']));
$t->add_entry_or_merge(new Translation_Entry(['singular' => 'One', 'references' => ['a.php:1'], 'translations' => ['Uno']]));
$t->set_header('X-Zz', 'yes');
$t->set_headers(['Plural-Forms' => 'nplurals=3; plural=n%10==1 ? 0 : 1;', 'X-More' => 'more']);
$other = new Translations();
$other->add_entry(['singular' => 'Three', 'translations' => ['Drei']]);
$other->add_entry(['singular' => 'One', 'translations' => ['Ein']]);
$say('translations', [
    array_keys($t->entries), $t->headers, $t->get_header('X-Zz'), $t->get_header('Nope'),
    $t->translate('One'), $t->translate('Two'), $t->translate('Absent'), $t->translate('One', 'ctx'),
    $t->translate_entry(new Translation_Entry(['singular' => 'Two'])) instanceof Translation_Entry,
    $t->translate_entry(new Translation_Entry(['singular' => 'Nope'])),
    $t->select_plural_form(1), $t->select_plural_form(5), $t->get_plural_forms_count(),
    $t->translate_plural('%d one', '%d many', 1), $t->translate_plural('%d one', '%d many', 2),
    $t->entries['One']->references,
    (static function () use ($t, $other) {
        $copy = clone $t;
        $copy->merge_with($other);
        $originals = clone $t;
        $originals->merge_originals_with($other);
        return [array_keys($copy->entries), $copy->translate('One'), array_keys($originals->entries), $originals->translate('One')];
    })(),
]);
$g = new Gettext_Translations();
$g->set_header('Plural-Forms', 'nplurals=3; plural=(n%10==1 && n%100!=11 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2);');
$say('gettext plurals', [
    array_map([$g, 'gettext_select_plural_form'], [1, 2, 5, 11, 21, 22, 25, 111]),
    $g->nplurals_and_expression_from_header('nplurals=2; plural=n != 1;'),
    $g->nplurals_and_expression_from_header('garbage'),
    $g->parenthesize_plural_exression('n==1 ? 0 : n>=2 && n<=4 ? 1 : 2'),
    array_map($g->make_plural_form_function(2, 'n != 1'), [0, 1, 2]),
    $g->make_headers("Project-Id-Version: Zz\nPlural-Forms: nplurals=1; plural=0;\nX-Folded: a\n b\n"),
    $g->get_plural_forms_count(),
    (new NOOP_Translations())->translate('Same'),
]);

// $wp_locale and the registry.
global $wp_locale, $wp_textdomain_registry;
$say('the locale', [
    get_class($wp_locale) === WP_Locale::class,
    array_map([$wp_locale, 'get_weekday'], [0, 3, 6]),
    $wp_locale->get_weekday_initial('Sunday'), $wp_locale->get_weekday_abbrev('Wednesday'),
    array_map([$wp_locale, 'get_month'], [1, '02', 12, '7']),
    $wp_locale->get_month_abbrev('March'), $wp_locale->get_month_genitive('05'),
    array_map([$wp_locale, 'get_meridiem'], ['am', 'pm', 'AM', 'PM']),
    $wp_locale->is_rtl(), $wp_locale->get_list_item_separator(), $wp_locale->get_word_count_type(),
    $wp_locale->text_direction, $wp_locale->number_format,
    (static function () use ($wp_locale) {
        $copy = new WP_Locale();
        $copy->init();
        $copy->register_globals();
        return [$GLOBALS['weekday'][1] ?? null, $GLOBALS['month']['04'] ?? null, $GLOBALS['month_abbrev']['April'] ?? null, $GLOBALS['weekday_initial']['Monday'] ?? null];
    })(),
]);
$say('the registry', $mask([
    get_class($wp_textdomain_registry) === WP_Textdomain_Registry::class,
    $wp_textdomain_registry->has('zz-probe'),
    $wp_textdomain_registry->get('zz-probe', 'de_DE'),
    (static function () use ($wp_textdomain_registry, $dir) {
        $wp_textdomain_registry->set('zz-probe', 'de_DE', $dir);
        $wp_textdomain_registry->set_custom_path('zz-probe-custom', $dir);
        $out = [$wp_textdomain_registry->has('zz-probe'), $wp_textdomain_registry->get('zz-probe', 'de_DE'), $wp_textdomain_registry->get('zz-probe', 'fr_FR'), $wp_textdomain_registry->has('zz-probe-custom'), $wp_textdomain_registry->get('zz-probe-custom', 'de_DE')];
        $wp_textdomain_registry->set('zz-probe', 'de_DE', false);
        $out[] = $wp_textdomain_registry->get('zz-probe', 'de_DE');
        return $out;
    })(),
    (static function () use ($wp_textdomain_registry, $dir, $inside) {
        $files = $wp_textdomain_registry->get_language_files_from_path($dir . '/');
        $files[] = $inside($wp_textdomain_registry, 'get_path_from_lang_dir', 'zz-nothing', 'de_DE');
        sort($files);
        return $files;
    })(),
    $wp_textdomain_registry->get_language_files_from_path($dir . '/zz-nowhere/'),
    $wp_textdomain_registry->invalidate_mo_files_cache(null, ['type' => 'translation', 'action' => 'update', 'translations' => [['language' => 'de_DE', 'type' => 'plugin', 'slug' => 'zz']]]),
]));

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
