<?php

declare(strict_types=1);

namespace Minn\Objects;

/**
 * A registered script or style as the WordPress layer hands it to plugins
 * (_WP_Dependency): its handle, source, dependencies, version and extra
 * data. The engine knows these objects by this mark, so it never names the
 * WordPress layer's class.
 */
interface Dependency
{
}
