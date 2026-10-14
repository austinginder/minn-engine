<?php

declare(strict_types=1);

namespace Minn\Objects;

/**
 * The toolbar as the WordPress layer hands it to plugins (WP_Admin_Bar):
 * the nodes and groups a menu callback adds. The engine knows it by this
 * mark, so it never names the WordPress layer's class.
 */
interface Toolbar
{
}
