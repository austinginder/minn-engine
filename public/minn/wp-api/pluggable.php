<?php
/** Nonces, hashes, passwords, referers, wp_die, and mail. */

use Minn\Auth\AuthCookies;
use Minn\Auth\Cookie;
use Minn\Auth\Nonce;
use Minn\Auth\Password;
use Minn\Auth\Salts;
use Minn\Auth\Sessions;
use Minn\Content\Users;
use Minn\Runtime\Runtime;
use Minn\Support\Url;

function wp_get_session_token()
{
    return Runtime::current()->reader->sessionToken;
}

function wp_create_nonce($action = -1)
{
    $user = wp_get_current_user();
    $uid = (int) apply_filters('nonce_user_logged_out', $user->ID, $action);
    return Nonce::create($uid, wp_get_session_token(), (string) $action);
}

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

function wp_nonce_tick($action = -1)
{
    return Nonce::tick();
}

function wp_nonce_field($action = -1, $name = '_wpnonce', $referer = true, $display = true)
{
    $name = esc_attr((string) $name);
    $field = '<input type="hidden" id="' . $name . '" name="' . $name . '" value="' . wp_create_nonce($action) . '" />';
    if ($referer) {
        $field .= wp_referer_field(false);
    }
    if ($display) {
        echo $field;
    }
    return $field;
}

function wp_nonce_url($actionurl, $action = -1, $name = '_wpnonce')
{
    $actionurl = str_replace('&amp;', '&', (string) $actionurl);
    return esc_html(add_query_arg($name, wp_create_nonce($action), $actionurl));
}

function wp_nonce_ays($action)
{
    wp_die('The link you followed has expired.', 'Something went wrong.', 403);
}

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

function wp_referer_field($display = true)
{
    $field = '<input type="hidden" name="_wp_http_referer" value="' . esc_attr(wp_unslash(_minn_request_uri())) . '" />';
    if ($display) {
        echo $field;
    }
    return $field;
}

function wp_original_referer_field($display = true, $jump_back_to = 'current')
{
    $ref = wp_get_original_referer() ?: ($jump_back_to === 'previous' ? wp_get_referer() : _minn_request_uri());
    $field = '<input type="hidden" name="_wp_original_http_referer" value="' . esc_attr((string) $ref) . '" />';
    if ($display) {
        echo $field;
    }
    return $field;
}

/** @internal the request URI as the reference sees it */
function _minn_request_uri(): string
{
    $request = Runtime::current()->request;
    if ($request === null) {
        return '';
    }
    $query = http_build_query($request->query);
    return $request->path . ($query === '' ? '' : '?' . $query);
}

function wp_get_referer()
{
    $request = Runtime::current()->request;
    $ref = wp_get_raw_referer();
    if ($ref && $ref !== _minn_request_uri() && $ref !== home_url() . _minn_request_uri()) {
        return wp_validate_redirect($ref, false);
    }
    return false;
}

function wp_get_raw_referer()
{
    $request = Runtime::current()->request;
    if ($request === null) {
        return false;
    }
    if (!empty($request->form['_wp_http_referer'])) {
        return wp_unslash($request->form['_wp_http_referer']);
    }
    $header = $request->header('referer');
    return $header === null || $header === '' ? false : wp_unslash($header);
}

function wp_get_original_referer()
{
    $value = Runtime::current()->request?->form['_wp_original_http_referer'] ?? null;
    return $value ? wp_validate_redirect(wp_unslash($value), false) : false;
}

function wp_validate_redirect($location, $fallback_url = '')
{
    $location = wp_sanitize_redirect(trim((string) $location, " \t\n\r\0\x08\x0B"));
    $safe = Url::safeRedirect($location, static fn (string $host) => (array) apply_filters('allowed_redirect_hosts', [wp_parse_url(home_url())['host'] ?? ''], $host));
    return $safe ?? $fallback_url;
}

function wp_sanitize_redirect($location)
{
    $location = (string) $location;
    $location = preg_replace('|[^a-z0-9-~+_.?#=&;,/:%!*\[\]()@]|i', '', $location);
    return preg_replace('|%0[0-9a-f]|i', '', $location);
}

function wp_safe_redirect($location, $status = 302, $x_redirect_by = 'WordPress')
{
    return wp_redirect(wp_validate_redirect(apply_filters('wp_safe_redirect_fallback', admin_url(), $status) ? $location : $location, apply_filters('wp_safe_redirect_fallback', admin_url(), $status)), $status, $x_redirect_by);
}

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

function wp_hash($data, $scheme = 'auth', $algo = 'md5')
{
    return hash_hmac($algo, (string) $data, wp_salt($scheme));
}

function wp_salt($scheme = 'auth')
{
    return Salts::for($scheme);
}

function wp_hash_password($password)
{
    return Password::hash((string) $password);
}

function wp_check_password($password, $hash, $user_id = '')
{
    $check = Password::verify((string) $password, (string) $hash);
    return apply_filters('check_password', $check, $password, $hash, $user_id);
}

