<?php
/**
 * The WordPress classes around the feed object: the transient cache
 * fetch_feed() stores parsed feeds in (site transients feed_{md5(url)} and
 * feed_mod_{md5(url)}), the HTTP file and the kses sanitizer it plugs in,
 * and the pre-namespace class names (SimplePie, SimplePie_Item, ...) as
 * aliases.
 */

class WP_Feed_Cache_Transient implements SimplePie\Cache\Base
{
    public $name;
    public $mod_name;
    public $lifetime = 43200;

    public function __construct($location, $name, $type)
    {
        $this->name = 'feed_' . $name;
        $this->mod_name = 'feed_mod_' . $name;
        $lifetime = $this->lifetime;
        $this->lifetime = apply_filters('wp_feed_cache_transient_lifetime', $lifetime, $name);
    }

    public function save($data)
    {
        if ($data instanceof SimplePie\SimplePie) {
            $data = $data->data;
        }
        set_site_transient($this->name, $data, $this->lifetime);
        set_site_transient($this->mod_name, time(), $this->lifetime);
        return true;
    }

    public function load()
    {
        return get_site_transient($this->name);
    }

    public function mtime()
    {
        return get_site_transient($this->mod_name);
    }

    public function touch()
    {
        return set_site_transient($this->mod_name, time(), $this->lifetime);
    }

    public function unlink()
    {
        delete_site_transient($this->name);
        delete_site_transient($this->mod_name);
        return true;
    }
}

class WP_SimplePie_File extends SimplePie\File
{
}

class WP_SimplePie_Sanitize_KSES extends SimplePie\Sanitize
{
}

foreach (['SimplePie', 'Item', 'Author', 'Category', 'Enclosure', 'Source', 'Caption', 'Credit', 'Copyright', 'Rating', 'Restriction', 'File', 'Sanitize', 'Cache', 'Locator', 'Parser', 'Registry', 'Misc'] as $minnSimplePieClass) {
    $minnLegacyName = $minnSimplePieClass === 'SimplePie' ? 'SimplePie' : 'SimplePie_' . $minnSimplePieClass;
    if (!class_exists($minnLegacyName, false)) {
        class_alias('SimplePie\\' . $minnSimplePieClass, $minnLegacyName);
    }
}
if (!class_exists('SimplePie_Content_Type_Sniffer', false)) {
    class_alias('SimplePie\\Content\\Type\\Sniffer', 'SimplePie_Content_Type_Sniffer');
}
unset($minnSimplePieClass, $minnLegacyName);
