<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Blocks\Block;
use Minn\Blocks\Serializer;
use Minn\Content\Menus;

/**
 * The navigation menu a navigation block falls back to
 * (WP_Navigation_Fallback, and the block_core_navigation_* helpers the
 * reference keeps as deprecated): the newest published navigation menu, else
 * one made from the site's classic menu, else a default listing the pages
 * (probes navigation-fallback, nav-pomo-rest-doubts).
 */
final class NavigationFallback
{
    /**
     * The navigation menu to fall back to: the newest published one; else,
     * unless wp_navigation_should_create_fallback says not, one made from the
     * classic menu or, without one, the default, then the newest again.
     */
    public static function fallback(): mixed
    {
        $create = Runtime::hooks()->filter('wp_navigation_should_create_fallback', [true]);
        $fallback = self::newest();
        if ($fallback || !$create) {
            return $fallback;
        }
        if (!self::fromClassicMenu()) {
            self::createDefault();
        }
        return self::newest();
    }

    /**
     * The classic menu a navigation falls back to: the one in the primary
     * location, else the one slugged primary, else the newest; null without menus.
     */
    public static function classicMenu(): mixed
    {
        $menus = (array) NavMenu::menus();
        if ($menus === []) {
            return null;
        }
        $primary = NavMenu::locations()['primary'] ?? null;
        $menu = $primary ? NavMenu::menu($primary) : null;
        if ($menu) {
            return $menu;
        }
        foreach ($menus as $candidate) {
            if (($candidate->slug ?? null) === 'primary') {
                return $candidate;
            }
        }
        usort($menus, static fn ($a, $b) => $b->term_id <=> $a->term_id);
        return $menus[0];
    }

    /** A classic menu's items as serialized navigation blocks, parsed by the callable given (else here). */
    public static function classicMenuBlocks(object $menu, ?callable $parse = null): string
    {
        $items = MenuItemMarks::apply(array_values((array) NavMenu::items($menu->term_id, ['update_post_term_cache' => false])));
        $byParent = [];
        foreach ($items as $item) {
            $byParent[(int) $item->menu_item_parent][] = $item;
        }
        $blocks = ($parse ?? self::menuItemBlocks(...))($byParent[0] ?? [], $byParent);
        return Serializer::blocks(array_map(static fn (array $block): Block => Block::fromArray($block), array_values((array) $blocks)));
    }

    /**
     * Menu items as navigation blocks: a link each, a submenu for an item with
     * children (its inner blocks serialized as its content), the children
     * parsed by the callable given (else here).
     *
     * @return list<array<string, mixed>>
     */
    public static function menuItemBlocks(mixed $items, mixed $byParent, ?callable $parse = null): array
    {
        $blocks = [];
        foreach ((array) $items as $item) {
            $block = ['blockName' => 'core/navigation-link', 'attrs' => Menus::linkAttributes((array) $item), 'innerBlocks' => [], 'innerContent' => []];
            $children = $byParent[$item->ID] ?? [];
            if ($children !== []) {
                $block['blockName'] = 'core/navigation-submenu';
                $block['innerBlocks'] = ($parse ?? self::menuItemBlocks(...))($children, $byParent);
                $block['innerContent'] = array_map(static fn (array $inner): string => Serializer::block(Block::fromArray($inner)), $block['innerBlocks']);
            }
            $blocks[] = $block;
        }
        return $blocks;
    }

    /** The newest published navigation menu, through the query's filters, or null. */
    public static function newest(): mixed
    {
        $posts = Handoff::newQuery()->query(['post_type' => 'wp_navigation', 'post_status' => 'publish', 'orderby' => 'date', 'order' => 'DESC', 'posts_per_page' => 1, 'no_found_rows' => true]);
        return $posts[0] ?? null;
    }

    /** The classic menu made a published navigation menu: the insert's answer, or null without a classic menu or with an empty one. */
    public static function fromClassicMenu(): mixed
    {
        $menu = self::classicMenu();
        $content = $menu ? self::classicMenuBlocks($menu) : '';
        if ($content === '') {
            return null;
        }
        return PostWrites::insert(['post_content' => $content, 'post_title' => $menu->name, 'post_name' => $menu->slug, 'post_status' => 'publish', 'post_type' => 'wp_navigation'], true);
    }

    /** A published navigation menu titled Navigation, listing the site's pages while the page list block is registered. */
    public static function createDefault(): mixed
    {
        $content = Handoff::blockTypes()->is_registered('core/page-list') ? '<!-- wp:page-list /-->' : '';
        return PostWrites::insert(['post_type' => 'wp_navigation', 'post_title' => \Minn\I18n\Gettext::inContext('Navigation', 'Title of a Navigation menu'), 'post_content' => $content, 'post_status' => 'publish'], true);
    }
}
