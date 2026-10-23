<?php
/**
 * What the security review of 2026-10-22 fixed (docs/security-review-2026-10-22.md),
 * each as the reference does it: editorial notes listed only to people who may
 * edit their post; a plugin's own activate_plugin capability asked before REST
 * changes its status; validate_plugin's order (a path out of the plugins
 * folder, a missing file, no header) and activate_plugin asking it first;
 * delete_plugins leaving a folder named without a file; the curl transports
 * refusing anything but HTTP and HTTPS; the session manager deciding whether a
 * cookie's session is live; a query block that lists no posts querying
 * nothing. Same protocol as api-probe.php. Its users, posts, notes and plugin
 * files (zz-sweepc) are removed after, and no plugin is left active.
 */

require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
add_filter('doing_it_wrong_trigger_error', '__return_false');
$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$shape = static fn ($value) => is_wp_error($value) ? [$value->get_error_code(), $value->get_error_message()] : $value;
$users = [];
$user = static function (string $role) use (&$users): int {
    $login = 'zz-sweepc-' . $role . '-' . wp_generate_password(5, false);
    $id = wp_insert_user(['user_login' => $login, 'user_pass' => wp_generate_password(20), 'user_email' => $login . '@example.test', 'role' => $role]);
    if (is_int($id) && $id > 1) {
        $users[] = $id;
        return $id;
    }
    return 0;
};

// Notes: listed only to someone who may edit the note's post.
$editor = $user('editor');
$author = $user('author');
$contributor = $user('contributor');
$post = $author > 0 ? wp_insert_post(['post_title' => 'zz-sweepc note post', 'post_status' => 'publish', 'post_author' => $author, 'post_content' => 'x']) : 0;
$note = is_int($post) && $post > 0 && $editor > 0 ? wp_insert_comment(['comment_post_ID' => $post, 'comment_type' => 'note', 'comment_content' => 'zz-sweepc note', 'comment_approved' => 1, 'user_id' => $editor, 'comment_author' => 'zz']) : 0;
foreach (['a contributor' => $contributor, 'an editor' => $editor, 'the post author' => $author] as $who => $id) {
    wp_set_current_user($id);
    $seen = [];
    foreach (['note' => ['type' => 'note'], 'all' => ['type' => 'all'], 'the post\'s notes' => ['type' => 'note', 'post' => $post]] as $label => $params) {
        $request = new WP_REST_Request('GET', '/wp/v2/comments');
        $request->set_query_params($params + ['per_page' => 100]);
        $response = rest_do_request($request);
        $data = $response->get_data();
        $seen[$label] = [$response->get_status(), is_array($data) && array_is_list($data) && in_array($note, array_column($data, 'id'), true)];
    }
    $say("notes listed to {$who}", $seen);
}
wp_set_current_user(0);

// A plugin's own capability before REST changes its status.
$admin = $user('administrator');
$file = 'minn-test-plugin/minn-test-plugin.php';
if ($admin > 0 && !is_plugin_active($file)) {
    wp_set_current_user($admin);
    $asked = [];
    $refuse = static function ($caps, $cap, $id, $args) use (&$asked, $file) {
        if (in_array($cap, ['activate_plugin', 'deactivate_plugin'], true)) {
            $asked[] = [$cap, $args[0] ?? null];
            if (($args[0] ?? null) === $file) {
                return ['do_not_allow'];
            }
        }
        return $caps;
    };
    add_filter('map_meta_cap', $refuse, 10, 4);
    $request = new WP_REST_Request('PUT', '/wp/v2/plugins/minn-test-plugin/minn-test-plugin');
    $request->set_body_params(['status' => 'active']);
    $response = rest_do_request($request);
    $data = $response->get_data();
    $say('a plugin whose own activation is refused, over REST', [$response->get_status(), $data['code'] ?? null, $data['message'] ?? null, is_plugin_active($file), $asked]);
    $request = new WP_REST_Request('PUT', '/wp/v2/plugins/minn-test-plugin/minn-test-plugin');
    $request->set_body_params(['status' => 'inactive']);
    $say('the same plugin asked for the status it has', [rest_do_request($request)->get_status(), $asked]);
    remove_filter('map_meta_cap', $refuse, 10);
    if (is_plugin_active($file)) {
        deactivate_plugins($file, true);
    }
    wp_set_current_user(0);
}

// validate_plugin and activate_plugin.
foreach (['../index.php', 'zz-sweepc-missing.php', 'minn-test-plugin/minn-test-plugin.php', 'minn-test-plugin/../minn-test-plugin/minn-test-plugin.php', WP_PLUGIN_DIR . '/minn-test-plugin/minn-test-plugin.php', './minn-test-plugin/minn-test-plugin.php', 'minn-test-plugin/probe.css'] as $plugin) {
    $say('validate_plugin ' . str_replace(WP_PLUGIN_DIR, '{plugins}', $plugin), $shape(validate_plugin($plugin)));
}
$activeBefore = get_option('active_plugins');
foreach (['../index.php', 'minn-test-plugin/../minn-test-plugin/minn-test-plugin.php', 'zz-sweepc-missing.php', 'minn-test-plugin/probe.css'] as $plugin) {
    $say("activate_plugin {$plugin}", [$shape(activate_plugin($plugin)), get_option('active_plugins') === $activeBefore]);
}

