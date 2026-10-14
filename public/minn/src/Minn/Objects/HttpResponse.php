<?php

declare(strict_types=1);

namespace Minn\Objects;

/**
 * An HTTP response as the WordPress layer hands it to plugins
 * (WP_HTTP_Response): its data, status and headers. The engine knows these
 * objects by this mark and makes them through Runtime\Handoff, so it never
 * names the WordPress layer's class.
 */
interface HttpResponse
{
}
