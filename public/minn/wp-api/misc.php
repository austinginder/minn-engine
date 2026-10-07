<?php
/** Rewrite registrations, post formats, user writes, and the small leftovers plugins reach for. Behaviour from contracts/fixtures/api/blocks.json. */

use Minn\Front\PostEmbed;
use Minn\Content\Users;
use Minn\Runtime\Runtime;
use Minn\Runtime\Refusal;
use Minn\Runtime\UserInsert;
use Minn\Runtime\PostLookup;

function _minn_rewrite(): WP_Rewrite
{
    if (!isset($GLOBALS['wp_rewrite']) || !$GLOBALS['wp_rewrite'] instanceof WP_Rewrite) {
        $GLOBALS['wp_rewrite'] = new WP_Rewrite();
        Minn\Runtime\RewriteRules::builtins($GLOBALS['wp_rewrite']);
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
    // A tag given no query of its own is a public query var of its name; one given a query adds none.
    if ($query === '') {
        $GLOBALS['wp']->add_query_var($qv);
    }
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
    // Each field through its pre_user_* filter first, the reference's order (probe user-insert-filters).
    $fields = \Minn\Runtime\UserSave::fields($userdata, $existing ? $existing->to_array() : null);
    if ($fields instanceof Refusal) {
        return new WP_Error($fields->code, $fields->message, $fields->data);
    }
    // What the caller gave is what the filters are handed; the filtered fields are what is written.
    $given = $userdata;
    $userdata = array_replace($userdata, $fields);
    if ($existing && (string) ($userdata['user_pass'] ?? '') === (string) $existing->user_pass) {
        // The stored hash, carried by an update's merge, is no new password.
        unset($userdata['user_pass']);
    }
    $insert = new UserInsert(
        static fn (string $login): string => $login,
        static fn (string $slug): string => $slug,
        static fn (string $email): bool => (bool) is_email($email),
        // UserSave::fields has looked the login up already, where the reference does.
        static fn (string $login): bool => false,
        static fn (string $email): int => (int) email_exists($email),
        static fn (string $role): bool => isset(Runtime::current()->capabilities->roles()->all()[$role]),
    );
    $resolved = $insert->resolve($userdata, $existing ? $existing->to_array() : null);
    if ($resolved instanceof Refusal) {
        return new WP_Error($resolved->code, $resolved->message, $resolved->data);
    }
    if (!$update) {
        return _minn_insert_account(_minn_user_row_filtered(UserInsert::account($userdata, $resolved, (string) get_option('default_role')), $given), $given);
    }
    return _minn_update_user_profile($existing, $userdata, $resolved, $given);
}

/** @internal a new account's row through wp_pre_insert_user_data, as the reference hands it (the password hashed), and back */
function _minn_user_row_filtered(array $account, array $userdata): array
{
    $row = ['user_pass' => (string) wp_hash_password((string) $account['password']), 'user_nicename' => (string) $account['nicename'], 'user_email' => (string) $account['email'], 'user_url' => (string) $account['url'], 'user_registered' => (string) ($userdata['user_registered'] ?? gmdate('Y-m-d H:i:s')), 'user_activation_key' => '', 'display_name' => (string) $account['display_name'], 'user_login' => (string) $account['login']];
    $data = \Minn\Runtime\UserSave::data($row, 0, $userdata);
    $map = ['login' => 'user_login', 'nicename' => 'user_nicename', 'email' => 'user_email', 'url' => 'user_url', 'display_name' => 'display_name'];
    foreach ($map as $field => $column) {
        if (array_key_exists($column, $data)) {
            $account[$field] = (string) $data[$column];
        }
    }
    return $account;
}

/**
 * @internal A new account as the reference writes it: the row, wp_set_password,
 * each profile meta through add_user_meta, the role, clean_user_cache, then
 * user_register (where the count is kept). A role given as false (the REST
 * controller's way) leaves the account without one for the caller to add.
 */
function _minn_insert_account(array $account, array $userdata): int
{
    $users = new Users(Runtime::current()->db);
    $id = $users->insertAccount($account);
    if (!empty($userdata['user_registered'])) {
        $users->update($id, ['user_registered' => (string) $userdata['user_registered']]);
    }
    do_action('wp_set_password', (string) $account['password'], $id, new WP_User($id));
    foreach (\Minn\Runtime\UserSave::meta(Users::profileMeta($account), $id, $userdata) as $key => $value) {
        add_user_meta($id, $key, $value);
    }
    $user = new WP_User($id);
    $user->set_role(array_key_exists('role', $userdata) && $userdata['role'] === false ? false : $account['role']);
    clean_user_cache($user);
    do_action('user_register', $id, $userdata);
    return $id;
}

/** @internal the update half of wp_insert_user: changed columns, the profile meta, the role */
function _minn_update_user_profile(WP_User $existing, array $userdata, array $resolved, array $given = []): int
{
    $id = $existing->ID;
    $stored = $existing->to_array();
    $columns = UserInsert::changes($userdata, $stored, $resolved, static fn (string $url): string => Minn\Support\Kses::url($url), static fn (string $password): string => (string) wp_hash_password($password));
    // The whole row through wp_pre_insert_user_data; what it changes is written too.
    $row = array_intersect_key(array_replace($stored, $columns), array_flip(['user_pass', 'user_nicename', 'user_email', 'user_url', 'user_registered', 'user_activation_key', 'display_name']));
    foreach (\Minn\Runtime\UserSave::data($row, $id, $given ?: $userdata) as $column => $value) {
        if ($column !== 'user_login' && (string) $value !== (string) ($stored[$column] ?? '')) {
            $columns[$column] = (string) $value;
        }
    }
    if ($columns !== []) {
        (new Users(Runtime::current()->db))->update($id, $columns);
    }
    if (isset($columns['user_pass'])) {
        do_action('wp_set_password', (string) $userdata['user_pass'], $id, $existing);
    }
    clean_user_cache($id);
    $meta = array_intersect_key($userdata, array_flip([...UserInsert::META_KEYS, 'infinite_scrolling']));
    foreach (\Minn\Runtime\UserSave::meta($meta, $id, $given ?: $userdata) as $key => $value) {
        update_user_meta($id, $key, $value);
    }
    if ($resolved['role'] !== null) {
        (new WP_User($id))->set_role($resolved['role']);
    }
    clean_user_cache($id);
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
    $before = get_userdata($id)->to_array();
    // The stored account and its profile under the given fields, as the reference merges them; the stored
    // password hash is no new password.
    $merged = $before;
    foreach ([...UserInsert::META_KEYS, 'infinite_scrolling'] as $key) {
        if (metadata_exists('user', $id, $key)) {
            $merged[$key] = get_user_meta($id, $key, true);
        }
    }
    // What was read from the database is slashed to stand beside what the caller gave, slashed as callers give it.
    $result = wp_insert_user(array_merge(wp_slash($merged), $userdata));
    if (!is_wp_error($result)) {
        _minn_user_change_notices($before, $userdata);
        do_action('wp_update_user', $id, $userdata, $userdata);
    }
    return $result;
}

/** @internal The notices the reference mails a user whose password or email address just changed, to the address they had. */
function _minn_user_change_notices(array $user, array $userdata): void
{
    $site = wp_specialchars_decode((string) get_option('blogname'), ENT_QUOTES);
    $values = ['USERNAME' => (string) $user['user_login'], 'ADMIN_EMAIL' => (string) get_option('admin_email'), 'EMAIL' => (string) $user['user_email'], 'SITENAME' => $site, 'SITEURL' => home_url()];
    $sends = [];
    if (!empty($userdata['user_pass']) && apply_filters('send_password_change_email', true, $user, $userdata)) {
        $sends['password_change_email'] = Minn\Mail\ChangeNotices::password();
    }
    if (isset($userdata['user_email']) && (string) $userdata['user_email'] !== (string) $user['user_email'] && apply_filters('send_email_change_email', true, $user, $userdata)) {
        $sends['email_change_email'] = Minn\Mail\ChangeNotices::email();
        $values['NEW_EMAIL'] = (string) $userdata['user_email'];
    }
    foreach ($sends as $filter => $template) {
        $mail = apply_filters($filter, ['to' => $user['user_email']] + $template + ['headers' => ''], $user, $userdata);
        wp_mail($mail['to'], sprintf($mail['subject'], $site), Minn\Mail\ChangeNotices::fill((string) $mail['message'], $values), $mail['headers']);
    }
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
    // Each meta key removed with its own actions, then the row; the count follows deleted_user.
    foreach (_minn_meta()->rowsOf('user', $id) as $row) {
        delete_metadata_by_mid('user', $row['meta_id']);
    }
    (new Users(Runtime::current()->db))->deleteRow($id);
    clean_user_cache($user);
    do_action('deleted_user', $id, $reassign, $user);
    return true;
}

/** Lets go of a user's cached rows and meta, telling plugins. */
function clean_user_cache($user)
{
    $user = is_numeric($user) ? new WP_User((int) $user) : $user;
    if (!$user instanceof WP_User || !$user->exists()) {
        return;
    }
    wp_cache_delete($user->ID, 'users');
    wp_cache_delete($user->user_login, 'userlogins');
    wp_cache_delete($user->user_nicename, 'userslugs');
    wp_cache_delete($user->user_email, 'useremail');
    wp_cache_delete($user->ID, 'user_meta');
    do_action('clean_user_cache', $user->ID, $user);
}

/** The default on user_register and deleted_user: the site's user count is kept current. */
function wp_maybe_update_user_counts($network_id = null)
{
    return wp_update_user_counts($network_id);
}

/** Stores the number of accounts as the user_count site option. */
function wp_update_user_counts($network_id = null)
{
    return update_site_option('user_count', (new Users(Runtime::current()->db))->count());
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

/** A URL's embed HTML through WP_oEmbed (pre_oembed_result first, then the provider), or false. */
function wp_oembed_get($url, $args = '')
{
    return _wp_oembed_get_object()->get_html($url, $args);
}

function wp_embed_defaults($url = '')
{
    // The theme's content width drives the size; the height is capped at 1000.
    // These exact numbers feed the oEmbed cache key, so they must match.
    $width = (int) ($GLOBALS['content_width'] ?? 0) ?: 500;
    $height = (int) min(ceil($width * 1.5), 1000);
    return apply_filters('embed_defaults', ['width' => $width, 'height' => $height], $url);
}

function wp_no_robots()
{
    header('X-Robots-Tag: noindex, noarchive', true);
}

/** Sets each named global from the request's POST else GET value, empty string when absent. */
function wp_reset_vars($vars)
{
    $request = Runtime::current()->request;
    foreach ((array) $vars as $var) {
        $value = $request?->form[$var] ?? $request?->query[$var] ?? '';
        $GLOBALS[$var] = is_scalar($value) ? (string) $value : $value;
    }
}

/** The rich-editing preference, browser-gated: false without a browser user agent (the CLI probes false even for admins). */
function user_can_richedit()
{
    $pref = get_user_option('rich_editing');
    $wants = $pref === false || $pref === 'true' || $pref === true;
    $agent = (string) (Runtime::current()->request?->header('user-agent') ?? '');
    return (bool) apply_filters('user_can_richedit', $wants && stripos($agent, 'mozilla') !== false);
}

/** The reference answers tinymce even where rich editing probes false. */
function wp_default_editor()
{
    return apply_filters('wp_default_editor', 'tinymce');
}

/** Runtime\Constants::define() already defined the cookie constants at boot; nothing is left to define when plugin code calls this. */
function wp_cookie_constants()
{
}

/** Runtime\Constants::define() already defined the plugin directory constants at boot. */
function wp_plugin_directory_constants()
{
}

/** Both the site and home URLs on https (probed true on the dev reference). */
function wp_is_using_https()
{
    return str_starts_with((string) get_option('siteurl'), 'https://') && str_starts_with((string) get_option('home'), 'https://');
}

/** JSONP callback names: word characters and dots only (probed: my.Fn_2 yes, bad() and a-b no). */
function wp_check_jsonp_callback($callback)
{
    if (!is_string($callback)) {
        return false;
    }
    return preg_match('/[^\w.]/', $callback) === 0;
}

function calendar_week_mod($num)
{
    return fmod((float) $num, 7);
}

/** Recursive key sort in place, the probed shape. */
function wp_recursive_ksort(&$input_array)
{
    foreach ($input_array as &$value) {
        if (is_array($value)) {
            wp_recursive_ksort($value);
        }
    }
    unset($value);
    return ksort($input_array);
}

function wp_using_themes()
{
    return defined('WP_USE_THEMES') ? (bool) WP_USE_THEMES : true;
}

function get_main_network_id()
{
    return 1;
}

/** True only while a .maintenance file at the webroot is fresh. */
function wp_is_maintenance_mode()
{
    $file = ABSPATH . '.maintenance';
    if (!file_exists($file)) {
        return false;
    }
    $upgrading = 0;
    include $file;
    return $upgrading >= time() - 600 && (bool) apply_filters('enable_maintenance_mode', true, $upgrading);
}

function wp_should_upgrade_global_tables()
{
    return (bool) apply_filters('wp_should_upgrade_global_tables', true);
}

function wp_can_install_language_pack()
{
    return (bool) apply_filters('file_mod_allowed', !defined('DISALLOW_FILE_MODS') || !DISALLOW_FILE_MODS, 'download_language_pack');
}

/** Maintains a marker block in a file (the .htaccess shape), preamble and all. */
function insert_with_markers($filename, $marker, $insertion)
{
    return \Minn\Support\Markers::write((string) $filename, (string) $marker, array_map('strval', (array) $insertion));
}

function copy_dir($from, $to, $skip_list = [])
{
    return Minn\Support\FileTree::copy((string) $from, (string) $to, array_map('strval', (array) $skip_list));
}

function list_files($folder = '', $levels = 100, $exclusions = [], $include_hidden = false)
{
    if ((string) $folder === '' || (int) $levels < 1) {
        return false;
    }
    return Minn\Support\FileTree::files((string) $folder, (int) $levels, array_map('strval', (array) $exclusions));
}

function win_is_writable($path)
{
    // Windows reports a directory's own writability wrongly, so WordPress
    // probes it by opening a file. Everywhere else is_writable is the answer,
    // and a path that does not exist yet is judged by the folder holding it.
    $path = (string) $path;
    return is_writable(file_exists($path) ? $path : dirname($path));
}

function wp_get_mu_plugins()
{
    $dir = defined('WPMU_PLUGIN_DIR') ? WPMU_PLUGIN_DIR : WP_CONTENT_DIR . '/mu-plugins';
    $files = [];
    foreach (glob(rtrim($dir, '/') . '/*.php') ?: [] as $file) {
        $files[] = $file;
    }
    sort($files);
    return $files;
}

function wp_opcache_invalidate($filepath, $force = false)
{
    if (!file_exists($filepath) || !function_exists('opcache_invalidate')) {
        return false;
    }
    if (!apply_filters('wp_opcache_invalidate_file', true, $filepath)) {
        return false;
    }
    $enabled = ini_get('opcache.enable');
    if (!$enabled || (php_sapi_name() === 'cli' && !ini_get('opcache.enable_cli'))) {
        return false;
    }
    return opcache_invalidate($filepath, (bool) $force);
}

function extract_from_markers($filename, $marker)
{
    return \Minn\Support\Markers::read((string) $filename, (string) $marker);
}

function got_mod_rewrite()
{
    return apply_filters('got_rewrite', apache_mod_loaded('mod_rewrite', true));
}

/** Writes the document with Windows line endings, the way an IIS web.config is kept. */
function saveDomDocument($doc, $filename)
{
    file_put_contents($filename, str_replace("\n", "\r\n", (string) $doc->saveXML()));
}

/** The default on set_user_role: cached user queries are asked again. */
function wp_cache_set_users_last_changed()
{
    wp_cache_set_last_changed('users');
}

/** The site's oEmbed endpoint for a post, JSON unless asked for XML, through oembed_endpoint_url. */
function get_oembed_endpoint_url($permalink = '', $format = 'json')
{
    $url = rest_url('oembed/1.0/embed');
    if ($permalink !== '') {
        $url = add_query_arg(['url' => urlencode((string) $permalink), 'format' => $format !== 'json' ? $format : false], $url);
    }
    return apply_filters('oembed_endpoint_url', $url, $permalink, $format);
}

/** A post's oEmbed data (Front\PostEmbed), or false when it cannot be embedded. */
function get_oembed_response_data($post, $width)
{
    return PostEmbed::data($post, (int) $width);
}

/** The rich part of a post's oEmbed data: its size, markup and thumbnail. */
function get_oembed_response_data_rich($data, $post, $width, $height)
{
    return PostEmbed::rich((array) $data, get_post($post), (int) $width, (int) $height);
}

/** A post's embed markup: blockquote, sandboxed iframe, sizing script. */
function get_post_embed_html($width, $height, $post = null)
{
    return PostEmbed::html((int) $width, (int) $height, $post);
}

function get_post_embed_url($post = null)
{
    return PostEmbed::url($post);
}

/** oEmbed data for one of the site's own addresses, or false. */
function get_oembed_response_data_for_url($url, $args)
{
    $post_id = (int) apply_filters('oembed_request_post_id', url_to_postid((string) $url), $url);
    return $post_id > 0 ? get_oembed_response_data($post_id, (int) ($args['width'] ?? 600)) : false;
}

/** The data as an oEmbed XML document. */
function _oembed_create_xml($data, $node = null)
{
    return is_array($data) || is_object($data) ? PostEmbed::xml((array) $data) : false;
}

/** A rich or video provider's iframe given the title its data names, when it has none (oembed_dataparse). */
function wp_filter_oembed_iframe_title_attribute($result, $data, $url)
{
    $title = is_object($data) ? (string) ($data->title ?? '') : '';
    $framed = is_object($data) && in_array($data->type ?? '', ['rich', 'video'], true);
    if (!is_string($result) || !$framed || $title === '' || !str_contains($result, '<iframe') || preg_match('/<iframe[^>]*\stitle=/i', $result)) {
        return $result;
    }
    return (string) preg_replace('/<iframe\b/i', '<iframe title="' . esc_attr($title) . '"', $result, 1);
}

/** The engine serves format=xml itself on the way out (Rest\RuntimeRoutes::serve); the hook is the reference's name for it. */
function _oembed_rest_pre_serve_request($served, $result, $request, $server)
{
    return $served;
}
