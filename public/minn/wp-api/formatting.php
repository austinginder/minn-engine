<?php
/** Escaping, sanitising, and the small string helpers. Behaviour from contracts/fixtures/api/functions.json. */

use Minn\Content\Autop;
use Minn\Content\Blocks;
use Minn\Content\Slug;
use Minn\Content\Texturize;
use Minn\Json;
use Minn\Runtime\Handoff;
use Minn\Runtime\QueryArgs;
use Minn\Runtime\Runtime;
use Minn\Runtime\SiteUrls;
use Minn\Support\Accents;
use Minn\Support\Email;
use Minn\Support\Entities;
use Minn\Support\Escape;
use Minn\Html;
use Minn\Support\KsesEntities;
use Minn\Support\Kses;
use Minn\Support\Lists;
use Minn\Support\Paths;
use Minn\Support\Slashes;
use Minn\Support\Time;
use Minn\Support\Url;
use Minn\Support\Sanitize;
use Minn\Support\Text;

function wp_check_invalid_utf8($text, $strip = false)
{
    $text = (string) $text;
    $valid = Escape::validUtf8($text);
    // Stripping replaces each bad sequence with U+FFFD, as the reference does now (probe formatting-remaining).
    return $valid === '' && $text !== '' && $strip ? wp_scrub_utf8($text) : $valid;
}

function _wp_specialchars($text, $quote_style = ENT_NOQUOTES, $charset = false, $double_encode = false)
{
    $text = (string) $text;
    if ($double_encode) {
        return Entities::specialchars($text, $quote_style, true, KsesEntities::known(...));
    }
    return Escape::specialchars($text, $quote_style);
}

function esc_html($text)
{
    return Escape::html($text);
}

function esc_attr($text)
{
    return Escape::attr($text);
}

function esc_textarea($text)
{
    return Escape::textarea($text);
}

function esc_js($text)
{
    return Escape::js($text);
}

function esc_url($url, $protocols = null, $_context = 'display')
{
    return Escape::url($url, $protocols, $_context);
}

function esc_url_raw($url, $protocols = null)
{
    return sanitize_url($url, $protocols);
}

function sanitize_url($url, $protocols = null)
{
    return Escape::url($url, $protocols, 'db');
}

function esc_sql($data)
{
    if (is_array($data)) {
        foreach ($data as $k => $v) {
            $data[$k] = esc_sql($v);
        }
        return $data;
    }
    // Through the database object plugins query with, so a literal % comes back as its placeholder escape.
    global $wpdb;
    return $wpdb instanceof wpdb ? $wpdb->_escape((string) $data) : Runtime::current()->db->escape((string) $data);
}

// The engine escapes through Minn\Runtime\Handoff, so a database drop-in's object escapes for it.
Handoff::bind(['escSql' => static fn ($data) => esc_sql($data)]);

function wp_pre_kses_less_than($content)
{
    return Kses::lessThan((string) $content, static fn (string $text): string => esc_html($text));
}

function _sanitize_text_fields($str, $keep_newlines = false)
{
    if (is_object($str) || is_array($str)) {
        return '';
    }
    return Html::textField((string) $str, (bool) $keep_newlines, static fn (string $t) => wp_check_invalid_utf8($t), static fn (string $t) => wp_pre_kses_less_than($t));
}

function sanitize_text_field($str)
{
    return Sanitize::textField($str);
}

function sanitize_textarea_field($str)
{
    return Sanitize::textareaField($str);
}

function wp_strip_all_tags($text, $remove_breaks = false)
{
    return is_scalar($text) ? Html::stripAllTags((string) $text, (bool) $remove_breaks) : '';
}

function trailingslashit($value)
{
    return Minn\Support\Url::withTrailingSlash((string) $value);
}

function untrailingslashit($value)
{
    return Minn\Support\Url::withoutTrailingSlash((string) $value);
}

function wp_parse_args($args, $defaults = [])
{
    return Minn\Support\Lists::args($args, $defaults);
}

/** A string split on commas and whitespace; an array comes back as given (probe functions-remaining). */
function wp_parse_list($input_list)
{
    return is_array($input_list) ? $input_list : Lists::items($input_list);
}

/** Whole numbers made positive, each once, keeping the keys of their first appearance (probe functions-remaining). */
function wp_parse_id_list($input_list)
{
    return array_unique(array_map('absint', wp_parse_list($input_list)));
}

function wp_parse_slug_list($input_list)
{
    return array_values(array_unique(array_map('sanitize_title', wp_parse_list($input_list))));
}

function wp_json_encode($value, $flags = 0, $depth = 512)
{
    try {
        return Json::encode($value, (int) $flags, (int) $depth);
    } catch (JsonException $e) {
        // Asked to throw, the reference lets json_encode's own exception through.
        if (((int) $flags & JSON_THROW_ON_ERROR) !== 0) {
            throw $e;
        }
        return false;
    }
}

/** A string made valid UTF-8 for encoding: each invalid byte becomes "?" (probe functions-utils). */
function _wp_json_convert_string($input_string)
{
    return is_string($input_string) ? Json::repair($input_string) : $input_string;
}

/** A value with every string and key made valid UTF-8; nesting deeper than $depth throws "Reached depth limit". */
function _wp_json_sanity_check($value, $depth)
{
    return Json::repair($value, (int) $depth);
}

function _wp_json_prepare_data($value)
{
    _deprecated_function(__FUNCTION__, '5.3.0');
    return $value;
}

function sanitize_key($key)
{
    return Sanitize::key($key);
}

function sanitize_html_class($classname, $fallback = '')
{
    return Sanitize::htmlClass($classname, $fallback);
}

function remove_accents($text, $locale = '')
{
    return Accents::strip((string) $text, (string) ($locale !== '' ? $locale : get_locale()));
}

function sanitize_title($title, $fallback_title = '', $context = 'save')
{
    return Sanitize::title($title, $fallback_title, $context);
}