function wp_password_needs_rehash($hash, $user_id = '')
{
    return !str_starts_with((string) $hash, '$wp$');
}

function wp_set_password($password, $user_id)
{
    (new Users(Runtime::current()->db))->setPassword((int) $user_id, wp_hash_password($password));
    do_action('wp_set_password', $password, $user_id);
}

function wp_generate_uuid4()
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

function wp_is_uuid($uuid, $version = null)
{
    if (!is_string($uuid)) {
        return false;
    }
    return $version === 4
        ? preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid) === 1
        : preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid) === 1;
}

function wp_die($message = '', $title = '', $args = [])
{
    if (is_int($args)) {
        $args = ['response' => $args];
    }
    if (is_int($title)) {
        $args = ['response' => $title];
        $title = '';
    }
    if (wp_doing_ajax()) {
        $callback = apply_filters('wp_die_ajax_handler', '_ajax_wp_die_handler');
    } elseif (wp_is_json_request()) {
        $callback = apply_filters('wp_die_json_handler', '_json_wp_die_handler');
    } elseif (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST) {
        $callback = apply_filters('wp_die_xmlrpc_handler', '_xmlrpc_wp_die_handler');
    } else {
        $callback = apply_filters('wp_die_handler', '_default_wp_die_handler');
    }
    $callback($message, $title, $args);
}

function _wp_die_process_input($message, $title = '', $args = [])
{
    $defaults = ['response' => 0, 'code' => '', 'exit' => true, 'back_link' => false, 'link_url' => '', 'link_text' => '', 'text_direction' => 'ltr', 'charset' => 'utf-8', 'additional_errors' => []];
    if (is_wp_error($message)) {
        $errors = [];
        foreach ($message->get_error_codes() as $code) {
            $data = $message->get_error_data($code);
            $errors[] = ['code' => $code, 'message' => $message->get_error_message($code), 'data' => $data];
        }
        $args = wp_parse_args($args, $defaults + ($errors[0]['data'] ?? []) );
        $args['code'] = $errors[0]['code'] ?? '';
        $message = $errors[0]['message'] ?? '';
        if ($title === '' && isset($errors[0]['data']['title'])) {
            $title = $errors[0]['data']['title'];
        }
        $args['additional_errors'] = array_slice($errors, 1);
    } else {
        $args = wp_parse_args($args, $defaults);
    }
    if ($args['response'] === 0) {
        $args['response'] = 500;
    }
    if ($title === '') {
        $title = 'WordPress &rsaquo; Error';
    }
    return [$message, $title, $args];
}

