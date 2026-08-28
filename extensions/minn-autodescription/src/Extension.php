<?php

declare(strict_types=1);

namespace Minn\Ext\SeoFramework;

use Minn\Extension\Extension as MinnExtension;
use Minn\Extension\Seams;

/** Registers the title and the head block; the work is in Page. */
final class Extension implements MinnExtension
{
    public function register(Seams $minn): void
    {
        $page = new Page($minn);
        $minn->title(static fn (string $engineTitle): string => $page->title() ?? $engineTitle);
        $minn->head(static fn (): string => $page->head());
    }
}
