<?php
/**
 * The fourth wave of the plugin catalogue's queue (probe plugin-queue4), as
 * the reference answers it: the category and page dropdowns through their
 * walkers (the default ones, a plugin's subclass, the list_cats and
 * list_pages filters, the walk_* functions called directly), whether one
 * category is another's ancestor, the user and role dropdowns, the links
 * manager (a link made, read, sanitized field by field, listed, updated,
 * given categories, deleted, with the hooks each step fires), and a menu's
 * items given their classes for the current page. Everything made is
 * removed at the end. Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = ['terms' => [], 'posts' => [], 'links' => [], 'menus' => []];
register_shutdown_function(static function () use (&$made): void {
    foreach ($made['links'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_link($id);
        }
    }
    foreach ($made['menus'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_nav_menu($id);
        }
    }
    foreach ($made['posts'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_post($id, true);
        }
    }
    foreach (array_reverse($made['terms']) as [$id, $taxonomy]) {
        if (is_int($id) && $id > 0) {
            wp_delete_term($id, $taxonomy);
        }
    }
});
set_error_handler(static fn () => true, E_USER_DEPRECATED | E_DEPRECATED | E_USER_NOTICE);
if (!function_exists('wp_insert_link')) {
    require_once ABSPATH . 'wp-admin/includes/bookmark.php';
}
if (!function_exists('wp_dropdown_roles')) {
    require_once ABSPATH . 'wp-admin/includes/template.php';
}
if (!did_action('init')) {
    do_action('init');
}
// Link ids start high, so they never read as a count, a rating or an option's value.
global $wpdb;
$wpdb->query("ALTER TABLE {$wpdb->links} AUTO_INCREMENT = 900001");
$ids = [];
$mask = static function ($value) use (&$mask, &$ids) {
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    if (is_object($value)) {
        return $mask(get_object_vars($value));
    }
    if (is_int($value) && isset($ids[$value])) {
        return '{' . $ids[$value] . '}';
    }
    if (!is_string($value)) {
        return $value;
    }
    $value = str_replace([home_url(), site_url()], ['{home}', '{site}'], $value);
    if (preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/', $value) && $value !== '0000-00-00 00:00:00') {
        $at = strtotime($value . ' UTC');
        return abs($at - (int) current_time('timestamp')) < 300 ? '{now, local}' : (abs($at - time()) < 300 ? '{now, gmt}' : $value);
    }
    foreach ($ids as $id => $label) {
        $value = (string) preg_replace('/(?<![0-9a-z])' . $id . '(?![0-9a-z])/i', '{' . $label . '}', $value);
    }
    return $value;
};
$term = static function (string $name, string $taxonomy, int $parent = 0) use (&$made, &$ids): int {
    $result = wp_insert_term($name, $taxonomy, ['parent' => $parent, 'slug' => sanitize_title($name)]);
    $id = is_array($result) ? (int) $result['term_id'] : 0;
    if ($id > 0) {
        $made['terms'][] = [$id, $taxonomy];
        $ids[$id] = sanitize_title($name);
    }
    return $id;
};
$heard = [];
$listen = static function (...$args) use (&$heard) {
    $hook = (string) ($args[0] ?? '');
    if (preg_match('/link|bookmark|list_cats|list_pages/', $hook) && !in_array($hook, $heard, true)) {
        $heard[] = $hook;
    }
    return $args[0] ?? null;
};
$hearing = static function (callable $run) use (&$heard, $listen) {
    $heard = [];
    add_filter('all', $listen);
    $result = $run();
    remove_filter('all', $listen);
    return [$result, $heard];
};

// Categories, nested three deep, and one beside them.
$top = $term('ZZ Top', 'category');
$middle = $term('ZZ Middle', 'category', $top);
$bottom = $term('ZZ Bottom', 'category', $middle);
$beside = $term('ZZ Beside & Co', 'category');
$ours = implode(',', [$top, $middle, $bottom, $beside]);
$dropdown = static fn (array $args) => $mask(wp_dropdown_categories($args + ['echo' => 0, 'hide_empty' => 0, 'include' => $ours, 'orderby' => 'name']));

$say('the dropdown, flat', $dropdown([]));
$say('the dropdown, nested with counts', $dropdown(['hierarchical' => 1, 'show_count' => 1, 'selected' => $middle]));
$say('the dropdown, two levels', $dropdown(['hierarchical' => 1, 'depth' => 2]));
$say('the dropdown by slug', $dropdown(['value_field' => 'slug', 'selected' => 'zz-bottom', 'show_option_all' => 'All', 'show_option_none' => 'None']));
$say('the dropdown by name', $dropdown(['value_field' => 'name', 'selected' => 'ZZ Beside & Co']));
$say('the dropdown by an unknown field', $dropdown(['value_field' => 'nope']));
$suffix = static fn ($name, $category = null) => $name . (is_object($category) ? ' [' . $category->slug . ']' : ' [none]');
add_filter('list_cats', $suffix, 10, 2);
$say('the dropdown, list_cats filtered', $dropdown(['hierarchical' => 1, 'show_option_all' => 'All', 'show_option_none' => 'None']));
remove_filter('list_cats', $suffix, 10);
$marked = static fn ($name) => '<b>' . $name . '</b> & "q"';
add_filter('list_cats', $marked);
$say('the dropdown, list_cats returning markup', $dropdown(['include' => (string) $beside, 'show_option_all' => 'All']));
remove_filter('list_cats', $marked);

class ZZ_Queue4_Category_Walker extends Walker_CategoryDropdown
{
    public function start_el(&$output, $data_object, $depth = 0, $args = [], $current_object_id = 0)
    {
        $before = strlen($output);
        parent::start_el($output, $data_object, $depth, $args, $current_object_id);
        $output = substr($output, 0, $before) . '<!-- d' . $depth . ' -->' . substr($output, $before);
    }
}
$say('the dropdown with a plugin\'s walker', $dropdown(['hierarchical' => 1, 'walker' => new ZZ_Queue4_Category_Walker()]));
$say('Walker_CategoryDropdown', [(new Walker_CategoryDropdown())->tree_type, (new Walker_CategoryDropdown())->db_fields]);
$categories = get_terms(['taxonomy' => 'category', 'include' => $ours, 'hide_empty' => false, 'orderby' => 'name']);
$say('walk_category_dropdown_tree', $mask(walk_category_dropdown_tree($categories, 0, ['selected' => $bottom, 'show_count' => 1, 'value_field' => 'term_id'])));
$say('walk_category_dropdown_tree, a plugin\'s walker', $mask(walk_category_dropdown_tree($categories, 1, ['walker' => new ZZ_Queue4_Category_Walker()])));
$say('walk_category_dropdown_tree, no args', $mask(walk_category_dropdown_tree($categories, 0)));
$say('walk_category_tree', $mask(walk_category_tree($categories, 0, ['style' => 'list', 'use_desc_for_title' => 0, 'current_category' => $middle])));
$say('walk_category_tree, plain', $mask(walk_category_tree($categories, -1, ['style' => 'none', 'separator' => ' | '])));
$walker = new Walker_CategoryDropdown();
$say('the walker walked by hand', $mask($walker->walk($categories, 0, ['selected' => 'zz-top', 'value_field' => 'slug'])));
$say('cat_is_ancestor_of', $mask([
    cat_is_ancestor_of($top, $bottom), cat_is_ancestor_of($top, $middle), cat_is_ancestor_of($bottom, $top), cat_is_ancestor_of($top, $top),
    cat_is_ancestor_of(get_term($top), get_term($bottom)), cat_is_ancestor_of($beside, $bottom), cat_is_ancestor_of(0, $bottom), cat_is_ancestor_of($top, 999999999),
]));

// The category list, through Walker_Category.
wp_update_term($middle, 'category', ['description' => 'The <em>middle</em> one & "more"']);
$cats = static fn (array $args) => $mask(wp_list_categories($args + ['echo' => 0, 'hide_empty' => 0, 'include' => $ours]));
$say('wp_list_categories', $cats([]));
$say('wp_list_categories, counted, described, fed, current', $cats(['show_count' => 1, 'use_desc_for_title' => 1, 'feed' => 'Feed', 'current_category' => $middle, 'class' => 'zz-cats']));
$say('wp_list_categories, a feed image', $mask(wp_list_categories(['echo' => 0, 'hide_empty' => 0, 'include' => (string) $beside, 'title_li' => '', 'feed_image' => 'https://img.example.com/f.png'])));
$say('wp_list_categories, a feed image and text, atom, titled', $mask(wp_list_categories(['echo' => 0, 'hide_empty' => 0, 'include' => (string) $beside, 'title_li' => '', 'feed_image' => 'https://img.example.com/f.png', 'feed' => 'Atom feed', 'feed_type' => 'atom', 'title' => ' data-zz="t"'])));
$say('wp_list_categories, a feed, flat', $mask(wp_list_categories(['echo' => 0, 'hide_empty' => 0, 'include' => (string) $beside, 'title_li' => '', 'feed' => 'Feed', 'style' => 'none'])));
$say('wp_list_categories, flat with a separator', $cats(['style' => 'none', 'separator' => ', ', 'hierarchical' => 0, 'title_li' => 'Cats']));
$say('wp_list_categories, flat with the default separator', $cats(['style' => 'none', 'title_li' => '']));
$say('wp_list_categories, one level', $cats(['depth' => 1, 'title_li' => '']));
$say('wp_list_categories, all and none offered', $cats(['title_li' => '', 'show_option_all' => 'All of them', 'show_option_none' => 'None at all']));
$say('wp_list_categories, nothing', [$cats(['include' => '999999999']), $cats(['include' => '999999999', 'style' => 'none', 'show_option_none' => 'Nothing']), $cats(['include' => '999999999', 'hide_title_if_empty' => true])]);
$say('wp_list_categories, a tree left out', $mask(wp_list_categories(['echo' => 0, 'hide_empty' => 0, 'title_li' => '', 'exclude_tree' => (string) $top, 'search' => 'ZZ'])));
$say('wp_list_categories of no taxonomy', wp_list_categories(['echo' => 0, 'taxonomy' => 'zz_nope']));
$catClass = static fn ($classes, $category, $depth, $args) => array_merge($classes, ['zz-d' . $depth, 'zz-' . $category->slug]);
$catAttrs = static fn ($atts, $category, $depth, $args, $current) => $atts + ['data-zz' => implode(',', array_keys($atts)) . '/' . $depth . '/' . (int) $current];
add_filter('list_cats', $suffix, 10, 2);
add_filter('category_css_class', $catClass, 10, 4);
add_filter('category_list_link_attributes', $catAttrs, 10, 5);
$say('wp_list_categories, filtered', $cats(['title_li' => '', 'current_category' => $bottom, 'use_desc_for_title' => 1]));
remove_filter('list_cats', $suffix, 10);
remove_filter('category_css_class', $catClass, 10);
remove_filter('category_list_link_attributes', $catAttrs, 10);
$catsSeen = null;
$catsFilter = static function ($output, $args) use (&$catsSeen) {
    $catsSeen = [$args['depth'] ?? null, $args['class'] ?? null, isset($args['walker']), $args['hierarchical'] ?? null, $args['pad_counts'] ?? null];
    return $output;
};
add_filter('wp_list_categories', $catsFilter, 10, 2);
$cats(['show_count' => 1]);
remove_filter('wp_list_categories', $catsFilter, 10);
$say('wp_list_categories filter arguments', $catsSeen);
class ZZ_Queue4_List_Category_Walker extends Walker_Category
{
    public function start_el(&$output, $data_object, $depth = 0, $args = [], $current_object_id = 0)
    {
        $output .= '<li data-zz="' . $depth . '">' . esc_html($data_object->name);
    }
}
$say('wp_list_categories with a plugin\'s walker', $cats(['title_li' => '', 'walker' => new ZZ_Queue4_List_Category_Walker()]));
$catMain = $GLOBALS['wp_query'] ?? null;
$catMainThe = $GLOBALS['wp_the_query'] ?? null;
$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'] = new WP_Query(['cat' => $bottom]);
$say('wp_list_categories on a category', $cats(['title_li' => '']));
$GLOBALS['wp_query'] = $catMain;
$GLOBALS['wp_the_query'] = $catMainThe;
ob_start();
$catsEchoed = wp_list_categories(['hide_empty' => 0, 'include' => (string) $beside, 'title_li' => '']);
$say('wp_list_categories, echoed', [$mask(ob_get_clean()), $catsEchoed]);

// Pages.
$page = static function (string $title, int $parent = 0) use (&$made, &$ids): int {
    $id = wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title, 'post_parent' => $parent, 'post_name' => sanitize_title($title)]);
    $id = is_int($id) ? $id : 0;
    if ($id > 0) {
        $made['posts'][] = $id;
        $ids[$id] = sanitize_title($title);
    }
    return $id;
};
$pageTop = $page('ZZ Page Top');
$pageChild = $page('ZZ Page Child', $pageTop);
$pageGrand = $page('ZZ Page "Grand"', $pageChild);
$pageIds = implode(',', [$pageTop, $pageChild, $pageGrand]);
$pages = get_pages(['include' => $pageIds, 'sort_column' => 'post_title']);
$say('walk_page_dropdown_tree', $mask(walk_page_dropdown_tree($pages, 0, ['selected' => $pageChild])));
$say('walk_page_dropdown_tree by name', $mask(walk_page_dropdown_tree($pages, 2, ['selected' => 'zz-page-top', 'value_field' => 'post_name'])));
$say('walk_page_dropdown_tree, no args', $mask(walk_page_dropdown_tree($pages, 0)));
$pageSuffix = static fn ($title, $page = null) => $title . (is_object($page) ? ' (' . $page->post_name . ')' : '');
add_filter('list_pages', $pageSuffix, 10, 2);
$say('wp_dropdown_pages, list_pages filtered', $mask(wp_dropdown_pages(['echo' => 0, 'include' => $pageIds, 'selected' => $pageGrand, 'show_option_none' => 'None'])));
remove_filter('list_pages', $pageSuffix, 10);
$pageMarked = static fn ($title) => '<i>' . $title . '</i> & more';
add_filter('list_pages', $pageMarked);
$say('walk_page_dropdown_tree, list_pages returning markup', $mask(walk_page_dropdown_tree($pages, 1, [])));
remove_filter('list_pages', $pageMarked);
$say('walk_page_dropdown_tree by name, chosen by id', $mask(walk_page_dropdown_tree($pages, 1, ['selected' => $pageTop, 'value_field' => 'post_name'])));
$say('walk_page_dropdown_tree by an unknown field', $mask(walk_page_dropdown_tree($pages, 1, ['value_field' => 'nope', 'selected' => (string) $pageTop])));
$untitled = $page('');
$say('walk_page_dropdown_tree, an untitled page', $mask(walk_page_dropdown_tree(get_pages(['include' => (string) $untitled]), 0, [])));
$say('wp_dropdown_pages, nothing', [wp_dropdown_pages(['echo' => 0, 'include' => '999999999']), wp_dropdown_pages(['echo' => 0, 'include' => '999999999', 'show_option_none' => 'None'])]);

class ZZ_Queue4_Page_Walker extends Walker_PageDropdown
{
    public function start_el(&$output, $data_object, $depth = 0, $args = [], $current_object_id = 0)
    {
        $output .= '<!-- p' . $depth . ' -->';
        parent::start_el($output, $data_object, $depth, $args, $current_object_id);
    }
}
$say('wp_dropdown_pages with a plugin\'s walker', $mask(wp_dropdown_pages(['echo' => 0, 'include' => $pageIds, 'walker' => new ZZ_Queue4_Page_Walker(), 'show_option_no_change' => 'No change'])));
$say('Walker_PageDropdown', [(new Walker_PageDropdown())->tree_type, (new Walker_PageDropdown())->db_fields]);

class ZZ_Queue4_List_Walker extends Walker_Page
{
    public function start_el(&$output, $data_object, $depth = 0, $args = [], $current_object_id = 0)
    {
        $output .= '<li data-zz="' . $depth . '">' . esc_html($data_object->post_title);
    }
}
$listPages = static fn (array $args) => $mask(wp_list_pages($args + ['echo' => 0, 'include' => $pageIds]));
$say('wp_list_pages', $listPages([]));
$say('wp_list_pages, no title, discarded spacing', $listPages(['title_li' => '', 'item_spacing' => 'discard', 'link_before' => '<span>', 'link_after' => '</span>']));
$say('wp_list_pages, flat', $listPages(['depth' => -1, 'title_li' => '']));
$say('wp_list_pages, one level', $listPages(['depth' => 1, 'title_li' => 'Some <b>pages</b>']));
$say('wp_list_pages, under the top', $mask(wp_list_pages(['echo' => 0, 'child_of' => $pageTop, 'title_li' => ''])));
$say('wp_list_pages, dated', (string) preg_replace('/\d{4}-\d\d-\d\d|[A-Z][a-z]+ \d{1,2}, \d{4}/', '{date}', $listPages(['show_date' => 'created', 'date_format' => 'Y-m-d', 'title_li' => '', 'depth' => 1])));
$cssClass = static fn ($classes, $page, $depth, $args, $current) => array_merge($classes, ['zz-d' . $depth, 'zz-c' . (int) $current, 'zz-' . (isset($args['pages_with_children']) ? 'pwc' : 'none')]);
$linkAttrs = static fn ($atts, $page, $depth, $args, $current) => $atts + ['data-zz' => implode(',', array_keys($atts))];
add_filter('page_css_class', $cssClass, 10, 5);
add_filter('page_menu_link_attributes', $linkAttrs, 10, 5);
$say('wp_list_pages, filtered classes and attributes', $listPages(['title_li' => '']));
remove_filter('page_css_class', $cssClass, 10);
remove_filter('page_menu_link_attributes', $linkAttrs, 10);
$listFilter = static function ($output, $args, $pages) use (&$listSeen) {
    $listSeen = [count($pages), isset($args['walker']), isset($args['pages_with_children'])];
    return $output;
};
$listSeen = null;
add_filter('wp_list_pages', $listFilter, 10, 3);
$listPages(['title_li' => '']);
remove_filter('wp_list_pages', $listFilter, 10);
$say('wp_list_pages filter arguments', $listSeen);
$pageMain = $GLOBALS['wp_query'] ?? null;
$pageMainThe = $GLOBALS['wp_the_query'] ?? null;
foreach (['child' => $pageChild, 'grandchild' => $pageGrand] as $which => $current) {
    $GLOBALS['wp_the_query'] = $GLOBALS['wp_query'] = new WP_Query(['page_id' => $current]);
    $say("wp_list_pages on the {$which} page", $listPages(['title_li' => '']));
    $say("wp_page_menu on the {$which} page", $mask(wp_page_menu(['echo' => false, 'include' => $pageIds, 'show_home' => 'Start', 'menu_class' => 'zz-menu', 'link_before' => '<em>', 'link_after' => '</em>'])));
}
$GLOBALS['wp_query'] = $pageMain;
$GLOBALS['wp_the_query'] = $pageMainThe;
$say('wp_page_menu', $mask(wp_page_menu(['echo' => false, 'include' => $pageIds, 'container' => 'nav', 'menu_id' => 'zz-nav', 'before' => '<ul class="zz">', 'after' => '</ul>', 'item_spacing' => 'preserve'])));
$say('wp_page_menu without a container', $mask([wp_page_menu(['echo' => false, 'include' => (string) $pageTop, 'container' => '', 'depth' => 1]), wp_page_menu(['echo' => false, 'include' => (string) $pageTop, 'container' => 'ul', 'depth' => 1, 'fallback_cb' => 'wp_page_menu']), wp_page_menu(['echo' => false, 'include' => (string) $pageTop, 'depth' => 1, 'fallback_cb' => 'wp_page_menu', 'show_home' => true])]));
$postsPage = get_option('page_for_posts');
update_option('page_for_posts', $pageTop);
$say('wp_list_pages with the top as the posts page', $listPages(['title_li' => '', 'depth' => 1]));
update_option('page_for_posts', $postsPage);
$say('wp_page_menu, nothing', $mask(wp_page_menu(['echo' => false, 'include' => '999999999'])));
$say('wp_list_pages with a plugin\'s walker', $mask(wp_list_pages(['echo' => 0, 'include' => $pageIds, 'title_li' => '', 'walker' => new ZZ_Queue4_List_Walker()])));

// Users and roles.
$users = static fn (array $args) => $mask(wp_dropdown_users($args + ['echo' => 0]));
$say('wp_dropdown_users', $users([]));
$say('wp_dropdown_users, chosen and named', $users(['selected' => 2, 'name' => 'zz_author', 'id' => 'zz-author', 'class' => 'zz', 'show_option_all' => 'Everyone', 'show_option_none' => 'Nobody', 'option_none_value' => '0']));
$say('wp_dropdown_users, shown by login', $users(['show' => 'user_login', 'include' => [1, 3]]));
$say('wp_dropdown_users, display name with login', $users(['show' => 'display_name_with_login', 'exclude' => [1]]));
$say('wp_dropdown_users, multi', $users(['multi' => 1, 'include_selected' => true, 'selected' => 3, 'include' => [2]]));
$say('wp_dropdown_users, nobody', $users(['include' => [999999999]]));
$say('wp_dropdown_users, nobody, hidden when empty', $users(['include' => [999999999], 'hide_if_only_one_author' => true]));
$say('wp_dropdown_users, one author, hidden', $users(['include' => [2], 'hide_if_only_one_author' => true]));
$filtered = static fn ($html) => $html . '<!-- zz -->';
add_filter('wp_dropdown_users', $filtered);
$say('wp_dropdown_users, filtered', $users(['include' => [2]]));
remove_filter('wp_dropdown_users', $filtered);
$say('wp_dropdown_users_args', (function () use ($users) {
    $seen = [];
    $spy = static function ($query, $args) use (&$seen) {
        $seen = [array_keys($query), $args['show'] ?? null];
        return $query;
    };
    add_filter('wp_dropdown_users_args', $spy, 10, 2);
    $users(['include' => [2]]);
    remove_filter('wp_dropdown_users_args', $spy, 10);
    return $seen;
})());
foreach (['', 'editor', 'nope'] as $role) {
    ob_start();
    $returned = wp_dropdown_roles($role);
    $say("wp_dropdown_roles '{$role}'", [ob_get_clean(), $returned]);
}

// The links manager. First the chains each link field runs through, as registered.
$chains = [];
foreach (['link_id', 'link_url', 'link_name', 'link_image', 'link_target', 'link_description', 'link_visible', 'link_owner', 'link_rating', 'link_updated', 'link_rel', 'link_notes', 'link_rss', 'link_category'] as $field) {
    foreach (["pre_{$field}", $field, "edit_{$field}"] as $hook) {
        foreach ($GLOBALS['wp_filter'][$hook]->callbacks ?? [] as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                $chains[$hook][] = $priority . ' ' . (is_string($callback['function']) ? $callback['function'] : 'closure') . ' ' . $callback['accepted_args'];
            }
        }
    }
}
$say('the link field chains', $chains);
$say('the link category taxonomy', [taxonomy_exists('link_category'), get_taxonomy('link_category')->object_type ?? null, (bool) get_option('link_manager_enabled')]);
$friends = $term('ZZ Friends', 'link_category');
$tools = $term('ZZ Tools', 'link_category');
$link = static function (array $data) use (&$made, &$ids): int {
    $id = wp_insert_link($data);
    $id = is_int($id) ? $id : 0;
    if ($id > 0) {
        $made['links'][] = $id;
        $ids[$id] = 'link ' . sanitize_title($data['link_name'] ?? '');
    }
    return $id;
};
[$one, $oneHeard] = $hearing(static fn () => $link(['link_name' => 'ZZ One', 'link_url' => 'https://one.example.com/', 'link_description' => 'The first <b>one</b>', 'link_category' => [$friends], 'link_rating' => 7, 'link_notes' => "Note & more\nlines"]));
$say('wp_insert_link', [$mask($one), $oneHeard]);
$two = $link(['link_name' => 'ZZ Two', 'link_url' => 'two.example.com', 'link_target' => '_blank', 'link_rel' => 'friend met', 'link_category' => [$friends, $tools], 'link_image' => 'https://two.example.com/i.png']);
$hidden = $link(['link_name' => 'ZZ Hidden', 'link_url' => 'https://hidden.example.com/', 'link_visible' => 'N', 'link_category' => [$tools]]);
$plain = $link(['link_name' => 'ZZ Plain', 'link_url' => 'https://plain.example.com/']);
$try = static function (callable $run) {
    try {
        return $run();
    } catch (Throwable $e) {
        return ['threw' => get_class($e)];
    }
};
$say('wp_insert_link without a url', [$mask(wp_insert_link(['link_name' => 'ZZ None'])), $mask(wp_insert_link(['link_name' => 'ZZ None'], true))]);
$say('wp_insert_link without a name', $mask((static function () use ($link) {
    return $link(['link_url' => 'https://nameless.example.com/']);
})()));
$fields = ['link_id', 'link_url', 'link_name', 'link_image', 'link_target', 'link_description', 'link_visible', 'link_owner', 'link_rating', 'link_rel', 'link_notes', 'link_rss', 'link_category'];
$say('get_bookmark', $mask(get_bookmark($one)));
$say('get_bookmark as arrays', $mask([get_bookmark($two, ARRAY_A), get_bookmark($two, ARRAY_N)]));
$say('get_bookmark, display', $mask(get_bookmark($one, OBJECT, 'display')));
$say('get_bookmark, edit', $mask(get_bookmark($one, OBJECT, 'edit')));
$say('get_bookmark of an object', $mask(get_bookmark(get_bookmark($plain))));
$say('get_bookmark of nothing', $try(static fn () => [get_bookmark(999999999), get_bookmark(0)]));
$say('get_bookmark_field', $mask([get_bookmark_field('link_name', $one), get_bookmark_field('link_url', $two, 'display'), get_bookmark_field('nope', $one), get_bookmark_field('link_name', 999999999)]));
foreach (['raw', 'edit', 'db', 'display', 'attribute', 'js'] as $context) {
    $values = [];
    foreach (['link_id' => '12abc', 'link_url' => " https://x.example.com/a b?c=<d>&e ", 'link_name' => 'A <b>"bold"</b> & \'more\'', 'link_target' => '_parent', 'link_visible' => 'maybe', 'link_rating' => '3.9', 'link_notes' => "a <i>note</i>\nnext", 'link_category' => ['3', 'x', -4], 'link_rel' => 'me   friend', 'link_image' => 'javascript:alert(1)', 'link_rss' => 'feed.example.com', 'link_owner' => '2x'] as $field => $raw) {
        [$value, $hooks] = $hearing(static fn () => sanitize_bookmark_field($field, $raw, 5, $context));
        $values[$field] = [$value, $hooks];
    }
    $say("sanitize_bookmark_field {$context}", $values);
}
$say('sanitize_bookmark_field, targets', array_map(static fn ($t) => sanitize_bookmark_field('link_target', $t, 1, 'raw'), ['_blank', '_top', '_self', '_parent', '', 'x']));
$say('sanitize_bookmark_field, visible', array_map(static fn ($v) => sanitize_bookmark_field('link_visible', $v, 1, 'raw'), ['Y', 'N', 'y', '', 'x']));
$say('sanitize_bookmark', $mask([sanitize_bookmark(get_bookmark($one, ARRAY_A), 'display'), sanitize_bookmark((object) ['link_id' => 9, 'link_name' => '<x>'], 'attribute')]));
$bookmarks = static fn (array $args) => $mask(array_map(static fn ($l) => [$l->link_name, $l->link_url, $l->link_visible, $l->link_category ?? null], get_bookmarks($args)));
$say('get_bookmarks', $bookmarks([]));
$say('get_bookmarks, hidden too', $bookmarks(['hide_invisible' => 0]));
$say('get_bookmarks, one category', $bookmarks(['category' => (string) $tools, 'hide_invisible' => 0]));
$say('get_bookmarks, two categories', $bookmarks(['category' => $friends . ',' . $tools]));
$say('get_bookmarks, by category name', $bookmarks(['category_name' => 'ZZ Tools', 'hide_invisible' => 0]));
$say('get_bookmarks, by an unknown category name', $bookmarks(['category_name' => 'ZZ Nope']));
$say('get_bookmarks, included', $bookmarks(['include' => $two . ',' . $plain]));
$say('get_bookmarks, excluded', $bookmarks(['exclude' => (string) $one]));
$say('get_bookmarks, searched', $bookmarks(['search' => 'TWO.example']));
$say('get_bookmarks, by rating, descending', $bookmarks(['orderby' => 'rating', 'order' => 'DESC']));
$say('get_bookmarks, by url', $bookmarks(['orderby' => 'url']));
$say('get_bookmarks, by length', $bookmarks(['orderby' => 'length']));
$say('get_bookmarks, by id, limited', $bookmarks(['orderby' => 'id', 'limit' => 2]));
$say('get_bookmarks, by owner then name', $bookmarks(['orderby' => 'owner,name']));
$say('get_bookmarks, an unknown order', $bookmarks(['orderby' => 'nope', 'order' => 'sideways']));
$say('get_bookmarks, updated', array_map(static fn ($l) => [isset($l->recently_updated), isset($l->link_updated_f)], get_bookmarks(['show_updated' => 1, 'include' => (string) $one])));
$say('get_bookmarks, filtered', (function () use ($bookmarks) {
    $seen = null;
    $spy = static function ($links, $args) use (&$seen) {
        $seen = [count($links), array_keys($args)];
        return array_slice($links, 0, 1);
    };
    add_filter('get_bookmarks', $spy, 10, 2);
    $out = $bookmarks([]);
    remove_filter('get_bookmarks', $spy, 10);
    return [$out, $seen];
})());
$say('wp_get_link_cats', $mask([wp_get_link_cats($one), wp_get_link_cats($two), wp_get_link_cats($plain), wp_get_link_cats(999999999)]));
wp_set_link_cats($plain, [$tools]);
$say('wp_set_link_cats', $mask(wp_get_link_cats($plain)));
wp_set_link_cats($plain, []);
$say('wp_set_link_cats, none', $mask([wp_get_link_cats($plain), get_option('default_link_category')]));
[$updated, $updateHeard] = $hearing(static fn () => wp_update_link(['link_id' => $one, 'link_name' => 'ZZ One Renamed', 'link_category' => [$tools]]));
$say('wp_update_link', [$mask($updated), $updateHeard, $mask(get_bookmark($one, ARRAY_A))]);
$say('wp_update_link, url kept', $mask([wp_update_link(['link_id' => $two, 'link_url' => '']), get_bookmark_field('link_url', $two), wp_get_link_cats($two)]));
// The reference dies on wp_update_link for a link that is not there (array_merge of null), so the probe never asks.
// So does wp_update_link with no id at all.
$list = static fn (array $args) => $mask(wp_list_bookmarks($args + ['echo' => 0]));
$say('wp_list_bookmarks', $list([]));
$say('wp_list_bookmarks, one category', $list(['category' => (string) $friends, 'show_description' => 1, 'show_rating' => 1, 'show_name' => 1, 'show_images' => 1, 'between' => ' — ']));
$say('wp_list_bookmarks, flat', $list(['categorize' => 0, 'title_li' => 'ZZ Links', 'class' => 'zz-links', 'before' => '<li class="zz">', 'after' => '</li>']));
$say('wp_list_bookmarks, flat, no title', $list(['categorize' => 0, 'title_li' => '', 'show_images' => 0, 'show_updated' => 0]));
$say('wp_list_bookmarks, ordered categories', $list(['category_orderby' => 'name', 'category_order' => 'DESC', 'title_before' => '<h3>', 'title_after' => '</h3>', 'category_before' => '<li id="%id" class="%class zz">']));
$say('wp_list_bookmarks, nothing', $list(['category' => '999999999']));
ob_start();
$echoed = wp_list_bookmarks(['category' => (string) $tools]);
$say('wp_list_bookmarks, echoed', [$mask(ob_get_clean()), $echoed]);
$say('_walk_bookmarks', $mask(_walk_bookmarks(get_bookmarks(['hide_invisible' => 0, 'category' => (string) $tools]), ['show_description' => 1, 'show_rating' => 1, 'show_updated' => 0, 'show_images' => 0, 'link_before' => '<span>', 'link_after' => '</span>'])));
$say('_walk_bookmarks, defaults', $mask(_walk_bookmarks(get_bookmarks(['include' => (string) $two]))));
$say('clean_bookmark_cache', [clean_bookmark_cache($one)]);
[$deleted, $deleteHeard] = $hearing(static fn () => wp_delete_link($hidden));
$say('wp_delete_link', [$deleted, $deleteHeard, get_bookmark($hidden), $mask(wp_get_link_cats($hidden))]);
$say('wp_delete_link of nothing', $try(static fn () => wp_delete_link(999999999)));
$say('the link category counts', $mask(array_map(static fn ($id) => (int) get_term($id, 'link_category')->count, [$friends, $tools])));

// Menu items. A request host, so the reference weighs custom items as a web request would; a path neither stack's items point at.
$_SERVER['HTTP_HOST'] = (string) parse_url(home_url(), PHP_URL_HOST);
$_SERVER['REQUEST_URI'] = '/zz-nowhere/';
if (parse_url(home_url(), PHP_URL_SCHEME) === 'https') {
    $_SERVER['HTTPS'] = 'on';
}
$menu = wp_create_nav_menu('ZZ Queue Four Menu');
$menu = is_int($menu) ? $menu : 0;
$made['menus'][] = $menu;
$ids[$menu] = 'menu';
$item = static function (array $fields) use ($menu, &$ids): int {
    $id = wp_update_nav_menu_item($menu, 0, $fields + ['menu-item-status' => 'publish']);
    $id = is_int($id) ? $id : 0;
    if ($id > 0) {
        $ids[$id] = 'item ' . sanitize_title($fields['menu-item-title'] ?? ($fields['menu-item-object'] ?? 'x'));
    }
    return $id;
};
$homeItem = $item(['menu-item-title' => 'Home', 'menu-item-url' => home_url('/'), 'menu-item-type' => 'custom']);
$topItem = $item(['menu-item-type' => 'post_type', 'menu-item-object' => 'page', 'menu-item-object-id' => $pageTop, 'menu-item-title' => 'Top']);
$childItem = $item(['menu-item-type' => 'post_type', 'menu-item-object' => 'page', 'menu-item-object-id' => $pageChild, 'menu-item-parent-id' => $topItem, 'menu-item-title' => 'Child']);
$catItem = $item(['menu-item-type' => 'taxonomy', 'menu-item-object' => 'category', 'menu-item-object-id' => $middle, 'menu-item-title' => 'Middle']);
$outside = $item(['menu-item-title' => 'Outside', 'menu-item-url' => 'https://outside.example.com/', 'menu-item-type' => 'custom', 'menu-item-classes' => 'zz-out extra']);
$say('is_nav_menu_item', $mask([is_nav_menu_item($topItem), is_nav_menu_item($pageTop), is_nav_menu_item(0), is_nav_menu_item(999999999), is_nav_menu_item(new WP_Error('x'))]));
$classesOf = static function () use ($menu, $mask) {
    $items = wp_get_nav_menu_items($menu);
    $returned = _wp_menu_item_classes_by_context($items);
    return [$returned, $mask(array_map(static fn ($i) => [$i->title, array_values(array_filter((array) $i->classes)), (bool) ($i->current ?? false), (bool) ($i->current_item_parent ?? false), (bool) ($i->current_item_ancestor ?? false)], $items))];
};
$main = $GLOBALS['wp_query'] ?? null;
$mainThe = $GLOBALS['wp_the_query'] ?? null;
$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'] = new WP_Query(['page_id' => $pageChild]);
$say('_wp_menu_item_classes_by_context on the child page', $classesOf());
$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'] = new WP_Query(['page_id' => $pageGrand]);
$say('_wp_menu_item_classes_by_context on the grandchild page', $classesOf());
$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'] = new WP_Query(['cat' => $bottom]);
$say('_wp_menu_item_classes_by_context on a category below', $classesOf());
$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'] = new WP_Query(['cat' => $middle]);
$say('_wp_menu_item_classes_by_context on the category', $classesOf());
$bottomItem = $item(['menu-item-type' => 'taxonomy', 'menu-item-object' => 'category', 'menu-item-object-id' => $bottom, 'menu-item-parent-id' => $catItem, 'menu-item-title' => 'Bottom']);
$post = wp_insert_post(['post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'ZZ Queue Post', 'post_category' => [$bottom]]);
$post = is_int($post) ? $post : 0;
if ($post > 0) {
    $made['posts'][] = $post;
    $ids[$post] = 'post';
}
$tag = $term('ZZ Queue Tag', 'post_tag');
wp_set_post_tags($post, [$tag]);
$tagItem = $item(['menu-item-type' => 'taxonomy', 'menu-item-object' => 'post_tag', 'menu-item-object-id' => $tag, 'menu-item-title' => 'Tag']);
$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'] = new WP_Query(['p' => $post]);
$say('_wp_menu_item_classes_by_context on a post in the bottom category', $classesOf());
$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'] = new WP_Query(['tag_id' => $tag]);
$say('_wp_menu_item_classes_by_context on the tag', $classesOf());
$postsPage = get_option('page_for_posts');
update_option('page_for_posts', $pageTop);
foreach (['a post' => ['p' => $post], 'the category' => ['cat' => $middle], 'the child page' => ['page_id' => $pageChild], 'the posts page' => ['page_id' => $pageTop]] as $where => $query) {
    $GLOBALS['wp_the_query'] = $GLOBALS['wp_query'] = new WP_Query($query);
    $say("_wp_menu_item_classes_by_context with the top as the posts page, on {$where}", $classesOf());
}
update_option('page_for_posts', $postsPage);
$GLOBALS['wp_query'] = $main;
$GLOBALS['wp_the_query'] = $mainThe;

restore_error_handler();
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
