<?php

declare(strict_types=1);

namespace Minn\Support;

use Minn\Html\Decoder;

/**
 * The HTML a user without unfiltered_html may store. Tags outside the
 * allowlist are removed and their text kept; attributes outside the tag's
 * list (and the global set) are dropped; URL attributes lose unsafe
 * schemes; style attributes keep only listed properties and no code.
 * Comments (the block delimiters) pass through untouched.
 */
final class Kses
{
    /** @var array<string, list<string>> tag => attributes, for post content */
    public const POST = [
        'a' => ['href', 'rel', 'rev', 'name', 'target', 'download'],
        'abbr' => [], 'acronym' => [], 'address' => [], 'article' => [], 'aside' => [],
        'audio' => ['autoplay', 'controls', 'loop', 'muted', 'preload', 'src'],
        'b' => [], 'bdi' => [], 'bdo' => [], 'big' => [],
        'blockquote' => ['cite'], 'br' => [], 'button' => ['disabled', 'name', 'type', 'value'],
        'caption' => ['align'], 'cite' => [], 'code' => [], 'col' => ['align', 'span', 'valign', 'width'],
        'colgroup' => ['align', 'span', 'valign', 'width'], 'dd' => [], 'del' => ['datetime'],
        'details' => ['open'], 'dfn' => [], 'div' => ['align'], 'dl' => [], 'dt' => [], 'em' => [],
        'fieldset' => [], 'figcaption' => [], 'figure' => ['align'], 'font' => ['color', 'face', 'size'],
        'footer' => [], 'h1' => ['align'], 'h2' => ['align'], 'h3' => ['align'], 'h4' => ['align'],
        'h5' => ['align'], 'h6' => ['align'], 'header' => [], 'hgroup' => [], 'hr' => ['align', 'noshade', 'size', 'width'],
        'i' => [],
        'img' => ['alt', 'align', 'border', 'decoding', 'fetchpriority', 'height', 'hspace', 'loading', 'longdesc', 'sizes', 'src', 'srcset', 'usemap', 'vspace', 'width'],
        'ins' => ['cite', 'datetime'], 'kbd' => [], 'label' => ['for'], 'legend' => ['align'],
        'li' => ['align', 'value'], 'main' => ['align'], 'map' => ['name'], 'mark' => [], 'menu' => ['type'],
        'nav' => ['align'], 'object' => [], 'ol' => ['reversed', 'start', 'type'], 'p' => ['align'],
        'picture' => [], 'pre' => ['width'], 'q' => ['cite'], 'rb' => [], 'rp' => [], 'rt' => [], 'rtc' => [], 'ruby' => [],
        's' => [], 'samp' => [], 'section' => ['align'], 'small' => [],
        'source' => ['height', 'media', 'sizes', 'src', 'srcset', 'type', 'width'],
        'span' => ['align'], 'strike' => [], 'strong' => [], 'sub' => [], 'summary' => ['align'], 'sup' => [],
        'table' => ['align', 'bgcolor', 'border', 'cellpadding', 'cellspacing', 'rules', 'summary', 'width'],
        'tbody' => ['align', 'valign'], 'td' => ['abbr', 'align', 'axis', 'bgcolor', 'colspan', 'headers', 'height', 'nowrap', 'rowspan', 'scope', 'valign', 'width'],
        'textarea' => ['cols', 'disabled', 'name', 'readonly', 'rows'], 'tfoot' => ['align', 'valign'],
        'th' => ['abbr', 'align', 'axis', 'bgcolor', 'colspan', 'headers', 'height', 'nowrap', 'rowspan', 'scope', 'valign', 'width'],
        'thead' => ['align', 'valign'], 'title' => [], 'tr' => ['align', 'bgcolor', 'valign'],
        'track' => ['default', 'kind', 'label', 'src', 'srclang'], 'tt' => [], 'u' => [], 'ul' => ['type'], 'var' => [],
        'video' => ['autoplay', 'controls', 'height', 'loop', 'muted', 'playsinline', 'poster', 'preload', 'src', 'width'],
    ];

