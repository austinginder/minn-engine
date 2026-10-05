<?php
/** Dates, files, mime types, and the odds and ends of wp-includes/functions.php. */

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

function get_allowed_mime_types($user = null)
{
    return apply_filters('upload_mimes', _minn_mime_table()['allowed'], $user);
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

function wp_check_filetype_and_ext($file, $filename, $mimes = null)
{
    $check = wp_check_filetype($filename, $mimes);
    return ['ext' => $check['ext'], 'type' => $check['type'], 'proper_filename' => false];
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

function wp_unique_filename($dir, $filename, $unique_filename_callback = null)
{
    $filename = sanitize_file_name($filename);
    $ext = pathinfo($filename, PATHINFO_EXTENSION);
    $name = pathinfo($filename, PATHINFO_FILENAME);
    $candidate = $filename;
    $n = 1;
    while (file_exists(rtrim((string) $dir, '/') . '/' . $candidate)) {
        $candidate = $name . '-' . (++$n) . ($ext !== '' ? '.' . $ext : '');
    }
    return $candidate;
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


function wp_remote_fopen($uri)
{
    return false;
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

function wp_suspend_cache_addition($suspend = null)
{
    return false;
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
    if (!headers_sent()) {
        http_response_code((int) $code);
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
