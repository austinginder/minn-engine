<?php
/**
 * The pluggable functions: loaded after the plugins (Runtime::loadPluggables),
 * each defined only when no plugin defined it first, so a plugin's own
 * wp_mail or wp_hash_password wins as it does on the reference.
 */

use Minn\Auth\AuthCookies;
use Minn\Auth\Cookie;
use Minn\Auth\Nonce;
use Minn\Auth\Password;
use Minn\Auth\Salts;
use Minn\Auth\Sessions;
use Minn\Content\Users;
use Minn\Runtime\Runtime;
use Minn\Support\Url;
use Minn\Runtime\Avatar;

if (!function_exists('wp_create_nonce')) :
function wp_create_nonce($action = -1)
{
    $user = wp_get_current_user();
    $uid = (int) apply_filters('nonce_user_logged_out', $user->ID, $action);
    return Nonce::create($uid, wp_get_session_token(), (string) $action);
}
endif;

if (!function_exists('wp_verify_nonce')) :
function wp_verify_nonce($nonce, $action = -1)
{
    $nonce = (string) $nonce;
    $user = wp_get_current_user();
    $uid = (int) apply_filters('nonce_user_logged_out', $user->ID, $action);
    if ($nonce === '') {
        return false;
    }
    $token = wp_get_session_token();
    $tick = Nonce::tick();
    if (hash_equals(Nonce::at($tick, $uid, $token, (string) $action), $nonce)) {
        return 1;
    }
    if (hash_equals(Nonce::at($tick - 1, $uid, $token, (string) $action), $nonce)) {
        return 2;
    }
    do_action('wp_verify_nonce_failed', $nonce, $action, $user, $token);
    return false;
}
endif;

if (!function_exists('wp_nonce_tick')) :
function wp_nonce_tick($action = -1)
{
    return Nonce::tick();
}
endif;

if (!function_exists('check_admin_referer')) :
function check_admin_referer($action = -1, $query_arg = '_wpnonce')
{
    $nonce = (string) (Runtime::current()->request?->form[$query_arg] ?? Runtime::current()->request?->query[$query_arg] ?? '');
    $result = wp_verify_nonce($nonce, $action);
    do_action('check_admin_referer', $action, $result);
    if (!$result) {
        wp_nonce_ays($action);
    }
    return $result;
}
endif;

if (!function_exists('check_ajax_referer')) :
function check_ajax_referer($action = -1, $query_arg = false, $stop = true)
{
    $request = Runtime::current()->request;
    $nonce = '';
    foreach (array_filter([$query_arg ?: null, '_ajax_nonce', '_wpnonce']) as $key) {
        $nonce = (string) ($request?->form[$key] ?? $request?->query[$key] ?? '');
        if ($nonce !== '') {
            break;
        }
    }
    $result = wp_verify_nonce($nonce, $action);
    do_action('check_ajax_referer', $action, $result);
    if ($stop && $result === false) {
        wp_die('-1', 403);
    }
    return $result;
}
endif;

if (!function_exists('wp_validate_redirect')) :
function wp_validate_redirect($location, $fallback_url = '')
{
    $location = wp_sanitize_redirect(trim((string) $location, " \t\n\r\0\x08\x0B"));
    $safe = Url::safeRedirect($location, static fn (string $host) => (array) apply_filters('allowed_redirect_hosts', [wp_parse_url(home_url())['host'] ?? ''], $host));
    return $safe ?? $fallback_url;
}
endif;

if (!function_exists('wp_sanitize_redirect')) :
function wp_sanitize_redirect($location)
{
    $location = (string) $location;
    $location = preg_replace('|[^a-z0-9-~+_.?#=&;,/:%!*\[\]()@]|i', '', $location);
    return preg_replace('|%0[0-9a-f]|i', '', $location);
}
endif;

if (!function_exists('wp_safe_redirect')) :
function wp_safe_redirect($location, $status = 302, $x_redirect_by = 'WordPress')
{
    $fallback = apply_filters('wp_safe_redirect_fallback', admin_url(), $status);
    return wp_redirect(wp_validate_redirect(wp_sanitize_redirect((string) $location), $fallback), $status, $x_redirect_by);
}
endif;

