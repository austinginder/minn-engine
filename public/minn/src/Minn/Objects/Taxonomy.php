<?php

declare(strict_types=1);

namespace Minn\Objects;

/**
 * A registered taxonomy as the WordPress layer hands it to plugins
 * (WP_Taxonomy): its labels, capabilities and settings. The engine knows
 * these objects by this mark and makes them through Runtime\Handoff, so it
 * never names the WordPress layer's class.
 */
interface Taxonomy
{
}
