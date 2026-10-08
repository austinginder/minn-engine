<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\I18n\Gettext;
use Minn\Support\Lists;

/**
 * Classic menu items as the reference serves them to walkers and plugins
 * (probe nav-menu-items). wp_setup_nav_menu_item dresses a nav_menu_item
 * post with the fields walkers read: its _menu_item_* meta (ids as
 * strings), kept when already set; then by its kind the label, address
 * and title of what it points at (a post's title through the_title, every
 * time, the item's own title winning when it has one; a post or term
 * gone, or a post trashed, marks it _invalid; a post not published is
 * labelled by its status); its title attribute and description through
 * their filters. A post or term of another kind is dressed as a would-be
 * item, as the admin's boxes use it: id 0, its own title, address and
 * parent. wp_get_nav_menu_items reads a menu's items through get_posts,
 * sets each up, drops the invalid ones outside the admin, and for ARRAY_A
 * output sorts them by the output key and numbers that key from 1, all
 * before its filter.
 */
final class NavMenuItems
{
    /** wp_setup_nav_menu_item: the item dressed, whatever it was, then the filter. */
    public static function setUp(mixed $item): mixed
    {
        if (is_object($item) && isset($item->post_type)) {
            $item->post_type === 'nav_menu_item' ? self::menuItem($item) : self::post($item);
        } elseif (is_object($item) && isset($item->taxonomy)) {
            self::term($item);
        }
        return \apply_filters('wp_setup_nav_menu_item', $item);
    }

    /**
     * wp_get_nav_menu_items for a menu found.
     *
     * @param array<string, mixed> $args
     * @return array<int, object>
     */
    public static function forMenu(\WP_Term $menu, array $args): array
    {
        $args = Lists::args($args, [
            'order' => 'ASC', 'orderby' => 'menu_order', 'post_type' => 'nav_menu_item', 'post_status' => 'publish', 'output' => \ARRAY_A, 'output_key' => 'menu_order', 'nopaging' => true,
            'update_menu_item_cache' => true, 'tax_query' => [['taxonomy' => 'nav_menu', 'field' => 'term_taxonomy_id', 'terms' => $menu->term_taxonomy_id]],
        ]);
        $items = array_map(self::setUp(...), (array) \get_posts($args));
        if (!Runtime::current()->isAdmin) {
            $items = array_filter($items, static fn ($item) => empty($item->_invalid));
        }
        if ($args['output'] === \ARRAY_A) {
            $key = (string) $args['output_key'];
            usort($items, static fn ($a, $b) => is_numeric($a->$key ?? '') && is_numeric($b->$key ?? '') ? $a->$key <=> $b->$key : strcmp((string) ($a->$key ?? ''), (string) ($b->$key ?? '')));
            foreach ($items as $n => $item) {
                $item->$key = $n + 1;
            }
        }
        return (array) \apply_filters('wp_get_nav_menu_items', $items, $menu, $args);
    }

    /** A nav_menu_item post: its meta, what it points at, its attribute and description. */
    private static function menuItem(object $item): void
    {
        $id = (int) $item->ID;
        $meta = static fn (string $key): mixed => \get_post_meta($id, '_menu_item_' . $key, true);
        $item->db_id = $id;
        $item->menu_item_parent ??= (string) $meta('menu_item_parent');
        $item->object_id ??= (string) $meta('object_id');
        $item->object ??= (string) $meta('object');
        $item->type ??= (string) $meta('type');
        match ((string) $item->type) {
            'post_type' => self::pointsAtPost($item),
            'taxonomy' => self::pointsAtTerm($item),
            'post_type_archive' => self::pointsAtArchive($item),
            default => self::custom($item, (string) $meta('url')),
        };
        $item->target ??= (string) $meta('target');
        $item->attr_title ??= \apply_filters('nav_menu_attr_title', $item->post_excerpt ?? '');
        $item->description ??= \apply_filters('nav_menu_description', \wp_trim_words((string) ($item->post_content ?? ''), 200));
        $item->classes ??= (array) $meta('classes');
        $item->xfn ??= (string) $meta('xfn');
    }

