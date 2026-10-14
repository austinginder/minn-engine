<?php

declare(strict_types=1);

namespace Minn\Objects;

/**
 * A registered block type as the WordPress layer hands it to plugins
 * (WP_Block_Type): its attributes, supports, styles and render callback.
 * The engine knows these objects by this mark and finds them through
 * Runtime\Handoff, so it never names the WordPress layer's class.
 */
interface BlockType
{
}
