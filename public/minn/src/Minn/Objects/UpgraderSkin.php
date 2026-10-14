<?php

declare(strict_types=1);

namespace Minn\Objects;

/**
 * What an upgrader reports through (WP_Upgrader_Skin and its kinds): the
 * feedback and errors of an install or update. The engine knows these
 * objects by this mark and makes them through Runtime\Handoff, so it never
 * names the WordPress layer's class.
 */
interface UpgraderSkin
{
}
