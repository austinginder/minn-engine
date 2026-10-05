<?php
/**
 * Script translations: which JSON file load_script_textdomain reads for a
 * script (by handle, by the md5 of its path inside its plugin, theme or the
 * site, in the folder given or the languages folder), the filters on the
 * way, and the block wp_set_script_translations prints before the script.
 * Files go in a scratch plugin folder and are removed again. Same protocol
 * as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
foreach (['home_url', 'site_url', 'option_home', 'option_siteurl', 'content_url', 'plugins_url'] as $devHook) {
    remove_all_filters($devHook);
}
add_filter('doing_it_wrong_trigger_error', '__return_false');
$fx = dirname(__DIR__) . '/fixtures/languages';
$home = home_url();
$bare = (string) preg_replace('#^https?://#', '', $home);
$plain = static fn ($v) => is_string($v) ? str_replace([WP_LANG_DIR, WP_PLUGIN_DIR, get_theme_root(), 'https://' . $bare, 'http://' . $bare], ['{lang}', '{plugins}', '{themes}', '{home}', '{home}'], $v) : $v;
$heard = [];
foreach (['pre_load_script_translations', 'load_script_translation_file', 'load_script_translations', 'load_script_textdomain_relative_path'] as $hook) {
    add_filter($hook, static function () use ($hook, &$heard, $plain) {
        $args = func_get_args();
        $heard[] = [$hook, array_map(static fn ($a) => is_string($a) && str_starts_with($a, '{') ? 'JSON' : $plain($a), array_slice($args, 0, 4))];
        return $args[0] ?? null;
    }, 10, 9);
}
$caught = static function () use (&$heard): array {
    $out = $heard;
    $heard = [];
    return $out;
};
$json = (string) file_get_contents("{$fx}/minn-probe-pl_PL-minn-probe-handle.json");
$asPolish = static fn () => 'pl_PL';
add_filter('determine_locale', $asPolish);

$plugin = WP_PLUGIN_DIR . '/minn-probe-js';
$theme = get_theme_root() . '/minn-probe-js-theme';
$written = [];
$put = static function (string $file) use ($json, &$written): void {
    @mkdir(dirname($file), 0777, true);
    file_put_contents($file, $json);
    $written[] = $file;
};
$run = static function (string $label, string $handle, string $src, string $domain, string $path = '') use ($say, $caught, $plain): void {
    wp_register_script($handle, $src, [], '1');
    $result = load_script_textdomain($handle, $domain, $path);
    $say($label, [$result === false ? false : (json_decode((string) $result, true)['locale_data']['messages']['Hello'][0] ?? 'other'), $caught()]);
    wp_deregister_script($handle);
};

// --- In the folder given: by handle first, then by the md5 of the path in the plugin.
$put("{$plugin}/languages/minn-probe-pl_PL-minn-probe-by-handle.json");
$run('by handle', 'minn-probe-by-handle', plugins_url('minn-probe-js/js/app.js'), 'minn-probe', "{$plugin}/languages");
$put("{$plugin}/languages/minn-probe-pl_PL-" . md5('js/app.js') . '.json');
$run('by md5 in the folder', 'minn-probe-md5', plugins_url('minn-probe-js/js/app.js'), 'minn-probe', "{$plugin}/languages");
$run('min file shares the md5', 'minn-probe-min', plugins_url('minn-probe-js/js/app.min.js'), 'minn-probe', "{$plugin}/languages");
$run('relative src', 'minn-probe-rel', '/wp-content/plugins/minn-probe-js/js/app.js', 'minn-probe', "{$plugin}/languages");
$run('src with query', 'minn-probe-query', plugins_url('minn-probe-js/js/app.js') . '?ver=9', 'minn-probe', "{$plugin}/languages");

// --- No folder: the languages folder for plugins, themes, or the site.
$put(WP_LANG_DIR . '/plugins/minn-probe-lang-pl_PL-' . md5('js/lang.js') . '.json');
$run('plugin in the languages folder', 'minn-probe-lang', plugins_url('minn-probe-js/js/lang.js'), 'minn-probe-lang');
$put(WP_LANG_DIR . '/themes/minn-probe-theme-pl_PL-' . md5('js/theme.js') . '.json');
$run('theme in the languages folder', 'minn-probe-theme-js', get_theme_root_uri() . '/minn-probe-js-theme/js/theme.js', 'minn-probe-theme');
$put(WP_LANG_DIR . '/pl_PL-' . md5('wp-includes/js/core.js') . '.json');
$run('core domain by locale alone', 'minn-probe-core', includes_url('js/core.js'), 'default');
$run('elsewhere', 'minn-probe-cdn', 'https://cdn.example.test/x.js', 'minn-probe');
$run('nothing anywhere', 'minn-probe-none', plugins_url('minn-probe-js/js/none.js'), 'minn-probe-none');

// --- The relative path filter decides the md5.
$retarget = static fn ($relative, $src) => 'js/app.js';
add_filter('load_script_textdomain_relative_path', $retarget, 20, 2);
$run('relative path filtered', 'minn-probe-retarget', plugins_url('minn-probe-js/js/other.js'), 'minn-probe', "{$plugin}/languages");
remove_filter('load_script_textdomain_relative_path', $retarget, 20);
$giveUp = static fn () => false;
add_filter('load_script_textdomain_relative_path', $giveUp, 20);
$run('relative path refused', 'minn-probe-refused', plugins_url('minn-probe-js/js/app.js'), 'minn-probe', "{$plugin}/languages");
remove_filter('load_script_textdomain_relative_path', $giveUp, 20);

// --- wp_set_script_translations and the block printed before the script. The
// engine registers its default scripts on init, which a probe run does not fire,
// so wp-i18n gets a stand-in there (a script whose dependency is missing never prints).
if (!wp_script_is('wp-i18n', 'registered')) {
    wp_register_script('wp-i18n', false, [], '1');
}
wp_register_script('minn-probe-printed', plugins_url('minn-probe-js/js/app.js'), [], '1');
$set = wp_set_script_translations('minn-probe-printed', 'minn-probe', "{$plugin}/languages");
$registered = wp_scripts()->registered['minn-probe-printed'] ?? null;
ob_start();
wp_print_scripts('minn-probe-printed');
$printed = (string) ob_get_clean();
preg_match('#<script id="minn-probe-printed-js-translations">.*?</script>\n#s', $printed, $block);
$say('wp_set_script_translations', [$set, $registered ? $registered->deps : null, $registered ? $registered->textdomain : null, $registered ? $plain($registered->translations_path) : null, $block[0] ?? null, $caught()]);
$say('wp_set_script_translations unknown handle', wp_set_script_translations('minn-probe-nope', 'minn-probe'));
wp_register_script('minn-probe-ordered', plugins_url('minn-probe-js/js/app.js'), [], '1');
wp_localize_script('minn-probe-ordered', 'minnProbe', ['a' => '1']);
wp_add_inline_script('minn-probe-ordered', 'var before = 1;', 'before');
wp_add_inline_script('minn-probe-ordered', 'var after = 1;', 'after');
wp_set_script_translations('minn-probe-ordered', 'minn-probe', "{$plugin}/languages");
ob_start();
wp_print_scripts('minn-probe-ordered');
preg_match_all('#<script[^>]*id="minn-probe-ordered-js(?:-([a-z]+))?"#', (string) ob_get_clean(), $order);
$say('printed order', $order[1]);
$say('print_translations', [wp_scripts()->print_translations('minn-probe-ordered', false) !== false, wp_scripts()->print_translations('minn-probe-untranslated-nope', false)]);
$caught();
wp_register_script('minn-probe-untranslated', plugins_url('minn-probe-js/js/none.js'), [], '1');
wp_set_script_translations('minn-probe-untranslated', 'minn-probe-none');
ob_start();
wp_print_scripts('minn-probe-untranslated');
$say('no translations, no block', !str_contains((string) ob_get_clean(), 'minn-probe-untranslated-js-translations'));
$caught();

remove_filter('determine_locale', $asPolish);
foreach ($written as $file) {
    @unlink($file);
}
foreach (["{$plugin}/languages", $plugin, "{$theme}/js", $theme, WP_LANG_DIR . '/themes'] as $dir) {
    @rmdir($dir);
}
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