if (!function_exists('wp_redirect')) :
function wp_redirect($location, $status = 302, $x_redirect_by = 'WordPress')
{
    $location = apply_filters('wp_redirect', $location, $status);
    $status = apply_filters('wp_redirect_status', $status, $location);
    if (!$location) {
        return false;
    }
    $location = wp_sanitize_redirect($location);
    $x_redirect_by = apply_filters('x_redirect_by', $x_redirect_by, $status, $location);
    Runtime::current()->set('redirect', ['location' => $location, 'status' => (int) $status, 'by' => $x_redirect_by]);
    if (!headers_sent()) {
        if (is_string($x_redirect_by)) {
            header("X-Redirect-By: {$x_redirect_by}");
        }
        header("Location: {$location}", true, (int) $status);
    }
    return true;
}
endif;

if (!function_exists('wp_hash')) :
function wp_hash($data, $scheme = 'auth', $algo = 'md5')
{
    return hash_hmac($algo, (string) $data, wp_salt($scheme));
}
endif;

if (!function_exists('wp_salt')) :
function wp_salt($scheme = 'auth')
{
    return Salts::for($scheme);
}
endif;

if (!function_exists('wp_hash_password')) :
function wp_hash_password($password)
{
    [$algorithm, $options] = _minn_password_settings();
    return Password::hash((string) $password, $algorithm, $options);
}
endif;

/** @internal the hashing algorithm and its options, through wp_hash_password_algorithm then wp_hash_password_options (probe password-rehash) */
function _minn_password_settings(): array
{
    $algorithm = apply_filters('wp_hash_password_algorithm', PASSWORD_BCRYPT);
    return [(string) $algorithm, (array) apply_filters('wp_hash_password_options', [], $algorithm)];
}

if (!function_exists('wp_check_password')) :
function wp_check_password($password, $hash, $user_id = '')
{
    $check = Password::verify((string) $password, (string) $hash);
    return apply_filters('check_password', $check, $password, $hash, $user_id);
}
endif;

if (!function_exists('wp_password_needs_rehash')) :
function wp_password_needs_rehash($hash, $user_id = '')
{
    [$algorithm, $options] = _minn_password_settings();
    return apply_filters('password_needs_rehash', Password::needsRehash((string) $hash, $algorithm, $options), $hash, $user_id);
}
endif;

if (!function_exists('wp_set_password')) :
function wp_set_password($password, $user_id)
{
    (new Users(Runtime::current()->db))->setPassword((int) $user_id, wp_hash_password($password));
    clean_user_cache((int) $user_id);
    do_action('wp_set_password', $password, $user_id);
}
endif;

if (!function_exists('wp_mail')) :
function wp_mail($to, $subject, $message, $headers = '', $attachments = [], $embeds = [])
{
    $atts = apply_filters('wp_mail', compact('to', 'subject', 'message', 'headers', 'attachments', 'embeds'));
    $pre = apply_filters('pre_wp_mail', null, $atts);
    if ($pre !== null) {
        return $pre;
    }
    $args = array_merge(compact('to', 'subject', 'message', 'headers', 'attachments', 'embeds'), array_intersect_key((array) $atts, array_flip(['to', 'subject', 'message', 'headers', 'attachments', 'embeds'])));
    $args['to'] = is_array($args['to']) ? $args['to'] : explode(',', (string) $args['to']);
    $args['attachments'] = is_array($args['attachments']) ? $args['attachments'] : explode("\n", str_replace("\r\n", "\n", (string) $args['attachments']));
    $parsed = Minn\Mail\MailHeaders::parse($args['headers']);
    $data = ['to' => $args['to'], 'subject' => $args['subject'], 'message' => $args['message'], 'headers' => $parsed->custom, 'attachments' => $args['attachments'], 'embeds' => (array) $args['embeds']];
    global $phpmailer;
    _minn_wp_phpmailer();
    try {
        _minn_wp_mail_prepare($phpmailer, $parsed, $args);
    } catch (PHPMailer\PHPMailer\Exception $e) {
        // A refused sender reports the arguments without the embeds.
        return _minn_wp_mail_failed($e, array_diff_key($data, ['embeds' => true]));
    }
    do_action_ref_array('phpmailer_init', [&$phpmailer]);
    try {
        $sent = $phpmailer->send();
    } catch (PHPMailer\PHPMailer\Exception $e) {
        return _minn_wp_mail_failed($e, $data);
    }
    if ($sent) {
        do_action('wp_mail_succeeded', $data);
    }
    return $sent;
}
endif;


