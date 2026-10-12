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
        return 'error:' . $value->get_error_code();
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
$listen = static function (array $hooks) use (&$heard, $describe): void {
    foreach ($hooks as $hook) {
        add_filter($hook, static function (...$args) use ($hook, &$heard, $describe) {
            $heard[] = [$hook, array_map($describe, $args)];
            return $args[0] ?? null;
        }, 10, 9);
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
$listen(['wp_cache_themes_persistently', 'theme_block_pattern_files', 'wp_theme_files_cache_ttl']);
$run('a theme made', static fn () => (new WP_Theme('twentytwentyfive', get_theme_root()))->get('Name'));
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
remove_theme_support('html5');
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

// ---- privacy mail ----------------------------------------------------------------------
$listen(['user_confirmed_action_email_content', 'user_erasure_complete_email_headers', 'user_erasure_complete_email_subject', 'user_erasure_fulfillment_email_to', 'user_request_confirmed_email_to']);
add_filter('pre_wp_mail', '__return_true');
$request = wp_create_user_request('zz-hi2-request@example.org', 'remove_personal_data', [], 'confirmed');
$ids['request'] = (int) $request;
$run('the request confirmed, the admin told', static fn () => _wp_privacy_send_request_confirmation_notification($request));
$run('the erasure done, the person told', static fn () => _wp_privacy_send_erasure_fulfillment_notification($request));
remove_filter('pre_wp_mail', '__return_true');

// ---- users -----------------------------------------------------------------------------
$listen(['make_spam_user', 'make_ham_user', 'password_reset_key_expired']);
$user = wp_insert_user(['user_login' => 'zz-hi2-user', 'user_email' => 'zz-hi2-user@example.org', 'user_pass' => 'zz-' . wp_generate_password(12)]);
$ids['user'] = (int) $user;
$run('a user marked spam', static fn () => is_wp_error(wp_insert_user(['ID' => $user, 'user_login' => 'zz-hi2-user', 'spam' => 1])) ? 'error' : 'saved');
$run('a user marked ham', static fn () => is_wp_error(wp_insert_user(['ID' => $user, 'user_login' => 'zz-hi2-user', 'spam' => 0])) ? 'error' : 'saved');
$run('an old-shape reset key', static function () use ($user) {
    global $wpdb;
    require_once ABSPATH . WPINC . '/class-phpass.php';
    $wpdb->update($wpdb->users, ['user_activation_key' => (new PasswordHash(8, true))->HashPassword('zz-old-key')], ['ID' => $user]);
    clean_user_cache($user);
    return check_password_reset_key('zz-old-key', 'zz-hi2-user');
});
$run('an old-shape reset key, the wrong one', static fn () => check_password_reset_key('zz-not-the-key', 'zz-hi2-user'));

// ---- REST: trash, autosaves ------------------------------------------------------------
$listen(['rest_post_trashable', 'rest_comment_trashable', 'wp_creating_autosave']);
$post = wp_insert_post(['post_title' => 'zz-hi2', 'post_name' => 'zz-hi2', 'post_status' => 'publish', 'post_content' => 'zz']);
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

echo json_encode($log, JSON_UNESCAPED_SLASHES);
