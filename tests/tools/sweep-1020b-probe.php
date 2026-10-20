<?php
/**
 * What the facade doubts' second wave settled as security fixes
 * (docs/security-review-2026-10-20.md, "The doubts' security fixes"), each
 * as the reference does it: users in the edit context and a user reassigned
 * to itself; a good sign-in ends a pending reset; a template part's tag
 * through kses; term data of a taxonomy visitors cannot see; the read-more
 * text and the recent-posts links escaped; wpdb's prefix, empty queries and
 * placeholder; nav menus kept out of the sitemaps; active plugins that are
 * not plugin files; an old plugin deleted by its own folder only; sticky,
 * revision and protected-post comment permissions; is_serialized; the RSS
 * widget's address; sendmail's safe characters; REST arguments checked as
 * they are sanitized; the media list's MIME types; upgrader skins' errors.
 * Same protocol as api-probe.php; the probe's rows (zzsweepb) are removed
 * after, and every request the RSS widget makes is answered by a filter.
 */

require_once ABSPATH . 'wp-admin/includes/user.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$code = static fn ($value) => is_wp_error($value) ? $value->get_error_code() : $value;
$made = ['posts' => [], 'users' => [], 'terms' => [], 'files' => []];
$before = get_current_user_id();
$grant = [];
$granting = static function ($all, $caps, $args, $user) use (&$grant) {
    foreach ($grant[$user->ID] ?? [] as $cap => $value) {
        $all[$cap] = $value;
    }
    return $all;
};
add_filter('user_has_cap', $granting, 10, 4);
try {
    foreach (['administrator', 'editor', 'author', 'contributor', 'subscriber'] as $role) {
        $id = wp_insert_user(['user_login' => 'zzsweepb_' . $role, 'user_pass' => 'zzsweepb-Pass-1', 'role' => $role, 'user_email' => "zzsweepb_{$role}@example.test"]);
        if (is_wp_error($id)) {
            throw new RuntimeException($id->get_error_message());
        }
        $made['users'][$role] = $id;
    }
    $users = $made['users'];

    // Users: the edit context needs edit_user, a list shows edit fields only for users the caller may edit, and a user is never reassigned to itself.
    $grant[$users['contributor']] = ['list_users' => true];
    wp_set_current_user($users['contributor']);
    $one = rest_do_request(new WP_REST_Request('GET', '/wp/v2/users/' . $users['author']));
    $edit = new WP_REST_Request('GET', '/wp/v2/users/' . $users['author']);
    $edit->set_param('context', 'edit');
    $edit = rest_do_request($edit);
    $say('another user in the edit context, with list_users only', [$one->get_status(), $edit->get_status(), $edit->get_data()['code'] ?? null]);
    $grant = [];
    wp_set_current_user($users['administrator']);
    $delete = new WP_REST_Request('DELETE', '/wp/v2/users/' . $users['subscriber']);
    $delete->set_param('force', true);
    $delete->set_param('reassign', $users['subscriber']);
    $delete = rest_do_request($delete);
    $say('a user reassigned to itself', [$delete->get_status(), $delete->get_data()['code'] ?? null, (bool) get_userdata($users['subscriber'])]);

    // A good sign-in ends a pending password reset.
    $key = get_password_reset_key(get_userdata($users['author']));
    $signed = wp_signon(['user_login' => 'zzsweepb_author', 'user_password' => 'zzsweepb-Pass-1', 'remember' => false]);
    clean_user_cache($users['author']);
    $say('a reset key after a good sign-in', [$signed instanceof WP_User, $code(check_password_reset_key($key, 'zzsweepb_author'))]);
    wp_set_current_user($users['administrator']);

    // A template part's tag through kses, inside its own block and outside it.
    $say('a template part tag', [
        filter_block_content('<!-- wp:template-part {"tagName":"script","slug":"x"} /-->', 'post'),
        filter_block_content('<!-- wp:template-part {"tagName":"aside","slug":"x"} /-->', 'post'),
        filter_block_content('<!-- wp:paragraph {"tagName":"script"} /-->', 'post'),
        array_map(static fn ($args) => filter_block_core_template_part_attributes(...$args), [['header', 'tagName', 'post'], ['div', 'tagName', 'post'], ['script', 'tagName', 'post'], ['', 'tagName', 'post'], [null, 'tagName', 'post'], ['header', 'tagName', ['header' => []]], ['HEADER', 'tagName', 'post'], ['script', 'className', 'post']]),
    ]);

    // Term data of a taxonomy visitors cannot see.
    register_taxonomy('zzsweepb_hidden', 'post', ['public' => false, 'publicly_queryable' => false]);
    register_taxonomy('zzsweepb_shown', 'post', ['public' => true]);
    $hidden = wp_insert_term('zzsweepb hidden term', 'zzsweepb_hidden', ['description' => 'zzsweepb secret']);
    $shown = wp_insert_term('zzsweepb shown term', 'zzsweepb_shown');
    $made['terms'] = [[$hidden['term_id'] ?? 0, 'zzsweepb_hidden'], [$shown['term_id'] ?? 0, 'zzsweepb_shown']];
    $terms = [];
    foreach ([0, $users['administrator']] as $who) {
        wp_set_current_user($who);
        foreach ([[$hidden['term_id'], 'zzsweepb_hidden'], [$shown['term_id'], 'zzsweepb_shown']] as [$termId, $taxonomy]) {
            $block = new WP_Block(['blockName' => 'core/paragraph', 'attrs' => [], 'innerBlocks' => [], 'innerHTML' => '<p></p>', 'innerContent' => ['<p></p>']]);
            $block->context = ['termId' => $termId, 'taxonomy' => $taxonomy];
            $terms[] = array_map(static fn ($key) => _block_bindings_term_data_get_value(['key' => $key], $block, 'content'), ['name', 'description', 'count', 'parent']);
        }
    }
    $say('term data for a visitor and an administrator', $terms);
    wp_set_current_user($users['administrator']);

    // The read-more text and the recent-posts links.
    $post = wp_insert_post(['post_title' => '', 'post_content' => 'zzsweepb body', 'post_status' => 'publish', 'post_author' => $users['administrator']]);
    $made['posts'][] = $post;
    $readMore = new WP_Block(['blockName' => 'core/read-more', 'attrs' => ['content' => '<b>Go</b><script>zz()</script><img src=x onerror=zz()>'], 'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => []], ['postId' => $post]);
    $say('read-more text through the post allowlist', str_replace((string) $post, '{id}', preg_replace('/href="[^"]*"/', 'href="{link}"', $readMore->render())));
    $link = static fn () => 'http://zz.example/a"b<c> d';
    add_filter('post_link', $link);
    $pound = static fn ($url) => $url . '#zzpl';
    add_filter('the_permalink', $pound);
    ob_start();
    (new WP_Widget_Recent_Posts())->widget(['before_widget' => '', 'after_widget' => '', 'before_title' => '', 'after_title' => ''], ['number' => 1]);
    $recent = ob_get_clean();
    remove_filter('post_link', $link);
    remove_filter('the_permalink', $pound);
    $say('the recent posts widget: an untitled post and a link to escape', preg_replace('/\s+/', ' ', trim(preg_replace('/^.*?(<ul>.*<\/ul>).*$/s', '$1', $recent))));

    // wpdb: a prefix it cannot use, queries of nothing, the placeholder's shape.
    global $wpdb;
    $prefix = $wpdb->prefix;
    $bad = $wpdb->set_prefix('zz-bad;');
    $say('wpdb refuses a prefix, runs no empty query', [$code($bad), $wpdb->prefix === $prefix, $wpdb->query(''), $wpdb->query("  \n "), preg_match('/^\{[0-9a-f]{64}\}$/', $wpdb->placeholder_escape())]);

    // Nav menus are no sitemap, whatever is asked.
    $sitemap = new WP_Sitemaps_Taxonomies();
    $say('sitemap lists for a taxonomy that is not one of its own', [$sitemap->get_url_list(1, 'nav_menu'), $sitemap->get_url_list(1, 'zzsweepb_hidden'), $sitemap->get_max_num_pages('nav_menu'), (new WP_Sitemaps_Posts())->get_url_list(1, 'nav_menu_item')]);

    // Active plugins that are not plugin files are never loaded.
    $made['files'] = [WP_PLUGIN_DIR . '/zzsweepb-one.php', WP_PLUGIN_DIR . '/zzsweepb.txt', dirname(WP_PLUGIN_DIR) . '/zzsweepb-outside.php'];
    foreach ($made['files'] as $file) {
        file_put_contents($file, "<?php\n");
    }
    $active = static fn () => ['zzsweepb-one.php', '/zzsweepb-one.php', './zzsweepb-one.php', '../zzsweepb-outside.php', 'zz/../../zzsweepb-outside.php', 'zzsweepb.txt', 'zzsweepb-missing/x.php'];
    add_filter('pre_option_active_plugins', $active);
    $say('active plugins loaded', array_map(static fn ($file) => str_replace(WP_PLUGIN_DIR, '{plugins}', $file), wp_get_active_and_valid_plugins()));
    remove_filter('pre_option_active_plugins', $active);

    // An old plugin is deleted by its own folder, never the plugins folder (a filesystem that only records).
    $GLOBALS['zzsweepb_fs'] = [];
    $previousFs = $GLOBALS['wp_filesystem'] ?? null;
    $GLOBALS['wp_filesystem'] = new class () extends WP_Filesystem_Base {
        public function wp_plugins_dir()
        {
            return '/zz/plugins/';
        }
        public function exists($path)
        {
            $GLOBALS['zzsweepb_fs'][] = ['exists', $path];
            return true;
        }
        public function is_dir($path)
        {
            $GLOBALS['zzsweepb_fs'][] = ['is_dir', $path];
            return true;
        }
        public function is_file($path)
        {
            $GLOBALS['zzsweepb_fs'][] = ['is_file', $path];
            return false;
        }
        public function delete($file, $recursive = false, $type = false)
        {
            $GLOBALS['zzsweepb_fs'][] = ['delete', $file, $recursive];
            return true;
        }
    };
    $upgrader = new Plugin_Upgrader(new WP_Upgrader_Skin());
    $deletes = [];
    foreach (['zz-dir/zz.php', '/x.php', 'single.php'] as $plugin) {
        $GLOBALS['zzsweepb_fs'] = [];
        $result = $upgrader->delete_old_plugin(true, '', '', ['plugin' => $plugin]);
        $deletes[] = [$plugin, $code($result), array_values(array_filter($GLOBALS['zzsweepb_fs'], static fn ($call) => $call[0] === 'delete'))];
    }
    $GLOBALS['wp_filesystem'] = $previousFs;
    $say('an old plugin deleted', $deletes);

    // Sticky posts, revision deletes and a protected post's comments.
    $object = new WP_REST_Posts_Controller('post');
    $sticky = [];
    foreach (['author' => [], 'editor' => [], 'contributor' => ['edit_others_posts' => true]] as $role => $caps) {
        $grant = [$users[$role] => $caps];
        wp_set_current_user($users[$role]);
        $request = new WP_REST_Request('POST', '/wp/v2/posts');
        $request->set_param('sticky', true);
        $sticky[$role] = $code($object->create_item_permissions_check($request));
    }
    $grant = [];
    $say('who may make a post sticky', $sticky);
    wp_set_current_user($users['administrator']);
    $parent = wp_insert_post(['post_title' => 'zzsweepb parent', 'post_status' => 'publish', 'post_content' => 'a']);
    $made['posts'][] = $parent;
    wp_update_post(['ID' => $parent, 'post_content' => 'b']);
    $revision = array_key_first(wp_get_post_revisions($parent));
    $deny = static fn ($caps, $cap, $uid, $args) => in_array($cap, ['edit_post', 'read_post'], true) && (int) ($args[0] ?? 0) === $parent ? ['do_not_allow'] : $caps;
    add_filter('map_meta_cap', $deny, 10, 4);
    $request = new WP_REST_Request('DELETE', "/wp/v2/posts/{$parent}/revisions/{$revision}");
    $request->set_param('parent', $parent);
    $request->set_param('id', $revision);
    $request->set_param('force', true);
    $direct = $code((new WP_REST_Revisions_Controller('post'))->delete_item_permissions_check($request));
    $served = rest_do_request($request);
    remove_filter('map_meta_cap', $deny, 10);
    $say('deleting a revision of a post the user may not edit', [$direct, $served->get_status(), $served->get_data()['code'] ?? null, (bool) get_post($revision)]);
    $protected = wp_insert_post(['post_title' => 'zzsweepb protected', 'post_status' => 'publish', 'post_password' => 'pw', 'post_author' => $users['author']]);
    $made['posts'][] = $protected;
    $comments = new WP_REST_Comments_Controller();
    $read = new ReflectionMethod($comments, 'check_read_post_permission');
    $read->setAccessible(true);
    $readers = [];
    foreach (['visitor' => 0, 'subscriber' => $users['subscriber'], 'contributor' => $users['contributor'], 'author' => $users['author'], 'editor' => $users['editor']] as $who => $id) {
        wp_set_current_user($id);
        $readers[$who] = $read->invoke($comments, get_post($protected), new WP_REST_Request('GET', '/wp/v2/comments'));
    }
    $say('who may read a protected post\'s comments', $readers);
    wp_set_current_user($users['administrator']);

    // is_serialized, strict and loose, and what maybe_serialize stores.
    $serialized = [];
    foreach (['N;', ' N; ', 'b:1;x', 'b:2;', 'i:5;x', 'i:a;', 'd:x;', 'd:1e5;', 's:1:"x";garbage', 's:1:"x;', 's::"x";', 's:1:x";', 'a:1:{i:0;s:1:"x";garbage', 'a:1:{xxxx', 'a:1:{xxxx}', 'a:1:{', 'O:8:"stdClass"', 'C:3:"Foo":0:{}', 'E:3:"a:b";', 'E:3:"a:b";x', 'b:1;}', 'a:1:{}x', 'O:1:"a":0:{}}', 'r:1;', 's:";', 's:x"x";'] as $text) {
        $serialized[] = [$text, (int) is_serialized($text), (int) is_serialized($text, false), (int) is_serialized_string($text), maybe_serialize($text)];
    }
    $say('serialized or not', $serialized);

    // The RSS widget: the address from its first "http", never the site's own.
    $asked = [];
    $feed = static function ($pre, $args, $url) use (&$asked) {
        $asked[] = $url;
        return ['headers' => ['content-type' => 'application/rss+xml'], 'body' => '<?xml version="1.0"?><rss version="2.0"><channel><title>' . (str_contains($url, 'notitle') ? '' : 'Zz Feed') . '</title><link>http://zzfeed.example/</link><description>Zz feed description</description><item><title>One</title><link>http://zzfeed.example/1</link></item></channel></rss>', 'response' => ['code' => 200, 'message' => 'OK'], 'cookies' => [], 'filename' => null];
    };
    add_filter('pre_http_request', $feed, 10, 3);
    $noCache = static fn () => 0;
    add_filter('wp_feed_cache_transient_lifetime', $noCache);
    $rss = [];
    $home = home_url();
    foreach (['junk http://zzfeed.example/sweepb', 'zzfeed.example/sweepb3', 'xHTTP://zzfeed.example/sweepb4', 'http://zzfeed.example/sweepb-notitle', site_url(), $home, home_url('/'), site_url('/'), 'xx' . $home, untrailingslashit($home) . '/feed/'] as $url) {
        $asked = [];
        ob_start();
        (new WP_Widget_RSS())->widget(['before_widget' => '<W>', 'after_widget' => '</W>', 'before_title' => '<T>', 'after_title' => '</T>'], ['url' => $url, 'items' => 1, 'title' => '']);
        $html = ob_get_clean();
        $mask = static fn (string $text): string => str_replace([$home, set_url_scheme($home, 'http')], '{home}', $text);
        $rss[] = [$mask($url), $mask(implode(' ', $asked)), $mask((string) preg_replace('/\s+/', ' ', $html))];
        foreach ($asked as $fetched) {
            delete_site_transient('feed_' . md5($fetched));
            delete_site_transient('feed_mod_' . md5($fetched));
        }
    }
    remove_filter('pre_http_request', $feed, 10);
    remove_filter('wp_feed_cache_transient_lifetime', $noCache);
    $say('the RSS widget\'s address', $rss);

    // Characters sendmail's command line may carry.
    require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
    $safe = new ReflectionMethod(PHPMailer\PHPMailer\PHPMailer::class, 'isShellSafe');
    $safe->setAccessible(true);
    $say('shell-safe sender addresses', array_map(static fn ($text) => [$text, $safe->invoke(null, $text)], ['a+b@x.com', 'a=b@x.com', 'ä@example.com', 'a_b-c.d@x.com', '-oQ@x.com', "a'b@x.com", 'a b@x.com', 'a~b@x.com', '', '@', "a\nb@x.com", 'A@X.COM']));

    // REST arguments: validated only by a callback they name, checked against their schema as they are sanitized.
    $argRows = [];
    foreach ([
        'integer' => [['type' => 'integer'], 'abc'],
        'integer, own sanitize' => [['type' => 'integer', 'sanitize_callback' => static fn ($v) => $v . '!'], 'abc'],
        'integer, own validate' => [['type' => 'integer', 'validate_callback' => static fn () => true], 'abc'],
        'integer, sanitize null' => [['type' => 'integer', 'sanitize_callback' => null], 'abc'],
        'enum' => [['type' => 'string', 'enum' => ['a', 'b']], 'abc'],
        'enum without a type' => [['enum' => ['a', 'b']], 'abc'],
        'boolean' => [['type' => 'boolean'], 'yes'],
        'array of integers' => [['type' => 'array', 'items' => ['type' => 'integer']], ['1', 'x']],
        'minimum' => [['type' => 'integer', 'minimum' => 10], '5'],
        'validate false' => [['type' => 'integer', 'validate_callback' => static fn () => false], '5'],
    ] as $label => [$arg, $value]) {
        $request = new WP_REST_Request('GET', '/zz');
        $request->set_attributes(['args' => ['x' => $arg]]);
        $request->set_query_params(['x' => $value]);
        $valid = $request->has_valid_params();
        $sanitized = $request->sanitize_params();
        $argRows[$label] = [is_wp_error($valid) ? $valid->get_error_data()['params'] : $valid, is_wp_error($sanitized) ? $sanitized->get_error_data()['params'] : $sanitized, $request->get_param('x')];
    }
    $say('REST arguments checked', $argRows);

    // The media list's MIME types: the media types', then each allowed one named.
    $media = new WP_REST_Attachments_Controller('attachment');
    $query = new ReflectionMethod($media, 'prepare_items_query');
    $query->setAccessible(true);
    $mimes = [];
    foreach ([[['image'], ['application/pdf']], [[], ['text/x-zz-notallowed']], [[], ['image/jpeg', 'text/x-zz', 'application/pdf']], [['image'], ['image/jpeg']], [[], ['IMAGE/JPEG']], [['bogus'], ['application/pdf']]] as [$types, $named]) {
        $request = new WP_REST_Request('GET', '/wp/v2/media');
        $types && $request->set_param('media_type', $types);
        $request->set_param('mime_type', $named);
        $prepared = $query->invoke($media, [], $request)['post_mime_type'] ?? 'unset';
        $mimes[] = is_array($prepared) ? [count($prepared), array_slice($prepared, -2)] : $prepared;
    }
    $say('media list MIME types', $mimes);

    // Upgrader skins' errors: the error's own data after each message; the bulk skin keeps it and hides the spinner.
    $error = new WP_Error('c1', 'Msg one', 'Data one');
    $error->add('c2', 'Msg two', 'Data two');
    $error->add('c3', '<i>Msg</i> three');
    $error->add('c1', 'Msg one b');
    $html = new WP_Error('h', 'Html data', '<b>bold</b> & x');
    // The plain skin's lines are recorded as feedback (show_message would flush every buffer).
    $recording = new class () extends WP_Upgrader_Skin {
        public $lines = [];
        public function feedback($feedback, ...$args)
        {
            $this->lines[] = $feedback;
        }
    };
    $skins = [];
    foreach ([$error, $html, 'up_to_date', new WP_Error()] as $given) {
        $skin = clone $recording;
        $skin->done_header = true;
        $skin->set_upgrader($upgrader);
        ob_start();
        $skin->error($given);
        $skins[] = ['WP_Upgrader_Skin', ob_get_clean(), $skin->lines];
    }
    foreach ([Bulk_Upgrader_Skin::class] as $class) {
        foreach ([$error, $html, 'up_to_date', new WP_Error()] as $given) {
            $skin = new $class();
            $skin->done_header = true;
            $skin->set_upgrader($upgrader);
            $upgrader->update_current = 3;
            ob_start();
            $skin->error($given);
            $skins[] = [$class, ob_get_clean(), $skin->error ?? null];
        }
    }
    $say('upgrader skin errors', $skins);
} finally {
    foreach ($made['files'] as $file) {
        @unlink($file);
    }
    remove_filter('user_has_cap', $granting, 10);
    wp_set_current_user($before);
    foreach ($made['posts'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_post($id, true);
        }
    }
    foreach ($made['terms'] as [$termId, $taxonomy]) {
        if (is_int($termId) && $termId > 0) {
            wp_delete_term($termId, $taxonomy);
        }
    }
    foreach ($made['users'] as $id) {
        if (is_int($id) && $id > 0 && get_userdata($id)) {
            wp_delete_user($id);
        }
    }
}

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
