<?php
/** Nonces, hashes, passwords, referers, wp_die, and mail. */

use Minn\Auth\Nonce;
use Minn\Auth\Password;
use Minn\Auth\Salts;
use Minn\Runtime\Runtime;

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
    if (str_starts_with($location, '//')) {
        $location = 'http:' . $location;
    }
    $test = str_starts_with($location, '/') ? 'http://placeholder.invalid' . $location : $location;
    $parts = wp_parse_url($test);
    if ($parts === false || !isset($parts['host'])) {
        return $fallback_url;
    }
    if (isset($parts['scheme']) && !in_array($parts['scheme'], ['http', 'https'], true)) {
        return $fallback_url;
    }
    if (isset($parts['user']) || isset($parts['pass'])) {
        return $fallback_url;
    }
    $home = wp_parse_url(home_url());
    $allowed = apply_filters('allowed_redirect_hosts', [$home['host'] ?? ''], $parts['host']);
    if ($parts['host'] !== 'placeholder.invalid' && !in_array($parts['host'], (array) $allowed, true)) {
        return $fallback_url;
    }
    return $location;
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
    $db = Runtime::current()->db;
    $db->execute("UPDATE {$db->table('users')} SET user_pass = ?, user_activation_key = '' WHERE ID = ?", [wp_hash_password($password), (int) $user_id]);
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

function wp_notify_postauthor($comment_id, $deprecated = null)
{
    return false;
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
