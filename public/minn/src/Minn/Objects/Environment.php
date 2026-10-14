<?php

declare(strict_types=1);

namespace Minn\Objects;

/**
 * The request's environment as the WordPress layer hands it to plugins
 * (WP, the $wp global): the query variables parsed from the request and
 * the headers sent. The engine knows it by this mark, so it never names
 * the WordPress layer's class.
 */
interface Environment
{
}