if (!function_exists('wp_notify_moderator')) :
/**
 * Tells the moderators of a held comment when moderation_notify is on:
 * Minn's own notice (it points to Minn Admin), the reference's filters over
 * its recipients, headers, text and subject, sent through wp_mail so a mail
 * plugin delivers it. True when there was nothing to send.
 */
function wp_notify_moderator($comment_id)
{
    if ((string) get_option('moderation_notify') !== '1') {
        return true;
    }
    $comment = get_comment($comment_id);
    $post = $comment === null ? null : get_post((int) $comment->comment_post_ID);
    if ($post === null) {
        return false;
    }
    $notice = \Minn\Mail\Mailer::noticesFor(Runtime::current()->site)->moderation((string) get_option('admin_email'), (string) $post->post_title, (string) $comment->comment_author, (string) $comment->comment_content);
    $emails = (array) apply_filters('comment_moderation_recipients', $notice->to, (int) $comment_id);
    $headers = apply_filters('comment_moderation_headers', '', (int) $comment_id);
    $text = apply_filters('comment_moderation_text', $notice->body, (int) $comment_id);
    $subject = apply_filters('comment_moderation_subject', $notice->subject, (int) $comment_id);
    foreach ($emails as $email) {
        wp_mail((string) $email, wp_specialchars_decode((string) $subject), (string) $text, $headers);
    }
    return true;
}
endif;

if (!function_exists('wp_notify_postauthor')) :
/**
 * Tells a post's author of a comment on it: Minn's notice, the reference's
 * comment_notification_* filters, wp_mail. An author commenting on their own
 * post is not told unless comment_notification_notify_author says so.
 */
function wp_notify_postauthor($comment_id, $deprecated = null)
{
    $comment = get_comment($comment_id);
    $post = $comment === null ? null : get_post((int) $comment->comment_post_ID);
    $author = $post === null ? false : get_userdata((int) $post->post_author);
    $emails = $author instanceof WP_User && (string) $author->user_email !== '' ? [(string) $author->user_email] : [];
    $emails = array_values(array_filter((array) apply_filters('comment_notification_recipients', $emails, (int) $comment_id)));
    $own = $author instanceof WP_User && $comment !== null && (int) $comment->user_id === (int) $author->ID;
    if ($own && !apply_filters('comment_notification_notify_author', false, (int) $comment_id)) {
        $emails = array_values(array_diff($emails, [(string) $author->user_email]));
    }
    if ($post === null || $emails === []) {
        return false;
    }
    $notice = \Minn\Mail\Mailer::noticesFor(Runtime::current()->site)->newComment($emails[0], (string) $post->post_title, (string) $comment->comment_author, (string) $comment->comment_content, (string) get_permalink($post));
    $text = apply_filters('comment_notification_text', $notice->body, (int) $comment_id);
    $subject = apply_filters('comment_notification_subject', $notice->subject, (int) $comment_id);
    $headers = apply_filters('comment_notification_headers', '', (int) $comment_id);
    foreach ($emails as $email) {
        wp_mail((string) $email, wp_specialchars_decode((string) $subject), (string) $text, $headers);
    }
    return true;
}
endif;

if (!function_exists('wp_new_user_notification')) :
function wp_new_user_notification($user_id, $deprecated = null, $notify = '')
{
    Minn\Runtime\AccountFlows::newUserNotification((int) $user_id, $deprecated, (string) $notify);
}
endif;

