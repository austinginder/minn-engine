<?php

declare(strict_types=1);

namespace Minn\Support;

/** What the SERVER_SOFTWARE string says about the web server in front of the site. */
final class WebServer
{
    /** Whether the server speaks Apache's module and .htaccess conventions: Apache itself, or LiteSpeed. */
    public static function isApache(string $software): bool
    {
        return str_contains($software, 'Apache') || str_contains($software, 'LiteSpeed');
    }
}