function sanitize_title_with_dashes($title, $raw_title = '', $context = 'display')
{
    return Slug::dashes((string) $title, $context === 'save', static fn (string $text, int $length) => utf8_uri_encode($text, $length));
}

/**
 * A file name safe to store, as the reference cleans one (probe
 * upload-filters): accents transliterated, the special characters out,
 * spaces and runs of dashes as one dash, no dots or dashes at the ends; an
 * inner part that looks like an extension but is no type the site allows
 * gets an underscore (x.php.png is x.php_.png); a name that is only an
 * extension becomes unnamed-file.{ext}; sanitize_file_name has the last word.
 */
function sanitize_file_name($filename)
{
    $raw = (string) $filename;
    $special = ['?', '[', ']', '/', '\\', '=', '<', '>', ':', ';', ',', "'", '"', '&', '$', '#', '*', '(', ')', '|', '~', '`', '!', '{', '}', '%', '+', '’', '«', '»', '”', '“', chr(0)];
    $special = apply_filters('sanitize_file_name_chars', $special, $raw);
    $filename = remove_accents(str_replace("\x00", '', $raw));
    $filename = (string) preg_replace("#\x{00a0}#siu", ' ', $filename);
    $filename = str_replace($special, '', $filename);
    $filename = str_replace(['%20', '+'], '-', $filename);
    $filename = (string) preg_replace('/\.{2,}/', '.', $filename);
    $filename = (string) preg_replace('/[\r\n\t -]+/', '-', $filename);
    $filename = trim($filename, '.-_');
    $filename = _minn_neutralize_inner_extensions($filename);
    if (!str_contains($filename, '.')) {
        $named = wp_check_filetype('test.' . $filename, _minn_mime_table()['mime']);
        if ($named['ext'] !== false && $named['ext'] === $filename) {
            $filename = 'unnamed-file.' . $named['ext'];
        }
    }
    return apply_filters('sanitize_file_name', $filename, $raw);
}

/** @internal an inner part of a file name that looks like an extension (two to five letters, maybe a digit) and is no allowed type gets an underscore */
function _minn_neutralize_inner_extensions(string $filename): string
{
    $parts = explode('.', $filename);
    if (count($parts) <= 2) {
        return $filename;
    }
    $first = array_shift($parts);
    $last = array_pop($parts);
    $allowed = array_keys(get_allowed_mime_types());
    foreach ($parts as $i => $part) {
        $known = array_filter($allowed, static fn (string $pattern): bool => preg_match('!^(' . $pattern . ')$!i', $part) === 1) !== [];
        $parts[$i] = preg_match('/^[a-zA-Z]{2,5}\d?$/', $part) === 1 && !$known ? $part . '_' : $part;
    }
    return implode('.', [$first, ...$parts, $last]);
}

function sanitize_email($email)
{
    return Sanitize::email($email);
}

function is_email($email, $deprecated = false)
{
    return Sanitize::isEmail($email);
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
    return Slashes::strip($value);
}

function wp_slash($value)
{
    return Slashes::add($value);
}

function addslashes_strings_only($value)
{
    return is_string($value) ? addslashes($value) : $value;
}

function map_deep($value, $callback)
{
    return Text::mapDeep($value, $callback);
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
    return Entities::decode((string) $text, is_int($quote_style) || is_string($quote_style) ? $quote_style : ENT_NOQUOTES);
}

function zeroise($number, $threshold)
{
    return sprintf('%0' . (int) $threshold . 's', $number);
}

function wp_basename($path, $suffix = '')
{
    return Text::basename($path, (string) $suffix);
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
    return Text::htmlExcerpt($str, $count, $more);
}

function wp_trim_words($text, $num_words = 55, $more = null)
{
    return Text::trimWords($text, $num_words, $more);
}

function wptexturize($text, $reset = false)
{
    if ($reset) {
        Texturize::askAgain();
    }
    // Empty text ("0" and null too) comes back as given and asks nothing; texturizing off leaves the text as written (probe filter-inputs-3).
    if (empty($text) || !Texturize::enabled()) {
        return $text;
    }
    $skip = Texturize::skipPattern();
    return Texturize::entities(Texturize::html((string) $text, $skip), $skip);
}

function wpautop($text, $br = true)
{
    return Autop::apply((string) $text, (bool) $br);
}

/** Bare URLs, www. and ftp. hosts and email addresses in text made links, outside tags and code, pre, script and style (probe formatting-callbacks). */
function make_clickable($text)
{
    return Minn\Content\Clickable::make(
        (string) $text,
        static fn (array $m): string => _make_url_clickable_cb($m),
        static fn (array $m): string => _make_web_ftp_clickable_cb($m),
        static fn (array $m): string => _make_email_clickable_cb($m),
    );
}

function wp_normalize_path($path)
{
    return Paths::normalize((string) $path);
}

function wp_parse_url($url, $component = -1)
{
    return Url::parse((string) $url, (int) $component);
}

/** One part of a parsed URL (its key found as an array key would be, so "1" and true name the host), all of it for -1, null for no part. */
function _get_component_from_parsed_url_array($url_parts, $component = -1)
{
    if ($component === -1) {
        return $url_parts;
    }
    $key = _wp_translate_php_url_constant_to_key($component);
    return $key === false ? null : ($url_parts[$key] ?? null);
}

function set_url_scheme($url, $scheme = null)
{
    return SiteUrls::withScheme($url, $scheme);
}

/** Whether the admin is forced onto HTTPS; a value given is kept for the request and the previous one returned (probe functions-remaining). */
function force_ssl_admin($force = null)
{
    return SiteUrls::forceSslAdmin($force);
}

function add_query_arg(...$args)
{
    return QueryArgs::add(...$args);
}

/** @internal keys and values are left as given (probe query-args) */
function _minn_build_query(array $data, string $prefix = ''): string
{
    return Url::buildQuery($data, $prefix);
}

function remove_query_arg($key, $query = false)
{
    return QueryArgs::remove($key, $query);
}

