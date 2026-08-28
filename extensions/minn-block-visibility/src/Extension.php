<?php

declare(strict_types=1);

namespace Minn\Ext\BlockVisibility;

use Minn\Blocks\Block;
use Minn\Extension\Extension as MinnExtension;
use Minn\Extension\Seams;
use Minn\Support\Html;

final class Extension implements MinnExtension
{
    public function register(Seams $minn): void
    {
        $device = Device::fromUserAgent((string) ($minn->request->header('user-agent') ?? ''));
        $rules = new Rules($device, $minn->reader, $minn->site);
        $minn->gateBlocks(static fn (Block $block): ?bool => $rules->visible($block->attrs) ? null : false);
        $minn->filterBlocks(static function (Block $block, string $html) use ($rules): string {
            $classes = $rules->screenClasses($block->attrs);
            return $classes === [] ? $html : Html::addClasses($html, $classes);
        });
        $minn->head(static fn (): string => ScreenSizes::stylesheet());
    }
}
