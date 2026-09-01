<?php

declare(strict_types=1);

namespace Minn\Extension;

/**
 * The request's seams once the loader has filled them, and the runner the
 * engine calls them through; nothing before that (REST and CLI).
 */
final class Extensions
{
    private static ?Seams $seams = null;
    private static ?SeamRunner $runner = null;

    /** Installs the seams for this request. */
    public static function set(Seams $seams): void
    {
        self::$seams = $seams;
        self::$runner = new SeamRunner($seams, $seams->registrations());
    }

    /** The registered seams, if any. */
    public static function seams(): ?Seams
    {
        return self::$seams;
    }

    /** The engine side of the seams, if any. */
    public static function runner(): ?SeamRunner
    {
        return self::$runner;
    }
}
