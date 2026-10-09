<?php

use Minn\Runtime\Assets;
use Minn\Runtime\ScriptLoading;

/**
 * The scripts registry (probe dependencies-classes). Printing goes through
 * it: each script prints its localized data, translations, inline code
 * before, the tag and inline code after. A script in the footer group is
 * held back from the head pass (in_footer) unless something in the head
 * depends on it; so is a delayed script with inline code after it that
 * nothing enqueued depends on. Its tag carries the strategy and fetch priority it may
 * load with (Runtime\ScriptLoading), the one it asked for kept in
 * data-wp-strategy and data-wp-fetchpriority. A version left out or empty
 * is the WordPress version; null is none.
 */
class WP_Scripts extends WP_Dependencies
{
    public $base_url;
    public $content_url;
    public $default_version;
    public $in_footer = [];
    public $concat = '';
    public $concat_version = '';
    public $do_concat = false;
    public $print_html = '';
    public $print_code = '';
    public $ext_handles = '';
    public $ext_version = '';
    public $default_dirs;

    public function __construct(?Assets $assets = null)
    {
        parent::__construct($assets ?? new Assets('script'));
        $this->init();
    }

    /** The registry's URLs and default directories, then wp_default_scripts, its edits handed back. */
    public function init()
    {
        $this->base_url = (string) get_option('siteurl');
        $this->content_url = defined('WP_CONTENT_URL') ? WP_CONTENT_URL : '';
        // The page's registries are made as the facade loads, before $wp_version is set.
        $this->default_version = $GLOBALS['wp_version'] ?? (Minn\Runtime\Runtime::booted() ? Minn\Runtime\Runtime::current()->version : '');
        $this->default_dirs = ['/wp-admin/js/', '/wp-includes/js/'];
        $self = $this;
        do_action_ref_array('wp_default_scripts', [&$self]);
        $this->push();
    }

    public function print_scripts($handles = false, $group = false)
    {
        return $this->do_items($handles, $group);
    }

    public function print_scripts_l10n($handle, $display = true)
    {
        _deprecated_function(__FUNCTION__, '3.3.0', 'WP_Scripts::print_extra_script()');
        return $this->print_extra_script($handle, $display);
    }

    /** A script's localized data: printed as its own tag (true), or returned as code; nothing when it has none. */
    public function print_extra_script($handle, $display = true)
    {
        $item = $this->assets->item((string) $handle);
        if ($item === null || $item['localized'] === []) {
            return null;
        }
        $output = implode("\n", $item['localized']) . _minn_source_url($handle . '-js-extra');
        if (!$display) {
            return $output;
        }
        echo wp_get_inline_script_tag($output, ['id' => $handle . '-js-extra']);
        return true;
    }

    public function do_item($handle, $group = false)
    {
        if (!parent::do_item($handle)) {
            return false;
        }
        if ($group === 0 && (($this->groups[$handle] ?? 0) > 0 || (new ScriptLoading($this->assets))->heldForFooter((string) $handle))) {
            $this->in_footer[] = $handle;
            return false;
        }
        echo $this->markup((string) $handle);
        return true;
    }

    public function add_inline_script($handle, $data, $position = 'after')
    {
        return $this->assets->addInline((string) $handle, (string) $data, (string) $position);
    }

    public function print_inline_script($handle, $position = 'after', $display = true)
    {
        _deprecated_function(__METHOD__, '6.3.0', 'WP_Scripts::get_inline_script_data() or WP_Scripts::get_inline_script_tag()');
        $output = $this->get_inline_script_data($handle, $position);
        if ($display && $output !== '') {
            echo $this->get_inline_script_tag($handle, $position);
        }
        return $output;
    }

    /** A script's inline code at one position, each piece trimmed and a line apart, with its sourceURL trailer; '' when it has none. */
    public function get_inline_script_data($handle, $position = 'after')
    {
        $position = $position === 'before' ? 'before' : 'after';
        $code = $this->assets->item((string) $handle)['inline'][$position] ?? [];
        if ($code === []) {
            return '';
        }
        return implode("\n", array_map(static fn ($piece): string => trim((string) $piece, "\n\r "), $code)) . _minn_source_url("{$handle}-js-{$position}");
    }

    public function get_inline_script_tag($handle, $position = 'after')
    {
        $data = $this->get_inline_script_data($handle, $position);
        return $data === '' ? '' : wp_get_inline_script_tag($data, ['id' => "{$handle}-js-" . ($position === 'before' ? 'before' : 'after')]);
    }

    public function localize($handle, $object_name, $l10n)
    {
        return $this->assets->localize((string) $handle, (string) $object_name, (array) $l10n);
    }

