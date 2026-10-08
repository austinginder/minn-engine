<?php

declare(strict_types=1);

namespace Minn\Support;

/**
 * Kses one attribute at a time, for the shortcode pass that runs inside
 * HTML tags: an opening tag cut into its attributes, and one attribute
 * judged as kses judges it on its element. Probe shortcode-run.
 */
final class KsesAttributes
{
    /**
     * An opening tag cut as wp_kses_attr_parse cuts it: the start ("<a "),
     * each attribute with the whitespace after it (a shortcode standing as
     * a name counts as one), and the end (">" or "/>"). Null for a closing
     * tag, a comment, CDATA, or anything between the brackets that is not
     * attributes.
     *
     * @return list<string>|null
     */
    public static function elementParts(string $element): ?array
    {
        if (!preg_match('%^(<\s*)(/\s*)?([a-zA-Z0-9]+\s*)([^>]*)(>?)$%', $element, $m) || $m[2] !== '') {
            return null;
        }
        [$attributes, $end] = str_ends_with($m[4], '/') ? [substr($m[4], 0, -1), '/' . $m[5]] : [$m[4], $m[5]];
        preg_match_all('%(?:[_a-zA-Z][-_a-zA-Z0-9:.]*|\[\[?[^\[\]]+\]\]?)(?:\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s"\']+(?:\s|$)))?\s*%', $attributes, $found);
        if (implode('', $found[0]) !== $attributes) {
            return null;
        }
        return [$m[1] . $m[3], ...$found[0], $end];
    }

    /**
     * One attribute as written, judged as kses judges it on the element
     * (wp_kses_one_attr): the value escaped for an attribute and kept in its
     * own quotes (a bare one gains double quotes), a URL's bad scheme cut
     * away, style kept to the allowed properties; nothing when the element
     * may not carry it or a quoted value never closes. The whitespace
     * around it stays.
     */
    public static function oneAttribute(string $attr, string $element, KsesPolicy $policy): string
    {
        $attr = str_replace(chr(0), '', $attr);
        preg_match('/^(\s*)(.*?)(\s*)$/s', $attr, $parts);
        [, $lead, $core, $trail] = $parts;
        [$name, $value] = array_pad(preg_split('/\s*=\s*/', $core, 2) ?: [$core], 2, null);
        $rules = $policy->rules(strtolower($element), strtolower($name));
        if ($value === null) {
            return $rules !== null && KsesValues::satisfies('', 'y', $rules) ? $lead . $core . $trail : '';
        }
        $quote = $value === '' ? '' : $value[0];
        if ($quote === '"' || $quote === "'") {
            if (strlen($value) < 2 || !str_ends_with($value, $quote)) {
                return '';
            }
            $value = substr($value, 1, -1);
        } else {
            $quote = '"';
        }
        $value = Escape::attr($value);
        if ($policy->holdsUri(strtolower($name))) {
            $value = Kses::attributeUrl($value, $policy->schemes);
        }
        if ($rules === null || !KsesValues::satisfies($value, 'n', $rules)) {
            return '';
        }
        if (strtolower($name) === 'style' && ($value = Kses::style($value)) === '') {
            return '';
        }
        return $lead . $name . '=' . $quote . $value . $quote . $trail;
    }
}
