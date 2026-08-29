<?php
/** Rewrite registrations, post formats, user writes, and the small leftovers plugins reach for. Behaviour from contracts/fixtures/api/blocks.json. */

use Minn\Content\Users;
use Minn\Runtime\Runtime;
use Minn\Runtime\PostLookup;

function _minn_rewrite(): WP_Rewrite
{
    if (!isset($GLOBALS['wp_rewrite']) || !$GLOBALS['wp_rewrite'] instanceof WP_Rewrite) {
        $GLOBALS['wp_rewrite'] = new WP_Rewrite();
    }
    if (!isset($GLOBALS['wp']) || !$GLOBALS['wp'] instanceof WP) {
        $GLOBALS['wp'] = new WP();
    }
    return $GLOBALS['wp_rewrite'];
}

function add_rewrite_rule($regex, $query, $after = 'bottom')
{
    _minn_rewrite()->add_rule($regex, $query, $after);
}

function add_rewrite_tag($tag, $regex, $query = '')
{
    if (strlen((string) $tag) < 3 || $tag[0] !== '%' || $tag[strlen($tag) - 1] !== '%') {
        return;
    }
    $qv = trim((string) $tag, '%');
    _minn_rewrite();
    $GLOBALS['wp']->add_query_var($qv);
    _minn_rewrite()->add_rewrite_tag($tag, $regex, $query === '' ? $qv . '=' : $query);
}

function remove_rewrite_tag($tag)
{
    _minn_rewrite()->remove_rewrite_tag($tag);
}

function add_rewrite_endpoint($name, $places, $query_var = true)
{
    _minn_rewrite()->add_endpoint($name, $places, $query_var);
}

function add_permastruct($name, $struct, $args = [])
{
    _minn_rewrite()->add_permastruct($name, $struct, $args);
}

function remove_permastruct($name)
{
    _minn_rewrite()->remove_permastruct($name);
}

function flush_rewrite_rules($hard = true)
{
    _minn_rewrite()->flush_rules($hard);
}

function add_feed($feedname, $callback)
{
    $rewrite = _minn_rewrite();
    if (!in_array($feedname, $rewrite->feeds, true)) {
        $rewrite->feeds[] = $feedname;
    }
    add_action("do_feed_{$feedname}", $callback, 10, 2);
    return $feedname;
}

function _wp_filter_taxonomy_base($base)
{
    return trim((string) $base, '/');
}

function wp_filesize($path)
{
    $size = (int) apply_filters('pre_wp_filesize', 0, $path);
    if ($size === 0 && is_file((string) $path)) {
        $size = (int) filesize((string) $path);
    }
    return (int) apply_filters('wp_filesize', $size, $path);
}

function edit_post_link($text = null, $before = '', $after = '', $post = 0, $css_class = 'post-edit-link')
{
    $post = get_post($post);
    if ($post === null) {
        return;
    }
    $url = get_edit_post_link($post->ID);
    if (!$url) {
        return;
    }
    $text ??= 'Edit This';
    $link = '<a class="' . esc_attr($css_class) . '" href="' . esc_url($url) . '">' . $text . '</a>';
    echo $before . apply_filters('edit_post_link', $link, $post->ID, $text) . $after;
}

function get_num_queries()
{
    return (int) ($GLOBALS['wpdb']->num_queries ?? 0);
}

function wp_json_file_decode($filename, $options = [])
{
    $result = null;
    $filename = wp_normalize_path(realpath((string) $filename) ?: (string) $filename);
    if (!is_file($filename) || !is_readable($filename)) {
        return $result;
    }
    $options = wp_parse_args($options, ['associative' => false]);
    $decoded = json_decode((string) file_get_contents($filename), $options['associative']);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return $result;
    }
    return $decoded;
}

function links_add_base_url($content, $base, $attrs = ['src', 'href'])
{
    $attrs = implode('|', (array) $attrs);
    return preg_replace_callback("!($attrs)=(['\"])(.+?)\\2!i", static function (array $m) use ($base): string {
        $url = $m[3];
        if (!preg_match('#^(https?:)?//#i', $url) && !str_starts_with($url, 'mailto:') && !str_starts_with($url, '#')) {
            $url = WP_Http::make_absolute_url($url, $base);
        }
        return $m[1] . '=' . $m[2] . $url . $m[2];
    }, (string) $content);
}

