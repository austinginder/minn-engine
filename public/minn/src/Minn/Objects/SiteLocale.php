<?php

declare(strict_types=1);

namespace Minn\Objects;

/**
 * The site's locale as the WordPress layer hands it to plugins
 * (WP_Locale): the names of weekdays and months, the number format and the
 * text direction. The engine knows it by this mark and makes it through
 * Runtime\Handoff, so it never names the WordPress layer's class.
 */
interface SiteLocale
{
}
