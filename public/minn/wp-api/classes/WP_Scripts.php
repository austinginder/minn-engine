<?php

use Minn\Runtime\Assets;

/** A read view of the asset registry in the shape plugins inspect: $wp_scripts->registered[$handle]->src and friends. */
#[AllowDynamicProperties]
class WP_Dependencies
{
    public $registered = [];
    public $queue = [];
    public $to_do = [];
    public $done = [];
    public $args = [];
    public $groups = [];
    public $group = 0;
    protected $assets;

    public function __construct(?Assets $assets = null)
    {
        $this->assets = $assets;
        if ($assets === null) {
            return;
        }
        $assets->watch(function (): void {
            $this->sync();
        });
    }

    /** Refreshes the public arrays from the registry; the registry calls this after every change. */
    public function sync(): void
    {
        if ($this->assets === null) {
            return;
        }
        $this->registered = [];
        foreach ($this->assets->items() as $handle => $item) {
            $dep = new _WP_Dependency($handle, $item['src'], $item['deps'], $item['ver'], $item['extra']);
            $dep->extra = ['data' => implode("\n", $item['localized']), 'before' => $item['inline']['before'], 'after' => $item['inline']['after']] + $item['data'];
            if (isset($item['translations'])) {
                $dep->set_translations($item['translations']['domain'], $item['translations']['path']);
            }
            $this->registered[$handle] = $dep;
        }
        $this->queue = $this->assets->queue();
    }

    /** Hands a queue a plugin edited in place back to the registry before printing. */
    public function push(): void
    {
        if ($this->assets !== null && $this->queue !== $this->assets->queue()) {
            $this->assets->setQueue((array) $this->queue);
        }
    }

    public function add($handle, $src, $deps = [], $ver = false, $args = null)
    {
        return $this->assets?->register((string) $handle, $src === false ? false : (string) $src, (array) $deps, $ver, $args) ?? false;
    }

    public function add_data($handle, $key, $value)
    {
        return $this->assets?->addData((string) $handle, (string) $key, $value) ?? false;
    }

    public function get_data($handle, $key)
    {
        return $this->assets?->data((string) $handle, (string) $key) ?? false;
    }

    public function remove($handles)
    {
        foreach ((array) $handles as $handle) {
            $this->assets?->deregister((string) $handle);
        }
    }

    public function enqueue($handles)
    {
        foreach ((array) $handles as $handle) {
            $this->assets?->enqueue((string) $handle);
        }
    }

    public function dequeue($handles)
    {
        foreach ((array) $handles as $handle) {
            $this->assets?->dequeue((string) $handle);
        }
    }

    public function query($handle, $status = 'registered')
    {
        return match ($status) {
            'registered', 'scripts' => $this->registered[$handle] ?? false,
            'enqueued', 'queue' => $this->assets?->enqueued((string) $handle) ?? false,
            'done' => $this->assets?->done((string) $handle) ?? false,
            default => false,
        };
    }
}

class WP_Scripts extends WP_Dependencies
{
    public $base_url = '';
    public $content_url = '';
    public $default_version = '';
    public $in_footer = [];

    public function __construct(?Assets $assets = null)
    {
        parent::__construct($assets);
        $this->base_url = site_url();
        $this->content_url = defined('WP_CONTENT_URL') ? WP_CONTENT_URL : '';
        $this->default_version = $GLOBALS['wp_version'] ?? '';
    }

    public function localize($handle, $object_name, $l10n)
    {
        return $this->assets?->localize((string) $handle, (string) $object_name, (array) $l10n) ?? false;
    }

    public function add_inline_script($handle, $data, $position = 'after')
    {
        return $this->assets?->addInline((string) $handle, (string) $data, (string) $position) ?? false;
    }

    public function get_inline_script_data($handle, $position = 'after')
    {
        $item = $this->assets?->item((string) $handle);
        return $item === null ? '' : implode("\n", $item['inline'][$position === 'before' ? 'before' : 'after']);
    }

    public function set_translations($handle, $domain = 'default', $path = '')
    {
        return $this->assets?->setTranslations((string) $handle, (string) $domain, (string) $path) ?? false;
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
}

class WP_Styles extends WP_Dependencies
{
    public $base_url = '';
    public $content_url = '';
    public $default_version = '';
    public $text_direction = 'ltr';

    public function __construct(?Assets $assets = null)
    {
        parent::__construct($assets);
        $this->base_url = site_url();
        $this->content_url = defined('WP_CONTENT_URL') ? WP_CONTENT_URL : '';
        $this->default_version = $GLOBALS['wp_version'] ?? '';
    }

    public function add_inline_style($handle, $code)
    {
        return $this->assets?->addInline((string) $handle, (string) $code, 'after') ?? false;
    }
}

#[AllowDynamicProperties]
class _WP_Dependency
{
    public $handle;
    public $src;
    public $deps = [];
    public $ver = false;
    public $args = null;
    public $extra = [];
    public $textdomain;
    public $translations_path;

    public function __construct(...$args)
    {
        [$this->handle, $this->src, $this->deps, $this->ver, $this->args] = $args + [null, null, [], false, null];
        if (!is_array($this->deps)) {
            $this->deps = [];
        }
    }

    public function add_data($name, $data)
    {
        if (!is_scalar($name)) {
            return false;
        }
        $this->extra[$name] = $data;
        return true;
    }

    public function set_translations($domain, $path = '')
    {
        $this->textdomain = $domain;
        $this->translations_path = $path;
        return true;
    }
}
