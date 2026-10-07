<?php

declare(strict_types=1);

namespace Minn\Theme;

use Exception;

/**
 * Raised once a reader's copy of a feed has been found current and the
 * headers that say so are sent: the reference stops the request there, so
 * the engine answers with nothing more.
 */
final class NotModified extends Exception
{
}
