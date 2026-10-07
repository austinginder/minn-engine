<?php
/** Translation functions over Minn\I18n: text domains loaded from .l10n.php and .mo files. Behaviour from contracts/fixtures/api/l10n.json. */

use Minn\I18n\Gettext;
use Minn\I18n\ScriptTranslations;
use Minn\I18n\TranslationFiles;
use Minn\Runtime\Runtime;

function get_locale()
{
    if (Runtime::locales()->switched()) {
        return (string) Runtime::locales()->current();
    }
    $locale = apply_filters('pre_determine_locale', null);
    if (is_string($locale) && $locale !== '') {
        return $locale;
    }
    $locale = (string) (Runtime::current()->site->option('WPLANG') ?? '');
    return apply_filters('locale', $locale === '' ? 'en_US' : $locale);
}

/** A user's locale (the current user's for 0), or the site's when the user has none or does not exist. */
function get_user_locale($user = 0)
{
    $object = match (true) {
        $user instanceof WP_User => $user,
        (int) $user === 0 && !is_object($user) => wp_get_current_user(),
        is_numeric($user) => get_userdata((int) $user),
        default => false,
    };
    $locale = $object instanceof WP_User ? (string) get_user_meta($object->ID, 'locale', true) : '';
    return $locale !== '' ? $locale : get_locale();
}

function determine_locale()
{
    if (Runtime::locales()->switched()) {
        return (string) Runtime::locales()->current();
    }
    $locale = apply_filters('pre_determine_locale', null);
    if (is_string($locale) && $locale !== '') {
        return $locale;
    }
    return apply_filters('determine_locale', get_locale());
}

// Engine code and these names translate through one class; it loads a domain just in time through the facade's loader.
Gettext::loadWith(static fn (string $domain) => _load_textdomain_just_in_time($domain));

function translate($text, $domain = 'default')
{
    if (!is_scalar($text)) {
        $text = apply_filters('gettext', $text, $text, $domain);
        return apply_filters("gettext_{$domain}", $text, $text, $domain);
    }
    return Gettext::text((string) $text, (string) $domain);
}

function translate_with_gettext_context($text, $context, $domain = 'default')
{
    return Gettext::inContext((string) $text, (string) $context, (string) $domain);
}

function __($text, $domain = 'default')
{
    return translate($text, $domain);
}

function _e($text, $domain = 'default')
{
    echo translate($text, $domain);
}

function _x($text, $context, $domain = 'default')
{
    return translate_with_gettext_context($text, $context, $domain);
}

function _ex($text, $context, $domain = 'default')
{
    echo _x($text, $context, $domain);
}

function _n($single, $plural, $number, $domain = 'default')
{
    return Gettext::plural((string) $single, (string) $plural, (int) $number, (string) $domain);
}

function _nx($single, $plural, $number, $context, $domain = 'default')
{
    return Gettext::pluralInContext((string) $single, (string) $plural, (int) $number, (string) $context, (string) $domain);
}

function _n_noop($singular, $plural, $domain = null)
{
    return ['0' => $singular, '1' => $plural, 'singular' => $singular, 'plural' => $plural, 'context' => null, 'domain' => $domain];
}

function _nx_noop($singular, $plural, $context, $domain = null)
{
    return ['0' => $singular, '1' => $plural, '2' => $context, 'singular' => $singular, 'plural' => $plural, 'context' => $context, 'domain' => $domain];
}

function translate_nooped_plural($nooped_plural, $count, $domain = 'default')
{
    if ($nooped_plural['domain']) {
        $domain = $nooped_plural['domain'];
    }
    return $nooped_plural['context']
        ? _nx($nooped_plural['singular'], $nooped_plural['plural'], $count, $nooped_plural['context'], $domain)
        : _n($nooped_plural['singular'], $nooped_plural['plural'], $count, $domain);
}

function esc_html__($text, $domain = 'default')
{
    return esc_html(translate($text, $domain));
}

function esc_attr__($text, $domain = 'default')
{
    return esc_attr(translate($text, $domain));
}

function esc_html_e($text, $domain = 'default')
{
    echo esc_html(translate($text, $domain));
}

function esc_attr_e($text, $domain = 'default')
{
    echo esc_attr(translate($text, $domain));
}

