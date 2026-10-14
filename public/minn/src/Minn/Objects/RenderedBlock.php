<?php

declare(strict_types=1);

namespace Minn\Objects;

/**
 * A block being rendered as the WordPress layer hands it to plugins
 * (WP_Block): its parsed block, attributes, context and inner blocks. The
 * engine knows these objects by this mark and makes them through
 * Runtime\Handoff, so it never names the WordPress layer's class.
 */
interface RenderedBlock
{
}
