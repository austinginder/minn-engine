<?php
/** The kses family over the engine's own allowlist filter and the captured tables in data/kses.json. */

use Minn\Runtime\Runtime;
use Minn\Support\Kses;

/** @internal */
function _minn_kses_table(): array
{
    static $table = null;
    return $table ??= json_decode((string) file_get_contents(Runtime::current()->engineDir . '/data/kses.json'), true);
}

function wp_kses_allowed_html($context = '')
{
    $table = _minn_kses_table();
    if (is_array($context)) {
        return apply_filters('wp_kses_allowed_html', $context, 'explicit');
    }
    return match ($context) {
        'post' => apply_filters('wp_kses_allowed_html', $table['post'], 'post'),
        'user_description', 'pre_user_description' => apply_filters('wp_kses_allowed_html', $table['data'] + ['a' => $table['data']['a'] + ['rel' => true]], $context),
        'strip' => apply_filters('wp_kses_allowed_html', [], 'strip'),
        'entities' => apply_filters('wp_kses_allowed_html', $table['entities'], 'entities'),
        default => apply_filters('wp_kses_allowed_html', $table['data'], $context),
    };
}

function wp_allowed_protocols()
{
    return apply_filters('kses_allowed_protocols', _minn_kses_table()['protocols']);
}

/** @internal a caller's allowed_html in any of its shapes as tag => list of attribute names */
function _minn_kses_normalize(array|string $allowed_html): array
{
    if (is_string($allowed_html)) {
        $allowed_html = wp_kses_allowed_html($allowed_html);
    }
    $out = [];
    foreach ($allowed_html as $tag => $attributes) {
        if (is_int($tag)) {
            continue; // a list of values, not tags: nothing is allowed
        }
        $names = [];
        if (is_array($attributes)) {
            foreach ($attributes as $name => $allowed) {
                if (is_string($name) && $allowed) {
                    $names[] = strtolower($name);
                }
            }
        }
        $out[strtolower((string) $tag)] = $names;
    }
    return $out;
}

function wp_kses($content, $allowed_html, $allowed_protocols = [])
{
    $content = (string) $content;
    if ($allowed_protocols === []) {
        $allowed_protocols = wp_allowed_protocols();
    }
    $content = wp_kses_no_null($content, ['slash_zero' => 'keep']);
    $content = wp_kses_normalize_entities($content);
    $content = wp_kses_hook($content, $allowed_html, $allowed_protocols);
    return _minn_kses_text_brackets(Kses::filter($content, _minn_kses_normalize($allowed_html)));
}