if (!function_exists('wp_text_diff')) :
function wp_text_diff($left_string, $right_string, $args = null)
{
    return '';
}
endif;

if (!function_exists('cache_users')) :
function cache_users($user_ids)
{
}
endif;

if (!function_exists('auth_redirect')) :
function auth_redirect()
{
    if (!is_user_logged_in()) {
        wp_redirect(wp_login_url(_minn_request_uri()));
        exit;
    }
}
endif;

if (!function_exists('wp_authenticate')) :
function wp_authenticate($username, $password)
{
    $username = sanitize_user((string) $username);
    $password = trim((string) $password);
    $user = apply_filters('authenticate', null, $username, $password);
    if ($user === null || $user === false) {
        $user = new WP_Error('authentication_failed', '<strong>Error:</strong> Invalid username, email address or incorrect password.');
    }
    // An empty field is the form's mistake, not a failed attempt; only the first code decides.
    if (is_wp_error($user) && !in_array($user->get_error_code(), ['empty_username', 'empty_password'], true)) {
        do_action('wp_login_failed', $username, $user);
    }
    return $user;
}
endif;

if (!function_exists('wp_validate_auth_cookie')) :
function wp_validate_auth_cookie($cookie = '', $scheme = '')
{
    // The engine's sign-in cookie session answers here; an application password answers through wp_validate_application_password.
    $reader = Runtime::current()->reader;
    return $reader->sessionToken !== '' && $reader->userId > 0 ? $reader->userId : false;
}
endif;

if (!function_exists('wp_validate_logged_in_cookie')) :
/** The user an earlier determine_current_user callback found, else the session's (the engine's session covers the logged-in cookie). */
function wp_validate_logged_in_cookie($user_id)
{
    return $user_id ?: wp_validate_auth_cookie();
}
endif;

if (!function_exists('wp_logout')) :
function wp_logout()
{
    do_action('wp_logout', get_current_user_id());
}
endif;

if (!function_exists('wp_clear_auth_cookie')) :
function wp_clear_auth_cookie()
{
    do_action('clear_auth_cookie');
}
endif;

if (!function_exists('wp_password_change_notification')) :
/** Tells the site admin a user changed their password; nothing when the user is that admin. */
function wp_password_change_notification($user)
{
    $user = is_object($user) ? $user : get_userdata((int) $user);
    if (!$user || (string) $user->user_email === (string) get_option('admin_email')) {
        return null;
    }
    $blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
    $email = apply_filters('wp_password_change_notification_email', [
        'to' => get_option('admin_email'),
        'subject' => sprintf('[%s] Password Changed', $blogname),
        'message' => sprintf('Password changed for user: %s', $user->user_login) . "\r\n",
        'headers' => '',
    ], $user, $blogname);
    wp_mail($email['to'], wp_specialchars_decode(sprintf($email['subject'], $blogname)), $email['message'], $email['headers']);
    return null;
}
endif;

