<?php
/** Users, the current user, capabilities, and user meta. */

use Minn\Auth\PasswordReset;
use Minn\Runtime\Runtime;
use Minn\Content\Users;
use Minn\Auth\Sessions;

function _wp_get_current_user()
{
    $runtime = Runtime::current();
    $user = $runtime->get('current_user');
    if ($user instanceof WP_User) {
        return $user;
    }
    $id = $runtime->reader->userId;
    $user = new WP_User($id > 0 ? $id : 0);
    $runtime->set('current_user', $user);
    return $user;
}

function get_current_user_id()
{
    return Runtime::booted() ? wp_get_current_user()->ID : 0;
}

function get_user($user_id)
{
    return get_userdata($user_id);
}

function username_exists($username)
{
    $user = get_user_by('login', $username);
    $id = $user ? $user->ID : false;
    return apply_filters('username_exists', $id, $username);
}

function email_exists($email)
{
    $user = get_user_by('email', $email);
    $id = $user ? $user->ID : false;
    return apply_filters('email_exists', $id, $email);
}

function current_user_can($capability, ...$args)
{
    return user_can(wp_get_current_user(), $capability, ...$args);
}

function current_user_can_for_site($site_id, $capability, ...$args)
{
    return current_user_can($capability, ...$args);
}

function current_user_can_for_blog($blog_id, $capability, ...$args)
{
    return current_user_can($capability, ...$args);
}

function user_can($user, $capability, ...$args)
{
    if (!is_object($user)) {
        $user = get_userdata($user);
    }
    if (!$user || !($user instanceof WP_User)) {
        $user = new WP_User(0);
    }
    return $user->has_cap($capability, ...$args);
}

function author_can($post, $capability, ...$args)
{
    $post = get_post($post);
    if ($post === null) {
        return false;
    }
    return user_can((int) $post->post_author, $capability, ...$args);
}

function map_meta_cap($cap, $user_id, ...$args)
{
    $caps = Runtime::current()->capabilities->map((string) $cap, (int) $user_id, isset($args[0]) && is_numeric($args[0]) ? (int) $args[0] : null);
    return apply_filters('map_meta_cap', $caps, $cap, $user_id, $args);
}

function get_role($role)
{
    $roles = Runtime::current()->capabilities->roles()->all();
    return isset($roles[$role]) ? new WP_Role($role, $roles[$role]['capabilities']) : null;
}

function wp_roles()
{
    return new WP_Roles();
}

function get_editable_roles()
{
    return apply_filters('editable_roles', wp_roles()->roles);
}

function wp_get_user_contact_methods($user = null)
{
    return apply_filters('user_contactmethods', [], $user);
}

function get_user_meta($user_id, $key = '', $single = false)
{
    return get_metadata('user', $user_id, $key, $single);
}

function update_user_meta($user_id, $meta_key, $meta_value, $prev_value = '')
{
    return update_metadata('user', $user_id, $meta_key, $meta_value, $prev_value);
}

function add_user_meta($user_id, $meta_key, $meta_value, $unique = false)
{
    return add_metadata('user', $user_id, $meta_key, $meta_value, $unique);
}

function delete_user_meta($user_id, $meta_key, $meta_value = '')
{
    return delete_metadata('user', $user_id, $meta_key, $meta_value);
}

function get_user_option($option, $user = 0, $deprecated = '')
{
    $user = $user === 0 || $user === '' ? get_current_user_id() : (int) $user;
    if ($user === 0) {
        return false;
    }
    $prefix = Runtime::current()->db->prefix();
    if (metadata_exists('user', $user, $prefix . $option)) {
        $result = get_user_meta($user, $prefix . $option, true);
    } elseif (metadata_exists('user', $user, $option)) {
        $result = get_user_meta($user, $option, true);
    } else {
        $result = false;
    }
    return apply_filters("get_user_option_{$option}", $result, $option, get_userdata($user));
}

function update_user_option($user_id, $option_name, $newvalue, $is_global = false)
{
    $key = $is_global ? $option_name : Runtime::current()->db->prefix() . $option_name;
    return update_user_meta($user_id, $key, $newvalue);
}

function delete_user_option($user_id, $option_name, $is_global = false)
{
    $key = $is_global ? $option_name : Runtime::current()->db->prefix() . $option_name;
    return delete_user_meta($user_id, $key);
}

function count_users($strategy = 'time', $site_id = null)
{
    return ['total_users' => (new Users(Runtime::current()->db))->count(), 'avail_roles' => []];
}

function get_users($args = [])
{
    // The reference's own: a user query, totals left uncounted. search,
    // include, exclude and the rest narrow it; the list never widens to every account.
    $args = wp_parse_args($args);
    $args['count_total'] = false;
    return (array) (new WP_User_Query($args))->get_results();
}

/** A username survives strict sanitising unchanged and is not empty. */
function validate_username($username)
{
    $username = (string) $username;
    $valid = $username !== '' && sanitize_user($username, true) === $username;
    return (bool) apply_filters('validate_username', $valid, $username);
}

