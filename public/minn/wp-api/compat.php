<?php
/**
 * What newer PHP brings and the reference supplies on older PHP (probe
 * compat): the multibyte fallbacks, the UTF-8 charset check, and the array
 * and number functions of PHP 8.4, 8.5 and later. A function PHP already
 * has is left to PHP.
 */

use Minn\Support\Utf8;

/** Whether a charset name is UTF-8: "utf-8" or "utf8", any case, nothing around it. */
function _is_utf8_charset($charset_slug)
{
    return is_string($charset_slug) && in_array(strtolower($charset_slug), ['utf-8', 'utf8'], true);
}

/** PCRE's /u is always there; the argument that once overrode the answer is deprecated. */
function _wp_can_use_pcre_u($set = null)
{
    if ($set !== null) {
        _deprecated_argument(__FUNCTION__, '6.9.0');
    }
    return true;
}

/** A string's length in characters when the encoding (the site's by default) is UTF-8, a malformed byte counting as one; in bytes otherwise. */
function _mb_strlen($str, $encoding = null)
{
    return _is_utf8_charset($encoding ?? get_option('blog_charset')) ? count(Utf8::characters((string) $str)) : strlen((string) $str);
}

/** Part of a string by characters when the encoding (the site's by default) is UTF-8, by bytes otherwise; start and length as mb_substr takes them. */
function _mb_substr($str, $start, $length = null, $encoding = null)
{
    if (!_is_utf8_charset($encoding ?? get_option('blog_charset'))) {
        return $length === null ? substr((string) $str, (int) $start) : substr((string) $str, (int) $start, (int) $length);
    }
    return implode('', array_slice(Utf8::characters((string) $str), (int) $start, $length === null ? null : (int) $length));
}

/** The UTF-8 character for an integer code point; false for anything else, another encoding, a surrogate or one past U+10FFFF. */
function _mb_chr($codepoint, $encoding = null)
{
    if (!is_int($codepoint) || !_is_utf8_charset($encoding ?? get_option('blog_charset'))) {
        return false;
    }
    return Utf8::fromCodePoint($codepoint) ?? false;
}

/** The code point of a string's first UTF-8 character; false for another encoding, an empty string, or a malformed start. */
function _mb_ord($string, $encoding = null)
{
    if (!_is_utf8_charset($encoding ?? get_option('blog_charset'))) {
        return false;
    }
    return Utf8::firstCodePoint((string) $string) ?? false;
}

if (!function_exists('array_find')) :
/** The first value the callback (value, key) accepts, or null. */
function array_find(array $array, callable $callback)
{
    foreach ($array as $key => $value) {
        if ($callback($value, $key)) {
            return $value;
        }
    }
    return null;
}
endif;

if (!function_exists('array_find_key')) :
/** The key of the first value the callback (value, key) accepts, or null. */
function array_find_key(array $array, callable $callback)
{
    foreach ($array as $key => $value) {
        if ($callback($value, $key)) {
            return $key;
        }
    }
    return null;
}
endif;

if (!function_exists('array_any')) :
/** Whether the callback (value, key) accepts any value. */
function array_any(array $array, callable $callback): bool
{
    return array_find_key($array, $callback) !== null;
}
endif;

if (!function_exists('array_all')) :
/** Whether the callback (value, key) accepts every value; true for an empty array. */
function array_all(array $array, callable $callback): bool
{
    foreach ($array as $key => $value) {
        if (!$callback($value, $key)) {
            return false;
        }
    }
    return true;
}
endif;

if (!function_exists('array_first')) :
/** The first value, in the array's order, or null. */
function array_first(array $array): mixed
{
    return $array === [] ? null : $array[array_key_first($array)];
}
endif;

if (!function_exists('array_last')) :
/** The last value, in the array's order, or null. */
function array_last(array $array): mixed
{
    return $array === [] ? null : $array[array_key_last($array)];
}
endif;

if (!function_exists('clamp')) :
/** A value held between a minimum and a maximum, compared as PHP compares; a NAN bound, or a minimum above the maximum, is a ValueError. */
function clamp(mixed $value, mixed $min, mixed $max): mixed
{
    if (is_float($min) && is_nan($min)) {
        throw new ValueError('clamp(): Argument #2 ($min) must not be NAN');
    }
    if (is_float($max) && is_nan($max)) {
        throw new ValueError('clamp(): Argument #3 ($max) must not be NAN');
    }
    if ($min > $max) {
        throw new ValueError('clamp(): Argument #2 ($min) must be smaller than or equal to argument #3 ($max)');
    }
    return $value < $min ? $min : ($value > $max ? $max : $value);
}
endif;