/** @internal a stray < or > in text is escaped, as the reference does after splitting */
function _minn_kses_text_brackets(string $html): string
{
    $parts = preg_split('/(<!--.*?-->|<[^>]*>)/s', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    foreach ($parts as $i => $part) {
        if ($i % 2 === 0) {
            $parts[$i] = str_replace(['<', '>'], ['&lt;', '&gt;'], $part);
        }
    }
    return implode('', $parts);
}

function wp_kses_hook($content, $allowed_html, $allowed_protocols)
{
    return apply_filters('pre_kses', $content, $allowed_html, $allowed_protocols);
}

function wp_kses_post($data)
{
    return wp_kses((string) $data, 'post');
}

function wp_kses_post_deep($data)
{
    return map_deep($data, 'wp_kses_post');
}

function wp_kses_data($data)
{
    return wp_kses((string) $data, current_filter());
}

function wp_filter_kses($data)
{
    return addslashes(wp_kses(stripslashes((string) $data), current_filter()));
}

function wp_filter_post_kses($data)
{
    return addslashes(wp_kses(stripslashes((string) $data), 'post'));
}

function wp_filter_nohtml_kses($data)
{
    return addslashes(wp_kses(stripslashes((string) $data), 'strip'));
}

function wp_kses_no_null($content, $options = null)
{
    $content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', (string) $content);
    if (($options['slash_zero'] ?? 'remove') !== 'keep') {
        $content = preg_replace('/\\\\+0+/', '', $content);
    }
    return $content;
}

function wp_kses_normalize_entities($content, $context = 'html')
{
    $content = str_replace('&', '&amp;', (string) $content);
    $content = preg_replace_callback('/&amp;([A-Za-z]{2,8}[0-9]{0,8});/', static fn (array $m) => _minn_known_entity($m[1]) ? '&' . $m[1] . ';' : '&amp;' . $m[1] . ';', $content);
    $content = preg_replace_callback('/&amp;#(0*[0-9]{1,7});/', static function (array $m): string {
        $code = (int) $m[1];
        return $code > 0 && $code <= 0x10FFFF && !($code >= 0xD800 && $code <= 0xDFFF) ? '&#' . str_pad((string) $code, 3, '0', STR_PAD_LEFT) . ';' : '&amp;#' . $m[1] . ';';
    }, $content);
    $content = preg_replace_callback('/&amp;#[Xx](0*[0-9A-Fa-f]{1,6});/', static function (array $m): string {
        $code = hexdec($m[1]);
        return $code > 0 && $code <= 0x10FFFF && !($code >= 0xD800 && $code <= 0xDFFF) ? '&#x' . $m[1] . ';' : '&amp;#x' . $m[1] . ';';
    }, $content);
    return $content;
}

function wp_kses_bad_protocol($content, $allowed_protocols)
{
    $content = wp_kses_no_null((string) $content);
    $iterations = 0;
    do {
        $original = $content;
        $content = wp_kses_bad_protocol_once($content, $allowed_protocols);
    } while ($original !== $content && ++$iterations < 6);
    return $original === $content ? $content : '';
}

function wp_kses_bad_protocol_once($content, $allowed_protocols, $count = 1)
{
    $content = preg_replace('/(&colon;|&#0*58;|&#x0*3a;)/i', ':', (string) $content);
    $parts = preg_split('/:|&#0*58;|&#x0*3a;|&colon;/i', $content, 2);
    if (isset($parts[1]) && !preg_match('%/\?%', $parts[0])) {
        $protocol = preg_replace('/[\s\x00-\x20]/', '', wp_kses_decode_entities($parts[0]));
        $protocol = preg_replace('/[^a-zA-Z0-9-+.]/', '', $protocol);
        $allowed = false;
        foreach ((array) $allowed_protocols as $p) {
            if (strtolower($p) === strtolower($protocol)) {
                $allowed = true;
                break;
            }
        }
        if (!$allowed) {
            return $parts[1];
        }
        return $protocol . ':' . $parts[1];
    }
    return $content;
}

function wp_kses_decode_entities($content)
{
    $content = preg_replace_callback('/&#([0-9]+);/', static fn (array $m) => chr((int) $m[1] % 256), (string) $content);
    return preg_replace_callback('/&#[Xx]([0-9A-Fa-f]+);/', static fn (array $m) => chr(hexdec($m[1]) % 256), $content);
}

function wp_kses_split($content, $allowed_html, $allowed_protocols)
{
    return _minn_kses_text_brackets(Kses::filter((string) $content, _minn_kses_normalize($allowed_html)));
}

function wp_kses_version()
{
    return '0.2.2';
}

function wp_kses_attr_check(&$name, &$value, &$whole, $vless, $element, $allowed_html)
{
    $allowed = _minn_kses_normalize($allowed_html);
    return in_array(strtolower($name), $allowed[strtolower($element)] ?? [], true);
}

function safecss_filter_attr($css, $deprecated = '')
{
    return Kses::style((string) $css);
}

function wp_kses_hair($attr, $allowed_protocols)
{
    $out = [];
    preg_match_all('/([a-zA-Z0-9_:-]+)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'>]+)))?/', (string) $attr, $m, PREG_SET_ORDER);
    foreach ($m as $hit) {
        $name = strtolower($hit[1]);
        $value = $hit[2] ?? $hit[3] ?? $hit[4] ?? null;
        $out[$name] = ['name' => $name, 'value' => $value ?? '', 'whole' => $hit[0], 'vless' => $value === null ? 'y' : 'n'];
    }
    return $out;
}

function wp_kses_attr_parse($element)
{
    return false;
}

function wp_kses_one_attr($attr, $element)
{
    return $attr;
}

function wp_kses_array_lc($inarray)
{
    $out = [];
    foreach ((array) $inarray as $k => $v) {
        $out[strtolower((string) $k)] = is_array($v) ? array_change_key_case($v) : $v;
    }
    return $out;
}

/** The content filters a user without unfiltered_html gets; comments take the post rules for those who have it. */
function kses_init_filters()
{
    foreach (['content_save_pre', 'excerpt_save_pre', 'content_filtered_save_pre'] as $hook) {
        add_filter($hook, 'wp_filter_post_kses');
    }
    add_filter('pre_comment_content', current_user_can('unfiltered_html') ? 'wp_filter_post_kses' : 'wp_filter_kses');
    add_filter('title_save_pre', 'wp_filter_kses');
    add_filter('pre_comment_author_name', 'wp_filter_kses');
    add_filter('pre_term_description', 'wp_filter_kses');
    add_filter('pre_link_description', 'wp_filter_kses');
}

function kses_remove_filters()
{
    foreach (['content_save_pre', 'excerpt_save_pre', 'content_filtered_save_pre'] as $hook) {
        remove_filter($hook, 'wp_filter_post_kses');
    }
    remove_filter('pre_comment_content', 'wp_filter_post_kses');
    remove_filter('pre_comment_content', 'wp_filter_kses');
    remove_filter('title_save_pre', 'wp_filter_kses');
}

function kses_init()
{
    kses_remove_filters();
    if (!current_user_can('unfiltered_html')) {
        kses_init_filters();
    }
}

function wp_kses_uri_attributes()
{
    return apply_filters('wp_kses_uri_attributes', Kses::URI_ATTRIBUTES);
}
