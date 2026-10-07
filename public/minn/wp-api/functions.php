<?php
/** Dates, files, mime types, and the odds and ends of wp-includes/functions.php. */

use Minn\Runtime\Deferrals;
use Minn\Runtime\Runtime;

function wp_timezone_string()
{
    $timezone = (string) get_option('timezone_string');
    if ($timezone !== '') {
        return $timezone;
    }
    $offset = (float) get_option('gmt_offset');
    $hours = (int) $offset;
    $minutes = ($offset - $hours);
    $sign = $offset < 0 ? '-' : '+';
    $abs = abs($hours);
    $abs_minutes = abs($minutes * 60);
    return sprintf('%s%02d:%02d', $sign, $abs, $abs_minutes);
}

function wp_timezone()
{
    return new DateTimeZone(wp_timezone_string());
}

/** The MySQL week of a column, counted from the site's first day of the week (probe query-clauses). */
function _wp_mysql_week($column)
{
    return Minn\Query\DateSql::week((string) $column, (int) get_option('start_of_week'));
}

function current_time($type, $gmt = false)
{
    if ($type === 'timestamp' || $type === 'U') {
        return $gmt ? time() : time() + (int) ((float) get_option('gmt_offset') * HOUR_IN_SECONDS);
    }
    if ($type === 'mysql') {
        $type = 'Y-m-d H:i:s';
    }
    $timezone = $gmt ? new DateTimeZone('UTC') : wp_timezone();
    $datetime = new DateTime('now', $timezone);
    return $datetime->format($type);
}

function current_datetime()
{
    return new DateTimeImmutable('now', wp_timezone());
}

function wp_date($format, $timestamp = null, $timezone = null)
{
    $timestamp ??= time();
    $timezone ??= wp_timezone();
    $datetime = date_create('@' . $timestamp);
    $datetime->setTimezone($timezone);
    $date = $datetime->format($format);
    return apply_filters('wp_date', $date, $format, $timestamp, $timezone);
}

function date_i18n($format, $timestamp_with_offset = false, $gmt = false)
{
    $timestamp = is_numeric($timestamp_with_offset) ? (int) $timestamp_with_offset : current_time('timestamp', $gmt);
    // The timestamp arrives pre-offset ("WordPress local"): its digits print
    // as-is, attached to the site timezone only for timezone-name formats.
    $timezone = $gmt ? new DateTimeZone('UTC') : wp_timezone();
    $datetime = date_create(gmdate('Y-m-d H:i:s', $timestamp), $timezone);
    $date = $datetime->format($format);
    return apply_filters('date_i18n', $date, $format, $timestamp_with_offset, $gmt);
}

function get_gmt_from_date($date_string, $format = 'Y-m-d H:i:s')
{
    $datetime = date_create((string) $date_string, wp_timezone());
    if ($datetime === false) {
        return gmdate($format, 0);
    }
    return $datetime->setTimezone(new DateTimeZone('UTC'))->format($format);
}

function get_date_from_gmt($date_string, $format = 'Y-m-d H:i:s')
{
    $datetime = date_create((string) $date_string, new DateTimeZone('UTC'));
    if ($datetime === false) {
        return gmdate($format, 0);
    }
    return $datetime->setTimezone(wp_timezone())->format($format);
}

function mysql2date($format, $date, $translate = true)
{
    if (empty($date)) {
        return false;
    }
    $datetime = date_create((string) $date, wp_timezone());
    if ($datetime === false) {
        return false;
    }
    if ($format === 'G') {
        return $datetime->getTimestamp() + $datetime->getOffset();
    }
    if ($format === 'U') {
        return $datetime->getTimestamp();
    }
    return $translate ? wp_date($format, $datetime->getTimestamp()) : $datetime->format($format);
}

function mysql_to_rfc3339($date_string)
{
    return mysql2date('Y-m-d\TH:i:s', $date_string, false);
}

function wp_checkdate($month, $day, $year, $source_date)
{
    return apply_filters('wp_checkdate', checkdate((int) $month, (int) $day, (int) $year), $source_date);
}

function get_weekstartend($mysqlstring, $start_of_week = '')
{
    $time = strtotime((string) $mysqlstring);
    $weekday = (int) date('w', $time);
    $start_of_week = $start_of_week === '' ? (int) get_option('start_of_week') : (int) $start_of_week;
    $offset = ($weekday - $start_of_week + 7) % 7;
    $start = strtotime(date('Y-m-d', $time)) - $offset * DAY_IN_SECONDS;
    return ['start' => $start, 'end' => $start + WEEK_IN_SECONDS - 1];
}

