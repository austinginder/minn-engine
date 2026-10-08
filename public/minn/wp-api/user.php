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
    // Who the request is, as plugins may say (the engine's own session answers through wp_validate_auth_cookie).
    $id = (int) apply_filters('determine_current_user', false);
    return wp_set_current_user($id > 0 ? $id : 0);
}

/** A reset link mailed to the account a login or email names (the posted user_login when none is given): true, or the refusal. Minn\Runtime\AccountFlows. */
function retrieve_password($user_login = '')
{
    if ($user_login === '' && Runtime::booted()) {
        $user_login = (string) (Runtime::current()->request?->form['user_login'] ?? '');
    }
    return Minn\Runtime\AccountFlows::retrievePassword((string) $user_login);
}

/** A user's new password, announced through password_reset and after_password_reset. */
function reset_password($user, $new_pass)
{
    Minn\Runtime\AccountFlows::resetPassword($user, (string) $new_pass);
}

/** A new account for a login and an email: its id, or every refusal at once. Minn\Runtime\AccountFlows. */
function register_new_user($user_login, $user_email)
{
    return Minn\Runtime\AccountFlows::registerNewUser((string) $user_login, (string) $user_email);
}

/** A new account announced to the site, the user, or both (register_new_user and edit_user_created_user call it). */
function wp_send_new_user_notifications($user_id, $notify = 'both')
{
    wp_new_user_notification($user_id, null, in_array($notify, ['admin', 'user', 'both'], true) ? $notify : 'both');
}

/** Application passwords are supported over HTTPS, or anywhere on a local site. */
function wp_is_application_passwords_supported()
{
    return is_ssl() || wp_get_environment_type() === 'local';
}

function wp_is_application_passwords_available()
{
    return apply_filters('wp_is_application_passwords_available', wp_is_application_passwords_supported());
}

/** Whether a user may sign in by application password: the site allows them, then wp_is_application_passwords_available_for_user. */
function wp_is_application_passwords_available_for_user($user)
{
    $user = is_numeric($user) ? get_user_by('id', $user) : $user;
    if (!$user instanceof WP_User || !$user->exists() || !wp_is_application_passwords_available()) {
        return false;
    }
    return apply_filters('wp_is_application_passwords_available_for_user', true, $user);
}

/** A user signed in by application password (in an API request), the input as given, or the refusal: Minn\Runtime\ApplicationPasswordSignIn. */
function wp_authenticate_application_password($input_user, $username, $password)
{
    return Minn\Runtime\ApplicationPasswordSignIn::authenticate($input_user, (string) $username, (string) $password);
}

/** An earlier callback's user, as given; else the REST request's application password sign-in, heard again now that plugins may refuse it: Minn\Runtime\ApplicationPasswordSignIn. */
function wp_validate_application_password($input_user)
{
    return Minn\Runtime\ApplicationPasswordSignIn::validate($input_user);
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
    // A meta capability asks the key's auth filters as well (probe meta-api).
    $caps = Minn\Runtime\MetaKeys::capabilities((string) $cap, (int) $user_id, $args)
        ?? Runtime::current()->capabilities->map((string) $cap, (int) $user_id, isset($args[0]) && is_numeric($args[0]) ? (int) $args[0] : null);
    return apply_filters('map_meta_cap', $caps, $cap, $user_id, $args);
}

function get_role($role)
{
    $roles = Runtime::current()->capabilities->roles()->all();
    return isset($roles[$role]) ? new WP_Role($role, $roles[$role]['capabilities']) : null;
}

/** The site's one roles object ($wp_roles), made the first time it is asked for. */
function wp_roles()
{
    if (!isset($GLOBALS['wp_roles']) || !$GLOBALS['wp_roles'] instanceof WP_Roles) {
        $GLOBALS['wp_roles'] = new WP_Roles();
    }
    return $GLOBALS['wp_roles'];
}

/**
 * The user select: the users get_users finds for the caller's query (through
 * wp_dropdown_users_args), each shown by the field asked for, or by display
 * name and login; nothing when there are none, or only one and the caller
 * hides a lone author.
 */
