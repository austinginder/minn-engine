<?php
/** The shortcode API over Minn\Runtime\Shortcodes. */

use Minn\Runtime\Runtime;

function add_shortcode($tag, $callback)
{
    if ($tag === '' || $tag === null) {
        return;
    }
    if (preg_match('@[<>&/\[\]\x00-\x20=]@', (string) $tag)) {
        return;
    }
    Runtime::shortcodes()->add((string) $tag, $callback);
}

function remove_shortcode($tag)
{
    Runtime::shortcodes()->remove((string) $tag);
}

function remove_all_shortcodes()
{
    Runtime::shortcodes()->removeAll();
}

function shortcode_exists($tag)
{
    return Runtime::shortcodes()->has((string) $tag);
}

function has_shortcode($content, $tag)
{
    if (!str_contains((string) $content, '[') || !shortcode_exists($tag)) {
        return false;
    }
    $pattern = Runtime::shortcodes()->pattern([(string) $tag]);
    return $pattern !== null && preg_match($pattern, (string) $content) === 1;
}

function do_shortcode($content, $ignore_html = false)
{
    return Runtime::shortcodes()->apply((string) $content, $ignore_html ? 'escape' : 'run');
}

function strip_shortcodes($content)
{
    return Runtime::shortcodes()->strip((string) $content);
}

function get_shortcode_regex($tagnames = null)
{
    $pattern = Runtime::shortcodes()->pattern($tagnames === null ? null : array_values((array) $tagnames));
    return $pattern === null ? '' : substr($pattern, 1, -2);
}

function shortcode_parse_atts($text)
{
    return Minn\Runtime\Shortcodes::parse((string) $text);
}

/** The pattern shortcode_parse_atts reads attribute text by. */
function get_shortcode_atts_regex()
{
    return Minn\Runtime\Shortcodes::ATTRIBUTES;
}

/** One matched shortcode run, as do_shortcode runs each (probe shortcode-run). */
function do_shortcode_tag($m)
{
    return Runtime::shortcodes()->run((array) $m);
}

/** One matched shortcode taken out, as strip_shortcodes takes each. */
function strip_shortcode_tag($m)
{
    return Minn\Runtime\Shortcodes::stripped((array) $m);
}

/** The context images rendered inside a shortcode see. */
function _filter_do_shortcode_context()
{
    return 'do_shortcode';
}

function shortcode_atts($pairs, $atts, $shortcode = '')
{
    $atts = (array) $atts;
    $out = [];
    foreach ($pairs as $name => $default) {
        $out[$name] = array_key_exists($name, $atts) ? $atts[$name] : $default;
    }
    if ($shortcode !== '') {
        $out = apply_filters("shortcode_atts_{$shortcode}", $out, $pairs, $atts, $shortcode);
    }
    return $out;
}

function do_shortcodes_in_html_tags($content, $ignore_html, $tagnames)
{
    return Runtime::shortcodes()->inTags((string) $content, $ignore_html ? 'escape' : 'run', array_values((array) $tagnames));
}

function unescape_invalid_shortcodes($content)
{
    return str_replace('&#91;', '[', str_replace('&#93;', ']', (string) $content));
}

function apply_shortcodes($content, $ignore_html = false)
{
    return do_shortcode($content, $ignore_html);
}