/** @internal */
function _minn_mime_table(): array
{
    static $table = null;
    return $table ??= json_decode((string) file_get_contents(Runtime::current()->engineDir . '/data/mime.json'), true);
}

function wp_get_mime_types()
{
    return apply_filters('mime_types', _minn_mime_table()['mime']);
}

function wp_get_ext_types()
{
    return apply_filters('ext2type', _minn_mime_table()['ext2type']);
}

function wp_ext2type($ext)
{
    $ext = strtolower((string) $ext);
    foreach (wp_get_ext_types() as $type => $exts) {
        if (in_array($ext, $exts, true)) {
            return $type;
        }
    }
    return null;
}

/** The types a user may upload, through upload_mimes: never Flash or executables, and web pages and scripts only for those who may post unfiltered HTML. */
function get_allowed_mime_types($user = null)
{
    $types = _minn_mime_table()['allowed'];
    $unfiltered = $user === null ? current_user_can('unfiltered_html') : user_can($user, 'unfiltered_html');
    if ($unfiltered) {
        $all = _minn_mime_table()['mime'];
        $types += array_intersect_key($all, ['htm|html' => 0, 'js' => 0]);
    }
    return apply_filters('upload_mimes', $types, $user);
}

function wp_check_filetype($filename, $mimes = null)
{
    $mimes ??= get_allowed_mime_types();
    $type = false;
    $ext = false;
    foreach ($mimes as $ext_preg => $mime_match) {
        $ext_preg = '!\.(' . $ext_preg . ')$!i';
        if (preg_match($ext_preg, (string) $filename, $m)) {
            $type = $mime_match;
            $ext = $m[1];
            break;
        }
    }
    return compact('ext', 'type');
}

/** A file's extension and type from its content as much as its name (Minn\Runtime\FileTypeCheck), with a corrected name when an image carried the wrong extension. */
function wp_check_filetype_and_ext($file, $filename, $mimes = null)
{
    $images = (array) apply_filters('getimagesize_mimes_to_exts', \Minn\Runtime\FileTypeCheck::IMAGES);
    $checked = \Minn\Runtime\FileTypeCheck::check((string) $file, (string) $filename, is_array($mimes) ? $mimes : null, $images);
    $result = ['ext' => $checked['ext'], 'type' => $checked['type'], 'proper_filename' => $checked['proper_filename']];
    return apply_filters('wp_check_filetype_and_ext', $result, $file, $filename, $mimes, $checked['real_mime']);
}

function wp_get_image_mime($file)
{
    $info = @getimagesize((string) $file);
    return $info['mime'] ?? false;
}

function get_attached_file($attachment_id, $unfiltered = false)
{
    $file = (string) get_post_meta((int) $attachment_id, '_wp_attached_file', true);
    if ($file !== '' && !str_starts_with($file, '/') && !preg_match('|^.:\\\\|', $file)) {
        $file = wp_get_upload_dir()['basedir'] . '/' . $file;
    }
    return $unfiltered ? $file : apply_filters('get_attached_file', $file, (int) $attachment_id);
}

function wp_upload_bits($name, $deprecated, $bits, $time = null)
{
    $name = (string) $name;
    if ($name === '') {
        return ['error' => 'Empty filename'];
    }
    $wp_filetype = wp_check_filetype($name);
    if (!$wp_filetype['ext'] && !current_user_can('unfiltered_upload')) {
        return ['error' => 'Sorry, you are not allowed to upload this file type.'];
    }
    $upload = wp_upload_dir($time);
    if ($upload['error'] !== false) {
        return ['error' => $upload['error']];
    }
    $filename = wp_unique_filename($upload['path'], $name);
    $new_file = $upload['path'] . '/' . $filename;
    wp_mkdir_p(dirname($new_file));
    if (file_put_contents($new_file, $bits) === false) {
        return ['error' => sprintf('Could not write file %s', $new_file)];
    }
    @chmod($new_file, 0644);
    $url = $upload['url'] . '/' . $filename;
    return apply_filters('wp_handle_upload', ['file' => $new_file, 'url' => $url, 'type' => $wp_filetype['type'], 'error' => false], 'sideload');
}

function wp_mkdir_p($target)
{
    $target = rtrim(str_replace('//', '/', (string) $target), '/');
    if ($target === '') {
        $target = '/';
    }
    if (is_dir($target)) {
        return @is_writable($target);
    }
    if (@mkdir($target, 0777, true)) {
        return true;
    }
    return is_dir($target);
}

