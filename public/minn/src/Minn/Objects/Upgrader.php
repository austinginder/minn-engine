<?php

declare(strict_types=1);

namespace Minn\Objects;

/**
 * An upgrader as the WordPress layer hands it to plugins (WP_Upgrader and
 * the plugin and theme upgraders): it fetches, unpacks and installs a
 * package. The engine knows these objects by this mark and makes them
 * through Runtime\Handoff, so it never names the WordPress layer's class.
 */
interface Upgrader
{
}