function get_comment_type($comment_id = 0)
{
    $comment = get_comment($comment_id);
    if ($comment === null) {
        return null;
    }
    $type = $comment->comment_type === '' ? 'comment' : $comment->comment_type;
    return apply_filters('get_comment_type', $type, $comment->comment_ID, $comment);
}

function get_post_format_strings()
{
    return ['standard' => 'Standard', 'aside' => 'Aside', 'chat' => 'Chat', 'gallery' => 'Gallery', 'link' => 'Link', 'image' => 'Image', 'quote' => 'Quote', 'status' => 'Status', 'video' => 'Video', 'audio' => 'Audio'];
}

function get_post_format_slugs()
{
    $slugs = array_keys(get_post_format_strings());
    return array_combine($slugs, $slugs);
}

function get_post_format_string($slug)
{
    $strings = get_post_format_strings();
    if (!$slug) {
        return $strings['standard'];
    }
    return $strings[$slug] ?? '';
}

function get_post_format($post = null)
{
    $post = get_post($post);
    if ($post === null) {
        return false;
    }
    if (!post_type_supports($post->post_type, 'post-formats')) {
        return false;
    }
    $format = get_the_terms($post->ID, 'post_format');
    if (empty($format)) {
        return false;
    }
    $format = array_shift($format);
    return str_replace('post-format-', '', $format->slug);
}

function has_post_format($format = [], $post = null)
{
    $prefixed = [];
    foreach ((array) $format as $single) {
        $prefixed[] = 'post-format-' . sanitize_key($single);
    }
    return has_term($prefixed, 'post_format', $post);
}

function set_post_format($post, $format)
{
    $post = get_post($post);
    if ($post === null) {
        return new WP_Error('invalid_post', 'Invalid post.');
    }
    if (!empty($format)) {
        $format = sanitize_key((string) $format);
        if ($format === 'standard' || !in_array($format, get_post_format_slugs(), true)) {
            $format = '';
        } else {
            $format = 'post-format-' . $format;
        }
    }
    return wp_set_post_terms($post->ID, $format, 'post_format');
}

function get_post_format_link($format)
{
    $term = get_term_by('slug', 'post-format-' . $format, 'post_format');
    if (!$term instanceof WP_Term) {
        return false;
    }
    return get_term_link($term);
}

