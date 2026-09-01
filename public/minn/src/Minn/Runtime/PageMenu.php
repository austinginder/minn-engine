<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Support\Html;

/**
 * The page-list menu a classic theme falls back to when no menu is
 * assigned to a location. Storefront's header is one of these, so its
 * markup is what the theme's CSS binds to.
 *
 * Shapes captured from the reference: the optional home item carries no
 * class (the attribute is written but left empty), a page item carries
 * `page_item page-item-{id}` plus `current_page_item` for the page being
 * viewed, and the whole list is wrapped in the caller's before/after.
 */
final class PageMenu
{
    /**
     * The page menu's list items.
     *
     * @param list<array{id: int, title: string, url: string, current: bool}> $pages
     * @param array{label: string, url: string, current: bool}|null $home
     */
    public static function items(array $pages, ?array $home, string $linkBefore = '', string $linkAfter = ''): string
    {
        $list = '';
        if ($home !== null) {
            $classes = $home['current'] ? 'current_page_item' : '';
            $list .= '<li ' . ($classes === '' ? '' : 'class="' . $classes . '"') . '><a href="' . Html::attr($home['url']) . '">'
                . $linkBefore . Html::esc($home['label']) . $linkAfter . '</a></li>';
        }
        foreach ($pages as $page) {
            $classes = 'page_item page-item-' . $page['id'] . ($page['current'] ? ' current_page_item' : '');
            $list .= '<li class="' . $classes . '"><a href="' . Html::attr($page['url']) . '">'
                . $linkBefore . Html::esc($page['title']) . $linkAfter . '</a></li>';
        }
        return $list;
    }

    /**
     * The home item a `show_home` argument asks for: true (or 1) means the
     * default label, a string is the label itself, anything falsy means no
     * item at all.
     *
     * @return array{label: string, url: string, current: bool}|null
     */
    public static function home(mixed $showHome, string $url, bool $current, string $defaultLabel = 'Home'): ?array
    {
        if (empty($showHome)) {
            return null;
        }
        $label = is_string($showHome) && $showHome !== '' && $showHome !== '1' ? $showHome : $defaultLabel;
        return ['label' => $label, 'url' => $url, 'current' => $current];
    }
}