function wp_is_writable($path)
{
    return @is_writable((string) $path);
}

function path_is_absolute($path)
{
    $path = (string) $path;
    return $path !== '' && ($path[0] === '/' || preg_match('#^[a-zA-Z]:[/\\\\]#', $path) === 1 || wp_is_stream($path));
}

function path_join($base, $path)
{
    return path_is_absolute($path) ? $path : rtrim((string) $base, '/') . '/' . ltrim((string) $path, '/');
}

function wp_is_stream($path)
{
    $pos = strpos((string) $path, '://');
    return $pos !== false && in_array(substr((string) $path, 0, $pos), stream_get_wrappers(), true);
}

function wp_tempnam($filename = '', $dir = '')
{
    $dir = $dir === '' ? get_temp_dir() : (string) $dir;
    $name = basename((string) $filename) ?: 'file';
    return tempnam($dir, pathinfo($name, PATHINFO_FILENAME));
}

function get_temp_dir()
{
    return trailingslashit(sys_get_temp_dir());
}

/**
 * A clean name no file in the folder has: the name sanitized, its extension
 * lowercased, a callback's name when one is given, else -1, -2... before the
 * extension; wp_unique_filename has the last word.
 */
function wp_unique_filename($dir, $filename, $unique_filename_callback = null)
{
    $filename = sanitize_file_name($filename);
    $dot = strrpos($filename, '.');
    $ext = $dot === false ? '' : substr($filename, $dot);
    $name = $dot === false ? $filename : substr($filename, 0, $dot);
    // The reference looks at the type and the uploads folder here, for an image's sub-size names.
    wp_check_filetype($filename);
    wp_upload_dir();
    $dir = rtrim((string) $dir, '/');
    $number = '';
    if ($unique_filename_callback !== null && is_callable($unique_filename_callback)) {
        $candidate = (string) call_user_func($unique_filename_callback, $dir, $name, $ext);
    } else {
        $candidate = $name . strtolower($ext);
        while (file_exists($dir . '/' . $candidate)) {
            $number = $number === '' ? 1 : $number + 1;
            $candidate = $name . '-' . $number . strtolower($ext);
        }
    }
    return apply_filters('wp_unique_filename', $candidate, $ext, $dir, $unique_filename_callback, [], $number);
}




function wp_max_upload_size()
{
    return apply_filters('upload_size_limit', min(wp_convert_hr_to_bytes(ini_get('upload_max_filesize')), wp_convert_hr_to_bytes(ini_get('post_max_size'))), 0, 0);
}

function wp_ob_end_flush_all()
{
    while (ob_get_level() > 0) {
        ob_end_flush();
    }
}



function wp_scheduled_delete()
{
}


/** A URL's body through wp_safe_remote_get (ten seconds), whatever its status; false when it could not be fetched. */
function wp_remote_fopen($uri)
{
    if (!is_array(parse_url((string) $uri))) {
        return false;
    }
    $response = wp_safe_remote_get($uri, ['timeout' => 10]);
    return is_wp_error($response) ? false : wp_remote_retrieve_body($response);
}

function is_blog_installed()
{
    return true;
}

function _deprecated_function($function_name, $version, $replacement = '')
{
    do_action('deprecated_function_run', $function_name, $replacement, $version);
}

function _deprecated_argument($function_name, $version, $message = '')
{
    do_action('deprecated_argument_run', $function_name, $message, $version);
}

function _deprecated_hook($hook, $version, $replacement = '', $message = '')
{
    do_action('deprecated_hook_run', $hook, $replacement, $version, $message);
}

function _deprecated_file($file, $version, $replacement = '', $message = '')
{
    do_action('deprecated_file_included', $file, $replacement, $version, $message);
}

function _deprecated_class($class_name, $version, $replacement = '')
{
    do_action('deprecated_class_run', $class_name, $replacement, $version);
}

function _deprecated_constructor($class_name, $version, $parent_class = '')
{
    do_action('deprecated_constructor_run', $class_name, $version, $parent_class);
}

function _doing_it_wrong($function_name, $message, $version)
{
    do_action('doing_it_wrong_run', $function_name, $message, $version);
}

function wp_trigger_error($function_name, $message, $error_level = E_USER_NOTICE)
{
    do_action('wp_trigger_error_run', $function_name, $message, $error_level);
}

