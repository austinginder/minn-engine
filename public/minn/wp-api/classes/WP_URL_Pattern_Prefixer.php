<?php

/**
 * Path patterns put under the site's paths (probe speculative-loading): a
 * context's path (home, site, uploads, content, plugins, template,
 * stylesheet) is escaped for URL patterns and joined to the pattern by one
 * slash; a path holding ? or : is grouped in braces so it stays literal.
 */
class WP_URL_Pattern_Prefixer
{
    private $contexts;

    public function __construct(array $contexts = [])
    {
        $contexts = $contexts !== [] ? $contexts : self::get_default_contexts();
        $this->contexts = array_map(static fn ($path) => self::escape_pattern_string((string) $path), $contexts);
    }

    public function prefix_path_pattern(string $path_pattern, string $context = 'home'): string
    {
        if (!isset($this->contexts[$context])) {
            _doing_it_wrong(__FUNCTION__, sprintf('Invalid URL pattern context %s.', $context), '6.8.0');
            return $path_pattern;
        }
        $prefix = rtrim($this->contexts[$context], '/');
        $prefix = str_contains($prefix, '\\?') || str_contains($prefix, '\\:') ? '{' . $prefix . '}' : $prefix;
        return $prefix . '/' . ltrim($path_pattern, '/');
    }

    public static function get_default_contexts(): array
    {
        $path = static fn (string $url) => trailingslashit((string) wp_parse_url($url, PHP_URL_PATH));
        return [
            'home' => $path(home_url('/')),
            'site' => $path(site_url('/')),
            'uploads' => $path(wp_upload_dir(null, false)['baseurl']),
            'content' => $path(content_url()),
            'plugins' => $path(plugins_url()),
            'template' => $path(get_template_directory_uri()),
            'stylesheet' => $path(get_stylesheet_directory_uri()),
        ];
    }

    private static function escape_pattern_string(string $str): string
    {
        return addcslashes($str, '+*?:{}()\\');
    }
}
