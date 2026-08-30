<?php

use Minn\Runtime\Runtime;

/**
 * The [embed] shortcode and the URL-on-its-own-line embeds: handlers a
 * plugin registers by regex, then the oEmbed providers, then a plain link.
 */
class WP_Embed
{
    public $handlers = [];
    public $post_ID = null;
    public $usecache = true;
    public $linkifunknown = true;
    public $last_attr = [];
    public $last_url = '';
    public $return_false_on_fail = false;

    public function __construct()
    {
    }

    /** Runs only the [embed] shortcode over the content, leaving every other shortcode for later. */
    public function run_shortcode($content)
    {
        $shortcodes = Runtime::shortcodes();
        $saved = $shortcodes->all();
        $shortcodes->removeAll();
        $shortcodes->add('embed', [$this, 'shortcode']);
        $content = do_shortcode((string) $content, true);
        $shortcodes->restore($saved);
        return $content;
    }

    public function maybe_run_ajax_cache()
    {
    }

    public function register_handler($id, $regex, $callback, $priority = 10)
    {
        $this->handlers[$priority][$id] = ['regex' => $regex, 'callback' => $callback];
    }

    public function unregister_handler($id, $priority = 10)
    {
        unset($this->handlers[$priority][$id]);
    }

    /** The first registered handler whose pattern matches the URL, or false. */
    public function get_embed_handler_html($attr, $url)
    {
        $rawattr = $attr;
        $attr = wp_parse_args($attr, wp_embed_defaults($url));
        ksort($this->handlers);
        foreach ($this->handlers as $priority => $handlers) {
            foreach ($handlers as $id => $handler) {
                if (preg_match($handler['regex'], (string) $url, $matches) && is_callable($handler['callback'])) {
                    $return = $handler['callback']($matches, $attr, $url, $rawattr);
                    if ($return !== false) {
                        return apply_filters('embed_handler_html', $return, $url, $attr);
                    }
                }
            }
        }
        return false;
    }

    /** The embed for a URL: a handler, an oEmbed provider, else a link (or false when asked). */
    public function shortcode($attr, $url = '')
    {
        $attr = is_array($attr) ? $attr : [];
        $url = (string) $url;
        if ($url === '' && !empty($attr['src'])) {
            $url = (string) $attr['src'];
        }
        $this->last_url = $url;
        if ($url === '') {
            $this->last_attr = $attr;
            return '';
        }
        $rawattr = $attr;
        $attr = wp_parse_args($attr, wp_embed_defaults($url));
        $this->last_attr = $attr;
        $url = str_replace('&amp;', '&', $url);
        $html = $this->get_embed_handler_html($rawattr, $url);
        if ($html !== false) {
            return $html;
        }
        // The stored oEmbed cache on the post answers before any fetch, the reference's order.
        if ($this->usecache && (int) $this->post_ID > 0) {
            $cached = get_post_meta((int) $this->post_ID, '_oembed_' . md5($url . serialize($attr)), true);
            if ($cached === '{{unknown}}') {
                return $this->maybe_make_link($url);
            }
            if (is_string($cached) && $cached !== '') {
                return apply_filters('embed_oembed_html', $cached, $url, $attr, (int) $this->post_ID);
            }
        }
        $html = wp_oembed_get($url, $attr);
        if ($html) {
            return apply_filters('embed_oembed_html', $html, $url, $attr, (int) $this->post_ID);
        }
        return $this->maybe_make_link($url);
    }

    public function delete_oembed_caches($post_id)
    {
        foreach ((array) get_post_custom_keys((int) $post_id) as $key) {
            if (str_starts_with((string) $key, '_oembed_')) {
                delete_post_meta((int) $post_id, (string) $key);
            }
        }
    }

    public function cache_oembed($post_id)
    {
        $post = get_post($post_id);
        if (!$post || !in_array($post->post_type, apply_filters('embed_cache_oembed_types', ['post', 'page']), true)) {
            return;
        }
        $this->post_ID = (int) $post->ID;
        $this->usecache = false;
        $content = $this->run_shortcode($post->post_content);
        $this->autoembed($content);
        $this->usecache = true;
    }

    /** URLs standing alone on a line, or alone in a paragraph, become embeds. */
    public function autoembed($content)
    {
        $content = (string) $content;
        $content = (string) preg_replace_callback('|^(\s*)(https?://[^\s<>"]+)(\s*)$|im', [$this, 'autoembed_callback'], $content);
        return (string) preg_replace_callback('|(<p(?: [^>]*)?>\s*)(https?://[^\s<>"]+)(\s*<\/p>)|i', [$this, 'autoembed_callback'], $content);
    }

    public function autoembed_callback($matches)
    {
        $oldval = $this->linkifunknown;
        $this->linkifunknown = false;
        $return = $this->shortcode([], $matches[2]);
        $this->linkifunknown = $oldval;
        return $matches[1] . $return . $matches[3];
    }

    public function maybe_make_link($url)
    {
        if ($this->return_false_on_fail) {
            return false;
        }
        $output = $this->linkifunknown ? '<a href="' . esc_url((string) $url) . '">' . esc_html((string) $url) . '</a>' : (string) $url;
        return apply_filters('embed_maybe_make_link', $output, (string) $url);
    }

    public function find_oembed_post_id($cache_key)
    {
        return null;
    }
}