function esc_html_x($text, $context, $domain = 'default')
{
    return esc_html(translate_with_gettext_context($text, $context, $domain));
}

function esc_attr_x($text, $context, $domain = 'default')
{
    return esc_attr(translate_with_gettext_context($text, $context, $domain));
}

/** @internal loads the domain from a folder's file for the locale when there is one, or names the folder for later */
function _minn_load_from_folder(string $domain, string $folder, string $file, string $locale): bool
{
    $folder = rtrim($folder, '/');
    $mofile = "{$folder}/{$file}.mo";
    if (is_readable($mofile) || is_readable("{$folder}/{$file}.l10n.php")) {
        return load_textdomain($domain, $mofile, $locale);
    }
    Runtime::textDomains()->rememberFolder($domain, $folder);
    return true;
}

function load_plugin_textdomain($domain, $deprecated = false, $plugin_rel_path = false)
{
    $folder = WP_PLUGIN_DIR . ($plugin_rel_path !== false ? '/' . trim((string) $plugin_rel_path, '/') : '');
    $locale = determine_locale();
    return _minn_load_from_folder((string) $domain, $folder, "{$domain}-{$locale}", $locale);
}

function load_muplugin_textdomain($domain, $mu_plugin_rel_path = '')
{
    $locale = determine_locale();
    return _minn_load_from_folder((string) $domain, WPMU_PLUGIN_DIR . '/' . ltrim((string) $mu_plugin_rel_path, '/'), "{$domain}-{$locale}", $locale);
}

function load_theme_textdomain($domain, $path = false)
{
    $locale = determine_locale();
    // A folder named in code holds "{domain}-{locale}" files, inside the themes root or not; only the active theme's own folder goes by locale alone.
    return _minn_load_from_folder((string) $domain, $path !== false ? (string) $path : get_template_directory(), "{$domain}-{$locale}", $locale);
}

function load_child_theme_textdomain($domain, $path = false)
{
    return load_theme_textdomain($domain, $path !== false ? $path : get_stylesheet_directory());
}

function load_textdomain($domain, $mofile, $locale = null)
{
    $loaded = apply_filters('pre_load_textdomain', null, $domain, $mofile, $locale);
    if ($loaded !== null) {
        return (bool) $loaded;
    }
    if (apply_filters('override_load_textdomain', false, $domain, $mofile, $locale)) {
        return true;
    }
    do_action('load_textdomain', $domain, $mofile);
    $mofile = (string) apply_filters('load_textdomain_mofile', $mofile, $domain);
    $locale ??= determine_locale();
    $format = apply_filters('translation_file_format', 'php', $domain);
    foreach (TranslationFiles::candidates($mofile, $format === 'mo' ? 'mo' : 'php') as $candidate) {
        $file = (string) apply_filters('load_translation_file', $candidate, $domain, $locale);
        if (WP_Translation_Controller::get_instance()->load_file($file, (string) $domain, (string) $locale)) {
            return true;
        }
    }
    return false;
}

function unload_textdomain($domain, $reloadable = false)
{
    if (apply_filters('override_unload_textdomain', false, $domain, $reloadable)) {
        return true;
    }
    do_action('unload_textdomain', $domain, $reloadable);
    $had = Runtime::textDomains()->unload((string) $domain);
    // Only a domain that really had files stays closed; one merely looked for may load later.
    if ($had && !$reloadable) {
        Runtime::textDomains()->close((string) $domain);
    }
    return $had;
}

/**
 * A domain nobody loaded or asked for yet is looked for once: the folder a
 * plugin or theme named for it when its file is there, then the site's
 * languages folder for plugins and themes, then the named folder's file even
 * though it is missing. A domain unloaded for good stays out.
 */
function _load_textdomain_just_in_time($domain)
{
    $domains = Runtime::textDomains();
    $domain = (string) $domain;
    if ($domain === 'default' || $domains->known($domain)) {
        return false;
    }
    $domains->markKnown($domain);
    if ($domains->closed($domain)) {
        return false;
    }
    $locale = determine_locale();
    $file = _minn_textdomain_file($domain, $locale);
    return $file !== null && load_textdomain($domain, $file, $locale);
}

