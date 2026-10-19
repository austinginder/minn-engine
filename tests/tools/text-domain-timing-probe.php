<?php
/**
 * When a text domain loads (probe text-domain-timing): load_plugin_textdomain,
 * load_theme_textdomain, load_child_theme_textdomain and
 * load_muplugin_textdomain only name the domain's folder and answer true,
 * firing nothing, not even the locale filters; the domain is read at its
 * first translation (or get_translations_for_domain), from the named
 * folder's file for the locale ("{domain}-{locale}", or the locale alone
 * in the theme's folder when no path was given), the load attempted even
 * when the file is not there; once read it is not read again, and naming the folder again
 * reads nothing. A domain unloaded before its first translation had
 * nothing to unload and still loads at it. A domain looked for in vain is
 * looked for again once a folder is named for it. A domain nobody named
 * asks only the locale. determine_locale asks pre_determine_locale once,
 * then the site's locale through the locale filter. The probe's translation files are written
 * under throwaway plugin folders, read with the locale filtered to de_DE,
 * and removed at the end. Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$plugins = WP_PLUGIN_DIR;
$folders = ['zz-timing-a', 'zz-timing-b', 'zz-timing-c', 'zz-timing-d', 'zz-timing-t'];
foreach ($folders as $folder) {
    if (file_exists("{$plugins}/{$folder}")) {
        fwrite(STDERR, "text-domain-timing: {$plugins}/{$folder} is already there\n");
        exit(1);
    }
}
$files = [
    "{$plugins}/zz-timing-a/lang/zz-timing-a-de_DE.l10n.php" => 'zz-timing-a',
    "{$plugins}/zz-timing-c/lang/zz-timing-c-de_DE.l10n.php" => 'zz-timing-c',
    "{$plugins}/zz-timing-d/lang/zz-timing-d-de_DE.l10n.php" => 'zz-timing-d',
    "{$plugins}/zz-timing-t/zz-timing-t-de_DE.l10n.php" => 'zz-timing-t',
];
register_shutdown_function(static function () use ($files, $folders, $plugins): void {
    foreach (array_keys($files) as $file) {
        @unlink($file);
    }
    foreach ($folders as $folder) {
        @rmdir("{$plugins}/{$folder}/lang");
        @rmdir("{$plugins}/{$folder}");
    }
});
foreach ($folders as $folder) {
    mkdir("{$plugins}/{$folder}/lang", 0755, true);
}
foreach ($files as $file => $domain) {
    file_put_contents($file, "<?php\nreturn ['domain' => '{$domain}', 'language' => 'de_DE', 'messages' => ['Hello' => 'Hallo {$domain}']];\n");
}

$heard = [];
$paths = [$plugins => '{plugins}', WPMU_PLUGIN_DIR => '{mu-plugins}', get_theme_root() => '{themes}'];
$watch = static function ($hook, ...$args) use (&$heard, $paths) {
    $hook = (string) $hook;
    if (str_contains($hook, 'locale') || preg_match('/textdomain|translation_file|mofile/', $hook) && (str_contains((string) json_encode($args), 'zz-timing') || $hook === 'translation_file_format')) {
        $heard[] = [$hook, array_map(static fn ($arg) => is_string($arg) ? strtr($arg, $paths) : $arg, array_slice($args, 0, 3))];
    }
};
$step = static function (string $label, callable $call) use ($say, &$heard): void {
    $heard = [];
    $result = $call();
    $say($label, ['result' => $result, 'heard' => $heard]);
};

// determine_locale, before the locale is filtered.
add_filter('all', $watch, 10, 4);
$step('determine_locale', static fn () => determine_locale());
$early = static fn () => 'fr_FR';
add_filter('pre_determine_locale', $early);
$step('determine_locale, pre_determine_locale saying fr_FR', static fn () => determine_locale());
remove_filter('pre_determine_locale', $early);

$german = static fn () => 'de_DE';
add_filter('locale', $german);
$step('a: load_plugin_textdomain, a file there', static fn () => load_plugin_textdomain('zz-timing-a', false, 'zz-timing-a/lang'));
$step('a: is_textdomain_loaded before a translation', static fn () => is_textdomain_loaded('zz-timing-a'));
$step('a: the first translation', static fn () => __('Hello', 'zz-timing-a'));
$step('a: the second translation', static fn () => __('Hello', 'zz-timing-a'));
$step('a: load_plugin_textdomain again', static fn () => load_plugin_textdomain('zz-timing-a', false, 'zz-timing-a/lang'));
$step('a: is_textdomain_loaded after', static fn () => is_textdomain_loaded('zz-timing-a'));
$step('b: load_plugin_textdomain, no file', static fn () => load_plugin_textdomain('zz-timing-b', false, 'zz-timing-b/lang'));
$step('b: the first translation', static fn () => __('Hello', 'zz-timing-b'));
$step('t: load_theme_textdomain with a path', static fn () => load_theme_textdomain('zz-timing-t', WP_PLUGIN_DIR . '/zz-timing-t'));
$step('t: get_translations_for_domain', static fn () => get_class(get_translations_for_domain('zz-timing-t')));
$step('t: the first translation', static fn () => __('Hello', 'zz-timing-t'));
$step('mu: load_muplugin_textdomain', static fn () => load_muplugin_textdomain('zz-timing-mu', 'zz-timing-mu'));
$step('mu: the first translation', static fn () => __('Hello', 'zz-timing-mu'));
$step('child: load_child_theme_textdomain', static fn () => load_child_theme_textdomain('zz-timing-child'));
$step('child: the first translation', static fn () => __('Hello', 'zz-timing-child'));
$step('theme: load_theme_textdomain with no path', static fn () => load_theme_textdomain('zz-timing-theme'));
$step('theme: the first translation', static fn () => __('Hello', 'zz-timing-theme'));
$step('theme path: load_theme_textdomain naming the theme folder', static fn () => load_theme_textdomain('zz-timing-theme-path', get_template_directory()));
$step('theme path: the first translation', static fn () => __('Hello', 'zz-timing-theme-path'));
$step('child path: load_child_theme_textdomain naming the theme folder', static fn () => load_child_theme_textdomain('zz-timing-child-path', get_stylesheet_directory()));
$step('child path: the first translation', static fn () => __('Hello', 'zz-timing-child-path'));
$step('c: named, then unloaded before a translation', static fn () => [load_plugin_textdomain('zz-timing-c', false, 'zz-timing-c/lang'), unload_textdomain('zz-timing-c'), is_textdomain_loaded('zz-timing-c'), __('Hello', 'zz-timing-c')]);
$step('d: looked for before anyone named it', static fn () => __('Hello', 'zz-timing-d'));
$step('d: then named', static fn () => load_plugin_textdomain('zz-timing-d', false, 'zz-timing-d/lang'));
$step('d: the next translation', static fn () => __('Hello', 'zz-timing-d'));
$step('none: nobody named it', static fn () => __('Hello', 'zz-timing-none'));
$step('none: again', static fn () => __('Hello', 'zz-timing-none'));
remove_filter('all', $watch, 10);

// The file a named folder is read for: by locale alone at or below the active theme's folder, whichever domain names it.
$template = get_template_directory();
$root = get_theme_root();
$where = [
    "the theme's languages folder" => static fn () => load_theme_textdomain('zz-timing-w1', $template . '/languages'),
    "a folder below the theme's that is not there" => static fn () => load_theme_textdomain('zz-timing-w2', $template . '/lang/sub'),
    'another theme' => static fn () => load_theme_textdomain('zz-timing-w3', $root . '/twentytwentythree'),
    'the themes root' => static fn () => load_theme_textdomain('zz-timing-w4', $root),
    "the theme's folder with a slash after it" => static fn () => load_theme_textdomain('zz-timing-w5', $template . '/'),
    "a plugin path climbing into the theme's folder" => static fn () => load_plugin_textdomain('zz-timing-w6', false, '../themes/' . get_template()),
    'the languages folder for themes' => static fn () => load_theme_textdomain('zz-timing-w7', WP_LANG_DIR . '/themes'),
    "a folder whose name starts with the theme's" => static fn () => load_theme_textdomain('zz-timing-w8', $template . '-other'),
];
$n = 0;
foreach ($where as $label => $name) {
    $domain = 'zz-timing-w' . ++$n;
    $named = $name();
    $tried = null;
    $grab = static function ($mofile) use (&$tried) {
        $tried ??= $mofile;
        return $mofile;
    };
    add_filter('load_textdomain_mofile', $grab);
    __('Hello', $domain);
    remove_filter('load_textdomain_mofile', $grab);
    unload_textdomain($domain);
    $say("the file read: {$label}", [$named, strtr((string) $tried, [$root => '{themes}', $plugins => '{plugins}', WP_LANG_DIR => '{languages}'])]);
}
// The deprecated second argument: a folder under the site's root, told as deprecated, used when no plugin path is given.
$deprecations = [];
$noted = static function ($function, $replacement, $version) use (&$deprecations) {
    $deprecations[] = [$function, $replacement, $version];
};
add_action('deprecated_argument_run', $noted, 10, 3);
add_filter('deprecated_argument_trigger_error', '__return_false');
foreach ([
    'the deprecated folder alone' => static fn () => load_plugin_textdomain('zz-timing-old', 'wp-content/plugins/zz-timing-a/lang'),
    'the deprecated folder and a plugin path' => static fn () => load_plugin_textdomain('zz-timing-old2', 'wp-content/plugins/zz-timing-a/lang', 'zz-timing-b/lang'),
    'false for the deprecated folder' => static fn () => load_plugin_textdomain('zz-timing-old3', false),
] as $label => $name) {
    $deprecations = [];
    $named = $name();
    $domain = match ($label) {
        'the deprecated folder alone' => 'zz-timing-old',
        'the deprecated folder and a plugin path' => 'zz-timing-old2',
        default => 'zz-timing-old3',
    };
    $tried = null;
    $grab = static function ($mofile) use (&$tried) {
        $tried ??= $mofile;
        return $mofile;
    };
    add_filter('load_textdomain_mofile', $grab);
    __('Hello', $domain);
    remove_filter('load_textdomain_mofile', $grab);
    unload_textdomain($domain);
    $say("the deprecated argument: {$label}", [$named, $deprecations, str_replace(rtrim(ABSPATH, '/'), '{root}', strtr((string) $tried, [$plugins => '{plugins}']))]);
}
remove_action('deprecated_argument_run', $noted, 10);
remove_filter('deprecated_argument_trigger_error', '__return_false');
remove_filter('locale', $german);
foreach (['zz-timing-a', 'zz-timing-b', 'zz-timing-c', 'zz-timing-d', 'zz-timing-t', 'zz-timing-mu', 'zz-timing-child', 'zz-timing-theme', 'zz-timing-theme-path', 'zz-timing-child-path', 'zz-timing-none'] as $domain) {
    unload_textdomain($domain);
}
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n";
