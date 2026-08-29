<?php
/** Escaping, sanitising, and the small string helpers. Behaviour from contracts/fixtures/api/functions.json. */

use Minn\Content\Blocks;
use Minn\Content\Texturize;
use Minn\Runtime\Runtime;
use Minn\Support\Url;

function wp_check_invalid_utf8($text, $strip = false)
{
    $text = (string) $text;
    if ($text === '' || preg_match('/^./us', $text) === 1) {
        return $text;
    }
    return $strip ? (string) iconv('UTF-8', 'UTF-8//IGNORE', $text) : '';
}

/** @internal the named entities the reference keeps intact when escaping */
function _minn_known_entity(string $name): bool
{
    static $known = null;
    if ($known === null) {
        $table = json_decode((string) file_get_contents(Runtime::current()->engineDir . '/data/kses.json'), true);
        $known = array_fill_keys(array_merge($table['entities'] ?? [], ['quot', 'amp', 'lt', 'gt', 'apos']), true);
    }
    return isset($known[$name]);
}

function _wp_specialchars($text, $quote_style = ENT_NOQUOTES, $charset = false, $double_encode = false)
{
    $text = (string) $text;
    if ($text === '') {
        return '';
    }
    if (!preg_match('/[&<>"\']/', $text)) {
        return $text;
    }
    if ($quote_style === 'single') {
        $flags = ENT_NOQUOTES;
        $single = true;
    } elseif ($quote_style === 'double') {
        $flags = ENT_COMPAT;
        $single = false;
    } else {
        $flags = $quote_style === false || $quote_style === 0 ? ENT_NOQUOTES : (int) $quote_style;
        $single = false;
    }
    if (!$double_encode) {
        $text = preg_replace_callback('/&(#\d+|#[xX][0-9a-fA-F]+|[A-Za-z][A-Za-z0-9]*);/', static function (array $m): string {
            $name = $m[1];
            if ($name[0] === '#') {
                return '&' . $name . ';';
            }
            return _minn_known_entity($name) ? '&' . $name . ';' : '&amp;' . $name . ';';
        }, $text);
        $text = preg_replace('/&(?!(?:#\d+|#[xX][0-9a-fA-F]+|[A-Za-z][A-Za-z0-9]*);)/', '&amp;', $text);
        $text = preg_replace('/&amp;amp;(?=[A-Za-z0-9#]+;)/', '&amp;', $text);
        $text = htmlspecialchars($text, $flags & ~ENT_HTML401 | ENT_SUBSTITUTE, 'UTF-8', false);
    } else {
        $text = htmlspecialchars($text, $flags | ENT_SUBSTITUTE, 'UTF-8', true);
    }
    if ($single) {
        $text = str_replace("'", '&#039;', $text);
    } elseif ($flags & ENT_QUOTES) {
        $text = str_replace("'", '&#039;', $text);
    }
    return $text;
}

function esc_html($text)
{
    $safe = wp_check_invalid_utf8((string) $text);
    $safe = _wp_specialchars($safe, ENT_QUOTES);
    return apply_filters('esc_html', $safe, $text);
}

function esc_attr($text)
{
    $safe = wp_check_invalid_utf8((string) $text);
    $safe = _wp_specialchars($safe, ENT_QUOTES);
    return apply_filters('attribute_escape', $safe, $text);
}

function esc_textarea($text)
{
    $safe = htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    return apply_filters('esc_textarea', $safe, $text);
}

function esc_js($text)
{
    $safe = wp_check_invalid_utf8((string) $text);
    $safe = _wp_specialchars($safe, ENT_COMPAT);
    $safe = preg_replace('/&#(x)?0*(?(1)27|39);?/i', "'", stripslashes($safe));
    $safe = str_replace("\r", '', $safe);
    $safe = str_replace("\n", '\\n', addslashes($safe));
    return apply_filters('js_escape', $safe, $text);
}

function esc_url($url, $protocols = null, $_context = 'display')
{
    $original = $url;
    $url = Url::clean((string) $url);
    if ($url === '') {
        return $url;
    }
    if ($_context === 'display') {
        $url = str_replace(['&amp;', "'"], ['&#038;', '&#039;'], wp_kses_normalize_entities($url));
    }
    $url = Url::encodeBrackets($url, (array) wp_parse_url($url));
    if (str_contains($url, ':') && wp_kses_bad_protocol($url, is_array($protocols) ? $protocols : wp_allowed_protocols()) !== $url) {
        return '';
    }
    return apply_filters('clean_url', $url, $original, $_context);
}

function esc_url_raw($url, $protocols = null)
{
    return sanitize_url($url, $protocols);
}

function sanitize_url($url, $protocols = null)
{
    return esc_url($url, $protocols, 'db');
}

function esc_sql($data)
{
    if (is_array($data)) {
        foreach ($data as $k => $v) {
            $data[$k] = esc_sql($v);
        }
        return $data;
    }
    $db = Runtime::current()->db;
    return $db->escape((string) $data);
}