function wp_insert_user($userdata)
{
    if ($userdata instanceof stdClass) {
        $userdata = get_object_vars($userdata);
    } elseif ($userdata instanceof WP_User) {
        $userdata = $userdata->to_array();
    }
    $userdata = wp_unslash((array) $userdata);
    $update = !empty($userdata['ID']);
    $existing = $update ? get_userdata((int) $userdata['ID']) : false;
    if ($update && !$existing) {
        return new WP_Error('invalid_user_id', 'Invalid user ID.');
    }
    $users = new Users(Runtime::current()->db);
    $login = $update ? $existing->user_login : sanitize_user(trim((string) ($userdata['user_login'] ?? '')), true);
    if (!$update) {
        if ($login === '') {
            return new WP_Error('empty_user_login', 'Cannot create a user with an empty login name.');
        }
        if (mb_strlen($login) > 60) {
            return new WP_Error('user_login_too_long', 'Username may not be longer than 60 characters.');
        }
        if (username_exists($login)) {
            return new WP_Error('existing_user_login', 'Sorry, that username already exists!');
        }
        if (empty($userdata['user_pass'])) {
            return new WP_Error('empty_user_pass', 'A password is required for a new user.');
        }
    }
    $email = isset($userdata['user_email']) ? (string) $userdata['user_email'] : ($update ? $existing->user_email : '');
    if ($email !== '' && !is_email($email)) {
        return new WP_Error('invalid_email', 'The email address isn&#8217;t correct.');
    }
    $emailOwner = $email !== '' ? email_exists($email) : false;
    if ($emailOwner && (!$update || (int) $emailOwner !== (int) $userdata['ID'])) {
        return new WP_Error('existing_user_email', 'Sorry, that email address is already used!');
    }
    $nicename = isset($userdata['user_nicename']) && $userdata['user_nicename'] !== '' ? sanitize_title((string) $userdata['user_nicename']) : ($update ? $existing->user_nicename : null);
    $display_name = isset($userdata['display_name']) && $userdata['display_name'] !== '' ? (string) $userdata['display_name'] : ($update ? $existing->display_name : '');
    $role = $userdata['role'] ?? null;
    if ($role !== null && !isset(Runtime::current()->capabilities->roles()->all()[$role])) {
        return new WP_Error('invalid_role', 'Invalid role.');
    }
    $meta_keys = ['nickname', 'first_name', 'last_name', 'description', 'rich_editing', 'syntax_highlighting', 'comment_shortcuts', 'admin_color', 'use_ssl', 'show_admin_bar_front', 'locale'];
    if (!$update) {
        $id = $users->createAccount([
            'login' => $login,
            'password' => (string) $userdata['user_pass'],
            'email' => $email,
            'url' => (string) ($userdata['user_url'] ?? ''),
            'nicename' => $nicename,
            'display_name' => $display_name,
            'role' => $role ?? (string) get_option('default_role'),
            'nickname' => (string) ($userdata['nickname'] ?? $login),
            'first_name' => (string) ($userdata['first_name'] ?? ''),
            'last_name' => (string) ($userdata['last_name'] ?? ''),
            'description' => (string) ($userdata['description'] ?? ''),
            'locale' => (string) ($userdata['locale'] ?? ''),
        ]);
        if (!empty($userdata['user_registered'])) {
            $users->update($id, ['user_registered' => (string) $userdata['user_registered']]);
        }
        wp_cache_delete($id, 'user_meta');
        $user = get_userdata($id);
        do_action('user_register', $id, $userdata);
        return $id;
    }
    $id = (int) $userdata['ID'];
    $columns = [];
    foreach (['user_email' => $email, 'user_url' => isset($userdata['user_url']) ? Minn\Support\Kses::url((string) $userdata['user_url']) : null, 'user_nicename' => $nicename, 'display_name' => $display_name, 'user_registered' => $userdata['user_registered'] ?? null] as $column => $value) {
        if ($value !== null && (string) $value !== (string) $existing->{$column}) {
            $columns[$column] = (string) $value;
        }
    }
    if (!empty($userdata['user_pass'])) {
        $columns['user_pass'] = wp_hash_password((string) $userdata['user_pass']);
    }
    if ($columns !== []) {
        $users->update($id, $columns);
    }
    foreach ($meta_keys as $key) {
        if (array_key_exists($key, $userdata)) {
            update_user_meta($id, $key, $userdata[$key]);
        }
    }
    if ($role !== null) {
        $existing->set_role($role);
    }
    wp_cache_delete($id, 'user_meta');
    do_action('profile_update', $id, $existing, $userdata);
    return $id;
}

function wp_update_user($userdata)
{
    if ($userdata instanceof stdClass) {
        $userdata = get_object_vars($userdata);
    } elseif ($userdata instanceof WP_User) {
        $userdata = $userdata->to_array();
    }
    $userdata = (array) $userdata;
    $id = (int) ($userdata['ID'] ?? 0);
    if ($id <= 0 || !get_userdata($id)) {
        return new WP_Error('invalid_user_id', 'Invalid user ID.');
    }
    return wp_insert_user($userdata);
}

function wp_create_user($username, $password, $email = '')
{
    return wp_insert_user(['user_login' => wp_slash($username), 'user_email' => wp_slash($email), 'user_pass' => $password]);
}

function wp_delete_user($id, $reassign = null)
{
    $id = (int) $id;
    $user = get_userdata($id);
    if (!$user) {
        return false;
    }
    do_action('delete_user', $id, $reassign, $user);
    if ($reassign === null) {
        foreach ((new PostLookup(Runtime::current()->db))->idsByAuthor($id) as $postId) {
            wp_delete_post($postId, true);
        }
    } else {
        _minn_post_writer()->reassignAuthor($id, (int) $reassign);
    }
    $users = new Users(Runtime::current()->db);
    $users->delete($id);
    $users->deleteAllMeta($id);
    wp_cache_delete($id, 'user_meta');
    do_action('deleted_user', $id, $reassign, $user);
    return true;
}

