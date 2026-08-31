<?php

declare(strict_types=1);

namespace Minn\Login;

use Exception;

/**
 * Thrown by the wp-login.php shape file when plugin code require's it
 * mid-request (a hide-login plugin serving its own sign-in URL, the
 * perfmatters pattern). Engine::respond() catches it and answers the
 * current request with the sign-in surface.
 */
final class ServeLogin extends Exception
{
}