function wp_dropdown_users($args = '')
{
    $r = wp_parse_args($args, ['blog_id' => get_current_blog_id(), 'show_option_all' => '', 'show_option_none' => '', 'hide_if_only_one_author' => '', 'orderby' => 'display_name', 'order' => 'ASC', 'include' => '', 'exclude' => '', 'multi' => 0, 'show' => 'display_name', 'echo' => 1, 'selected' => 0, 'name' => 'user', 'class' => '', 'id' => '', 'who' => '', 'include_selected' => false, 'option_none_value' => -1, 'role' => '', 'role__in' => [], 'role__not_in' => [], 'capability' => '', 'capability__in' => [], 'capability__not_in' => []]);
    $query = [];
    foreach (['blog_id', 'include', 'exclude', 'orderby', 'order', 'who', 'role', 'role__in', 'role__not_in', 'capability', 'capability__in', 'capability__not_in'] as $key) {
        $query[$key] = $r[$key];
    }
    $query['fields'] = ['ID', 'user_login', $r['show'] === 'display_name_with_login' ? 'display_name' : $r['show']];
    $users = get_users(apply_filters('wp_dropdown_users_args', $query, $r));
    $output = '';
    if ($users !== [] && (empty($r['hide_if_only_one_author']) || count($users) > 1)) {
        $name = esc_attr($r['name']);
        $id = $r['multi'] && !$r['id'] ? '' : " id='" . ($r['id'] ? esc_attr($r['id']) : $name) . "'";
        $output = "<select name='{$name}'{$id} class='" . $r['class'] . "'>\n" . _minn_user_option_lines($users, $r) . '</select>';
    }
    $html = apply_filters('wp_dropdown_users', $output);
    if ($r['echo']) {
        echo $html;
    }
    return $html;
}

/** @internal the dropdown's option lines: the all and none choices, then each user (the selected one added when asked) */
function _minn_user_option_lines(array $users, array $r): string
{
    $out = $r['show_option_all'] ? "\t<option value='0'>{$r['show_option_all']}</option>\n" : '';
    if ($r['show_option_none']) {
        $out .= "\t<option value='" . esc_attr($r['option_none_value']) . "'" . selected($r['option_none_value'], $r['selected'], false) . ">{$r['show_option_none']}</option>\n";
    }
    $selected = (int) $r['selected'];
    if ($r['include_selected'] && $selected > 0 && !in_array($selected, array_map(static fn ($u) => (int) $u->ID, $users), true) && ($user = get_userdata($selected))) {
        $users[] = $user;
    }
    foreach ($users as $user) {
        $show = (string) $r['show'];
        $display = match (true) {
            $show === 'display_name_with_login' => sprintf(_x('%1$s (%2$s)', 'user dropdown'), $user->display_name, $user->user_login),
            !empty($user->$show) => $user->$show,
            default => '(' . $user->user_login . ')',
        };
        $out .= "\t<option value='{$user->ID}'" . selected($user->ID, $r['selected'], false) . '>' . esc_html($display) . "</option>\n";
    }
    return $out;
}

function get_editable_roles()
{
    return apply_filters('editable_roles', wp_roles()->roles);
}

function wp_get_user_contact_methods($user = null)
{
    return apply_filters('user_contactmethods', [], $user);
}

/** The editor's per-user preferences, registered on init under the site's prefix (probe meta-registry). */
function wp_register_persisted_preferences_meta()
{
    register_meta('user', $GLOBALS['wpdb']->get_blog_prefix() . 'persisted_preferences', [
        'type' => 'object',
        'single' => true,
        'show_in_rest' => [
            'name' => 'persisted_preferences',
            'type' => 'object',
            'schema' => [
                'type' => 'object',
                'context' => ['edit'],
                'properties' => ['_modified' => ['description' => __('The date and time the preferences were updated.'), 'type' => 'string', 'format' => 'date-time', 'readonly' => false]],
                'additionalProperties' => true,
            ],
        ],
    ]);
}

/**
 * The toolbar preference minn-admin registers on init (its
 * register_toolbar_meta), for a boot that runs no init: 'false' or 'true',
 * editable by whoever may edit the user.
 */
