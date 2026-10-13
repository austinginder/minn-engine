<?php

declare(strict_types=1);

namespace Minn\Objects;

/**
 * A post as the WordPress layer hands it to plugins (WP_Post). The engine
 * knows these objects by this mark and makes them through Runtime\Handoff,
 * so it never names the WordPress layer's class.
 */
interface Post
{
}
