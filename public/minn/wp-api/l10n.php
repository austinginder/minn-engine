<?php
/** Translation functions. English passes through; the .mo reader is a later milestone. */

use Minn\Runtime\Runtime;

function get_locale()
{
    $locale = apply_filters('pre_determine_locale', null);
    if (is_string($locale) && $locale !== '') {
        return $locale;
    }
    $locale = (string) (Runtime::current()->site->option('WPLANG') ?? '');
    return apply_filters('locale', $locale === '' ? 'en_US' : $locale);
}

function get_user_locale($user = 0)
{
    return get_locale();
}

function determine_locale()
{
    $locale = apply_filters('pre_determine_locale', null);
    if (is_string($locale) && $locale !== '') {
        return $locale;
    }
    return apply_filters('determine_locale', get_locale());
}

function translate($text, $domain = 'default')
{
    $translation = apply_filters('gettext', $text, $text, $domain);
    return apply_filters("gettext_{$domain}", $translation, $text, $domain);
}

function translate_with_gettext_context($text, $context, $domain = 'default')
{
    $translation = apply_filters('gettext_with_context', $text, $text, $context, $domain);
    return apply_filters("gettext_with_context_{$domain}", $translation, $text, $context, $domain);
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
    $translation = (int) $number === 1 ? $single : $plural;
    $translation = apply_filters('ngettext', $translation, $single, $plural, $number, $domain);
    return apply_filters("ngettext_{$domain}", $translation, $single, $plural, $number, $domain);
}

function _nx($single, $plural, $number, $context, $domain = 'default')
{
    $translation = (int) $number === 1 ? $single : $plural;
    $translation = apply_filters('ngettext_with_context', $translation, $single, $plural, $number, $context, $domain);
    return apply_filters("ngettext_with_context_{$domain}", $translation, $single, $plural, $number, $context, $domain);
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

function load_plugin_textdomain($domain, $deprecated = false, $plugin_rel_path = false)
{
    return true;
}

function load_muplugin_textdomain($domain, $mu_plugin_rel_path = '')
{
    return true;
}

function load_theme_textdomain($domain, $path = false)
{
    return true;
}

function load_child_theme_textdomain($domain, $path = false)
{
    return true;
}

function load_textdomain($domain, $mofile, $locale = null)
{
    return false;
}

function unload_textdomain($domain, $reloadable = false)
{
    return false;
}

function load_default_textdomain($locale = null)
{
    return false;
}

function is_textdomain_loaded($domain)
{
    return false;
}

function get_translations_for_domain($domain)
{
    return new NOOP_Translations();
}

function wp_set_script_translations($handle, $domain = 'default', $path = '')
{
    return true;
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
    return false;
}

function switch_to_locale($locale)
{
    return false;
}

function restore_previous_locale()
{
    return false;
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
        if (!preg_match('/^(?:(.+)-)?([a-z]{2,3}(?:_[A-Z]{2})?(?:_[a-z]+)?)$/', $base, $m)) {
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
