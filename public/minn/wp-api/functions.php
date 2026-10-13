<?php
/** Dates, files, mime types, and the odds and ends of wp-includes/functions.php. */

use Minn\Blocks\RenderState;
use Minn\Runtime\Deferrals;
use Minn\Runtime\Notices;
use Minn\Runtime\QueryArgs;
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
    // The names in the site's language, as the reference's wp_date writes them.
    $date = Minn\I18n\LocalDates::format($datetime, (string) $format);
    return apply_filters('wp_date', $date, $format, $timestamp, $timezone);
}

function date_i18n($format, $timestamp_with_offset = false, $gmt = false)
{
    $timestamp = is_numeric($timestamp_with_offset) ? (int) $timestamp_with_offset : current_time('timestamp', $gmt);
    // The timestamp arrives pre-offset ("WordPress local"): its digits print
    // as-is, attached to the site timezone only for timezone-name formats.
    $timezone = $gmt ? new DateTimeZone('UTC') : wp_timezone();
    $datetime = date_create(gmdate('Y-m-d H:i:s', $timestamp), $timezone);
    $date = Minn\I18n\LocalDates::format($datetime, (string) $format);
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

/** An image's real type from its header (Media\ImageMime), or false. */
function wp_get_image_mime($file)
{
    return Minn\Media\ImageMime::of((string) $file) ?? false;
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
    // A filter answering anything but the file's own details refuses the upload with it (probe filter-inputs-3).
    $refused = apply_filters('wp_upload_bits', ['name' => $name, 'bits' => $bits, 'time' => $time]);
    if (!is_array($refused)) {
        return ['error' => $refused];
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

/** A path starting with a slash or backslash, or a drive letter and a backslash (C:/ is not one); a URL is not (probe functions-remaining). */
function path_is_absolute($path)
{
    return preg_match('#^[/\\\\]|^[a-zA-Z]:\\\\#', (string) $path) === 1;
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
    $uploads = rtrim((string) (wp_upload_dir()['path'] ?? ''), '/');
    $dir = rtrim((string) $dir, '/');
    $number = '';
    // The uploads folder's files through pre_wp_unique_filename_file_list (probe filter-inputs-3); a list given is checked
    // instead of the folder. Another folder is only looked at (probe functions-remaining).
    $listed = $dir === $uploads ? apply_filters('pre_wp_unique_filename_file_list', null, $dir, $filename) : null;
    $taken = is_array($listed) ? static fn (string $candidate): bool => in_array($candidate, $listed, true) : static fn (string $candidate): bool => file_exists($dir . '/' . $candidate);
    if ($unique_filename_callback !== null && is_callable($unique_filename_callback)) {
        // The callback is handed the whole file name and the extension (probe functions-remaining).
        $candidate = (string) call_user_func($unique_filename_callback, $dir, $filename, $ext);
    } else {
        // A name that reads like a size of an image (-150x150, -scaled, -rotated) takes a number even when it is free (probe rest-attachments-sideload).
        $number = preg_match('/-(?:\d+x\d+|scaled|rotated)$/', $name) === 1 ? 1 : '';
        $candidate = $name . ($number === '' ? '' : '-' . $number) . strtolower($ext);
        while ($taken($candidate)) {
            $number = $number === '' ? 1 : $number + 1;
            $candidate = $name . '-' . $number . strtolower($ext);
        }
    }
    return apply_filters('wp_unique_filename', $candidate, $ext, $dir, $unique_filename_callback, [], $number);
}




function wp_max_upload_size()
{
    // The filter hears both limits as well as the smaller one (probe media-remaining).
    $upload = wp_convert_hr_to_bytes(ini_get('upload_max_filesize'));
    $post = wp_convert_hr_to_bytes(ini_get('post_max_size'));
    return apply_filters('upload_size_limit', min($upload, $post), $upload, $post);
}

/** Ends as many output buffers as were open, each flushed; one that refuses to end does not loop forever. */
function wp_ob_end_flush_all()
{
    for ($levels = ob_get_level(); $levels > 0; $levels--) {
        ob_end_flush();
    }
}


/** Deletes the posts and comments trashed more than EMPTY_TRASH_DAYS ago; a live one still holding a trash time loses it, and one whose deleting is refused keeps it for the next run. */
function wp_scheduled_delete()
{
    $before = time() - DAY_IN_SECONDS * EMPTY_TRASH_DAYS;
    foreach (_minn_meta()->objectsBelow('post', '_wp_trash_meta_time', $before) as $id) {
        $post = get_post($id);
        if ($post && $post->post_status === 'trash') {
            wp_delete_post($id);
            continue;
        }
        delete_post_meta($id, '_wp_trash_meta_status');
        delete_post_meta($id, '_wp_trash_meta_time');
    }
    foreach (_minn_meta()->objectsBelow('comment', '_wp_trash_meta_time', $before) as $id) {
        $comment = get_comment($id);
        if ($comment && $comment->comment_approved === 'trash') {
            wp_delete_comment($id);
            continue;
        }
        delete_comment_meta($id, '_wp_trash_meta_status');
        delete_comment_meta($id, '_wp_trash_meta_time');
    }
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

/** @internal Whether a debugging notice is raised (Runtime\\Notices::wanted). */
function _minn_notice_wanted($filter, ...$args)
{
    return Notices::wanted((string) $filter, ...$args);
}

function _deprecated_function($function_name, $version, $replacement = '')
{
    Notices::deprecated('function', $function_name, $version, $replacement);
}

function _deprecated_argument($function_name, $version, $message = '')
{
    Notices::deprecated('argument', $function_name, $version, $message);
}

function _deprecated_hook($hook, $version, $replacement = '', $message = '')
{
    Notices::deprecated('hook', $hook, $version, $replacement, $message);
}

function _deprecated_file($file, $version, $replacement = '', $message = '')
{
    Notices::deprecated('file', $file, $version, $replacement, $message);
}

function _deprecated_class($class_name, $version, $replacement = '')
{
    Notices::deprecated('class', $class_name, $version, $replacement);
}

function _deprecated_constructor($class_name, $version, $parent_class = '')
{
    Notices::constructor($class_name, $version, $parent_class);
}

function _doing_it_wrong($function_name, $message, $version)
{
    Notices::doingItWrong($function_name, $message, $version);
}

/** The notice as Runtime\\Notices words and cleans it; a fatal level is thrown as a WP_Exception rather than raised. */
function wp_trigger_error($function_name, $message, $error_level = E_USER_NOTICE)
{
    $raised = Notices::message($function_name, $message, $error_level);
    if ($raised === null) {
        return;
    }
    if ($error_level === E_USER_ERROR) {
        throw new WP_Exception($raised);
    }
    trigger_error($raised, $error_level);
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

/** Never IIS here, through iis7_supports_permalinks. */
function iis7_supports_permalinks()
{
    return apply_filters('iis7_supports_permalinks', false);
}

/**
 * 0 for a safe path, else why not (probe functions-remaining): 1 for a lone
 * ../, more than one, or one that is not the last segment (backslashes read
 * as slashes); 2 for a colon in second place (a drive); 3 for a file outside
 * the allowed list.
 */
function validate_file($file, $allowed_files = [])
{
    $file = str_replace('\\', '/', (string) $file);
    if ($file === '../' || substr_count($file, '../') > 1 || (str_contains($file, '../') && !str_ends_with($file, '../'))) {
        return 1;
    }
    if (($file[1] ?? '') === ':') {
        return 2;
    }
    if (!empty($allowed_files) && !in_array($file, $allowed_files, true)) {
        return 3;
    }
    return 0;
}

/**
 * The site's address guessed from the request, as the installer does
 * (probe functions-remaining): WP_SITEURL when set, else the scheme, the
 * host, and the folder the request points into.
 */
function wp_guess_url()
{
    if (defined('WP_SITEURL') && WP_SITEURL !== '') {
        return WP_SITEURL;
    }
    return rtrim((is_ssl() ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? '') . _minn_guessed_path(), '/');
}

/**
 * @internal The folder part of a guessed address: the request up to wp-admin
 * or wp-login.php; for a script at the install's root, the script's own
 * folder; for one in a subfolder, the request without that subfolder and
 * what follows; for one outside the install, the request as made.
 */
function _minn_guessed_path(): string
{
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
    $root = rtrim(str_replace('\\', '/', ABSPATH), '/');
    $folder = dirname(str_replace('\\', '/', (string) ($_SERVER['SCRIPT_FILENAME'] ?? '')));
    if (str_contains($uri, 'wp-admin') || str_contains($uri, 'wp-login.php')) {
        return (string) preg_replace('#/(wp-admin/?.*|wp-login\.php.*)#i', '', $uri);
    }
    if ($folder === $root) {
        return (string) preg_replace('#/[^/]*$#', '', (string) ($_SERVER['PHP_SELF'] ?? ''));
    }
    if (str_starts_with($folder, $root . '/')) {
        return (string) preg_replace('#/' . preg_quote(substr($folder, strlen($root) + 1), '#') . '/[^/]*$#i', '', $uri);
    }
    return $uri;
}

/** Whether wp_cache_add is refused for now (Runtime\Deferrals). */
function wp_suspend_cache_addition($suspend = null)
{
    return Deferrals::cacheAddition($suspend);
}

/** Holds back clean_post_cache while set; the previous setting is returned, null before the first call (probe functions-remaining). */
function wp_suspend_cache_invalidation($suspend = true)
{
    global $_wp_suspend_cache_invalidation;
    $previous = $_wp_suspend_cache_invalidation;
    $_wp_suspend_cache_invalidation = $suspend;
    return $previous;
}

/** A status code's reason phrase, from the reference's table; empty for a code it does not know (probe functions-remaining). */
function get_status_header_desc($code)
{
    return Minn\Http\StatusText::of(absint($code));
}

/** robots.txt: the admin kept out (its AJAX endpoint let in), under do_robotstxt and robots_txt, whether or not the site is public. */
function do_robots()
{
    header('Content-Type: text/plain; charset=utf-8');
    do_action('do_robotstxt');
    $public = (bool) get_option('blog_public');
    $path = (string) parse_url(site_url(), PHP_URL_PATH);
    $output = "User-agent: *\nDisallow: {$path}/wp-admin/\nAllow: {$path}/wp-admin/admin-ajax.php\n";
    echo apply_filters('robots_txt', $output, $public);
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

/** The no-cache headers sent; one set to false (Last-Modified) is taken off instead. */
function nocache_headers()
{
    if (headers_sent()) {
        return;
    }
    foreach (wp_get_nocache_headers() as $name => $value) {
        if ($value === false) {
            header_remove($name);
        } else {
            header("{$name}: {$value}");
        }
    }
}

/** The no-cache headers through nocache_headers, then Last-Modified false, which a plugin cannot keep (probe functions-remaining). */
function wp_get_nocache_headers()
{
    $headers = apply_filters('nocache_headers', ['Expires' => 'Wed, 11 Jan 1984 05:00:00 GMT', 'Cache-Control' => 'no-cache, must-revalidate, max-age=0, no-store, private']);
    $headers['Last-Modified'] = false;
    return $headers;
}

/** A script served by PHP: JavaScript in the site's charset, kept for ten days (runtime suite, over HTTP). */
function cache_javascript_headers()
{
    header('Content-Type: text/javascript; charset=' . get_bloginfo('charset'));
    header('Vary: Accept-Encoding');
    header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 10 * DAY_IN_SECONDS) . ' GMT');
}

function wp_send_json($response, $status_code = null, $flags = 0)
{
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=' . get_option('blog_charset'));
        if ($status_code !== null) {
            status_header($status_code);
        }
    }
    echo wp_json_encode($response, $flags);
    if (wp_doing_ajax()) {
        wp_die('', '', ['response' => null]);
    } else {
        exit;
    }
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

/** Whether this is a REST request, or a REST request is being dispatched inside this one; the filter may say otherwise (probe script-printing). */
function wp_is_rest_endpoint()
{
    $server = $GLOBALS['wp_rest_server'] ?? null;
    $dispatching = $server instanceof WP_REST_Server && $server->is_dispatching();
    return (bool) apply_filters('wp_is_rest_endpoint', wp_is_serving_rest_request() || $dispatching);
}

function wp_is_mobile()
{
    $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    return apply_filters('wp_is_mobile', $ua !== '' && preg_match('/Mobile|Android|Silk\/|Kindle|BlackBerry|Opera Mini|Opera Mobi/', $ua) === 1);
}

/** Whether the request came with basic auth credentials (probe load-remaining); the filter may say otherwise. */
function wp_is_site_protected_by_basic_auth($context = '')
{
    if ($context === '') {
        $context = wp_doing_ajax() || is_admin() ? 'admin' : (isset($GLOBALS['pagenow']) && $GLOBALS['pagenow'] === 'wp-login.php' ? 'login' : 'front');
    }
    $is_protected = !empty($_SERVER['PHP_AUTH_USER']) || !empty($_SERVER['PHP_AUTH_PW']);
    return (bool) apply_filters('wp_is_site_protected_by_basic_auth', $is_protected, $context);
}

/**
 * The URLs in a text, each once (probe functions-remaining): an optional
 * scheme and one or two slashes, a run with no space, parenthesis or angle
 * bracket up to its last dot, then word characters and URL punctuation (a
 * colon only before a digit). Each is entity-decoded and loses its
 * semicolons.
 */
function wp_extract_urls($content)
{
    preg_match_all('!(?:[\w-]+:)?//?[^\s()<>]+\.(?:[\w#$%&*+\-./=?@\\\\^|~;]|:(?=\d))*!u', (string) $content, $m);
    return array_values(array_unique(array_map(static fn (string $url): string => str_replace(';', '', html_entity_decode($url)), $m[0])));
}

/**
 * The post's enclosures brought up to date with the audio and video its
 * content links to (probe functions-remaining): an enclosure whose address
 * left the content is deleted; each new link, through enclosure_links and
 * without its fragment, is asked for its headers and kept when its
 * content-type is audio or video. Kept quirks: the length is read from
 * Content-Length as written, and the type stored is the one the link's
 * extension matched, carried over from the last link that had one (the
 * last type in the list when the extension matches none).
 */
function do_enclose($content, $post)
{
    $post = get_post($post);
    if (!$post) {
        return false;
    }
    $urls = wp_extract_urls($content ?? $post->post_content);
    $enclosed = _minn_kept_enclosures($post->ID, $urls);
    $links = [];
    foreach ($urls as $url) {
        if (!in_array($url, $enclosed, true)) {
            $links[] = $url;
        }
    }
    $mime = '';
    foreach ((array) apply_filters('enclosure_links', $links, $post->ID) as $link) {
        $url = (string) preg_replace('/#.*$/s', '', (string) $link);
        $headers = in_array($url, $enclosed, true) ? false : wp_get_http_headers($url);
        if (!$headers) {
            continue;
        }
        _minn_enclosure_type($url, $mime);
        $type = (string) ($headers['content-type'] ?? '');
        $slash = strpos($type, '/');
        if ($slash !== false && in_array(substr($type, 0, $slash), ['audio', 'video'], true)) {
            add_post_meta($post->ID, 'enclosure', $url . "\n" . (isset($headers['Content-Length']) ? (int) $headers['Content-Length'] : 0) . "\n" . $mime . "\n");
            $enclosed[] = $url;
        }
    }
}

/** @internal The enclosure addresses still in the content; the others' enclosure rows are deleted. */
function _minn_kept_enclosures(int $post_id, array $urls): array
{
    $kept = [];
    foreach ((array) get_post_meta($post_id, 'enclosure') as $value) {
        $url = explode("\n", (string) $value)[0];
        if (in_array($url, $urls, true)) {
            $kept[] = $url;
        } else {
            delete_post_meta($post_id, 'enclosure', $value);
        }
    }
    return $kept;
}

/** @internal Sets $mime to the type the address's extension matches, the last in the list when none does; untouched without an extension. */
function _minn_enclosure_type(string $url, string &$mime): void
{
    $extension = pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
    if ($extension === '') {
        return;
    }
    foreach (wp_get_mime_types() as $extensions => $type) {
        $mime = $type;
        if (preg_match('!^(' . $extensions . ')$!i', $extension) === 1) {
            return;
        }
    }
}

/** A URL's headers from a HEAD request, whatever the status; false when the request fails (probe functions-remaining). */
function wp_get_http_headers($url, $deprecated = false)
{
    $response = wp_safe_remote_head($url);
    return is_wp_error($response) ? false : wp_remote_retrieve_headers($response);
}

/** 1 while the loop's post is dated on a different day from the last date the_date printed, else 0. */
function is_new_day()
{
    return ($GLOBALS['currentday'] ?? null) !== ($GLOBALS['previousday'] ?? null) ? 1 : 0;
}

function build_query($data)
{
    return _minn_build_query((array) $data);
}

/**
 * A query string (probe functions-remaining): a number key takes $prefix,
 * nested keys go in brackets under $key, pairs join with $sep, and keys and
 * values are URL-encoded unless $urlencode is false; nulls are left out and
 * booleans are 1 and 0.
 */
function _http_build_query($data, $prefix = null, $sep = null, $key = '', $urlencode = true)
{
    $pairs = [];
    foreach ((array) $data as $name => $value) {
        $name = (string) (is_int($name) && $prefix !== null ? $prefix . $name : $name);
        $name = $key === '' ? ($urlencode ? urlencode($name) : $name) : $key . '%5B' . ($urlencode ? urlencode($name) : $name) . '%5D';
        if (is_array($value) || is_object($value)) {
            $pairs[] = _http_build_query($value, '', $sep, $name, $urlencode);
        } elseif ($value !== null) {
            $value = (string) (is_bool($value) ? (int) $value : $value);
            $pairs[] = $name . '=' . ($urlencode ? urlencode($value) : $value);
        }
    }
    return implode($sep ?? (string) ini_get('arg_separator.output'), array_filter($pairs, static fn (string $pair): bool => $pair !== ''));
}

/** A registered widget's description, escaped; nothing for a widget not registered. */
function wp_widget_description($id)
{
    if (!is_scalar($id) || !isset($GLOBALS['wp_registered_widgets'][$id]['description'])) {
        return null;
    }
    return esc_html($GLOBALS['wp_registered_widgets'][$id]['description']);
}


/**
 * The expired-session dialog: the sign-in form in a frame when it is on
 * this request's host and scheme (wp_auth_check_same_domain), always the
 * link to sign in again in a new tab (probe hook-inputs-2).
 */
function wp_auth_check_html()
{
    $login_url = wp_login_url();
    $same_domain = parse_url($login_url, PHP_URL_HOST) === ($_SERVER['HTTP_HOST'] ?? null) && parse_url($login_url, PHP_URL_SCHEME) === (is_ssl() ? 'https' : 'http');
    $same_domain = apply_filters('wp_auth_check_same_domain', $same_domain);
    $form = $same_domain ? "\t<div id=\"wp-auth-check-form\" class=\"loading\" data-src=\"" . esc_url(add_query_arg(['interim-login' => '1', 'wp_lang' => get_user_locale()], $login_url)) . "\"></div>\n\t\t\t" : '';
    echo "\t<div id=\"wp-auth-check-wrap\" class=\"" . ($same_domain ? 'hidden' : 'hidden fallback') . "\">\n\t<div id=\"wp-auth-check-bg\"></div>\n\t<div id=\"wp-auth-check\">\n\t<button type=\"button\" class=\"wp-auth-check-close button-link\"><span class=\"screen-reader-text\">\n\t\t" . esc_html__('Close dialog') . "\t</span></button>\n\t\t" . $form . "<div class=\"wp-auth-fallback\">\n\t\t<p><b class=\"wp-auth-fallback-expired\" tabindex=\"0\">" . esc_html__('Session expired') . "</b></p>\n\t\t<p><a href=\"" . esc_url($login_url) . "\" target=\"_blank\">" . esc_html__('Please log in again.') . "</a>\n\t\t" . esc_html__('The login page will open in a new tab. After logging in you can close it and return to this page.') . "</p>\n\t</div>\n\t</div>\n\t</div>\n\t";
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
    return apply_filters('tag_escape', strtolower(preg_replace('/[^a-zA-Z0-9-_:]/', '', (string) $tag_name)), $tag_name);
}

function wp_fuzzy_number_match($expected, $actual, $precision = 1)
{
    return abs((float) $expected - (float) $actual) <= $precision;
}

/** A prefix and the next number of the request's one counter, which the engine's renderer numbers galleries, style variations and inputs from too. */
function wp_unique_id($prefix = '')
{
    return $prefix . RenderState::current()->nextId();
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

/**
 * An admin notice's markup (probe functions-remaining): the arguments through
 * wp_admin_notice_args, the id before the class, a true attribute written
 * bare, the markup through wp_admin_notice_markup. A type with a space is
 * done wrong but still used.
 */
function wp_get_admin_notice($message, $args = [])
{
    $args = apply_filters('wp_admin_notice_args', wp_parse_args($args, ['type' => '', 'dismissible' => false, 'id' => '', 'additional_classes' => [], 'attributes' => [], 'paragraph_wrap' => true]), $message);
    if (is_string($args['type']) && str_contains($args['type'], ' ')) {
        _doing_it_wrong(__FUNCTION__, __('The <code>type</code> key must be a string without spaces.'), '6.4.0');
    }
    $classes = array_merge(['notice'], $args['type'] !== '' ? ['notice-' . $args['type']] : [], $args['dismissible'] ? ['is-dismissible'] : [], (array) $args['additional_classes']);
    $attributes = ($args['id'] !== '' ? ' id="' . esc_attr($args['id']) . '"' : '') . ' class="' . esc_attr(implode(' ', $classes)) . '"';
    foreach ((array) $args['attributes'] as $name => $value) {
        $attributes .= ' ' . esc_attr($name) . ($value === true ? '' : '="' . esc_attr((string) $value) . '"');
    }
    $paragraph = $args['paragraph_wrap'] ? "<p>{$message}</p>" : $message;
    return apply_filters('wp_admin_notice_markup', '<div' . $attributes . '>' . $paragraph . '</div>', $paragraph, $args);
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
    return Minn\Support\Flag::of($value);
}

/** The page refuses to be framed by another site, in both the old header and the policy one (runtime suite, over HTTP). */
function send_frame_options_header()
{
    header('X-Frame-Options: SAMEORIGIN');
    header("Content-Security-Policy: frame-ancestors 'self';");
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
    return QueryArgs::withNonce($actionurl, $action, $name);
}

/** The page an expired or wrong nonce stops on: a log-out link to confirm for log-out, else a link back to the referer when there is one (probe functions-remaining). */
function wp_nonce_ays($action)
{
    if ($action === 'log-out') {
        $title = sprintf(__('You are attempting to log out of %s'), get_bloginfo('name'));
        $redirect = isset($_REQUEST['redirect_to']) ? (string) $_REQUEST['redirect_to'] : '';
        $html = $title . '</p><p>' . sprintf(__('Do you really want to <a href="%s">log out</a>?'), wp_logout_url($redirect));
    } else {
        $html = __('The link you followed has expired.');
        if (wp_get_referer()) {
            $html .= '</p><p>' . sprintf('<a href="%s">%s</a>', esc_url(remove_query_arg('updated', wp_get_referer())), __('Please try again.'));
        }
        $title = __('An error occurred.');
    }
    wp_die($html, $title, ['response' => 403]);
}

function wp_referer_field($display = true)
{
    $field = '<input type="hidden" name="_wp_http_referer" value="' . esc_attr(wp_unslash(_minn_request_uri())) . '" />';
    if ($display) {
        echo $field;
    }
    return $field;
}

/** The original referer as a hidden field: the one the form carried, else the referer ('previous') or this request's address (probe functions-remaining). */
function wp_original_referer_field($display = true, $jump_back_to = 'current')
{
    $ref = wp_get_original_referer() ?: ($jump_back_to === 'previous' ? wp_get_referer() : wp_unslash($_SERVER['REQUEST_URI'] ?? ''));
    $field = '<input type="hidden" name="_wp_original_http_referer" value="' . esc_attr((string) $ref) . '" />';
    if ($display) {
        echo $field;
    }
    return $field;
}

/** @internal the request URI as the reference sees it */
function _minn_request_uri(): string
{
    return QueryArgs::requestUri();
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

/** The referer as given: the form's _wp_http_referer when it has one, else the Referer header, unslashed; false when neither (probe functions-remaining). */
function wp_get_raw_referer()
{
    if (!empty($_REQUEST['_wp_http_referer'])) {
        return wp_unslash($_REQUEST['_wp_http_referer']);
    }
    return empty($_SERVER['HTTP_REFERER']) ? false : wp_unslash($_SERVER['HTTP_REFERER']);
}

/** The form's _wp_original_http_referer when it is a safe address on this site, else false. */
function wp_get_original_referer()
{
    return empty($_REQUEST['_wp_original_http_referer']) ? false : wp_validate_redirect(wp_unslash($_REQUEST['_wp_original_http_referer']), false);
}

function wp_generate_uuid4()
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

/** A lowercase UUID; with a version, only 4 is known, and any other is done wrong and false (probe functions-remaining). */
function wp_is_uuid($uuid, $version = null)
{
    if (!is_string($uuid)) {
        return false;
    }
    if (is_numeric($version)) {
        if ((int) $version !== 4) {
            _doing_it_wrong(__FUNCTION__, __('Only UUID V4 is supported at this time.'), '4.9.0');
            return false;
        }
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid) === 1;
    }
    return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $uuid) === 1;
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
    } elseif (defined('REST_REQUEST') && REST_REQUEST && wp_is_jsonp_request()) {
        $callback = apply_filters('wp_die_jsonp_handler', '_jsonp_wp_die_handler');
    } elseif (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST) {
        $callback = apply_filters('wp_die_xmlrpc_handler', '_xmlrpc_wp_die_handler');
    } elseif (wp_is_xml_request() || (isset($GLOBALS['wp_query']) && (is_feed() || is_comment_feed() || is_trackback()))) {
        $callback = apply_filters('wp_die_xml_handler', '_xml_wp_die_handler');
    } else {
        $callback = apply_filters('wp_die_handler', '_default_wp_die_handler');
    }
    $callback($message, $title, $args);
}

/**
 * The timezone menu's order: Etc entries among themselves by their own
 * names (GMT+N falling, UTC between them and the rest, the rest
 * naturally), Etc after every other continent, and the others by their
 * translated continent, city, then subcity, naturally and caselessly.
 */
function _wp_timezone_choice_usort_callback($a, $b)
{
    if ($a['continent'] === 'Etc' && $b['continent'] === 'Etc') {
        return _minn_etc_zone_order((string) $a['city'], (string) $b['city']);
    }
    if ($a['t_continent'] !== $b['t_continent']) {
        if ($a['continent'] === 'Etc') {
            return 1;
        }
        return $b['continent'] === 'Etc' ? -1 : strnatcasecmp($a['t_continent'], $b['t_continent']);
    }
    return $a['t_city'] === $b['t_city'] ? strnatcasecmp($a['t_subcity'], $b['t_subcity']) : strnatcasecmp($a['t_city'], $b['t_city']);
}

/** @internal two Etc zones in the timezone menu's order */
function _minn_etc_zone_order(string $a, string $b): int
{
    if (str_starts_with($a, 'GMT+') && str_starts_with($b, 'GMT+')) {
        return -1 * strnatcasecmp($a, $b);
    }
    if ($a === 'UTC') {
        return str_starts_with($b, 'GMT+') ? 1 : -1;
    }
    if ($b === 'UTC') {
        return str_starts_with($a, 'GMT+') ? -1 : 1;
    }
    return strnatcasecmp($a, $b);
}

function _canonical_charset($charset)
{
    $name = strtolower((string) $charset);
    if ($name === 'utf-8' || $name === 'utf8') {
        return 'UTF-8';
    }
    if ($name === 'iso-8859-1' || $name === 'iso8859-1') {
        return 'ISO-8859-1';
    }
    return $charset;
}

function _wp_die_process_input($message, $title = '', $args = [])
{
    $original = $args;
    $defaults = ['response' => 0, 'code' => '', 'exit' => true, 'back_link' => false, 'link_url' => '', 'link_text' => '', 'text_direction' => 'ltr', 'charset' => 'utf-8', 'additional_errors' => []];
    if (is_wp_error($message)) {
        $errors = [];
        foreach ($message->get_error_codes() as $code) {
            $data = $message->get_error_data($code);
            $errors[] = ['code' => $code, 'message' => $message->get_error_message($code), 'data' => $data];
        }
        $data = $errors[0]['data'] ?? [];
        // The first error's status is the response unless the caller names one; of its data, only wp_die's own arguments carry over (probe functions-remaining).
        if (is_array($data) && isset($data['status'])) {
            $defaults['response'] = $data['status'];
        }
        $args = wp_parse_args($args, array_intersect_key(is_array($data) ? $data : [], $defaults) + $defaults);
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
    if (empty($args['code'])) {
        $args['code'] = 'wp_die';
    }
    // The charset is the site's unless the caller names one, in its usual spelling either way (probe functions-remaining).
    $args['charset'] = _canonical_charset(array_key_exists('charset', (array) $original) ? (string) $args['charset'] : (string) get_option('blog_charset'));
    return [$message, $title, $args];
}

/** The error as an XML document (a feed's, or any request that asked for XML), its status and no caching. */
function _xml_wp_die_handler($message, $title = '', $args = [])
{
    [$message, $title, $args] = _wp_die_process_input($message, $title, $args);
    $message = htmlspecialchars((string) $message);
    $title = htmlspecialchars((string) $title);
    if (!headers_sent()) {
        header("Content-Type: text/xml; charset={$args['charset']}");
        status_header((int) $args['response']);
        nocache_headers();
    }
    echo "<error>\n    <code>{$args['code']}</code>\n    <title><![CDATA[{$title}]]></title>\n    <message><![CDATA[{$message}]]></message>\n    <data>\n        <status>{$args['response']}</status>\n    </data>\n</error>\n";
    if ($args['exit']) {
        exit;
    }
}

function _default_wp_die_handler($message, $title = '', $args = [])
{
    [$message, $title, $args] = _wp_die_process_input($message, $title, $args);
    if ($args['additional_errors'] !== []) {
        $items = array_merge([$message], array_column($args['additional_errors'], 'message'));
        $message = "<ul>\n\t\t<li>" . implode("</li>\n\t\t<li>", $items) . "</li>\n\t</ul>";
    }
    $links = $args['link_url'] && $args['link_text'] ? "\n<p><a href='" . esc_url($args['link_url']) . "'>{$args['link_text']}</a></p>" : '';
    if ($args['back_link']) {
        $links .= "\n<p><a href='javascript:history.back()'>" . __('&laquo; Back') . '</a></p>';
    }
    if (!headers_sent()) {
        header("Content-Type: text/html; charset={$args['charset']}");
        status_header((int) $args['response']);
        nocache_headers();
    }
    add_filter('wp_robots', 'wp_robots_no_robots');
    ob_start();
    wp_robots();
    echo Minn\Http\DiePage::html(get_language_attributes(), (string) $args['text_direction'], (string) $args['charset'], (string) ob_get_clean(), (string) $title, (string) $message, $links);
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

/** The error as a JSONP call: the JSON handler's body passed to the _jsonp callback as given (empty when there is none), behind an empty comment. */
function _jsonp_wp_die_handler($message, $title = '', $args = [])
{
    [$message, $title, $args] = _wp_die_process_input($message, $title, $args);
    if (!headers_sent()) {
        http_response_code((int) $args['response']);
        header('Content-Type: application/javascript; charset=' . $args['charset']);
        header('X-Content-Type-Options: nosniff');
    }
    $body = wp_json_encode(['code' => $args['code'], 'message' => $message, 'data' => ['status' => $args['response']], 'additional_errors' => $args['additional_errors']]);
    echo '/**/' . ($_GET['_jsonp'] ?? '') . '(' . $body . ')';
    if ($args['exit']) {
        exit;
    }
}

/**
 * The page a lost database connection shows: wp-content/db-error.php when
 * the site has one, else, while installing, the database's own error, and
 * then "Error establishing a database connection" through wp_die.
 */
function dead_db()
{
    global $wpdb;
    if (file_exists(WP_CONTENT_DIR . '/db-error.php')) {
        require_once WP_CONTENT_DIR . '/db-error.php';
        exit;
    }
    if (wp_installing()) {
        wp_die($wpdb->error ?? '');
    }
    wp_die('<h1>' . __('Error establishing a database connection') . '</h1>', __('Database Error'));
}

/** A favicon request: do_faviconico, then a 302 to the 32px site icon (the reference's grey W logo when there is none), and the request ends. */
function do_favicon()
{
    do_action('do_faviconico');
    wp_redirect(get_site_icon_url(32, includes_url('images/w-logo-gray-white-bg.png')));
    exit;
}

/** The error handed to the XML-RPC server as its fault; nothing is printed without a server (probe functions-remaining). */
function _xmlrpc_wp_die_handler($message, $title = '', $args = [])
{
    global $wp_xmlrpc_server;
    [$message, $title, $args] = _wp_die_process_input($message, $title, $args);
    if ($wp_xmlrpc_server) {
        $wp_xmlrpc_server->output((new IXR_Error($args['response'], $message))->getXml());
    }
    if ($args['exit']) {
        exit;
    }
}

/** The message alone when it is a scalar (a WP_Error's first message counts); nothing else (probe functions-remaining). */
function _scalar_wp_die_handler($message = '', $title = '', $args = [])
{
    [$message, $title, $args] = _wp_die_process_input($message, $title, $args);
    if (is_scalar($message)) {
        echo (string) $message;
    }
    if ($args['exit']) {
        exit;
    }
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

/** Where the "Learn more about updating PHP" links point: wp_update_php_url, or the default when that answers nothing (probe plugin-activation). */
function wp_get_update_php_url()
{
    $url = apply_filters('wp_update_php_url', wp_get_default_update_php_url());
    return is_string($url) && $url !== '' ? $url : wp_get_default_update_php_url();
}

function wp_get_default_update_php_url()
{
    return _x('https://wordpress.org/support/update-php/', 'localized PHP upgrade information page');
}

/** The timezone setting's offset from UTC now, in hours; false when the site keeps a plain offset instead. */
function wp_timezone_override_offset()
{
    $zone = get_option('timezone_string');
    $timezone = $zone ? timezone_open($zone) : false;
    $now = date_create();
    if ($timezone === false || $now === false) {
        return false;
    }
    return round(timezone_offset_get($timezone, $now) / HOUR_IN_SECONDS, 2);
}

/** The hosting provider's own link for updating PHP (WP_DIRECT_UPDATE_PHP_URL), through wp_direct_php_update_url; empty when there is none. */
function wp_get_direct_php_update_url()
{
    $url = getenv('WP_DIRECT_UPDATE_PHP_URL');
    return apply_filters('wp_direct_php_update_url', $url === false ? '' : $url);
}

/**
 * The emoji detection script a plugin asks for. Minn prints none of the
 * reference's emoji plumbing on any page (see the runtime contract), and
 * on the reference a second call prints nothing either.
 */
function print_emoji_detection_script()
{
}

/** The emoji styles, enqueued while print_emoji_styles is hooked; it never is on Minn, which prints no emoji plumbing. */
function wp_enqueue_emoji_styles()
{
    $action = is_admin() ? 'admin_print_styles' : 'wp_print_styles';
    if (has_action($action, 'print_emoji_styles')) {
        remove_action($action, 'print_emoji_styles');
    }
}

/** @deprecated 6.4.0 The emoji styles, printed once per request (probe deprecated-remaining). */
function print_emoji_styles()
{
    static $printed = false;
    _deprecated_function(__FUNCTION__, '6.4.0', 'wp_enqueue_emoji_styles');
    if ($printed) {
        return;
    }
    $printed = true;
    echo "\t<style>\n\timg.wp-smiley,\n\timg.emoji {\n\t\tdisplay: inline !important;\n\t\tborder: none !important;\n\t\tbox-shadow: none !important;\n\t\theight: 1em !important;\n\t\twidth: 1em !important;\n"
        . "\t\tmargin: 0 0.07em !important;\n\t\tvertical-align: -0.1em !important;\n\t\tbackground: none !important;\n\t\tpadding: 0 !important;\n\t}\n\t</style>\n\t";
}

/** A secret's fast hash, as keys are stored: BLAKE2b keyed for this purpose, in URL-safe base64 after "$generic$". */
function wp_fast_hash(string $message): string
{
    return Minn\Auth\FastHash::hash($message);
}

/** Whether a secret matches a fast hash, or a phpass hash from before WordPress 6.8. */
function wp_verify_fast_hash(string $message, string $hash): bool
{
    return str_starts_with($hash, '$generic$') ? Minn\Auth\FastHash::verify($message, $hash) : Minn\Auth\PortableHash::verify($message, $hash);
}

/**
 * A folder's size in bytes, its folders' too, all kept in the dirsize_cache
 * transient: a size already there is answered from it; a folder named in
 * $exclude, or one that is not there, counts as false; past the time
 * allowed (the PHP limit less a second) the size is null.
 */
function recurse_dirsize($directory, $exclude = null, $max_execution_time = null, &$directory_cache = null)
{
    $directory = untrailingslashit($directory);
    $save = !isset($directory_cache);
    if ($save) {
        $directory_cache = get_transient('dirsize_cache');
    }
    if (isset($directory_cache[$directory]) && is_int($directory_cache[$directory])) {
        return $directory_cache[$directory];
    }
    if (!is_dir($directory) || !is_readable($directory) || (is_string($exclude) && $directory === $exclude) || (is_array($exclude) && in_array($directory, $exclude, true))) {
        return false;
    }
    if ($max_execution_time === null) {
        $max_execution_time = (int) ini_get('max_execution_time');
        $max_execution_time = $max_execution_time > 10 ? $max_execution_time - 1 : $max_execution_time;
    }
    $size = apply_filters('pre_recurse_dirsize', false, $directory, $exclude, $max_execution_time, $directory_cache);
    if ($size === false) {
        $size = _minn_dirsize($directory, $exclude, (int) $max_execution_time, $directory_cache);
    }
    $directory_cache = is_array($directory_cache) ? $directory_cache : [];
    $directory_cache[$directory] = $size;
    if ($save) {
        set_transient('dirsize_cache', $directory_cache, wp_using_ext_object_cache() ? 0 : 10 * YEAR_IN_SECONDS);
    }
    return $size;
}

/** @internal a folder's files and folders summed, or null when the time ran out */
function _minn_dirsize(string $directory, $exclude, int $max_execution_time, &$directory_cache): ?int
{
    $size = 0;
    $started = defined('WP_START_TIMESTAMP') ? WP_START_TIMESTAMP : (float) ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));
    foreach (scandir($directory, SCANDIR_SORT_NONE) ?: [] as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        $path = $directory . '/' . $file;
        $size += is_file($path) ? (int) filesize($path) : (is_dir($path) ? max(0, (int) recurse_dirsize($path, $exclude, $max_execution_time, $directory_cache)) : 0);
        if ($max_execution_time > 0 && microtime(true) - $started > $max_execution_time) {
            return null;
        }
    }
    return $size;
}

/** A folder's size, through recurse_dirsize and its cache. */
function get_dirsize($directory, $max_execution_time = null)
{
    return recurse_dirsize($directory, null, $max_execution_time);
}

/** The folder personal data exports are written to, in uploads. */
function wp_privacy_exports_dir()
{
    return apply_filters('wp_privacy_exports_dir', trailingslashit(wp_upload_dir()['basedir']) . 'wp-personal-data-exports/');
}

/** The address of the personal data exports folder. */
function wp_privacy_exports_url()
{
    return apply_filters('wp_privacy_exports_url', trailingslashit(wp_upload_dir()['baseurl']) . 'wp-personal-data-exports/');
}

/** True for "y" in either case; anything else is false. */
function bool_from_yn($yn)
{
    return strtolower((string) $yn) === 'y';
}

/** Every string in an array slashed, nested arrays too; other values as they were. */
function add_magic_quotes($input_array)
{
    foreach ((array) $input_array as $key => $value) {
        $input_array[$key] = is_array($value) ? add_magic_quotes($value) : (is_string($value) ? addslashes($value) : $value);
    }
    return $input_array;
}

/** The first extension wp_get_mime_types lists for a MIME type; false for one it does not know. */
function wp_get_default_extension_for_mime_type($mime_type)
{
    $extensions = (string) array_search($mime_type, wp_get_mime_types(), true);
    return $extensions === '' ? false : explode('|', $extensions)[0];
}

/** Whether the device can upload files: any but a mobile one, and of the iOS devices only iOS 6 and later. */
function _device_can_upload()
{
    if (!wp_is_mobile()) {
        return true;
    }
    $agent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    if (str_contains($agent, 'iPhone') || str_contains($agent, 'iPad') || str_contains($agent, 'iPod')) {
        return preg_match('#OS ([\d_]+) like Mac OS X#', $agent, $version) === 1 && version_compare($version[1], '6', '>=');
    }
    return true;
}

/** An id for a set of values: the prefix and the first eight hex of the MD5 of their JSON. */
function wp_unique_id_from_values(array $data, string $prefix = ''): string
{
    return $prefix . substr(md5((string) wp_json_encode($data)), 0, 8);
}

/** Deprecated since 6.1: global terms are gone. */
function global_terms_enabled()
{
    _deprecated_function(__FUNCTION__, '6.1.0');
    return false;
}

/** A duration as words ("1 hour, 2 minutes, 3 seconds") from [H:]MM:SS (a leading minus ignored); false for anything else. */
function human_readable_duration($duration = '')
{
    if (empty($duration) || !is_string($duration)) {
        return false;
    }
    $duration = ltrim(trim($duration), '-');
    if (!preg_match('/^(?:(\d+):)?([0-5]?\d):([0-5]?\d)$/', $duration, $m)) {
        return false;
    }
    $parts = (int) $m[1] > 0 ? [sprintf(_n('%s hour', '%s hours', (int) $m[1]), (int) $m[1])] : [];
    $parts[] = sprintf(_n('%s minute', '%s minutes', (int) $m[2]), (int) $m[2]);
    $parts[] = sprintf(_n('%s second', '%s seconds', (int) $m[3]), (int) $m[3]);
    return implode(', ', $parts);
}

/** A WebP file's width, height and kind (Media\WebpInfo); all false for anything else. */
function wp_get_webp_info($filename)
{
    if (wp_get_image_mime($filename) !== 'image/webp') {
        return ['width' => false, 'height' => false, 'type' => false];
    }
    return Minn\Media\WebpInfo::read((string) @file_get_contents((string) $filename, false, null, 0, 40));
}

/** The folder fonts upload to (uploads/fonts), through font_dir. */
function wp_get_font_dir()
{
    $uploads = wp_upload_dir(null, false);
    $path = untrailingslashit((string) $uploads['basedir']) . '/fonts';
    $url = untrailingslashit((string) $uploads['baseurl']) . '/fonts';
    return apply_filters('font_dir', ['path' => $path, 'url' => $url, 'subdir' => '', 'basedir' => $path, 'baseurl' => $url, 'error' => false]);
}

/** WP_HOME, when wp-config.php defines it, stands for the home option (hooked on option_home; probe functions-utils). */
function _config_wp_home($url = '')
{
    return defined('WP_HOME') ? untrailingslashit(WP_HOME) : $url;
}

/** WP_SITEURL, when wp-config.php defines it, stands for the siteurl option (hooked on option_siteurl). */
function _config_wp_siteurl($url = '')
{
    return defined('WP_SITEURL') ? untrailingslashit(WP_SITEURL) : $url;
}

/** Whether a cache id is an integer or a string that reads as one exactly; anything else is reported and refused. */
function _validate_cache_id($object_id)
{
    if (is_int($object_id) || (is_string($object_id) && (string) (int) $object_id === $object_id)) {
        return true;
    }
    _doing_it_wrong('_get_non_cached_ids', sprintf(__('Object ID must be an integer, %s given.'), gettype($object_id)), '6.3.0');
    return false;
}

/** Whether a list of files holds one of a file's sub-sizes: name-WxH, name-scaled or name-rotated, same extension, any case. */
function _wp_check_existing_file_names($filename, $files)
{
    $filename = (string) $filename;
    $dot = strrpos($filename, '.');
    $pattern = '/^' . preg_quote($dot === false ? $filename : substr($filename, 0, $dot), '/') . '-(?:\d+x\d+|scaled|rotated)' . preg_quote($dot === false ? '' : substr($filename, $dot), '/') . '$/i';
    foreach ((array) $files as $file) {
        if (preg_match($pattern, (string) $file) === 1) {
            return true;
        }
    }
    return false;
}

/** Whether any alternate name is taken: on disk as $dir joined to the name as given (no separator added), or by a sub-size in $files. */
function _wp_check_alternate_file_names($filenames, $dir, $files)
{
    foreach ((array) $filenames as $filename) {
        if (file_exists($dir . $filename) || (!empty($files) && _wp_check_existing_file_names($filename, $files))) {
            return true;
        }
    }
    return false;
}

/** @internal a parent lookup for the hierarchy-loop finders: an override first, else the callback with its extra arguments */
function _minn_hierarchy_parent(callable $callback, array $override, array $callback_args): Closure
{
    return static fn ($id) => isset($override[$id]) ? $override[$id] : call_user_func_array($callback, array_merge([$id], $callback_args));
}

/** The loop a parent chain falls into from $start (given $start_parent), as the nodes in it; empty when there is none. */
function wp_find_hierarchy_loop($callback, $start, $start_parent, $callback_args = [])
{
    $override = $start_parent === null ? [] : [$start => $start_parent];
    $member = wp_find_hierarchy_loop_tortoise_hare($callback, $start, $override, $callback_args);
    return $member ? wp_find_hierarchy_loop_tortoise_hare($callback, $member, $override, $callback_args, true) : [];
}

/** Where a slow and a fast walker up the parent chain meet (or every node walked, with $_return_loop), or false when the chain ends. */
function wp_find_hierarchy_loop_tortoise_hare($callback, $start, $override = [], $callback_args = [], $_return_loop = false)
{
    $parentOf = _minn_hierarchy_parent($callback, (array) $override, (array) $callback_args);
    $found = $_return_loop ? Minn\Support\Hierarchy::walked($parentOf, $start) : Minn\Support\Hierarchy::meeting($parentOf, $start);
    return $found ?? false;
}

/** Drops a folder and every folder above it from the dirsize cache; folders below it and beside it keep their sizes. */
function clean_dirsize_cache($path)
{
    $cache = get_transient('dirsize_cache');
    if (!is_array($cache) || $cache === []) {
        return;
    }
    $path = untrailingslashit((string) $path);
    for ($parent = ''; $path !== $parent && $path !== '' && $path !== '.'; $parent = $path, $path = dirname($path)) {
        unset($cache[$path]);
    }
    set_transient('dirsize_cache', $cache, wp_using_ext_object_cache() ? 0 : 10 * YEAR_IN_SECONDS);
}

/** Whether the server is lighttpd older than 1.5.0. */
function is_lighttpd_before_150()
{
    $software = explode('/', (string) ($_SERVER['SERVER_SOFTWARE'] ?? ''));
    return $software[0] === 'lighttpd' && version_compare($software[1] ?? '', '1.5.0') === -1;
}

/** Whether sites in a network can carry meta: never on a single site. */
function is_site_meta_supported()
{
    return is_multisite() && (bool) get_network_option(get_main_network_id(), 'site_meta_supported', false);
}

/** Whether a charset (the blog_charset option when none is given) names UTF-8, with or without the dash, any case. */
function is_utf8_charset($blog_charset = null)
{
    return _is_utf8_charset($blog_charset ?? get_option('blog_charset'));
}

/** Whether a type is one of the HEIC/HEIF image types, spelled exactly. */
function wp_is_heic_image_mime_type($mime_type)
{
    return in_array($mime_type, ['image/heic', 'image/heif', 'image/heic-sequence', 'image/heif-sequence'], true);
}

/** The documentation page on moving a site to HTTPS. */
function wp_get_default_update_https_url()
{
    return __('https://developer.wordpress.org/advanced-administration/security/https/');
}

/** Where the host lets a site switch to HTTPS in one step: WP_DIRECT_UPDATE_HTTPS_URL, through wp_direct_update_https_url. */
function wp_get_direct_update_https_url()
{
    $url = getenv('WP_DIRECT_UPDATE_HTTPS_URL');
    return apply_filters('wp_direct_update_https_url', $url === false ? '' : $url);
}

/** Where to read about HTTPS: WP_UPDATE_HTTPS_URL or the documentation, through wp_update_https_url; empty falls back to the documentation. */
function wp_get_update_https_url()
{
    $default = wp_get_default_update_https_url();
    $url = getenv('WP_UPDATE_HTTPS_URL');
    $url = apply_filters('wp_update_https_url', $url === false ? $default : $url);
    return empty($url) ? $default : $url;
}

/** The note that the PHP update page comes from the host, when wp_update_php_url changed it; else nothing. */
function wp_get_update_php_annotation()
{
    $default = wp_get_default_update_php_url();
    if (wp_get_update_php_url() === $default) {
        return '';
    }
    return sprintf(__('This resource is provided by your web host, and is specific to your site. For more information, <a href="%s" target="_blank">see the official WordPress documentation</a>.'), esc_url($default));
}

/** Tells the old admin address that the admin email changed (hooked on update_option_admin_email); never from an empty or placeholder address. */
function wp_site_admin_email_change_notification($old_email, $new_email, $option_name)
{
    if (!apply_filters('send_site_admin_email_change_email', $old_email !== '' && $old_email !== 'you@example.com', $old_email, $new_email)) {
        return;
    }
    $email = apply_filters('site_admin_email_change_email', [
        'to' => $old_email,
        'subject' => __('[%s] Admin Email Changed'),
        'message' => __("Hi,\n\nThis notice confirms that the admin email address was changed on ###SITENAME###.\n\nThe new admin email address is ###NEW_EMAIL###.\n\nThis email has been sent to ###OLD_EMAIL###\n\nRegards,\nAll at ###SITENAME###\n###SITEURL###"),
        'headers' => '',
    ], $old_email, $new_email);
    $site = wp_specialchars_decode((string) get_option('blogname'), ENT_QUOTES);
    $message = str_replace(['###OLD_EMAIL###', '###NEW_EMAIL###', '###SITENAME###', '###SITEURL###'], [$old_email, $new_email, $site, home_url()], $email['message']);
    wp_mail($email['to'], sprintf($email['subject'], $site), $message, $email['headers']);
}

/** Adds the Widgets menu unless load_default_widgets turns it off (hooked on plugins_loaded at 0); the engine registers the widgets themselves at init. */
function wp_maybe_load_widgets()
{
    if (apply_filters('load_default_widgets', true)) {
        add_action('_admin_menu', 'wp_widgets_add_menu');
    }
}

/** Schedules the hourly removal of old personal data exports, once (on init). */
function wp_schedule_delete_old_privacy_export_files()
{
    if (!wp_next_scheduled('wp_privacy_delete_old_export_files')) {
        wp_schedule_event(time(), 'hourly', 'wp_privacy_delete_old_export_files');
    }
}

/** Removes the personal data export files written more than three days ago (wp_privacy_export_expiration); the folder's index.php stays. */
function wp_privacy_delete_old_export_files()
{
    $before = time() - (int) apply_filters('wp_privacy_export_expiration', 3 * DAY_IN_SECONDS);
    foreach (Minn\Content\PersonalData::expiredExports(wp_privacy_exports_dir(), $before) as $file) {
        wp_delete_file($file);
    }
}

/** Schedules the daily clean-up of unconfirmed personal data requests, once (on init). */
function wp_schedule_personal_data_cleanup_requests()
{
    if (!wp_next_scheduled('wp_privacy_personal_data_cleanup_requests')) {
        wp_schedule_event(time(), 'daily', 'wp_privacy_personal_data_cleanup_requests');
    }
}

/** Marks a request still pending once its confirmation key has expired (a day; user_request_key_expiration) as failed. */
function wp_privacy_personal_data_cleanup_requests()
{
    $before = time() - (int) apply_filters('user_request_key_expiration', DAY_IN_SECONDS);
    $pending = get_posts(['post_type' => 'user_request', 'post_status' => 'request-pending', 'posts_per_page' => -1]);
    foreach ($pending as $request) {
        if (strtotime($request->post_modified_gmt . ' UTC') < $before) {
            wp_update_post(['ID' => $request->ID, 'post_status' => 'request-failed']);
        }
    }
}

/** On a post's preview, the script that names the window for it and forgets the name when the page goes (wp_head 1). */
function wp_post_preview_js()
{
    global $post;
    if (!is_preview() || empty($post)) {
        return;
    }
    $name = 'wp-preview-' . (int) $post->ID;
    $script = "\t( function() {\n\t\tvar query = document.location.search;\n\n\t\tif ( query && query.indexOf( 'preview=true' ) !== -1 ) {\n\t\t\twindow.name = '{$name}';\n\t\t}\n\n\t\tif ( window.addEventListener ) {\n\t\t\twindow.addEventListener( 'pagehide', function() { window.name = ''; } );\n\t\t}\n\t}());\n\t//# sourceURL=wp_post_preview_js\t";
    wp_print_inline_script_tag($script);
}

/** The request run through the WP object (WP::main), the main query its result; the query of record set when none is. */
function wp($query_vars = '')
{
    global $wp, $wp_query, $wp_the_query;
    $wp->main($query_vars);
    if (!isset($wp_the_query)) {
        $wp_the_query = $wp_query;
    }
}

/** @internal the reference's refusal of a request whose query string and form disagree on a public var */
function _minn_die_variable_mismatch(): void
{
    wp_die(__('A variable mismatch has been detected.'), __('Sorry, you are not allowed to view this item.'), 400);
}

/** The timezone choices for a settings form, the chosen one marked (Minn\\Support\\TimezoneChoice; probe placeholder-helpers). */
function wp_timezone_choice($selected_zone, $locale = null)
{
    return Minn\Support\TimezoneChoice::html((string) $selected_zone, static fn (string $text, string $domain): string => translate($text, $domain));
}

/** The note under a PHP update notice when the host gave its own address: printed in the wrapper, or returned (probe placeholder-helpers). */
function wp_update_php_annotation($before = '<p class="description">', $after = '</p>', $display = true)
{
    $annotation = wp_get_update_php_annotation();
    if ($annotation === '') {
        return null;
    }
    if (!$display) {
        return $before . $annotation . $after;
    }
    echo $before . $annotation . $after;
    return null;
}
