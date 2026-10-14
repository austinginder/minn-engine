<?php
/** The kses family over the engine's own allowlist filter and the captured tables in data/kses.json. */

use Minn\Runtime\Runtime;
use Minn\Support\Kses;
use Minn\Support\KsesPolicy;
use Minn\Support\KsesValues;
use Minn\Support\KsesProtocols;

/** @internal */
function _minn_kses_table(): array
{
    return Minn\Runtime\KsesRules::table();
}

function wp_kses_allowed_html($context = '')
{
    return Minn\Runtime\KsesRules::allowedHtml($context);
}

function wp_allowed_protocols()
{
    return KsesProtocols::allowed();
}

/**
 * @internal a caller's allowed_html in any of its shapes, with the protocols and URI attributes in force. For
 * $content, the URI attributes are asked only when an opening tag of an allowed element that may carry attributes
 * is there, as the reference asks them of each such tag (probe hook-inputs-2); otherwise none are consulted.
 */
function _minn_kses_policy(array|string $allowed_html, array $allowed_protocols, ?string $content = null): KsesPolicy
{
    return Minn\Runtime\KsesRules::policy($allowed_html, $allowed_protocols, $content);
}

function wp_kses($content, $allowed_html, $allowed_protocols = [])
{
    return Minn\Runtime\KsesRules::filter($content, $allowed_html, $allowed_protocols);
}

function wp_kses_hook($content, $allowed_html, $allowed_protocols)
{
    return apply_filters('pre_kses', $content, $allowed_html, $allowed_protocols);
}

function wp_kses_post($data)
{
    return Minn\Runtime\KsesRules::post($data);
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
    return Minn\Runtime\KsesRules::withoutNull($content, $options);
}

function wp_kses_normalize_entities($content, $context = 'html')
{
    return Kses::normalizeEntities((string) $content);
}

function wp_kses_bad_protocol($content, $allowed_protocols)
{
    return KsesProtocols::strip((string) $content, is_array($allowed_protocols) ? $allowed_protocols : (string) $allowed_protocols);
}

function wp_kses_bad_protocol_once($content, $allowed_protocols, $count = 1)
{
    return KsesProtocols::stripOnce((string) $content, is_array($allowed_protocols) ? $allowed_protocols : (string) $allowed_protocols);
}

function wp_kses_decode_entities($content)
{
    return Minn\Support\Entities::numericAsBytes((string) $content);
}

