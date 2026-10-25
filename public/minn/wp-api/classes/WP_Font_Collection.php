<?php

/**
 * A font collection (probe fonts): its slug (sanitized, with a notice when
 * that changed it), and its data, read when first asked for: the args, or
 * the font families from a JSON file or address given in their place,
 * kept to the collection schema. Missing data, a name or font families
 * are errors with notices.
 */
final class WP_Font_Collection
{
    public $slug;
    private $data;
    private $src;

    public function __construct(string $slug, array $args)
    {
        $this->slug = sanitize_title($slug);
        if ($this->slug !== $slug) {
            _doing_it_wrong(__METHOD__, sprintf('Font collection slug "%s" is not valid. Slugs must use only alphanumeric characters, dashes, and underscores.', $slug), '6.5.0');
        }
        if (is_string($args['font_families'] ?? null)) {
            $this->src = $args['font_families'];
        }
        $this->data = $args;
    }

    public function get_data()
    {
        if (is_wp_error($this->data)) {
            return $this->data;
        }
        if ($this->src !== null) {
            $families = $this->load_from_json($this->src);
            $this->src = null;
            if (is_wp_error($families)) {
                return $this->data = $families;
            }
            $this->data['font_families'] = $families;
        }
        $this->data = $this->sanitize_and_validate_data($this->data, ['name', 'font_families']);
        return $this->data;
    }

    private function load_from_json($file_or_url)
    {
        $json = wp_http_validate_url($file_or_url) ? $this->load_from_url($file_or_url) : $this->load_from_file($file_or_url);
        if (is_array($json) && isset($json['font_families'])) {
            return $json['font_families'];
        }
        $message = 'Font collection JSON file is invalid or does not exist.';
        _doing_it_wrong(__METHOD__, $message, '6.5.0');
        return new WP_Error('font_collection_json_missing', $message);
    }

    private function load_from_file($file)
    {
        return is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
    }

    /** The collection at an address, kept a day as the reference keeps it (Minn\Theme\FontCollectionSource). */
    private function load_from_url($url)
    {
        return \Minn\Theme\FontCollectionSource::remember((string) $url, static fn () => wp_safe_remote_get($url), static fn (array $families): array => (array) (WP_Font_Utils::sanitize_from_schema(['font_families' => $families], self::get_sanitization_schema())['font_families'] ?? []));
    }

    private function sanitize_and_validate_data($data, $required_properties = [])
    {
        // A description and categories always, first, whatever sanitizing left of them.
        $data = array_merge(['description' => '', 'categories' => []], WP_Font_Utils::sanitize_from_schema((array) $data, self::get_sanitization_schema()));
        foreach ((array) $required_properties as $property) {
            if (empty($data[$property])) {
                $message = sprintf('Font collection "%1$s" has missing or empty property: "%2$s".', $this->slug, $property);
                _doing_it_wrong(__METHOD__, $message, '6.5.0');
                return new WP_Error('font_collection_missing_property', $message);
            }
        }
        return $data;
    }

    private static function get_sanitization_schema()
    {
        $face = array_fill_keys(['fontFamily', 'fontStyle', 'fontWeight', 'fontDisplay', 'fontStretch', 'ascentOverride', 'descentOverride', 'fontVariant', 'fontFeatureSettings', 'fontVariationSettings', 'lineGapOverride', 'sizeAdjust', 'unicodeRange'], 'sanitize_text_field') + ['src' => static fn ($src) => is_array($src) ? array_map('sanitize_text_field', $src) : sanitize_text_field($src), 'preview' => 'sanitize_url'];
        return [
            'name' => 'sanitize_text_field',
            'description' => 'sanitize_text_field',
            'font_families' => [['font_family_settings' => ['name' => 'sanitize_text_field', 'slug' => static fn ($slug) => _wp_to_kebab_case(sanitize_title($slug)), 'fontFamily' => 'WP_Font_Utils::sanitize_font_family', 'preview' => 'sanitize_url', 'fontFace' => [$face]], 'categories' => ['sanitize_title']]],
            'categories' => [['name' => 'sanitize_text_field', 'slug' => 'sanitize_title']],
        ];
    }
}
