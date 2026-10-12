<?php

use Minn\Query\NetworkSql;

/**
 * A network, made from its site row (probe multisite-classes): its id as a
 * number, its main site through blog_id (as text) and site_id (as a
 * number), its name from the site_name network option (the domain,
 * capitalised, when there is none) and its cookie domain (the domain
 * without www.) when the row gives neither.
 */
#[AllowDynamicProperties]
class WP_Network
{
    private $id;
    public $domain = '';
    public $path = '';
    private $blog_id = '0';
    public $cookie_domain = '';
    public $site_name = '';

    /** The network by its id, from the object cache (networks) or its row; false for none. */
    public static function get_instance($network_id)
    {
        global $wpdb;
        $network_id = (int) $network_id;
        if (!$network_id) {
            return false;
        }
        $network = wp_cache_get($network_id, 'networks');
        if ($network === false) {
            $network = (new NetworkSql($wpdb))->rowById((string) $wpdb->site, 'id', $network_id);
            if ($network === null) {
                return false;
            }
            wp_cache_add($network_id, $network, 'networks');
        }
        return new WP_Network($network);
    }

    public function __construct($network)
    {
        foreach (get_object_vars($network) as $key => $value) {
            $this->$key = $value;
        }
        $this->_set_site_name();
        $this->_set_cookie_domain();
    }

    public function __get($key)
    {
        return match ($key) {
            'id' => (int) $this->id,
            'blog_id' => (string) $this->get_main_site_id(),
            'site_id' => $this->get_main_site_id(),
            default => null,
        };
    }

    public function __isset($key)
    {
        return in_array($key, ['id', 'blog_id', 'site_id'], true);
    }

    public function __set($key, $value)
    {
        match ($key) {
            'id' => $this->id = (int) $value,
            'blog_id', 'site_id' => $this->blog_id = (string) $value,
            default => $this->$key = $value,
        };
    }

    /** The network's main site: what pre_get_main_site_id answers, else the one its row names. */
    private function get_main_site_id()
    {
        $pre = apply_filters('pre_get_main_site_id', null, $this);
        return $pre !== null && (int) $pre > 0 ? (int) $pre : (int) $this->blog_id;
    }

    private function _set_site_name()
    {
        if (!empty($this->site_name)) {
            return;
        }
        $this->site_name = get_network_option($this->id, 'site_name', ucfirst((string) $this->domain));
    }

    private function _set_cookie_domain()
    {
        if (!empty($this->cookie_domain)) {
            return;
        }
        $this->cookie_domain = str_starts_with((string) $this->domain, 'www.') ? substr((string) $this->domain, 4) : (string) $this->domain;
    }

    /**
     * The network for a domain and path: the paths tried run from the
     * path's segments (as many as asked, network_by_path_segments_count)
     * back to the root, and pre_get_network_by_path may answer; one site has
     * no networks to look through.
     */
    public static function get_by_path($domain = '', $path = '', $segments = null)
    {
        $segments = apply_filters('network_by_path_segments_count', $segments, $domain, $path);
        $parts = array_values(array_filter(explode('/', trim((string) $path, '/')), 'strlen'));
        if ($segments !== null && count($parts) > (int) $segments) {
            $parts = array_slice($parts, 0, (int) $segments);
        }
        $paths = [];
        for (; $parts !== []; array_pop($parts)) {
            $paths[] = '/' . implode('/', $parts) . '/';
        }
        $paths[] = '/';
        $pre = apply_filters('pre_get_network_by_path', null, $domain, $path, $segments, $paths);
        return $pre !== null ? $pre : false;
    }
}
