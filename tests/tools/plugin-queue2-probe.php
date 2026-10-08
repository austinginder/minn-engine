<?php
/**
 * The second wave of the plugin catalogue's queue (probe plugin-queue2),
 * as the reference answers it: a post's galleries from [gallery] shortcodes
 * and gallery blocks (old and new formats, nested), as markup and as
 * attributes with their image sources, and the image lists built on them;
 * revoking a user's roles; the core post types registered again; the
 * whitespace pattern; the emoji script and styles a plugin asks for; a
 * user's sessions listed and destroyed; the Allow header for a REST
 * route; the direct PHP update URL; kses's global attributes; whether a
 * term is shared; WP_List_Util; the comment statuses; whether the site
 * and home URLs use https; backslashit; the tag edit link; the old
 * translation helpers; post dates resolved; the deprecated login lookup;
 * srcset and sizes added to one image; the style loader source; and
 * whether a file is a valid zip. The probe's own posts, attachments, user
 * and files, removed at the end. Same protocol as api-probe.php.
 */

global $wpdb;
$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = ['posts' => [], 'users' => [], 'files' => []];
register_shutdown_function(static function () use (&$made): void {
    foreach ($made['posts'] as $id) {
        wp_delete_post($id, true);
    }
    require_once ABSPATH . 'wp-admin/includes/user.php';
    foreach ($made['users'] as $id) {
        wp_delete_user($id);
    }
    foreach ($made['files'] as $file) {
        @unlink($file);
    }
});
$names = [];
$mask = static function ($value) use (&$mask, &$names) {
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    if (is_int($value) && isset($names[$value])) {
        return '{' . $names[$value] . '}';
    }
    if (is_string($value)) {
        $value = str_replace(home_url(), '{home}', $value);
        foreach ($names as $id => $label) {
            $value = (string) preg_replace('/(?<![0-9a-z])' . $id . '(?![0-9a-z])/i', '{' . $label . '}', $value);
        }
    }
    return $value;
};
set_error_handler(static fn () => true, E_USER_DEPRECATED | E_DEPRECATED | E_USER_NOTICE);

