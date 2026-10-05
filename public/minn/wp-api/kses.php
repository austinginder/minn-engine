<?php
/** The kses family over the engine's own allowlist filter and the captured tables in data/kses.json. */

use Minn\Runtime\Runtime;
use Minn\Support\Kses;
use Minn\Support\KsesPolicy;
use Minn\Support\KsesValues;

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
    // The list follows its filter on every call until wp_loaded starts; from then on the last list computed stands.
    static $protocols = null;
    if ($protocols === null || !did_action('wp_loaded')) {
        $protocols = array_values(array_unique((array) apply_filters('kses_allowed_protocols', _minn_kses_table()['protocols'])));
    }
    return $protocols;
}

/** @internal a caller's allowed_html in any of its shapes, with the protocols and URI attributes in force */
function _minn_kses_policy(array|string $allowed_html, array $allowed_protocols): KsesPolicy
{
    if (is_string($allowed_html)) {
        $allowed_html = wp_kses_allowed_html($allowed_html);
    }
    return KsesPolicy::fromAllowlist((array) $allowed_html, array_values($allowed_protocols), (array) wp_kses_uri_attributes());
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
    return Kses::filter((string) $content, _minn_kses_policy($allowed_html, (array) $allowed_protocols));
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
    return Kses::normalizeEntities((string) $content);
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
    return Kses::filter((string) $content, _minn_kses_policy($allowed_html, (array) $allowed_protocols));
}

/** Block delimiters written back with every attribute key and string value run through the same kses pass. */
function wp_pre_kses_block_attributes($content, $allowed_html, $allowed_protocols)
{
    return Kses::blockAttributes((string) $content, static fn (string $value): string => wp_kses($value, $allowed_html, $allowed_protocols));
}

function wp_kses_version()
{
    return '0.2.2';
}

function wp_kses_attr_check(&$name, &$value, &$whole, $vless, $element, $allowed_html)
{
    $rules = _minn_kses_policy($allowed_html, wp_allowed_protocols())->rules(strtolower((string) $element), strtolower((string) $name));
    return $rules !== null && KsesValues::satisfies((string) $value, (string) $vless, $rules);
}

function wp_kses_check_attr_val($value, $vless, $checkname, $checkvalue)
{
    return KsesValues::check((string) $value, (string) $vless, (string) $checkname, $checkvalue);
}

/** The value rule behind the post allowlist's object element: a PDF on the uploads host. */
function _wp_kses_allow_pdf_objects($url)
{
    return Kses::pdfObject((string) $url, (string) (wp_upload_dir(null, false)['url'] ?? ''));
}

function safecss_filter_attr($css, $deprecated = '')
{
    return Kses::style((string) $css);
}

function wp_kses_hair($attr, $allowed_protocols)
{
    $out = [];
    foreach (Kses::attributeList((string) $attr) ?? [] as $name => $attribute) {
        $value = $attribute['value'] ?? '';
        if ($attribute['value'] !== null && in_array($name, wp_kses_uri_attributes(), true)) {
            $value = wp_kses_bad_protocol($value, $allowed_protocols);
        }
        $out[$name] = ['name' => $name, 'value' => $value, 'whole' => $attribute['whole'], 'vless' => $attribute['value'] === null ? 'y' : 'n'];
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
