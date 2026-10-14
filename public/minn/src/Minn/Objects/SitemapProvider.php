<?php

declare(strict_types=1);

namespace Minn\Objects;

/**
 * A sitemap provider as the WordPress layer hands it to plugins
 * (WP_Sitemaps_Provider and its kinds): the object subtypes it lists and
 * their URLs. The engine knows these objects by this mark, so it never
 * names the WordPress layer's class.
 */
interface SitemapProvider
{
}