function apache_mod_loaded($mod, $default_value = false)
{
    // Off Apache no module is loaded, whatever default the caller offers.
    $software = Minn\Runtime\Runtime::current()->context->request?->server['software'] ?? '';
    if (!Minn\Support\WebServer::isApache((string) $software)) {
        return false;
    }
    return function_exists('apache_get_modules') ? in_array($mod, apache_get_modules(), true) : $default_value;
}

function iis7_supports_permalinks()
{
    return false;
}

function validate_file($file, $allowed_files = [])
{
    $file = (string) $file;
    if ($file === '' || str_contains($file, '..') || str_contains($file, './')) {
        return $file === '' ? 0 : 1;
    }
    if ($allowed_files !== [] && !in_array($file, $allowed_files, true)) {
        return 3;
    }
    if (preg_match('|^.:[/\\\\]|', $file)) {
        return 2;
    }
    return 0;
}

function wp_guess_url()
{
    return home_url();
}

/** Whether wp_cache_add is refused for now (Runtime\Deferrals). */
function wp_suspend_cache_addition($suspend = null)
{
    return Deferrals::cacheAddition($suspend);
}

function wp_suspend_cache_invalidation($suspend = true)
{
    return false;
}

function get_status_header_desc($code)
{
    $codes = [200 => 'OK', 201 => 'Created', 204 => 'No Content', 301 => 'Moved Permanently', 302 => 'Found', 303 => 'See Other', 304 => 'Not Modified', 307 => 'Temporary Redirect', 308 => 'Permanent Redirect', 400 => 'Bad Request', 401 => 'Unauthorized', 403 => 'Forbidden', 404 => 'Not Found', 405 => 'Method Not Allowed', 409 => 'Conflict', 410 => 'Gone', 429 => 'Too Many Requests', 500 => 'Internal Server Error', 501 => 'Not Implemented', 502 => 'Bad Gateway', 503 => 'Service Unavailable'];
    return $codes[(int) $code] ?? '';
}

function status_header($code, $description = '')
{
    $description = $description ?: get_status_header_desc($code);
    if ($description === '') {
        return;
    }
    $protocol = wp_get_server_protocol();
    $line = (string) apply_filters('status_header', "{$protocol} {$code} {$description}", $code, $description, $protocol);
    if (!headers_sent() && PHP_SAPI !== 'cli') {
        header($line, true, (int) $code);
    }
}

function nocache_headers()
{
    if (!headers_sent()) {
        foreach (wp_get_nocache_headers() as $name => $value) {
            header("{$name}: {$value}");
        }
    }
}

function wp_get_nocache_headers()
{
    return apply_filters('nocache_headers', ['Expires' => 'Wed, 11 Jan 1984 05:00:00 GMT', 'Cache-Control' => 'no-cache, must-revalidate, max-age=0, no-store, private']);
}

function cache_javascript_headers()
{
}

function wp_send_json($response, $status_code = null, $flags = 0)
{
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=' . get_option('blog_charset'));
        if ($status_code !== null) {
            http_response_code((int) $status_code);
        }
    }
    echo wp_json_encode($response, $flags);
    if (wp_doing_ajax()) {
        wp_die('', '', ['response' => null]);
    }
    exit;
}

function wp_send_json_success($value = null, $status_code = null, $flags = 0)
{
    $response = ['success' => true];
    if ($value !== null) {
        $response['data'] = $value;
    }
    wp_send_json($response, $status_code, $flags);
}

function wp_send_json_error($value = null, $status_code = null, $flags = 0)
{
    $response = ['success' => false];
    if ($value !== null) {
        if (is_wp_error($value)) {
            $result = [];
            foreach ($value->errors as $code => $messages) {
                foreach ($messages as $message) {
                    $result[] = ['code' => $code, 'message' => $message];
                }
            }
            $response['data'] = $result;
        } else {
            $response['data'] = $value;
        }
    }
    wp_send_json($response, $status_code, $flags);
}

function wp_is_serving_rest_request()
{
    return defined('REST_REQUEST') && REST_REQUEST;
}

function wp_is_rest_endpoint()
{
    return wp_is_serving_rest_request();
}

function wp_is_mobile()
{
    $ua = (string) (Runtime::current()->request?->header('user-agent') ?? '');
    return apply_filters('wp_is_mobile', $ua !== '' && preg_match('/Mobile|Android|Silk\/|Kindle|BlackBerry|Opera Mini|Opera Mobi/', $ua) === 1);
}