function wp_pre_kses_less_than($content)
{
    return preg_replace_callback('%<[^>]*?((?=<)|>|$)%', static function (array $m): string {
        if (str_contains($m[0], '>')) {
            return $m[0];
        }
        return str_replace('<', '&lt;', $m[0]);
    }, (string) $content);
}

function _sanitize_text_fields($str, $keep_newlines = false)
{
    if (is_object($str) || is_array($str)) {
        return '';
    }
    $str = (string) $str;
    $filtered = wp_check_invalid_utf8($str);
    if (str_contains($filtered, '<')) {
        $filtered = wp_pre_kses_less_than($filtered);
        $filtered = wp_strip_all_tags($filtered, false);
        $filtered = str_replace("<\n", "&lt;\n", $filtered);
    }
    if (!$keep_newlines) {
        $filtered = preg_replace('/[\r\n\t ]+/', ' ', $filtered);
    }
    $filtered = trim($filtered);
    $filtered = str_replace("\0", '', $filtered);
    $found = false;
    while (preg_match('/%[a-f0-9]{2}/i', $filtered, $match)) {
        $filtered = str_replace($match[0], '', $filtered);
        $found = true;
    }
    if ($found) {
        $filtered = trim(preg_replace('/ +/', ' ', $filtered));
    }
    return $filtered;
}

function sanitize_text_field($str)
{
    return apply_filters('sanitize_text_field', _sanitize_text_fields($str, false), $str);
}

function sanitize_textarea_field($str)
{
    return apply_filters('sanitize_textarea_field', _sanitize_text_fields($str, true), $str);
}

function wp_strip_all_tags($text, $remove_breaks = false)
{
    if (is_null($text)) {
        return '';
    }
    if (!is_scalar($text)) {
        return '';
    }
    $text = preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $text);
    $text = strip_tags($text);
    if ($remove_breaks) {
        $text = preg_replace('/[\r\n\t ]+/', ' ', $text);
    }
    return trim($text);
}

function trailingslashit($value)
{
    return untrailingslashit($value) . '/';
}

function untrailingslashit($value)
{
    return rtrim((string) $value, '/\\');
}

function wp_parse_args($args, $defaults = [])
{
    if (is_object($args)) {
        $parsed = get_object_vars($args);
    } elseif (is_array($args)) {
        $parsed = $args;
    } else {
        parse_str((string) $args, $parsed);
    }
    return is_array($defaults) && $defaults ? array_merge($defaults, $parsed) : $parsed;
}

function wp_parse_list($input_list)
{
    if (!is_array($input_list)) {
        return preg_split('/[\s,]+/', (string) $input_list, -1, PREG_SPLIT_NO_EMPTY);
    }
    return array_filter(array_map('trim', $input_list), static fn ($v) => $v !== '');
}

function wp_parse_id_list($input_list)
{
    return array_values(array_unique(array_map('absint', wp_parse_list($input_list))));
}

function wp_parse_slug_list($input_list)
{
    return array_values(array_unique(array_map('sanitize_title', wp_parse_list($input_list))));
}

function wp_json_encode($value, $flags = 0, $depth = 512)
{
    $json = json_encode($value, $flags, $depth);
    if ($json !== false) {
        return $json;
    }
    $clean = _minn_json_sanitize($value);
    return json_encode($clean, $flags, $depth);
}

/** @internal invalid UTF-8 becomes ? so encoding can succeed */
function _minn_json_sanitize($value)
{
    if (is_string($value)) {
        return preg_match('/^./us', $value) === 1 || $value === '' ? $value : (string) preg_replace('/[\x80-\xff]/', '?', $value);
    }
    if (is_array($value)) {
        return array_map('_minn_json_sanitize', $value);
    }
    if (is_object($value)) {
        foreach (get_object_vars($value) as $k => $v) {
            $value->{$k} = _minn_json_sanitize($v);
        }
    }
    return $value;
}

function sanitize_key($key)
{
    $raw = $key;
    $key = strtolower((string) $key);
    $key = preg_replace('/[^a-z0-9_\-]/', '', $key);
    return apply_filters('sanitize_key', $key, $raw);
}

function sanitize_html_class($classname, $fallback = '')
{
    $class = $classname;
    $sanitized = preg_replace('|%[a-fA-F0-9][a-fA-F0-9]|', '', (string) $class);
    $sanitized = preg_replace('/[^A-Za-z0-9_-]/', '', $sanitized);
    if ($sanitized === '' && $fallback !== '') {
        return sanitize_html_class($fallback);
    }
    return apply_filters('sanitize_html_class', $sanitized, $class, $fallback);
}

