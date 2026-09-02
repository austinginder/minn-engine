<?php

declare(strict_types=1);

namespace Minn\Extension;

use Minn\Runtime\Runtime;

/**
 * Where the engine looks for the seams an extension registered. The
 * request's runtime holds them, so nothing here keeps state of its own
 * and a request cannot inherit the extensions of the one before it.
 * Before the front has loaded any (REST and the command line never do)
 * there are none.
 */
final class Extensions
{
    /** Installs the seams for this request. */
    public static function set(Seams $seams): void
    {
        Runtime::current()->useSeams(new SeamRunner($seams, $seams->registrations()));
    }

    /** The engine side of the seams, if any. */
    public static function runner(): ?SeamRunner
    {
        return Runtime::booted() ? Runtime::current()->seams() : null;
    }
}
