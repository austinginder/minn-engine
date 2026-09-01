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

    public static function set(Seams $seams): void
    {
        self::$seams = $seams;
        self::$runner = new SeamRunner($seams, $seams->registrations());
    }

    public static function seams(): ?Seams
    {
        return self::$seams;
    }

    public static function runner(): ?SeamRunner
    {
        return self::$runner;
    }
}
