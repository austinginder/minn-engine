<?php

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
        $args = wp_parse_args($args);
        $provider = false;
        if (!isset($args['discover'])) {
            $args['discover'] = true;
        }
        foreach ($this->providers as $matchmask => $data) {
            [$providerurl, $regex] = $data;
            if (!$regex) {
                $matchmask = '#' . str_replace('___wildcard___', '(.+)', preg_quote(str_replace('*', '___wildcard___', $matchmask), '#')) . '#i';
                $matchmask = preg_replace('|^#http\\\\://|', '#https?\://', $matchmask);
            }
            if (preg_match($matchmask, $url)) {
                $provider = str_replace('{format}', 'json', $providerurl);
                break;
            }
        }
        if (!$provider && $args['discover']) {
            $provider = $this->discover($url);
        }
        return $provider;
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
        $data = json_decode(trim($response_body));
        return ($data && is_object($data)) ? $data : false;
    }

    private function _parse_xml($response_body)
    {
        if (!function_exists('simplexml_import_dom') || !class_exists('DOMDocument', false)) {
            return false;
        }
        return $this->_parse_xml_body($response_body);
    }

    private function _parse_xml_body($response_body)
    {
        $dom = new DOMDocument();
        if (!$dom->loadXML($response_body)) {
            return false;
        }
        if ('oembed' !== $dom->documentElement->tagName) {
            return false;
        }
        $xml = simplexml_import_dom($dom->documentElement);
        $return = new stdClass();
        foreach ($xml as $key => $value) {
            $return->$key = (string) $value;
        }
        return $return;
    }

    public function data2html($data, $url)
    {
        if (!is_object($data) || empty($data->type)) {
            return false;
        }
        $return = false;
        switch ($data->type) {
            case 'photo':
                if (empty($data->url) || empty($data->width) || empty($data->height)) {
                    break;
                }
                $title = !empty($data->title) && is_string($data->title) ? $data->title : '';
                $return = '<a href="' . esc_url($url) . '"><img src="' . esc_url($data->url) . '" alt="' . esc_attr($title) . '" width="' . esc_attr($data->width) . '" height="' . esc_attr($data->height) . '" /></a>';
                break;
            case 'video':
            case 'rich':
                if (!empty($data->html) && is_string($data->html)) {
                    $return = $data->html;
                }
                break;
            case 'link':
                if (!empty($data->title) && is_string($data->title)) {
                    $return = '<a href="' . esc_url($url) . '">' . esc_html($data->title) . '</a>';
                }
                break;
        }
        return apply_filters('oembed_dataparse', $return, $data, $url);
    }

    public function _strip_newlines($html, $data, $url)
    {
        if (!str_contains($html, "\n")) {
            return $html;
        }
        $count = 1;
        $found = [];
        $token = '__PRE__';
        $search = ["\t", "\n", "\r", ' '];
        $replace = ['__TAB__', '__NL__', '__CR__', '__SPACE__'];
        $tokenized = str_replace($search, $replace, $html);
        preg_match_all('#(<pre[^>]*>.+?</pre>)#i', $tokenized, $matches, PREG_SET_ORDER);
        foreach ($matches as $i => $match) {
            $tag_html = str_replace($replace, $search, $match[0]);
            $tag_token = $token . $i;
            $found[$tag_token] = $tag_html;
            $html = str_replace($tag_html, $tag_token, $html, $count);
        }
        $replaced = str_replace($replace, $search, $html);
        $stripped = str_replace(["\r\n", "\n"], '', $replaced);
        $pre = array_values($found);
        $tokens = array_keys($found);
        return str_replace($tokens, $pre, $stripped);
    }
}
