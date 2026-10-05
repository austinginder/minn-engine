<?php

declare(strict_types=1);

namespace Minn\Support;

use Closure;
use Minn\Blocks\Block;
use Minn\Blocks\Parser;
use Minn\Blocks\Serializer;
use Minn\Html\Decoder;

/**
 * The HTML a user without unfiltered_html may store. Tags outside the
 * allowlist are removed and their text kept; attributes outside the tag's
 * list are dropped; URL attributes lose unsafe schemes; style attributes
 * keep only listed properties and no code. Comments keep their text, and
 * block delimiters are written back with their attribute values filtered.
 */
final class Kses
{
    /** @var array<string, list<string>> the smaller set for comments and descriptions */
    public const COMMENT = [
        'a' => ['href', 'title', 'rel'], 'abbr' => ['title'], 'acronym' => ['title'], 'b' => [],
        'blockquote' => ['cite'], 'cite' => [], 'code' => [], 'del' => ['datetime'], 'em' => [], 'i' => [],
        'q' => ['cite'], 's' => [], 'strike' => [], 'strong' => [],
    ];

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
        return self::sanitize($html, KsesPolicy::post());
    }

    /** A comment, profile or term description as anyone without unfiltered_html may store it: listed attributes only. */
    public static function comment(string $html): string
    {
        return self::sanitize($html, KsesPolicy::comment());
    }

    /**
     * The whole pass wp_kses makes with its default hooks: control
     * characters out, references normalized, a "<" that opens no tag
     * escaped, block attribute values filtered the same way, then the tags.
     */
    public static function sanitize(string $html, KsesPolicy $policy): string
    {
        $html = self::normalizeEntities(self::withoutControls($html));
        $html = self::blockAttributes(self::lessThan($html), static fn (string $value): string => self::sanitize($value, $policy));
        return self::filter($html, $policy);
    }

    /** The control characters kses removes (tab, newline and carriage return stay). */
    public static function withoutControls(string $html): string
    {
        return (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $html);
    }

    /**
     * Every "&" made a reference, in one pass: a known name or a code point
     * kses accepts stays one (decimal padded to three digits, hex with a
     * lower-case x), anything else becomes "&amp;".
     */
    public static function normalizeEntities(string $html): string
    {
        return (string) preg_replace_callback('/&(?:([A-Za-z]{2,8}[0-9]{0,8});|#(0*[0-9]{1,7});|#([Xx]0*[0-9A-Fa-f]{1,6});)?/', static function (array $m): string {
            if (($m[1] ?? '') !== '') {
                return KsesEntities::known($m[1]) ? $m[0] : '&amp;' . substr($m[0], 1);
            }
            if (($m[2] ?? '') !== '') {
                $code = self::codePoint('#' . $m[2]);
                return $code === null ? '&amp;' . substr($m[0], 1) : '&#' . str_pad((string) $code, 3, '0', STR_PAD_LEFT) . ';';
            }
            if (($m[3] ?? '') !== '') {
                return self::codePoint('#' . $m[3]) === null ? '&amp;' . substr($m[0], 1) : '&#x' . substr($m[3], 1) . ';';
            }
            return '&amp;';
        }, $html);
    }

    /**
     * A "<" that reaches the next "<" or the end without a ">" is text: the
     * run is escaped the way esc_html escapes it (quotes included).
     *
     * @param (Closure(string): string)|null $escape
     */
    public static function lessThan(string $html, ?Closure $escape = null): string
    {
        $escape ??= self::escapeHtml(...);
        return (string) preg_replace_callback('%<[^>]*?((?=<)|>|$)%', static fn (array $m): string => str_contains($m[0], '>') ? $m[0] : $escape($m[0]), $html);
    }

    /** Text escaped for HTML with references kept: invalid UTF-8 gives nothing, quotes become &quot; and &#039;. */
    public static function escapeHtml(string $text): string
    {
        if (!Utf8::isValid($text)) {
            return '';
        }
        return Entities::specialchars(self::normalizeEntities($text), ENT_QUOTES, false, KsesEntities::known(...));
    }

    /**
     * Block markup with every attribute key and string value passed through
     * the same filter, and the delimiters written back in their canonical
     * form ("--->" read as "-->"). Markup without a comment is left alone.
     *
     * @param Closure(string): string $clean
     */
    public static function blockAttributes(string $html, Closure $clean): string
    {
        if (!str_contains($html, '<!--')) {
            return $html;
        }
        if (str_contains($html, '--->')) {
            $html = (string) preg_replace_callback('%<!--(.*?)--->%', static fn (array $m): string => '<!--' . rtrim($m[1], '-') . '-->', $html);
        }
        $filter = static function (Block $block) use (&$filter, $clean): Block {
            return new Block($block->name, self::cleanValue($block->attrs, $clean), array_map($filter, $block->innerBlocks), $block->innerHtml, $block->innerContent);
        };
        return Serializer::blocks(array_map($filter, Parser::parse($html)));
    }

    /** @param Closure(string): string $clean */
    private static function cleanValue(mixed $value, Closure $clean): mixed
    {
        if (is_string($value)) {
            return $clean($value);
        }
        if (!is_array($value)) {
            return $value;
        }
        $out = [];
        foreach ($value as $key => $inner) {
            $out[is_string($key) ? $clean($key) : $key] = self::cleanValue($inner, $clean);
        }
        return $out;
    }

    /**
     * The tag pass: each run from "<" to the next ">" (or the end) is a
     * comment, an inert bogus comment ("</" before a non-letter, "<!"
     * before a lower-case letter), or an element judged by the policy;
     * anything else in angle brackets goes. Text between keeps its
     * references, with stray ones escaped. Run alone (wp_kses_split), a
     * last "<" with no ">" still reads as a tag; inside sanitize() the
     * less-than pass has already escaped it.
     */
    public static function filter(string $html, KsesPolicy $policy): string
    {
        return (string) preg_replace_callback(
            '/<[^>]*(?:>|$)|[^<]+/',
            static fn (array $m): string => $m[0][0] === '<' ? self::run($m[0], $policy) : self::normalizeText($m[0]),
            $html,
        );
    }

    /** One bracketed run as the tag pass keeps it, or nothing. */
    private static function run(string $run, KsesPolicy $policy): string
    {
        $closed = str_ends_with($run, '>');
        if ($closed && str_starts_with($run, '<!--')) {
            return self::htmlComment(substr($run, 4));
        }
        if ($closed && (preg_match('#^</[^a-zA-Z>]#', $run) || preg_match('#^<![a-z]#', $run))) {
            return substr($run, 0, 2) . self::normalizeText(substr($run, 2, -1)) . '>';
        }
        if (!preg_match('%^<\s*(/\s*)?([a-zA-Z0-9-]+)([^>]*)>?$%', $run, $m)) {
            return '';
        }
        [, $closing, $name, $rest] = $m;
        $tag = strtolower($name);
        if (!$policy->allowsTag($tag)) {
            return '';
        }
        if ($closing !== '') {
            return "</{$name}>";
        }
        return '<' . $name . self::attributes($rest, $tag, $policy) . (str_ends_with(rtrim($rest), '/') ? ' />' : '>');
    }

    /** A comment kept with its body as text: no "--" inside, an empty one dropped. */
    private static function htmlComment(string $body): string
    {
        if (str_ends_with($body, '-->')) {
            $body = substr($body, 0, -3);
        }
        while (str_contains($body, '--')) {
            $body = str_replace('--', '-', $body);
        }
        $body = self::normalizeText($body);
        return $body === '' ? '' : '<!--' . $body . '-->';
    }

    /**
     * The attributes written inside a tag, read the way kses reads them: a
     * name, then "=" and a quoted or bare value, or no value at all; junk
     * between (stray quotes, "=", "/") is skipped; the first of two
     * same-named attributes wins. Null when a quoted value never closes,
     * which costs the tag every attribute.
     *
     * @return array<string, array{name: string, value: ?string, whole: string}>|null
     */
    public static function attributeList(string $raw): ?array
    {
        $out = [];
        $rest = $raw;
        while (($rest = ltrim($rest)) !== '') {
            // A name runs to whitespace, "=", a quote or "/", so "@class" is one name and never "class".
            if (!preg_match('#^[^\\s"\'=/]+#', $rest, $m)) {
                $rest = (string) preg_replace('/^(?:"[^"]*(?:"|$)|\'[^\']*(?:\'|$)|\S)/', '', $rest, 1);
                continue;
            }
            $name = strtolower($m[0]);
            $whole = $m[0];
            $rest = substr($rest, strlen($m[0]));
            $value = null;
            if (preg_match('/^\s*=\s*/', $rest, $eq)) {
                $rest = substr($rest, strlen($eq[0]));
                $quote = $rest[0] ?? '';
                if ($quote === '"' || $quote === "'") {
                    $close = strpos($rest, $quote, 1);
                    if ($close === false) {
                        return null;
                    }
                    $value = substr($rest, 1, $close - 1);
                    $whole .= $eq[0] . substr($rest, 0, $close + 1);
                    $rest = substr($rest, $close + 1);
                } else {
                    preg_match('/^\S*/', $rest, $bare);
                    $value = $bare[0];
                    $whole .= $eq[0] . $value;
                    $rest = substr($rest, strlen($value));
                }
            }
            $out[$name] ??= ['name' => $name, 'value' => $value, 'whole' => $whole];
        }
        return $out;
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
        foreach (self::attributeList($raw) ?? [] as $name => $attribute) {
            $rules = $policy->rules($tag, $name);
            $value = $attribute['value'] === null ? null : self::normalizeAttribute($attribute['value']);
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