function remove_accents($text, $locale = '')
{
    $text = (string) $text;
    if (!preg_match('/[\x80-\xff]/', $text)) {
        return $text;
    }
    $map = ['ß' => 'ss', 'Æ' => 'AE', 'æ' => 'ae', 'Œ' => 'OE', 'œ' => 'oe', 'Ø' => 'O', 'ø' => 'o', 'Đ' => 'D', 'đ' => 'd', 'Ł' => 'L', 'ł' => 'l', 'Þ' => 'TH', 'þ' => 'th', 'Ð' => 'D', 'ð' => 'd', '€' => 'E', '£' => '', '“' => '', '”' => '', '‘' => '', '’' => '', '–' => '-', '—' => '-', '…' => ''];
    $text = strtr($text, $map);
    $out = '';
    foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) as $char) {
        if (ord($char) < 0x80) {
            $out .= $char;
            continue;
        }
        $decomposed = Normalizer::normalize($char, Normalizer::FORM_D);
        $stripped = preg_replace('/\p{Mn}+/u', '', (string) $decomposed);
        $out .= $stripped === '' || preg_match('/[\x80-\xff]/', $stripped) ? $char : $stripped;
    }
    return $out;
}

function sanitize_title($title, $fallback_title = '', $context = 'save')
{
    $raw = (string) $title;
    $title = $context === 'save' ? remove_accents($raw) : $raw;
    $title = apply_filters('sanitize_title', $title, $raw, $context);
    if ($title === '' && $fallback_title !== '') {
        $title = (string) $fallback_title;
    }
    return $title;
}

function sanitize_title_with_dashes($title, $raw_title = '', $context = 'display')
{
    $title = strip_tags((string) $title);
    $title = preg_replace('|%([a-fA-F0-9][a-fA-F0-9])|', '---$1---', $title);
    $title = str_replace('%', '', $title);
    $title = preg_replace('|---([a-fA-F0-9][a-fA-F0-9])---|', '%$1', $title);
    if (mb_check_encoding($title, 'UTF-8')) {
        $title = mb_strtolower($title, 'UTF-8');
        $title = _minn_utf8_uri_encode($title, 200);
    }
    $title = strtolower($title);
    if ($context === 'save') {
        $title = str_replace(['%c2%a0', '%e2%80%93', '%e2%80%94'], '-', $title);
        $title = str_replace(['&nbsp;', '&#160;', '&ndash;', '&#8211;', '&mdash;', '&#8212;', '/', '×', '%c3%97'], '-', $title);
        $title = str_replace(['%c2%ad', '%c2%a1', '%c2%bf', '%c2%ab', '%c2%bb', '%e2%80%b9', '%e2%80%ba', '%e2%80%98', '%e2%80%99', '%e2%80%9c', '%e2%80%9d', '%e2%80%9a', '%e2%80%9b', '%e2%80%9e', '%e2%80%9f', '%e2%80%a2', '%c2%a9', '%c2%ae', '%c2%b0', '%e2%80%a6', '%e2%84%a2', '%c2%b4', '%cb%8a', '%cc%81', '%cd%81', '%cc%80', '%cc%84', '%cc%8c', '%e2%82%ac', '%c2%a3', '%e2%80%80', '%e2%80%81', '%e2%80%82', '%e2%80%83', '%e2%80%84', '%e2%80%85', '%e2%80%86', '%e2%80%87', '%e2%80%88', '%e2%80%89', '%e2%80%8a', '%e2%80%8b', '%e2%80%8c', '%e2%80%8d', '%e2%80%8e', '%e2%80%8f', '%e2%80%aa', '%e2%80%ab', '%e2%80%ac', '%e2%80%ad', '%e2%80%ae', '%e2%80%af', '%e2%81%9f', '%e3%80%80', '%ef%bb%bf'], '', $title);
        $title = str_replace('%c3%97', 'x', $title);
    }
    $title = preg_replace('/&.+?;/', '', $title);
    $title = str_replace('.', '-', $title);
    $title = preg_replace('/[^%a-z0-9 _-]/', '', $title);
    $title = preg_replace('/\s+/', '-', $title);
    $title = preg_replace('|-+|', '-', $title);
    return trim($title, '-');
}

/** @internal percent-encodes non-ASCII bytes the way slugs need */
function _minn_utf8_uri_encode(string $utf8, int $length = 0): string
{
    $out = '';
    foreach (str_split($utf8) as $byte) {
        $out .= ord($byte) < 0x80 ? $byte : '%' . strtolower(bin2hex($byte));
    }
    return $out;
}

function sanitize_file_name($filename)
{
    $raw = (string) $filename;
    $special = ['?', '[', ']', '/', '\\', '=', '<', '>', ':', ';', ',', "'", '"', '&', '$', '#', '*', '(', ')', '|', '~', '`', '!', '{', '}', '%', '+', '’', '«', '»', '”', '“', chr(0)];
    $special = apply_filters('sanitize_file_name_chars', $special, $raw);
    $filename = str_replace("\x00", '', $raw);
    $filename = preg_replace("#\x{00a0}#siu", ' ', $filename);
    $filename = str_replace($special, '', $filename);
    $filename = str_replace(['%20', '+'], '-', $filename);
    $filename = preg_replace('/\.{2,}/', '.', $filename);
    $filename = preg_replace('/[\r\n\t -]+/', '-', $filename);
    $filename = trim($filename, '.-_');
    return apply_filters('sanitize_file_name', $filename, $raw);
}

