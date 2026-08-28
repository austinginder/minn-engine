<?php

declare(strict_types=1);

namespace Minn\Blocks\Dynamic\Theme;

use Minn\Blocks\Block;
use Minn\Blocks\Layout;
use Minn\Blocks\Parser;
use Minn\Blocks\Renderer;
use Minn\Blocks\RenderState;
use Minn\Blocks\Styles;
use Minn\Content\Posts;
use Minn\Db;
use Minn\Front\Kind;
use Minn\Front\Permalinks;
use Minn\Support\Html;

/**
 * navigation, navigation-link, page-list. A navigation block's items come
 * from its inner blocks, the wp_navigation post it references, or the
 * newest published wp_navigation post; a responsive menu wraps them in the
 * overlay markup the reference emits.
 */
final readonly class Navigation
{
    public function __construct(
        private Db $db,
        private Posts $posts,
        private Permalinks $permalinks,
    ) {
    }

    public function register(Renderer $renderer): void
    {
        $renderer->registerDynamic('core/navigation', $this->navigation(...));
        $renderer->registerDynamic('core/navigation-link', $this->link(...));
        $renderer->registerDynamic('core/page-list', $this->pageList(...));
    }

    private function navigation(Block $block, Renderer $renderer): string
    {
        $items = $block->innerBlocks;
        $label = '';
        if ($items === []) {
            $menu = $this->menuPost((int) $block->attr('ref', 0));
            $items = $menu === null ? [] : Parser::parse((string) $menu['post_content']);
            // The menu's title labels the nav only when the block names the menu.
            $label = (int) $block->attr('ref', 0) > 0 ? (string) ($menu['post_title'] ?? '') : '';
        }
        // Only blocks are items; the whitespace between them is not rendered.
        $items = array_values(array_filter($items, static fn (Block $item) => $item->name !== null));
        $label = RenderState::uniqueLabel($label);
        $id = RenderState::nextId();
        $layout = (array) $block->attr('layout', []);
        $vertical = ($layout['orientation'] ?? '') === 'vertical';
        $justify = (string) ($layout['justifyContent'] ?? '');
        $responsive = (string) $block->attr('overlayMenu', 'mobile') !== 'never';
        $colors = self::overlayColors($block);
        // The block's own colour, size, and family presets ride on both
        // the nav and its list; the typography style rides inline on both.
        $presets = self::presetClasses($block);
        $family = empty($block->attrs['fontFamily']) ? [] : ['has-' . $block->attrs['fontFamily'] . '-font-family'];
        $inline = Styles::inline((array) $block->attr('style', []));
        $style = $inline === '' ? '' : 'style="' . Html::attr($inline) . '" ';

        $listClasses = implode(' ', array_values(array_filter([
            'wp-block-navigation__container',
            ...$presets,
            $vertical ? 'is-vertical' : null,
            $responsive ? 'is-responsive' : null,
            $justify !== '' ? 'items-justified-' . $justify : null,
            'wp-block-navigation',
            ...$family,
        ])));
        $list = '<ul ' . $style . 'class="' . $listClasses . '">' . $this->items($items, $renderer) . '</ul>';

        $navClasses = array_values(array_filter([
            ...$presets,
            $vertical ? 'is-vertical' : null,
            $responsive ? 'is-responsive' : null,
            $justify !== '' ? 'items-justified-' . $justify : null,
            'wp-block-navigation',
            ...$family,
            ...array_filter(Layout::classes('navigation', $block->attrs, 'flex'), static fn (string $c) => $c !== 'is-vertical'),
        ]));
        $nav = '<nav ' . $style . 'class="' . implode(' ', $navClasses) . '"';
        if (!$responsive) {
            return $nav . ' aria-label="' . Html::attr($label) . '">' . $list . '</nav>';
        }
        $ariaLabel = 'Menu';
        $hasIcon = (bool) $block->attr('hasIcon', true);
        $open = $hasIcon
            ? 'aria-label="Open menu"'
            : '';
        $openInner = $hasIcon
            ? '<svg width="24" height="24" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 7.5h16v1.5H4z"></path><path d="M4 15h16v1.5H4z"></path></svg>'
            : 'Menu';
        $close = $hasIcon ? 'aria-label="Close menu"' : '';
        $closeInner = $hasIcon
            ? '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false"><path d="m13.06 12 6.47-6.47-1.06-1.06L12 10.94 5.53 4.47 4.47 5.53 10.94 12l-6.47 6.47 1.06 1.06L12 13.06l6.47 6.47 1.06-1.06L13.06 12Z"></path></svg>'
            : 'Close';
        return $nav . ($label === '' ? '' : ' aria-label="' . Html::attr($label) . '"') . ' ' . "\n\t\t" . ' data-wp-interactive="core/navigation" data-wp-context=\'{"overlayOpenedBy":{"click":false,"hover":false,"focus":false},"type":"overlay","roleAttribute":"","ariaLabel":"' . $ariaLabel . '"}\'>'
            . '<button aria-haspopup="dialog" ' . $open . ' class="wp-block-navigation__responsive-container-open" ' . "\n\t\t\t\t" . 'data-wp-on--click="actions.openMenuOnClick"' . "\n\t\t\t\t" . 'data-wp-on--keydown="actions.handleMenuKeydown"' . "\n\t\t\t" . '>' . $openInner . '</button>'
            . "\n\t\t\t\t" . '<div class="wp-block-navigation__responsive-container' . $colors . '"  id="modal-' . $id . '" ' . "\n\t\t\t\t" . 'data-wp-class--has-modal-open="state.isMenuOpen"' . "\n\t\t\t\t" . 'data-wp-class--is-menu-open="state.isMenuOpen"' . "\n\t\t\t\t" . 'data-wp-watch="callbacks.initMenu"' . "\n\t\t\t\t" . 'data-wp-on--keydown="actions.handleMenuKeydown"' . "\n\t\t\t\t" . 'data-wp-on--focusout="actions.handleMenuFocusout"' . "\n\t\t\t\t" . 'tabindex="-1"' . "\n\t\t\t" . '>'
            . "\n\t\t\t\t\t" . '<div class="wp-block-navigation__responsive-close" tabindex="-1">'
            . "\n\t\t\t\t\t\t" . '<div class="wp-block-navigation__responsive-dialog" ' . "\n\t\t\t\t" . 'data-wp-bind--aria-modal="state.ariaModal"' . "\n\t\t\t\t" . 'data-wp-bind--aria-label="state.ariaLabel"' . "\n\t\t\t\t" . 'data-wp-bind--role="state.roleAttribute"' . "\n\t\t\t" . '>'
            . "\n\t\t\t\t\t\t\t" . '<button ' . $close . ' class="wp-block-navigation__responsive-container-close" ' . "\n\t\t\t\t" . 'data-wp-on--click="actions.closeMenuOnClick"' . "\n\t\t\t" . '>' . $closeInner . '</button>'
            . "\n\t\t\t\t\t\t\t" . '<div class="wp-block-navigation__responsive-container-content" ' . "\n\t\t\t\t" . 'data-wp-watch="callbacks.focusFirstElement"' . "\n\t\t\t" . ' id="modal-' . $id . '-content">'
            . "\n\t\t\t\t\t\t\t\t" . $list
            . "\n\t\t\t\t\t\t\t" . '</div>'
            . "\n\t\t\t\t\t\t" . '</div>'
            . "\n\t\t\t\t\t" . '</div>'
            . "\n\t\t\t\t" . '</div></nav>';
    }

    /**
     * The menu's items. Link-shaped blocks render as their own list
     * items; any other block is wrapped in a plain item.
     *
     * @param list<Block> $items
     */
    private function items(array $items, Renderer $renderer): string
    {
        $out = '';
        foreach ($items as $item) {
            $html = $renderer->renderBlock($item);
            $out .= in_array($item->name, ['core/navigation-link', 'core/navigation-submenu', 'core/page-list', 'core/home-link'], true) || trim($html) === ''
                ? $html
                : '<li class="wp-block-navigation-item">' . "\n" . $html . '</li>';
        }
        return $out;
    }

    /** @return list<string> the colour and font-size preset classes the block's attributes declare */
    private static function presetClasses(Block $block): array
    {
        $classes = [];
        if (!empty($block->attrs['textColor']) || isset($block->attrs['style']['color']['text'])) {
            $classes[] = 'has-text-color';
        }
        if (!empty($block->attrs['textColor'])) {
            $classes[] = 'has-' . $block->attrs['textColor'] . '-color';
        }
        if (!empty($block->attrs['backgroundColor']) || isset($block->attrs['style']['color']['background'])) {
            $classes[] = 'has-background';
        }
        if (!empty($block->attrs['backgroundColor'])) {
            $classes[] = 'has-' . $block->attrs['backgroundColor'] . '-background-color';
        }
        if (!empty($block->attrs['fontSize'])) {
            $classes[] = 'has-' . $block->attrs['fontSize'] . '-font-size';
        }
        return $classes;
    }

    /** The overlay's colour classes, from the overlay* attributes. */
    private static function overlayColors(Block $block): string
    {
        $classes = '';
        if (!empty($block->attrs['overlayTextColor'])) {
            $classes .= ' has-text-color has-' . $block->attrs['overlayTextColor'] . '-color';
        }
        if (!empty($block->attrs['overlayBackgroundColor'])) {
            $classes .= ' has-background has-' . $block->attrs['overlayBackgroundColor'] . '-background-color';
        }
        return $classes;
    }

    private function link(Block $block, Renderer $renderer): string
    {
        $label = (string) $block->attr('label', '');
        $url = (string) $block->attr('url', '');
        $resolution = $renderer->context()->resolution;
        $current = (string) $block->attr('kind', '') === 'post-type'
            && (int) $block->attr('id', 0) === $resolution->id()
            && $resolution->kind === ((string) $block->attr('type', '') === 'page' ? Kind::Page : Kind::Single);
        $classes = 'wp-block-navigation-item' . ($current ? ' current-menu-item' : '') . ' wp-block-navigation-link';
        $target = (bool) $block->attr('opensInNewTab', false) ? ' target="_blank"  ' : '';
        return '<li class="' . $classes . '"><a class="wp-block-navigation-item__content"  href="' . Html::attr($url) . '"' . $target . ($current ? ' aria-current="page"' : '') . '><span class="wp-block-navigation-item__label">' . $label . '</span></a></li>';
    }

    private function pageList(Block $block, Renderer $renderer): string
    {
        $tree = $this->posts->pageTree();
        // The reference compares the queried object's id to page ids without
        // regard to type, so a term or author archive can light up a page too.
        $currentId = $renderer->context()->resolution->id();
        return '<ul class="wp-block-page-list">' . $this->pageItems($tree, 0, $currentId, $this->ancestorsOf($tree, $currentId)) . '</ul>';
    }

    /** @return list<int> the ids of the current page's ancestors */
    private function ancestorsOf(array $tree, int $currentId): array
    {
        $parents = [];
        foreach ($tree as $parent => $children) {
            foreach ($children as $child) {
                $parents[(int) $child['ID']] = (int) $parent;
            }
        }
        $ancestors = [];
        $id = $parents[$currentId] ?? 0;
        while ($id > 0) {
            $ancestors[] = $id;
            $id = $parents[$id] ?? 0;
        }
        return $ancestors;
    }

    private function pageItems(array $tree, int $parent, int $currentId, array $ancestors, string $submenuColors = ' has-text-color has-contrast-color has-background has-base-background-color'): string
    {
        $out = '';
        foreach ($tree[$parent] ?? [] as $page) {
            $id = (int) $page['ID'];
            $isCurrent = $id === $currentId;
            $marker = $isCurrent ? ' current-menu-item' : (in_array($id, $ancestors, true) ? ' current-menu-ancestor' : '');
            $link = '<a class="wp-block-pages-list__item__link wp-block-navigation-item__content" href="' . Html::attr($this->permalinks->forPage($page + ['post_type' => 'page', 'post_status' => 'publish'])) . '"' . ($isCurrent ? ' aria-current="page"' : '') . '>' . Html::esc((string) $page['post_title']) . '</a>';
            if (isset($tree[$id])) {
                $out .= '<li data-wp-context="{ &quot;submenuOpenedBy&quot;: { &quot;click&quot;: false, &quot;hover&quot;: false, &quot;focus&quot;: false }, &quot;type&quot;: &quot;submenu&quot;, &quot;modal&quot;: null, &quot;previousFocus&quot;: null }" data-wp-interactive="core/navigation" data-wp-on--focusout="actions.handleMenuFocusout" data-wp-on--keydown="actions.handleMenuKeydown" data-wp-on--pointerenter="actions.openMenuOnHover" data-wp-on--pointerleave="actions.closeMenuOnHover" data-wp-watch="callbacks.initMenu" tabindex="-1" class="wp-block-pages-list__item' . $marker . ' has-child wp-block-navigation-item open-on-hover-click">'
                    . $link
                    . '<button data-wp-bind--aria-expanded="state.isSubmenuOpen" data-wp-on--click="actions.toggleMenuOnClick" aria-label="' . Html::attr((string) $page['post_title']) . ' submenu" class="wp-block-navigation__submenu-icon wp-block-navigation-submenu__toggle" ><svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true" focusable="false"><path d="M1.50002 4L6.00002 8L10.5 4" stroke-width="1.5"></path></svg></button>'
                    . '<ul data-wp-on--focus="actions.openMenuOnFocus" class="wp-block-navigation__submenu-container">' . $this->pageItems($tree, $id, $currentId, $ancestors, $submenuColors) . '</ul></li>';
                continue;
            }
            $itemClasses = 'wp-block-pages-list__item' . $marker . ' wp-block-navigation-item' . ($parent > 0 ? ' open-on-hover-click' . $submenuColors : '');
            $out .= '<li class="' . $itemClasses . '">' . $link . '</li>';
        }
        return $out;
    }

    private function menuPost(int $ref): ?array
    {
        $table = $this->db->table('posts');
        if ($ref > 0) {
            return $this->db->row("SELECT * FROM {$table} WHERE ID = ? AND post_type = 'wp_navigation' LIMIT 1", [$ref]);
        }
        return $this->db->row("SELECT * FROM {$table} WHERE post_type = 'wp_navigation' AND post_status = 'publish' ORDER BY post_date DESC LIMIT 1");
    }
}