function wp_is_site_protected_by_basic_auth($context = '')
{
    return false;
}

function wp_extract_urls($content)
{
    preg_match_all('#\bhttps?://[^\s<>"\']+#i', (string) $content, $m);
    return array_values(array_unique($m[0]));
}

function do_enclose($content, $post)
{
    return null;
}

function wp_get_http_headers($url, $deprecated = false)
{
    return false;
}

function is_new_day()
{
    return false;
}

function build_query($data)
{
    return _minn_build_query((array) $data);
}

function _http_build_query($data, $prefix = null, $sep = null, $key = '', $urlencode = true)
{
    return _minn_build_query((array) $data, (string) $key);
}

function wp_widget_description($id)
{
    return '';
}

function wp_auth_check_load()
{
}

function wp_auth_check_html()
{
    $login_url = wp_login_url();
    echo "\t\t<div id=\"wp-auth-check-wrap\" class=\"hidden fallback\">\n\t<div id=\"wp-auth-check-bg\"></div>\n\t<div id=\"wp-auth-check\">\n\t<button type=\"button\" class=\"wp-auth-check-close button-link\"><span class=\"screen-reader-text\">\n\t\t" . esc_html__('Close dialog') . "\t</span></button>\n\t\t<div class=\"wp-auth-fallback\">\n\t\t<p><b class=\"wp-auth-fallback-expired\" tabindex=\"0\">" . esc_html__('Session expired') . "</b></p>\n\t\t<p><a href=\"" . esc_url($login_url) . "\" target=\"_blank\">" . esc_html__('Please log in again.') . "</a>\n\t\t" . esc_html__('The login page will open in a new tab. After logging in you can close it and return to this page.') . "</p>\n\t</div>\n\t</div>\n\t</div>\n";
}

function wp_auth_check($response)
{
    $response['wp-auth-check'] = is_user_logged_in();
    return $response;
}

function get_tag_regex($tag)
{
    return $tag === '' ? '' : sprintf('<%1$s[^<]*(?:>[\s\S]*<\/%1$s>|\s*\/>)', tag_escape($tag));
}

function tag_escape($tag_name)
{
    return apply_filters('tag_escape', strtolower(preg_replace('/[^a-zA-Z0-9_:]/', '', (string) $tag_name)), $tag_name);
}

function wp_fuzzy_number_match($expected, $actual, $precision = 1)
{
    return abs((float) $expected - (float) $actual) <= $precision;
}

function wp_unique_id($prefix = '')
{
    static $id = 0;
    return $prefix . (++$id);
}

function wp_unique_prefixed_id($prefix = '')
{
    static $ids = [];
    $ids[$prefix] = ($ids[$prefix] ?? 0) + 1;
    return $prefix . $ids[$prefix];
}

function wp_cache_get_last_changed($group)
{
    $last = wp_cache_get('last_changed', $group);
    if (!$last) {
        $last = microtime();
        wp_cache_set('last_changed', $last, $group);
    }
    return $last;
}

function _get_non_cached_ids($object_ids, $cache_group)
{
    $ids = [];
    foreach ((array) $object_ids as $id) {
        $id = (int) $id;
        if (!in_array($id, $ids, true) && wp_cache_get($id, (string) $cache_group) === false) {
            $ids[] = $id;
        }
    }
    return $ids;
}

function wp_cache_set_last_changed($group)
{
    $previous = wp_cache_get('last_changed', $group);
    $last = microtime();
    wp_cache_set('last_changed', $last, $group);
    do_action('wp_cache_set_last_changed', $group, $last, $previous);
    return $last;
}

function wp_get_wp_version()
{
    return $GLOBALS['wp_version'];
}

function wp_get_admin_notice($message, $args = [])
{
    $args = wp_parse_args($args, ['type' => '', 'dismissible' => false, 'id' => '', 'additional_classes' => [], 'attributes' => [], 'paragraph_wrap' => true]);
    $classes = ['notice'];
    if ($args['type'] !== '') {
        $classes[] = 'notice-' . $args['type'];
    }
    if ($args['dismissible']) {
        $classes[] = 'is-dismissible';
    }
    $classes = array_merge($classes, (array) $args['additional_classes']);
    $attributes = $args['id'] !== '' ? ' id="' . esc_attr($args['id']) . '"' : '';
    foreach ((array) $args['attributes'] as $name => $value) {
        $attributes .= ' ' . esc_attr($name) . '="' . esc_attr((string) $value) . '"';
    }
    $message = $args['paragraph_wrap'] ? "<p>{$message}</p>" : $message;
    return '<div class="' . esc_attr(implode(' ', $classes)) . '"' . $attributes . '>' . $message . '</div>';
}