function wp_list_pluck($input_list, $field, $index_key = null)
{
    return \Minn\Support\Lists::pluck((array) $input_list, $field, $index_key);
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
    $util = new WP_List_Util($input_list);
    $filtered = $util->filter($args, $operator);
    return $field ? $util->pluck($field) : $filtered;
}

function wp_list_sort($input_list, $orderby = [], $order = 'ASC', $preserve_keys = false)
{
    return is_array($input_list) ? (new WP_List_Util($input_list))->sort($orderby, $order, $preserve_keys) : [];
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

function __checked_selected_helper($helper, $current, $display, $type)
{
    $result = Minn\Html::chosen($helper, $current, (string) $type);
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
    [$count, $unit] = Time::span($diff);
    $since = sprintf(_n("%s {$unit}", "%s {$unit}s", $count), $count);
    return apply_filters('human_time_diff', $since, $diff, $from, $to);
}

function ent2ncr($text)
{
    return Text::numericReferences($text);
}

function convert_chars($content, $deprecated = '')
{
    return Text::encodeAmpersands($content);
}

function convert_smilies($text)
{
    if (!get_option('use_smilies') || (string) $text === '') {
        return $text;
    }
    return Minn\Content\TextFilters::smilies((string) $text, _minn_smilies(), static fn (string $file, string $code): string => translate_smiley([$code]));
}

/** @internal The smilies table, from the reference's own (data/smilies.json), through the smilies filter. */
function _minn_smilies(): array
{
    // smilies_init's table once it has run (init, 5), as the reference reads it; else the defaults, filtered.
    if (isset($GLOBALS['wpsmiliestrans']) && is_array($GLOBALS['wpsmiliestrans'])) {
        return $GLOBALS['wpsmiliestrans'];
    }
    return (array) apply_filters('smilies', _minn_smilies_defaults());
}

/** @internal the default smilies table (data/smilies.json): code => emoji or image file, in the stored order */
function _minn_smilies_defaults(): array
{
    static $table = null;
    return $table ??= json_decode((string) file_get_contents(MINN_ENGINE_DIR . '/data/smilies.json'), true) ?: [];
}

/** Builds $wpsmiliestrans (filtered through smilies, reverse-sorted) and its search pattern $wp_smiliessearch, when smilies are on. */
function smilies_init()
{
    global $wpsmiliestrans, $wp_smiliessearch;
    if (!get_option('use_smilies')) {
        return;
    }
    $wpsmiliestrans = (array) apply_filters('smilies', is_array($wpsmiliestrans) ? $wpsmiliestrans : _minn_smilies_defaults());
    if ($wpsmiliestrans === []) {
        return;
    }
    krsort($wpsmiliestrans);
    $wp_smiliessearch = Minn\Content\TextFilters::smiliesSearch(array_map('strval', array_keys($wpsmiliestrans)));
}

function capital_P_dangit($text)
{
    // A title is set right everywhere; elsewhere only where the word plainly starts.
    return current_filter() === 'the_title' ? Minn\Content\TextFilters::capitalPEverywhere((string) $text) : Minn\Content\TextFilters::capitalP((string) $text);
}

function wp_kses_stripslashes($content)
{
    return preg_replace('%\\\\"%', '"', (string) $content);
}

function force_balance_tags($text)
{
    return Minn\Content\TagBalancer::balance((string) $text);
}

/** Tags balanced when forced or when the use_balanceTags setting is on; as given otherwise. */
function balanceTags($text, $force = false)
{
    return $force || (int) get_option('use_balanceTags') === 1 ? force_balance_tags($text) : $text;
}

function format_to_edit($content, $rich_text = false)
{
    return Minn\Runtime\PostFields::formatToEdit($content, $rich_text);
}

/**
 * sprintf() a fragment at a time, as the reference formats (probe
 * filter-inputs-3): the text before the first % as it is, %% as a %, and
 * each fragment from one % to the next handed to wp_sprintf with its
 * argument (by position with %1$s, else the next one); a fragment the
 * filter changed is used as it came back (wp_sprintf_l makes %l a list),
 * one it left alone is formatted with its argument.
 */
function wp_sprintf($pattern, ...$args)
{
    $pattern = (string) $pattern;
    $at = strpos($pattern, '%');
    if ($at === false) {
        return $pattern;
    }
    $out = substr($pattern, 0, $at);
    $length = strlen($pattern);
    $next = 0;
    while ($at < $length) {
        if (substr($pattern, $at, 2) === '%%') {
            $out .= '%';
            $at += 2;
            continue;
        }
        $end = strpos($pattern, '%', $at + 1);
        $fragment = $end === false ? substr($pattern, $at) : substr($pattern, $at, $end - $at);
        $at = $end === false ? $length : $end;
        if (preg_match('/^%(\d+)\$/', $fragment, $position) === 1) {
            $value = $args[(int) $position[1] - 1] ?? '';
            $fragment = '%' . substr($fragment, strlen($position[0]));
        } else {
            $value = $args[$next++] ?? '';
        }
        $filtered = apply_filters('wp_sprintf', $fragment, $value);
        if ($filtered !== $fragment) {
            $out .= $filtered;
            continue;
        }
        try {
            $out .= sprintf($fragment, is_array($value) ? '' : $value);
        } catch (\ValueError) {
            $out .= $fragment;
        }
    }
    return $out;
}

/** A mail subject's quoted-printable encoded word decoded to its bytes (underscores as spaces), in the subject's own charset. */
function wp_iso_descrambler($subject)
{
    if (!preg_match('#\=\?(.+)\?Q\?(.+)\?\=#i', (string) $subject, $m)) {
        return $subject;
    }
    return preg_replace_callback('#\=([0-9a-f]{2})#i', '_wp_iso_convert', str_replace('_', ' ', $m[2]));
}

/** One =XX escape as its byte. */
function _wp_iso_convert($matches)
{
    return chr((int) hexdec(strtolower($matches[1])));
}

/** Non-ASCII as lower-case percent escapes, whole characters up to $length bytes; ASCII too (as rawurlencode does) when asked. */
function utf8_uri_encode($utf8_string, $length = 0, $encode_ascii_characters = false)
{
    return Slug::uriEncode((string) $utf8_string, (int) $length, static fn (string $char): string => $encode_ascii_characters ? rawurlencode($char) : $char);
}

function seems_utf8($str)
{
    _deprecated_function(__FUNCTION__, '6.9.0', 'wp_is_valid_utf8()');
    return preg_match('/^./us', (string) $str) === 1 || $str === '';
}

function wp_encode_emoji($content)
{
    return Minn\Content\Emoji::encode((string) $content);
}

function wp_staticize_emoji($text)
{
    // WordPress's emoji images, served by the Minn update service (feeds and mail point readers here, never at wordpress.org).
    return Minn\Content\Emoji::staticize((string) $text, (string) apply_filters('emoji_url', Minn\Ops\Directory::url('assets/s/images/core/emoji/17.0.2/72x72/')), (string) apply_filters('emoji_ext', '.png'));
}

/** The reference's emoji list: every sequence as entities, longest first, or the single code points they are made of. */
function _wp_emoji_list($type = 'entities')
{
    static $list = null;
    $list ??= json_decode((string) file_get_contents(MINN_ENGINE_DIR . '/data/emoji.json'), true) ?: ['entities' => [], 'partials' => []];
    return $list[$type === 'partials' ? 'partials' : 'entities'];
}

/** A feed carries an embedded post's iframe without the style that hides it until its script runs. */
function _oembed_filter_feed_content($content)
{
    return Minn\Content\TextFilters::feedEmbeds((string) $content);
}

/** Deprecated since 6.7: links that open a new window (a target) gain rel="noopener" (wp_targeted_link_rel may change it), outside script and style. */
function wp_targeted_link_rel($text)
{
    _deprecated_function(__FUNCTION__, '6.7.0');
    if (stripos((string) $text, 'target') === false || stripos((string) $text, '<a ') === false || is_serialized($text)) {
        return $text;
    }
    return preg_replace_callback('/<(script|style)\b.*?<\/\1>|<a\s([^>]*target\s*=[^>]*)>/si', static fn (array $m) => ($m[2] ?? '') !== '' ? wp_targeted_link_rel_callback([$m[0], $m[2]]) : $m[0], (string) $text);
}

/**
 * One link with the rel values merged in when it has a target (probe
 * formatting-remaining): the tag is written again from its attributes, in
 * their order, double-quoted and single-spaced, rel where it was or last;
 * a link with no target comes back as it was.
 */
function wp_targeted_link_rel_callback($matches)
{
    _deprecated_function(__FUNCTION__, '6.7.0');
    $link = $matches[1];
    $escaped = !preg_match('/(^|[^\\\\])[\'"]/', $link);
    $atts = wp_kses_hair($escaped ? preg_replace('/\\\\([\'"])/', '$1', $link) : $link, wp_allowed_protocols());
    $rel = apply_filters('wp_targeted_link_rel', 'noopener', $link);
    if (!$rel || !isset($atts['target'])) {
        return "<a {$link}>";
    }
    $atts['rel'] = ['name' => 'rel', 'value' => implode(' ', array_unique(preg_split('/\s/', ($atts['rel']['value'] ?? '') . " {$rel}", -1, PREG_SPLIT_NO_EMPTY))), 'vless' => 'n'];
    $quote = $escaped ? '\\"' : '"';
    $written = '';
    foreach ($atts as $name => $attribute) {
        $written .= $attribute['vless'] === 'y' ? " {$name}" : " {$name}={$quote}{$attribute['value']}{$quote}";
    }
    return "<a{$written}>";
}

/** Every listed tag given a target, replacing any it had (probe formatting-callbacks). */
function links_add_target($content, $target = '_blank', $tags = ['a'])
{
    global $_links_add_target;
    $_links_add_target = $target;
    return preg_replace_callback('!<(' . implode('|', array_map(static fn ($tag): string => preg_quote((string) $tag, '!'), (array) $tags)) . ')((\s[^>]*)?)>!i', '_links_add_target', (string) $content);
}

function get_url_in_content($content)
{
    if (preg_match('/<a\s[^>]*?href\s*=\s*([\'"])(.+?)\1/is', (string) $content, $m)) {
        return esc_url_raw($m[2]);
    }
    return false;
}

function wp_html_split($input)
{
    return Minn\Html::split((string) $input);
}

/** The pattern the reference cuts markup by, as it returns it (probe formatting-remaining); the engine's own split matches it. */
function get_html_split_regex()
{
    return "/(<(?(?=!--|!\\[CDATA\\[)(?(?=!-)!(?:-(?!->)[^\\-]*+)*+(?:-->)?|!\\[CDATA\\[[^\\]]*+(?:](?!]>)[^\\]]*+)*+(?:]]>)?)|[^>]*>?))/";
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

/** Hides an address from harvesters as numeric entities; hex_encoding 1 mixes hex in, 0 mixes plain characters in. */
function antispambot($email_address, $hex_encoding = 0)
{
    $out = '';
    foreach (mb_str_split((string) $email_address) as $char) {
        $pick = random_int(0, 1 + (int) (bool) $hex_encoding);
        $code = mb_ord($char);
        if ($pick === 0 && $hex_encoding == 0) {
            $out .= $char;
        } elseif ($pick === 2) {
            $out .= '&#x' . dechex($code) . ';';
        } else {
            $out .= '&#' . $code . ';';
        }
    }
    return str_replace('@', '&#64;', $out);
}

/** Seconds east of UTC for an ISO 8601 zone designator; Z and anything unreadable are 0. */
function iso8601_timezone_to_offset($timezone)
{
    if (!preg_match('/^([+-])(\d{2}):?(\d{2})$/', (string) $timezone, $m)) {
        return 0;
    }
    $seconds = ((int) $m[2] * 3600) + ((int) $m[3] * 60);
    return $m[1] === '-' ? -$seconds : $seconds;
}

/** Unwraps a paragraph that holds nothing but one registered shortcode (with its closing tag). */
function shortcode_unautop($text)
{
    $pattern = Runtime::shortcodes()->pattern();
    if ($pattern === null) {
        return $text;
    }
    $tags = implode('|', array_map('preg_quote', Runtime::shortcodes()->names()));
    $open = '\[(' . $tags . ')(?![\w-])[^\]\/]*(?:\/(?!\])[^\]\/]*)*?(?:\/\]|\](?:[^\[]*+(?:\[(?!\/\2\])[^\[]*+)*+\[\/\2\])?)';
    return (string) preg_replace('/<p>\s*+(' . $open . ')\s*+<\/p>/s', '$1', (string) $text);
}

function sanitize_title_for_query($title)
{
    return Sanitize::title($title, '', 'query');
}

/** The value of a file header line, cut at the end of its comment. */
function _cleanup_header_comment($str)
{
    return trim((string) preg_replace('/\s*(?:\*\/|\?>).*/', '', (string) $str));
}

/** Keeps case, drops anything outside the probed set: letters, digits, dash, dot, plus, star, slash. */
function sanitize_mime_type($mime_type)
{
    $sanitized = (string) preg_replace('#[^-+*.a-zA-Z0-9/]#', '', (string) $mime_type);
    return apply_filters('sanitize_mime_type', $sanitized, $mime_type);
}

/** Deprecated LIKE escape: backslash ahead of % and _ only, backslashes left alone (probed). */
function like_escape($text)
{
    _deprecated_function(__FUNCTION__, '4.0.0', 'wpdb::esc_like()');
    return str_replace(['%', '_'], ['\\%', '\\_'], (string) $text);
}

/** Letters, digits, underscores, and dashes survive (probed: bad locale!<x> becomes badlocalex). */
function sanitize_locale_name($locale_name)
{
    $sanitized = (string) preg_replace('/[^A-Za-z0-9_-]/', '', (string) $locale_name);
    return apply_filters('sanitize_locale_name', $sanitized, $locale_name);
}

function wp_slash_strings_only($value)
{
    if (is_array($value)) {
        return array_map('wp_slash_strings_only', $value);
    }
    return is_string($value) ? addslashes($value) : $value;
}

function wp_privacy_anonymize_ip($ip_addr, $ipv6_fallback = false)
{
    return Minn\Support\Ip::anonymize((string) $ip_addr);
}

function wp_maybe_decline_date($date, $format = '')
{
    // Declension applies to the Slavic locales only; en_US never changes.
    return apply_filters('wp_maybe_decline_date', (string) $date, (string) $format);
}

/**
 * An excerpt made from the content when none was written (probe excerpt):
 * the content as the loop gives it, without shortcodes, blocks an excerpt
 * leaves out, or footnotes, through the_content (block rendering and image
 * tags aside), cut to excerpt_length words with excerpt_more; then
 * wp_trim_excerpt either way.
 */
function wp_trim_excerpt($text = '', $post = null)
{
    $raw = (string) $text;
    $text = $raw;
    if (trim($text) === '') {
        $post = get_post($post);
        // No image the excerpt renders counts toward the page's loading budget, as the reference fits none here.
        $text = Minn\Blocks\RenderState::current()->aside(static function () use ($post): string {
            $text = excerpt_remove_footnotes(excerpt_remove_blocks(strip_shortcodes(get_the_content('', false, $post))));
            $images = remove_filter('the_content', 'wp_filter_content_tags', 12);
            $blocks = remove_filter('the_content', 'do_blocks', 9);
            $text = str_replace(']]>', ']]&gt;', (string) apply_filters('the_content', $text));
            $blocks && add_filter('the_content', 'do_blocks', 9);
            $images && add_filter('the_content', 'wp_filter_content_tags', 12);
            return $text;
        });
        $text = wp_trim_words($text, (int) apply_filters('excerpt_length', 55), apply_filters('excerpt_more', ' [&hellip;]'));
    }
    return apply_filters('wp_trim_excerpt', $text, $raw);
}

/** The default on excerpt_more: inside an embed the more text becomes a link to the post; anywhere else it stays. */
function wp_embed_excerpt_more($more_string)
{
    if (!is_embed()) {
        return $more_string;
    }
    $link = sprintf('<a href="%1$s" class="wp-embed-more" target="_top">%2$s</a>', esc_url(get_permalink()), sprintf('Continue reading %s', '<span class="screen-reader-text">' . get_the_title() . '</span>'));
    return ' &hellip; ' . $link;
}

function format_for_editor($text, $default_editor = null)
{
    $text = (string) $text;
    if ($text !== '') {
        $text = htmlspecialchars($text, ENT_NOQUOTES, get_option('blog_charset'));
    }
    return apply_filters('format_for_editor', $text, $default_editor);
}

function wp_privacy_anonymize_data($type, $data = '')
{
    $anonymous = match ($type) {
        'email' => 'deleted@site.invalid',
        'url' => 'https://site.invalid',
        'ip' => wp_privacy_anonymize_ip($data),
        'date' => '0000-00-00 00:00:00',
        'text' => '[deleted]',
        'longtext' => 'This content was deleted by the author.',
        default => '',
    };
    return apply_filters('wp_privacy_anonymize_data', $anonymous, $type, $data);
}

function convert_invalid_entities($content)
{
    return Entities::convertInvalid((string) $content);
}

function wp_is_valid_utf8($bytes)
{
    return Minn\Support\Utf8::isValid((string) $bytes);
}

// UTF-8 helpers and the fallbacks they rest on (probe utf8).

/** The text with each malformed run replaced by U+FFFD. */
function wp_scrub_utf8($text)
{
    return Minn\Support\Utf8::scrub((string) $text);
}

/** Whether the text holds a noncharacter (U+FDD0 to U+FDEF, or a plane's last two), malformed bytes stepped over. */
function wp_has_noncharacters($text)
{
    return Minn\Support\Utf8::hasNoncharacters((string) $text);
}

/** wp_is_valid_utf8 without mbstring. */
function _wp_is_valid_utf8_fallback($bytes)
{
    return Minn\Support\Utf8::isValid((string) $bytes);
}

/** wp_scrub_utf8 without mbstring. */
function _wp_scrub_utf8_fallback($bytes)
{
    return Minn\Support\Utf8::scrub((string) $bytes);
}

/** wp_has_noncharacters without mbstring; deprecated since 7.1.0. */
function _wp_has_noncharacters_fallback($text)
{
    _deprecated_function(__FUNCTION__, '7.1.0');
    return Minn\Support\Utf8::hasNoncharacters((string) $text);
}

/**
 * Well-formed characters read from $at (Support\Utf8::scan): $at left where
 * it stopped, $invalid_length the malformed run there, and
 * $has_noncharacters false, then 0 or 1 once a sequence that could hold a
 * noncharacter was read. The count read.
 */
function _wp_scan_utf8($bytes, &$at, &$invalid_length, $max_bytes = null, $max_code_points = null, &$has_noncharacters = null)
{
    [$found, $at, $invalid_length, $noncharacter] = Minn\Support\Utf8::scan((string) $bytes, (int) $at, $max_bytes, $max_code_points);
    $has_noncharacters = $noncharacter === null ? false : (int) $noncharacter;
    return $found;
}

/** The characters in up to $max_byte_length bytes from $byte_offset, each malformed run counting once. */
function _wp_utf8_codepoint_count($text, $byte_offset = 0, $max_byte_length = PHP_INT_MAX)
{
    $text = (string) $text;
    $offset = (int) $byte_offset;
    $length = min((int) $max_byte_length, max(0, strlen($text) - $offset));
    return count(Minn\Support\Utf8::characters(substr($text, $offset, $length)));
}

/** How many bytes from $byte_offset the first $max_code_points characters take; $found_code_points how many there were. */
function _wp_utf8_codepoint_span($text, $byte_offset, $max_code_points, &$found_code_points = 0)
{
    [$span, $found_code_points] = Minn\Support\Utf8::span((string) $text, (int) $byte_offset, (int) $max_code_points);
    return $span;
}

/** UTF-8 as ISO-8859-1, "?" for what it cannot hold. */
function _wp_utf8_decode_fallback($utf8_text)
{
    return Minn\Support\Utf8::toLatin1((string) $utf8_text);
}

/** ISO-8859-1 as UTF-8. */
function _wp_utf8_encode_fallback($iso_8859_1_text)
{
    return Minn\Support\Utf8::fromLatin1((string) $iso_8859_1_text);
}

function sanitize_user($username, $strict = false)
{
    return Sanitize::user($username, $strict);
}

/** An ORDER BY that is only column names, each with an optional ASC or DESC, or RAND(); anything else is false. */
function sanitize_sql_orderby($orderby)
{
    $orderby = (string) $orderby;
    $column = '\s*(`\w+`|\w+)(\s+(ASC|DESC))?\s*';
    if (preg_match("/^{$column}(,{$column})*$/i", $orderby) === 1 || preg_match('/^\s*RAND\(\s*\)\s*$/i', $orderby) === 1) {
        return $orderby;
    }
    return false;
}

/** A comment's links marked user-generated (Content\TextFilters::rel); a link to the site itself is ugc alone. */
function wp_rel_ugc($text)
{
    return \Minn\Content\TextFilters::rel((string) $text, 'nofollow ugc', static fn (string $href): bool => wp_is_internal_link($href));
}

/** The links in slashed text marked nofollow, except those to the site itself (Content\TextFilters::rel). */
function wp_rel_nofollow($text)
{
    return \Minn\Content\TextFilters::rel((string) $text, 'nofollow', static fn (string $href): bool => wp_is_internal_link($href));
}

/** One link's opening tag, from its matched attributes, with the rel added (Content\TextFilters::linkRel). */
function wp_rel_callback($matches, $rel)
{
    return \Minn\Content\TextFilters::linkRel((string) $matches[1], (string) $rel, static fn (string $href): bool => wp_is_internal_link($href));
}

/** wp_rel_callback with nofollow. */
function wp_rel_nofollow_callback($matches)
{
    return wp_rel_callback($matches, 'nofollow');
}

/** Whether a link points at one of the site's own hosts: a known scheme and a host from wp_internal_hosts. */
function wp_is_internal_link($link)
{
    $link = strtolower((string) $link);
    return in_array(wp_parse_url($link, PHP_URL_SCHEME), wp_allowed_protocols(), true) && in_array(wp_parse_url($link, PHP_URL_HOST), wp_internal_hosts(), true);
}

/** The hosts the site's own links are on: its home's, through wp_internal_hosts, lower-cased and once each. */
function wp_internal_hosts()
{
    return array_values(array_unique(array_map('strtolower', (array) apply_filters('wp_internal_hosts', [wp_parse_url(home_url(), PHP_URL_HOST)]))));
}

/** Deprecated since 6.7: the save filters no longer add rel to targeted links. */
function wp_init_targeted_link_rel_filters()
{
    _deprecated_function(__FUNCTION__, '6.7.0');
}

/** Deprecated since 6.7: there are no targeted-link rel filters to remove. */
function wp_remove_targeted_link_rel_filters()
{
    _deprecated_function(__FUNCTION__, '6.7.0');
}

/** A span in a comment keeps no class, so a note's mention cannot be faked (Minn\Content\TextFilters::noteMentionClasses). */
function _wp_kses_sanitize_note_mention_classes($content)
{
    return \Minn\Content\TextFilters::noteMentionClasses((string) $content);
}

/** The pattern for whitespace in content (newlines, tabs, spaces, no-break spaces), settled the first time it is asked for. */
function wp_spaces_regexp()
{
    static $spaces = '';
    if ($spaces === '') {
        $spaces = apply_filters('wp_spaces_regexp', '[\r\n\t ]|\xC2\xA0|&nbsp;');
    }
    return $spaces;
}

/** Escapes each letter with a backslash (and doubles one before a leading digit), as a date() format needs. */
function backslashit($value)
{
    if (isset($value[0]) && $value[0] >= '0' && $value[0] <= '9') {
        $value = '\\\\' . $value;
    }
    return addcslashes($value, 'A..Za..z');
}

/** @deprecated 4.3.0 Text for the old plain-text editor: tags and ampersands escaped, quotes left. */
function wp_htmledit_pre($output)
{
    _deprecated_function(__FUNCTION__, '4.3.0', 'format_for_editor()');
    if (!empty($output)) {
        $output = htmlspecialchars($output, ENT_NOQUOTES, get_option('blog_charset'));
    }
    return apply_filters('htmledit_pre', $output);
}

/** @deprecated 4.3.0 Text for the old visual editor: characters converted, paragraphs made, then escaped. */
function wp_richedit_pre($text)
{
    _deprecated_function(__FUNCTION__, '4.3.0', 'format_for_editor()');
    if (empty($text)) {
        return apply_filters('richedit_pre', '');
    }
    return apply_filters('richedit_pre', htmlspecialchars(wpautop(convert_chars($text)), ENT_NOQUOTES, get_option('blog_charset')));
}

/**
 * A string cut into pieces of at most $goal bytes, each ending after
 * whitespace; a word longer than that runs on to the next whitespace. The
 * rest, when it fits, is the last piece.
 */
function _split_str_by_whitespace($text, $goal)
{
    $text = (string) $text;
    $chunks = [];
    while (strlen($text) > $goal) {
        $blanks = strtr($text, "\r\n\t\v\f ", "\0\0\0\0\0\0");
        $at = strrpos(substr($blanks, 0, $goal + 1), "\0");
        $at = $at === false ? strpos($blanks, "\0", $goal + 1) : $at;
        if ($at === false) {
            break;
        }
        $chunks[] = substr($text, 0, $at + 1);
        $text = substr($text, $at + 1);
    }
    return $text === '' ? $chunks : [...$chunks, $text];
}

/** Deprecated since 2.8: esc_html, or _wp_specialchars when given more than the text. */
function wp_specialchars($text, $quote_style = ENT_NOQUOTES, $charset = false, $double_encode = false)
{
    _deprecated_function(__FUNCTION__, '2.8.0', 'esc_html()');
    return func_num_args() > 1 ? _wp_specialchars(...func_get_args()) : esc_html($text);
}

/** The search removed from the subject again and again, until none is left (a removal can join a new match). */
function _deep_replace($search, $subject)
{
    $subject = (string) $subject;
    do {
        $subject = str_replace($search, '', $subject, $count);
    } while ($count > 0);
    return $subject;
}

/** Deprecated since 7.0: wp_slash. */
function addslashes_gpc($gpc)
{
    _deprecated_function(__FUNCTION__, '7.0.0', 'wp_slash()');
    return wp_slash($gpc);
}

/** Deprecated since 2.8: esc_attr. */
function attribute_escape($text)
{
    _deprecated_function(__FUNCTION__, '2.8.0', 'esc_attr()');
    return esc_attr($text);
}

/** Deprecated since 3.0: esc_url (sanitize_url for the database context). */
function clean_url($url, $protocols = null, $context = 'display')
{
    if ($context === 'db') {
        _deprecated_function('clean_url( $context = \'db\' )', '3.0.0', 'sanitize_url()');
    } else {
        _deprecated_function(__FUNCTION__, '3.0.0', 'esc_url()');
    }
    return esc_url($url, $protocols, $context);
}

/** A trackback list kept to its http and https addresses, one a line, through sanitize_trackback_urls. */
function sanitize_trackback_urls($to_ping)
{
    $urls = array_filter(preg_split('/[\r\n\t ]/', trim((string) $to_ping), -1, PREG_SPLIT_NO_EMPTY), static fn (string $url): bool => (bool) preg_match('#^https?://.#i', $url));
    return apply_filters('sanitize_trackback_urls', implode("\n", array_map('sanitize_url', $urls)), $to_ping);
}

/** A paragraph's newlines kept through autop as <WPPreserveNewline /> markers. */
function _autop_newline_preservation_helper($matches)
{
    return str_replace("\n", '<WPPreserveNewline />', $matches[0]);
}

/**
 * The pattern fragment that finds the named shortcodes for texturize.
 *
 * @param string[] $tagnames
 */
function _get_wptexturize_shortcode_regex($tagnames)
{
    return Minn\Content\Texturize::shortcodeTags(array_map('strval', (array) $tagnames));
}

/** The pattern that cuts markup into texturize's pieces: comments whole, tags, and the given shortcodes. */
function _get_wptexturize_split_regex($shortcode_regex = '')
{
    return Minn\Content\Texturize::splitPattern((string) $shortcode_regex);
}

/** Pushes or pops one tag or shortcode on the stack of elements texturize leaves alone. */
function _wptexturize_pushpop_element($text, &$stack, $disabled_elements)
{
    Minn\Content\Texturize::pushPop((string) $text, $stack, array_map('strval', (array) $disabled_elements));
}

/** Each $needle made a prime after a digit, a closing quote where a word ends, and left as it is elsewhere. */
function wptexturize_primes($haystack, $needle, $prime, $open_quote, $close_quote)
{
    return Minn\Content\Texturize::primes((string) $haystack, (string) $needle, (string) $prime, (string) $close_quote);
}

/** One attribute's URL made absolute against the base links_add_base_url set, unless it already names an allowed protocol. */
function _links_add_base($m)
{
    global $_links_add_base;
    $url = (string) $m[3];
    $absolute = preg_match('#^(\w{1,20}):#', $url, $protocol) === 1 && in_array($protocol[1], wp_allowed_protocols(), true);
    return $m[1] . '=' . $m[2] . ($absolute ? $url : WP_Http::make_absolute_url($url, (string) $_links_add_base)) . $m[2];
}

/** One tag given the target links_add_target set, any target it had taken out. */
function _links_add_target($m)
{
    global $_links_add_target;
    $attributes = (string) preg_replace('|( target=([\'"])(.*?)\2)|i', '', (string) $m[2]);
    return '<' . $m[1] . $attributes . ' target="' . esc_attr((string) $_links_add_target) . '">';
}

/** The rel a made link carries: nofollow off the site's host for http and https, ugc in a comment; make_clickable_rel has the last word. */
function _make_clickable_rel_attr($url)
{
    $words = [];
    $scheme = strtolower((string) wp_parse_url((string) $url, PHP_URL_SCHEME));
    $host = strtolower((string) wp_parse_url((string) $url, PHP_URL_HOST));
    if (in_array($scheme, ['http', 'https'], true) && $host !== strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST))) {
        $words[] = 'nofollow';
    }
    if (doing_filter('comment_text')) {
        $words[] = 'ugc';
    }
    $rel = apply_filters('make_clickable_rel', implode(' ', $words), $url);
    return $rel ? ' rel="' . esc_attr($rel) . '"' : '';
}

