<?php

declare(strict_types=1);

namespace Minn\Ops;

/**
 * The Minn update service, https://updates.minn.run: the one place the
 * engine asks about anything it does not have yet. Minn's own releases and
 * changelogs come from here (read from GitHub by the service), and the
 * plugin and theme directory will too, so a site running Minn talks to Minn
 * and never to wordpress.org. Every address the engine fetches from the
 * service is built here, and every download from it is pinned to ORIGIN.
 */
final class Directory
{
    public const ORIGIN = 'https://updates.minn.run/';
    public const BASE = self::ORIGIN . 'v1/';

    /** An address on the service: a path under /v1/, such as "minn/releases/latest". */
    public static function url(string $path): string
    {
        return self::BASE . ltrim($path, '/');
    }
}
