<?php

/**
 * A classic menu as navigation blocks (probe schema-and-menus): its
 * published items, nested by parent, an item with children a submenu and
 * one without a link, each carrying its menu item fields (the classes with
 * the item's context classes, the object id unless a custom link, the type
 * as its kind, the object as its type) in the order the block takes them.
 * An empty menu is '', anything but a menu a WP_Error.
 */
#[AllowDynamicProperties]
class WP_Classic_To_Block_Menu_Converter
{
    public static function convert($menu)
    {
        if (!is_object($menu) || empty($menu->term_id)) {
            return new WP_Error('invalid_menu', __('The menu provided is not a valid menu.'));
        }
        $items = wp_get_nav_menu_items($menu->term_id, ['update_post_term_cache' => false]);
        if (empty($items)) {
            return '';
        }
        _wp_menu_item_classes_by_context($items);
        $byParent = self::group_by_parent_id($items);
        return serialize_blocks(self::to_blocks($byParent[0] ?? [], $byParent));
    }

    private static function group_by_parent_id($menu_items)
    {
        $grouped = [];
        foreach ($menu_items as $item) {
            $grouped[(int) $item->menu_item_parent][] = $item;
        }
        return $grouped;
    }

    private static function to_blocks($menu_items, $menu_items_by_parent_id)
    {
        $blocks = [];
        foreach ($menu_items as $item) {
            $children = isset($menu_items_by_parent_id[$item->ID]) ? self::to_blocks($menu_items_by_parent_id[$item->ID], $menu_items_by_parent_id) : [];
            $blocks[] = [
                'blockName' => $children === [] ? 'core/navigation-link' : 'core/navigation-submenu',
                'attrs' => self::item_attributes($item),
                'innerBlocks' => $children,
                'innerContent' => array_map('serialize_block', $children),
            ];
        }
        return $blocks;
    }

    /** A menu item's fields as a navigation link's attributes, in the block's order. */
    private static function item_attributes($item)
    {
        return [
            'className' => empty($item->classes) ? null : implode(' ', (array) $item->classes),
            'description' => $item->description,
            'id' => $item->object !== 'custom' ? $item->object_id : null,
            'kind' => str_replace('_', '-', (string) ($item->type ?? 'custom')),
            'label' => $item->title,
            'opensInNewTab' => $item->target === '_blank',
            'rel' => (string) $item->xfn !== '' ? $item->xfn : null,
            'title' => $item->attr_title,
            'type' => $item->object,
            'url' => $item->url,
        ];
    }
}
