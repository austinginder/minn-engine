<?php

declare(strict_types=1);

namespace Minn\Ext\MetaSlider;

use Minn\Extension\Extension as MinnExtension;
use Minn\Extension\Seams;

/** The body class the plugin adds to every page; nothing else of it is used by this site's content. */
final class Extension implements MinnExtension
{
    public function register(Seams $minn): void
    {
        $minn->bodyClass('metaslider-plugin');
    }
}
