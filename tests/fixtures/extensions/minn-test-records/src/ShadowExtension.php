<?php

declare(strict_types=1);

namespace Minn\Ext\TestRecords;

use Minn\Extension\Extension as MinnExtension;
use Minn\Extension\Seams;

/** Asks for REST routes in a namespace the engine keeps, which skips the extension whole. */
final class ShadowExtension implements MinnExtension
{
    public function register(Seams $minn): void
    {
        $minn->rest(new Notes($minn), new Shadow());
    }
}