    /** @var array<string, list<string>> the smaller set for comments and descriptions */
    public const COMMENT = [
        'a' => ['href', 'title', 'rel'], 'abbr' => ['title'], 'acronym' => ['title'], 'b' => [],
        'blockquote' => ['cite'], 'cite' => [], 'code' => [], 'del' => ['datetime'], 'em' => [], 'i' => [],
        'q' => ['cite'], 's' => [], 'strike' => [], 'strong' => [],
    ];

    /** The attributes every post-content tag takes besides its own. */
    public const GLOBAL_ATTRIBUTES = ['class', 'id', 'style', 'title', 'role', 'dir', 'lang', 'xml:lang', 'hidden', 'tabindex'];

    /** Every attribute the reference treats as holding a URI, so its scheme is judged wherever the attribute is allowed. */
    public const URI_ATTRIBUTES = [
        'action', 'archive', 'background', 'cite', 'classid', 'codebase', 'data', 'formaction', 'href',
        'icon', 'longdesc', 'manifest', 'poster', 'profile', 'src', 'usemap', 'xmlns',
    ];
    /** The URI schemes allowed by default. */
    public const SCHEMES = ['http', 'https', 'ftp', 'ftps', 'mailto', 'news', 'irc', 'gopher', 'nntp', 'feed', 'telnet', 'mms', 'rtsp', 'sms', 'svn', 'tel', 'fax', 'xmpp', 'webcal', 'urn'];
    private const CSS_PROPERTIES = [
        'background', 'background-color', 'background-image', 'background-position', 'background-repeat', 'background-size', 'background-attachment', 'background-blend-mode',
        'border', 'border-radius', 'border-width', 'border-color', 'border-style', 'border-spacing', 'border-collapse',
        'border-top', 'border-right', 'border-bottom', 'border-left',
        'border-top-color', 'border-right-color', 'border-bottom-color', 'border-left-color',
        'border-top-width', 'border-right-width', 'border-bottom-width', 'border-left-width',
        'border-top-style', 'border-right-style', 'border-bottom-style', 'border-left-style',
        'border-top-left-radius', 'border-top-right-radius', 'border-bottom-right-radius', 'border-bottom-left-radius',
        'caption-side', 'clear', 'color', 'columns', 'column-count', 'column-gap', 'column-width', 'column-span', 'column-rule',
        'cursor', 'direction', 'display', 'filter', 'float', 'flex', 'flex-basis', 'flex-direction', 'flex-flow', 'flex-grow', 'flex-shrink', 'flex-wrap',
        'font', 'font-family', 'font-size', 'font-style', 'font-variant', 'font-weight', 'font-display',
        'gap', 'row-gap', 'column-gap', 'grid', 'grid-area', 'grid-auto-columns', 'grid-auto-flow', 'grid-auto-rows', 'grid-column', 'grid-column-end',
        'grid-column-gap', 'grid-column-start', 'grid-gap', 'grid-row', 'grid-row-end', 'grid-row-gap', 'grid-row-start', 'grid-template',
        'grid-template-areas', 'grid-template-columns', 'grid-template-rows',
        'height', 'min-height', 'max-height', 'width', 'min-width', 'max-width',
        'justify-content', 'justify-items', 'justify-self', 'align-content', 'align-items', 'align-self',
        'letter-spacing', 'line-height', 'list-style', 'list-style-image', 'list-style-position', 'list-style-type',
        'margin', 'margin-top', 'margin-right', 'margin-bottom', 'margin-left', 'margin-block', 'margin-block-start', 'margin-block-end', 'margin-inline', 'margin-inline-start', 'margin-inline-end',
        'object-fit', 'object-position', 'opacity', 'order', 'overflow', 'overflow-wrap', 'overflow-x', 'overflow-y',
        'padding', 'padding-top', 'padding-right', 'padding-bottom', 'padding-left', 'padding-block', 'padding-block-start', 'padding-block-end', 'padding-inline', 'padding-inline-start', 'padding-inline-end',
        'position', 'resize', 'table-layout', 'text-align', 'text-decoration', 'text-indent', 'text-shadow', 'text-transform', 'text-wrap',
        'vertical-align', 'visibility', 'white-space', 'word-break', 'word-spacing', 'word-wrap', 'writing-mode',
        'aspect-ratio', 'box-shadow', 'box-sizing', 'z-index',
    ];

