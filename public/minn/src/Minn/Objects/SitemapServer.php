<?php

declare(strict_types=1);

namespace Minn\Objects;

/**
 * The sitemaps as the WordPress layer hands them to plugins (WP_Sitemaps):
 * the providers registered and the index and stylesheets they serve. The
 * engine knows them by this mark, so it never names the WordPress layer's
 * class.
 */
interface SitemapServer
{
}
