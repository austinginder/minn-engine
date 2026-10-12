<?php
/**
 * What the remaining smaller hooks hand their callbacks (probe
 * hook-inputs-2), recorded as probe hook-inputs records them: the database
 * door's charsets, the textdomain registry, a theme's own setup, the menu
 * walker, the widgets, script modules, the HTTP proxy, privacy mail,
 * password keys of the old shape, user spam flags, REST trash and the
 * autosave, rewrite rules for IIS, and the AI client. The rows and files
 * the probe makes are removed again.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$home = rtrim(home_url(), '/');
$host = (string) parse_url($home, PHP_URL_HOST);
$ids = [];
$describe = static function ($value) use (&$describe, $host, &$ids) {
    if ($value instanceof WP_Post) {
        return "post:{$value->post_type}:{$value->post_name}";
    }
    if ($value instanceof WP_Term) {
        return "term:{$value->taxonomy}:{$value->slug}";
    }
    if ($value instanceof WP_User) {
        return 'user:' . $value->user_login;
    }
    if ($value instanceof WP_Error) {
        return 'error:' . $value->get_error_code() . ':' . $value->get_error_message();
    }
    if (is_object($value)) {
        return 'object:' . get_class($value);
    }
    if (is_array($value)) {
        return array_map($describe, $value);
    }
    if (is_int($value) && in_array($value, $ids, true)) {
        return '{' . array_search($value, $ids, true) . '}';
    }
    if ((is_int($value) || (is_string($value) && ctype_digit($value))) && (int) $value >= 100000) {
        return '{n}';
    }
    if (is_string($value)) {
        $value = (string) preg_replace('#https?://' . preg_quote($host, '#') . '#', '{home}', $value);
        $value = str_replace([WP_CONTENT_DIR, ABSPATH], ['{content}', '{abspath}/'], $value);
        foreach ($ids as $name => $id) {
            $value = (string) preg_replace('/\b' . $id . '\b/', '{' . $name . '}', $value);
        }
        return $value;
    }
    return $value;
};
$heard = [];
// Each section listens to its own hooks only: the previous section's listeners go first, so a
// hook the reference asks on every write (the charset lookups) stays in the rows about it.
$listening = [];
$listen = static function (array $hooks) use (&$heard, $describe, &$listening): void {
    foreach ($listening as [$hook, $callback]) {
        remove_filter($hook, $callback, 10);
    }
    $listening = [];
    foreach ($hooks as $hook) {
        $callback = static function (...$args) use ($hook, &$heard, $describe) {
            $heard[] = [$hook, array_map($describe, $args)];
            return $args[0] ?? null;
        };
        add_filter($hook, $callback, 10, 9);
        $listening[] = [$hook, $callback];
    }
};
$run = static function (string $label, callable $call) use (&$heard, $say, $describe): void {
    $heard = [];
    $result = $call();
    $kept = [];
    foreach ($heard as $entry) {
        if ($kept === [] || end($kept) !== $entry) {
            $kept[] = $entry;
        }
    }
    $say($label, ['heard' => $kept, 'result' => $describe($result)]);
};
add_filter('doing_it_wrong_trigger_error', '__return_false');
add_filter('deprecated_function_trigger_error', '__return_false');
add_filter('deprecated_hook_trigger_error', '__return_false');

$sweep = static function (): void {
    global $wpdb;
    foreach ((array) $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_title LIKE 'zz-hi2%' OR post_name LIKE 'zz-hi2%' OR post_title = 'zz-hi2-request@example.org'") as $id) {
        wp_delete_post((int) $id, true);
    }
    foreach ((array) $wpdb->get_col("SELECT comment_ID FROM {$wpdb->comments} WHERE comment_content = 'zz-hi2'") as $id) {
        wp_delete_comment((int) $id, true);
    }
    $menu = wp_get_nav_menu_object('zz-hi2-menu');
    if ($menu) {
        wp_delete_nav_menu($menu->term_id);
    }
    $user = get_user_by('login', 'zz-hi2-user');
    if ($user) {
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user($user->ID);
    }
};
$sweep();
register_shutdown_function($sweep);
wp_set_current_user(1);

// ---- the database door, the textdomain registry ----------------------------------------
global $wpdb;
$listen(['pre_get_col_charset', 'pre_get_table_charset']);
$run('a column\'s charset', static fn () => $wpdb->get_col_charset($wpdb->posts, 'post_title'));
$run('a column the table lacks', static fn () => $wpdb->get_col_charset($wpdb->posts, 'zz_no_column'));
$listen(['lang_dir_for_domain', 'pre_get_language_files_from_path']);
$run('the registry, a domain', static fn () => $GLOBALS['wp_textdomain_registry']->get('zz-hi2', 'en_US'));
$run('the registry, a folder\'s files', static fn () => count($GLOBALS['wp_textdomain_registry']->get_language_files_from_path(WP_LANG_DIR . '/zz-none/')));

// ---- a theme ---------------------------------------------------------------------------
// The reference has made a theme by now (the engine's probe runner stops before the theme loads): the
// first one made asks wp_cache_themes_persistently, so one is made before listening.
new WP_Theme('twentytwentyfive', get_theme_root());
$listen(['wp_cache_themes_persistently', 'theme_block_pattern_files', 'wp_theme_files_cache_ttl']);
$run('a theme made', static fn () => (new WP_Theme('twentytwentyfive', get_theme_root()))->get('Name'));
$run('the themes searched for again', static fn () => count((array) search_theme_directories(true)) > 0);
$run('a theme\'s patterns', static function () {
    $theme = wp_get_theme('twentytwentyfive');
    $theme->delete_pattern_cache();
    return count($theme->get_block_patterns()) > 0;
});

// ---- the menu walker -------------------------------------------------------------------
$listen(['nav_menu_item_attributes', 'nav_menu_submenu_attributes']);
$menu = wp_create_nav_menu('zz-hi2-menu');
$parent = wp_update_nav_menu_item($menu, 0, ['menu-item-title' => 'zz-hi2-parent', 'menu-item-url' => 'https://example.org/', 'menu-item-status' => 'publish', 'menu-item-type' => 'custom']);
$child = wp_update_nav_menu_item($menu, 0, ['menu-item-title' => 'zz-hi2-child', 'menu-item-url' => 'https://example.org/c', 'menu-item-status' => 'publish', 'menu-item-type' => 'custom', 'menu-item-parent-id' => $parent]);
$ids['parent'] = (int) $parent;
$ids['child'] = (int) $child;
$run('a menu with a child', static fn () => wp_nav_menu(['menu' => $menu, 'echo' => false, 'container' => false]));

// ---- widgets ---------------------------------------------------------------------------
// The engine's probe runner stops before init: the widget factory is made here, as init makes it.
if (!did_action('widgets_init')) {
    wp_widgets_init();
}
$listen(['widget_media_image_instance', 'widget_block_dynamic_classname', 'navigation_widgets_format', 'rss_widget_feed_link']);
$widget = static function (string $class, array $instance): string {
    ob_start();
    the_widget($class, $instance, ['before_widget' => '<section class="widget %s">', 'after_widget' => '</section>']);
    return (string) ob_get_clean();
};
$run('an image widget', static fn () => $widget('WP_Widget_Media_Image', ['url' => 'https://example.org/i.png', 'title' => 'zz']));
$run('a block widget', static fn () => $widget('WP_Widget_Block', ['content' => '<!-- wp:search /-->']));
$run('a block widget, nothing in it', static fn () => $widget('WP_Widget_Block', ['content' => '']));
$run('the archives widget', static fn () => $widget('WP_Widget_Archives', []));
add_theme_support('html5', ['navigation-widgets']);
$run('the archives widget, html5 navigation', static fn () => $widget('WP_Widget_Archives', ['title' => 'Zz <b>Archives</b>']));
$run('the meta widget, html5 navigation', static fn () => $widget('WP_Widget_Meta', []));
$run('the categories widget, html5 navigation', static fn () => $widget('WP_Widget_Categories', ['title' => ' <em>Zz</em> "Cats" & co ']));
$run('the categories widget as a dropdown', static fn () => $widget('WP_Widget_Categories', ['dropdown' => 1]));
$run('the pages widget, html5 navigation', static fn () => $widget('WP_Widget_Pages', []));
$run('the recent comments widget, html5 navigation', static fn () => $widget('WP_Widget_Recent_Comments', ['number' => 1]));
$run('the recent posts widget, html5 navigation', static fn () => $widget('WP_Widget_Recent_Posts', ['number' => 1]));
$run('the tag cloud widget, html5 navigation', static fn () => $widget('WP_Widget_Tag_Cloud', []));
$run('the menu widget, html5 navigation', static fn () => $widget('WP_Nav_Menu_Widget', ['nav_menu' => $menu, 'title' => ' <b>Zz</b> & "x" ']));
$run('the menu widget, html5 navigation, no title', static fn () => $widget('WP_Nav_Menu_Widget', ['nav_menu' => $menu]));
$run('the RSS widget, html5 navigation', static fn () => $widget('WP_Widget_RSS', ['url' => 'http://127.0.0.1:1/feed/', 'items' => 1]));
add_filter('rss_widget_feed_link', '__return_empty_string', 20);
$run('the RSS widget, no feed link', static fn () => $widget('WP_Widget_RSS', ['url' => 'http://127.0.0.1:1/feed/', 'items' => 1]));
remove_filter('rss_widget_feed_link', '__return_empty_string', 20);
add_filter('widget_title', '__return_empty_string', 20);
$run('the categories widget, html5 navigation, the title emptied', static fn () => $widget('WP_Widget_Categories', []));
$run('the RSS widget, html5 navigation, the title emptied', static fn () => $widget('WP_Widget_RSS', ['url' => 'http://127.0.0.1:1/feed/', 'items' => 1]));
remove_filter('widget_title', '__return_empty_string', 20);
add_filter('navigation_widgets_format', $xhtml = static fn () => 'xhtml', 20);
$run('the pages widget, xhtml asked for', static fn () => $widget('WP_Widget_Pages', []));
remove_filter('navigation_widgets_format', $xhtml, 20);
remove_theme_support('html5');
$run('the menu widget, xhtml', static fn () => $widget('WP_Nav_Menu_Widget', ['nav_menu' => $menu]));
add_theme_support('html5', ['comment-list', 'comment-form', 'search-form', 'gallery', 'caption', 'style', 'script']);

// ---- script modules, the HTTP proxy ----------------------------------------------------
$listen(['script_module_loader_src', 'pre_http_send_through_proxy']);
$run('a module\'s address', static function () {
    wp_register_script_module('zz-hi2-module', 'https://example.org/zz.js', [], '1.2');
    wp_enqueue_script_module('zz-hi2-module');
    ob_start();
    wp_script_modules()->print_enqueued_script_modules();
    return trim((string) ob_get_clean());
});
$run('the proxy, another host', static fn () => (new WP_HTTP_Proxy())->send_through_proxy('https://example.org/x'));
$run('the proxy, the site', static fn () => (new WP_HTTP_Proxy())->send_through_proxy(home_url('/x')));
$run('the proxy, localhost', static fn () => (new WP_HTTP_Proxy())->send_through_proxy('http://localhost/x'));
add_filter('pre_http_send_through_proxy', '__return_false', 20);
$run('the proxy, a plugin answers', static fn () => (new WP_HTTP_Proxy())->send_through_proxy('https://example.org/x'));
remove_filter('pre_http_send_through_proxy', '__return_false', 20);

// ---- privacy mail ----------------------------------------------------------------------
$listen(['user_confirmed_action_email_content', 'user_erasure_complete_email_headers', 'user_erasure_complete_email_subject', 'user_erasure_fulfillment_email_to', 'user_request_confirmed_email_to']);
add_filter('pre_wp_mail', '__return_true');
$request = wp_create_user_request('zz-hi2-request@example.org', 'remove_personal_data', [], 'confirmed');
$ids['request'] = (int) $request;
$run('the request confirmed, the admin told', static fn () => _wp_privacy_send_request_confirmation_notification($request));
wp_update_post(['ID' => $request, 'post_status' => 'request-completed']);
$run('the erasure done, the person told', static fn () => _wp_privacy_send_erasure_fulfillment_notification($request));
remove_filter('pre_wp_mail', '__return_true');

// ---- users -----------------------------------------------------------------------------
$listen(['make_spam_user', 'make_ham_user', 'password_reset_key_expired']);
$user = wp_insert_user(['user_login' => 'zz-hi2-user', 'user_email' => 'zz-hi2-user@example.org', 'user_pass' => 'zz-' . wp_generate_password(12)]);
// A failed insert is an error object, which (int) makes user 1: nothing below may touch an account then.
if (!is_int($user) || $user <= 1) {
    throw new RuntimeException('the probe could not make its own user');
}
$ids['user'] = $user;
$run('a user marked spam', static function () use ($user) {
    $saved = wp_insert_user(['ID' => $user, 'user_login' => 'zz-hi2-user', 'spam' => 1]);
    return is_wp_error($saved) ? $saved : 'saved';
});
$run('a user marked ham', static function () use ($user) {
    $saved = wp_insert_user(['ID' => $user, 'user_login' => 'zz-hi2-user', 'spam' => 0]);
    return is_wp_error($saved) ? $saved : 'saved';
});
$run('a user saved, spam not named', static function () use ($user) {
    $saved = wp_insert_user(['ID' => $user, 'user_login' => 'zz-hi2-user', 'display_name' => 'zz']);
    return is_wp_error($saved) ? $saved : 'saved';
});
// Keys are letters and digits (a dash is stripped before the check). An old shape is stored without its time.
$run('an old-shape reset key', static function () use ($user) {
    global $wpdb;
    $wpdb->update($wpdb->users, ['user_activation_key' => wp_fast_hash('zzoldkey1')], ['ID' => $user]);
    clean_user_cache($user);
    return check_password_reset_key('zzoldkey1', 'zz-hi2-user');
});
$run('an old-shape reset key, the wrong one', static fn () => check_password_reset_key('zznotthekey', 'zz-hi2-user'));
$run('a reset key stored as itself', static function () use ($user) {
    global $wpdb;
    $wpdb->update($wpdb->users, ['user_activation_key' => 'zzplainkey1'], ['ID' => $user]);
    clean_user_cache($user);
    return [check_password_reset_key('zzplainkey1', 'zz-hi2-user'), check_password_reset_key('ZZPLAINKEY1', 'zz-hi2-user')];
});
$run('a reset key with its time', static function () use ($user) {
    global $wpdb;
    $wpdb->update($wpdb->users, ['user_activation_key' => time() . ':' . wp_fast_hash('zztimedkey1')], ['ID' => $user]);
    clean_user_cache($user);
    return check_password_reset_key('zztimedkey1', 'zz-hi2-user');
});

// ---- REST: trash, autosaves ------------------------------------------------------------
$listen(['rest_post_trashable', 'rest_comment_trashable', 'wp_creating_autosave']);
$post = wp_insert_post(['post_title' => 'zz-hi2', 'post_name' => 'zz-hi2', 'post_status' => 'publish', 'post_content' => 'zz']);
if (!is_int($post) || $post <= 1) {
    throw new RuntimeException('the probe could not make its own post');
}
$ids['post'] = (int) $post;
$comment = wp_insert_comment(['comment_post_ID' => $post, 'comment_content' => 'zz-hi2', 'comment_approved' => '1']);
$ids['comment'] = (int) $comment;
$ask = static function (string $method, string $route, array $params = []) {
    $request = new WP_REST_Request($method, $route);
    foreach ($params as $key => $value) {
        $request->set_param($key, $value);
    }
    $response = rest_do_request($request);
    return [$response->get_status(), is_array($response->get_data()) ? ($response->get_data()['code'] ?? $response->get_data()['status'] ?? null) : null];
};
$run('an autosave, the first', static fn () => $ask('POST', "/wp/v2/posts/{$post}/autosaves", ['title' => 'zz-hi2 a', 'content' => 'zz a']));
$run('an autosave, again', static fn () => $ask('POST', "/wp/v2/posts/{$post}/autosaves", ['title' => 'zz-hi2 b', 'content' => 'zz b']));
$run('a comment trashed over REST', static fn () => $ask('DELETE', "/wp/v2/comments/{$comment}"));
$run('a post trashed over REST', static fn () => $ask('DELETE', "/wp/v2/posts/{$post}"));

// ---- rewrite rules for IIS, the AI client ----------------------------------------------
$listen(['iis7_url_rewrite_rules']);
$run('IIS rules', static fn () => $GLOBALS['wp_rewrite']->iis7_url_rewrite_rules());
$run('IIS rules, with their parent tags', static fn () => $GLOBALS['wp_rewrite']->iis7_url_rewrite_rules(true));
$listen(['wp_ai_client_prevent_prompt']);
$run('a prompt, its support asked', static function () {
    $builder = wp_ai_client_prompt('zz');
    return [is_object($builder) ? get_class($builder) : $builder, $builder->is_supported_for_text_generation()];
});
add_filter('wp_ai_client_prevent_prompt', '__return_true', 20);
$run('a prompt a plugin prevents', static function () {
    $builder = wp_ai_client_prompt('zz');
    return [$builder->is_supported_for_text_generation(), $builder->generate_text()];
});
remove_filter('wp_ai_client_prevent_prompt', '__return_true', 20);

// ---- the navigation block: its items, the blocks it lists, the links to posts it shows ----------------
$listen(['block_core_navigation_render_inner_blocks', 'block_core_navigation_listable_blocks', 'render_block_core_navigation_link_allowed_post_status']);
$navLink = static fn (string $label, string $extra = '') => '<!-- wp:navigation-link {"label":"' . $label . '","url":"https://example.org/' . strtolower($label) . '","kind":"custom"' . $extra . '} /-->';
$nav = static function (string $inner, string $attrs = '{"overlayMenu":"never"}'): string {
    $html = do_blocks('<!-- wp:navigation ' . $attrs . ' -->' . $inner . '<!-- /wp:navigation -->');
    $html = (string) preg_replace(['/ aria-label=" \d+"/', '/search__input-\d+/', '#<svg.*?</svg>#s'], [' aria-label=" {n}"', 'search__input-{n}', '<svg/>'], $html);
    return $html;
};
$draft = wp_insert_post(['post_title' => 'zz-hi2-draft', 'post_status' => 'draft']);
$ids['draft'] = (int) $draft;
$run('a navigation, links around a paragraph', static fn () => $nav($navLink('A') . '<!-- wp:paragraph --><p>P</p><!-- /wp:paragraph -->' . $navLink('B')));
$run('a navigation, a link to a draft between', static fn () => $nav($navLink('A') . '<!-- wp:navigation-link {"label":"D","type":"post","id":' . $draft . ',"url":"/?p=' . $draft . '","kind":"post-type"} /-->' . $navLink('B')));
$run('a navigation, a link to a published post', static fn () => $nav('<!-- wp:navigation-link {"label":"Hello","type":"post","id":1,"url":"/hello-world/","kind":"post-type"} /-->'));
$run('a navigation, the site title and a search', static fn () => $nav('<!-- wp:site-title /-->' . $navLink('A') . '<!-- wp:search {"label":"Search","buttonText":"Search"} /-->'));
$run('a navigation, links without a label or an address', static fn () => $nav('<!-- wp:navigation-link {"label":"","url":""} /--><!-- wp:navigation-link {"label":"L"} /--><!-- wp:navigation-submenu {"label":"S"} -->' . $navLink('C') . '<!-- /wp:navigation-submenu -->'));
$run('a navigation, a page list under overlay colours', static fn () => $nav('<!-- wp:page-list /-->', '{"overlayMenu":"never","customOverlayTextColor":"#ff0000","overlayBackgroundColor":"base"}'));
add_filter('render_block_core_navigation_link_allowed_post_status', $drafts = static fn ($statuses) => [...$statuses, 'draft'], 20);
add_filter('block_core_navigation_listable_blocks', $listParagraphs = static fn ($names) => [...$names, 'core/paragraph'], 20);
$run('a navigation, drafts and paragraphs let in by plugins', static fn () => $nav($navLink('A') . '<!-- wp:paragraph --><p>P</p><!-- /wp:paragraph --><!-- wp:navigation-link {"label":"D","type":"post","id":' . $draft . ',"url":"/?p=' . $draft . '","kind":"post-type"} /-->'));
remove_filter('render_block_core_navigation_link_allowed_post_status', $drafts, 20);
remove_filter('block_core_navigation_listable_blocks', $listParagraphs, 20);
add_filter('block_core_navigation_render_inner_blocks', $firstOnly = static function ($blocks) {
    $kept = [];
    foreach ($blocks as $block) {
        $kept[] = $block->parsed_block;
        break;
    }
    return new WP_Block_List($kept);
}, 20);
$run('a navigation, a plugin keeps only its first item', static fn () => $nav($navLink('A') . $navLink('B')));
remove_filter('block_core_navigation_render_inner_blocks', $firstOnly, 20);

// ---- the breadcrumbs block: its trail is the plugins' to change -------------------------------------------
$listen(['block_core_breadcrumbs_items']);
$crumbs = static function (array $query): string {
    $GLOBALS['wp_query'] = new WP_Query($query);
    $GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
    if (have_posts()) {
        the_post();
    }
    $html = do_blocks('<!-- wp:breadcrumbs /-->');
    wp_reset_postdata();
    return $html;
};
$run('the breadcrumbs of a child page', static fn () => $crumbs(['page_id' => 6]));
$run('the breadcrumbs of a post', static fn () => $crumbs(['p' => 1]));
add_filter('block_core_breadcrumbs_items', $lastOnly = static fn ($items) => array_slice($items, -1), 20);
$run('the breadcrumbs a plugin cuts to the current item', static fn () => $crumbs(['p' => 1]));
remove_filter('block_core_breadcrumbs_items', $lastOnly, 20);

// ---- theme.json: core's layer, the site editor's, and the style nodes a stylesheet prints -----------------
$listen([]);
$sheet = static function (): array {
    // The reference keeps the stylesheet and the settings for the request: each row starts afresh.
    wp_clean_theme_json_cache();
    $css = wp_get_global_stylesheet();
    preg_match_all('/--wp--preset--(?:color|gradient)--[a-z0-9-]+/', $css, $vars);
    $counts = [];
    foreach (['a:where(:not(.wp-element-button))', '.wp-block-site-title', '#123456'] as $needle) {
        $counts[$needle] = substr_count($css, $needle);
    }
    return ['presets' => array_values(array_unique($vars[0])), 'counts' => $counts, 'palette' => array_map(static fn ($origin) => array_column((array) $origin, 'slug'), (array) wp_get_global_settings(['color', 'palette']))];
};
$layers = [];
add_filter('wp_theme_json_data_default', $core = static function ($data) use (&$layers) {
    $layers[] = ['default', array_keys((array) $data->get_data())];
    return $data->update_with(['version' => 3, 'settings' => ['color' => ['palette' => [['slug' => 'zz-red', 'name' => 'Zz red', 'color' => '#ff0000']], 'gradients' => []]]]);
});
$run('core\'s layer changed by a plugin', static function () use ($sheet, &$layers) {
    $layers = [];
    return ['sheet' => $sheet(), 'asked' => array_values(array_unique($layers, SORT_REGULAR))];
});
remove_filter('wp_theme_json_data_default', $core);
add_filter('wp_theme_json_data_user', $user = static fn ($data) => $data->update_with(['version' => 3, 'styles' => ['color' => ['background' => '#123456']], 'settings' => ['color' => ['palette' => [['slug' => 'zz-user', 'name' => 'Zz user', 'color' => '#abcdef']]]]]));
$run('the site editor\'s layer added to by a plugin', static fn () => $sheet());
remove_filter('wp_theme_json_data_user', $user);
add_filter('wp_theme_json_get_style_nodes', $nodes = static fn ($list) => array_values(array_filter($list, static fn ($node) => !in_array($node['path'], [['styles', 'elements', 'link'], ['styles', 'blocks', 'core/site-title']], true))));
$run('style nodes a plugin leaves out', static fn () => $sheet()['counts']);
remove_filter('wp_theme_json_get_style_nodes', $nodes);
$run('the stylesheet again, nothing filtered', static fn () => $sheet()['counts']);

// ---- what a theme supports: a feature's own filter is asked only where the reference asks it -------------
$listen(['current_theme_supports-html5', 'current_theme_supports-post-thumbnails', 'current_theme_supports-editor-styles', 'current_theme_supports-post-formats', 'current_theme_supports-custom-header', 'current_theme_supports-custom-background', 'current_theme_supports-zz-hi2-list', 'current_theme_supports-title-tag', 'current_theme_supports-menus']);
add_theme_support('post-formats', ['aside']);
add_theme_support('custom-header', ['width' => 100, 'uploads' => true]);
add_theme_support('custom-background', ['default-color' => 'fff']);
add_theme_support('zz-hi2-list', ['a', 'b']);
foreach ([['html5', 'gallery'], ['html5'], ['zz-hi2-feature'], ['title-tag'], ['post-thumbnails'], ['editor-styles'], ['menus'], ['post-formats', 'aside'], ['post-formats', 'gallery'], ['post-thumbnails', 'post'], ['html5', 'zz'], ['custom-header', 'uploads'], ['custom-header', 'video'], ['custom-background', 'default-color'], ['custom-background', 'zz'], ['zz-hi2-list', 'a'], ['zz-hi2-list', 'zz'], ['editor-styles', 'x'], ['custom-logo', 'x']] as $feature) {
    $run('supports ' . implode(' ', $feature), static fn () => current_theme_supports(...$feature));
}
remove_theme_support('zz-hi2-list');

// ---- requests WP_HTTP_BLOCK_EXTERNAL blocks (last: the constants stay for the process) ------------
$listen(['block_local_requests', 'http_api_debug']);
$fetch = static fn (string $url, array $args = []) => wp_remote_get($url, ['timeout' => 1] + $args);
$run('a request refused, not a URL', static fn () => $fetch('not a url'));
$run('a request refused, not http', static fn () => $fetch('ftp://example.invalid/'));
define('WP_HTTP_BLOCK_EXTERNAL', true);
define('WP_ACCESSIBLE_HOSTS', '*.allowed.invalid,exact.invalid , spaced.invalid');
$run('a request blocked', static fn () => $fetch('http://127.0.0.1:1/x'));
$run('a request blocked, streamed', static fn () => $fetch('http://127.0.0.1:1/', ['stream' => true]));
$run('a request unsafe and blocked', static fn () => wp_safe_remote_get('http://10.0.0.1:1/'));
$block = static fn (string $url) => (new WP_Http())->block_request($url);
$run('blocked or not, by host', static fn () => array_map($block, ['https://a.b.allowed.invalid/', 'https://A-B.Allowed.invalid/', 'https://allowed.invalid/', 'https://xallowed.invalid/', 'https://exact.invalid/', 'https://EXACT.invalid/', 'https://exact.invalidx/', 'https://spaced.invalid/', 'https://LOCALHOST/', 'file:///etc/hosts', 'https://other.invalid/']));
$run('blocked or not, the site and localhost', static fn () => [$block('http://localhost:1/x'), $block('//localhost/x'), $block($home . ':1/x')]);
add_filter('block_local_requests', '__return_true', 20);
$run('blocked or not, local requests blocked too', static fn () => [$block('http://localhost:1/x'), $block($home . ':1/x'), $fetch('http://localhost:1/x')]);
remove_filter('block_local_requests', '__return_true', 20);

echo json_encode($log, JSON_UNESCAPED_SLASHES);
