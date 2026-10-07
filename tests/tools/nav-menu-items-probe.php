<?php
/**
 * Classic menu items as the reference keeps and serves them (probe
 * nav-menu-items): a menu given a custom link with every field set, a
 * page by its page's title and by its own, a child, a category, the posts'
 * archive, a page since trashed, a draft and an item with no position;
 * each item as stored (the post's columns, its _menu_item_* meta) and as
 * wp_get_nav_menu_items sets it up (the fields walkers read); that
 * function's arguments (post_status, output) and ways of naming the menu;
 * a page and a term set up as would-be items (wp_setup_nav_menu_item, as
 * the admin's boxes use it); and a plugin on nav_menu_attr_title,
 * nav_menu_description, wp_setup_nav_menu_item and wp_get_nav_menu_items.
 * The menu, its items and the page are the probe's own and go at the end.
 * Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
wp_set_current_user(1);
$sweep = static function (): void {
    foreach (wp_get_nav_menus() as $old) {
        if ($old->name === 'Zz Items Menu') {
            wp_delete_nav_menu($old->term_id);
        }
    }
    foreach (get_posts(['post_type' => 'page', 'post_status' => ['publish', 'draft', 'private', 'trash'], 'numberposts' => -1, 'fields' => 'ids']) as $id) {
        if (in_array(get_post_field('post_title', $id, 'raw'), ['Zz Items Gone', 'Zz Items Unpublished', 'Zz Items Private'], true)) {
            wp_delete_post($id, true);
        }
    }
    foreach (get_terms(['taxonomy' => 'category', 'hide_empty' => false]) as $term) {
        if ($term->name === 'Zz Items Cat') {
            wp_delete_term($term->term_id, 'category');
        }
    }
};
$sweep();

$menuId = (int) wp_create_nav_menu('Zz Items Menu');
$gone = (int) wp_insert_post(['post_type' => 'page', 'post_title' => 'Zz Items Gone', 'post_status' => 'publish']);
$unpublished = (int) wp_insert_post(['post_type' => 'page', 'post_title' => 'Zz Items Unpublished', 'post_status' => 'draft']);
$private = (int) wp_insert_post(['post_type' => 'page', 'post_title' => 'Zz Items Private', 'post_status' => 'private']);
$cat = wp_insert_term('Zz Items Cat', 'category', ['description' => 'Zz cat about', 'parent' => 1]);
$catId = is_array($cat) ? (int) $cat['term_id'] : 0;
$ids = ['menu' => $menuId, 'menutt' => (int) wp_get_nav_menu_object($menuId)->term_taxonomy_id, 'gone' => $gone, 'unpublished' => $unpublished, 'private' => $private, 'cat' => $catId];
$mask = static function ($value) use (&$ids) {
    if (is_string($value)) {
        foreach ($ids as $name => $id) {
            $value = $value === (string) $id ? '{' . $name . '}' : (string) preg_replace(['/([?&]p(?:age_id)?=)' . $id . '\b/', '#/' . $id . '/$#'], ['$1{' . $name . '}', '/{' . $name . '}/'], $value);
        }
        return $value;
    }
    foreach ($ids as $name => $id) {
        if ($value === $id) {
            return '{' . $name . '}';
        }
    }
    return $value;
};
$add = static function (string $name, array $args) use ($menuId, &$ids): void {
    $ids[$name] = (int) wp_update_nav_menu_item($menuId, 0, $args);
};
$add('link', ['menu-item-type' => 'custom', 'menu-item-title' => 'Zz Link', 'menu-item-url' => 'https://zz.example/', 'menu-item-attr-title' => 'Zz Attr', 'menu-item-description' => 'Zz described', 'menu-item-target' => '_blank', 'menu-item-classes' => 'zz-a zz-b', 'menu-item-xfn' => 'nofollow me', 'menu-item-status' => 'publish', 'menu-item-position' => 1]);
$add('page', ['menu-item-type' => 'post_type', 'menu-item-object' => 'page', 'menu-item-object-id' => 2, 'menu-item-status' => 'publish', 'menu-item-position' => 2]);
$add('titled', ['menu-item-type' => 'post_type', 'menu-item-object' => 'page', 'menu-item-object-id' => 2, 'menu-item-title' => 'Zz Own Title', 'menu-item-status' => 'publish', 'menu-item-position' => 3]);
$add('child', ['menu-item-type' => 'custom', 'menu-item-title' => 'Zz Child', 'menu-item-url' => '/zz-child/', 'menu-item-parent-id' => $ids['link'], 'menu-item-status' => 'publish', 'menu-item-position' => 4]);
$add('category', ['menu-item-type' => 'taxonomy', 'menu-item-object' => 'category', 'menu-item-object-id' => 1, 'menu-item-status' => 'publish', 'menu-item-position' => 5]);
$add('archive', ['menu-item-type' => 'post_type_archive', 'menu-item-object' => 'post', 'menu-item-status' => 'publish', 'menu-item-position' => 6]);
$add('trashed', ['menu-item-type' => 'post_type', 'menu-item-object' => 'page', 'menu-item-object-id' => $gone, 'menu-item-status' => 'publish', 'menu-item-position' => 7]);
$add('draft', ['menu-item-type' => 'custom', 'menu-item-title' => 'Zz Draft', 'menu-item-url' => 'https://zz.example/draft/', 'menu-item-status' => 'draft', 'menu-item-position' => 20]);
$add('hinted', ['menu-item-type' => 'post_type', 'menu-item-object' => 'page', 'menu-item-object-id' => 2, 'menu-item-attr-title' => 'Zz Hint', 'menu-item-status' => 'publish', 'menu-item-position' => 9]);
$add('described', ['menu-item-type' => 'post_type', 'menu-item-object' => 'page', 'menu-item-object-id' => 2, 'menu-item-description' => 'Zz about', 'menu-item-status' => 'publish', 'menu-item-position' => 10]);
$add('unpublished page', ['menu-item-type' => 'post_type', 'menu-item-object' => 'page', 'menu-item-object-id' => $unpublished, 'menu-item-status' => 'publish', 'menu-item-position' => 11]);
$add('private page', ['menu-item-type' => 'post_type', 'menu-item-object' => 'page', 'menu-item-object-id' => $private, 'menu-item-status' => 'publish', 'menu-item-position' => 11]);
$add('own category', ['menu-item-type' => 'taxonomy', 'menu-item-object' => 'category', 'menu-item-object-id' => $catId, 'menu-item-status' => 'publish', 'menu-item-position' => 12]);
$add('untitled link', ['menu-item-type' => 'custom', 'menu-item-url' => 'https://zz.example/untitled/', 'menu-item-status' => 'publish', 'menu-item-position' => 13]);
$add('unplaced', ['menu-item-type' => 'custom', 'menu-item-title' => 'Zz Unplaced', 'menu-item-url' => 'https://zz.example/unplaced/', 'menu-item-status' => 'publish']);
wp_trash_post($gone);

$stored = [];
foreach ($ids as $name => $id) {
    if (in_array($name, ['menu', 'menutt', 'gone', 'unpublished', 'private', 'cat'], true)) {
        continue;
    }
    $post = get_post($id);
    $meta = [];
    foreach ((array) get_post_meta($id) as $key => $values) {
        if (str_starts_with((string) $key, '_menu_item_')) {
            $value = get_post_meta($id, $key, true);
            $meta[$key] = is_array($value) ? $value : $mask($value);
        }
    }
    ksort($meta);
    $stored[$name] = $post ? ['title' => $post->post_title, 'excerpt' => $post->post_excerpt, 'content' => $post->post_content, 'name' => $mask($post->post_name), 'status' => $post->post_status, 'order' => $post->menu_order, 'parent' => $mask($post->post_parent), 'guid' => $mask($post->guid), 'author' => (int) $post->post_author, 'comments' => $post->comment_status, 'pings' => $post->ping_status, 'meta' => $meta] : null;
}
$say('the items as stored', $stored);

$view = static function ($item) use ($mask): array {
    $out = [];
    foreach (['ID', 'db_id', 'menu_item_parent', 'object_id', 'object', 'type', 'type_label', 'title', 'url', 'target', 'attr_title', 'description', 'classes', 'xfn', '_invalid', 'post_status', 'menu_order', 'post_type', 'post_title', 'post_excerpt', 'post_content', 'post_parent'] as $field) {
        $value = is_object($item) ? ($item->$field ?? '(unset)') : ($item[$field] ?? '(unset)');
        $out[$field] = is_array($value) ? $value : $mask($value);
    }
    return $out;
};
$items = wp_get_nav_menu_items($menuId);
$say('wp_get_nav_menu_items', array_map($view, (array) $items));
$say('its order', array_map(static fn ($item) => [$mask($item->ID), $item->menu_order], (array) $items));
$say('its keys', array_keys((array) $items));

$titles = static fn ($items) => is_array($items) ? array_map(static fn ($item) => is_object($item) ? $item->title : ($item['title'] ?? '(no title)'), $items) : $items;
$say('every status', $titles(wp_get_nav_menu_items($menuId, ['post_status' => 'any'])));
$say('drafts', $titles(wp_get_nav_menu_items($menuId, ['post_status' => 'draft'])));
$arrays = wp_get_nav_menu_items($menuId, ['output' => ARRAY_A]);
$say('output ARRAY_A', [array_keys((array) $arrays), is_array($arrays) ? gettype(reset($arrays)) : $arrays]);
$say('by order, keyed', array_keys((array) wp_get_nav_menu_items($menuId, ['output' => ARRAY_A, 'output_key' => 'menu_order'])));
$say('ordered by title', $titles(wp_get_nav_menu_items($menuId, ['orderby' => 'title', 'order' => 'DESC'])));
$objects = wp_get_nav_menu_items($menuId, ['output' => OBJECT, 'orderby' => 'title', 'order' => 'DESC']);
$say('objects by title', [array_keys((array) $objects), $titles($objects), array_map(static fn ($item) => $item->menu_order, (array) $objects)]);
$keyed = wp_get_nav_menu_items($menuId, ['output_key' => 'post_title']);
$say('keyed by title', array_map(static fn ($item) => [$mask($item->ID), $item->menu_order, $item->post_title], (array) $keyed));
$say('by slug', $titles(wp_get_nav_menu_items('zz-items-menu')));
$say('by name', $titles(wp_get_nav_menu_items('Zz Items Menu')));
$say('by term', $titles(wp_get_nav_menu_items(wp_get_nav_menu_object($menuId))));
$say('none', wp_get_nav_menu_items(999999));

$again = wp_get_nav_menu_items($menuId);
foreach ((array) $again as $item) {
    if ($item->ID === $ids['link']) {
        foreach (['title', 'url', 'type_label', 'attr_title', 'description', 'target', 'object', 'menu_item_parent', 'classes', 'xfn', 'db_id'] as $field) {
            $item->$field = $field === 'classes' ? ['zz-changed'] : 'Zz changed';
        }
        $say('an item set up again', $view(wp_setup_nav_menu_item($item)));
    }
}
$say('a page set up as an item', $view(wp_setup_nav_menu_item(get_post(2))));
$say('a term set up as an item', $view(wp_setup_nav_menu_item(get_term(1, 'category'))));
$say('a described child term set up as an item', $view(wp_setup_nav_menu_item(get_term($catId, 'category'))));
$say('a draft page set up as an item', $view(wp_setup_nav_menu_item(get_post($unpublished))));
$say('a post type set up as an item', $view(wp_setup_nav_menu_item(get_post_type_object('page'))));

$heard = [];
$filters = [
    'nav_menu_attr_title' => static function ($title) use (&$heard) {
        $heard[] = ['nav_menu_attr_title', $title];
        return $title === '' ? $title : $title . ' (zz)';
    },
    'nav_menu_description' => static function ($description) use (&$heard) {
        $heard[] = ['nav_menu_description', $description];
        return $description;
    },
    'wp_setup_nav_menu_item' => static function ($item) use (&$heard, $mask) {
        $heard[] = ['wp_setup_nav_menu_item', get_class($item), $mask($item->ID ?? $item->term_id ?? null), $item->title ?? null];
        $item->zz = 'set';
        return $item;
    },
    'wp_get_nav_menu_items' => static function ($items, $menu, $args) use (&$heard, $mask) {
        ksort($args);
        $args['tax_query'][0]['terms'] = $mask($args['tax_query'][0]['terms'] ?? null);
        $heard[] = ['wp_get_nav_menu_items', array_keys($items), array_map(static fn ($item) => $item->menu_order, $items), get_class($menu), $args];
        return $items;
    },
    'the_title' => static function ($title, $id = 0) use (&$heard, $mask) {
        $heard[] = ['the_title', $title, $mask($id)];
        return $title;
    },
];
foreach ($filters as $hook => $callback) {
    add_filter($hook, $callback, 10, ['wp_get_nav_menu_items' => 3, 'the_title' => 2][$hook] ?? 1);
}
$items = wp_get_nav_menu_items($menuId);
$heard[] = ['-- a page and a term set up'];
wp_setup_nav_menu_item(get_post(2));
wp_setup_nav_menu_item(get_term($catId, 'category'));
$say('with a plugin on them', [array_map(static fn ($item) => [$item->title, $item->attr_title, $item->zz ?? null], (array) $items), array_map(static fn ($h) => array_map($mask, $h), $heard)]);
foreach ($filters as $hook => $callback) {
    remove_filter($hook, $callback, 10);
}

$sweep();
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