function _default_wp_die_handler($message, $title = '', $args = [])
{
    [$message, $title, $args] = _wp_die_process_input($message, $title, $args);
    if (is_string($message) && !str_contains($message, '<p>') && $message !== '') {
        $message = '<p>' . $message . '</p>';
    }
    if ($args['back_link']) {
        $message .= '<p><a href="javascript:history.back()">&laquo; Back</a></p>';
    }
    if (!headers_sent()) {
        http_response_code((int) $args['response']);
        header('Content-Type: text/html; charset=' . $args['charset']);
    }
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="' . esc_attr($args['charset']) . '"><meta name="viewport" content="width=device-width"><title>' . esc_html(wp_specialchars_decode($title)) . '</title><style>html{background:#f1f1f1}body{background:#fff;border:1px solid #ccd0d4;color:#3c434a;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;margin:2em auto;padding:1em 2em;max-width:700px;box-shadow:0 1px 1px rgba(0,0,0,.04)}h1{border-bottom:1px solid #dadada;font-size:24px;margin:30px 0 0;padding:0 0 7px}p{font-size:14px;line-height:1.5;margin:25px 0 20px}</style></head><body id="error-page">' . "\n" . $message . "\n" . '</body></html>' . "\n";
    if ($args['exit']) {
        exit;
    }
}

function _ajax_wp_die_handler($message, $title = '', $args = [])
{
    [$message, $title, $args] = _wp_die_process_input($message, $title, $args);
    if (!headers_sent() && $args['response'] !== 0) {
        http_response_code((int) $args['response']);
    }
    if (is_scalar($message)) {
        echo (string) $message;
    }
    if ($args['exit']) {
        exit;
    }
}

function _json_wp_die_handler($message, $title = '', $args = [])
{
    [$message, $title, $args] = _wp_die_process_input($message, $title, $args);
    if (!headers_sent()) {
        http_response_code((int) $args['response']);
        header('Content-Type: application/json; charset=' . $args['charset']);
    }
    echo wp_json_encode(['code' => $args['code'], 'message' => $message, 'data' => ['status' => $args['response']], 'additional_errors' => $args['additional_errors']]);
    if ($args['exit']) {
        exit;
    }
}

function _xmlrpc_wp_die_handler($message, $title = '', $args = [])
{
    _ajax_wp_die_handler($message, $title, $args);
}

function _scalar_wp_die_handler($message = '', $title = '', $args = [])
{
    if (is_scalar($message)) {
        echo (string) $message;
    }
    exit;
}

function is_wp_error($thing)
{
    $is = $thing instanceof WP_Error;
    if ($is) {
        do_action('is_wp_error_instance', $thing);
    }
    return $is;
}

function wp_mail($to, $subject, $message, $headers = '', $attachments = [], $embeds = [])
{
    $atts = apply_filters('wp_mail', compact('to', 'subject', 'message', 'headers', 'attachments'));
    $pre = apply_filters('pre_wp_mail', null, $atts);
    if ($pre !== null) {
        return $pre;
    }
    $mailer = Minn\Mail\Mailer::forSite(Runtime::current()->site);
    $to = is_array($atts['to']) ? array_values($atts['to']) : array_map('trim', explode(',', (string) $atts['to']));
    $ok = $mailer->send(new Minn\Mail\Message($to, (string) $atts['subject'], (string) $atts['message']));
    if ($ok) {
        do_action('wp_mail_succeeded', $atts);
    }
    return $ok;
}


function wp_notify_moderator($comment_id)
{
    return true;
}

function wp_new_user_notification($user_id, $deprecated = null, $notify = '')
{
}

function wp_text_diff($left_string, $right_string, $args = null)
{
    return '';
}

function cache_users($user_ids)
{
}

function auth_redirect()
{
    if (!is_user_logged_in()) {
        wp_redirect(wp_login_url(_minn_request_uri()));
        exit;
    }
}

function wp_authenticate($username, $password)
{
    $username = sanitize_user((string) $username);
    $user = apply_filters('authenticate', null, $username, (string) $password);
    if ($user === null) {
        $user = new WP_Error('authentication_failed', 'Invalid username, email address or password.');
    }
    return $user;
}

function sanitize_user($username, $strict = false)
{
    $raw = (string) $username;
    $username = wp_strip_all_tags($raw);
    $username = remove_accents($username);
    $username = preg_replace('|%([a-fA-F0-9][a-fA-F0-9])|', '', $username);
    $username = preg_replace('/&.+?;/', '', $username);
    if ($strict) {
        $username = preg_replace('|[^a-z0-9 _.\-@]|i', '', $username);
    }
    $username = trim(preg_replace('|\s+|', ' ', $username));
    return apply_filters('sanitize_user', $username, $raw, $strict);
}

function wp_validate_auth_cookie($cookie = '', $scheme = '')
{
    return Runtime::current()->reader->userId ?: false;
}

function wp_logout()
{
    do_action('wp_logout', get_current_user_id());
}

function wp_clear_auth_cookie()
{
    do_action('clear_auth_cookie');
}

function is_user_member_of_blog($user_id = 0, $blog_id = 0)
{
    $user_id = $user_id ?: get_current_user_id();
    return $user_id > 0 && get_userdata($user_id) !== false;
}

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
        $token = $sessions->create((int) $user_id, $expiration, $runtime->request?->remoteAddress ?? '', (string) ($runtime->request?->header('user-agent') ?? ''));
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

function wp_login_form($args = [])
{
    $defaults = [
        'echo' => true,
        'redirect' => (is_ssl() ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? ''),
        'form_id' => 'loginform',
        'label_username' => 'Username or Email Address',
        'label_password' => 'Password',
        'label_remember' => 'Remember Me',
        'label_log_in' => 'Log In',
        'id_username' => 'user_login',
        'id_password' => 'user_pass',
        'id_remember' => 'rememberme',
        'id_submit' => 'wp-submit',
        'remember' => true,
        'value_username' => '',
        'value_remember' => false,
    ];
    $args = wp_parse_args($args, apply_filters('login_form_defaults', $defaults));
    $args['action'] = wp_login_url();
    $form = Minn\Login\LoginForm::embedded(
        $args,
        (string) apply_filters('login_form_top', '', $args),
        (string) apply_filters('login_form_middle', '', $args),
        (string) apply_filters('login_form_bottom', '', $args),
    );
    if ($args['echo']) {
        echo $form;
        return;
    }
    return $form;
}

function wp_loginout($redirect = '', $display = true)
{
    $link = is_user_logged_in()
        ? '<a href="' . esc_url(wp_logout_url($redirect)) . '">Log out</a>'
        : '<a href="' . esc_url(wp_login_url($redirect)) . '">Log in</a>';
    $link = apply_filters('loginout', $link);
    if (!$display) {
        return $link;
    }
    echo $link;
}

function wp_register($before = '<li>', $after = '</li>', $display = true)
{
    if (!is_user_logged_in()) {
        $link = get_option('users_can_register')
            ? $before . '<a href="' . esc_url(wp_registration_url()) . '">Register</a>' . $after
            : '';
    } else {
        $link = $before . '<a href="' . esc_url(admin_url()) . '">Site Admin</a>' . $after;
    }
    $link = apply_filters('register', $link);
    if (!$display) {
        return $link;
    }
    echo $link;
}

function wp_meta()
{
    do_action('wp_meta');
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
