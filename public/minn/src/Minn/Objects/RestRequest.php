<?php

declare(strict_types=1);

namespace Minn\Objects;

/**
 * A REST request as the WordPress layer hands it to plugins
 * (WP_REST_Request): its route, method, parameters, headers and body. The
 * engine knows these objects by this mark and makes them through
 * Runtime\Handoff, so it never names the WordPress layer's class.
 */
interface RestRequest
{
}
