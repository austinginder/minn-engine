<?php
/**
 * The core object classes used directly (probe core-objects): a term and a
 * comment fetched, built and filtered, a comment's children, a role's
 * capabilities changed, and the category and menu walkers' four steps.
 * The probe's post, comments, terms and menu are removed; the roles option
 * is saved first and written back in place.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
global $wpdb;
$rolesOption = $wpdb->prefix . 'user_roles';
$rolesRow = $wpdb->get_row($wpdb->prepare("SELECT option_value, autoload FROM {$wpdb->options} WHERE option_name = %s", $rolesOption), ARRAY_A);
$made = ['posts' => [], 'comments' => [], 'terms' => [], 'menus' => []];
register_shutdown_function(static function () use (&$made, $rolesRow, $rolesOption, $wpdb): void {
    foreach ($made['comments'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_comment($id, true);
        }
    }
    foreach ($made['posts'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_post($id, true);
        }
    }
    foreach ($made['terms'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_term($id, 'category');
        }
    }
    foreach ($made['menus'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_nav_menu($id);
        }
    }
    if (is_array($rolesRow)) {
        $wpdb->update($wpdb->options, $rolesRow, ['option_name' => $rolesOption]);
        wp_cache_delete($rolesOption, 'options');
        wp_cache_delete('alloptions', 'options');
    }
});
$host = (string) parse_url(home_url(), PHP_URL_HOST);
$ids = [];
$mask = static function ($value) use ($host, &$ids, &$mask) {
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    if (is_int($value) && isset($ids[$value])) {
        return '{' . $ids[$value] . '}';
    }
    if (!is_string($value)) {
        return $value;
    }
    foreach ($ids as $id => $name) {
        $value = (string) preg_replace('/(?<![0-9])' . $id . '(?![0-9])/', '{' . $name . '}', $value);
    }
    return (string) preg_replace('#https?://' . preg_quote($host, '#') . '#', '//{host}', $value);
};

$parentTerm = wp_insert_term('Zz Obj Parent', 'category', ['slug' => 'zz-obj-parent', 'description' => 'Parent "desc" <b>x</b>']);
$made['terms'][] = $parent = (int) $parentTerm['term_id'];
$childTerm = wp_insert_term('Zz Obj Child & Co', 'category', ['slug' => 'zz-obj-child', 'parent' => $parent]);
$made['terms'][] = $child = (int) $childTerm['term_id'];
$ids[$parent] = 'parent';
$ids[$child] = 'child';
// The term taxonomy id follows its own counter; only that it is set is compared.
$termRow = static fn (WP_Term $term) => array_map(static fn ($value) => is_string($value) || is_int($value) ? $value : gettype($value), ['term_taxonomy_id' => (int) $term->term_taxonomy_id > 0] + $term->to_array());
$say('a term', $mask([
    $termRow(WP_Term::get_instance($child)),
    $termRow(WP_Term::get_instance($child, 'category')),
    WP_Term::get_instance($child, 'post_tag'),
    WP_Term::get_instance(0),
    WP_Term::get_instance(999999),
    WP_Term::get_instance('zz'),
    (static function () use ($child, $termRow) {
        $term = new WP_Term((object) ['term_id' => $child, 'name' => 'Built <b>"x"</b>', 'slug' => 'built', 'taxonomy' => 'category', 'description' => 'A & B', 'zz' => 'extra']);
        $out = [$termRow($term), $term->zz ?? null];
        $term->filter('display');
        $out[] = [$term->name, $term->description, $term->filter];
        $term->filter('raw');
        $out[] = [$term->name, $term->filter];
        return $out;
    })(),
    (static function () use ($child) {
        $term = WP_Term::get_instance($child);
        return [$term->data instanceof stdClass, get_object_vars($term->data) === $term->to_array(), $term->nothing ?? 'unset'];
    })(),
]));

$made['posts'][] = $post = (int) wp_insert_post(['post_title' => 'Zz Obj Post', 'post_status' => 'publish', 'post_content' => 'x']);
$ids[$post] = 'post';
$comment = static function (array $args) use ($post, &$made, &$ids) {
    $made['comments'][] = $id = (int) wp_insert_comment($args + ['comment_post_ID' => $post, 'comment_author' => 'Zz', 'comment_author_email' => 'zz@zz.example', 'comment_content' => 'x', 'comment_approved' => 1, 'comment_date' => '2020-01-01 00:00:00', 'comment_date_gmt' => '2020-01-01 00:00:00']);
    return $id;
};
$top = $comment(['comment_content' => 'Top']);
$reply = $comment(['comment_content' => 'Reply', 'comment_parent' => $top]);
$deep = $comment(['comment_content' => 'Deep', 'comment_parent' => $reply]);
$held = $comment(['comment_content' => 'Held', 'comment_parent' => $top, 'comment_approved' => 0]);
$ids += [$top => 'top', $reply => 'reply', $deep => 'deep', $held => 'held'];
$commentRow = static fn ($c) => $c instanceof WP_Comment ? [$c->comment_ID, $c->comment_content, $c->comment_parent, $c->comment_approved] : $c;
$say('a comment', $mask([
    $commentRow(WP_Comment::get_instance($top)),
    WP_Comment::get_instance(0),
    WP_Comment::get_instance(999999),
    WP_Comment::get_instance('zz'),
    array_keys(WP_Comment::get_instance($top)->to_array()),
    (static function () use ($top) {
        $c = WP_Comment::get_instance($top);
        return [isset($c->post_title), $c->post_title, isset($c->post_status), $c->post_status, isset($c->post_zz), $c->post_zz ?? 'unset', isset($c->comment_content), isset($c->zz), $c->post_type];
    })(),
    (static function () use ($top, $commentRow) {
        $c = new WP_Comment((object) ['comment_ID' => 77, 'comment_content' => 'Built', 'comment_parent' => 0, 'comment_approved' => '1', 'zz' => 'kept']);
        return [$commentRow($c), $c->zz ?? null];
    })(),
]));
$say('a comment\'s children', $mask((static function () use ($top, $reply, $deep) {
    $out = [];
    $c = WP_Comment::get_instance($top);
    $tree = static fn (array $children) => array_map(static fn (WP_Comment $kid) => [(int) $kid->comment_ID, array_keys($kid->get_children(['format' => 'flat', 'status' => 'all']))], $children);
    $out[] = array_keys($c->get_children());
    $out[] = array_keys($c->get_children(['status' => 'all']));
    $out[] = array_keys($c->get_children(['format' => 'flat']));
    $out[] = array_keys($c->get_children(['format' => 'flat', 'status' => 'all']));
    $out[] = array_values($tree($c->get_children(['format' => 'tree'])));
    $out[] = array_keys($c->get_children(['hierarchical' => false]));
    $c->populated_children(false);
    $out[] = array_keys($c->get_children());
    $fresh = WP_Comment::get_instance($reply);
    $fresh->populated_children(true);
    $out[] = [array_keys($fresh->get_children()), $fresh->get_child($deep) instanceof WP_Comment, $fresh->get_child(999999)];
    $built = new WP_Comment((object) ['comment_ID' => 77]);
    $built->add_child(WP_Comment::get_instance($deep));
    $out[] = [array_keys($built->get_children()), (int) $built->get_child($deep)->comment_ID];
    return $out;
})()));

$say('a role', (static function () {
    $role = new WP_Role('zz_obj_role', ['read' => true, 'zz_cap' => false]);
    $out = [$role->name, $role->capabilities, $role->has_cap('read'), $role->has_cap('zz_cap'), $role->has_cap('edit_posts')];
    $filter = static fn ($caps, $cap, $name) => $name === 'zz_obj_role' ? $caps + ['edit_posts' => true] : $caps;
    add_filter('role_has_cap', $filter, 10, 3);
    $out[] = $role->has_cap('edit_posts');
    remove_filter('role_has_cap', $filter, 10);
    $editor = get_role('editor');
    $out[] = [$editor->add_cap('zz_probe_cap'), $editor->has_cap('zz_probe_cap'), get_role('editor')->has_cap('zz_probe_cap'), $editor->capabilities['zz_probe_cap'] ?? null];
    $out[] = [$editor->add_cap('zz_probe_cap', false), $editor->has_cap('zz_probe_cap'), $editor->capabilities['zz_probe_cap'] ?? null];
    $out[] = [$editor->remove_cap('zz_probe_cap'), $editor->has_cap('zz_probe_cap'), array_key_exists('zz_probe_cap', $editor->capabilities)];
    return $out;
})());

$printed = static function (Closure $call): string {
    $output = '';
    $call($output);
    return $output;
};
$say('the category walker', $mask((static function () use ($parent, $child, $printed) {
    $walker = new Walker_Category();
    $term = get_term($child, 'category');
    $out = [];
    foreach ([['style' => 'list'], ['style' => 'none'], ['style' => 'list', 'show_count' => 1, 'use_desc_for_title' => 1, 'feed' => 'Feed', 'current_category' => $child], ['style' => 'list', 'current_category' => $parent, 'has_children' => true]] as $args) {
        $args += ['use_desc_for_title' => 0, 'show_count' => 0, 'feed' => '', 'feed_type' => '', 'feed_image' => '', 'current_category' => 0, 'hierarchical' => 1];
        $out[] = [
            $printed(static fn (&$o) => $walker->start_lvl($o, 1, $args)),
            $printed(static fn (&$o) => $walker->start_el($o, $term, 1, $args)),
            $printed(static fn (&$o) => $walker->end_el($o, $term, 1, $args)),
            $printed(static fn (&$o) => $walker->end_lvl($o, 1, $args)),
        ];
    }
    return $out;
})()));

$say('the menu walker', $mask((static function () use (&$made, &$ids, $printed) {
    $made['menus'][] = $menu = (int) wp_create_nav_menu('Zz Obj Menu');
    $item = wp_update_nav_menu_item($menu, 0, ['menu-item-title' => 'Item "A" <b>', 'menu-item-url' => 'https://zz.example/a', 'menu-item-status' => 'publish', 'menu-item-classes' => 'zz-one', 'menu-item-target' => '_blank', 'menu-item-xfn' => 'nofollow', 'menu-item-attr-title' => 'Tip', 'menu-item-description' => 'Desc']);
    $ids[$item] = 'item';
    $items = wp_get_nav_menu_items($menu);
    $walker = new Walker_Nav_Menu();
    $out = [];
    $current = clone $items[0];
    $current->current = true;
    $current->classes[] = 'current-menu-item';
    $out[] = $printed(static fn (&$o) => $walker->start_el($o, $current, 0, (object) ['before' => '', 'after' => '', 'link_before' => '', 'link_after' => '']));
    $plain = clone $items[0];
    $plain->target = '';
    $plain->xfn = '';
    $plain->attr_title = '';
    $out[] = $printed(static fn (&$o) => $walker->start_el($o, $plain, 0, (object) ['before' => '', 'after' => '', 'link_before' => '', 'link_after' => '']));
    $blank = clone $plain;
    $blank->target = '_blank';
    $out[] = $printed(static fn (&$o) => $walker->start_el($o, $blank, 0, (object) ['before' => '', 'after' => '', 'link_before' => '', 'link_after' => '']));
    foreach ([(object) ['before' => '<i>', 'after' => '</i>', 'link_before' => '[', 'link_after' => ']', 'item_spacing' => 'preserve'], (object) ['before' => '', 'after' => '', 'link_before' => '', 'link_after' => '', 'item_spacing' => 'discard']] as $args) {
        $out[] = [
            $printed(static fn (&$o) => $walker->start_lvl($o, 1, $args)),
            $printed(static fn (&$o) => $walker->start_el($o, $items[0], 1, $args)),
            $printed(static fn (&$o) => $walker->end_el($o, $items[0], 1, $args)),
            $printed(static fn (&$o) => $walker->end_lvl($o, 1, $args)),
        ];
    }
    return $out;
})()));

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
