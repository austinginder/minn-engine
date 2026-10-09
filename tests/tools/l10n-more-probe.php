<?php
/**
 * More translation helpers (probe l10n-more): a PHP translation file's
 * headers read by including it, whether a domain loaded for a locale
 * translates a string (and the locale load_textdomain makes current), and
 * the JSON translations for a script or module source, the relative path
 * filter told which it is. Files go in a scratch plugin folder and the
 * system temp folder and are removed again. Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
foreach (['home_url', 'site_url', 'option_home', 'option_siteurl', 'content_url', 'plugins_url'] as $devHook) {
    remove_all_filters($devHook);
}
$fx = dirname(__DIR__) . '/fixtures/languages';
$written = [];
register_shutdown_function(static function () use (&$written): void {
    foreach ($written as $file) {
        @unlink($file);
    }
});

// A PHP translation file's headers.
$temp = static function (string $php) use (&$written): string {
    $file = (string) tempnam(sys_get_temp_dir(), 'zzl10n');
    file_put_contents($file, $php);
    $written[] = $file;
    return $file;
};
$read = static function (string $file): array {
    $warnings = [];
    set_error_handler(static function (int $level) use (&$warnings): bool {
        $warnings[] = $level;
        return true;
    });
    ob_start();
    try {
        $data = wp_get_l10n_php_file_data($file);
    } finally {
        $printed = (string) ob_get_clean();
        restore_error_handler();
    }
    return [$data, $printed, $warnings];
};
$say('a PHP translation file\'s headers', [
    $read("{$fx}/minn-probe-pl_PL.l10n.php"),
    $read($temp("<?php return 'notarray';")),
    $read($temp("<?php return ['messages' => ['a' => 'b']];")),
    $read($temp("<?php return ['x-generator' => 'zz gen', 'project-id-version' => 7, 'POT-Creation-Date' => 'upper', 'po-revision-date' => null, 'language' => 'pl_PL'];")),
    $read($temp("<?php echo 'printed'; return [1];")),
    $read($temp('not php at all')),
    $read(sys_get_temp_dir() . '/zz-never-there.l10n.php'),
]);

// Whether a domain loaded for a locale translates a string.
$controller = WP_Translation_Controller::get_instance();
$was = $controller->get_locale();
$say('has a translation', [
    [has_translation('Hello', 'minn-probe'), has_translation('Hello', 'minn-probe', 'pl_PL')],
    load_textdomain('minn-probe', "{$fx}/minn-probe-pl_PL.mo", 'pl_PL'),
    $controller->get_locale(),
    [
        has_translation('Hello', 'minn-probe'), has_translation('Hello', 'minn-probe', 'pl_PL'), has_translation('Nope zz', 'minn-probe', 'pl_PL'),
        has_translation('Hello', 'minn-probe', 'de_DE'), has_translation('Hello', 'minn-probe', 'en_US'), has_translation('Hello', 'zz-none', 'pl_PL'), has_translation('Hello', 'default', 'pl_PL'),
        has_translation('%d comment', 'minn-probe', 'pl_PL'), has_translation('Post', 'minn-probe', 'pl_PL'), has_translation('verbPost', 'minn-probe', 'pl_PL'), has_translation("verb\4Post", 'minn-probe', 'pl_PL'), has_translation('', 'minn-probe', 'pl_PL'),
    ],
    load_textdomain('minn-probe-extra', "{$fx}/minn-probe-extra-pl_PL.mo", 'de_DE'),
    $controller->get_locale(),
    [has_translation('Hello', 'minn-probe'), has_translation('Hello', 'minn-probe', 'pl_PL'), has_translation('Hello', 'minn-probe-extra'), has_translation('Hello', 'minn-probe-extra', 'de_DE'), has_translation('Hello', 'minn-probe-extra', 'pl_PL')],
    (static function () use ($controller) {
        $controller->set_locale('fr_FR');
        return [has_translation('Hello', 'minn-probe'), has_translation('Hello', 'minn-probe-extra'), has_translation('Hello', 'minn-probe', 'pl_PL')];
    })(),
    (static function () use ($controller, $fx) {
        $controller->load_file("{$fx}/minn-probe-be-pl_PL.mo", 'minn-probe-be', 'pl_PL');
        return [$controller->get_locale(), has_translation('Hello', 'minn-probe-be', 'pl_PL'), has_translation('Hello', 'minn-probe-be')];
    })(),
    (static function () use ($controller, $fx) {
        $loaded = load_textdomain('minn-probe-ar', "{$fx}/minn-probe-ar-ar.mo");
        return [$loaded, $controller->get_locale(), determine_locale()];
    })(),
    (static function () {
        unload_textdomain('minn-probe');
        return [has_translation('Hello', 'minn-probe', 'pl_PL')];
    })(),
]);
$controller->set_locale($was);

// Where a loaded domain's files live: recorded by a load that worked, under its locale.
$registry = $GLOBALS['wp_textdomain_registry'];
$folder = static fn ($path) => is_string($path) ? str_replace($fx, '{fx}', $path) : $path;
$say('where a loaded domain lives', [
    $folder($registry->get('minn-probe', 'pl_PL')),
    $folder($registry->get('minn-probe', 'de_DE')),
    load_textdomain('zz-missing', "{$fx}/zz-missing-pl_PL.mo", 'pl_PL'),
    $folder($registry->get('zz-missing', 'pl_PL')),
    (static function () use ($controller, $registry, $fx, $folder) {
        $controller->load_file("{$fx}/minn-probe-extra-pl_PL.mo", 'minn-probe-extra-file', 'pl_PL');
        return $folder($registry->get('minn-probe-extra-file', 'pl_PL'));
    })(),
    (static function () use ($controller, $registry, $fx, $folder, $was) {
        load_textdomain('minn-probe-be-again', "{$fx}/minn-probe-be-pl_PL.mo");
        $controller->set_locale($was);
        return [$folder($registry->get('minn-probe-be-again', determine_locale())), $folder($registry->get('minn-probe-be-again', 'pl_PL'))];
    })(),
]);

// The JSON translations for a script or module source.
$json = (string) file_get_contents("{$fx}/minn-probe-pl_PL-minn-probe-handle.json");
$home = home_url();
$bare = (string) preg_replace('#^https?://#', '', $home);
$plain = static fn ($v) => is_string($v) ? str_replace([$fx, WP_LANG_DIR, WP_PLUGIN_DIR, 'https://' . $bare, 'http://' . $bare], ['{fx}', '{lang}', '{plugins}', '{home}', '{home}'], $v) : $v;
$heard = [];
foreach (['pre_load_script_translations', 'load_script_translation_file', 'load_script_translations', 'load_script_textdomain_relative_path'] as $hook) {
    add_filter($hook, static function () use ($hook, &$heard, $plain) {
        $args = func_get_args();
        $heard[] = [$hook, array_map(static fn ($a) => is_string($a) && str_starts_with($a, '{"') ? 'JSON' : $plain($a), $args)];
        return $args[0] ?? null;
    }, 10, 9);
}
$asPolish = static fn () => 'pl_PL';
add_filter('determine_locale', $asPolish);
$plugin = WP_PLUGIN_DIR . '/minn-probe-js-more';
$put = static function (string $file) use ($json, &$written): void {
    @mkdir(dirname($file), 0777, true);
    file_put_contents($file, $json);
    $written[] = $file;
};
$put("{$plugin}/languages/minn-probe-pl_PL-" . md5('js/app.js') . '.json');
$put("{$plugin}/languages/minn-probe-pl_PL-zz-mod.json");
$found = static function (Closure $call) use (&$heard): array {
    $heard = [];
    $result = $call();
    return [$result === false ? false : (json_decode((string) $result, true)['locale_data']['messages']['Hello'][0] ?? 'other'), $heard];
};
$say('translations for a source', [
    $found(static fn () => _load_script_textdomain_from_src('zz-h1', plugins_url('minn-probe-js-more/js/app.js'), 'minn-probe', "{$plugin}/languages", false)),
    $found(static fn () => _load_script_textdomain_from_src('zz-h2', plugins_url('minn-probe-js-more/js/app.js'), 'minn-probe', "{$plugin}/languages", true)),
    $found(static fn () => _load_script_textdomain_from_src('zz-h3', plugins_url('minn-probe-js-more/js/none.js'), 'minn-probe', "{$plugin}/languages", true)),
    $found(static fn () => _load_script_textdomain_from_src('zz-h4', 'https://cdn.example.test/x.js', 'minn-probe', '', false)),
    $found(static fn () => _load_script_textdomain_from_src('zz-mod', plugins_url('minn-probe-js-more/js/other.js'), 'minn-probe', "{$plugin}/languages/", true)),
    $found(static fn () => _load_script_textdomain_from_src('zz-h5', '/wp-content/plugins/minn-probe-js-more/js/app.js?ver=2', 'minn-probe', "{$plugin}/languages", false)),
]);
wp_register_script_module('zz-mod', plugins_url('minn-probe-js-more/js/mod.js'), [], '1');
wp_register_script_module('zz-mod-app', plugins_url('minn-probe-js-more/js/app.js'), [], '1');
$say('translations for a script module', [
    $found(static fn () => load_script_module_textdomain('zz-mod', 'minn-probe', "{$plugin}/languages")),
    $found(static fn () => load_script_module_textdomain('zz-mod', 'minn-probe')),
    $found(static fn () => load_script_module_textdomain('zz-mod-app', 'minn-probe', "{$plugin}/languages")),
    $found(static fn () => load_script_module_textdomain('zz-unregistered', 'minn-probe', "{$plugin}/languages")),
]);
remove_filter('determine_locale', $asPolish);
foreach ($written as $file) {
    @unlink($file);
}
@rmdir("{$plugin}/languages");
@rmdir($plugin);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
