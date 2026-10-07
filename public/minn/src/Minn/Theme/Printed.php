<?php

declare(strict_types=1);

namespace Minn\Theme;

use Exception;

/**
 * Raised once a WordPress handler has printed a whole response (a sitemap,
 * a stylesheet) or sent a redirect, where the reference exits: the request
 * ends there and the engine answers with what was printed.
 */
final class Printed extends Exception
{
}