function get_preview_post_link($post = null, $query_args = [], $preview_link = '')
{
    $post = get_post($post);
    if ($post === null) {
        return null;
    }
    $post_type_object = get_post_type_object($post->post_type);
    if (is_post_type_viewable($post_type_object)) {
        if (!$preview_link) {
            $preview_link = set_url_scheme(get_permalink($post));
        }
        $query_args['preview'] = 'true';
        $preview_link = add_query_arg($query_args, $preview_link);
    }
    return apply_filters('preview_post_link', $preview_link, $post);
}

function wp_delete_object_term_relationships($object_id, $taxonomies)
{
    $object_id = (int) $object_id;
    foreach ((array) $taxonomies as $taxonomy) {
        $term_ids = wp_get_object_terms($object_id, $taxonomy, ['fields' => 'ids']);
        if (is_wp_error($term_ids) || $term_ids === []) {
            continue;
        }
        wp_remove_object_terms($object_id, array_map('intval', $term_ids), $taxonomy);
    }
}

function wp_get_custom_css_post($stylesheet = '')
{
    if ($stylesheet === '') {
        $stylesheet = get_stylesheet();
    }
    $post_id = $stylesheet === get_stylesheet() ? (int) get_theme_mod('custom_css_post_id', -1) : -1;
    if ($post_id > 0) {
        $post = get_post($post_id);
        if ($post !== null && $post->post_type === 'custom_css' && $post->post_name === $stylesheet) {
            return $post;
        }
    }
    $posts = get_posts(['post_type' => 'custom_css', 'post_status' => get_post_stati(), 'name' => $stylesheet, 'numberposts' => 1, 'orderby' => 'ID', 'order' => 'DESC', 'suppress_filters' => true]);
    return $posts === [] ? null : $posts[0];
}

function wp_get_custom_css($stylesheet = '')
{
    $css = '';
    if ($stylesheet === '') {
        $stylesheet = get_stylesheet();
    }
    $post = wp_get_custom_css_post($stylesheet);
    if ($post !== null) {
        $css = $post->post_content;
    }
    return apply_filters('wp_get_custom_css', $css, $stylesheet);
}

function wp_update_custom_css_post($css, $args = [])
{
    $args = wp_parse_args($args, ['preprocessed' => '', 'stylesheet' => get_stylesheet()]);
    $data = ['css' => $css, 'preprocessed' => $args['preprocessed']];
    $data = apply_filters('update_custom_css_data', $data, array_merge($args, compact('css')));
    $post_data = ['post_title' => $args['stylesheet'], 'post_name' => sanitize_title($args['stylesheet']), 'post_type' => 'custom_css', 'post_status' => 'publish', 'post_content' => $data['css'], 'post_content_filtered' => $data['preprocessed']];
    $post = wp_get_custom_css_post($args['stylesheet']);
    if ($post !== null) {
        $post_data['ID'] = $post->ID;
        $r = wp_update_post(wp_slash($post_data), true);
    } else {
        $r = wp_insert_post(wp_slash($post_data), true);
        if (!is_wp_error($r) && $args['stylesheet'] === get_stylesheet()) {
            set_theme_mod('custom_css_post_id', $r);
        }
    }
    if (is_wp_error($r)) {
        return $r;
    }
    return get_post($r);
}

function wp_check_post_lock($post)
{
    $post = get_post($post);
    if ($post === null) {
        return false;
    }
    $lock = get_post_meta($post->ID, '_edit_lock', true);
    if (!$lock) {
        return false;
    }
    $lock = explode(':', (string) $lock);
    $time = (int) $lock[0];
    $user = isset($lock[1]) ? (int) $lock[1] : (int) get_post_meta($post->ID, '_edit_last', true);
    if (!$user || !$time) {
        return false;
    }
    $time_window = apply_filters('wp_check_post_lock_window', 150);
    if ($time && $time > time() - $time_window && $user !== get_current_user_id()) {
        return $user;
    }
    return false;
}

