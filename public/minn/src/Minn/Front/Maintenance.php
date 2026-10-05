<?php

declare(strict_types=1);

namespace Minn\Front;

use Minn\Http\Failure;
use Minn\Http\Response;
use Minn\Runtime\EarlyFilters;

/**
 * Maintenance mode, as hosting tools and updaters switch it on: a
 * .maintenance file in the webroot whose $upgrading is less than ten minutes
 * old. While it holds, every request gets the site's own maintenance.php
 * (which prints its page and stops) or the 503 page; an older file is ignored.
 */
final class Maintenance
{
    private const WINDOW = 600;

    /** The answer while maintenance holds, or null when the site is open. */
    public static function answer(string $abspath, string $contentDir, int $now): ?Response
    {
        $file = rtrim($abspath, '/') . '/.maintenance';
        if (!is_file($file)) {
            return null;
        }
        $upgrading = (static function (string $path): int {
            $upgrading = 0;
            include $path;
            return (int) $upgrading;
        })($file);
        if ($now - $upgrading >= self::WINDOW || !EarlyFilters::apply('enable_maintenance_mode', true, $upgrading)) {
            return null;
        }
        $page = rtrim($contentDir, '/') . '/maintenance.php';
        if (is_file($page)) {
            ob_start();
            require $page;
            return Response::html((string) ob_get_clean(), 200);
        }
        return Failure::maintenance();
    }
}