/** A bare URL made a link, its address cleaned for both href and text, closers it does not open left after it. */
function _make_url_clickable_cb($matches)
{
    [$url, $after] = Minn\Content\Clickable::unbalancedClosers((string) $matches[2]);
    $url = esc_url($url);
    if ($url === '') {
        return $matches[0];
    }
    return $matches[1] . '<a href="' . $url . '"' . _make_clickable_rel_attr($url) . '>' . $url . '</a>' . $after . ($matches[3] ?? '');
}

/** A www. or ftp. host made an http link, a trailing "." or ")" left after it. */
function _make_web_ftp_clickable_cb($matches)
{
    $dest = 'http://' . $matches[2];
    $after = '';
    if (in_array(substr($dest, -1), ['.', ')'], true)) {
        $after = substr($dest, -1);
        $dest = substr($dest, 0, -1);
    }
    $dest = esc_url($dest);
    if ($dest === '') {
        return $matches[0];
    }
    return $matches[1] . '<a href="' . $dest . '"' . _make_clickable_rel_attr($dest) . '>' . $dest . '</a>' . $after . ($matches[3] ?? '');
}

/** An email address made a mailto link. */
function _make_email_clickable_cb($matches)
{
    $email = $matches[2] . '@' . $matches[3];
    return $matches[1] . '<a href="mailto:' . $email . '">' . $email . '</a>';
}

