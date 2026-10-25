<?php

declare(strict_types=1);

namespace Minn\Ext\TestRecords;

use Minn\Extension\Extension as MinnExtension;
use Minn\Extension\Seams;

/** A table of its own and REST routes over it, so the suite can see both seams work. */
final class Extension implements MinnExtension
{
    public function register(Seams $minn): void
    {
        $minn->rest(new Notes($minn));
    }
}