if (!function_exists('wp_set_auth_cookie')) :
/** Mints a session and sends the three sign-in cookies, the way the login endpoint does. */
function wp_set_auth_cookie($user_id, $remember = false, $secure = '', $token = '')
{
    $runtime = Runtime::current();
    $user = (new Users($runtime->db))->find((int) $user_id);
    if ($user === null) {
        return null;
    }
    $expiration = time() + (int) apply_filters('auth_cookie_expiration', ($remember ? 14 : 2) * DAY_IN_SECONDS, (int) $user_id, (bool) $remember);
    $expire = $remember ? $expiration + (12 * HOUR_IN_SECONDS) : 0;
    $secure = (bool) apply_filters('secure_auth_cookie', $secure === '' ? is_ssl() : (bool) $secure, (int) $user_id);
    $secureLoggedIn = (bool) apply_filters('secure_logged_in_cookie', $secure && is_ssl(), (int) $user_id, $secure);
    $sessions = new Sessions(new Users($runtime->db));
    if ($token === '') {
        $token = WP_Session_Tokens::get_instance((int) $user_id)->create($expiration);
    }
    $hash = (new AuthCookies($runtime->db, new Cookie($runtime->db, new Users($runtime->db), $sessions)))->hash();
    $scheme = $secure ? 'secure_auth' : 'auth';
    $auth = AuthCookies::mint($user, $expiration, $token, $scheme);
    $loggedIn = AuthCookies::mint($user, $expiration, $token, 'logged_in');
    do_action('set_auth_cookie', $auth, $expire, $expiration, (int) $user_id, $scheme, $token);
    do_action('set_logged_in_cookie', $loggedIn, $expire, $expiration, (int) $user_id, 'logged_in', $token);
    if (!apply_filters('send_auth_cookies', true, $expire, $expiration, (int) $user_id, $scheme, $token) || headers_sent()) {
        return null;
    }
    $name = ($secure ? 'wordpress_sec_' : 'wordpress_') . $hash;
    setcookie($name, $auth, ['expires' => $expire, 'path' => '/wp-admin', 'secure' => $secure, 'httponly' => true]);
    setcookie($name, $auth, ['expires' => $expire, 'path' => '/wp-content/plugins', 'secure' => $secure, 'httponly' => true]);
    setcookie('wordpress_logged_in_' . $hash, $loggedIn, ['expires' => $expire, 'path' => '/', 'secure' => $secureLoggedIn, 'httponly' => true]);
    return null;
}
endif;

if (!function_exists('wp_generate_auth_cookie')) :
/** A cookie under the named scheme's salt; an empty token gets a fresh session, the reference's shape. */
function wp_generate_auth_cookie($user_id, $expiration, $scheme = 'auth', $token = '')
{
    $runtime = Runtime::current();
    $user = (new Users($runtime->db))->find((int) $user_id);
    if ($user === null) {
        return '';
    }
    if ((string) $token === '') {
        $request = $runtime->request;
        $token = (new Sessions(new Users($runtime->db)))->create((int) $user_id, (int) $expiration, (string) ($request?->remoteAddress ?? ''), (string) ($request?->header('user-agent') ?? ''));
    }
    $cookie = AuthCookies::mint($user, (int) $expiration, (string) $token, (string) $scheme);
    return apply_filters('auth_cookie', $cookie, (int) $user_id, (int) $expiration, (string) $scheme, (string) $token);
}
endif;

if (!function_exists('wp_parse_auth_cookie')) :
/** The four-part cookie split into its named fields plus the scheme, false when it does not parse (probed keys). */
function wp_parse_auth_cookie($cookie = '', $scheme = '')
{
    if ((string) $cookie === '') {
        $names = ['auth' => defined('AUTH_COOKIE') ? AUTH_COOKIE : '', 'secure_auth' => defined('SECURE_AUTH_COOKIE') ? SECURE_AUTH_COOKIE : '', 'logged_in' => defined('LOGGED_IN_COOKIE') ? LOGGED_IN_COOKIE : ''];
        if ($scheme === '') {
            $scheme = is_ssl() ? 'secure_auth' : 'auth';
        }
        $cookie = (string) (Runtime::current()->request?->cookies[$names[$scheme] ?? ''] ?? '');
    }
    $parts = explode('|', (string) $cookie);
    if (count($parts) !== 4) {
        return false;
    }
    [$username, $expiration, $token, $hmac] = $parts;
    return ['username' => $username, 'expiration' => $expiration, 'token' => $token, 'hmac' => $hmac, 'scheme' => $scheme ?: 'auth'];
}
endif;

