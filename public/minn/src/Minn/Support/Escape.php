<?php

declare(strict_types=1);

namespace Minn\Support;

use Minn\Html;
use Minn\Runtime\Runtime;

/**
 * Text made safe the way WordPress's escapers make it, each ending in the
 * filter plugins hook there, handed the result and the original: for HTML
 * (esc_html, esc_attr) invalid UTF-8 emptied, entities already present
 * normalised rather than encoded again, the specials and both quotes
 * encoded; for a textarea, everything encoded; for an inline script, a
 * single-quoted string's specials; and an address (esc_url) cleaned, its
 * protocol checked against the allowed ones.
 */
final class Escape
{
    /** Text for an element's content. */
    public static function html(mixed $text): string
    {
        return (string) Runtime::hooks()->filter('esc_html', [self::encoded((string) $text), $text]);
    }

    /** Text for an attribute's value. */
    public static function attr(mixed $text): string
    {
        return (string) Runtime::hooks()->filter('attribute_escape', [self::encoded((string) $text), $text]);
    }

    /** A tag name (tag_escape): letters, digits, hyphens, underscores and colons, lowercased, as tag_escape leaves it. */
    public static function tag(mixed $tagName): mixed
    {
        return Runtime::hooks()->filter('tag_escape', [strtolower((string) preg_replace('/[^a-zA-Z0-9-_:]/', '', (string) $tagName)), $tagName]);
    }

    /** Text for a textarea (esc_textarea): every special and quote encoded, references included. */
    public static function textarea(mixed $text): string
    {
        return (string) Runtime::hooks()->filter('esc_textarea', [Html::encodeAll((string) $text), $text]);
    }

    /** Text inside a single-quoted script string (esc_js): specials and double quotes encoded, single quotes and newlines escaped. */
    public static function js(mixed $text): string
    {
        $safe = self::specialchars(self::validUtf8((string) $text), ENT_COMPAT);
        $safe = (string) preg_replace('/&#(x)?0*(?(1)27|39);?/i', "'", stripslashes($safe));
        $safe = str_replace("\n", '\\n', addslashes(str_replace("\r", '', $safe)));
        return (string) Runtime::hooks()->filter('js_escape', [$safe, $text]);
    }

    /**
     * An address to print ('display') or to store ('db'): a missing scheme is
     * http (https when only https is allowed); for display "&amp;" and "'"
     * become references; brackets are encoded; an address on a protocol not
     * allowed (the site's, unless a list is given) is empty. Then clean_url,
     * handed the address, the original and the context.
     */
    public static function url(mixed $url, mixed $protocols = null, mixed $context = 'display'): string
    {
        $scheme = is_array($protocols) && !in_array('http', $protocols, true) && in_array('https', $protocols, true) ? 'https' : 'http';
        $address = Url::clean((string) $url, $scheme);
        if ($address === '') {
            return '';
        }
        if ($context === 'display') {
            $address = str_replace(['&amp;', "'"], ['&#038;', '&#039;'], Kses::normalizeEntities($address));
        }
        $address = Url::encodeBrackets($address, (array) Url::parse($address));
        if (str_contains($address, ':')) {
            $checked = KsesProtocols::strip($address, is_array($protocols) ? $protocols : KsesProtocols::allowed());
            if (strcasecmp($checked, $address) !== 0) {
                return '';
            }
            $address = $checked;
        }
        return (string) Runtime::hooks()->filter('clean_url', [$address, $url, $context]);
    }

    /** The text when it is valid UTF-8, else nothing (wp_check_invalid_utf8 without stripping). */
    public static function validUtf8(string $text): string
    {
        return $text === '' || preg_match('/^./us', $text) === 1 ? $text : '';
    }

    /**
     * The specials encoded in the given quote style (_wp_specialchars),
     * references already present normalised the way kses normalises them
     * rather than encoded again.
     */
    public static function specialchars(string $text, int|string|false $quotes): string
    {
        return Entities::specialchars(str_contains($text, '&') ? Kses::normalizeEntities($text) : $text, $quotes, false, KsesEntities::known(...));
    }

    /** Text for XML (esc_xml): invalid UTF-8 emptied, the specials encoded outside CDATA sections, through esc_xml. */
    public static function forXml(mixed $text): mixed
    {
        return Runtime::hooks()->filter('esc_xml', [self::xml(self::validUtf8((string) $text)), $text]);
    }

    /**
     * Text for an XML document (what esc_xml filters): named references XML lacks are
     * decoded (or their ampersand escaped), a bare ampersand and the four
     * specials encoded, and CDATA sections left exactly as written (probe
     * media-template-doubts).
     */
    public static function xml(string $text): string
    {
        $parts = preg_split('/(<!\[CDATA\[.*?\]\]>)/s', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$text];
        foreach ($parts as $i => $part) {
            $parts[$i] = $i % 2 === 1 ? $part : self::xmlText($part);
        }
        return implode('', $parts);
    }

    private static function xmlText(string $text): string
    {
        $text = (string) preg_replace_callback('/&([A-Za-z][A-Za-z0-9]*);/', static function (array $m): string {
            if (in_array($m[1], ['amp', 'lt', 'gt', 'quot', 'apos'], true)) {
                return $m[0];
            }
            $decoded = html_entity_decode($m[0], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            return $decoded === $m[0] ? '&amp;' . $m[1] . ';' : $decoded;
        }, $text);
        // The five XML names stay; a character reference is written the reference's way (decimal in at least three digits, hex in lower-case x without leading zeros); any other ampersand is escaped.
        $text = (string) preg_replace_callback('/&(?:(amp|lt|gt|quot|apos);|#(0*[0-9]{1,7});|#[xX](0*[0-9A-Fa-f]{1,6});)?/', static function (array $m): string {
            if (($m[1] ?? '') !== '') {
                return $m[0];
            }
            $code = ($m[2] ?? '') !== '' ? (int) $m[2] : (($m[3] ?? '') !== '' ? (int) hexdec($m[3]) : null);
            if ($code === null || !self::xmlCharacter($code)) {
                return '&amp;' . substr($m[0], 1);
            }
            return ($m[2] ?? '') !== '' ? '&#' . str_pad((string) $code, 3, '0', STR_PAD_LEFT) . ';' : '&#x' . ltrim($m[3], '0') . ';';
        }, $text);
        return str_replace(['<', '>', '"', "'"], ['&lt;', '&gt;', '&quot;', '&apos;'], $text);
    }

    /** Whether a code point is a character XML allows. */
    private static function xmlCharacter(int $code): bool
    {
        return in_array($code, [0x9, 0xA, 0xD], true) || ($code >= 0x20 && $code <= 0xD7FF) || ($code >= 0xE000 && $code <= 0xFFFD) || ($code >= 0x10000 && $code <= 0x10FFFF);
    }

    private static function encoded(string $text): string
    {
        return self::specialchars(self::validUtf8($text), ENT_QUOTES);
    }
}
