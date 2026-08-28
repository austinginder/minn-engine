<?php

declare(strict_types=1);

namespace Minn\Extension;

/** The request's seams, once the loader has filled them; empty seams before that (REST and CLI). */
final class Extensions
{
    private static ?Seams $seams = null;

    public static function set(Seams $seams): void
    {
        self::$seams = $seams;
    }

    public static function seams(): ?Seams
    {
        return self::$seams;
    }
}
