<?php
// The sitemaps server (built on init), its providers, and sitemap URLs; requests are answered at template_redirect.

use Minn\Runtime\Runtime;

function wp_sitemaps_get_server()
{
    $runtime = Runtime::current();
    $server = $runtime->get('sitemaps_server');
    if ($server instanceof WP_Sitemaps) {
        return $server;
    }
    // Held before it starts, so a plugin asking for it while it does gets this one.
    $server = new WP_Sitemaps();
    $runtime->set('sitemaps_server', $server);
    $GLOBALS['wp_sitemaps'] = $server;
    $server->init();
    do_action('wp_sitemaps_init', $server);
    return $server;
}

function wp_get_sitemap_providers()
{
    return wp_sitemaps_get_server()->registry->get_providers();
}

function wp_register_sitemap_provider($name, WP_Sitemaps_Provider $provider)
{
    return wp_sitemaps_get_server()->registry->add_provider($name, $provider);
}

/** @internal the reference's canonical step for a sitemap address (SitemapRequest::canonical()) */
function _minn_sitemap_canonical()
{
    Minn\Front\SitemapRequest::canonical();
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
