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
    return Nonce::create($uid, wp_get_session_token(), is_int($action) ? $action : (string) $action);
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
    $tick = wp_nonce_tick($action);
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
    return Nonce::tick(is_int($action) ? $action : (string) $action);
}
endif;

if (!function_exists('check_admin_referer')) :
function check_admin_referer($action = -1, $query_arg = '_wpnonce')
{
    // The nonce comes from $_REQUEST, as on the reference (probe pluggable-remaining).
    $nonce = (string) ($_REQUEST[$query_arg] ?? '');
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
    $nonce = '';
    foreach (array_filter([$query_arg ?: null, '_ajax_nonce', '_wpnonce']) as $key) {
        $nonce = (string) ($_REQUEST[$key] ?? '');
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

if (!function_exists('_wp_sanitize_utf8_in_redirect')) :
/** One UTF-8 sequence of a redirect's address, URL-encoded. */
function _wp_sanitize_utf8_in_redirect($matches)
{
    return urlencode($matches[0]);
}
endif;

if (!function_exists('wp_sanitize_redirect')) :
/** A redirect's address made safe: spaces as %20, UTF-8 URL-encoded, any other character outside the URL set (and an encoded control character) dropped. */
function wp_sanitize_redirect($location)
{
    return Minn\Http\Location::sanitize((string) $location);
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
    return apply_filters('salt', Salts::for($scheme), $scheme);
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
    // The action also hears the user as they were before the change (probe pluggable-remaining).
    $old_user_data = get_userdata((int) $user_id);
    (new Users(Runtime::current()->db))->setPassword((int) $user_id, wp_hash_password($password));
    clean_user_cache((int) $user_id);
    do_action('wp_set_password', $password, $user_id, $old_user_data);
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
    // The post's author hears too when they may moderate the comment (probe pluggable-remaining).
    $recipients = array_values(array_map('strval', (array) $notice->to));
    $author = get_userdata((int) $post->post_author);
    if ($author instanceof WP_User && (string) $author->user_email !== '' && user_can($author->ID, 'edit_comment', (int) $comment_id) && !in_array((string) $author->user_email, $recipients, true)) {
        $recipients[] = (string) $author->user_email;
    }
    $emails = (array) apply_filters('comment_moderation_recipients', $recipients, (int) $comment_id);
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
 * Tells a post's author of a comment on it, or of a note: Minn's notice,
 * the reference's comment_notification_* filters, wp_mail. An author
 * commenting on their own post is not told unless
 * comment_notification_notify_author says so.
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
    $notices = \Minn\Mail\Mailer::noticesFor(Runtime::current()->site);
    $notice = (string) $comment->comment_type === 'note'
        ? $notices->newNote($emails[0], (string) $post->post_title, (string) $comment->comment_author, (string) $comment->comment_content, admin_url('post.php?post=' . $post->ID . '&action=edit'))
        : $notices->newComment($emails[0], (string) $post->post_title, (string) $comment->comment_author, (string) $comment->comment_content, (string) get_permalink($post));
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

if (!function_exists('cache_users')) :
/** The users not yet cached, read in one query and cached with their meta (probe pluggable-remaining). */
function cache_users($user_ids)
{
    global $wpdb;
    $ids = array_values(array_filter(_get_non_cached_ids($user_ids, 'users')));
    if ($ids === []) {
        return;
    }
    foreach ($wpdb->get_results('SELECT * FROM ' . $wpdb->users . ' WHERE ID IN (' . implode(',', array_map('intval', $ids)) . ')') as $row) {
        update_user_caches($row);
    }
    update_meta_cache('user', $ids);
}
endif;

if (!function_exists('auth_redirect')) :
/**
 * A screen that needs a signed-in user (probe loose-functions): a wp-admin
 * request over http moves to https first when admin screens are secure; a
 * valid sign-in cookie (its scheme filtered) passes, telling auth_redirect;
 * anyone else signs in again, sent back to the address asked for.
 */
function auth_redirect()
{
    $uri = _minn_request_uri();
    $https = _minn_admin_https_address($uri);
    if ($https !== null) {
        wp_redirect($https);
        exit;
    }
    $user_id = wp_validate_auth_cookie('', apply_filters('auth_redirect_scheme', ''));
    if ($user_id) {
        do_action('auth_redirect', $user_id);
        return;
    }
    nocache_headers();
    wp_redirect(wp_login_url((is_ssl() ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? '') . $uri, true));
    exit;
}
endif;

/** @internal a wp-admin request's https address when it came over http and admin screens are secure (secure_auth_redirect); null otherwise */
function _minn_admin_https_address(string $uri): ?string
{
    $secure = (bool) apply_filters('secure_auth_redirect', is_ssl() || force_ssl_admin());
    if (!$secure || is_ssl() || !str_contains($uri, 'wp-admin')) {
        return null;
    }
    return str_starts_with($uri, 'http') ? set_url_scheme($uri, 'https') : 'https://' . ($_SERVER['HTTP_HOST'] ?? '') . $uri;
}

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
/**
 * The user a sign-in cookie proves under its scheme, firing the
 * auth_cookie_* action for the verdict (probe auth-cookies): malformed,
 * expired (a POST or ajax request gets an hour's grace), bad_username,
 * bad_hash, bad_session_token, or valid. With no cookie on a served request,
 * the session the engine already checked when it read the request answers
 * (a REST call's nonce included); with no cookie outside one, $_COOKIE's.
 */
function wp_validate_auth_cookie($cookie = '', $scheme = '')
{
    $runtime = Runtime::current();
    if ((string) $cookie === '' && $runtime->request !== null) {
        $reader = $runtime->reader;
        return $reader->sessionToken !== '' && $reader->userId > 0 ? $reader->userId : false;
    }
    $elements = wp_parse_auth_cookie($cookie, $scheme);
    if (!$elements) {
        do_action('auth_cookie_malformed', $cookie, $scheme);
        return false;
    }
    $grace = wp_doing_ajax() || ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' ? HOUR_IN_SECONDS : 0;
    $users = new Users($runtime->db);
    [$verdict, $record] = (new Cookie($runtime->db, $users, new Sessions($users)))->check($elements['username'], $elements['expiration'], $elements['token'], $elements['hmac'], (string) $elements['scheme'], $grace);
    if ($verdict !== 'valid' || $record === null) {
        do_action('auth_cookie_' . $verdict, $elements);
        return false;
    }
    $user = get_userdata($record->id);
    do_action('auth_cookie_valid', $elements, $user);
    return $record->id;
}
endif;

if (!function_exists('wp_validate_logged_in_cookie')) :
/** The user an earlier determine_current_user callback found; else, on a served request, the session the engine checked; else $_COOKIE's logged-in cookie. */
function wp_validate_logged_in_cookie($user_id)
{
    if ($user_id) {
        return $user_id;
    }
    if (Runtime::current()->request !== null) {
        return wp_validate_auth_cookie();
    }
    return empty($_COOKIE[LOGGED_IN_COOKIE]) ? false : wp_validate_auth_cookie($_COOKIE[LOGGED_IN_COOKIE], 'logged_in');
}
endif;

if (!function_exists('wp_logout')) :
/** The current session ends, the sign-in cookies go, and no one is signed in; then wp_logout (probe pluggable-remaining). */
function wp_logout()
{
    $user_id = get_current_user_id();
    wp_destroy_current_session();
    wp_clear_auth_cookie();
    wp_set_current_user(0);
    do_action('wp_logout', $user_id);
}
endif;

if (!function_exists('wp_clear_auth_cookie')) :
/** Every sign-in cookie expired, unless send_auth_cookies says not to (probe pluggable-remaining). */
function wp_clear_auth_cookie()
{
    $cookies = new AuthCookies(Runtime::current()->db, new Cookie(Runtime::current()->db, new Users(Runtime::current()->db), new Sessions(new Users(Runtime::current()->db))));
    if (!$cookies->clearing() || headers_sent()) {
        return;
    }
    $hash = $cookies->hash();
    foreach ([['wordpress_' . $hash, '/wp-admin'], ['wordpress_' . $hash, '/wp-content/plugins'], ['wordpress_sec_' . $hash, '/wp-admin'], ['wordpress_sec_' . $hash, '/wp-content/plugins'], ['wordpress_logged_in_' . $hash, '/']] as [$name, $path]) {
        setcookie($name, ' ', ['expires' => time() - YEAR_IN_SECONDS, 'path' => $path, 'httponly' => true]);
    }
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
    // The cookies settled with the engine's own sign-in: one path, its hooks told and asked (AuthCookies::settle).
    $runtime = Runtime::current();
    $user = (new Users($runtime->db))->find((int) $user_id);
    if ($user === null) {
        return null;
    }
    $cookies = new AuthCookies($runtime->db, new Cookie($runtime->db, new Users($runtime->db), new Sessions(new Users($runtime->db))));
    $plan = $cookies->settle($user, $remember ? 14 : 2, $secure === '' ? is_ssl() : (bool) $secure, is_ssl(), (string) $token);
    if (!$plan['send'] || headers_sent()) {
        return null;
    }
    $name = ($plan['secure'] ? 'wordpress_sec_' : 'wordpress_') . $cookies->hash();
    setcookie($name, $plan['auth'], ['expires' => $plan['expire'], 'path' => '/wp-admin', 'secure' => $plan['secure'], 'httponly' => true]);
    setcookie($name, $plan['auth'], ['expires' => $plan['expire'], 'path' => '/wp-content/plugins', 'secure' => $plan['secure'], 'httponly' => true]);
    setcookie('wordpress_logged_in_' . $cookies->hash(), $plan['loggedIn'], ['expires' => $plan['expire'], 'path' => '/', 'secure' => $plan['secureLoggedIn'], 'httponly' => true]);
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
        // No scheme, or one it does not know, reads the auth cookie for the connection (probe pluggable-remaining).
        if (!isset($names[$scheme])) {
            $scheme = is_ssl() ? 'secure_auth' : 'auth';
        }
        $name = $names[$scheme] ?? '';
        $cookie = (string) ($_COOKIE[$name] ?? Runtime::current()->request?->cookies[$name] ?? '');
    }
    $parts = explode('|', (string) $cookie);
    if (count($parts) !== 4) {
        return false;
    }
    [$username, $expiration, $token, $hmac] = $parts;
    return ['username' => $username, 'expiration' => $expiration, 'token' => $token, 'hmac' => $hmac, 'scheme' => (string) $scheme];
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
/**
 * A random whole number between the two bounds as the reference draws one:
 * no maximum means 4294967295, bounds given the wrong way round are
 * swapped, and the answer is never negative (its absolute value).
 */
function wp_rand($min = null, $max = null)
{
    $min = (int) ($min ?? 0);
    $max = $max === null ? 4294967295 : (int) $max;
    return abs(random_int(min($min, $max), max($min, $max)));
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

// The deprecated pluggable functions (probe deprecated).

if (!function_exists('get_userdatabylogin')) :
/** @deprecated 3.3.0 A user by login. */
function get_userdatabylogin($user_login)
{
    _deprecated_function(__FUNCTION__, '3.3.0', "get_user_by('login')");
    return get_user_by('login', $user_login);
}
endif;

if (!function_exists('get_user_by_email')) :
/** @deprecated 3.3.0 A user by email. */
function get_user_by_email($email)
{
    _deprecated_function(__FUNCTION__, '3.3.0', "get_user_by('email')");
    return get_user_by('email', $email);
}
endif;

if (!function_exists('get_currentuserinfo')) :
/** @deprecated 4.5.0 The current user. */
function get_currentuserinfo()
{
    _deprecated_function(__FUNCTION__, '4.5.0', 'wp_get_current_user()');
    return wp_get_current_user();
}
endif;

if (!function_exists('set_current_user')) :
/** @deprecated 3.0.0 Makes the user current. */
function set_current_user($id, $name = '')
{
    _deprecated_function(__FUNCTION__, '3.0.0', 'wp_set_current_user()');
    return wp_set_current_user($id, $name);
}
endif;

if (!function_exists('wp_setcookie')) :
/** @deprecated 2.5.0 The login cookies for the user with that login. */
function wp_setcookie($username, $password = '', $already_md5 = false, $home = '', $siteurl = '', $remember = false)
{
    _deprecated_function(__FUNCTION__, '2.5.0', 'wp_set_auth_cookie()');
    $user = get_user_by('login', $username);
    wp_set_auth_cookie($user->ID ?? 0, $remember);
}
endif;

if (!function_exists('wp_clearcookie')) :
/** @deprecated 2.5.0 The login cookies cleared. */
function wp_clearcookie()
{
    _deprecated_function(__FUNCTION__, '2.5.0', 'wp_clear_auth_cookie()');
    wp_clear_auth_cookie();
}
endif;

if (!function_exists('wp_get_cookie_login')) :
/** @deprecated 2.5.0 Never a login from the cookies. */
function wp_get_cookie_login()
{
    _deprecated_function(__FUNCTION__, '2.5.0');
    return false;
}
endif;

if (!function_exists('wp_login')) :
/** @deprecated 2.5.0 Whether the login and password authenticate; the failure's message left in $error. */
function wp_login($username, $password, $deprecated = '')
{
    _deprecated_function(__FUNCTION__, '2.5.0', 'wp_signon()');
    $user = wp_authenticate($username, $password);
    if (!is_wp_error($user)) {
        return true;
    }
    $GLOBALS['error'] = $user->get_error_message();
    return false;
}
endif;
