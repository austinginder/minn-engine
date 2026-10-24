<?php

declare(strict_types=1);

namespace Minn\Ext\TestTheme;

use Minn\Extension\Extension as MinnExtension;
use Minn\Extension\Seams;

/** A theme that is an extension: active only while it is the theme. */
final class Extension implements MinnExtension
{
    public function register(Seams $minn): void
    {
        $minn->head(static fn (): string => '<meta name="seam-theme" content="minn-test-theme">' . "\n");
    }
}
