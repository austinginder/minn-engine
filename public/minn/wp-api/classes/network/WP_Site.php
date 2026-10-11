<?php

use Minn\Query\NetworkSql;

/**
 * A site of a network, made from its blogs row (probe multisite-classes):
 * its id and network as numbers through id and network_id, and its name,
 * address and post count read once the network has loaded (ms_loaded);
 * before then, as on one site, they read as nothing.
 */
#[AllowDynamicProperties]
final class WP_Site
{
    public $blog_id;
    public $domain = '';
    public $path = '';
    public $site_id = '0';
    public $registered = '0000-00-00 00:00:00';
    public $last_updated = '0000-00-00 00:00:00';
    public $public = '1';
    public $archived = '0';
    public $mature = '0';
    public $spam = '0';
    public $deleted = '0';
    public $lang_id = '0';

    /** The site by its id, from the object cache (sites) or its row; false for none. */
    public static function get_instance($site_id)
    {
        global $wpdb;
        $site_id = (int) $site_id;
        if (!$site_id) {
            return false;
        }
        $site = wp_cache_get($site_id, 'sites');
        if ($site === false) {
            $site = (new NetworkSql($wpdb))->rowById((string) $wpdb->blogs, 'blog_id', $site_id);
            if ($site === null) {
                return false;
            }
            wp_cache_add($site_id, $site, 'sites');
        }
        return new WP_Site($site);
    }

    public function __construct($site)
    {
        foreach (get_object_vars($site) as $key => $value) {
            $this->$key = $value;
        }
    }

    public function to_array()
    {
        return get_object_vars($this);
    }

    public function __get($key)
    {
        return match ($key) {
            'id' => (int) $this->blog_id,
            'network_id' => (int) $this->site_id,
            'blogname', 'siteurl', 'post_count', 'home' => did_action('ms_loaded') ? ($this->get_details()->$key ?? null) : null,
            default => null,
        };
    }

    public function __isset($key)
    {
        return match ($key) {
            'id', 'network_id' => true,
            'blogname', 'siteurl', 'post_count', 'home' => (bool) did_action('ms_loaded'),
            default => false,
        };
    }

    public function __set($key, $value)
    {
        match ($key) {
            'id' => $this->blog_id = (string) $value,
            'network_id' => $this->site_id = (string) $value,
            default => $this->$key = $value,
        };
    }

    /** The site's name, addresses and post count: from their cache (site-details), else read on the site itself, through site_details. */
    private function get_details()
    {
        $details = wp_cache_get($this->blog_id, 'site-details');
        if ($details === false && function_exists('switch_to_blog')) {
            switch_to_blog($this->blog_id);
            $details = (object) ['blogname' => get_option('blogname'), 'siteurl' => get_option('siteurl'), 'post_count' => get_option('post_count'), 'home' => get_option('home')];
            restore_current_blog();
            wp_cache_set($this->blog_id, $details, 'site-details');
        }
        return apply_filters('site_details', $details === false ? new stdClass() : $details);
    }
}
