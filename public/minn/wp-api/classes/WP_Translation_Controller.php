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
        $catalog = TranslationFiles::read($translation_file);
        if ($catalog === null) {
            return false;
        }
        Runtime::textDomains()->add($textdomain, $translation_file, $catalog);
        return true;
    }

    public function unload_file($file, string $textdomain = 'default', ?string $locale = null): bool
    {
        return Runtime::textDomains()->unloadFile($textdomain, $file instanceof WP_Translation_File ? $file->get_file() : (string) $file);
    }

    public function unload_textdomain(string $textdomain = 'default', ?string $locale = null): bool
    {
        return Runtime::textDomains()->unload($textdomain);
    }

    public function is_textdomain_loaded(string $textdomain = 'default', ?string $locale = null): bool
    {
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

    public function has_translation(string $singular, string $textdomain = 'default', ?string $locale = null): bool
    {
        return Runtime::textDomains()->translate($textdomain, $singular) !== null;
    }
}