function sanitize_email($email)
{
    $raw = (string) $email;
    $email = trim($raw);
    if (strlen($email) < 6 || !str_contains($email, '@') || strrpos($email, '@') === 0) {
        return apply_filters('sanitize_email', '', $raw, 'email_too_short');
    }
    [$local, $domain] = explode('@', $email, 2) + ['', ''];
    if (substr_count($email, '@') !== 1) {
        return apply_filters('sanitize_email', '', $raw, 'email_no_at');
    }
    $local = preg_replace('/[^a-zA-Z0-9!#$%&\'*+\/=?^_`{|}~\.-]/', '', $local);
    if ($local === '') {
        return apply_filters('sanitize_email', '', $raw, 'local_invalid_chars');
    }
    $domain = preg_replace('/\.{2,}/', '', $domain);
    $domain = trim($domain, " \t\n\r\0\x0B.");
    if ($domain === '' || !str_contains($domain, '.')) {
        return apply_filters('sanitize_email', '', $raw, 'domain_no_periods');
    }
    $subs = [];
    foreach (explode('.', $domain) as $sub) {
        $sub = trim(preg_replace('/[^a-z0-9-]+/i', '', $sub), '-');
        if ($sub !== '') {
            $subs[] = $sub;
        }
    }
    if (count($subs) < 2) {
        return apply_filters('sanitize_email', '', $raw, 'domain_no_valid_subs');
    }
    return apply_filters('sanitize_email', $local . '@' . implode('.', $subs), $raw, null);
}

function is_email($email, $deprecated = false)
{
    $email = (string) $email;
    if (strlen($email) < 6 || substr_count($email, '@') !== 1) {
        return apply_filters('is_email', false, $email, 'email_too_short');
    }
    [$local, $domain] = explode('@', $email, 2);
    if (!preg_match('/^[a-zA-Z0-9!#$%&\'*+\/=?^_`{|}~\.-]+$/', $local)) {
        return apply_filters('is_email', false, $email, 'local_invalid_chars');
    }
    if (preg_match('/\.{2,}/', $domain) || trim($domain, " \t\n\r\0\x0B.") !== $domain) {
        return apply_filters('is_email', false, $email, 'domain_period_sequence');
    }
    $subs = explode('.', $domain);
    if (count($subs) < 2) {
        return apply_filters('is_email', false, $email, 'domain_no_periods');
    }
    foreach ($subs as $sub) {
        if (trim($sub, '-') !== $sub || !preg_match('/^[a-z0-9-]+$/i', $sub)) {
            return apply_filters('is_email', false, $email, 'sub_hyphen_limits');
        }
    }
    return apply_filters('is_email', $email, $email, null);
}

function stripslashes_deep($value)
{
    return map_deep($value, 'stripslashes_from_strings_only');
}

function stripslashes_from_strings_only($value)
{
    return is_string($value) ? stripslashes($value) : $value;
}

function wp_unslash($value)
{
    return stripslashes_deep($value);
}

function wp_slash($value)
{
    return map_deep($value, 'addslashes_strings_only');
}

function addslashes_strings_only($value)
{
    return is_string($value) ? addslashes($value) : $value;
}

function map_deep($value, $callback)
{
    if (is_array($value)) {
        foreach ($value as $k => $v) {
            $value[$k] = map_deep($v, $callback);
        }
    } elseif (is_object($value)) {
        foreach (get_object_vars($value) as $k => $v) {
            $value->{$k} = map_deep($v, $callback);
        }
    } else {
        $value = $callback($value);
    }
    return $value;
}

function urlencode_deep($value)
{
    return map_deep($value, 'urlencode');
}

function rawurlencode_deep($value)
{
    return map_deep($value, 'rawurlencode');
}

function urldecode_deep($value)
{
    return map_deep($value, 'urldecode');
}

function wp_specialchars_decode($text, $quote_style = ENT_NOQUOTES)
{
    $text = (string) $text;
    if ($text === '' || !str_contains($text, '&')) {
        return $text;
    }
    $translate = ['&amp;' => '&', '&#038;' => '&', '&#x26;' => '&', '&lt;' => '<', '&#060;' => '<', '&#x3C;' => '<', '&gt;' => '>', '&#062;' => '>', '&#x3E;' => '>'];
    $quotes = ['&quot;' => '"', '&#034;' => '"', '&#x22;' => '"', '&#039;' => "'", '&#x27;' => "'", '&#39;' => "'", '&apos;' => "'"];
    if ($quote_style === ENT_QUOTES) {
        $translate = array_merge($translate, $quotes);
    } elseif ($quote_style === ENT_COMPAT || $quote_style === 'double') {
        $translate = array_merge($translate, array_slice($quotes, 0, 3, true));
    } elseif ($quote_style === 'single') {
        $translate = array_merge($translate, array_slice($quotes, 3, null, true));
    }
    return strtr($text, $translate);
}

function zeroise($number, $threshold)
{
    return sprintf('%0' . (int) $threshold . 's', $number);
}

function wp_basename($path, $suffix = '')
{
    return urldecode(basename(str_replace(['%2F', '%5C'], '/', urlencode((string) $path)), $suffix));
}

