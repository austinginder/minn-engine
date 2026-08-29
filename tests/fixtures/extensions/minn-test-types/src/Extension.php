<?php

declare(strict_types=1);

namespace Minn\Ext\TestTypes;

use Minn\Extension\Extension as MinnExtension;
use Minn\Extension\Seams;

/** Declares a CPT in minn.json; no front-end seams. */
final class Extension implements MinnExtension
{
    public function register(Seams $minn): void
    {
    }
}
