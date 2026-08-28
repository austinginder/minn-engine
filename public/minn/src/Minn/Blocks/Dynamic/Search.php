<?php

declare(strict_types=1);

namespace Minn\Blocks\Dynamic;

use Minn\Blocks\Block;
use Minn\Blocks\RenderState;
use Minn\Blocks\Renderer;
use Minn\Front\Permalinks;
use Minn\Support\Html;
use Minn\Blocks\Styles;

/**
 * core/search: the site search form. The button sits outside or inside
 * the field and shows text or the search icon; the block's colour and
 * font presets land on the button (font family on the input too). The
 * input id comes from the shared request counter.
 */
final readonly class Search
{
    private const ICON = '<svg class="search-icon" viewBox="0 0 24 24" width="24" height="24">' . "\n\t\t\t\t\t"
        . '<path d="M13 5c-3.3 0-6 2.7-6 6 0 1.4.5 2.7 1.3 3.7l-3.8 3.8 1.1 1.1 3.8-3.8c1 .8 2.3 1.3 3.7 1.3 3.3 0 6-2.7 6-6S16.3 5 13 5zm0 10.5c-2.5 0-4.5-2-4.5-4.5s2-4.5 4.5-4.5 4.5 2 4.5 4.5-2 4.5-4.5 4.5z"></path>' . "\n\t\t\t\t"
        . '</svg>';

    public function __construct(private Permalinks $permalinks)
    {
    }

    public function render(Block $block, Renderer $renderer): string
    {
        $id = 'wp-block-search__input-' . RenderState::nextId();
        $label = (string) $block->attr('label', 'Search');
        $buttonText = (string) $block->attr('buttonText', 'Search');
        $placeholder = (string) $block->attr('placeholder', '');
        $value = $renderer->context()->resolution->search ?? '';
        $position = (string) $block->attr('buttonPosition', 'button-outside');
        if (!in_array($position, ['button-outside', 'button-inside', 'no-button', 'button-only'], true)) {
            $position = 'button-outside';
        }
        $useIcon = (bool) $block->attr('buttonUseIcon', false);
        $family = empty($block->attrs['fontFamily']) ? '' : ' has-' . Styles::slug((string) $block->attrs['fontFamily']) . '-font-family';

        $buttonClasses = ['wp-block-search__button'];
        if (!empty($block->attrs['textColor'])) {
            array_push($buttonClasses, 'has-text-color', 'has-' . Styles::slug((string) $block->attrs['textColor']) . '-color');
        }
        if (!empty($block->attrs['backgroundColor'])) {
            array_push($buttonClasses, 'has-background', 'has-' . Styles::slug((string) $block->attrs['backgroundColor']) . '-background-color');
        }
        if ($family !== '') {
            $buttonClasses[] = trim($family);
        }
        if ($useIcon) {
            $buttonClasses[] = 'has-icon';
        }
        $buttonClasses[] = 'wp-element-button';

        return '<form role="search" method="get" action="' . Html::attr($this->permalinks->url('/')) . '" class="wp-block-search__' . $position . ' wp-block-search__' . ($useIcon ? 'icon' : 'text') . '-button wp-block-search" >'
            . '<label class="wp-block-search__label' . ((bool) $block->attr('showLabel', true) ? '' : ' screen-reader-text') . '" for="' . $id . '" >' . Html::esc($label) . '</label>'
            . '<div class="wp-block-search__inside-wrapper" >'
            . '<input class="wp-block-search__input' . $family . '" id="' . $id . '" placeholder="' . Html::attr($placeholder) . '" value="' . Html::attr((string) $value) . '" type="search" name="s" required />'
            . '<button aria-label="' . Html::attr($buttonText) . '" class="' . implode(' ', $buttonClasses) . '" type="submit" >' . ($useIcon ? self::ICON : $buttonText) . '</button>'
            . '</div></form>';
    }
}