function size_format($bytes, $decimals = 0)
{
    $units = ['EB' => EB_IN_BYTES, 'PB' => PB_IN_BYTES, 'TB' => TB_IN_BYTES, 'GB' => GB_IN_BYTES, 'MB' => MB_IN_BYTES, 'KB' => KB_IN_BYTES, 'B' => 1];
    if ($bytes === 0 || $bytes === '0' || $bytes === 0.0) {
        return number_format_i18n(0, $decimals) . ' B';
    }
    foreach ($units as $unit => $mag) {
        if ((float) $bytes >= $mag) {
            return number_format_i18n($bytes / $mag, $decimals) . ' ' . $unit;
        }
    }
    return false;
}

function wp_html_excerpt($str, $count, $more = null)
{
    $more ??= '';
    $str = wp_strip_all_tags((string) $str, true);
    $excerpt = mb_substr($str, 0, (int) $count, 'UTF-8');
    if ($excerpt !== $str) {
        $excerpt = trim($excerpt) . $more;
    }
    return $excerpt;
}

function wp_trim_words($text, $num_words = 55, $more = null)
{
    $original = $text;
    $more ??= '&hellip;';
    $text = wp_strip_all_tags((string) $text);
    $text = str_replace("\xC2\xA0", ' ', $text);
    $words = preg_split("/[\n\r\t ]+/", $text, (int) $num_words + 1, PREG_SPLIT_NO_EMPTY);
    if (count($words) > (int) $num_words) {
        array_pop($words);
        $text = implode(' ', $words) . $more;
    } else {
        $text = implode(' ', $words);
    }
    return apply_filters('wp_trim_words', $text, $num_words, $more, $original);
}