function wp_get_password_hint()
{
    $hint = 'Hint: The password should be at least twelve characters long. To make it stronger, use upper and lower case letters, numbers, and symbols like ! " ? $ % ^ &amp; ).';
    return apply_filters('password_hint', $hint);
}

/** A fresh 20-character reset key, its hash stored under the time it was issued. */
function get_password_reset_key($user)
{
    $user = _minn_user_row($user);
    if ($user === null) {
        return new WP_Error('invalid_user', 'Invalid user.');
    }
    do_action('retrieve_password', $user['user_login']);
    $allow = apply_filters('allow_password_reset', true, (int) $user['ID']);
    if (!$allow) {
        return new WP_Error('no_password_reset', 'Password reset is not allowed for this user');
    }
    if (is_wp_error($allow)) {
        return $allow;
    }
    $key = (new PasswordReset(new Users(Runtime::current()->db)))->issue($user);
    do_action('retrieve_password_key', $user['user_login'], $key);
    return $key;
}

/** @internal a user row from an id, a login, a WP_User, or an object with an ID */
function _minn_user_row($user): ?Minn\Content\UserRecord
{
    $users = new Users(Runtime::current()->db);
    if (is_object($user) && isset($user->ID)) {
        return $users->find((int) $user->ID);
    }
    if (is_numeric($user)) {
        return $users->find((int) $user);
    }
    return is_string($user) && $user !== '' ? $users->findByLogin($user) : null;
}

/** The user the key belongs to, or expired_key / invalid_key. A reference-issued key is not readable here. */
function check_password_reset_key($key, $login)
{
    $key = (string) preg_replace('/[^a-z0-9]/i', '', (string) $key);
    $user = $login !== '' ? (new Users(Runtime::current()->db))->findByLogin((string) $login) : null;
    if ($key === '' || $user === null) {
        return new WP_Error('invalid_key', 'Invalid key.');
    }
    $status = (new PasswordReset(new Users(Runtime::current()->db)))->status($user, $key);
    if ($status === 'valid') {
        return new WP_User((int) $user['ID']);
    }
    return $status === 'expired' ? new WP_Error('expired_key', 'Invalid key.') : new WP_Error('invalid_key', 'Invalid key.');
}

/** Sign-in from credentials (or the login form's fields): the user, cookies set, or the refusal. */
function wp_signon($credentials = [], $secure_cookie = '')
{
    if (empty($credentials)) {
        $credentials = ['user_login' => wp_unslash($_POST['log'] ?? ''), 'user_password' => $_POST['pwd'] ?? '', 'remember' => !empty($_POST['rememberme'])];
    }
    $credentials += ['user_login' => '', 'user_password' => '', 'remember' => false];
    $credentials['user_login'] = trim((string) $credentials['user_login']);
    do_action_ref_array('wp_authenticate', [&$credentials['user_login'], &$credentials['user_password']]);
    if ($secure_cookie === '') {
        $secure_cookie = is_ssl();
    }
    $secure_cookie = apply_filters('secure_signon_cookie', $secure_cookie, $credentials);
    $user = wp_authenticate($credentials['user_login'], $credentials['user_password']);
    if (is_wp_error($user)) {
        return $user;
    }
    wp_set_auth_cookie($user->ID, (bool) $credentials['remember'], $secure_cookie);
    do_action('wp_login', $user->user_login, $user);
    return $user;
}

/** The username and password step of the authenticate chain, with the reference's refusal codes. */
function wp_authenticate_username_password($user, $username, $password)
{
    if ($user instanceof WP_User) {
        return $user;
    }
    if ($username === '' || $password === '') {
        $error = is_wp_error($user) ? $user : new WP_Error();
        if ($username === '') {
            $error->add('empty_username', '<strong>Error:</strong> The username field is empty.');
        }
        if ($password === '') {
            $error->add('empty_password', '<strong>Error:</strong> The password field is empty.');
        }
        return $error;
    }
    $found = get_user_by('login', $username);
    if (!$found) {
        return new WP_Error('invalid_username', '<strong>Error:</strong> The username <strong>' . esc_html($username) . '</strong> is not registered on this site. If you are unsure of your username, try your email address instead.');
    }
    $found = apply_filters('wp_authenticate_user', $found, $password);
    if (is_wp_error($found)) {
        return $found;
    }
    if (!wp_check_password($password, $found->user_pass, $found->ID)) {
        return new WP_Error('incorrect_password', '<strong>Error:</strong> The password you entered for the username <strong>' . esc_html($username) . '</strong> is incorrect.');
    }
    return $found;
}

/** The email-address step of the chain; only an address-shaped username reaches it. */
function wp_authenticate_email_password($user, $email, $password)
{
    if ($user instanceof WP_User || !is_email($email)) {
        return $user;
    }
    if ($password === '') {
        return new WP_Error('empty_password', '<strong>Error:</strong> The password field is empty.');
    }
    $found = get_user_by('email', $email);
    if (!$found) {
        return new WP_Error('invalid_email', 'Unknown email address. Check again or try your username.');
    }
    $found = apply_filters('wp_authenticate_user', $found, $password);
    if (is_wp_error($found)) {
        return $found;
    }
    if (!wp_check_password($password, $found->user_pass, $found->ID)) {
        return new WP_Error('incorrect_password', '<strong>Error:</strong> The password you entered for the email address <strong>' . esc_html($email) . '</strong> is incorrect.');
    }
    return $found;
}