    /** A script's group: its own (the footer is 1), or the group of what depends on it when that is lower. */
    public function set_group($handle, $recursion, $group = false)
    {
        $own = (int) $this->get_data($handle, 'group');
        return parent::set_group($handle, $recursion, $group !== false && $own > (int) $group ? (int) $group : $own);
    }

    public function set_translations($handle, $domain = 'default', $path = '')
    {
        return $this->assets->setTranslations((string) $handle, (string) $domain, (string) $path);
    }

    /** The translations block printed before a script, or false when it has none. */
    public function print_translations($handle, $display = true)
    {
        $block = _minn_script_translations_block((string) $handle);
        if ($block === null) {
            return false;
        }
        if ($display) {
            echo $block;
        }
        return $block;
    }

    public function all_deps($handles, $recursion = false, $group = false)
    {
        $result = parent::all_deps($handles, $recursion, $group);
        if (!$recursion) {
            $this->to_do = apply_filters('print_scripts_array', $this->to_do);
        }
        return $result;
    }

    public function do_head_items()
    {
        $this->do_items(false, 0);
        return $this->done;
    }

    public function do_footer_items()
    {
        $this->do_items(false, 1);
        return $this->done;
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

    /** A strategy or fetch priority is refused with a notice unless the reference accepts it; an empty priority means auto. */
    public function add_data($handle, $key, $value)
    {
        $refusal = ScriptLoading::refusal((string) $handle, (string) $key, $value);
        if ($refusal !== null) {
            _doing_it_wrong(__METHOD__, $refusal[0], $refusal[1]);
            return false;
        }
        return parent::add_data($handle, $key, $key === 'fetchpriority' && empty($value) ? 'auto' : $value);
    }

    public function reset()
    {
        $this->do_concat = false;
        $this->print_code = '';
        $this->concat = '';
        $this->concat_version = '';
        $this->print_html = '';
        $this->ext_version = '';
        $this->ext_handles = '';
    }

    protected function get_dependency_warning_message($handle, $missing_dependency_handles)
    {
        return sprintf('The script with the handle "%1$s" was enqueued with dependencies that are not registered: %2$s.', $handle, implode(', ', $missing_dependency_handles));
    }

    /** One script's markup: localized data, translations, inline code before, the tag (when it has a source), inline code after. */
    private function markup(string $handle): string
    {
        $item = $this->assets->item($handle);
        $before = $this->get_inline_script_tag($handle, 'before');
        $after = $this->get_inline_script_tag($handle, 'after');
        $translations = (string) $this->print_translations($handle, false);
        $extra = $this->print_extra_script($handle, false);
        $html = ($extra === null ? '' : wp_get_inline_script_tag($extra, ['id' => $handle . '-js-extra'])) . $translations . $before;
        if ($item['src'] !== false && $item['src'] !== '') {
            $src = $this->source((string) $item['src'], $item['ver'], $handle);
            $html .= apply_filters('script_loader_tag', wp_get_script_tag($this->tag_attributes($handle, $src)), $handle, $src);
        }
        return $html . $after;
    }

    /** The address a script prints: a relative source under base_url, the version (and any "handle?args") as ver, through script_loader_src. */
    private function source(string $src, $ver, string $handle): string
    {
        if (!preg_match('#^(https?:)?//#', $src) && !($this->content_url && str_starts_with($src, $this->content_url))) {
            $src = $this->base_url . $src;
        }
        $ver = $ver === false || $ver === '' ? $this->default_version : $ver;
        if (isset($this->args[$handle])) {
            $ver = $ver === null || $ver === true ? $this->args[$handle] : $ver . '&' . $this->args[$handle];
        }
        if ($ver !== null && $ver !== true) {
            $src = add_query_arg('ver', (string) $ver, $src);
        }
        return esc_url(apply_filters('script_loader_src', $src, $handle));
    }

    /** The tag's attributes: source and id, then the strategy and fetch priority it loads with. */
    private function tag_attributes(string $handle, string $src): array
    {
        $loading = new ScriptLoading($this->assets);
        $attributes = ['src' => $src, 'id' => $handle . '-js'];
        $asked = (string) $this->get_data($handle, 'strategy');
        if ($asked === 'defer' || $asked === 'async') {
            $strategy = $loading->strategy($handle);
            $attributes += ($strategy === '' ? [] : [$strategy => true]) + ['data-wp-strategy' => $asked];
        }
        [$own, $priority] = $loading->priority($handle);
        $attributes += $priority === 'auto' ? [] : ['fetchpriority' => $priority];
        return $attributes + ($priority === $own ? [] : ['data-wp-fetchpriority' => $own]);
    }
}
