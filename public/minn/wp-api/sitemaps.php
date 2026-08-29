<?php
// Sitemap URLs and the server object; the engine serves the XML itself.

use Minn\Runtime\Runtime;

function wp_sitemaps_get_server()
{
    return Runtime::current()->get('sitemaps_server') ?? (static function () {
        $server = new WP_Sitemaps();
        Runtime::current()->set('sitemaps_server', $server);
        return $server;
    })();
}

function wp_sitemaps_get_max_urls($object_type)
{
    return (int) apply_filters('wp_sitemaps_max_urls', 2000, $object_type);
}

function get_sitemap_url($name, $subtype_name = '', $page = 1)
{
    $sitemaps = wp_sitemaps_get_server();
    if (!$sitemaps) {
        return false;
    }
    if ($name === 'index') {
        return $sitemaps->index->get_index_url();
    }
    $provider = $sitemaps->registry->get_provider($name);
    if (!$provider) {
        return false;
    }
    if ($subtype_name && !in_array($subtype_name, array_keys($provider->get_object_subtypes()), true)) {
        return false;
    }
    $page = absint($page);
    if ($page === 0) {
        $page = 1;
    }
    return $provider->get_sitemap_url($subtype_name, $page);
}
