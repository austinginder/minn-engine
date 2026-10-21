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

    /** An upgrade that installs translations forgets the listings of the folders they went to. */
    public function init()
    {
        add_action('upgrader_process_complete', [$this, 'invalidate_mo_files_cache'], 10, 2);
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

    /** A domain's folder for a locale set, trailing-slashed; an empty one is none (false). */
    public function set($domain, $locale, $path)
    {
        $this->all[$domain][$locale] = $path ? rtrim((string) $path, '/') . '/' : false;
        $this->current[$domain] = $this->all[$domain][$locale];
    }

    public function set_custom_path($domain, $path)
    {
        $this->custom_paths[$domain] = rtrim((string) $path, '/');
    }

    /** The translation files in a folder, unless pre_get_language_files_from_path answers for it; listed once a request (Runtime\Translations::folderFiles). */
    public function get_language_files_from_path($path)
    {
        return Minn\Runtime\Translations::folderFiles((string) $path);
    }

    /** The listings of the folders an upgrade installed translations to, forgotten (Runtime\Translations::forgetFolders). */
    public function invalidate_mo_files_cache($upgrader, $hook_extra)
    {
        Minn\Runtime\Translations::forgetFolders($hook_extra);
    }

    private function get_paths_for_domain($domain)
    {
        $paths = defined('WP_LANG_DIR') ? [WP_LANG_DIR . '/plugins', WP_LANG_DIR . '/themes'] : [];
        if (isset($this->custom_paths[$domain])) {
            $paths[] = $this->custom_paths[$domain];
        }
        return array_values(array_filter($paths));
    }

    private function get_path_from_lang_dir($domain, $locale)
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
