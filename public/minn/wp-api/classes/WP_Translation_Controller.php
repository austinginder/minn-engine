<?php
/** The translation registry as plugins reach it: a facade over Minn\I18n\TextDomains, one per request. */

use Minn\I18n\Catalog;
use Minn\I18n\TranslationFiles;
use Minn\Runtime\Runtime;

class WP_Translation_Controller
{
    protected $current_locale = 'en_US';
    protected $loaded_translations = [];
    protected $loaded_files = [];
    private static $instance = null;

    public static function get_instance(): WP_Translation_Controller
    {
        return self::$instance ??= new self();
    }

    public function get_locale(): string
    {
        return $this->current_locale;
    }

    public function set_locale(string $locale)
    {
        $this->current_locale = $locale;
    }

    public function load_file(string $translation_file, string $textdomain = 'default', ?string $locale = null): bool
    {
        // A readable .mo or .php file loads; one that does not parse is accepted but kept out, as the reference drops it the first time it is read (probe translation-classes).
        if (!is_readable($translation_file) || !in_array(pathinfo($translation_file, PATHINFO_EXTENSION), ['mo', 'php'], true)) {
            return false;
        }
        $catalog = TranslationFiles::read($translation_file);
        if ($catalog !== null) {
            Runtime::textDomains()->add($textdomain, $translation_file, $catalog);
        }
        $this->loaded_translations[$locale ?? $this->current_locale][$textdomain] = true;
        return true;
    }

    /** The file taken out of the domain; true whether or not it was loaded, as on the reference (probe translation-classes). */
    public function unload_file($file, string $textdomain = 'default', ?string $locale = null): bool
    {
        Runtime::textDomains()->unloadFile($textdomain, $file instanceof WP_Translation_File ? $file->get_file() : (string) $file);
        return true;
    }

    public function unload_textdomain(string $textdomain = 'default', ?string $locale = null): bool
    {
        foreach (array_keys($this->loaded_translations) as $loaded) {
            unset($this->loaded_translations[$loaded][$textdomain]);
        }
        return Runtime::textDomains()->unload($textdomain);
    }

    /** Whether a domain is loaded for a locale (the current one when none is given); a domain loaded without a locale on record answers for any. */
    public function is_textdomain_loaded(string $textdomain = 'default', ?string $locale = null): bool
    {
        $byLocale = array_filter(array_map(static fn (array $domains): bool => isset($domains[$textdomain]), $this->loaded_translations));
        if ($byLocale !== [] && !isset($byLocale[$locale ?? $this->current_locale])) {
            return false;
        }
        return Runtime::textDomains()->has($textdomain);
    }

    public function translate(string $text, string $context = '', string $textdomain = 'default', ?string $locale = null)
    {
        return Runtime::textDomains()->translate($textdomain, Catalog::key($text, $context)) ?? false;
    }

    public function translate_plural(array $plurals, int $number, string $context = '', string $textdomain = 'default', ?string $locale = null)
    {
        return Runtime::textDomains()->translatePlural($textdomain, Catalog::key((string) ($plurals[0] ?? ''), $context), $number) ?? false;
    }

    public function get_headers(string $textdomain = 'default'): array
    {
        // For the current locale only: a domain loaded for another answers nothing (probe translation-classes).
        if (!$this->is_textdomain_loaded($textdomain)) {
            return [];
        }
        $headers = [];
        foreach (array_reverse(Runtime::textDomains()->catalogs($textdomain)) as $catalog) {
            foreach ($catalog->headers as $name => $value) {
                $headers[$this->normalize_header((string) $name)] = $value;
            }
        }
        return $headers;
    }

    protected function normalize_header(string $header): string
    {
        return implode('-', array_map('ucfirst', explode('-', strtolower($header))));
    }

    public function get_entries(string $textdomain = 'default'): array
    {
        // For the current locale only: a domain loaded for another answers nothing (probe translation-classes).
        if (!$this->is_textdomain_loaded($textdomain)) {
            return [];
        }
        $entries = [];
        foreach (array_reverse(Runtime::textDomains()->catalogs($textdomain)) as $catalog) {
            foreach ($catalog->entries as $key => $entry) {
                $entries[$key] = implode("\0", $entry['translations']);
            }
        }
        return $entries;
    }

    protected function locate_translation(string $singular, string $textdomain = 'default', ?string $locale = null)
    {
        foreach (Runtime::textDomains()->catalogs($textdomain) as $file => $catalog) {
            if ($catalog->has($singular)) {
                return ['entries' => $catalog->entries[$singular]['translations'], 'source' => WP_Translation_File::create($file)];
            }
        }
        return false;
    }

    protected function get_files(string $textdomain = 'default', ?string $locale = null): array
    {
        return array_values(array_filter(array_map(static fn (string $file) => WP_Translation_File::create($file), array_keys(Runtime::textDomains()->catalogs($textdomain)))));
    }

    /** Whether the domain was loaded for the locale (the current one by default) and its catalog translates the string. */
    public function has_translation(string $singular, string $textdomain = 'default', ?string $locale = null): bool
    {
        return isset($this->loaded_translations[$locale ?? $this->current_locale][$textdomain]) && Runtime::textDomains()->translate($textdomain, $singular) !== null;
    }
}
