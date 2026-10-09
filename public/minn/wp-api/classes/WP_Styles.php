<?php

use Minn\Runtime\Assets;

/**
 * The styles registry (probe dependencies-classes). Printing goes through
 * it: each stylesheet prints its link (rel alternate stylesheet when it is
 * an alt, its title when it has one), then its inline style. Right to
 * left, an rtl of 'replace' prints the -rtl file in its place (id
 * handle-rtl-css) and an rtl address prints after it. A version left out
 * or empty is the WordPress version; null is none.
 */
class WP_Styles extends WP_Dependencies
{
    public $base_url;
    public $content_url;
    public $default_version;
    public $text_direction = 'ltr';
    public $concat = '';
    public $concat_version = '';
    public $do_concat = false;
    public $print_html = '';
    public $print_code = '';
    public $default_dirs;

    public function __construct(?Assets $assets = null)
    {
        parent::__construct($assets ?? new Assets('style'));
        $this->base_url = (string) get_option('siteurl');
        $this->content_url = defined('WP_CONTENT_URL') ? WP_CONTENT_URL : '';
        // The page's registries are made as the facade loads, before $wp_version is set.
        $this->default_version = $GLOBALS['wp_version'] ?? (Minn\Runtime\Runtime::booted() ? Minn\Runtime\Runtime::current()->version : '');
        $this->default_dirs = ['/wp-admin/', '/wp-includes/css/'];
        $self = $this;
        do_action_ref_array('wp_default_styles', [&$self]);
        $this->push();
    }

    public function do_item($handle, $group = false)
    {
        if (!parent::do_item($handle)) {
            return false;
        }
        $item = $this->assets->item((string) $handle);
        $inline = $this->print_inline_style($handle, false);
        $inline = $inline === false ? '' : sprintf("<style id=\"%s-inline-css\">\n%s\n</style>\n", esc_attr($handle), $inline);
        $has_source = $item['src'] !== false && $item['src'] !== '';
        echo ($has_source ? $this->links((string) $handle, $item) : '') . $inline;
        return true;
    }

    public function add_inline_style($handle, $code)
    {
        return $this->assets->addInline((string) $handle, (string) $code, 'after');
    }

    /**
     * A style's inline CSS: printed as its own tag (true), or returned with
     * its sourceURL trailer; false when it has none. A style inlined from a
     * file names that file as its source.
     */
    public function print_inline_style($handle, $display = true)
    {
        $code = $this->assets->item((string) $handle)['inline']['after'] ?? [];
        if ($code === []) {
            return false;
        }
        $source = _minn_inline_style_sources()[$handle] ?? ($handle . '-inline-css');
        $output = implode("\n", $code) . (defined('WP_DEBUG') && WP_DEBUG ? "\n/*# sourceURL=" . esc_attr($source) . ' */' : '');
        if (!$display) {
            return $output;
        }
        printf("<style id=\"%s-inline-css\">\n%s\n</style>\n", esc_attr($handle), $output);
        return true;
    }

    public function all_deps($handles, $recursion = false, $group = false)
    {
        $result = parent::all_deps($handles, $recursion, $group);
        if (!$recursion) {
            $this->to_do = apply_filters('print_styles_array', $this->to_do);
        }
        return $result;
    }

    /** The address a stylesheet prints: a relative source under base_url, the version as ver, through style_loader_src. */
    public function _css_href($src, $ver, $handle)
    {
        $src = (string) $src;
        if (!preg_match('#^(https?:)?//#', $src) && !($this->content_url && str_starts_with($src, $this->content_url))) {
            $src = $this->base_url . $src;
        }
        $ver = $ver === false || $ver === '' ? $this->default_version : $ver;
        if ($ver !== null && $ver !== true) {
            $src = add_query_arg('ver', (string) $ver, $src);
        }
        return esc_url(apply_filters('style_loader_src', $src, $handle));
    }

    public function in_default_dir($src)
    {
        if (!$this->default_dirs) {
            return true;
        }
        foreach ((array) $this->default_dirs as $dir) {
            if (str_starts_with((string) $src, $dir)) {
                return true;
            }
        }
        return false;
    }

    public function do_footer_items()
    {
        $this->do_items(false, 1);
        return $this->done;
    }

    public function reset()
    {
        $this->do_concat = false;
        $this->concat = '';
        $this->concat_version = '';
        $this->print_html = '';
        $this->print_code = '';
    }

    protected function get_dependency_warning_message($handle, $missing_dependency_handles)
    {
        return sprintf('The style with the handle "%1$s" was enqueued with dependencies that are not registered: %2$s.', $handle, implode(', ', $missing_dependency_handles));
    }

    /** A stylesheet's link tags: its own, or right to left the -rtl file in its place or an rtl address after it. */
    private function links(string $handle, array $item): string
    {
        $rtl = $this->text_direction === 'rtl' ? $this->get_data($handle, 'rtl') : false;
        if ($rtl === 'replace') {
            $suffix = (string) $this->get_data($handle, 'suffix');
            $src = str_replace("{$suffix}.css", "-rtl{$suffix}.css", (string) $item['src']);
            return $this->link($handle . '-rtl', $handle, $src, $item);
        }
        $html = $this->link($handle, $handle, (string) $item['src'], $item);
        return $html . (is_string($rtl) && $rtl !== '' ? $this->link($handle . '-rtl', $handle, $rtl, $item) : '');
    }

    private function link(string $id, string $handle, string $src, array $item): string
    {
        $href = $this->_css_href($src, $item['ver'], $handle);
        $media = (string) ($item['extra'] ?: 'all');
        $title = $this->get_data($handle, 'title');
        $tag = sprintf("<link rel='%s' id='%s-css'%s href='%s' media='%s' />\n", $this->get_data($handle, 'alt') ? 'alternate stylesheet' : 'stylesheet', esc_attr($id), $title ? " title='" . esc_attr($title) . "'" : '', $href, esc_attr($media));
        return apply_filters('style_loader_tag', $tag, $handle, $href, $media);
    }
}
