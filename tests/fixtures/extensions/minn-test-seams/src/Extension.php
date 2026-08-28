<?php

declare(strict_types=1);

namespace Minn\Ext\TestSeams;

use Minn\Blocks\Block;
use Minn\Extension\Extension as MinnExtension;
use Minn\Extension\Seams;

/** Touches every seam once, so the suite can see each one fire. */
final class Extension implements MinnExtension
{
    public function register(Seams $minn): void
    {
        $minn->shortcode('seam_probe', static fn (array $attrs, ?string $content): string => '<span class="seam-probe" data-name="' . htmlspecialchars($attrs['name'] ?? $attrs['0'] ?? '') . '">' . htmlspecialchars($content ?? 'no content') . '</span>');
        $minn->filterContent(static fn (string $html, array $post): string => $html . '<!-- seam:content post-' . (int) $post['ID'] . ' -->');
        $minn->filterBlocks(static fn (Block $block, string $html): string => $block->name === 'core/paragraph' && str_contains($html, 'seam-mark') ? str_replace('seam-mark', 'seam-marked', $html) : $html);
        $minn->gateBlocks(static fn (Block $block): ?bool => ($block->attrs['className'] ?? '') === 'seam-gated' ? false : null);
        $minn->head(static fn (): string => '<meta name="seam-probe" content="head">' . "\n");
        $minn->footer(static fn (Seams $s): string => '<!-- seam:footer reader-' . $s->reader->userId . ' -->' . "\n");
        $minn->bodyClass('seam-body');
    }
}
