<?php

declare(strict_types=1);

namespace Minn\Http;

use RuntimeException;

/**
 * A handler declining a request its pattern matched: the router swallows
 * it and goes on to the next route, so a catch-all pattern can leave a
 * path it does not own to whatever registers after it (a plugin's route
 * under the same namespace, say) instead of answering "no route" itself.
 */
final class RouteMiss extends RuntimeException
{
}
