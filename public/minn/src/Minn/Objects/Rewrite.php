<?php

declare(strict_types=1);

namespace Minn\Objects;

/**
 * The rewrite object as the WordPress layer hands it to plugins
 * (WP_Rewrite): the permalink structures, the extra rules and endpoints
 * plugins add, and the rules built from them. The engine knows it by this
 * mark, so it never names the WordPress layer's class.
 */
interface Rewrite
{
}