    /** An item for a post: the post type's name (its status's for a post not published), the post's address and filtered title. */
    private static function pointsAtPost(object $item): void
    {
        $type = \get_post_type_object((string) $item->object);
        $original = \get_post((int) $item->object_id);
        $item->type_label = $type ? $type->labels->singular_name : (string) $item->object;
        if ($original && $original->post_status !== 'publish' && ($status = \get_post_status_object($original->post_status))) {
            $item->type_label = $status->label;
        }
        if (!$type || !$original || $original->post_status === 'trash') {
            $item->_invalid = true;
        }
        $item->url = $original ? (string) \get_permalink($original) : '';
        $title = $original ? \apply_filters('the_title', $original->post_title, $original->ID) : '';
        $item->title = (string) $item->post_title === '' ? (string) $title : (string) $item->post_title;
    }

    /** An item for a term: the taxonomy's name, the term's address and name. */
    private static function pointsAtTerm(object $item): void
    {
        $taxonomy = \get_taxonomy((string) $item->object);
        $term = \get_term((int) $item->object_id, (string) $item->object);
        $item->type_label = $taxonomy ? $taxonomy->labels->singular_name : (string) $item->object;
        $link = $term instanceof \WP_Term ? \get_term_link($term, (string) $item->object) : '';
        if (!$taxonomy || !$term instanceof \WP_Term || \is_wp_error($link)) {
            $item->_invalid = true;
        }
        $item->url = is_string($link) ? $link : '';
        $item->title = (string) $item->post_title === '' ? ($term instanceof \WP_Term ? $term->name : '') : (string) $item->post_title;
    }

    /** An item for a post type's archive: the archives label, the archive's address. */
    private static function pointsAtArchive(object $item): void
    {
        $type = \get_post_type_object((string) $item->object);
        $item->type_label = Gettext::text('Post Type Archive');
        if (!$type) {
            $item->_invalid = true;
        }
        $item->url = $type ? (string) \get_post_type_archive_link((string) $item->object) : '';
        $item->title = (string) $item->post_title === '' ? ($type ? (string) $type->labels->archives : '') : (string) $item->post_title;
    }

    /** A custom link: its own title and stored address. */
    private static function custom(object $item, string $url): void
    {
        $item->type_label = Gettext::text('Custom Link');
        $item->title = (string) $item->post_title;
        $item->url ??= $url;
    }

    /** A post of another kind, as a would-be item for it. */
    private static function post(object $item): void
    {
        $type = \get_post_type_object((string) $item->post_type);
        $item->db_id = 0;
        $item->menu_item_parent = 0;
        $item->object_id = (int) $item->ID;
        $item->type = 'post_type';
        $item->object = $type ? $type->name : (string) $item->post_type;
        $item->type_label = $type ? $type->labels->singular_name : (string) $item->post_type;
        $item->title = (string) $item->post_title;
        $item->url = (string) \get_permalink((int) $item->ID);
        $item->target = '';
        $item->attr_title = \apply_filters('nav_menu_attr_title', '');
        $item->description = \apply_filters('nav_menu_description', '');
        $item->classes = [];
        $item->xfn = '';
    }

    /** A term, as a would-be item for it. */
    private static function term(object $item): void
    {
        $taxonomy = \get_taxonomy((string) $item->taxonomy);
        $link = \get_term_link($item, (string) $item->taxonomy);
        $item->ID = (int) $item->term_id;
        $item->db_id = 0;
        $item->menu_item_parent = 0;
        $item->object_id = (int) $item->term_id;
        $item->post_parent = (int) ($item->parent ?? 0);
        $item->type = 'taxonomy';
        $item->object = $taxonomy ? $taxonomy->name : (string) $item->taxonomy;
        $item->type_label = $taxonomy ? $taxonomy->labels->singular_name : (string) $item->taxonomy;
        $item->title = (string) $item->name;
        $item->url = is_string($link) ? $link : '';
        $item->target = '';
        $item->attr_title = '';
        $item->description = \get_term_field('description', (int) $item->term_id, (string) $item->taxonomy);
        $item->classes = [];
        $item->xfn = '';
    }
}
