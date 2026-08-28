<?php

declare(strict_types=1);

namespace Minn\Support;

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

    private const GLOBAL_ATTRIBUTES = ['class', 'id', 'style', 'title', 'role', 'dir', 'lang', 'xml:lang', 'hidden', 'tabindex'];
    private const URL_ATTRIBUTES = ['href', 'src', 'cite', 'poster', 'longdesc', 'usemap'];
    private const SCHEMES = ['http', 'https', 'ftp', 'ftps', 'mailto', 'news', 'irc', 'gopher', 'nntp', 'feed', 'telnet', 'mms', 'rtsp', 'sms', 'svn', 'tel', 'fax', 'xmpp', 'webcal', 'urn'];
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

    /** @param array<string, list<string>> $allowed */
    public static function filter(string $html, array $allowed): string
    {
        return (string) preg_replace_callback(
            '/<!--.*?-->|<\/?([a-zA-Z][a-zA-Z0-9-]*)((?:\s+[^\s=>\/]+(?:\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s"\'>]+))?)*)\s*(\/?)>|<[^>]*>?/s',
            static function (array $m) use ($allowed): string {
                if (str_starts_with($m[0], '<!--')) {
                    return $m[0];
                }
                $tag = strtolower($m[1] ?? '');
                if ($tag === '' || !isset($allowed[$tag])) {
                    return '';
                }
                if (str_starts_with($m[0], '</')) {
                    return "</{$tag}>";
                }
                return '<' . $tag . self::attributes($m[2] ?? '', $allowed[$tag]) . (($m[3] ?? '') === '/' ? ' />' : '>');
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

    /** A URL inside markup: an unsafe scheme is cut off and the rest kept, as the reference does. */
    public static function attributeUrl(string $url): string
    {
        $url = trim($url);
        while (preg_match('/^([a-zA-Z][a-zA-Z0-9+.\s-]*):/', $url, $m)) {
            $scheme = strtolower((string) preg_replace('/\s+/', '', $m[1]));
            if (in_array($scheme, self::SCHEMES, true)) {
                return $url;
            }
            $url = substr($url, strlen($m[0]));
        }
        return $url;
    }

    /** @param list<string> $allowedForTag */
    private static function attributes(string $raw, array $allowedForTag): string
    {
        $out = '';
        preg_match_all('/([a-zA-Z_:][-a-zA-Z0-9_:.]*)(?:\s*=\s*("[^"]*"|\'[^\']*\'|[^\s"\'>]+))?/', $raw, $matches, PREG_SET_ORDER);
        foreach ($matches as $attribute) {
            $name = strtolower($attribute[1]);
            $value = isset($attribute[2]) ? trim($attribute[2], '"\'') : null;
            $permitted = in_array($name, $allowedForTag, true)
                || in_array($name, self::GLOBAL_ATTRIBUTES, true)
                || str_starts_with($name, 'aria-')
                || str_starts_with($name, 'data-');
            if (!$permitted) {
                continue;
            }
            if ($value === null) {
                $out .= ' ' . $name;
                continue;
            }
            if (in_array($name, self::URL_ATTRIBUTES, true)) {
                $value = self::attributeUrl($value);
            } elseif ($name === 'srcset') {
                $value = self::srcset($value);
                if ($value === '') {
                    continue;
                }
            } elseif ($name === 'style') {
                $value = self::css($value);
                if ($value === '') {
                    continue;
                }
            }
            $out .= ' ' . $name . '="' . str_replace('"', '&quot;', $value) . '"';
        }
        return $out;
    }

    /** Every candidate must be an absolute URL with a safe scheme, or the attribute goes. */
    private static function srcset(string $value): string
    {
        foreach (explode(',', $value) as $candidate) {
            $url = trim(explode(' ', trim($candidate))[0] ?? '');
            if ($url === '' || !preg_match('#^(?:https?:)?//#', $url) && !str_starts_with($url, '/')) {
                return '';
            }
        }
        return $value;
    }

    /** Listed properties only; no url() outside images, and no expression, behavior, or script anywhere. */
    private static function css(string $style): string
    {
        $kept = [];
        foreach (explode(';', $style) as $declaration) {
            if (!str_contains($declaration, ':')) {
                continue;
            }
            [$property, $value] = array_map('trim', explode(':', $declaration, 2));
            $property = strtolower($property);
            if (!in_array($property, self::CSS_PROPERTIES, true) || $value === '') {
                continue;
            }
            if (preg_match('/expression|behavior|javascript|vbscript|@import|\\\\|[<>{}]/i', $value)) {
                continue;
            }
            if (preg_match('/url\s*\(/i', $value)) {
                $image = in_array($property, ['background', 'background-image', 'list-style', 'list-style-image'], true);
                if (!$image || !preg_match('/^[^()]*url\s*\(\s*["\']?(?:https?:)?\/[^"\')]*["\']?\s*\)[^()]*$/i', $value)) {
                    continue;
                }
            }
            $kept[] = $property . ':' . $value;
        }
        return implode(';', $kept);
    }
}
