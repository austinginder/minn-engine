<?php

declare(strict_types=1);

namespace Minn\Ext\TestRecords;

use Minn\Extension\Extension as MinnExtension;
use Minn\Extension\Seams;

/** A table of its own, REST routes over it, and a page route, so the suite can see the seams work. */
final class Extension implements MinnExtension
{
    public function register(Seams $minn): void
    {
        $minn->rest(new Notes($minn), new Players($minn));
        $minn->routes(new PlayerPages($minn));
    }
}