function wptexturize($text, $reset = false)
{
    $text = Texturize::html((string) $text);
    // A bare ampersand outside the skipped elements becomes its entity.
    $parts = preg_split('/(<[^>]*>)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    $skip = 0;
    foreach ($parts as $i => $part) {
        if ($i % 2 === 1) {
            if (preg_match('#^<(/?)(pre|code|kbd|style|script|tt|textarea)\b#i', $part, $m)) {
                $skip += $m[1] === '/' ? -1 : 1;
            }
            continue;
        }
        if ($skip <= 0) {
            $parts[$i] = preg_replace('/&(?!#?\w+;)/', '&#038;', $part);
        }
    }
    return implode('', $parts);
}

function wpautop($text, $br = true)
{
    $text = (string) $text;
    if (trim($text) === '') {
        return '';
    }
    $text .= "\n";
    $blocks = '(?:table|thead|tfoot|caption|col|colgroup|tbody|tr|td|th|div|dl|dd|dt|ul|ol|li|pre|form|map|area|blockquote|address|style|p|h[1-6]|hr|fieldset|legend|section|article|aside|hgroup|header|footer|nav|figure|figcaption|details|menu|summary)';
    $text = preg_replace('|<br\s*/?>\s*<br\s*/?>|', "\n\n", $text);
    $text = preg_replace('!(<' . $blocks . '[\s/>])!', "\n\n$1", $text);
    $text = preg_replace('!(</' . $blocks . '>)!', "$1\n\n", $text);
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = preg_replace("/\n\n+/", "\n\n", $text);
    $paragraphs = preg_split('/\n\s*\n/', $text, -1, PREG_SPLIT_NO_EMPTY);
    $text = '';
    foreach ($paragraphs as $paragraph) {
        $text .= '<p>' . trim($paragraph, "\n") . "</p>\n";
    }
    $text = preg_replace('|<p>\s*</p>|', '', $text);
    $text = preg_replace('!<p>\s*(</?' . $blocks . '[^>]*>)\s*</p>!', '$1', $text);
    $text = preg_replace('|<p>(<li.+?)</p>|', '$1', $text);
    $text = preg_replace('!<p>\s*(</?' . $blocks . '[^>]*>)!', '$1', $text);
    $text = preg_replace('!(</?' . $blocks . '[^>]*>)\s*</p>!', '$1', $text);
    if ($br) {
        $text = preg_replace('|(?<!<br />)\s*\n|', "<br />\n", $text);
        $text = preg_replace('!(</?' . $blocks . '[^>]*>)\s*<br />!', '$1', $text);
        $text = preg_replace('!<br />(\s*</?(?:p|li|div|dl|dd|dt|th|pre|td|ul|ol)[^>]*>)!', '$1', $text);
    }
    $text = preg_replace("|\n</p>$|", '</p>', $text);
    return $text;
}

function make_clickable($text)
{
    $text = (string) $text;
    $text = preg_replace_callback('#(?<![\w"\'>=])((?:https?|ftp)://[^\s<"\']+?)(?=[.,;:!?)]*(?:\s|$|<))#i', static fn (array $m) => '<a href="' . esc_url($m[1]) . '" rel="nofollow">' . $m[1] . '</a>', $text);
    $text = preg_replace_callback('#(?<![\w"\'>=/])(www\.[^\s<"\']+?)(?=[.,;:!?)]*(?:\s|$|<))#i', static fn (array $m) => '<a href="' . esc_url('http://' . $m[1]) . '" rel="nofollow">' . $m[1] . '</a>', $text);
    $text = preg_replace_callback('#(?<![\w"\'>=:/])([a-z0-9._+-]+@[a-z0-9.-]+\.[a-z]{2,})#i', static fn (array $m) => '<a href="mailto:' . $m[1] . '">' . $m[1] . '</a>', $text);
    return $text;
}

function wp_normalize_path($path)
{
    $path = (string) $path;
    $wrapper = '';
    if (preg_match('#^([a-zA-Z0-9+.-]+)://#', $path, $m) && !preg_match('/^[a-zA-Z]:/', $path)) {
        $wrapper = $m[1] . '://';
        $path = substr($path, strlen($wrapper));
    }
    $path = str_replace('\\', '/', $path);
    $path = preg_replace('|(?<=.)/+|', '/', $path);
    if (preg_match('/^[a-z]:/', $path)) {
        $path = ucfirst($path);
    }
    return $wrapper . $path;
}

function wp_parse_url($url, $component = -1)
{
    $url = (string) $url;
    $toUnset = [];
    if (str_starts_with($url, '//')) {
        $toUnset[] = 'scheme';
        $url = 'placeholder:' . $url;
    } elseif (str_starts_with($url, '/')) {
        $toUnset = ['scheme', 'host'];
        $url = 'placeholder://placeholder' . $url;
    }
    $parts = parse_url($url);
    if ($parts === false) {
        return $component === -1 ? false : null;
    }
    foreach ($toUnset as $key) {
        unset($parts[$key]);
    }
    if ($component === -1) {
        return $parts;
    }
    $key = match ($component) {
        PHP_URL_SCHEME => 'scheme', PHP_URL_HOST => 'host', PHP_URL_PORT => 'port', PHP_URL_USER => 'user', PHP_URL_PASS => 'pass', PHP_URL_PATH => 'path', PHP_URL_QUERY => 'query', PHP_URL_FRAGMENT => 'fragment', default => null,
    };
    return $key === null ? null : ($parts[$key] ?? null);
}

function _get_component_from_parsed_url_array($url_parts, $component = -1)
{
    return $component === -1 ? $url_parts : ($url_parts[match ($component) { PHP_URL_SCHEME => 'scheme', PHP_URL_HOST => 'host', PHP_URL_PORT => 'port', PHP_URL_USER => 'user', PHP_URL_PASS => 'pass', PHP_URL_PATH => 'path', PHP_URL_QUERY => 'query', PHP_URL_FRAGMENT => 'fragment', default => '' }] ?? null);
}

function set_url_scheme($url, $scheme = null)
{
    $original = $url;
    $url = trim((string) $url);
    if ($scheme === null) {
        $scheme = is_ssl() ? 'https' : 'http';
    } elseif ($scheme === 'admin' || $scheme === 'login' || $scheme === 'login_post' || $scheme === 'rpc') {
        $scheme = is_ssl() || force_ssl_admin() ? 'https' : 'http';
    } elseif ($scheme !== 'http' && $scheme !== 'https' && $scheme !== 'relative') {
        $scheme = is_ssl() ? 'https' : 'http';
    }
    if (str_starts_with($url, '//')) {
        $url = 'http:' . $url;
    }
    if ($scheme === 'relative') {
        $url = ltrim(preg_replace('#^\w+://[^/]*#', '', $url));
        if ($url !== '' && $url[0] === '/') {
            $url = '/' . ltrim($url, "/ \t\n\r\0\x0B");
        }
    } else {
        $url = preg_replace('#^\w+://#', $scheme . '://', $url);
    }
    return apply_filters('set_url_scheme', $url, $scheme, $original);
}

function force_ssl_admin($force = null)
{
    return defined('FORCE_SSL_ADMIN') && FORCE_SSL_ADMIN;
}

function add_query_arg(...$args)
{
    $current = $GLOBALS['minn_request_uri'] ?? (Runtime::current()->request?->path ?? '');
    if (is_array($args[0])) {
        $uri = count($args) < 2 || $args[1] === false ? $current : (string) $args[1];
        $new = $args[0];
    } else {
        $uri = count($args) < 3 || $args[2] === false ? $current : (string) $args[2];
        $new = [$args[0] => $args[1] ?? null];
    }
    return Url::withQuery($uri, $new, static fn (array $q): array => urlencode_deep($q), static fn (array $q): string => _minn_build_query($q));
}

/** @internal keys are encoded, values are left as given (the reference shows raw values) */
function _minn_build_query(array $data, string $prefix = ''): string
{
    $pairs = [];
    foreach ($data as $k => $v) {
        $key = $prefix === '' ? urlencode((string) $k) : $prefix . '%5B' . urlencode((string) $k) . '%5D';
        if (is_array($v) || is_object($v)) {
            $pairs[] = _minn_build_query((array) $v, $key);
        } elseif ($v === null) {
            $pairs[] = $key;
        } else {
            $pairs[] = $key . '=' . (is_bool($v) ? (int) $v : (string) $v);
        }
    }
    return implode('&', array_filter($pairs, static fn ($p) => $p !== ''));
}

function remove_query_arg($key, $query = false)
{
    if (is_array($key)) {
        foreach ($key as $k) {
            $query = add_query_arg($k, false, $query);
        }
        return $query;
    }
    return add_query_arg($key, false, $query);
}

function wp_list_pluck($input_list, $field, $index_key = null)
{
    $out = [];
    foreach ($input_list as $key => $item) {
        $value = is_object($item) ? ($item->{$field} ?? null) : ($item[$field] ?? null);
        if ($index_key === null) {
            $out[$key] = $value;
            continue;
        }
        $index = is_object($item) ? ($item->{$index_key} ?? null) : ($item[$index_key] ?? null);
        if ($index === null) {
            $out[] = $value;
        } else {
            $out[$index] = $value;
        }
    }
    return $out;
}

function wp_list_filter($input_list, $args = [], $operator = 'AND')
{
    return wp_filter_object_list($input_list, $args, $operator);
}

function wp_filter_object_list($input_list, $args = [], $operator = 'and', $field = false)
{
    if (!is_array($input_list)) {
        return [];
    }
    $operator = strtoupper($operator);
    $out = [];
    foreach ($input_list as $key => $item) {
        $matched = 0;
        foreach ($args as $k => $v) {
            $value = is_object($item) ? ($item->{$k} ?? null) : ($item[$k] ?? null);
            if ((is_object($item) ? property_exists($item, $k) : array_key_exists($k, (array) $item)) && $value == $v) {
                $matched++;
            }
        }
        if (($operator === 'AND' && $matched === count($args)) || ($operator === 'OR' && $matched > 0) || ($operator === 'NOT' && $matched === 0)) {
            $out[$key] = $item;
        }
    }
    if ($field) {
        return wp_list_pluck($out, $field);
    }
    return $out;
}

function wp_list_sort($input_list, $orderby = [], $order = 'ASC', $preserve_keys = false)
{
    if (!is_array($orderby)) {
        $orderby = [$orderby => $order];
    }
    $sort = static function ($a, $b) use ($orderby): int {
        foreach ($orderby as $field => $direction) {
            $av = is_object($a) ? ($a->{$field} ?? null) : ($a[$field] ?? null);
            $bv = is_object($b) ? ($b->{$field} ?? null) : ($b[$field] ?? null);
            if ($av == $bv) {
                continue;
            }
            $result = $av < $bv ? -1 : 1;
            return strtoupper((string) $direction) === 'DESC' ? -$result : $result;
        }
        return 0;
    };
    if ($preserve_keys) {
        uasort($input_list, $sort);
    } else {
        usort($input_list, $sort);
    }
    return $input_list;
}

function wp_array_slice_assoc($input_array, $keys)
{
    $out = [];
    foreach ($keys as $key) {
        if (isset($input_array[$key])) {
            $out[$key] = $input_array[$key];
        }
    }
    return $out;
}

function wp_is_numeric_array($data)
{
    if (!is_array($data)) {
        return false;
    }
    foreach (array_keys($data) as $key) {
        if (!is_numeric($key)) {
            return false;
        }
    }
    return true;
}

function _wp_array_get($input_array, $path, $default_value = null)
{
    if (!is_array($path) || $path === []) {
        return $default_value;
    }
    foreach ($path as $key) {
        if (!is_array($input_array) || !array_key_exists($key, $input_array)) {
            return $default_value;
        }
        $input_array = $input_array[$key];
    }
    return $input_array;
}

function _wp_array_set(&$input_array, $path, $value = null)
{
    if (!is_array($input_array) || !is_array($path) || $path === []) {
        return;
    }
    $ref = &$input_array;
    foreach ($path as $key) {
        if (!isset($ref[$key]) || !is_array($ref[$key])) {
            $ref[$key] = [];
        }
        $ref = &$ref[$key];
    }
    $ref = $value;
}

function wp_rand($min = null, $max = null)
{
    $min = (int) ($min ?? 0);
    $max = (int) ($max ?? 0);
    if ($max <= $min) {
        return $min === $max ? $min : random_int(min($min, $max), max($min, $max));
    }
    return random_int($min, $max);
}

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

function __checked_selected_helper($helper, $current, $display, $type)
{
    $result = (string) $helper === (string) $current ? " {$type}='{$type}'" : '';
    if ($display) {
        echo $result;
    }
    return $result;
}

function checked($checked, $current = true, $display = true)
{
    return __checked_selected_helper($checked, $current, $display, 'checked');
}

function selected($selected, $current = true, $display = true)
{
    return __checked_selected_helper($selected, $current, $display, 'selected');
}

function disabled($disabled, $current = true, $display = true)
{
    return __checked_selected_helper($disabled, $current, $display, 'disabled');
}

function wp_readonly($readonly_value, $current = true, $display = true)
{
    return __checked_selected_helper($readonly_value, $current, $display, 'readonly');
}

function human_time_diff($from, $to = 0)
{
    $to = (int) $to === 0 ? time() : (int) $to;
    $diff = abs($to - (int) $from);
    if ($diff < MINUTE_IN_SECONDS) {
        $secs = max($diff, 1);
        $since = sprintf(_n('%s second', '%s seconds', $secs), $secs);
    } elseif ($diff < HOUR_IN_SECONDS) {
        $mins = max((int) round($diff / MINUTE_IN_SECONDS), 1);
        $since = sprintf(_n('%s minute', '%s minutes', $mins), $mins);
    } elseif ($diff < DAY_IN_SECONDS) {
        $hours = max((int) round($diff / HOUR_IN_SECONDS), 1);
        $since = sprintf(_n('%s hour', '%s hours', $hours), $hours);
    } elseif ($diff < WEEK_IN_SECONDS) {
        $days = max((int) round($diff / DAY_IN_SECONDS), 1);
        $since = sprintf(_n('%s day', '%s days', $days), $days);
    } elseif ($diff < MONTH_IN_SECONDS) {
        $weeks = max((int) round($diff / WEEK_IN_SECONDS), 1);
        $since = sprintf(_n('%s week', '%s weeks', $weeks), $weeks);
    } elseif ($diff < YEAR_IN_SECONDS) {
        $months = max((int) round($diff / MONTH_IN_SECONDS), 1);
        $since = sprintf(_n('%s month', '%s months', $months), $months);
    } else {
        $years = max((int) round($diff / YEAR_IN_SECONDS), 1);
        $since = sprintf(_n('%s year', '%s years', $years), $years);
    }
    return apply_filters('human_time_diff', $since, $diff, $from, $to);
}

function ent2ncr($text)
{
    return preg_replace_callback('/&([a-zA-Z0-9]+);/', static function (array $m): string {
        $decoded = html_entity_decode($m[0], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return $decoded === $m[0] ? $m[0] : '&#' . mb_ord($decoded, 'UTF-8') . ';';
    }, (string) $text);
}

function convert_chars($content, $deprecated = '')
{
    $content = (string) $content;
    if (!str_contains($content, '&')) {
        return $content;
    }
    return preg_replace('/&(?!(?:#\d+|#[xX][0-9a-fA-F]+|[A-Za-z][A-Za-z0-9]*);)/', '&#038;', $content);
}

function convert_smilies($text)
{
    return $text;
}

function capital_P_dangit($text)
{
    return $text;
}

function wp_kses_stripslashes($content)
{
    return preg_replace('%\\\\"%', '"', (string) $content);
}

function force_balance_tags($text)
{
    return $text;
}

function balanceTags($text, $force = false)
{
    return $text;
}

function format_to_edit($content, $rich_text = false)
{
    return $rich_text ? $content : esc_textarea($content);
}

function wp_sprintf($pattern, ...$args)
{
    if (str_starts_with((string) $pattern, '%l') && isset($args[0]) && is_array($args[0])) {
        return wp_sprintf_l($pattern, $args[0]);
    }
    return sprintf($pattern, ...$args);
}

function wp_iso_descrambler($subject)
{
    return $subject;
}

function utf8_uri_encode($utf8_string, $length = 0, $encode_ascii_characters = false)
{
    return _minn_utf8_uri_encode((string) $utf8_string, (int) $length);
}

function seems_utf8($str)
{
    return preg_match('/^./us', (string) $str) === 1 || $str === '';
}

function wp_encode_emoji($content)
{
    return $content;
}

function wp_staticize_emoji($text)
{
    return $text;
}

function wp_targeted_link_rel($text)
{
    return $text;
}

function links_add_target($content, $target = '_blank', $tags = ['a'])
{
    return preg_replace('|<(' . implode('|', $tags) . ')([^>]*)>|i', '<$1$2 target="' . esc_attr($target) . '">', (string) $content);
}

function get_url_in_content($content)
{
    if (preg_match('/<a\s[^>]*?href=([\'"])(.+?)\1/is', (string) $content, $m)) {
        return esc_url_raw($m[2]);
    }
    return false;
}

function wp_html_split($input)
{
    return preg_split('/(<[^>]*>)/', (string) $input, -1, PREG_SPLIT_DELIM_CAPTURE);
}

function wp_replace_in_html_tags($haystack, $replace_pairs)
{
    $parts = wp_html_split($haystack);
    foreach ($parts as $i => $part) {
        if ($i % 2 === 1) {
            $parts[$i] = strtr($part, $replace_pairs);
        }
    }
    return implode('', $parts);
}

function wp_parse_str($input_string, &$result)
{
    parse_str((string) $input_string, $result);
    $result = apply_filters('wp_parse_str', $result);
}

/** htmlentities that keeps named references and decimal numeric ones the reader already knows. */
function htmlentities2($text)
{
    $translation = get_html_translation_table(HTML_ENTITIES, ENT_QUOTES);
    $encoded = strtr((string) $text, $translation);
    return (string) preg_replace_callback('/&amp;(#[0-9]+|[a-zA-Z][a-zA-Z0-9]*);/', static function (array $m) use ($translation): string {
        $entity = '&' . $m[1] . ';';
        if ($m[1][0] === '#' || in_array($entity, $translation, true)) {
            return $entity;
        }
        return $m[0];
    }, $encoded);
}