function wp_set_post_lock($post)
{
    $post = get_post($post);
    if ($post === null) {
        return false;
    }
    $user_id = get_current_user_id();
    if ($user_id === 0) {
        return false;
    }
    $now = time();
    $lock = "$now:$user_id";
    update_post_meta($post->ID, '_edit_lock', $lock);
    return [$now, $user_id];
}

function wp_get_pomo_file_data($po_file)
{
    $headers = get_file_data($po_file, ['POT-Creation-Date' => '"POT-Creation-Date', 'PO-Revision-Date' => '"PO-Revision-Date', 'Project-Id-Version' => '"Project-Id-Version', 'X-Generator' => '"X-Generator']);
    foreach ($headers as $header => $value) {
        $headers[$header] = preg_replace('~(\\\\n)?"$~', '', (string) $value);
    }
    return $headers;
}

function wp_post_revision_title($revision, $link = true)
{
    $revision = get_post($revision);
    if ($revision === null) {
        return $revision;
    }
    if (!in_array($revision->post_type, ['post', 'page', 'revision'], true)) {
        return false;
    }
    $datef = 'F j, Y @ H:i:s';
    $date = date_i18n($datef, strtotime($revision->post_modified));
    if ($link && current_user_can('edit_post', $revision->ID)) {
        $date = "<a href='" . esc_url(admin_url('revision.php?revision=' . $revision->ID)) . "'>$date</a>";
    }
    if (!wp_is_post_revision($revision)) {
        $date = sprintf('%s [Current Revision]', $date);
    } elseif (wp_is_post_autosave($revision)) {
        $date = sprintf('%s [Autosave]', $date);
    }
    return $date;
}

function wp_sprintf_l($pattern, $args)
{
    if (!is_array($args) || strpos((string) $pattern, '%l') !== 0) {
        return $pattern;
    }
    $l = apply_filters('wp_sprintf_l', ['between' => ', ', 'between_last_two' => ', and ', 'between_only_two' => ' and ']);
    $args = (array) $args;
    $result = array_shift($args);
    if (count($args) === 1) {
        $result .= $l['between_only_two'] . array_shift($args);
    }
    $i = count($args);
    while ($i) {
        $arg = array_shift($args);
        $i--;
        if ($i === 0) {
            $result .= $l['between_last_two'] . $arg;
        } else {
            $result .= $l['between'] . $arg;
        }
    }
    return $result . substr((string) $pattern, 2);
}

function wp_oembed_add_provider($format, $provider, $regex = false)
{
    $providers = Runtime::current()->get('oembed_providers', []);
    $providers[$format] = [$provider, $regex];
    Runtime::current()->set('oembed_providers', $providers);
}

function wp_oembed_remove_provider($format)
{
    $providers = Runtime::current()->get('oembed_providers', []);
    if (!isset($providers[$format])) {
        return false;
    }
    unset($providers[$format]);
    Runtime::current()->set('oembed_providers', $providers);
    return true;
}

function wp_embed_register_handler($id, $regex, $callback, $priority = 10)
{
    $handlers = Runtime::current()->get('embed_handlers', []);
    $handlers[$priority][$id] = ['regex' => $regex, 'callback' => $callback];
    Runtime::current()->set('embed_handlers', $handlers);
}

function wp_embed_unregister_handler($id, $priority = 10)
{
    $handlers = Runtime::current()->get('embed_handlers', []);
    unset($handlers[$priority][$id]);
    Runtime::current()->set('embed_handlers', $handlers);
}

function wp_oembed_get($url, $args = '')
{
    return false;
}

function wp_embed_defaults($url = '')
{
    return apply_filters('embed_defaults', ['width' => (int) get_option('embed_size_w') ?: 500, 'height' => (int) get_option('embed_size_h') ?: 750], $url);
}

function fetch_feed($url)
{
    return new WP_Error('simplepie-error', 'A feed could not be found at the requested URL: the engine has no feed parser.');
}
