<?php

use Minn\Runtime\OEmbed;

/** The oEmbed provider table (data/oembed-providers.json) and the lookup; fetching is left to wp_remote_get. */
class WP_oEmbed
{
    public $providers = [];
    public static $early_providers = [];
    private $compat_methods = ['_fetch_with_format', '_parse_json', '_parse_xml', '_parse_xml_body'];

    public function __construct()
    {
        $providers = json_decode((string) file_get_contents(MINN_ENGINE_DIR . '/data/oembed-providers.json'), true) ?: [];
        if (!empty(self::$early_providers['add'])) {
            foreach (self::$early_providers['add'] as $format => $data) {
                $providers[$format] = $data;
            }
        }
        if (!empty(self::$early_providers['remove'])) {
            foreach (self::$early_providers['remove'] as $format) {
                unset($providers[$format]);
            }
        }
        self::$early_providers = [];
        $this->providers = apply_filters('oembed_providers', $providers);
        add_filter('oembed_dataparse', [$this, '_strip_newlines'], 10, 3);
    }

    public function __call($name, $arguments)
    {
        if (in_array($name, $this->compat_methods, true)) {
            return $this->$name(...$arguments);
        }
        return false;
    }

    public function get_provider($url, $args = '')
    {
        $args = wp_parse_args($args, ['discover' => true]);
        $provider = OEmbed::providerFor($this->providers, (string) $url);
        return $provider ?? ($args['discover'] ? $this->discover($url) : false);
    }

    public static function _add_provider_early($format, $provider, $regex = false)
    {
        if (empty(self::$early_providers['add'])) {
            self::$early_providers['add'] = [];
        }
        self::$early_providers['add'][$format] = [$provider, $regex];
    }

    public static function _remove_provider_early($format)
    {
        if (empty(self::$early_providers['remove'])) {
            self::$early_providers['remove'] = [];
        }
        self::$early_providers['remove'][] = $format;
    }

    public function get_data($url, $args = '')
    {
        $args = wp_parse_args($args);
        $provider = $this->get_provider($url, $args);
        if (!$provider) {
            return false;
        }
        $data = $this->fetch($provider, $url, $args);
        return $data === false ? false : $data;
    }

    public function get_html($url, $args = '')
    {
        $pre = apply_filters('pre_oembed_result', null, $url, $args);
        if ($pre !== null) {
            return $pre;
        }
        $data = $this->get_data($url, $args);
        if ($data === false) {
            return false;
        }
        return apply_filters('oembed_result', $this->data2html($data, $url), $url, $args);
    }

    public function discover($url)
    {
        return false;
    }

    public function fetch($provider, $url, $args = '')
    {
        $args = wp_parse_args($args, wp_embed_defaults($url));
        $provider = add_query_arg('maxwidth', (int) $args['width'], $provider);
        $provider = add_query_arg('maxheight', (int) $args['height'], $provider);
        $provider = add_query_arg('url', urlencode($url), $provider);
        $provider = add_query_arg('dnt', 1, $provider);
        $provider = apply_filters('oembed_fetch_url', $provider, $url, $args);
        foreach (['json', 'xml'] as $format) {
            $result = $this->_fetch_with_format($provider, $format);
            if (is_wp_error($result) && 'not-implemented' === $result->get_error_code()) {
                continue;
            }
            return $result ? $result : false;
        }
        return false;
    }

    private function _fetch_with_format($provider_url_with_args, $format)
    {
        $provider_url_with_args = add_query_arg('format', $format, $provider_url_with_args);
        $args = apply_filters('oembed_remote_get_args', [], $provider_url_with_args);
        $response = wp_safe_remote_get($provider_url_with_args, $args);
        if (501 === wp_remote_retrieve_response_code($response)) {
            return new WP_Error('not-implemented');
        }
        $body = wp_remote_retrieve_body($response);
        if (!$body) {
            return false;
        }
        $parse_method = "_parse_$format";
        return $this->$parse_method($body);
    }

    private function _parse_json($response_body)
    {
        $data = OEmbed::parseJson((string) $response_body);
        return $data === null ? false : (object) $data;
    }

    private function _parse_xml($response_body)
    {
        return $this->_parse_xml_body($response_body);
    }

    private function _parse_xml_body($response_body)
    {
        $data = OEmbed::parseXml((string) $response_body);
        return $data === null ? false : (object) $data;
    }

    public function data2html($data, $url)
    {
        if (!is_object($data) || empty($data->type)) {
            return false;
        }
        $html = OEmbed::html(get_object_vars($data), (string) $url, static fn (string $v) => esc_url($v), static fn (string $v) => esc_attr($v), static fn (string $v) => esc_html($v));
        return apply_filters('oembed_dataparse', $html ?? false, $data, $url);
    }

    public function _strip_newlines($html, $data, $url)
    {
        return OEmbed::stripNewlines((string) $html);
    }
}