    /** Post content as an author without unfiltered_html may store it. */
    public static function post(string $html): string
    {
        return self::filter($html, KsesPolicy::post());
    }

    /** A comment, profile or term description as anyone without unfiltered_html may store it: listed attributes only. */
    public static function comment(string $html): string
    {
        return self::filter($html, KsesPolicy::comment());
    }

    /** HTML with only what the policy allows kept. */
    public static function filter(string $html, KsesPolicy $policy): string
    {
        return (string) preg_replace_callback(
            '/<!--.*?-->|<\/?([a-zA-Z][a-zA-Z0-9-]*)((?:\s+[^\s=>\/]+(?:\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s"\'>]+))?)*)\s*(\/?)>|<[^>]*>?|[^<]+/s',
            static function (array $m) use ($policy): string {
                if (str_starts_with($m[0], '<!--')) {
                    return $m[0];
                }
                if ($m[0][0] !== '<') {
                    return self::normalizeText($m[0]);
                }
                $tag = strtolower($m[1] ?? '');
                if ($tag === '') {
                    // A "<" that starts no tag is text (the reference keeps "a &lt; b").
                    return '&lt;' . self::normalizeText(substr($m[0], 1));
                }
                if (!$policy->allowsTag($tag)) {
                    return '';
                }
                if (str_starts_with($m[0], '</')) {
                    return "</{$tag}>";
                }
                return '<' . $tag . self::attributes($m[2] ?? '', $tag, $policy) . (($m[3] ?? '') === '/' ? ' />' : '>');
            },
            $html,
        );
    }