/** One smiley's code made its emoji, or its image for the few that have one. */
function translate_smiley($matches)
{
    $smiley = trim((string) reset($matches));
    $table = _minn_smilies();
    if (!isset($table[$smiley])) {
        return '';
    }
    $file = (string) $table[$smiley];
    if (preg_match('/\.(?:png|gif|jpe?g|svg|webp)$/i', $file) !== 1) {
        return $file;
    }
    $src = apply_filters('smilies_src', includes_url("images/smilies/{$file}"), $file, site_url());
    return sprintf('<img src="%s" alt="%s" class="wp-smiley" style="height: 1em; max-height: 1em;" />', esc_url($src), esc_attr($smiley));
}

/** A URL shortened for display: no scheme, no "www.", no trailing slash, cut to $length with an ellipsis. */
function url_shorten($url, $length = 35)
{
    $short = untrailingslashit(str_replace(['https://', 'http://', 'www.'], '', (string) $url));
    return strlen($short) > $length ? substr($short, 0, $length - 3) . '&hellip;' : $short;
}

/** A "<" run that never reaches a ">" escaped as text; a whole tag left alone. */
function wp_pre_kses_less_than_callback($matches)
{
    return str_contains((string) $matches[0], '>') ? $matches[0] : esc_html($matches[0]);
}

