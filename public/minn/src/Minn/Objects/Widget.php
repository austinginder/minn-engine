<?php

declare(strict_types=1);

namespace Minn\Objects;

/**
 * A widget as the WordPress layer hands it to plugins (WP_Widget): its id
 * base, name, options and the form and update methods a widget class
 * writes. The engine knows these objects by this mark, so it never names
 * the WordPress layer's class.
 */
interface Widget
{
}