    /** Plain text: tags gone, whitespace collapsed, control characters dropped. */
    public static function text(string $value): string
    {
        $value = (string) preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $value);
        $value = strip_tags($value);
        $value = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);
        return trim((string) preg_replace('/\s+/', ' ', $value));
    }

    /** A URL for a stored field: empty when its scheme is not one the reference allows. */
    public static function url(string $url): string
    {
        $url = trim($url);
        if ($url === '' || !preg_match('/^([a-zA-Z][a-zA-Z0-9+.-]*):/', $url, $m)) {
            return $url;
        }
        return in_array(strtolower($m[1]), self::SCHEMES, true) ? $url : '';
    }

    /**
     * A URL inside markup: whatever stands before the first colon, once
     * every character reference and percent escape is decoded as deep as it
     * goes and the invisible characters are dropped, must be an allowed
     * scheme, or it is cut off and the rest is judged again. The reference
     * cuts "?q=a:b" to "b" and "javascript:alert(1)//http://" to "//" the
     * same way. A value with a good scheme is returned as given.
     *
     * @param list<string> $schemes
     */
    public static function attributeUrl(string $url, array $schemes = self::SCHEMES): string
    {
        $url = trim($url);
        for ($round = 0; $round < 8; $round++) {
            $decoded = self::deepDecode($url);
            $colon = strpos($decoded, ':');
            if ($colon === false) {
                return $url;
            }
            $scheme = strtolower(self::visible(substr($decoded, 0, $colon)));
            if (in_array($scheme, $schemes, true)) {
                return $url;
            }
            $url = self::normalizeAttribute(substr($decoded, $colon + 1));
        }
        return '';
    }

    /** A value with its references and percent escapes decoded until nothing changes, so no encoding hides a scheme. */
    private static function deepDecode(string $value): string
    {
        for ($round = 0; $round < 6; $round++) {
            $next = rawurldecode(Decoder::attribute($value));
            if ($next === $value) {
                break;
            }
            $value = $next;
        }
        return $value;
    }

    /** The text with whitespace, control characters, no-break spaces, and replacement characters removed. */
    private static function visible(string $text): string
    {
        return preg_replace('/[\s\x00-\x1F\x7F\x{A0}\x{FFFD}]+/u', '', $text)
            ?? (string) preg_replace('/[\s\x00-\x1F\x7F]+/', '', $text);
    }

    private const REFERENCE = '/&(#[0-9]+|#[xX][0-9a-fA-F]+|[a-zA-Z][a-zA-Z0-9]{0,31});/';
    private const STRAY_AMPERSAND = '/&(?!(?:#[0-9]+|#[xX][0-9a-fA-F]+|[a-zA-Z][a-zA-Z0-9]{0,31});)/';
    private const MARKUP = ['&' => '&amp;', '<' => '&lt;', '>' => '&gt;', '"' => '&quot;', "'" => '&apos;'];

    /**
     * Text between tags as the reference stores it: valid references stay
     * references (decimal ones padded to three digits), invalid ones and
     * stray ampersands become "&amp;", and a closing bracket is escaped.
     */
    private static function normalizeText(string $text): string
    {
        $text = (string) preg_replace_callback(self::REFERENCE, static function (array $m): string {
            $body = $m[1];
            if ($body[0] !== '#') {
                return self::named($body) === null ? '&amp;' . $body . ';' : '&' . $body . ';';
            }
            $code = self::codePoint($body);
            if ($code === null) {
                return '&amp;' . $body . ';';
            }
            return $body[1] === 'x' || $body[1] === 'X' ? '&' . $body . ';' : '&#' . str_pad((string) $code, 3, '0', STR_PAD_LEFT) . ';';
        }, $text);
        $text = (string) preg_replace(self::STRAY_AMPERSAND, '&amp;', $text);
        return str_replace('>', '&gt;', $text);
    }

    /**
     * An attribute value as the reference stores it: valid references become
     * their characters, except that the five markup characters stay escaped
     * (an apostrophe as &apos;); invalid references and stray ampersands
     * become "&amp;"; raw quotes are escaped.
     */
    private static function normalizeAttribute(string $value): string
    {
        $value = (string) preg_replace_callback(self::REFERENCE, static function (array $m): string {
            $body = $m[1];
            if ($body[0] !== '#') {
                $char = self::named($body);
            } else {
                $code = self::codePoint($body);
                $char = $code === null ? null : Decoder::text('&#' . $code . ';');
            }
            if ($char === null) {
                return '&amp;' . $body . ';';
            }
            return self::MARKUP[$char] ?? $char;
        }, $value);
        $value = (string) preg_replace(self::STRAY_AMPERSAND, '&amp;', $value);
        return str_replace(['"', "'"], ['&quot;', '&apos;'], $value);
    }

    /** An attribute value without the one pair of quotes that wrapped it; a value may itself end in the other quote. */
    private static function unquoted(string $value): string
    {
        $first = $value[0] ?? '';
        if (($first === '"' || $first === "'") && strlen($value) >= 2 && str_ends_with($value, $first)) {
            return substr($value, 1, -1);
        }
        return $value;
    }

    /** What a named reference stands for, or null when the name is not one. */
    private static function named(string $name): ?string
    {
        $decoded = html_entity_decode('&' . $name . ';', ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8');
        return $decoded === '&' . $name . ';' ? null : $decoded;
    }

    /** A numeric reference's code point, or null when it names no character the reference accepts (NUL, controls, surrogates, beyond Unicode). */
    private static function codePoint(string $body): ?int
    {
        $code = $body[1] === 'x' || $body[1] === 'X' ? hexdec(substr($body, 2)) : (int) substr($body, 1);
        if (!is_int($code) || $code > 0x10FFFF || ($code >= 0xD800 && $code <= 0xDFFF)) {
            return null;
        }
        return $code >= 0x20 || in_array($code, [0x09, 0x0A, 0x0D], true) ? $code : null;
    }

    /**
     * The attributes the policy allows, in the order written: a value must
     * meet its rules, a URI keeps only an allowed scheme, srcset and style are
     * judged on their own terms. A tag that loses a required attribute keeps
     * none at all.
     */
    private static function attributes(string $raw, string $tag, KsesPolicy $policy): string
    {
        $out = '';
        $kept = [];
        preg_match_all('/([a-zA-Z_:][-a-zA-Z0-9_:.]*)(?:\s*=\s*("[^"]*"|\'[^\']*\'|[^\s"\'>]+))?/', $raw, $matches, PREG_SET_ORDER);
        foreach ($matches as $attribute) {
            $name = strtolower($attribute[1]);
            $rules = $policy->rules($tag, $name);
            $value = isset($attribute[2]) ? self::normalizeAttribute(self::unquoted($attribute[2])) : null;
            if ($rules === null || !KsesValues::satisfies($value ?? '', $value === null ? 'y' : 'n', $rules)) {
                continue;
            }
            $rendered = self::rendered($name, $value, $policy);
            if ($rendered !== null) {
                $kept[$name] = true;
                $out .= $rendered;
            }
        }
        foreach ($policy->required($tag) as $name) {
            if (!isset($kept[$name])) {
                return '';
            }
        }
        return $out;
    }

    /** One allowed attribute as stored, or null when its value is judged away entirely. */
    private static function rendered(string $name, ?string $value, KsesPolicy $policy): ?string
    {
        if ($value === null) {
            return ' ' . $name;
        }
        if ($policy->holdsUri($name)) {
            $value = self::attributeUrl($value, $policy->schemes);
        } elseif ($name === 'srcset' || $name === 'style') {
            $value = $name === 'srcset' ? self::srcset($value) : self::css($value);
            if ($value === '') {
                return null;
            }
        }
        return ' ' . $name . '="' . $value . '"';
    }

    /**
     * Whether a URL may be an object's data in post content: an http or https
     * URL on the uploads host and port, no credentials, query or fragment,
     * whose path ends in ".pdf".
     */
    public static function pdfObject(string $url, string $uploadsUrl): bool
    {
        $target = parse_url($uploadsUrl);
        $parts = parse_url($url);
        if (!is_array($parts) || !is_array($target) || !isset($target['host']) || !in_array($parts['scheme'] ?? '', ['http', 'https'], true)) {
            return false;
        }
        foreach (['user', 'pass', 'query', 'fragment'] as $part) {
            if (isset($parts[$part])) {
                return false;
            }
        }
        $sameHost = ($parts['host'] ?? null) === $target['host'] && ($parts['port'] ?? null) === ($target['port'] ?? null);
        return $sameHost && str_ends_with($parts['path'] ?? '', '.pdf');
    }

    /** Every candidate must be an absolute URL with a safe scheme, or the attribute goes. */
    private static function srcset(string $value): string
    {
        foreach (explode(',', $value) as $candidate) {
            $url = trim(explode(' ', trim(self::deepDecode($candidate)))[0] ?? '');
            if ($url === '' || !preg_match('#^(?:https?:)?//#', $url) && !str_starts_with($url, '/')) {
                return '';
            }
        }
        return $value;
    }

    /** A style attribute's value with only the listed properties kept. */
    public static function style(string $style): string
    {
        return self::css($style);
    }

    /**
     * Listed properties plus custom properties (the reference keeps
     * --my-var:4px); no url() outside images, relative image urls allowed
     * (the reference keeps url(x.png)); no expression, behavior, script,
     * or data: anywhere.
     */
    private static function css(string $style): string
    {
        $kept = [];
        foreach (explode(';', $style) as $declaration) {
            if (!str_contains($declaration, ':')) {
                continue;
            }
            [$property, $value] = array_map('trim', explode(':', $declaration, 2));
            $property = strtolower($property);
            $custom = preg_match('/^--[a-z0-9_-]+$/', $property) === 1;
            if ((!$custom && !in_array($property, self::CSS_PROPERTIES, true)) || $value === '') {
                continue;
            }
            if (preg_match('/expression|behavior|javascript|vbscript|@import|data:|\\\\|[<>{}]/i', $value)) {
                continue;
            }
            if (preg_match('/url\s*\(/i', $value)) {
                $image = in_array($property, ['background', 'background-image', 'list-style', 'list-style-image'], true);
                if (!$image || !preg_match('/^[^()]*url\s*\(\s*["\']?[^"\')]*["\']?\s*\)[^()]*$/i', $value)) {
                    continue;
                }
            }
            $kept[] = $property . ':' . $value;
        }
        return implode(';', $kept);
    }
}