// Galleries: two attachments on the one image the site holds, and posts that show them.
$image = (int) (get_posts(['post_type' => 'attachment', 'post_mime_type' => 'image', 'numberposts' => 1, 'orderby' => 'ID', 'order' => 'ASC', 'fields' => 'ids'])[0] ?? 0);
$holder = (int) wp_insert_post(['post_title' => 'Zz Gallery Holder', 'post_status' => 'publish', 'post_content' => '[gallery]']);
$made['posts'][] = $holder;
$names[$holder] = 'holder';
// Each attachment gets its own copy of the file and no sizes, so removing it never touches the site's image.
$attachments = [];
$meta = wp_get_attachment_metadata($image);
foreach (['one', 'two'] as $n => $label) {
    $copy = dirname(get_attached_file($image)) . "/zz-queue-gallery-{$label}.png";
    copy(get_attached_file($image), $copy);
    $id = (int) wp_insert_attachment(['post_title' => "Zz image {$label}", 'post_mime_type' => 'image/png', 'post_status' => 'inherit', 'menu_order' => $n], $copy, $holder);
    wp_update_attachment_metadata($id, ['width' => (int) $meta['width'], 'height' => (int) $meta['height'], 'file' => dirname((string) $meta['file']) . "/zz-queue-gallery-{$label}.png"]);
    $made['posts'][] = $id;
    $made['files'][] = $copy;
    $names[$id] = "image {$label}";
    $attachments[] = $id;
}
[$one, $two] = $attachments;
$url = wp_get_attachment_url($one);
$contents = [
    'shortcode with ids' => "Intro [gallery ids=\"{$one},{$two}\" columns=\"2\"] outro",
    'shortcode of the post' => '[gallery size="thumbnail"]',
    'two shortcodes' => "[gallery ids=\"{$one}\"][gallery ids=\"{$two}\" link=\"file\"]",
    'old gallery block with ids' => "<!-- wp:gallery {\"ids\":[{$one},{$two}]} --><figure class=\"wp-block-gallery\"><ul><li><img src=\"{$url}\" data-id=\"{$one}\"/></li></ul></figure><!-- /wp:gallery -->",
    'old gallery block without ids' => "<!-- wp:gallery --><figure class=\"wp-block-gallery\"><ul><li><img src=\"{$url}\"/></li><li><img src=\"{$url}\"/></li></ul></figure><!-- /wp:gallery -->",
    'new gallery block' => "<!-- wp:gallery {\"linkTo\":\"none\"} --><figure class=\"wp-block-gallery has-nested-images\"><!-- wp:image {\"id\":{$one}} --><figure class=\"wp-block-image\"><img src=\"{$url}\" class=\"wp-image-{$one}\"/></figure><!-- /wp:image --><!-- wp:image {\"id\":{$two}} --><figure class=\"wp-block-image\"><img src=\"{$url}\" class=\"wp-image-{$two}\"/></figure><!-- /wp:image --></figure><!-- /wp:gallery -->",
    'a gallery block in a group' => "<!-- wp:group --><div class=\"wp-block-group\"><!-- wp:gallery {\"ids\":[{$two}]} --><figure class=\"wp-block-gallery\"></figure><!-- /wp:gallery --></div><!-- /wp:group -->",
    'none' => 'No gallery here.',
];
$heard = [];
add_filter('get_post_galleries', static function ($galleries, $post) use (&$heard, $mask) {
    $heard[] = $mask([count($galleries), (int) $post->ID]);
    return $galleries;
}, 10, 2);
foreach ($contents as $label => $content) {
    $id = (int) wp_insert_post(['post_title' => "Zz {$label}", 'post_status' => 'publish', 'post_content' => $content]);
    $made['posts'][] = $id;
    $names[$id] = $label;
    if ($label === 'shortcode of the post') {
        foreach ($attachments as $attachment) {
            wp_update_post(['ID' => $attachment, 'post_parent' => $id]);
        }
    }
    $say("get_post_galleries {$label}, markup", $mask(get_post_galleries($id)));
    $say("get_post_galleries {$label}, data", $mask(get_post_galleries($id, false)));
    $say("get_post_gallery {$label}", $mask(get_post_gallery($id, false)));
    $say("get_post_gallery_images {$label}", $mask(get_post_gallery_images($id)));
    $say("get_post_galleries_images {$label}", $mask(get_post_galleries_images($id)));
}
$say('get_post_galleries of nothing', get_post_galleries(0));
$say('get_post_galleries_images of nothing', get_post_galleries_images(-1));
$say('get_post_galleries filter heard', $heard);

// Revoking a user.
$user = (int) wp_insert_user(['user_login' => 'zz_queue_user', 'user_pass' => wp_generate_password(), 'user_email' => 'zz-queue@example.com', 'role' => 'editor']);
$made['users'][] = $user;
$names[$user] = 'user';
$person = get_userdata($user);
$person->add_cap('zz_extra');
wp_revoke_user($user);
clean_user_cache($user);
$person = get_userdata($user);
$say('wp_revoke_user', ['roles' => $person->roles, 'caps' => $person->caps, 'level' => get_user_meta($user, $wpdb->get_blog_prefix() . 'user_level', true), 'can edit_posts' => user_can($user, 'edit_posts')]);

// The core post types, registered again.
$before = [array_keys(get_post_types()), array_keys(get_post_stati())];
create_initial_post_types();
$say('create_initial_post_types', [array_keys(get_post_types()) === $before[0], array_keys(get_post_stati()) === $before[1], get_post_type_object('post')->labels->name, get_post_type_object('wp_block')->rest_base, get_post_status_object('future')->label]);

