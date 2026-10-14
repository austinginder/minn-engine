<?php

declare(strict_types=1);

namespace Minn\Objects;

/**
 * A REST response as the WordPress layer hands it to plugins
 * (WP_REST_Response): its data, status, headers and links. The engine knows
 * these objects by this mark and makes them through Runtime\Handoff, so it
 * never names the WordPress layer's class.
 */
interface RestResponse
{
}