/**
 * An ISO 8601 date and time as "Y-m-d H:i:s": in the site's timezone
 * ("user") or UTC ("gmt"). A time without an offset is read in the site's
 * timezone. False when the string is not one.
 */
function iso8601_to_datetime($date_string, $timezone = 'user')
{
    if (preg_match('#^(\d{4})-?(\d\d)-?(\d\d)T(\d\d):?(\d\d):?(\d\d)(?:\.\d+)?(Z|[+-]\d\d(?::?\d\d)?)?$#i', (string) $date_string, $m) !== 1) {
        return false;
    }
    $offset = $m[7] ?? '';
    $zone = $offset === '' ? wp_timezone() : new DateTimeZone(strtoupper($offset) === 'Z' ? 'UTC' : substr($offset, 0, 3) . ':' . (substr(str_replace(':', '', $offset), 3) ?: '00'));
    $date = date_create_immutable("{$m[1]}-{$m[2]}-{$m[3]} {$m[4]}:{$m[5]}:{$m[6]}", $zone);
    if ($date === false) {
        return false;
    }
    return $date->setTimezone(strtolower((string) $timezone) === 'gmt' ? new DateTimeZone('UTC') : wp_timezone())->format('Y-m-d H:i:s');
}

/** A mail's message with its emoji made images when the mail is HTML (its Content-Type header, through wp_mail_content_type). */
function wp_staticize_emoji_for_email($mail)
{
    if (!isset($mail['message'])) {
        return $mail;
    }
    $type = 'text/plain';
    $headers = $mail['headers'] ?? [];
    foreach (is_array($headers) ? $headers : explode("\n", str_replace("\r\n", "\n", (string) $headers)) as $header) {
        if (str_contains((string) $header, ':')) {
            [$name, $value] = explode(':', trim((string) $header), 2);
            if (strtolower(trim($name)) === 'content-type') {
                $type = trim(explode(';', $value)[0]);
            }
        }
    }
    if (apply_filters('wp_mail_content_type', $type) === 'text/html') {
        $mail['message'] = wp_staticize_emoji($mail['message']);
    }
    return $mail;
}
