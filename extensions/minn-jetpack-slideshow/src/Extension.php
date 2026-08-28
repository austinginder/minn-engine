<?php

declare(strict_types=1);

namespace Minn\Ext\JetpackSlideshow;

use Minn\Blocks\Block;
use Minn\Extension\Extension as MinnExtension;
use Minn\Extension\Seams;
use Minn\Support\Html;

/**
 * The Jetpack slideshow block, as the plugin's front end delivered it: the
 * stored markup with its images sized (the engine already does that) but
 * without the lazy "auto" sizes hint the plugin's own image tags lack, plus
 * the block's stylesheet, the Swiper library, and the view script from the
 * plugin's own folder, which the site still carries. Only this block; the
 * rest of Jetpack is not provided.
 */
final class Extension implements MinnExtension
{
    private bool $used = false;

    public function register(Seams $minn): void
    {
        $base = rtrim((string) ($minn->site->option('siteurl') ?? ''), '/') . '/wp-content/plugins/jetpack/_inc/blocks/';
        $dir = ABSPATH . 'wp-content/plugins/jetpack/_inc/blocks/';
        $minn->filterBlocks(function (Block $block, string $html): string {
            if ($block->name !== 'jetpack/slideshow') {
                return $html;
            }
            $this->used = true;
            return str_replace('sizes="auto, ', 'sizes="', $html);
        });
        $minn->head(function () use ($base, $dir): string {
            if (!$this->used || !is_file($dir . 'slideshow/view.css')) {
                return '';
            }
            return '<link rel="stylesheet" id="jetpack-block-slideshow-css" href="' . Html::attr($base . 'slideshow/view.css') . '" media="all" />' . "\n"
                . '<link rel="stylesheet" id="jetpack-swiper-library-css" href="' . Html::attr($base . 'swiper.css') . '" media="all" />' . "\n"
                . '<script id="jetpack-blocks-assets-base-url-js-before">var Jetpack_Block_Assets_Base_Url="' . Html::attr($base) . '";</script>' . "\n";
        });
        $minn->footer(function () use ($base, $dir): string {
            if (!$this->used || !is_file($dir . 'slideshow/view.js')) {
                return '';
            }
            return '<script id="jetpack-swiper-library-js" src="' . Html::attr($base . 'swiper.js') . '"></script>' . "\n"
                . '<script defer id="jetpack-block-slideshow-js" src="' . Html::attr($base . 'slideshow/view.js') . '"></script>' . "\n";
        });
    }
}