// Whitespace.
$say('wp_spaces_regexp', wp_spaces_regexp());
$spaces = static fn () => '[ ]';
add_filter('wp_spaces_regexp', $spaces);
$say('wp_spaces_regexp after a filter is added', wp_spaces_regexp());
remove_filter('wp_spaces_regexp', $spaces);

// The emoji script and styles, asked for by a plugin.
ob_start();
print_emoji_detection_script();
print_emoji_detection_script();
$say('print_emoji_detection_script printed something', ob_get_clean() !== '');
wp_enqueue_emoji_styles();
$say('wp_enqueue_emoji_styles leaves print_emoji_styles hooked', has_action('wp_print_styles', 'print_emoji_styles'));

// A user's sessions.
wp_set_current_user($user);
$manager = WP_Session_Tokens::get_instance($user);
$tokens = [$manager->create(time() + 3600), $manager->create(time() + 3600), $manager->create(time() + 3600)];
$say('wp_get_all_sessions', count(wp_get_all_sessions()));
// Signed in without a session cookie (as here), there is no session of one's own to keep, so none ends.
wp_destroy_other_sessions();
$say('wp_destroy_other_sessions with no session of its own leaves', count(wp_get_all_sessions()));
wp_destroy_all_sessions();
$say('wp_destroy_all_sessions leaves', count(wp_get_all_sessions()));
wp_set_current_user(0);

// The Allow header.
$server = rest_get_server();
foreach (['/wp/v2/posts' => 0, '/wp/v2/settings' => 0, '/wp/v2/types' => 0] as $route => $who) {
    $request = new WP_REST_Request('GET', $route);
    $response = new WP_REST_Response([]);
    $response->set_matched_route($route);
    $say("rest_send_allow_header {$route}", rest_send_allow_header($response, $server, $request)->get_headers()['Allow'] ?? null);
}
$response = new WP_REST_Response([]);
$say('rest_send_allow_header unmatched', rest_send_allow_header($response, $server, new WP_REST_Request('GET', '/nope'))->get_headers());
$editor = (int) (get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID'])[0] ?? 0);
wp_set_current_user($editor);
$response = new WP_REST_Response([]);
$response->set_matched_route('/wp/v2/posts');
$say('rest_send_allow_header /wp/v2/posts signed in', rest_send_allow_header($response, $server, new WP_REST_Request('GET', '/wp/v2/posts'))->get_headers()['Allow'] ?? null);
wp_set_current_user(0);

$say('wp_get_direct_php_update_url', wp_get_direct_php_update_url());
$direct = static fn () => 'https://host.example/php';
add_filter('wp_direct_php_update_url', $direct);
$say('wp_get_direct_php_update_url filtered', wp_get_direct_php_update_url());
remove_filter('wp_direct_php_update_url', $direct);

$say('_wp_add_global_attributes true', _wp_add_global_attributes(true));
$say('_wp_add_global_attributes array', array_keys(_wp_add_global_attributes(['href' => true, 'title' => false])));
$say('_wp_add_global_attributes string', _wp_add_global_attributes('x'));
$say('wp_term_is_shared', wp_term_is_shared(1));

// WP_List_Util.
$list = [
    ['id' => 3, 'name' => 'Cee', 'kind' => 'b', 'n' => 2],
    ['id' => 1, 'name' => 'Aye', 'kind' => 'a', 'n' => 2],
    (object) ['id' => 2, 'name' => 'Bee', 'kind' => 'a', 'n' => 1],
];
$util = new WP_List_Util($list);
$say('WP_List_Util input', $util->get_input() === $list);
$say('WP_List_Util filter', array_keys($util->filter(['kind' => 'a'])));
$say('WP_List_Util output after filter', array_keys($util->get_output()));
$say('WP_List_Util pluck', $util->pluck('name'));
$say('WP_List_Util pluck keyed', $util->pluck('name', 'id'));
$util = new WP_List_Util($list);
$say('WP_List_Util filter OR', array_keys($util->filter(['kind' => 'b', 'id' => 2], 'OR')));
$util = new WP_List_Util($list);
$say('WP_List_Util filter NOT', array_keys($util->filter(['kind' => 'a'], 'NOT')));
$util = new WP_List_Util($list);
$say('WP_List_Util filter bad operator', $util->filter(['kind' => 'a'], 'XOR'));
$util = new WP_List_Util($list);
$say('WP_List_Util sort', array_map(static fn ($row) => is_object($row) ? $row->id : $row['id'], $util->sort(['n' => 'DESC', 'name' => 'ASC'])));
$util = new WP_List_Util($list);
$say('WP_List_Util sort by one, keys kept', array_keys($util->sort('name', 'ASC', true)));
$say('WP_List_Util sort of nothing', (new WP_List_Util($list))->sort([]) === $list);

$say('get_comment_statuses', get_comment_statuses());
$say('wp_is_site_url_using_https', wp_is_site_url_using_https());
$say('wp_is_home_url_using_https', wp_is_home_url_using_https());
$plain = static fn ($url) => str_replace('https://', 'http://', $url);
add_filter('site_url', $plain);
$say('wp_is_site_url_using_https, site_url filtered', wp_is_site_url_using_https());
remove_filter('site_url', $plain);
$say('backslashit', [backslashit('abc'), backslashit('1st Y-m-d'), backslashit(''), backslashit('é and z')]);
$say('get_edit_tag_link anonymous', get_edit_tag_link(1, 'category'));
wp_set_current_user($editor);
$say('get_edit_tag_link', $mask(get_edit_tag_link(1, 'category')));
wp_set_current_user(0);
$say('__ngettext', [__ngettext('%s item', '%s items', 1), __ngettext('%s item', '%s items', 3)]);
$say('_c', [_c('Draft|post status'), _c('Plain')]);
$say('wp_resolve_post_date', [wp_resolve_post_date('2021-02-03 04:05:06'), wp_resolve_post_date('2021-02-30 04:05:06'), wp_resolve_post_date('', '2021-02-03 04:05:06'), wp_resolve_post_date('0000-00-00 00:00:00', '2021-02-03 04:05:06'), (bool) preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) wp_resolve_post_date())]);
$say('get_userdatabylogin', [get_userdatabylogin(get_userdata($editor)->user_login)->ID === $editor, get_userdatabylogin('zz-nobody')]);