/** The multisite step of the chain; on a single site nobody is marked as spam, so the user passes through. */
function wp_authenticate_spam_check($user)
{
    return $user;
}

function add_role($role, $display_name, $capabilities = [])
{
    return wp_roles()->add_role($role, $display_name, $capabilities);
}

function remove_role($role)
{
    wp_roles()->remove_role($role);
    return null;
}

function sanitize_user_field($field, $value, $user_id, $context)
{
    $field = (string) $field;
    // The field's hooks drop its user_ prefix: user_url is filtered by user_url, not user_user_url.
    $name = str_starts_with($field, 'user_') ? substr($field, 5) : $field;
    if ($field === 'ID') {
        $value = (int) $value;
    }
    if ($context === 'raw') {
        return $value;
    }
    if ($context === 'db') {
        return apply_filters("pre_user_{$name}", $value);
    }
    if ($context === 'edit') {
        $value = apply_filters("edit_user_{$name}", $value, $user_id);
        if ($field === 'user_url' && is_string($value)) {
            $value = esc_url($value);
        }
        return is_string($value) ? esc_attr($value) : $value;
    }
    $value = apply_filters("user_{$name}", $value, $user_id, $context);
    if ($context === 'attribute') {
        return is_string($value) ? esc_attr($value) : $value;
    }
    if ($context === 'js') {
        return is_string($value) ? esc_js($value) : $value;
    }
    return $value;
}

function wp_get_session_token()
{
    return Runtime::current()->reader->sessionToken;
}

function wp_destroy_current_session()
{
    $token = wp_get_session_token();
    $user = wp_get_current_user();
    if ($token === '' || (int) $user->ID === 0) {
        return;
    }
    (new Sessions(new Users(Runtime::current()->db)))->destroy((int) $user->ID, $token);
}

function is_user_member_of_blog($user_id = 0, $blog_id = 0)
{
    $user_id = $user_id ?: get_current_user_id();
    return $user_id > 0 && get_userdata($user_id) !== false;
}

/** The default on profile_update: a user who changed the generated password stops being reminded to. */
function default_password_nag_edit_user($user_ID, $old_data)
{
    $user = get_userdata($user_ID);
    if ($user && $old_data instanceof WP_User && $user->user_pass !== $old_data->user_pass && get_user_meta($user_ID, 'default_password_nag', true)) {
        delete_user_meta($user_ID, 'default_password_nag');
    }
}

/**
 * The signed-in user's screen settings: the wp-settings-{id} cookie when the
 * browser sent one (only letters, digits, = & _ - kept), else the stored
 * user-settings option. The first read is kept for the request in
 * $_updated_user_settings, as the reference keeps it. Signed out: [].
 */
function get_all_user_settings()
{
    $user = get_current_user_id();
    if ($user === 0) {
        return [];
    }
    if (isset($GLOBALS['_updated_user_settings']) && is_array($GLOBALS['_updated_user_settings'])) {
        return $GLOBALS['_updated_user_settings'];
    }
    $settings = [];
    $cookie = $_COOKIE["wp-settings-{$user}"] ?? null;
    $stored = $cookie === null ? get_user_option('user-settings', $user) : (string) preg_replace('/[^A-Za-z0-9=&_-]/', '', (string) $cookie);
    if (is_string($stored) && strpos($stored, '=') > 0) {
        parse_str($stored, $settings);
    }
    $GLOBALS['_updated_user_settings'] = $settings;
    return $settings;
}

function get_user_setting($name, $default_value = false)
{
    return get_all_user_settings()[$name] ?? $default_value;
}

/** user_has_cap's default: a user who may update core or install plugins or themes may install languages. */
function wp_maybe_grant_install_languages_cap($allcaps)
{
    if (!empty($allcaps['update_core']) || !empty($allcaps['install_plugins']) || !empty($allcaps['install_themes'])) {
        $allcaps['install_languages'] = true;
    }
    return $allcaps;
}

/** user_has_cap's default: whoever may activate plugins may resume a paused one; whoever may switch themes, a paused theme. */
function wp_maybe_grant_resume_extensions_caps($allcaps)
{
    if (!empty($allcaps['activate_plugins'])) {
        $allcaps['resume_plugins'] = true;
    }
    if (!empty($allcaps['switch_themes'])) {
        $allcaps['resume_themes'] = true;
    }
    return $allcaps;
}

/** user_has_cap's default: whoever may install plugins may see the site health checks. */
function wp_maybe_grant_site_health_caps($allcaps, $caps, $args, $user)
{
    if (!empty($allcaps['install_plugins'])) {
        $allcaps['view_site_health_checks'] = true;
    }
    return $allcaps;
}
