<?php
/**
 * The reference's deprecated functions (probe deprecated), the ones outside
 * block supports, and the few live functions they lean on: each called with
 * plain arguments, recorded as what it printed, what it returned and the
 * deprecation it reported (function, replacement, version). A user with old
 * profile fields, a post by them in a nested category, a child page and a
 * few links give them something to answer about; all removed at the end.
 * Same protocol as api-probe.php.
 */

global $wpdb;
$log = [];
$made = ['posts' => [], 'users' => [], 'terms' => [], 'links' => []];
register_shutdown_function(static function () use (&$made): void {
    foreach ($made['links'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_link($id);
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
    require_once ABSPATH . 'wp-admin/includes/user.php';
    foreach ($made['users'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_user($id);
        }
    }
});
set_error_handler(static fn () => true, E_USER_DEPRECATED | E_DEPRECATED | E_USER_NOTICE | E_USER_WARNING | E_WARNING | E_NOTICE);
if (!function_exists('wp_insert_link')) {
    require_once ABSPATH . 'wp-admin/includes/bookmark.php';
}
if (!did_action('init')) {
    do_action('init');
}
$heard = [];
add_action('deprecated_function_run', static function ($function, $replacement, $version) use (&$heard) {
    $heard[] = [$function, $replacement, $version];
}, 10, 3);
add_action('deprecated_argument_run', static function ($function, $message, $version) use (&$heard) {
    $heard[] = ['argument', $function, $version];
}, 10, 3);
$ids = [];
$host = (string) parse_url(home_url(), PHP_URL_HOST);
$mask = static function ($value) use (&$mask, &$ids, $host) {
    if (is_wp_error($value)) {
        return ['error' => $value->get_error_code()];
    }
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    if (is_object($value)) {
        return ['class' => get_class($value)] + $mask(get_object_vars($value));
    }
    if (is_int($value) && isset($ids[$value])) {
        return '{' . $ids[$value] . '}';
    }
    if (!is_string($value)) {
        return $value;
    }
    $value = str_replace([get_template_directory(), WP_CONTENT_DIR, ABSPATH, 'https://' . $host, 'http://' . $host], ['{theme-dir}', '{content}', '{abspath}/', '{home}', '{home}'], $value);
    // A link's last update is now, written out.
    $value = (string) preg_replace('/[A-Z][a-z]+ \d{1,2}, \d{4} \d{1,2}:\d\d (am|pm)/', '{when}', $value);
    if (preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/', $value) && abs(strtotime($value . ' UTC') - (int) current_time('timestamp')) < 300) {
        return '{now}';
    }
    foreach ($ids as $id => $label) {
        $value = (string) preg_replace('/(?<![0-9a-z])' . $id . '(?![0-9a-z])/i', '{' . $label . '}', $value);
    }
    return $value;
};
$say = static function (string $label, callable $run) use (&$log, &$heard, $mask): void {
    $heard = [];
    ob_start();
    try {
        $returned = $run();
    } catch (Throwable $e) {
        $returned = ['threw' => get_class($e)];
    }
    $printed = ob_get_clean();
    $log[] = [$label, $mask(['printed' => $printed, 'returned' => $returned, 'deprecated' => $heard])];
};

// Something to answer about: a user with old profile fields, their post in a nested category, links.
$user = wp_insert_user(['user_login' => 'zz_old_author', 'user_pass' => 'zz-old-pass-1', 'user_email' => 'zz-old@example.com', 'display_name' => 'ZZ Old Author', 'first_name' => 'Zed', 'last_name' => 'Old', 'nickname' => 'zeddy', 'user_url' => 'https://old.example.com/', 'description' => 'An old <b>author</b>.', 'role' => 'author']);
$user = is_int($user) ? $user : 0;
$made['users'][] = $user;
$ids[$user] = 'user';
foreach (['aim' => 'zz-aim', 'yim' => 'zz-yim', 'jabber' => 'zz-jabber', 'icq' => '12345', 'msn' => 'zz@msn.example'] as $key => $value) {
    update_user_meta($user, $key, $value);
}
$parent = wp_insert_term('ZZ Old Parent', 'category');
$parent = is_array($parent) ? (int) $parent['term_id'] : 0;
$made['terms'][] = [$parent, 'category'];
$ids[$parent] = 'parent';
$child = wp_insert_term('ZZ Old Child', 'category', ['parent' => $parent]);
$child = is_array($child) ? (int) $child['term_id'] : 0;
$made['terms'][] = [$child, 'category'];
$ids[$child] = 'child';
$post = wp_insert_post(['post_title' => 'ZZ Old Post', 'post_content' => "Para one.\n\n<!--nextpage-->\n\nPara two with <a href=\"https://x.example/\">a link</a>.", 'post_status' => 'publish', 'post_author' => $user, 'post_category' => [$child]]);
$post = is_int($post) ? $post : 0;
$made['posts'][] = $post;
$ids[$post] = 'post';
$linkCat = wp_insert_term('ZZ Old Links', 'link_category');
$linkCat = is_array($linkCat) ? (int) $linkCat['term_id'] : 0;
$made['terms'][] = [$linkCat, 'link_category'];
$ids[$linkCat] = 'linkcat';
$otherCat = wp_insert_term('ZZ Old Other Links', 'link_category');
$otherCat = is_array($otherCat) ? (int) $otherCat['term_id'] : 0;
$made['terms'][] = [$otherCat, 'link_category'];
$ids[$otherCat] = 'othercat';
foreach (['ZZ Alpha' => [9, $linkCat], 'ZZ Beta' => [3, $linkCat], 'ZZ Gamma' => [5, $otherCat]] as $name => [$rating, $cat]) {
    $link = wp_insert_link(['link_name' => $name, 'link_url' => 'https://' . strtolower(substr($name, 3)) . '.example.com/', 'link_rating' => $rating, 'link_description' => $name . ' desc', 'link_category' => [$cat]]);
    $made['links'][] = is_int($link) ? $link : 0;
}

// The author in the loop.
$GLOBALS['post'] = get_post($post);
$GLOBALS['authordata'] = get_userdata($user);
setup_postdata($GLOBALS['post']);
foreach (['ID', 'aim', 'description', 'firstname', 'icq', 'lastname', 'login', 'msn', 'nickname', 'url', 'yim'] as $field) {
    $say("get_the_author_{$field}", "get_the_author_{$field}");
    $say("the_author_{$field}", "the_author_{$field}");
}
$say('get_author_name', static fn () => get_author_name($user));
$say('get_author_link', static fn () => get_author_link(false, $user, 'zz_old_author'));
$say('get_author_link, displayed', static fn () => get_author_link(true, $user));
$say('get_author_rss_link', static fn () => get_author_rss_link(false, $user));
$say('get_profile', static fn () => [get_profile('user_email', 'zz_old_author'), get_profile('display_name', 'zz_old_author'), get_profile('nope', 'zz_old_author')]);
$say('get_usernumposts', static fn () => get_usernumposts($user));
// Every value, not a count: WP-CLI loads wp-admin's filters, which add a pointers row a front-end registration does not get.
$say('get_usermeta', static fn () => [get_usermeta($user, 'aim'), get_usermeta($user, 'nope'), in_array('zz-aim', (array) get_usermeta($user), true), in_array('zeddy', (array) get_usermeta($user), true)]);
$say('update_usermeta and delete_usermeta', static fn () => [update_usermeta($user, 'zz_old', 'v'), get_user_meta($user, 'zz_old', true), delete_usermeta($user, 'zz_old'), get_user_meta($user, 'zz_old', true)]);
$say('get_user_metavalues', static fn () => array_keys((array) get_user_metavalues([$user])));
$say('sanitize_user_object', static fn () => get_class(sanitize_user_object(get_userdata($user))));
$say('list_authors', static fn () => list_authors(true, false, true, false));
$say('user_pass_ok', static fn () => [user_pass_ok('zz_old_author', 'zz-old-pass-1'), user_pass_ok('zz_old_author', 'wrong')]);
$say('create_user', static function () use (&$made, &$ids) {
    $id = create_user('zz_created', 'zz-created-1', 'zz-created@example.com');
    if (is_int($id) && $id > 0) {
        $made['users'][] = $id;
        $ids[$id] = 'created';
    }
    return $id;
});
$say('set_current_user', static function () use ($user) {
    $set = set_current_user($user);
    $id = get_current_user_id();
    wp_set_current_user(0);
    return [is_object($set) ? get_class($set) : $set, $id];
});
foreach (['user_can_create_draft' => [$user], 'user_can_create_post' => [$user], 'user_can_set_post_date' => [$user], 'user_can_delete_post' => [$user, $post], 'user_can_delete_post_comments' => [$user, $post], 'user_can_edit_post' => [$user, $post], 'user_can_edit_post_comments' => [$user, $post], 'user_can_edit_post_date' => [$user, $post], 'user_can_edit_user' => [$user, 1]] as $function => $args) {
    $say($function, static fn () => $function(...$args));
}
$say('is_blog_user', static fn () => is_blog_user());

// Posts, links and navigation.
$say('get_postdata', static fn () => array_keys((array) get_postdata($post)));
$say('wp_get_single_post', static fn () => wp_get_single_post($post)->post_title ?? null);
$say('wp_get_post_cats', static fn () => wp_get_post_cats('1', $post));
$say('wp_set_post_cats', static fn () => [wp_set_post_cats('1', $post, [$parent]), wp_get_post_categories($post)]);
$say('_get_post_ancestors', static fn () => _get_post_ancestors(get_post($post)));
$say('post_permalink', static fn () => post_permalink($post));
$say('permalink_link', 'permalink_link');
$say('permalink_single_rss', 'permalink_single_rss');
$say('sticky_class', static fn () => sticky_class($post));
$say('link_pages', static fn () => link_pages('<p>', '</p>', 'number', 'next', 'prev', '%', ''));
$say('the_content_rss', static fn () => the_content_rss('(more)', 0, '', 5, 0));
$say('next_post and previous_post', static fn () => [next_post(), previous_post()]);
$say('get_boundary_post_rel_link', static fn () => get_boundary_post_rel_link());
$say('start_post_rel_link', 'start_post_rel_link');
$say('get_index_rel_link', 'get_index_rel_link');
$say('index_rel_link', 'index_rel_link');
$say('get_parent_post_rel_link', 'get_parent_post_rel_link');
$say('parent_post_rel_link', 'parent_post_rel_link');
$say('get_the_attachment_link', static fn () => get_the_attachment_link($post));
$say('get_attachment_icon_src', static fn () => get_attachment_icon_src($post));
$say('get_attachment_icon', static fn () => get_attachment_icon($post));
$say('get_attachment_innerHTML', static fn () => get_attachment_innerHTML($post));
$say('wp_get_attachment_thumb_file', static fn () => wp_get_attachment_thumb_file($post));
$say('get_autotoggle', 'get_autotoggle');
$say('get_paged_template', 'get_paged_template');
$say('get_comments_popup_template', 'get_comments_popup_template');
$say('comments_popup_script', 'comments_popup_script');
$say('is_comments_popup', 'is_comments_popup');
$say('comments_rss', 'comments_rss');
$say('comments_rss_link', static fn () => comments_rss_link('Feed'));
$say('get_commentdata', static fn () => array_keys((array) get_commentdata(1)));
$say('get_category_rss_link', static fn () => get_category_rss_link(false, $child));
$say('get_catname', static fn () => get_catname($child));
$say('get_category_children', static fn () => get_category_children($parent));
$say('the_category_ID', static fn () => the_category_ID(false));
$say('the_category_head', static fn () => the_category_head('<h2>', '</h2>'));
$say('get_archives', static fn () => get_archives('postbypost', 2, 'custom', '[', ']'));
$say('list_cats', static fn () => list_cats(0, 'All', 'name', 'asc', '', true, 0, 0, 0, 1, false, $parent));
$say('wp_list_cats', static fn () => wp_list_cats(['child_of' => $parent, 'hide_empty' => 0]));
$say('dropdown_cats', static fn () => dropdown_cats(1, 'All', 'name', 'asc', 0, 0, 0, false, $child));
$say('get_links', static fn () => get_links($linkCat, '<li>', '</li>', ' ', false, 'name', true, true, -1, 0, false));
$say('get_links_withrating', static fn () => get_links_withrating($linkCat, '<li>', '</li>', ' ', false, 'name'));
$say('get_linksbyname', static fn () => get_linksbyname('ZZ Old Links', '<li>', '</li>', ' ', false, 'rating'));
$say('get_linksbyname_withrating', static fn () => get_linksbyname_withrating('ZZ Old Links', '', '<br />', ' ', false, 'name'));
$say('get_links_list', static fn () => get_links_list());
$say('get_linkobjects', static fn () => array_map(static fn ($l) => $l->link_name, (array) get_linkobjects($linkCat)));
$say('get_linkobjectsbyname', static fn () => array_map(static fn ($l) => $l->link_name, (array) get_linkobjectsbyname('ZZ Old Links')));
$say('get_linkrating', static fn () => get_linkrating((object) ['link_rating' => 7]));
$say('get_linkcatname', static fn () => get_linkcatname($made['links'][0] ?? 0));
$say('wp_get_links', static fn () => [wp_get_links('category=' . $linkCat . '&echo=0'), wp_get_links((string) $linkCat)]);
$say('wp_get_linksbyname', static fn () => wp_get_linksbyname('ZZ Old Links', 'echo=0'));
$say('links_popup_script', static fn () => links_popup_script('Links', 400, 400, 'links.all.php', true));

// Text, options and the rest.
$say('clean_pre', static fn () => clean_pre(['', "<pre>a<br />b</pre><p>c</p>\n"]));
$say('format_to_post', static fn () => format_to_post('a "b" & c'));
$say('js_escape', static fn () => js_escape("it's \"x\"\n<y>"));
$say('wp_kses_js_entities', static fn () => wp_kses_js_entities('a &{alert(1)}; b'));
$say('make_url_footnote', static fn () => make_url_footnote('See <a href="https://a.example/">one</a> and <a href="/two">two</a>.'));
$say('translate_with_context', static fn () => translate_with_context('Post|noun'));
$say('_nc', static fn () => [_nc('One|context', 'Many|context', 1), _nc('One|context', 'Many|context', 2)]);
$say('__ngettext_noop', static fn () => __ngettext_noop('One', 'Many'));
$say('default_topic_count_text', static fn () => default_topic_count_text(3));
$say('wp_convert_bytes_to_hr', static fn () => [wp_convert_bytes_to_hr(500), wp_convert_bytes_to_hr(2048), wp_convert_bytes_to_hr(5 * 1048576), wp_convert_bytes_to_hr(3 * 1073741824)]);
$say('_search_terms_tidy', static fn () => _search_terms_tidy(' "zz" '));
$say('funky_javascript_fix', static fn () => funky_javascript_fix('a %u0041 b'));
$say('popuplinks', static fn () => popuplinks('<a href="https://out.example/">o</a>'));
$say('get_settings', static fn () => get_settings('blogname') !== false);
$say('get_alloptions', static fn () => isset(get_alloptions()->siteurl));
$say('_wp_register_meta_args_whitelist', static fn () => _wp_register_meta_args_whitelist(['type' => 'string', 'zz' => 1], ['type' => '', 'single' => false]));
$say('is_taxonomy', static fn () => [is_taxonomy('category'), is_taxonomy('zz_nope')]);
$say('is_term', static fn () => [is_term('ZZ Old Child', 'category'), is_term('zz-nope', 'category')]);
$say('is_plugin_page', 'is_plugin_page');
$say('rich_edit_exists', 'rich_edit_exists');
$say('noindex', 'noindex');
$say('wlwmanifest_link', 'wlwmanifest_link');
$say('automatic_feed_links', static fn () => [automatic_feed_links(true), current_theme_supports('automatic-feed-links')]);
$say('get_shortcut_link', 'get_shortcut_link');
$say('url_is_accessable_via_ssl', static fn () => url_is_accessable_via_ssl('http://127.0.0.1:9/'));
$say('wp_get_http', static fn () => wp_get_http('http://127.0.0.1:9/'));
$say('wp_timezone_supported', 'wp_timezone_supported');
$say('gzip_compression', 'gzip_compression');
$say('debug_fopen', static fn () => [debug_fopen(sys_get_temp_dir() . '/zz-debug.log', 'a'), debug_fwrite(false, 'x'), debug_fclose(false)]);
$say('gd_edit_image_support', static fn () => [gd_edit_image_support('image/jpeg'), gd_edit_image_support('image/nope')]);
$say('wp_explain_nonce', static fn () => wp_explain_nonce('zz-action'));
$say('wp_get_cookie_login', 'wp_get_cookie_login');
$say('wp_login', static fn () => [wp_login('zz_old_author', 'wrong'), wp_login('zz_nobody', 'x')]);
$say('force_ssl_login', static fn () => force_ssl_login());
$say('clean_page_cache, update_page_cache, update_category_cache', static function () {
    $pages = [get_post(2)];
    return [clean_page_cache(2), update_page_cache($pages), update_category_cache()];
});
$say('_usort_terms', static fn () => [_usort_terms_by_ID((object) ['term_id' => 2], (object) ['term_id' => 5]), _usort_terms_by_name((object) ['name' => 'b'], (object) ['name' => 'a'])]);
$say('_sort_nav_menu_items', static fn () => _sort_nav_menu_items((object) ['menu_order' => 2], (object) ['menu_order' => 1]));
$say('_save_post_hook', '_save_post_hook');
$say('preview_theme', 'preview_theme');
$say('preview_theme_ob_filter', static fn () => preview_theme_ob_filter('<a href="' . home_url('/x/') . '">x</a>'));
$say('wp_sensitive_page_meta', 'wp_sensitive_page_meta');
$say('wp_no_robots', 'wp_no_robots');
$say('wp_print_auto_sizes_contain_css_fix', 'wp_print_auto_sizes_contain_css_fix');
$say('wp_unregister_GLOBALS', 'wp_unregister_GLOBALS');
$say('wp_queue_comments_for_comment_meta_lazyload', static fn () => wp_queue_comments_for_comment_meta_lazyload([get_comment(1)]));
$say('wp_img_tag_add_decoding_attr', static fn () => [wp_img_tag_add_decoding_attr('<img src="x.jpg" width="10" height="10">', 'the_content'), wp_img_tag_add_decoding_attr('<img src="x.jpg" decoding="sync">', 'the_content')]);
$say('wp_embed_handler_googlevideo', static fn () => wp_embed_handler_googlevideo(['', 'abc'], [], 'https://video.google.com/videoplay?docid=abc', []));
$say('_filter_query_attachment_filenames', static fn () => _filter_query_attachment_filenames(['where' => 'x', 'join' => '']));
$say('the_block_template_skip_link', 'the_block_template_skip_link');
$say('unregister_sidebar_widget and unregister_widget_control', static fn () => [unregister_sidebar_widget('zz-nope'), unregister_widget_control('zz-nope')]);
$say('wp_setcookie and wp_clearcookie', static fn () => [wp_setcookie('zz_old_author', 'x'), wp_clearcookie()]);
$say('_get_path_to_translation', static fn () => [_get_path_to_translation('zz-domain'), _get_path_to_translation_from_lang_dir('zz-domain')]);
$say('_resolve_home_block_template', static fn () => _resolve_home_block_template());
$say('_preview_theme filters', static fn () => [_preview_theme_template_filter(), _preview_theme_stylesheet_filter(), preview_theme_ob_filter_callback(['<a href="x">', 'x'])]);
$say('funky_javascript_callback', static fn () => funky_javascript_callback(['%u0041', '0041']));
$say('block_core_ensure_interactivity_dependency', static fn () => [block_core_file_ensure_interactivity_dependency(), block_core_image_ensure_interactivity_dependency(), block_core_query_ensure_interactivity_dependency()]);
$say('wp_interactivity_process_directives_of_interactive_blocks', static fn () => wp_interactivity_process_directives_of_interactive_blocks(['blockName' => 'core/paragraph', 'attrs' => []]));
$say('start_wp', static function () use ($post) {
    $saved = $GLOBALS['wp_query'];
    $GLOBALS['wp_query'] = new WP_Query(['p' => $post]);
    try {
        start_wp();
        return [$GLOBALS['wp_query']->current_post, get_the_ID()];
    } finally {
        $GLOBALS['wp_query'] = $saved;
    }
});

// The live functions they lean on.
$columns = '<!-- wp:columns --><div class="wp-block-columns"><!-- wp:column --><div class="wp-block-column"><!-- wp:paragraph --><p>Kept</p><!-- /wp:paragraph --><!-- wp:image --><figure class="wp-block-image"><img src="x.jpg" alt=""/></figure><!-- /wp:image --><!-- wp:quote --><blockquote class="wp-block-quote"><!-- wp:paragraph --><p>Quoted</p><!-- /wp:paragraph --></blockquote><!-- /wp:quote --></div><!-- /wp:column --></div><!-- /wp:columns -->';
$say('_excerpt_render_inner_blocks', static function () use ($columns) {
    $block = parse_blocks($columns)[0];
    $allowed = ['core/columns', 'core/column', 'core/paragraph', 'core/quote'];
    return [_excerpt_render_inner_blocks($block, $allowed), _excerpt_render_inner_columns_blocks($block, $allowed)];
});
$say('excerpt_remove_blocks, a quote in a column', static fn () => excerpt_remove_blocks($columns));
$say('get_boundary_post and its rel links', static function () {
    $query = $GLOBALS['wp_query'];
    $was = $query->is_single;
    $query->is_single = true;
    try {
        $titles = static fn ($posts) => array_map(static fn ($p) => $p->post_title, (array) $posts);
        return [$titles(get_boundary_post()), $titles(get_boundary_post(false, '', false)), get_boundary_post_rel_link('%title'), get_boundary_post_rel_link('%title', false, '', false), $titles(get_boundary_post(true, '', true))];
    } finally {
        $query->is_single = $was;
    }
});
$say('get_parent_post_rel_link, a child page', static function () use (&$made, &$ids) {
    $parentPage = wp_insert_post(['post_title' => 'ZZ Old Parent Page', 'post_type' => 'page', 'post_status' => 'publish']);
    $childPage = wp_insert_post(['post_title' => 'ZZ Old Child Page', 'post_type' => 'page', 'post_status' => 'publish', 'post_parent' => $parentPage]);
    foreach ([$childPage => 'child-page', $parentPage => 'parent-page'] as $id => $label) {
        if (is_int($id) && $id > 0) {
            $made['posts'][] = $id;
            $ids[$id] = $label;
        }
    }
    $saved = $GLOBALS['post'];
    $GLOBALS['post'] = get_post($childPage);
    try {
        ob_start();
        parent_post_rel_link('%title (up)');
        return [get_parent_post_rel_link('%title (up)'), ob_get_clean()];
    } finally {
        $GLOBALS['post'] = $saved;
    }
});
$say('wp_strict_cross_origin_referrer', 'wp_strict_cross_origin_referrer');
$say('_wp_register_meta_args_allowed_list', static fn () => _wp_register_meta_args_allowed_list(['type' => 'string', 'zz' => 1, 'single' => true], ['type' => '', 'single' => false, 'default' => '']));
$say('register_meta drops unknown arguments', static function () {
    register_meta('post', 'zz_old_meta', ['type' => 'string', 'zz_unknown' => 1, 'single' => true]);
    $keys = array_keys(get_registered_meta_keys('post')['zz_old_meta'] ?? []);
    unregister_meta_key('post', 'zz_old_meta');
    return $keys;
});
$say('wp_lazyload_comment_meta', static fn () => [wp_lazyload_comment_meta([1]), wp_lazyload_comment_meta([])]);
$say('wp_register_widget_control and wp_unregister_widget_control', static function () {
    wp_register_widget_control('zz-old-control-2', 'ZZ', '__return_null', ['width' => 300], ['number' => 2]);
    $controls = $GLOBALS['wp_registered_widget_controls'];
    $updates = $GLOBALS['wp_registered_widget_updates'] ?? [];
    $registered = [$controls['zz-old-control-2'] ?? null, $updates['zz-old-control'] ?? null];
    wp_unregister_widget_control('zz-old-control-2');
    return [$registered, isset($GLOBALS['wp_registered_widget_controls']['zz-old-control-2']), isset($GLOBALS['wp_registered_widget_updates']['zz-old-control'])];
});

unset($GLOBALS['post'], $GLOBALS['authordata']);
restore_error_handler();
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
