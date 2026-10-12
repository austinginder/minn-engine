<?php
/**
 * What the smaller hooks of terms, meta, users, cron, menus, comments,
 * scripts, themes and options hand their callbacks (probe hook-inputs),
 * recorded as probe filter-inputs records them: each call runs with the
 * hooks it should fire listened to, and the row lists what each heard, in
 * order. The rows the probe makes are removed again.
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
    if ($value instanceof WP_Taxonomy) {
        return 'taxonomy:' . $value->name;
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
    // Meta row ids and timestamps move with the database and the clock.
    if ((is_int($value) || (is_string($value) && ctype_digit($value))) && (int) $value >= 10000) {
        return '{n}';
    }
    if (is_string($value)) {
        $value = (string) preg_replace('#https?://' . preg_quote($host, '#') . '#', '{home}', $value);
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
            $heard[] = [$hook, $hook === 'pre_cache_alloptions' ? ['options:' . (is_array($args[0]) && isset($args[0]['siteurl']) ? 'with siteurl' : gettype($args[0]))] : array_map($describe, $args)];
            return $args[0] ?? null;
        }, 10, 9);
    }
};
// A hook heard again with the same arguments straight after is one entry: how often a stack
// recounts the same terms is its own affair.
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

$made = ['posts' => [], 'terms' => [], 'menus' => []];
$sweep = static function () use (&$made): void {
    global $wpdb;
    foreach ((array) $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_title LIKE 'zz-hi%' OR post_name LIKE 'zz-hi%'") as $id) {
        wp_delete_post((int) $id, true);
    }
    foreach (['category', 'post_tag', 'nav_menu'] as $taxonomy) {
        foreach (['zz-hi-term', 'zz-hi-menu'] as $slug) {
            $term = get_term_by('slug', $slug, $taxonomy);
            if ($term) {
                wp_delete_term($term->term_id, $taxonomy);
            }
        }
    }
    $wpdb->query("DELETE FROM {$wpdb->term_taxonomy} WHERE description = 'zz-hi-shared'");
    $wpdb->query("DELETE t FROM {$wpdb->terms} t LEFT JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id WHERE t.slug = 'zz-hi-term' AND tt.term_id IS NULL");
    delete_user_meta(1, 'zz_hi_meta');
    delete_option('_split_terms');
    wp_clear_scheduled_hook('zz_hi_event');
};
$sweep();
register_shutdown_function($sweep);

// ---- terms -----------------------------------------------------------------------------
$listen(['update_post_term_count_statuses']);
// The split names the old term, the new one, the taxonomy row and the taxonomy: the new id is told apart by what it is.
add_action('split_shared_term', static function ($old, $new, $tt, $taxonomy) use (&$heard, &$ids): void {
    $ids['new_term'] = (int) $new;
    $heard[] = ['split_shared_term', [$old === $ids['term'] ? '{term}' : $old, (int) $new !== $ids['term'] && (int) $new > 0 ? '{new_term}' : $new, $tt === $ids['shared_tt'] ? '{shared_tt}' : $tt, $taxonomy]];
}, 10, 4);
$term = wp_insert_term('zz-hi-term', 'category', ['slug' => 'zz-hi-term']);
$ids['term'] = (int) $term['term_id'];
$ids['tt'] = (int) $term['term_taxonomy_id'];
$run('a category recount', static fn () => wp_update_term_count_now([$ids['tt']], 'category'));
global $wpdb;
$wpdb->insert($wpdb->term_taxonomy, ['term_id' => $ids['term'], 'taxonomy' => 'post_tag', 'description' => 'zz-hi-shared', 'parent' => 0, 'count' => 0]);
$ids['shared_tt'] = (int) $wpdb->insert_id;
$run('a shared term split', static function () use (&$ids) {
    $new = _split_shared_term($ids['term'], $ids['shared_tt']);
    $moved = get_term($new, 'post_tag');
    return [
        is_int($new) ? ($new === $ids['term'] ? 'same id' : 'new id') : $new,
        $moved instanceof WP_Term ? [$moved->slug, $moved->name, (int) $moved->term_taxonomy_id === $ids['shared_tt']] : null,
        wp_get_split_term($ids['term'], 'post_tag') === $new,
        get_term($ids['term'], 'category') instanceof WP_Term,
        _split_shared_term($ids['term'], $ids['tt']) === $ids['term'],
    ];
});
if (!empty($ids['new_term'])) {
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->terms} WHERE term_id = %d", $ids['new_term']));
}
// Those rows are gone: their ids must not name anything made later.
unset($ids['term'], $ids['tt'], $ids['shared_tt'], $ids['new_term']);

// ---- meta ------------------------------------------------------------------------------
$listen(['added_usermeta', 'update_usermeta', 'updated_usermeta', 'delete_usermeta', 'deleted_usermeta', 'delete_postmeta', 'deleted_postmeta', 'delete_post_meta', 'deleted_post_meta']);
$run('update_usermeta, new', static fn () => update_usermeta(1, 'zz_hi_meta', 'one'));
$run('update_usermeta, changed', static fn () => update_usermeta(1, 'zz_hi_meta', 'two'));
$run('update_usermeta, same', static fn () => update_usermeta(1, 'zz_hi_meta', 'two'));
$run('delete_usermeta', static fn () => delete_usermeta(1, 'zz_hi_meta'));
$post = wp_insert_post(['post_title' => 'zz-hi', 'post_name' => 'zz-hi', 'post_status' => 'publish', 'post_content' => 'zz']);
$ids['post'] = (int) $post;
$mid = add_post_meta($post, 'zz_hi_key', 'v');
$run('delete_metadata_by_mid', static fn () => delete_metadata_by_mid('post', $mid));
$listen(['delete_commentmeta', 'deleted_commentmeta', 'delete_comment_meta', 'deleted_comment_meta', 'delete_termmeta', 'delete_term_meta']);
$comment = wp_insert_comment(['comment_post_ID' => $post, 'comment_content' => 'zz-hi', 'comment_approved' => '0']);
$ids['comment'] = (int) $comment;
$commentMeta = add_comment_meta($comment, 'zz_hi_key', 'v');
$ids['comment_meta'] = (int) $commentMeta;
$run('delete_metadata_by_mid, a comment', static fn () => delete_metadata_by_mid('comment', $commentMeta));
wp_delete_comment($comment, true);

$listen(['auth_post_post_meta_zz_hi_key', 'auth_post_meta_zz_hi_key']);
wp_set_current_user(1);
$run('edit_post_meta for a post', static fn () => current_user_can('edit_post_meta', $post, 'zz_hi_key'));
wp_set_current_user(0);

// ---- posts -----------------------------------------------------------------------------
$listen(['default_category_post_types', 'add_trashed_suffix_to_trashed_posts']);
$run('wp_set_post_categories, none', static fn () => wp_set_post_categories($post, []));
$run('a post saved', static fn () => is_int(wp_insert_post(['ID' => $post, 'post_title' => 'zz-hi', 'post_name' => 'zz-hi', 'post_status' => 'publish', 'post_content' => 'zz again'])));
wp_trash_post($post);
$run('a post saved under a trashed one\'s slug', static function () use (&$ids) {
    $id = wp_insert_post(['post_title' => 'zz-hi-2', 'post_name' => 'zz-hi', 'post_status' => 'publish', 'post_content' => 'zz']);
    $ids['post2'] = (int) $id;
    return get_post_field('post_name', $id);
});
$run('a post titled, no slug given', static function () use (&$ids) {
    $id = wp_insert_post(['post_title' => 'zz-hi', 'post_status' => 'draft', 'post_content' => 'zz']);
    $ids['post3'] = (int) $id;
    return get_post_field('post_name', $id);
});
wp_delete_post($ids['post3'], true);

// ---- users -----------------------------------------------------------------------------
$listen(['is_user_member_of_blog', 'pre_get_blogs_of_user', 'the_author_posts_link', 'retreive_password', 'password_reset_key_expired', 'password_reset_expiration']);
$run('is_user_member_of_blog', static fn () => is_user_member_of_blog(1));
$run('get_blogs_of_user', static fn () => array_keys(get_blogs_of_user(1)));
$GLOBALS['authordata'] = get_userdata(1);
$run('get_the_author_posts_link', static fn () => get_the_author_posts_link());
unset($GLOBALS['authordata']);
$user = get_userdata(1);
$keyAt = null;
$run('get_password_reset_key', static function () use ($user, &$keyAt) {
    $keyAt = get_password_reset_key($user);
    return is_string($keyAt) ? 'a key' : $keyAt;
});
add_filter('password_reset_expiration', $expire = static fn (): int => -10, 5);
$run('check_password_reset_key, expired', static fn () => check_password_reset_key((string) $keyAt, $user->user_login));
remove_filter('password_reset_expiration', $expire, 5);
$wpdb->update($wpdb->users, ['user_activation_key' => ''], ['ID' => 1]);
clean_user_cache(1);

// ---- cron, memory, languages -------------------------------------------------------------
$listen(['wp_next_scheduled', 'cron_memory_limit', 'get_available_languages']);
$run('wp_next_scheduled, none', static fn () => wp_next_scheduled('zz_hi_event', ['a']));
wp_schedule_single_event(time() + 3600, 'zz_hi_event', ['a']);
$run('wp_next_scheduled, one', static fn () => wp_next_scheduled('zz_hi_event', ['a']) > time());
wp_clear_scheduled_hook('zz_hi_event', ['a']);
$memory = ini_get('memory_limit');
foreach (['cron', 'image', 'admin'] as $context) {
    ini_set('memory_limit', '256M');
    $run("wp_raise_memory_limit {$context}", static fn () => wp_raise_memory_limit($context));
}
ini_set('memory_limit', $memory);
$run('get_available_languages', static fn () => get_available_languages());

// ---- menus -----------------------------------------------------------------------------
$listen(['wp_get_nav_menu_object', 'pre_wp_setup_nav_menu_item']);
$menu = wp_create_nav_menu('zz-hi-menu');
$ids['menu'] = (int) $menu;
$run('wp_get_nav_menu_object by id', static fn () => wp_get_nav_menu_object($menu));
$run('wp_get_nav_menu_object by slug', static fn () => wp_get_nav_menu_object('zz-hi-menu'));
$run('wp_get_nav_menu_object, none', static fn () => wp_get_nav_menu_object('zz-hi-no-such-menu'));
$run('wp_get_nav_menu_object, nothing asked', static fn () => wp_get_nav_menu_object(0));
$item = wp_update_nav_menu_item($menu, 0, ['menu-item-title' => 'zz-hi-item', 'menu-item-url' => 'https://example.org/', 'menu-item-status' => 'publish', 'menu-item-type' => 'custom']);
$ids['item'] = (int) $item;
$run('wp_setup_nav_menu_item', static fn () => wp_setup_nav_menu_item(get_post($item))->title);
wp_delete_nav_menu($menu);

// ---- bookmarks, comments, errors -------------------------------------------------------
$listen(['link_url', 'pre_link_url', 'edit_link_url', 'link_name', 'pre_link_name', 'wp_blacklist_check', 'wp_check_comment_disallowed_list', 'wp_auto_approve_ping', 'wp_trigger_error_always_run', 'wp_trigger_error_run', 'wp_trigger_error_trigger_error']);
foreach (['display', 'raw', 'db', 'edit', 'attribute', 'js'] as $context) {
    $run("sanitize_bookmark_field link_url {$context}", static fn () => sanitize_bookmark_field('link_url', 'https://example.org/a b', 0, $context));
}
$run('sanitize_bookmark_field link_name display', static fn () => sanitize_bookmark_field('link_name', 'zz <b>name</b>', 0, 'display'));
add_action('wp_blacklist_check', '__return_null');
$run('wp_check_comment_disallowed_list', static fn () => wp_check_comment_disallowed_list('zz', 'zz@example.org', 'https://example.org', 'zz text', '127.0.0.1', 'zz-agent'));
remove_action('wp_blacklist_check', '__return_null');
$previously = get_option('comment_previously_approved');
update_option('comment_previously_approved', '1');
$run('check_comment, a pingback', static fn () => check_comment('zz', 'zz@example.org', 'https://example.org', 'zz text', '127.0.0.1', 'zz-agent', 'pingback'));
$run('check_comment, a pingback from the site', static fn () => check_comment('zz', '', home_url('/hello-world/'), 'zz text', '127.0.0.1', 'zz-agent', 'pingback'));
update_option('comment_previously_approved', '0');
$run('check_comment, a pingback, nothing previously approved', static fn () => check_comment('zz', '', 'https://example.org', 'zz text', '127.0.0.1', 'zz-agent', 'pingback'));
update_option('comment_previously_approved', $previously);
$run('check_comment, a trackback', static fn () => check_comment('zz', 'zz@example.org', 'https://example.org', 'zz text', '127.0.0.1', 'zz-agent', 'trackback'));
$run('check_comment, a comment', static fn () => check_comment('zz', 'zz@example.org', 'https://example.org', 'zz text', '127.0.0.1', 'zz-agent', 'comment'));
set_error_handler(static fn (): bool => true);
$run('wp_trigger_error', static fn () => wp_trigger_error('zz_hi', 'zz message', E_USER_NOTICE));
restore_error_handler();

// ---- scripts and styles ----------------------------------------------------------------
$listen(['print_head_scripts', 'print_footer_scripts', 'print_late_styles']);
foreach (['print_head_scripts', 'print_footer_scripts', 'print_late_styles'] as $printer) {
    $run($printer, static function () use ($printer) {
        ob_start();
        $printer();
        ob_end_clean();
        return null;
    });
}

// ---- themes ----------------------------------------------------------------------------
$listen(['validate_current_theme', 'validate_theme_requirements', 'pre_get_block_template', 'pre_get_block_templates', 'pre_wp_update_https_detection_errors']);
$run('validate_current_theme', static fn () => validate_current_theme());
$run('validate_theme_requirements', static fn () => validate_theme_requirements(get_stylesheet()));
add_filter('validate_theme_requirements', $refuse = static fn () => new WP_Error('zz_hi', 'zz'), 20);
$run('validate_theme_requirements, refused', static fn () => validate_theme_requirements(get_stylesheet()));
remove_filter('validate_theme_requirements', $refuse, 20);
add_filter('validate_current_theme', '__return_false', 20);
$run('validate_current_theme, skipped', static fn () => validate_current_theme());
remove_filter('validate_current_theme', '__return_false', 20);
$run('get_block_template', static fn () => get_block_template(get_stylesheet() . '//index')?->slug);
$run('get_block_templates', static fn () => count(get_block_templates(['slug__in' => ['index']])));
$run('wp_update_https_detection_errors', static function () {
    $kept = get_option('https_detection_errors');
    add_filter('pre_wp_update_https_detection_errors', $short = static fn () => new WP_Error('zz_hi', 'zz'), 20);
    $result = @wp_update_https_detection_errors();
    remove_filter('pre_wp_update_https_detection_errors', $short, 20);
    $stored = get_option('https_detection_errors');
    $kept === false ? delete_option('https_detection_errors') : update_option('https_detection_errors', $kept);
    return [$result, $stored];
});

// ---- the password form, options --------------------------------------------------------
$listen(['the_password_form_incorrect_password', 'the_password_form', 'enable_live_network_counts']);
$protected = wp_insert_post(['post_title' => 'zz-hi', 'post_name' => 'zz-hi', 'post_status' => 'publish', 'post_password' => 'zz', 'post_content' => 'zz']);
$ids['protected'] = (int) $protected;
$run('get_the_password_form', static fn () => strlen(get_the_password_form($protected)) > 0);
require_once ABSPATH . WPINC . '/class-phpass.php';
$_COOKIE['wp-postpass_' . COOKIEHASH] = (new PasswordHash(8, true))->HashPassword('zz-wrong');
$run('get_the_password_form, a wrong password', static fn () => strlen(get_the_password_form($protected)) > 0);
$_SERVER['HTTP_REFERER'] = get_permalink($protected);
$run('get_the_password_form, a wrong password from its page', static fn () => get_the_password_form($protected));
add_filter('the_password_form_incorrect_password', $worded = static fn () => '<b>zz</b> wrong', 20);
$run('get_the_password_form, the message reworded', static fn () => get_the_password_form($protected));
remove_filter('the_password_form_incorrect_password', $worded, 20);
unset($_COOKIE['wp-postpass_' . COOKIEHASH], $_SERVER['HTTP_REFERER']);
$run('get_the_password_form, a page sent nobody back', static fn () => get_the_password_form($protected));
$listen(['pre_cache_alloptions']);
$run('wp_load_alloptions, cached', static fn () => count(wp_load_alloptions()) > 0);
$run('wp_load_alloptions, the cache emptied', static function () {
    wp_cache_delete('alloptions', 'options');
    return count(wp_load_alloptions()) > 0;
});
$run('wp_maybe_update_user_counts', static fn () => wp_maybe_update_user_counts());

echo json_encode($log, JSON_UNESCAPED_SLASHES);