// delete_plugins: a name with no file deletes only a file.
$folder = WP_PLUGIN_DIR . '/zz-sweepc-folder';
@mkdir($folder);
file_put_contents("{$folder}/zz.php", "<?php\n/* Plugin Name: Zz sweepc folder */\n");
file_put_contents(WP_PLUGIN_DIR . '/zz-sweepc-single.php', "<?php\n/* Plugin Name: Zz sweepc single */\n");
wp_clean_plugins_cache(false);
$say('delete_plugins a folder named without a file', [$shape(delete_plugins(['zz-sweepc-folder'])), is_dir($folder), is_file("{$folder}/zz.php")]);
$say('delete_plugins a single-file plugin', [$shape(delete_plugins(['zz-sweepc-single.php'])), is_file(WP_PLUGIN_DIR . '/zz-sweepc-single.php')]);
$say('delete_plugins the folder plugin by its file', [$shape(delete_plugins(['zz-sweepc-folder/zz.php'])), is_dir($folder)]);
@unlink("{$folder}/zz.php");
@rmdir($folder);
@unlink(WP_PLUGIN_DIR . '/zz-sweepc-single.php');

// The curl transports: HTTP and HTTPS only.
$url = 'file://' . WP_PLUGIN_DIR . '/minn-test-plugin/probe.css';
$answer = (new WP_Http_Curl())->request($url, ['method' => 'GET', 'timeout' => 5, 'headers' => [], 'body' => null, 'cookies' => [], 'stream' => false, 'filename' => null, 'decompress' => true, 'limit_response_size' => null, 'user-agent' => 'zz', 'sslverify' => false, 'httpversion' => '1.1', 'blocking' => true, 'redirection' => 0]);
$say('WP_Http_Curl asked for a file:// address', is_wp_error($answer) ? [$answer->get_error_code(), $answer->get_error_message()] : 'answered');
try {
    (new WpOrg\Requests\Transport\Curl())->request($url, [], null, ['timeout' => 5, 'connect_timeout' => 5, 'useragent' => 'zz', 'protocol_version' => 1.1, 'redirects' => 0, 'follow_redirects' => false, 'blocking' => true, 'type' => 'GET', 'filename' => false, 'auth' => false, 'proxy' => false, 'cookies' => false, 'max_bytes' => false, 'idn' => true, 'hooks' => new WpOrg\Requests\Hooks(), 'transport' => null, 'verify' => false, 'verifyname' => false, 'data_format' => 'query']);
    $say('the Requests curl transport asked for a file:// address', 'answered');
} catch (Throwable $failure) {
    $say('the Requests curl transport asked for a file:// address', [get_class($failure), $failure->getMessage()]);
}

// The session manager decides whether a cookie's session is live.
if (!class_exists('Zz_Sweepc_Refusing_Sessions', false)) {
    eval('class Zz_Sweepc_Refusing_Sessions extends WP_User_Meta_Session_Tokens { protected function get_session($verifier) { return null; } }');
}
$subscriber = $user('subscriber');
if ($subscriber > 0) {
    $expiration = time() + HOUR_IN_SECONDS;
    $auth = wp_generate_auth_cookie($subscriber, $expiration, 'auth');
    $loggedIn = wp_generate_auth_cookie($subscriber, $expiration, 'logged_in', wp_parse_auth_cookie($auth, 'auth')['token']);
    $heard = [];
    $bad = static function () use (&$heard): void {
        $heard[] = 'auth_cookie_bad_session_token';
    };
    add_action('auth_cookie_bad_session_token', $bad);
    $say('cookies under the stock session manager', [wp_validate_auth_cookie($auth, 'auth') === $subscriber, wp_validate_auth_cookie($loggedIn, 'logged_in') === $subscriber]);
    $manager = static fn () => 'Zz_Sweepc_Refusing_Sessions';
    add_filter('session_token_manager', $manager);
    $say('cookies under a manager that refuses every session', [wp_validate_auth_cookie($auth, 'auth') === $subscriber, wp_validate_auth_cookie($loggedIn, 'logged_in') === $subscriber, $heard]);
    remove_filter('session_token_manager', $manager);
    remove_action('auth_cookie_bad_session_token', $bad);
    WP_Session_Tokens::get_instance($subscriber)->destroy_all();
}

// A query block queries only when a block inside lists posts.
$queries = 0;
$count = static function () use (&$queries): void {
    $queries++;
};
add_action('pre_get_posts', $count);
$open = '<!-- wp:query {"queryId":7,"query":{"perPage":5,"postType":"post","inherit":false}} --><div class="wp-block-query">';
// The first query block a request renders makes the reference look up what it needs once; counted after that.
do_blocks($open . '<!-- wp:post-template --><!-- wp:post-title /--><!-- /wp:post-template --></div><!-- /wp:query -->');
foreach (['a query block holding only its title' => '<!-- wp:query-title {"type":"archive"} /-->', 'a query block holding a post template' => '<!-- wp:post-template --><!-- wp:post-title /--><!-- /wp:post-template -->'] as $label => $inner) {
    $queries = 0;
    do_blocks($open . $inner . '</div><!-- /wp:query -->');
    $say($label . ': queries run', $queries);
}
if ($admin > 0) {
    wp_set_current_user($admin);
    $queries = 0;
    $request = new WP_REST_Request('GET', '/wp/v2/block-renderer/core/query');
    $request->set_query_params(['context' => 'edit', 'attributes' => ['query' => ['perPage' => 5, 'postType' => 'post', 'inherit' => false]]]);
    $say('the block renderer asked for a query block: queries run', [rest_do_request($request)->get_status(), $queries]);
    wp_set_current_user(0);
}
remove_action('pre_get_posts', $count);

if (is_int($note) && $note > 0) {
    wp_delete_comment($note, true);
}
if (is_int($post) && $post > 0) {
    wp_delete_post($post, true);
}
foreach ($users as $id) {
    if (is_int($id) && $id > 1) {
        wp_delete_user($id);
    }
}
remove_filter('doing_it_wrong_trigger_error', '__return_false');
echo json_encode($log, JSON_UNESCAPED_SLASHES), "\n";