function _minn_register_toolbar_meta()
{
    register_meta('user', 'show_admin_bar_front', [
        'type' => 'string',
        'single' => true,
        'default' => 'true',
        'show_in_rest' => true,
        'sanitize_callback' => static fn ($value) => $value === 'false' ? 'false' : 'true',
        'auth_callback' => static fn ($allowed, $meta_key, $object_id) => current_user_can('edit_user', $object_id),
    ]);
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

/** The site's user count as last counted (the user_count option), -1 before it was ever counted (probe theme-symbols). */
function get_user_count($network_id = null)
{
    return (int) get_network_option($network_id, 'user_count', -1);
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
    [$key, $stored] = PasswordReset::mint();
    do_action('retrieve_password_key', $user['user_login'], $key);
    // Saved through wp_update_user, as the reference saves it (its filters and profile_update run).
    $saved = wp_update_user(['ID' => (int) $user['ID'], 'user_activation_key' => $stored]);
    if (is_wp_error($saved)) {
        return new WP_Error('no_password_key_update', __('Could not save password reset key to database.'));
    }
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
    $lifetime = (int) apply_filters('password_reset_expiration', DAY_IN_SECONDS);
    $status = (new PasswordReset(new Users(Runtime::current()->db)))->status($user, $key, $lifetime);
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
    _minn_rehash_password($found, $password);
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
    _minn_rehash_password($found, $password);
    return $found;
}

/** @internal a good sign-in replaces an outdated hash (another cost, phpass, plain bcrypt) with the current one (probe password-rehash) */
function _minn_rehash_password(WP_User $user, string $password): void
{
    if (wp_password_needs_rehash($user->user_pass, $user->ID)) {
        wp_set_password($password, $user->ID);
    }
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

/** Whether the site counts more than ten thousand users, through wp_is_large_user_count. */
function wp_is_large_user_count($network_id = null)
{
    $count = get_user_count($network_id);
    return apply_filters('wp_is_large_user_count', $count > 10000, $count, $network_id);
}

/** Takes every role and capability from a user. */
function wp_revoke_user($id)
{
    (new WP_User((int) $id))->remove_all_caps();
}

/** The signed-in user's sessions. */
function wp_get_all_sessions()
{
    return WP_Session_Tokens::get_instance(get_current_user_id())->get_all();
}

/** Ends the signed-in user's other sessions, keeping this one. */
function wp_destroy_other_sessions()
{
    $token = wp_get_session_token();
    if ($token) {
        WP_Session_Tokens::get_instance(get_current_user_id())->destroy_others($token);
    }
}

/** Ends every session of the signed-in user. */
function wp_destroy_all_sessions()
{
    WP_Session_Tokens::get_instance(get_current_user_id())->destroy_all();
}

/** The two kinds of personal data request: export and erase. */
function _wp_privacy_action_request_types()
{
    return ['export_personal_data', 'remove_personal_data'];
}

/**
 * Records a personal data request (probe user-requests): a user_request
 * post for the address, the action and the data, pending or confirmed,
 * owned by the user with that address. A bad address, an action other
 * than export or erase, another status, or an unfinished request for the
 * same address and action is refused.
 */
function wp_create_user_request($email_address = '', $action_name = '', $request_data = [], $status = 'pending')
{
    $email_address = sanitize_email($email_address);
    $action_name = sanitize_key($action_name);
    $refusal = match (true) {
        !is_email($email_address) => ['invalid_email', __('Invalid email address.')],
        !in_array($action_name, _wp_privacy_action_request_types(), true) => ['invalid_action', __('Invalid action name.')],
        !in_array($status, ['pending', 'confirmed'], true) => ['invalid_status', __('Invalid request status.')],
        (bool) get_posts(['post_type' => 'user_request', 'post_name__in' => [$action_name], 'title' => $email_address, 'post_status' => ['request-pending', 'request-confirmed'], 'fields' => 'ids', 'numberposts' => 1]) => ['duplicate_request', __('An incomplete personal data request for this email address already exists.')],
        default => null,
    };
    if ($refusal !== null) {
        return new WP_Error($refusal[0], $refusal[1]);
    }
    $user = get_user_by('email', $email_address);
    return wp_insert_post(['post_author' => $user ? $user->ID : 0, 'post_name' => $action_name, 'post_title' => $email_address, 'post_content' => wp_json_encode($request_data), 'post_status' => 'request-' . $status, 'post_type' => 'user_request', 'post_date' => current_time('mysql', false), 'post_date_gmt' => current_time('mysql', true)], true);
}

/** A personal data request by its post, or false for anything else. */
function wp_get_user_request($request_id)
{
    $post = get_post(absint($request_id));
    return $post instanceof WP_Post && $post->post_type === 'user_request' ? new WP_User_Request($post) : false;
}

/** The action a request asks for, as its mails name it. */
function wp_user_request_action_description($action_name)
{
    $description = match ($action_name) {
        'export_personal_data' => __('Export Personal Data'),
        'remove_personal_data' => __('Erase Personal Data'),
        default => sprintf(__('Confirm the "%s" action'), $action_name),
    };
    return apply_filters('user_request_action_description', $description, $action_name);
}

/** A new confirmation key for a request: twenty letters and digits, stored hashed, the request pending again. */
function wp_generate_user_request_key($request_id)
{
    $key = wp_generate_password(20, false);
    wp_update_post(['ID' => $request_id, 'post_status' => 'request-pending', 'post_password' => wp_fast_hash($key)]);
    return $key;
}

/** Whether a confirmation key opens a request: it must be pending (or failed), the key given and right, and no older than a day (user_request_key_expiration). */
function wp_validate_user_request_key($request_id, $key)
{
    $request = wp_get_user_request($request_id);
    if (!$request || !$request->confirm_key || !$request->modified_timestamp) {
        return new WP_Error('invalid_request', __('Invalid personal data request.'));
    }
    if (!in_array($request->status, ['request-pending', 'request-failed'], true)) {
        return new WP_Error('expired_request', __('This personal data request has expired.'));
    }
    if (empty($key)) {
        return new WP_Error('missing_key', __('The confirmation key is missing from this personal data request.'));
    }
    $expires = $request->modified_timestamp + (int) apply_filters('user_request_key_expiration', DAY_IN_SECONDS);
    if (!wp_verify_fast_hash($key, $request->confirm_key)) {
        return new WP_Error('invalid_key', __('The confirmation key is invalid for this personal data request.'));
    }
    return time() > $expires ? new WP_Error('expired_key', __('The confirmation key has expired for this personal data request.')) : true;
}

/**
 * Mails the requester the link that confirms a request, in their language
 * (the site's for a visitor), with a fresh key; subject, content and
 * headers each pass their filter. True when the mail went.
 */
function wp_send_user_request($request_id)
{
    $request_id = absint($request_id);
    $request = wp_get_user_request($request_id);
    if (!$request) {
        return new WP_Error('invalid_request', __('Invalid personal data request.'));
    }
    $switched = !empty($request->user_id) ? switch_to_user_locale($request->user_id) : switch_to_locale(get_locale());
    $data = ['request' => $request, 'email' => $request->email, 'description' => wp_user_request_action_description($request->action_name), 'confirm_url' => add_query_arg(['action' => 'confirmaction', 'request_id' => $request_id, 'confirm_key' => wp_generate_user_request_key($request_id)], wp_login_url()), 'sitename' => wp_specialchars_decode(get_option('blogname'), ENT_QUOTES), 'siteurl' => home_url()];
    $subject = apply_filters('user_request_action_email_subject', sprintf(__('[%1$s] Confirm Action: %2$s'), $data['sitename'], $data['description']), $data['sitename'], $data);
    /* translators: Do not translate DESCRIPTION, CONFIRM_URL, SITENAME, SITEURL: those are placeholders. */
    $content = apply_filters('user_request_action_email_content', __("Howdy,\n\nA request has been made to perform the following action on your account:\n\n     ###DESCRIPTION###\n\nTo confirm this, please click on the following link:\n###CONFIRM_URL###\n\nYou can safely ignore and delete this email if you do not want to\ntake this action.\n\nRegards,\nAll at ###SITENAME###\n###SITEURL###"), $data);
    $content = strtr($content, ['###DESCRIPTION###' => $data['description'], '###CONFIRM_URL###' => sanitize_url($data['confirm_url']), '###EMAIL###' => $data['email'], '###SITENAME###' => $data['sitename'], '###SITEURL###' => sanitize_url($data['siteurl'])]);
    $headers = apply_filters('user_request_action_email_headers', '', $subject, $content, $request_id, $data);
    $sent = wp_mail($data['email'], $subject, $content, $headers);
    if ($switched) {
        restore_previous_locale();
    }
    return $sent ? true : new WP_Error('privacy_email_error', __('Unable to send personal data export confirmation email.'));
}

/** A pending (or failed) request confirmed: its time noted, its status confirmed (on user_request_action_confirmed). */
function _wp_privacy_account_request_confirmed($request_id)
{
    $request = wp_get_user_request($request_id);
    if (!$request || !in_array($request->status, ['request-pending', 'request-failed'], true)) {
        return;
    }
    update_post_meta($request_id, '_wp_user_request_confirmed_timestamp', time());
    wp_update_post(['ID' => $request_id, 'post_status' => 'request-confirmed']);
}

/** What the confirmation page says: thanks for an export or erasure, a plain confirmation otherwise; filtered by user_request_action_confirmed_message. */
function _wp_privacy_account_request_confirmed_message($request_id)
{
    $request = wp_get_user_request($request_id);
    $message = '<p class="success">' . __('Action has been confirmed.') . '</p><p>' . __('The site administrator has been notified and will fulfill your request as soon as possible.') . '</p>';
    if ($request && $request->action_name === 'export_personal_data') {
        $message = '<p class="success">' . __('Thanks for confirming your export request.') . '</p><p>' . __('The site administrator has been notified. You will receive a link to download your export via email when they fulfill your request.') . '</p>';
    } elseif ($request && $request->action_name === 'remove_personal_data') {
        $message = '<p class="success">' . __('Thanks for confirming your erasure request.') . '</p><p>' . __('The site administrator has been notified. You will receive an email confirmation when they erase your data.') . '</p>';
    }
    return apply_filters('user_request_action_confirmed_message', $message, $request_id);
}

/** Tells the site owner, once, that a request was confirmed and where to handle it (on user_request_action_confirmed). */
function _wp_privacy_send_request_confirmation_notification($request_id)
{
    $request = wp_get_user_request($request_id);
    if (!$request instanceof WP_User_Request || $request->status !== 'request-confirmed' || get_post_meta($request_id, '_wp_admin_notified', true)) {
        return;
    }
    $manage = ['export_personal_data' => admin_url('export-personal-data.php'), 'remove_personal_data' => admin_url('erase-personal-data.php')][$request->action_name] ?? '';
    $data = ['request' => $request, 'user_email' => $request->email, 'description' => wp_user_request_action_description($request->action_name), 'manage_url' => $manage, 'sitename' => wp_specialchars_decode(get_option('blogname'), ENT_QUOTES), 'siteurl' => home_url(), 'admin_email' => apply_filters('user_request_confirmed_email_to', get_site_option('admin_email'), $request)];
    $subject = apply_filters('user_request_confirmed_email_subject', sprintf(__('[%1$s] Action Confirmed: %2$s'), $data['sitename'], $data['description']), $data['sitename'], $data);
    /* translators: Do not translate SITENAME, USER_EMAIL, DESCRIPTION, MANAGE_URL, SITEURL: those are placeholders. */
    $content = apply_filters('user_request_confirmed_email_content', __("Howdy,\n\nA user data privacy request has been confirmed on ###SITENAME###:\n\nUser: ###USER_EMAIL###\nRequest: ###DESCRIPTION###\n\nYou can view and manage these data privacy requests here:\n\n###MANAGE_URL###\n\nRegards,\nAll at ###SITENAME###\n###SITEURL###"), $data);
    $content = strtr($content, ['###SITENAME###' => $data['sitename'], '###USER_EMAIL###' => $data['user_email'], '###DESCRIPTION###' => $data['description'], '###MANAGE_URL###' => sanitize_url($data['manage_url']), '###SITEURL###' => sanitize_url($data['siteurl'])]);
    $headers = apply_filters('user_request_confirmed_email_headers', '', $subject, $content, $request_id, $data);
    if (wp_mail($data['admin_email'], $subject, $content, $headers)) {
        update_post_meta($request_id, '_wp_admin_notified', true);
    }
}

/**
 * @internal the link a personal data request's mail carries
 * (wp-login.php?action=confirmaction): [true, the confirmation message]
 * once the key opens the request and plugins heard it confirmed
 * (user_request_action_confirmed); [false, why] for a missing id or key, or
 * a key that does not open it.
 */
function _minn_confirm_user_request(?string $request_id, ?string $confirm_key): array
{
    if ($request_id === null) {
        return [false, __('Missing request ID.')];
    }
    if ($confirm_key === null) {
        return [false, __('Missing confirm key.')];
    }
    $result = wp_validate_user_request_key((int) $request_id, sanitize_text_field(wp_unslash($confirm_key)));
    if (is_wp_error($result)) {
        return [false, $result->get_error_message()];
    }
    do_action('user_request_action_confirmed', (int) $request_id);
    return [true, _wp_privacy_account_request_confirmed_message((int) $request_id)];
}

/** Deprecated since 3.3: get_user_by('email'). */
function get_user_by_email($email)
{
    _deprecated_function(__FUNCTION__, '3.3.0', "get_user_by('email')");
    return get_user_by('email', $email);
}

/** The old user globals set for a user (the current one by default), or emptied for nobody. */
function setup_userdata($for_user_id = 0)
{
    global $user_login, $userdata, $user_level, $user_ID, $user_email, $user_url, $user_identity;
    $user = get_userdata($for_user_id ?: get_current_user_id());
    $user_ID = $user ? (int) $user->ID : 0;
    $user_level = $user ? (int) $user->user_level : 0;
    $userdata = $user ?: null;
    $user_login = $user ? $user->user_login : '';
    $user_email = $user ? $user->user_email : '';
    $user_url = $user ? $user->user_url : '';
    $user_identity = $user ? $user->display_name : '';
}

/** Deprecated since 3.1: the site's users with their capabilities row (Content\Users). */
function get_users_of_blog($id = '')
{
    _deprecated_function(__FUNCTION__, '3.1.0', 'get_users()');
    global $wpdb;
    return array_map(static fn (array $row): object => (object) $row, (new Users(Runtime::current()->db))->withCapabilities($wpdb->get_blog_prefix($id ?: get_current_blog_id()) . 'capabilities'));
}

/** Post counts for several users (as stored, 0 for none), published or also private (Runtime\PostLookup). */
function count_many_users_posts($users, $post_type = 'post', $public_only = false)
{
    if (empty($users) || !is_array($users)) {
        return [];
    }
    $counts = _minn_post_lookup()->countsByAuthors(array_map('absint', $users), array_map('strval', (array) $post_type), $public_only ? ['publish'] : ['publish', 'private']);
    foreach ($users as $id) {
        $counts[$id] ??= 0;
    }
    return $counts;
}

/** Whether a user may reset their password, through allow_password_reset; false for anything but a user. */
function wp_is_password_reset_allowed_for_user($user)
{
    return $user instanceof WP_User ? apply_filters('allow_password_reset', true, $user->ID) : false;
}

/** Deprecated since 4.5: wp_get_current_user. */
function get_currentuserinfo()
{
    _deprecated_function(__FUNCTION__, '4.5.0', 'wp_get_current_user()');
    return wp_get_current_user();
}

/** The ids of users whose capabilities name none of the site's roles (Content\Users). */
function wp_get_users_with_no_role($site_id = null)
{
    global $wpdb;
    $pattern = (string) preg_replace('/[^a-zA-Z_\|-]/', '', implode('|', array_keys(wp_roles()->get_names())));
    return (new Users(Runtime::current()->db))->withoutRole($wpdb->get_blog_prefix($site_id) . 'capabilities', $pattern);
}

/** Deprecated since 5.4: wp_get_user_request. */
function wp_get_user_request_data($request_id)
{
    _deprecated_function(__FUNCTION__, '5.4.0', 'wp_get_user_request()');
    return wp_get_user_request($request_id);
}
