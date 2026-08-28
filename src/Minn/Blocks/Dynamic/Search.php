<?php

declare(strict_types=1);

namespace Minn\Blocks\Dynamic;

use Minn\Blocks\Block;
use Minn\Blocks\RenderState;
use Minn\Front\Permalinks;
use Minn\Support\Html;

/** core/search: the site search form; the input id comes from the shared request counter. */
final readonly class Search
{
    public function __construct(private Permalinks $permalinks)
    {
    }

    public function render(Block $block): string
    {
        $id = 'wp-block-search__input-' . RenderState::nextId();
        $label = (string) $block->attr('label', 'Search');
        $buttonText = (string) $block->attr('buttonText', 'Search');
        $placeholder = (string) $block->attr('placeholder', '');
        return '<form role="search" method="get" action="' . Html::attr($this->permalinks->url('/')) . '" class="wp-block-search__button-outside wp-block-search__text-button wp-block-search" >'
            . '<label class="wp-block-search__label" for="' . $id . '" >' . $label . '</label>'
            . '<div class="wp-block-search__inside-wrapper" >'
            . '<input class="wp-block-search__input" id="' . $id . '" placeholder="' . Html::attr($placeholder) . '" value="" type="search" name="s" required />'
            . '<button aria-label="' . Html::attr($buttonText) . '" class="wp-block-search__button wp-element-button" type="submit" >' . $buttonText . '</button>'
            . '</div></form>';
    }
}