/** @internal the .mo path a domain reads for a locale, or null when nothing names one */
function _minn_textdomain_file(string $domain, string $locale): ?string
{
    $named = Runtime::textDomains()->folderFile($domain, $locale);
    foreach ([$named, WP_LANG_DIR . "/plugins/{$domain}-{$locale}.mo", WP_LANG_DIR . "/themes/{$domain}-{$locale}.mo"] as $mofile) {
        if ($mofile !== null && (is_readable($mofile) || is_readable(substr($mofile, 0, -3) . '.l10n.php'))) {
            return $mofile;
        }
    }
    return $named;
}

/**
 * @internal a locale switch: the core domain first, then every known domain
 * in the order it appeared, unloaded and loaded again for the new locale; a
 * domain closed for good or with nothing to read stays known and empty.
 */
function _minn_reload_textdomains(string $locale): void
{
    WP_Translation_Controller::get_instance()->set_locale($locale);
    load_default_textdomain($locale);
    $domains = Runtime::textDomains();
    foreach ($domains->knownDomains() as $domain) {
        if ($domain === 'default') {
            continue;
        }
        unload_textdomain($domain, true);
        $file = $domains->closed($domain) ? null : _minn_textdomain_file($domain, $locale);
        if ($file === null || !load_textdomain($domain, $file, $locale)) {
            $domains->markKnown($domain);
        }
    }
}

function load_default_textdomain($locale = null)
{
    $locale = (string) ($locale ?? determine_locale());
    unload_textdomain('default', true);
    return load_textdomain('default', WP_LANG_DIR . "/{$locale}.mo", $locale);
}

function is_textdomain_loaded($domain)
{
    return Runtime::textDomains()->has((string) $domain);
}

function get_translations_for_domain($domain)
{
    static $noop = null;
    _load_textdomain_just_in_time($domain);
    if (!Runtime::textDomains()->has((string) $domain)) {
        return $noop ??= new NOOP_Translations();
    }
    return new WP_Translations(WP_Translation_Controller::get_instance(), (string) $domain);
}

function wp_set_script_translations($handle, $domain = 'default', $path = '')
{
    return wp_scripts()->set_translations((string) $handle, (string) $domain, (string) $path);
}

/**
 * A registered script's JSON translations: by handle in the folder given,
 * then by the md5 of its path in that folder, then in the site's languages
 * folder for its plugin, theme or the site; false when none is found.
 */
function load_script_textdomain($handle, $domain = 'default', $path = '')
{
    $item = _minn_assets('script')->item((string) $handle);
    if ($item === null) {
        return false;
    }
    $prefix = ScriptTranslations::prefix((string) $domain, determine_locale());
    $path = rtrim((string) $path, '/');
    if ($path !== '' && ($found = load_script_translations("{$path}/{$prefix}-{$handle}.json", $handle, $domain)) !== false) {
        return $found;
    }
    $src = ScriptTranslations::absolute((string) $item['src'], site_url());
    [$relative, $folder] = ScriptTranslations::place($src, site_url(), plugins_url(), get_theme_root_uri());
    $relative = apply_filters('load_script_textdomain_relative_path', $relative, $src, false);
    if (!is_string($relative) || $relative === '') {
        return load_script_translations(false, $handle, $domain);
    }
    $file = $prefix . '-' . ScriptTranslations::fileHash($relative) . '.json';
    foreach ([...($path !== '' ? ["{$path}/{$file}"] : []), rtrim(WP_LANG_DIR . '/' . $folder, '/') . '/' . $file] as $candidate) {
        if (($found = load_script_translations($candidate, $handle, $domain)) !== false) {
            return $found;
        }
    }
    return load_script_translations(false, $handle, $domain);
}

function load_script_translations($file, $handle, $domain)
{
    $translations = apply_filters('pre_load_script_translations', null, $file, $handle, $domain);
    if ($translations !== null) {
        return $translations;
    }
    $file = apply_filters('load_script_translation_file', $file, $handle, $domain);
    if (!$file || !is_readable((string) $file)) {
        return false;
    }
    return apply_filters('load_script_translations', (string) file_get_contents((string) $file), $file, $handle, $domain);
}