function wp_admin_notice($message, $args = [])
{
    do_action('wp_admin_notice', $message, $args);
    echo wp_kses_post(wp_get_admin_notice($message, $args));
}

function get_main_site_id($network_id = null)
{
    return 1;
}

function mbstring_binary_safe_encoding($reset = false)
{
    static $encodings = [];
    static $overloaded = null;
    if ($overloaded === null) {
        $overloaded = function_exists('mb_internal_encoding') && (int) ini_get('mbstring.func_overload') & 2;
    }
    if (!$overloaded) {
        return;
    }
    if (!$reset) {
        $encodings[] = mb_internal_encoding();
        mb_internal_encoding('ISO-8859-1');
    } elseif ($encodings !== []) {
        mb_internal_encoding(array_pop($encodings));
    }
}

function reset_mbstring_encoding()
{
    mbstring_binary_safe_encoding(true);
}

/** Everything is cast to bool except the string "false" in any case, which is false. */
function wp_validate_boolean($value)
{
    if (is_bool($value)) {
        return $value;
    }
    if (is_string($value) && strtolower($value) === 'false') {
        return false;
    }
    return (bool) $value;
}

function send_frame_options_header()
{
    header('X-Frame-Options: SAMEORIGIN');
}

function send_nosniff_header()
{
    header('X-Content-Type-Options: nosniff');
}

/** The headers every admin response carries: the referrer policy, which a plugin may change. */
function wp_admin_headers()
{
    $policy = apply_filters('admin_referrer_policy', 'strict-origin-when-cross-origin');
    header(sprintf('Referrer-Policy: %s', $policy));
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
    // An ajax answer is a 200 unless the caller names a status; null (wp_send_json's) keeps the one already set.
    $status = is_array($args) && array_key_exists('response', $args) ? $args['response'] : 200;
    [$message, $title, $args] = _wp_die_process_input($message, $title, $args);
    if (!headers_sent() && $status !== null) {
        http_response_code((int) $status);
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

/** The notice arguments an admin screen strips from its address once shown, through removable_query_args. */
function wp_removable_query_args()
{
    $args = ['activate', 'activated', 'admin_email_remind_later', 'approved', 'core-major-auto-updates-saved', 'deactivate', 'delete_count', 'deleted', 'disabled', 'doing_wp_cron', 'enabled', 'error', 'hotkeys_highlight_first', 'hotkeys_highlight_last', 'ids', 'locked', 'message', 'same', 'saved', 'settings-updated', 'skipped', 'spammed', 'trashed', 'unspammed', 'untrashed', 'update', 'updated', 'wp-post-new-reload'];
    return apply_filters('removable_query_args', $args);
}

/**
 * The call stack as plugin debugging reads it (Support\Backtrace): outermost
 * first as a string, innermost first as a list, leaving out a class named in
 * $ignore_class and $skip_frames frames below this one; an included file's
 * path loses the content folder, then the install's path.
 */
function wp_debug_backtrace_summary($ignore_class = null, $skip_frames = 0, $pretty = true)
{
    $names = Minn\Support\Backtrace::summary(debug_backtrace(), $ignore_class === null ? null : (string) $ignore_class, (int) $skip_frames + 1, [wp_normalize_path(WP_CONTENT_DIR), wp_normalize_path(ABSPATH)]);
    return $pretty ? implode(', ', array_reverse($names)) : $names;
}

// PHP 8.4's array functions, which the reference provides on older PHP.
if (!function_exists('array_find')) {
    function array_find(array $array, callable $callback)
    {
        foreach ($array as $key => $value) {
            if ($callback($value, $key)) {
                return $value;
            }
        }
        return null;
    }
}

if (!function_exists('array_find_key')) {
    function array_find_key(array $array, callable $callback)
    {
        foreach ($array as $key => $value) {
            if ($callback($value, $key)) {
                return $key;
            }
        }
        return null;
    }
}

if (!function_exists('array_any')) {
    function array_any(array $array, callable $callback): bool
    {
        return array_find_key($array, $callback) !== null;
    }
}

if (!function_exists('array_all')) {
    function array_all(array $array, callable $callback): bool
    {
        foreach ($array as $key => $value) {
            if (!$callback($value, $key)) {
                return false;
            }
        }
        return true;
    }
}
