<?php
/**
 * Translation files and their helpers (probe translation-files): an MO and
 * a PHP translation file the probe writes, opened, read (headers, entries,
 * plurals, contexts), translated, exported and turned into one another;
 * plural form expressions evaluated and refused; translations looked up
 * across two files of one domain and through the gettext functions; and
 * the old translations object over the controller. The probe's files are
 * removed and its text domains unloaded.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$dir = sys_get_temp_dir() . '/zz-probe-l10n-' . getmypid();
@mkdir($dir);
register_shutdown_function(static function () use ($dir): void {
    foreach ((array) glob($dir . '/*') as $file) {
        @unlink((string) $file);
    }
    @rmdir($dir);
});
$mask = static function ($value) use ($dir, &$mask) {
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    return is_string($value) ? str_replace($dir, '{dir}', $value) : $value;
};

// An MO file written byte by byte: originals and translations tables, then the strings.
$mo = static function (array $entries): string {
    ksort($entries, SORT_STRING);
    $count = count($entries);
    $originalsAt = 28;
    $translationsAt = $originalsAt + $count * 8;
    $dataAt = $translationsAt + $count * 8;
    $originals = $translations = $originalData = $translationData = '';
    foreach (array_keys($entries) as $original) {
        $originals .= pack('VV', strlen((string) $original), $dataAt + strlen($originalData));
        $originalData .= $original . "\0";
    }
    $translationsDataAt = $dataAt + strlen($originalData);
    foreach ($entries as $translation) {
        $translations .= pack('VV', strlen($translation), $translationsDataAt + strlen($translationData));
        $translationData .= $translation . "\0";
    }
    return pack('VVVVVVV', 0x950412de, 0, $count, $originalsAt, $translationsAt, 0, $dataAt) . $originals . $translations . $originalData . $translationData;
};
$entries = [
    '' => "Project-Id-Version: Zz\nContent-Type: text/plain; charset=UTF-8\nPlural-Forms: nplurals=3; plural=(n==1 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2);\n",
    'Hello' => 'Witaj',
    "apple\0apples" => "jabłko\0jabłka\0jabłek",
    "menu\4Open" => 'Otwórz',
    'Empty' => '',
];
file_put_contents($moFile = $dir . '/zz-probe.mo', $mo($entries));
file_put_contents($phpFile = $dir . '/zz-probe.l10n.php', "<?php\nreturn " . var_export(['project-id-version' => 'Zz PHP', 'plural-forms' => 'nplurals=2; plural=(n != 1);', 'messages' => ['Hello' => 'Hallo', "apple\0apples" => "Apfel\0Äpfel", "menu\4Open" => 'Öffnen']], true) . ";\n");
file_put_contents($badFile = $dir . '/zz-bad.mo', 'not an mo file at all');
file_put_contents($shortFile = $dir . '/zz-short.mo', 'abc');

class Zz_Probe_MO_File extends WP_Translation_File_MO
{
    public static function open(string $file): self
    {
        return new self($file);
    }

    public function detect(string $header)
    {
        return $this->detect_endian_and_validate_file($header);
    }
}
class Zz_Probe_Plural_Forms extends Plural_Forms
{
    public function tokens(string $expression)
    {
        return $this->parse($expression);
    }
}

$file = static function ($opened) use ($mask) {
    if (!$opened instanceof WP_Translation_File) {
        return $opened;
    }
    return $mask([get_class($opened), $opened->get_file(), $opened->error(), $opened->headers(), $opened->entries(), $opened->translate('Hello'), $opened->translate('Nope'), $opened->translate("menu\4Open"), array_map(static fn ($n) => $opened->get_plural_form($n), [0, 1, 2, 5, 22, 25, 112])]);
};
$say('translation files opened', [
    $file(WP_Translation_File::create($moFile)),
    $file(WP_Translation_File::create($phpFile)),
    $file(WP_Translation_File::create($moFile, 'mo')),
    $file(WP_Translation_File::create($phpFile, 'php')),
    $file(WP_Translation_File::create($dir . '/missing.mo')),
    $file(WP_Translation_File::create($moFile, 'zz')),
    $mask([WP_Translation_File::create($badFile)->error(), WP_Translation_File::create($badFile)->entries()]),
    $mask([WP_Translation_File::create($shortFile)->error(), WP_Translation_File::create($shortFile)->headers()]),
]);

$say('the MO header checked', (static function () use ($moFile) {
    $opened = Zz_Probe_MO_File::open($moFile);
    return [$opened->detect(pack('V', 0x950412de)), $opened->detect(pack('N', 0x950412de)), $opened->detect('zzzz'), $opened->detect(''), $opened->error(), WP_Translation_File_MO::MAGIC_MARKER, get_parent_class(WP_Translation_File_PHP::class)];
})());

$say('translation files exported', $mask((static function () use ($moFile, $phpFile) {
    $mo = WP_Translation_File::create($moFile);
    $php = WP_Translation_File::create($phpFile);
    $toPhp = WP_Translation_File::transform($moFile, 'php');
    $toMo = WP_Translation_File::transform($phpFile, 'mo');
    return [
        md5((string) $mo->export()), strlen((string) $mo->export()),
        $php->export(),
        $toPhp,
        [md5((string) $toMo), strlen((string) $toMo)],
        WP_Translation_File::transform($moFile, 'zz'),
        WP_Translation_File::transform($moFile . '.missing', 'php'),
    ];
})()));

$say('plural form expressions', (static function () {
    $out = [];
    foreach (['n != 1', 'n==1 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2', '0', 'n', '(n > 1)', 'n%100==1 ? 0 : n%100==2 ? 1 : n%100==3 || n%100==4 ? 2 : 3'] as $expression) {
        $forms = new Plural_Forms($expression);
        $out[] = [$expression, array_map(static fn ($n) => $forms->get($n), [0, 1, 2, 3, 4, 5, 11, 21, 22, 101, 102, 104]), $forms->execute(7)];
    }
    // Each refusal says whether building the expression or evaluating it threw.
    foreach (['n +', 'zz', '(n', 'n)', '(n > 1', 'n > 1)', 'n n', '1 2', '', ': 1', 'n ? 1 : 2 : 3', '!n', '?', 'n ? 1', 'n % 0', 'n / 2', 'n * 2', 'n - 1', 'n ==', 'n >= ', 'n == 1 && ', 'n ? 1 : ', "\tn\t!=\t1\t", 'n  >  1', '12 % 5', 'n % 10 == 1 && n % 100 != 11', 'n % 10 % 3', '(n == 1) == 1', 'n == 1 == 1', 'n !=', "n\n!= 1", 'n = 1', 'n & 1', 'n || 0 && 0', '1 ? 0 : 1 ? 4 : 5', 'n ? 1 ? 2 : 3 : 4', 'n > 1 > 0'] as $expression) {
        try {
            $forms = new Plural_Forms($expression);
        } catch (Throwable $thrown) {
            $out[] = [$expression, 'built', get_class($thrown), $thrown->getMessage()];
            continue;
        }
        try {
            $out[] = [$expression, array_map(static fn ($n) => $forms->get($n), [0, 1, 3, 11, 21])];
        } catch (Throwable $thrown) {
            $out[] = [$expression, 'evaluated', get_class($thrown), $thrown->getMessage()];
        }
    }
    $out[] = (new Zz_Probe_Plural_Forms('n != 1'))->tokens('n % 3 == 1');
    return $out;
})());

// Two PHP files in one domain: the first file with an entry answers, empty or not; plurals look for the joined key in every file before the singular.
$say('translations looked up across files', (static function () use ($dir) {
    $write = static fn (string $file, array $data) => file_put_contents($file, "<?php\nreturn " . var_export($data, true) . ";\n");
    $write($first = $dir . '/zz-first.l10n.php', ['project-id-version' => 'First', 'x-zz' => 'a', 'plural-forms' => 'nplurals=3; plural=(n==1 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2);', 'messages' => ['Empty' => '', 'One' => 'A1', "p\0ps" => "f0\0f1", 'q' => "qa0\0qa1", 's' => "x\0y", "ctx\4c\0cs" => "c0\0c1", 'both' => "single0\0single1", "both\0boths" => "full0\0full1"]]);
    $write($second = $dir . '/zz-second.l10n.php', ['Project-Id-Version' => 'Second', 'x-other' => 'b', 'messages' => ['Empty' => 'Full', 'One' => 'B1', 'Two' => 'B2', "q\0qs" => "qb0\0qb1", "t\0ts\0tss" => "t0\0t1"]]);
    $controller = WP_Translation_Controller::get_instance();
    $previous = $controller->get_locale();
    $controller->set_locale('pl_PL');
    $controller->load_file($first, 'zz-probe-files', 'pl_PL');
    $controller->load_file($second, 'zz-probe-files', 'pl_PL');
    $domain = 'zz-probe-files';
    $plural = static fn (array $plurals, string $context = '') => array_map(static fn ($n) => $controller->translate_plural($plurals, $n, $context, $domain), [1, 2, 5]);
    $out = [
        [$controller->translate('Empty', '', $domain), $controller->translate('One', '', $domain), $controller->translate('Two', '', $domain), $controller->translate('p', '', $domain), $controller->translate("p\0ps", '', $domain), $controller->translate('c', 'ctx', $domain)],
        [$plural(['p', 'ps']), $plural(['q', 'qs']), $plural(['s', 'ss']), $plural(['c', 'cs'], 'ctx'), $plural(['both', 'boths']), $plural(['t', 'ts', 'tss']), $plural(['t', 'ts']), $plural(['p', 'other']), $plural(['p']), $plural(['k' => 'p', 'z' => 'ps']), $plural(['Two', 'Twos']), $plural(['Empty', 'Empties'])],
        array_map(static fn ($key) => $controller->has_translation($key, $domain), ['Empty', 'p', "p\0ps", 'Nope']),
        $controller->get_entries($domain),
        $controller->get_headers($domain),
    ];
    $translations = new WP_Translations($controller, $domain);
    $out[] = [$translations->translate('Empty'), $translations->translate_plural('Two', 'Twos', 3), $translations->translate_plural('zz', 'zzs', 3), $translations->translate_plural('zz', 'zzs', 1)];
    $controller->unload_textdomain($domain, 'pl_PL');
    $controller->set_locale($previous);
    return $out;
})());

$say('an empty translation through the gettext functions', (static function () use ($dir, $mo) {
    file_put_contents($file = $dir . '/zz-probe-gettext-pl_PL.mo', $mo(['' => "Content-Type: text/plain; charset=UTF-8\n", 'Empty' => '', 'Full' => 'P', "a\0b" => "\0x"]));
    load_textdomain('zz-probe-gettext', $file, 'pl_PL');
    $controller = WP_Translation_Controller::get_instance();
    $previous = $controller->get_locale();
    $controller->set_locale('pl_PL');
    $out = [__('Empty', 'zz-probe-gettext'), __('Full', 'zz-probe-gettext'), _n('a', 'b', 1, 'zz-probe-gettext'), _n('a', 'b', 2, 'zz-probe-gettext'), get_class(get_translations_for_domain('zz-probe-gettext'))];
    unload_textdomain('zz-probe-gettext');
    $controller->set_locale($previous);
    return $out;
})());

$say('the old translations object', $mask((static function () use ($moFile) {
    $controller = WP_Translation_Controller::get_instance();
    $controller->load_file($moFile, 'zz-probe-domain', 'pl_PL');
    $previous = $controller->get_locale();
    $controller->set_locale('pl_PL');
    $translations = new WP_Translations($controller, 'zz-probe-domain');
    $out = [
        $translations->translate('Hello'), $translations->translate('Nope'), $translations->translate('Open', 'menu'), $translations->translate('Empty'),
        array_map(static fn ($n) => $translations->translate_plural('apple', 'apples', $n), [1, 2, 5]),
        $translations->translate_plural('pear', 'pears', 2), $translations->translate_plural('pear', 'pears', 1),
        $translations->headers, array_map(static fn ($entry) => [get_class($entry), $entry->singular, $entry->plural, $entry->context, $entry->translations], $translations->entries), $translations->zz ?? 'unset',
    ];
    $controller->unload_textdomain('zz-probe-domain', 'pl_PL');
    $controller->set_locale($previous);
    return $out;
})()));

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