function get_available_languages($dir = null)
{
    $out = [];
    foreach (glob(($dir ?? WP_LANG_DIR) . '/*.mo') ?: [] as $file) {
        $name = basename($file, '.mo');
        if (!str_starts_with($name, 'continents-cities') && !str_starts_with($name, 'ms-') && !str_starts_with($name, 'admin-')) {
            $out[] = $name;
        }
    }
    return $out;
}

function is_locale_switched()
{
    return _minn_locale_switcher()->is_switched();
}

function switch_to_locale($locale)
{
    return _minn_locale_switcher()->switch_to_locale($locale);
}

function switch_to_user_locale($user_id)
{
    return _minn_locale_switcher()->switch_to_user_locale($user_id);
}

function restore_previous_locale()
{
    return _minn_locale_switcher()->restore_previous_locale();
}

function restore_current_locale()
{
    return _minn_locale_switcher()->restore_current_locale();
}

/** @internal the request's locale switcher, the global plugins call, made when nothing made it yet */
function _minn_locale_switcher(): WP_Locale_Switcher
{
    if (!($GLOBALS['wp_locale_switcher'] ?? null) instanceof WP_Locale_Switcher) {
        $GLOBALS['wp_locale_switcher'] = new WP_Locale_Switcher();
        $GLOBALS['wp_locale_switcher']->init();
    }
    return $GLOBALS['wp_locale_switcher'];
}

function number_format_i18n($number, $decimals = 0)
{
    return apply_filters('number_format_i18n', number_format((float) $number, absint($decimals), '.', ','), $number, $decimals);
}

function wp_get_word_count_type()
{
    return 'words';
}

function wp_get_list_item_separator()
{
    return ', ';
}

/** Installed .po files by text domain and locale with their headers; "core", "plugins", or "themes". */
function wp_get_installed_translations($type)
{
    $dir = WP_LANG_DIR . ($type === 'core' ? '' : '/' . $type);
    $out = [];
    foreach (glob($dir . '/*.po') ?: [] as $file) {
        $base = basename($file, '.po');
        // A translation is installed with both halves: the .po without its .mo is not one.
        if (!is_file(substr($file, 0, -3) . '.mo') || !preg_match('/^(?:(.+)-)?([a-z]{2,3}(?:_[A-Z]{2})?(?:_[a-z]+)?)$/', $base, $m)) {
            continue;
        }
        $domain = $m[1] !== '' ? $m[1] : 'default';
        $out[$domain][$m[2]] = get_file_data($file, ['POT-Creation-Date' => '"POT-Creation-Date', 'PO-Revision-Date' => '"PO-Revision-Date', 'Project-Id-Version' => '"Project-Id-Version', 'X-Generator' => '"X-Generator']);
    }
    return $out;
}

function translations_api($type, $args = null)
{
    $short = apply_filters('translations_api', false, $type, $args);
    if ($short !== false) {
        return $short;
    }
    if (!in_array($type, ['plugins', 'themes', 'core'], true)) {
        return new WP_Error('invalid_type', 'Invalid translation type.');
    }
    $response = wp_remote_post('https://api.wordpress.org/translations/' . $type . '/1.0/', ['timeout' => 10, 'body' => ['wp_version' => $GLOBALS['wp_version'] ?? '', 'locale' => get_locale(), 'version' => (string) (((array) $args)['version'] ?? '')]]);
    if (is_wp_error($response)) {
        return new WP_Error('translations_api_failed', 'An unexpected error occurred.', $response->get_error_message());
    }
    $body = json_decode(wp_remote_retrieve_body($response), true);
    return apply_filters('translations_api_result', is_array($body) ? $body : new WP_Error('translations_api_failed', 'An unexpected error occurred.'), $type, $args);
}

function wp_dropdown_languages($args = [])
{
    $args = wp_parse_args($args, ['id' => '', 'name' => '', 'languages' => [], 'translations' => [], 'selected' => '', 'echo' => true, 'show_available_translations' => true]);
    $output = Minn\Admin\LanguageChoices::dropdown(
        (string) $args['name'],
        (string) $args['id'],
        array_map('strval', (array) $args['languages']),
        (array) $args['translations'],
        (string) $args['selected'],
        (bool) $args['show_available_translations'],
    );
    if (!$args['echo']) {
        return $output;
    }
    echo $output;
    return '';
}
