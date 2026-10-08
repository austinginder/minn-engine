<?php

declare(strict_types=1);

namespace Minn\Blocks\Dynamic;

use Minn\Blocks\Block;
use Minn\Blocks\Layout;
use Minn\Blocks\Renderer;
use Minn\Support\Html;
use Minn\Blocks\Styles;
use Minn\Support\Kses;

/**
 * core/social-links and core/social-link. The list keeps its stored
 * wrapper and gains the flex layout classes; each link renders from its
 * parent's icon colours and the service's icon. The icons are the
 * reference's rendered output captured as data (src/data/social-icons.json,
 * the upstream set is CC0); an unknown service gets the share icon.
 */
final class SocialLinks
{
    /** What a social-links list provides its links as context. */
    private const PARENT_SETTINGS = ['openInNewTab' => true, 'showLabels' => true, 'iconColor' => true, 'iconColorValue' => true, 'iconBackgroundColor' => true, 'iconBackgroundColorValue' => true];

    /** @var array<string, array{label: string, svg: string}>|null */
    private ?array $icons = null;

    public function __construct(private readonly string $iconsFile)
    {
    }

    /** Registers the block and its links with the renderer. */
    public function register(Renderer $renderer): void
    {
        $renderer->registerDynamic('core/social-links', $this->list(...));
        // On its own (its render callback, or outside a list) a link takes the list's settings from its context.
        $renderer->registerDynamic('core/social-link', fn (Block $block, Renderer $renderer): string => $this->link($block, new Block('core/social-links', array_intersect_key($renderer->blockContext(), self::PARENT_SETTINGS), [], '', [])));
    }

    private function list(Block $block, Renderer $renderer): string
    {
        $out = '';
        $inner = 0;
        foreach ($block->innerContent as $chunk) {
            if ($chunk !== null) {
                $out .= $chunk;
                continue;
            }
            $child = $block->innerBlocks[$inner++];
            $out .= $child->name === 'core/social-link' ? $this->link($child, $block) : $renderer->renderBlock($child);
        }
        return Html::addClasses($out, Layout::classes('social-links', $block->attrs, 'flex'));
    }

    private function link(Block $link, Block $parent): string
    {
        $service = Styles::slug((string) $link->attr('service', ''));
        $icon = $this->icons()[$service] ?? ['label' => 'Share Icon', 'svg' => $this->icons()['chain']['svg'] ?? ''];
        $url = Kses::url((string) $link->attr('url', ''));
        $label = (string) $link->attr('label', '');

        $classes = ['wp-social-link', 'wp-social-link-' . $service];
        $style = [];
        if (!empty($parent->attrs['iconColor'])) {
            $classes[] = 'has-' . Styles::slug((string) $parent->attrs['iconColor']) . '-color';
        }
        if (!empty($parent->attrs['iconColorValue'])) {
            $style[] = 'color:' . Styles::value((string) $parent->attrs['iconColorValue']);
        }
        if (!empty($parent->attrs['iconBackgroundColor'])) {
            $classes[] = 'has-' . Styles::slug((string) $parent->attrs['iconBackgroundColor']) . '-background-color';
        }
        if (!empty($parent->attrs['iconBackgroundColorValue'])) {
            $style[] = 'background-color:' . Styles::value((string) $parent->attrs['iconBackgroundColorValue']);
        }
        if ($link->className() !== '') {
            $classes[] = $link->className();
        }
        $classes[] = 'wp-block-social-link';

        $anchor = (bool) $parent->attr('openInNewTab', false)
            ? '<a rel="noopener nofollow" target="_blank" href="' . Html::attr($url) . '" class="wp-block-social-link-anchor">'
            : '<a href="' . Html::attr($url) . '" class="wp-block-social-link-anchor">';
        $text = $label !== '' ? Html::esc($label) : Html::esc($icon['label']);
        $labelSpan = (bool) $parent->attr('showLabels', false)
            ? '<span class="wp-block-social-link-label">' . $text . '</span>'
            : '<span class="wp-block-social-link-label screen-reader-text">' . $text . '</span>';

        return '<li' . ($style === [] ? '' : ' style="' . Html::attr(implode(';', $style)) . '"') . ' class="' . Html::attr(implode(' ', $classes)) . '">'
            . $anchor . $icon['svg'] . $labelSpan . '</a></li>';
    }

    /** @return array<string, array{label: string, svg: string}> */
    private function icons(): array
    {
        return $this->icons ??= (array) json_decode((string) file_get_contents($this->iconsFile), true);
    }
}