if (!function_exists('get_avatar')) :
function get_avatar($id_or_email, $size = 96, $default_value = '', $alt = '', $args = null)
{
    $args = wp_parse_args($args, []);
    $args += array_filter(['size' => $size, 'default' => $default_value, 'alt' => $alt], static fn ($v) => !empty($v));
    $args = wp_parse_args($args, ['size' => 96, 'height' => null, 'width' => null, 'default' => get_option('avatar_default', 'mystery'), 'force_default' => false, 'rating' => get_option('avatar_rating', 'G'), 'scheme' => null, 'alt' => '', 'class' => null, 'force_display' => false, 'loading' => null, 'fetchpriority' => null, 'decoding' => null, 'extra_attr' => '']);
    if (empty($args['default'])) {
        $args['default'] = get_option('avatar_default', 'mystery');
    }
    $args['loading'] ??= wp_get_loading_optimization_attributes('img', ['width' => (int) $args['size'], 'height' => (int) $args['size']], 'get_avatar')['loading'] ?? null;
    $args['decoding'] ??= 'async';
    $args['height'] = $args['height'] ?: $args['size'];
    $args['width'] = $args['width'] ?: $args['size'];
    $avatar = apply_filters('pre_get_avatar', null, $id_or_email, $args);
    if ($avatar !== null) {
        return apply_filters('get_avatar', $avatar, $id_or_email, $args['size'], $args['default'], $args['alt'], $args);
    }
    if (!$args['force_display'] && !get_option('show_avatars')) {
        return false;
    }
    $data = get_avatar_data($id_or_email, $args + ['size' => $args['size']]);
    $url2x = get_avatar_url($id_or_email, array_merge($args, ['size' => $args['size'] * 2]));
    if (empty($data['url']) || is_wp_error($data['url'])) {
        return false;
    }
    $class = Avatar::classes((int) $args['size'], !$data['found_avatar'] || $args['force_default'], $args['class']);
    $extra = Avatar::extraAttributes((string) $args['extra_attr'], $args['loading'], $args['decoding']);
    $avatar = sprintf("<img alt='%s' src='%s' srcset='%s' class='%s' height='%d' width='%d' %s/>", esc_attr($args['alt']), esc_url($data['url']), esc_url($url2x) . ' 2x', esc_attr(implode(' ', $class)), (int) $args['height'], (int) $args['width'], $extra);
    return apply_filters('get_avatar', $avatar, $id_or_email, $args['size'], $args['default'], $args['alt'], $args);
}
endif;

if (!function_exists('wp_get_current_user')) :
function wp_get_current_user()
{
    return _wp_get_current_user();
}
endif;

if (!function_exists('wp_set_current_user')) :
function wp_set_current_user($id, $name = '')
{
    $user = new WP_User($id, $name);
    Runtime::current()->set('current_user', $user);
    do_action('set_current_user');
    return $user;
}
endif;

if (!function_exists('is_user_logged_in')) :
function is_user_logged_in()
{
    return wp_get_current_user()->exists();
}
endif;

if (!function_exists('get_userdata')) :
function get_userdata($user_id)
{
    return get_user_by('id', $user_id);
}
endif;

if (!function_exists('get_user_by')) :
function get_user_by($field, $value)
{
    $data = WP_User::get_data_by($field, $value);
    if (!$data) {
        return false;
    }
    $user = new WP_User();
    $user->init($data);
    return $user;
}
endif;

if (!function_exists('wp_rand')) :
function wp_rand($min = null, $max = null)
{
    $min = (int) ($min ?? 0);
    $max = (int) ($max ?? 0);
    if ($max <= $min) {
        return $min === $max ? $min : random_int(min($min, $max), max($min, $max));
    }
    return random_int($min, $max);
}
endif;

if (!function_exists('wp_generate_password')) :
function wp_generate_password($length = 12, $special_chars = true, $extra_special_chars = false)
{
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    if ($special_chars) {
        $chars .= '!@#$%^&*()';
    }
    if ($extra_special_chars) {
        $chars .= '-_ []{}<>~`+=,.;:/?|';
    }
    $password = '';
    for ($i = 0; $i < (int) $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return apply_filters('random_password', $password, $length, $special_chars, $extra_special_chars);
}
endif;

if (!function_exists('get_userdatabylogin')) :
/** @deprecated 3.3.0 A user by login. */
function get_userdatabylogin($user_login)
{
    _deprecated_function(__FUNCTION__, '3.3.0', "get_user_by('login')");
    return get_user_by('login', $user_login);
}
endif;
