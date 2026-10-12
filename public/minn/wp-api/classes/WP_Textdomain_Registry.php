<?php
/**
 * Where a text domain's translation files live. Recording plus a language-
 * directory scan; probed on the reference: get() answers false until a
 * locale's .mo actually exists (en_US included), a set() path wins, and
 * has('default') is true regardless.
 */
#[AllowDynamicProperties]
class WP_Textdomain_Registry
{
    protected $all = [];
    protected $current = [];
    protected $custom_paths = [];
    protected $cached_mo_files = [];
    protected $domains_with_translations = [];

    public function init()
    {
    }

    /** The folder a domain's files for a locale come from (false for none), as lang_dir_for_domain leaves it. */
    public function get($domain, $locale)
    {
        if (!isset($this->all[$domain][$locale]) && !array_key_exists($locale, $this->all[$domain] ?? [])) {
            $this->all[$domain][$locale] = $this->find($domain, $locale);
        }
        return apply_filters('lang_dir_for_domain', $this->all[$domain][$locale], $domain, $locale);
    }

    /** Whether a domain may have translations: one never looked up yet, default, a custom path, or a lookup that found a folder. */
    public function has($domain)
    {
        return !isset($this->all[$domain]) || $domain === 'default' || isset($this->custom_paths[$domain]) || !empty(array_filter($this->all[$domain]));
    }

    public function set($domain, $locale, $path)
    {
        $this->all[$domain][$locale] = $path && is_string($path) ? rtrim($path, '/') . '/' : $path;
        $this->current[$domain] = $this->all[$domain][$locale];
    }

    public function set_custom_path($domain, $path)
    {
        $this->custom_paths[$domain] = rtrim((string) $path, '/');
    }

    /** The translation files in a folder, unless pre_get_language_files_from_path answers for it. */
    public function get_language_files_from_path($path)
    {
        $pre = apply_filters('pre_get_language_files_from_path', null, $path);
        if ($pre !== null) {
            return $pre;
        }
        $path = rtrim((string) $path, '/');
        return array_values(array_filter(array_merge((array) glob($path . '/*.mo'), (array) glob($path . '/*.l10n.php'))));
    }

    public function invalidate_mo_files_cache($file, $translation)
    {
        $this->cached_mo_files = [];
    }

    public function get_paths_for_domain($domain)
    {
        $paths = [defined('WP_LANG_DIR') ? WP_LANG_DIR . '/plugins' : ''];
        if (isset($this->custom_paths[$domain])) {
            $paths[] = $this->custom_paths[$domain];
        }
        return array_values(array_filter($paths));
    }

    public function get_path_from_lang_dir($domain, $locale)
    {
        return $this->find($domain, $locale);
    }

    /** @return string|false the trailing-slashed directory holding {domain}-{locale}.mo, or false */
    /** The folder holding a domain's files for a locale; failing that, the domain's custom path; false for neither. */
    protected function find($domain, $locale)
    {
        $found = \Minn\Support\Paths::translationDir((string) $domain, (string) $locale, defined('WP_LANG_DIR') ? WP_LANG_DIR : '', $this->custom_paths[$domain] ?? null, fn (string $dir): array => $this->get_language_files_from_path($dir));
        return $found ?: (isset($this->custom_paths[$domain]) ? rtrim($this->custom_paths[$domain], '/') . '/' : false);
    }
}