function wp_kses_split($content, $allowed_html, $allowed_protocols)
{
    return Kses::filter((string) $content, _minn_kses_policy($allowed_html, (array) $allowed_protocols, (string) $content));
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

/**
 * Whether an attribute may stay on an element (probe kses-remaining): a
 * style keeps only what safecss_filter_attr leaves, and is refused when
 * nothing is left; a refused attribute has its name, value and whole
 * emptied.
 */
function wp_kses_attr_check(&$name, &$value, &$whole, $vless, $element, $allowed_html)
{
    $rules = _minn_kses_policy($allowed_html, wp_allowed_protocols())->rules(strtolower((string) $element), strtolower((string) $name));
    $allowed = $rules !== null && KsesValues::satisfies((string) $value, (string) $vless, $rules);
    if ($allowed && strtolower((string) $name) === 'style') {
        $filtered = safecss_filter_attr((string) $value);
        $allowed = $filtered !== '';
        if ($allowed && $filtered !== $value) {
            $value = $filtered;
            $whole = "{$name}=\"{$filtered}\"";
        }
    }
    if (!$allowed) {
        $name = $value = $whole = '';
    }
    return $allowed;
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

// Plugins' say over style attributes, wherever kses reads one: the property list and each declaration.
Minn\Support\KsesCss::styleHooks(
    static fn (array $properties): array => array_values((array) apply_filters('safe_style_css', $properties)),
    static fn (bool $allow, string $declaration): bool => (bool) apply_filters('safecss_filter_attr_allow_css', $allow, $declaration),
);

function safecss_filter_attr($css, $deprecated = '')
{
    return Minn\Support\KsesCss::style((string) $css);
}

function wp_kses_hair($attr, $allowed_protocols)
{
    $out = [];
    // A value is read as HTML and written back with &, <, >, " and ' encoded, and the whole
    // attribute as name="value" (probe kses-remaining).
    foreach (Kses::attributeList((string) $attr) ?? [] as $name => $attribute) {
        if ($attribute['value'] === null) {
            $out[$name] = ['name' => $name, 'value' => '', 'whole' => $name, 'vless' => 'y'];
            continue;
        }
        $value = html_entity_decode((string) $attribute['value'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (in_array($name, wp_kses_uri_attributes(), true)) {
            $value = wp_kses_bad_protocol($value, $allowed_protocols);
        }
        $value = htmlspecialchars($value, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8');
        $out[$name] = ['name' => $name, 'value' => $value, 'whole' => "{$name}=\"{$value}\"", 'vless' => 'n'];
    }
    return $out;
}

function wp_kses_attr_parse($element)
{
    return Minn\Support\KsesAttributes::elementParts((string) $element) ?? false;
}

function wp_kses_one_attr($attr, $element)
{
    return Minn\Runtime\KsesRules::oneAttribute($attr, $element);
}

function wp_kses_array_lc($inarray)
{
    $out = [];
    foreach ((array) $inarray as $k => $v) {
        // Each element's rules become a list with lower-cased keys, true included (probe kses-remaining).
        $out[strtolower((string) $k)] = array_change_key_case((array) $v);
    }
    return $out;
}

/** The content filters a user without unfiltered_html gets; comments take the post rules for those who have it. */
function kses_init_filters()
{
    foreach (['content_save_pre', 'excerpt_save_pre', 'content_filtered_save_pre'] as $hook) {
        add_filter($hook, 'wp_filter_post_kses');
    }
    // A user's global styles are filtered first (probe theme-json-insecure).
    add_filter('content_save_pre', 'wp_filter_global_styles_post', 9);
    add_filter('content_filtered_save_pre', 'wp_filter_global_styles_post', 9);
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
    remove_filter('content_save_pre', 'wp_filter_global_styles_post', 9);
    remove_filter('content_filtered_save_pre', 'wp_filter_global_styles_post', 9);
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
    return Kses::uriAttributes();
}

/** Block content without the custom CSS a block's style may carry (style.css; a style left empty goes too): slashed text in, slashed out. */
function wp_strip_custom_css_from_blocks($content)
{
    $plain = wp_unslash((string) $content);
    if (!str_contains($plain, '"css"')) {
        return $content;
    }
    // Nothing taken out leaves the content as it came (probe block-supports-remaining).
    $changed = false;
    $strip = static function (array $blocks) use (&$strip, &$changed): array {
        foreach ($blocks as $i => $block) {
            if (isset($block['attrs']['style']['css'])) {
                $changed = true;
                unset($blocks[$i]['attrs']['style']['css']);
                if ($blocks[$i]['attrs']['style'] === []) {
                    unset($blocks[$i]['attrs']['style']);
                }
            }
            $blocks[$i]['innerBlocks'] = $strip((array) ($block['innerBlocks'] ?? []));
        }
        return $blocks;
    };
    $blocks = $strip(parse_blocks($plain));
    return $changed ? wp_slash(serialize_blocks($blocks)) : $content;
}

/** A tag's allowed attributes with the ones every tag may carry (aria, class, data-*, dir, hidden, id, lang, style, tabindex, title, role, xml:lang); true means none of its own. */
function _wp_add_global_attributes($value)
{
    $global = ['aria-controls' => true, 'aria-current' => true, 'aria-describedby' => true, 'aria-details' => true, 'aria-expanded' => true, 'aria-hidden' => true, 'aria-label' => true, 'aria-labelledby' => true, 'aria-live' => true, 'class' => true, 'data-*' => true, 'dir' => true, 'hidden' => true, 'id' => true, 'lang' => true, 'style' => true, 'tabindex' => true, 'title' => true, 'role' => true, 'xml:lang' => true];
    if ($value === true) {
        $value = [];
    }
    return is_array($value) ? array_merge($value, $global) : $value;
}

/** Whether a code point may be named by a reference: tab, line feed, carriage return, and Unicode from space up but the surrogates, U+FFFE and U+FFFF. */
function valid_unicode($i)
{
    return Kses::validCodePoint((int) $i);
}

/** The byte a decimal reference's number stands for, as kses decodes it (wp_kses_decode_entities). */
function _wp_kses_decode_entities_chr($matches)
{
    return chr((int) $matches[1]);
}

/** The byte a hex reference's number stands for, as kses decodes it (wp_kses_decode_entities). */
function _wp_kses_decode_entities_chr_hexdec($matches)
{
    return chr((int) hexdec($matches[1]));
}

/** A named reference kept when kses knows the name, else its "&" escaped; nothing for no name. */
function wp_kses_named_entities($matches)
{
    if (empty($matches[1])) {
        return '';
    }
    return Minn\Support\KsesEntities::known($matches[1]) ? '&' . $matches[1] . ';' : '&amp;' . $matches[1] . ';';
}

/** A named reference for XML: the five XML names kept, another known name decoded to its character, anything else escaped. */
function wp_kses_xml_named_entities($matches)
{
    if (empty($matches[1])) {
        return '';
    }
    $name = $matches[1];
    if (in_array($name, ['amp', 'lt', 'gt', 'quot', 'apos'], true)) {
        return '&' . $name . ';';
    }
    return Minn\Support\KsesEntities::known($name) ? html_entity_decode('&' . $name . ';', ENT_HTML5) : '&amp;' . $name . ';';
}

/** A decimal reference kept, its digits padded to three, when it names a valid character; else its "&" escaped. */
function wp_kses_normalize_entities2($matches)
{
    if (empty($matches[1])) {
        return '';
    }
    $digits = ltrim($matches[1], '0');
    return valid_unicode($digits) ? '&#' . str_pad($digits, 3, '0', STR_PAD_LEFT) . ';' : '&amp;#' . $matches[1] . ';';
}

/** A hex reference kept, without leading zeros, when it names a valid character; else its "&" escaped. */
function wp_kses_normalize_entities3($matches)
{
    if (empty($matches[1])) {
        return '';
    }
    $hex = ltrim($matches[1], '0');
    return valid_unicode(hexdec($hex)) ? '&#x' . $hex . ';' : '&amp;#x' . $matches[1] . ';';
}

/** A URL's scheme, decoded, without whitespace and lower-cased, with its colon when it is allowed; nothing when it is not. */
function wp_kses_bad_protocol_once2($scheme, $allowed_protocols)
{
    $scheme = strtolower((string) preg_replace('/[\s\x00-\x20]/', '', wp_kses_decode_entities((string) $scheme)));
    foreach ((array) $allowed_protocols as $protocol) {
        if (strtolower((string) $protocol) === $scheme) {
            return $scheme . ':';
        }
    }
    return '';
}

/** An attribute string cut into its attributes, each with the whitespace after it; false when anything in it is not an attribute. */
function wp_kses_hair_parse($attr)
{
    return Minn\Support\KsesAttributes::parse((string) $attr) ?? false;
}

/** What follows the first malformed run of an attribute string (a quoted or bare run and the whitespace after it). */
function wp_kses_html_error($attr)
{
    return (string) preg_replace('/^("[^"]*("|$)|\'[^\']*(\'|$)|\S)*\s*/', '', (string) $attr);
}

/** An element rebuilt with the attributes the allowlist lets it keep, its name as written and an XHTML slash kept. */
function wp_kses_attr($element, $attr, $allowed_html, $allowed_protocols)
{
    return Kses::element((string) $element, (string) $attr, _minn_kses_policy($allowed_html, (array) $allowed_protocols));
}

/** One run of the split pass on its own: a comment, a tag judged by the allowlist, or a lone ">" escaped. */
function wp_kses_split2($content, $allowed_html, $allowed_protocols)
{
    return Kses::tag((string) $content, _minn_kses_policy($allowed_html, (array) $allowed_protocols));
}

/** The split pass's callback: one run judged by the rules wp_kses_split left in its globals. */
function _wp_kses_split_callback($matches)
{
    global $pass_allowed_html, $pass_allowed_protocols;
    return wp_kses_split2($matches[0], $pass_allowed_html, $pass_allowed_protocols);
}

/**
 * A user's global styles (JSON flagged isGlobalStylesUserThemeJSON) saved
 * with only their safe properties, in the custom origin; any other content
 * passes through as given.
 */
function wp_filter_global_styles_post($data)
{
    $decoded = json_decode(wp_unslash($data), true);
    if (!is_array($decoded) || empty($decoded['isGlobalStylesUserThemeJSON'])) {
        return $data;
    }
    unset($decoded['isGlobalStylesUserThemeJSON']);
    $filtered = WP_Theme_JSON::remove_insecure_properties($decoded, 'custom');
    $filtered['isGlobalStylesUserThemeJSON'] = true;
    // Tags and ampersands go out as \u escapes, so the kses that runs after this filter has nothing to rewrite (probe global-styles-unfiltered).
    return wp_slash(wp_json_encode($filtered, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP));
}