// srcset and sizes on one image.
$tag = '<img src="' . wp_get_attachment_url($image) . '" class="wp-image-' . $image . '" width="300" height="200" />';
$say('wp_img_tag_add_srcset_and_sizes_attr', $mask(wp_img_tag_add_srcset_and_sizes_attr($tag, 'the_content', $image)));
$never = static fn () => false;
add_filter('wp_img_tag_add_srcset_and_sizes_attr', $never);
$say('wp_img_tag_add_srcset_and_sizes_attr declined', $mask(wp_img_tag_add_srcset_and_sizes_attr($tag, 'the_content', $image)));
remove_filter('wp_img_tag_add_srcset_and_sizes_attr', $never);

$say('wp_style_loader_src', [wp_style_loader_src('https://cdn.example/a.css?ver=1', 'zz-style'), wp_style_loader_src('wp-admin/css/x.css', 'zz-style')]);

// A valid zip, and a file that is not one.
$zip = wp_tempnam('zz-queue') . '.zip';
$archive = new ZipArchive();
$archive->open($zip, ZipArchive::CREATE);
$archive->addFromString('zz/readme.txt', 'zz');
$archive->close();
$bad = wp_tempnam('zz-queue-bad');
file_put_contents($bad, 'not a zip');
array_push($made['files'], $zip, $bad);
require_once ABSPATH . 'wp-admin/includes/file.php';
$say('wp_zip_file_is_valid', [wp_zip_file_is_valid($zip), wp_zip_file_is_valid($bad)]);
restore_error_handler();

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
