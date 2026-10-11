<?php
/**
 * Loose functions left (probe loose-functions): each feed template called
 * by its own loader in a feed's main query; auth_redirect() for a visitor,
 * a signed-in cookie, a current user with no cookie, and its scheme
 * filtered (the redirect stopped before it exits); and core's ability
 * categories and abilities seeded inside their own actions on a registry
 * cleared of them, then asked for outside those actions. The posts, the
 * session and the registry entries the probe makes are removed or put back.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
if (!did_action('init')) {
    do_action('init');
}
add_filter('doing_it_wrong_trigger_error', '__return_false');
$notices = [];
add_action('doing_it_wrong_run', static function ($function, $message) use (&$notices) {
    $notices[] = [$function, wp_strip_all_tags((string) $message)];
}, 10, 2);

// The feeds: two posts, one with a comment, the main query a feed of them.
$made = ['posts' => [], 'comments' => []];
register_shutdown_function(static function () use (&$made): void {
    foreach ($made['comments'] as $id) {
        if (is_int($id) && $id > 1) {
            wp_delete_comment($id, true);
        }
    }
    foreach ($made['posts'] as $id) {
        if (is_int($id) && $id > 1) {
            wp_delete_post($id, true);
        }
    }
});
$author = (int) (get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID', 'orderby' => 'ID'])[0] ?? 1);
foreach (['ZZ lf first' => '2026-09-02 10:00:00', 'ZZ lf second' => '2026-09-02 11:00:00'] as $title => $date) {
    $post = wp_insert_post(['post_title' => $title, 'post_content' => "<p>{$title} body &amp; more.</p>", 'post_status' => 'publish', 'post_author' => $author, 'post_date' => $date, 'post_date_gmt' => $date]);
    if (!is_int($post) || $post <= 1) {
        fwrite(STDERR, "no fixture\n");
        exit(1);
    }
    $made['posts'][] = $post;
}
$comment = wp_insert_comment(['comment_post_ID' => $made['posts'][0], 'comment_author' => 'ZZ lf reader', 'comment_author_email' => 'zz-lf@example.com', 'comment_content' => 'ZZ lf comment.', 'comment_approved' => 1, 'comment_date' => '2026-09-02 12:00:00', 'comment_date_gmt' => '2026-09-02 12:00:00']);
if (!is_int($comment) || $comment <= 1) {
    fwrite(STDERR, "no fixture\n");
    exit(1);
}
$made['comments'][] = $comment;
$host = (string) parse_url(home_url(), PHP_URL_HOST);
$mask = static function (string $out) use ($made, $comment, $host): string {
    $out = str_replace([$host, '?p=' . $made['posts'][0], '?p=' . $made['posts'][1], 'comment-' . $comment], ['{host}', '?p={first}', '?p={second}', 'comment-{comment}'], $out);
    return (string) preg_replace('/<lastBuildDate>[^<]*<\/lastBuildDate>|<updated>[^<]*<\/updated>|<dc:date>[^<]*\t<\/dc:date>/', '{built}', $out);
};
// Each template prints once a request, so each loader is asked once.
$feed = static function (array $vars, Closure $load) use ($mask): string {
    query_posts($vars);
    $GLOBALS['post'] = $GLOBALS['wp_query']->post;
    ob_start();
    $load();
    $out = (string) ob_get_clean();
    wp_reset_query();
    return $mask($out);
};
$posts = ['post__in' => $made['posts'], 'orderby' => 'date', 'order' => 'DESC'];
$say('feed loaders', [
    'rss2' => $feed(['feed' => 'rss2'] + $posts, static fn () => do_feed_rss2(false)),
    'rss2 comments' => $feed(['feed' => 'rss2', 'p' => $made['posts'][0]], static fn () => do_feed_rss2(true)),
    'atom' => $feed(['feed' => 'atom'] + $posts, static fn () => do_feed_atom(false)),
    'atom comments' => $feed(['feed' => 'atom', 'p' => $made['posts'][0]], static fn () => do_feed_atom(true)),
    'rdf' => $feed(['feed' => 'rdf'] + $posts, static fn () => do_feed_rdf()),
    'rss' => $feed(['feed' => 'rss'] + $posts, static fn () => do_feed_rss()),
]);

// auth_redirect: the redirect stopped at wp_redirect, before it exits (WP-CLI's own redirect warning is taken off first).
$cli = has_filter('wp_redirect', 'WP_CLI\\Utils\\wp_redirect_handler');
if ($cli !== false) {
    remove_filter('wp_redirect', 'WP_CLI\\Utils\\wp_redirect_handler', $cli);
}
$_SERVER['HTTP_HOST'] = $host;
$_SERVER['REQUEST_URI'] = '/wp-admin/edit.php?post_type=page&zz=1';
$guard = static function () use ($host) {
    $heard = [];
    $listener = static function ($user_id) use (&$heard) {
        $heard[] = $user_id;
    };
    add_action('auth_redirect', $listener);
    $stop = static function ($location, $status) {
        throw new RuntimeException((string) json_encode([$location, $status], JSON_UNESCAPED_SLASHES));
    };
    add_filter('wp_redirect', $stop, 10, 2);
    try {
        auth_redirect();
        $out = ['passed', $heard];
    } catch (RuntimeException $e) {
        $out = ['redirected', json_decode(str_replace($host, '{host}', $e->getMessage()), true), $heard];
    }
    remove_filter('wp_redirect', $stop, 10);
    remove_action('auth_redirect', $listener);
    return $out;
};
global $wpdb;
$sessions = $wpdb->get_row($wpdb->prepare("SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = 1 AND meta_key = %s", 'session_tokens'), ARRAY_A);
register_shutdown_function(static function () use ($sessions): void {
    global $wpdb;
    if (is_array($sessions)) {
        $wpdb->update($wpdb->usermeta, ['meta_value' => $sessions['meta_value']], ['user_id' => 1, 'meta_key' => 'session_tokens']);
    } else {
        $wpdb->delete($wpdb->usermeta, ['user_id' => 1, 'meta_key' => 'session_tokens']);
    }
    wp_cache_delete(1, 'user_meta');
});
$auth = [];
unset($_COOKIE[AUTH_COOKIE], $_COOKIE[SECURE_AUTH_COOKIE], $_COOKIE[LOGGED_IN_COOKIE], $_SERVER['HTTPS']);
wp_set_current_user(0);
$auth['a visitor over http'] = $guard();
$auth['a page over http'] = (static function () use ($guard) {
    $_SERVER['REQUEST_URI'] = '/zz-page/?a=1';
    $out = $guard();
    $_SERVER['REQUEST_URI'] = '/wp-admin/edit.php?post_type=page&zz=1';
    return $out;
})();
$auth['a full address over http'] = (static function () use ($guard, $host) {
    $_SERVER['REQUEST_URI'] = "http://{$host}/wp-admin/zz.php";
    $out = $guard();
    $_SERVER['REQUEST_URI'] = '/wp-admin/edit.php?post_type=page&zz=1';
    return $out;
})();
$auth['secure redirects filtered off'] = (static function () use ($guard) {
    add_filter('secure_auth_redirect', '__return_false');
    $out = $guard();
    remove_filter('secure_auth_redirect', '__return_false');
    return $out;
})();
// Over https from here: the secure sign-in cookie is the one asked for.
$_SERVER['HTTPS'] = 'on';
$auth['a visitor'] = $guard();
wp_set_current_user(1);
$auth['a current user with no cookie'] = $guard();
wp_set_current_user(0);
$token = WP_Session_Tokens::get_instance(1)->create(time() + 3600);
$_COOKIE[SECURE_AUTH_COOKIE] = wp_generate_auth_cookie(1, time() + 3600, 'secure_auth', $token);
$auth['a secure sign-in cookie'] = $guard();
$auth['a bad cookie'] = (static function () use ($guard) {
    $kept = $_COOKIE[SECURE_AUTH_COOKIE];
    $_COOKIE[SECURE_AUTH_COOKIE] = 'zz|1|2|3';
    $out = $guard();
    $_COOKIE[SECURE_AUTH_COOKIE] = $kept;
    return $out;
})();
unset($_COOKIE[SECURE_AUTH_COOKIE]);
$_COOKIE[AUTH_COOKIE] = wp_generate_auth_cookie(1, time() + 3600, 'auth', $token);
$auth['the plain cookie over https'] = $guard();
unset($_COOKIE[AUTH_COOKIE]);
$_COOKIE[LOGGED_IN_COOKIE] = wp_generate_auth_cookie(1, time() + 3600, 'logged_in', $token);
$auth['the logged-in scheme, filtered'] = (static function () use ($guard) {
    $scheme = static fn () => 'logged_in';
    add_filter('auth_redirect_scheme', $scheme);
    $out = $guard();
    remove_filter('auth_redirect_scheme', $scheme);
    return $out;
})();
$auth['the logged-in cookie alone'] = $guard();
unset($_COOKIE[LOGGED_IN_COOKIE], $_SERVER['HTTPS']);
WP_Session_Tokens::get_instance(1)->destroy($token);
wp_set_current_user(0);
$say('auth redirect', $auth);
$_SERVER['REQUEST_URI'] = '/';

// Core's abilities, seeded inside their actions on a registry cleared of them.
$abilities = ['core/get-site-info', 'core/get-user-info', 'core/get-environment-info'];
$categories = ['site', 'user'];
$describe = static function () use ($abilities, $categories) {
    $out = ['categories' => [], 'abilities' => []];
    foreach ($categories as $slug) {
        $category = wp_get_ability_category($slug);
        $out['categories'][$slug] = $category ? [$category->get_label(), $category->get_description(), $category->get_meta()] : null;
    }
    foreach ($abilities as $name) {
        $ability = wp_get_ability($name);
        $out['abilities'][$name] = $ability ? [$ability->get_label(), $ability->get_description(), $ability->get_category(), $ability->get_input_schema(), $ability->get_output_schema(), $ability->get_meta()] : null;
    }
    return $out;
};
$seeded = ['before' => $describe()];
foreach ($abilities as $name) {
    wp_unregister_ability($name);
}
foreach ($categories as $slug) {
    wp_unregister_ability_category($slug);
}
$seeded['cleared'] = $describe();
$notices = [];
remove_all_actions('wp_abilities_api_categories_init');
add_action('wp_abilities_api_categories_init', static function () {
    wp_register_core_ability_categories();
});
do_action('wp_abilities_api_categories_init', WP_Ability_Categories_Registry::get_instance());
remove_all_actions('wp_abilities_api_init');
add_action('wp_abilities_api_init', static function () {
    wp_register_core_abilities();
});
do_action('wp_abilities_api_init', WP_Abilities_Registry::get_instance());
$seeded['seeded'] = $describe();
$seeded['seeded, notices'] = $notices;
$notices = [];
wp_register_core_ability_categories();
wp_register_core_abilities();
$seeded['outside their actions'] = $notices;
$seeded['after'] = $describe() === $seeded['before'];
$say('core abilities', $seeded);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
