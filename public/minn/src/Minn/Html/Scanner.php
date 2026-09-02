<?php

declare(strict_types=1);

namespace Minn\Html;

/**
 * Reads one token's shape out of raw HTML at an offset: a tag with its
 * name and attributes, a comment of one of the reference's kinds, a
 * doctype, or a processing instruction. Pure and stateless; null means the
 * input ends before the token does, which is where a streaming reader pauses.
 */
final class Scanner
{
    /**
     * A tag starting at $at, its name from $nameStart (one byte in for an
     * opener, two for a closer): the name, where it ends, the end offset,
     * the attributes with their source offsets, and the self-closing flag.
     *
     * @return array{name: string, nameEnd: int, end: int, attributes: list<array{name: string, lower: string, start: int, end: int, value: ?string, quoted: bool}>, selfClosing: bool}|null
     */
    public static function tag(string $html, int $at, int $nameStart): ?array
    {
        $length = strlen($html);
        $closer = $nameStart - $at === 2;
        $i = $nameStart;
        while ($i < $length && !self::isSpace($html[$i]) && $html[$i] !== '/' && $html[$i] !== '>') {
            $i++;
        }
        $name = strtolower(substr($html, $nameStart, $i - $nameStart));
        $nameEnd = $i;
        $attributes = [];
        $selfClosing = false;
        while (true) {
            while ($i < $length && (self::isSpace($html[$i]) || $html[$i] === '/')) {
                $i++;
            }
            if ($i >= $length) {
                return null;
            }
            if ($html[$i] === '>') {
                $selfClosing = $i > $nameEnd && $html[$i - 1] === '/' && ($i - 1 > $nameEnd || !$closer);
                // The flag is the slash immediately before ">" that is not part of an unquoted value.
                if ($attributes !== [] && end($attributes)['end'] === $i && end($attributes)['value'] !== null && !end($attributes)['quoted']) {
                    $selfClosing = false;
                }
                $i++;
                break;
            }
            $attribute = self::attribute($html, $i);
            if ($attribute === null) {
                return null;
            }
            [$i, $attrName, $attrStart, $attrEnd, $value, $quoted] = $attribute;
            if ($attrName === '') {
                // A stray character such as a quote before "=": consume it as a name.
                $i = max($i, $attrStart + 1);
                continue;
            }
            $attributes[] = ['name' => $attrName, 'lower' => strtolower($attrName), 'start' => $attrStart, 'end' => $attrEnd, 'value' => $value, 'quoted' => $quoted];
        }
        return ['name' => $name, 'nameEnd' => $nameEnd, 'end' => $i, 'attributes' => $attributes, 'selfClosing' => $selfClosing];
    }

    /**
     * A raw-text element's body (script, style, textarea, ...): from $from to
     * its closing tag, or null when the closer never comes.
     *
     * @return array{textStart: int, textLength: int, end: int}|null
     */
    public static function rawText(string $html, string $name, int $from): ?array
    {
        $closeAt = stripos($html, '</' . $name, $from);
        if ($closeAt === false) {
            return null;
        }
        $gt = strpos($html, '>', $closeAt);
        if ($gt === false) {
            return null;
        }
        return ['textStart' => $from, 'textLength' => $closeAt - $from, 'end' => $gt + 1];
    }

    /**
     * What "<!" opens at $at: an HTML comment (abruptly closed or not), a
     * doctype, a CDATA lookalike, or a bogus comment.
     *
     * @return array{kind: string, start: int, end: int, textStart: int, textLength: int, commentType?: string, fullStart?: int, fullLength?: int}|null
     */
    public static function markupDeclaration(string $html, int $at): ?array
    {
        if (substr($html, $at, 4) === '<!--') {
            if (substr($html, $at, 5) === '<!-->') {
                return self::comment($at, $at + 5, $at + 4, 0, Tags::COMMENT_ABRUPT, $at + 4, 0);
            }
            if (substr($html, $at, 6) === '<!--->') {
                return self::comment($at, $at + 6, $at + 4, 0, Tags::COMMENT_ABRUPT, $at + 4, 0);
            }
            $search = $at + 4;
            while (true) {
                $dashes = strpos($html, '--', $search);
                if ($dashes === false) {
                    return null;
                }
                $after = substr($html, $dashes + 2, 2);
                if (($after[0] ?? '') === '>') {
                    return self::comment($at, $dashes + 3, $at + 4, $dashes - $at - 4, Tags::COMMENT_HTML, $at + 4, $dashes - $at - 4);
                }
                if ($after === '!>') {
                    return self::comment($at, $dashes + 4, $at + 4, $dashes - $at - 4, Tags::COMMENT_HTML, $at + 4, $dashes - $at - 4);
                }
                $search = $dashes + 1;
            }
        }
        if (strtoupper(substr($html, $at, 9)) === '<!DOCTYPE') {
            $gt = strpos($html, '>', $at + 9);
            if ($gt === false) {
                return null;
            }
            return ['kind' => 'doctype', 'start' => $at, 'end' => $gt + 1, 'textStart' => $at + 9, 'textLength' => $gt - $at - 9];
        }
        // Bogus comment: "<!" up to the next ">".
        $gt = strpos($html, '>', $at + 2);
        if ($gt === false) {
            return null;
        }
        if (substr($html, $at, 9) === '<![CDATA[' && substr($html, $gt - 2, 2) === ']]' && $gt - 2 >= $at + 9) {
            return self::comment($at, $gt + 1, $at + 9, $gt - 2 - $at - 9, Tags::COMMENT_CDATA, $at + 2, $gt - $at - 2);
        }
        return self::comment($at, $gt + 1, $at + 2, $gt - $at - 2, Tags::COMMENT_INVALID, $at + 2, $gt - $at - 2);
    }

    /**
     * What "<?" opens at $at: a PHP tag as a processing instruction, another
     * PI-shaped run as a comment lookalike, or a plain bogus comment.
     *
     * @return array{kind: string, start: int, end: int, textStart: int, textLength: int, commentType?: string, fullStart?: int, fullLength?: int}|null
     */
    public static function question(string $html, int $at): ?array
    {
        $gt = strpos($html, '>', $at + 2);
        if ($gt === false) {
            return null;
        }
        if (strtolower(substr($html, $at, 5)) === '<?php' && substr($html, $gt - 1, 1) === '?') {
            // PHP tags read as processing instructions with a "php" target.
            $bodyStart = $at + 5;
            if ($bodyStart < $gt - 1 && self::isSpace($html[$bodyStart])) {
                $bodyStart++;
            }
            return ['kind' => 'pi', 'start' => $at, 'end' => $gt + 1, 'textStart' => $bodyStart, 'textLength' => max(0, $gt - 1 - $bodyStart)];
        }
        if ($html[$gt - 1] === '?' && preg_match('/^<\?([a-zA-Z][a-zA-Z0-9]*)/', substr($html, $at, min(64, $gt - $at)), $m)) {
            $bodyStart = $at + 2 + strlen($m[1]);
            return self::comment($at, $gt + 1, $bodyStart, max(0, $gt - 1 - $bodyStart), Tags::COMMENT_PI, $at + 1, $gt - $at - 1);
        }
        return self::comment($at, $gt + 1, $at + 2, $gt - $at - 2, Tags::COMMENT_HTML, $at + 2, $gt - $at - 2);
    }

    /**
     * A doctype's body split into its name and public and system identifiers.
     *
     * @return array{name: ?string, public: ?string, system: ?string}
     */
    public static function doctype(string $body): array
    {
        $body = trim($body);
        if ($body === '') {
            return ['name' => null, 'public' => null, 'system' => null];
        }
        preg_match('/^(\S+)\s*(.*)$/s', $body, $m);
        $name = strtolower($m[1]);
        $rest = $m[2] ?? '';
        $public = null;
        $system = null;
        if (preg_match('/^PUBLIC\s+(["\'])(.*?)\1\s*(?:(["\'])(.*?)\3)?/is', $rest, $pm)) {
            $public = $pm[2];
            $system = isset($pm[4]) ? $pm[4] : null;
        } elseif (preg_match('/^SYSTEM\s+(["\'])(.*?)\1/is', $rest, $sm)) {
            $system = $sm[2];
        }
        return ['name' => $name, 'public' => $public, 'system' => $system];
    }

    /** Whether a byte is HTML whitespace. */
    public static function isSpace(string $c): bool
    {
        return $c === ' ' || $c === "\t" || $c === "\n" || $c === "\r" || $c === "\f";
    }

    /**
     * One attribute from $i: its name (a leading "=" is part of it), then an
     * optional quoted or bare value. Null when the input ends first.
     *
     * @return array{int, string, int, int, ?string, bool}|null offset after, name, start, end, value, quoted
     */
    private static function attribute(string $html, int $i): ?array
    {
        $length = strlen($html);
        $attrStart = $i;
        if ($html[$i] === '=') {
            $i++;
        }
        while ($i < $length && !self::isSpace($html[$i]) && $html[$i] !== '/' && $html[$i] !== '>' && $html[$i] !== '=') {
            $i++;
        }
        $attrName = substr($html, $attrStart, $i - $attrStart);
        $j = $i;
        while ($j < $length && self::isSpace($html[$j])) {
            $j++;
        }
        $value = null;
        $quoted = false;
        $attrEnd = $i;
        if ($j < $length && $html[$j] === '=') {
            $j++;
            while ($j < $length && self::isSpace($html[$j])) {
                $j++;
            }
            if ($j >= $length) {
                return null;
            }
            $quote = $html[$j];
            if ($quote === '"' || $quote === "'") {
                $close = strpos($html, $quote, $j + 1);
                if ($close === false) {
                    return null;
                }
                $value = substr($html, $j + 1, $close - $j - 1);
                $quoted = true;
                $attrEnd = $close + 1;
            } else {
                $k = $j;
                while ($k < $length && !self::isSpace($html[$k]) && $html[$k] !== '>') {
                    $k++;
                }
                $value = substr($html, $j, $k - $j);
                $attrEnd = $k;
            }
            $i = $attrEnd;
        }
        return [$i, $attrName, $attrStart, $attrEnd, $value, $quoted];
    }

    /** @return array{kind: string, start: int, end: int, textStart: int, textLength: int, commentType: string, fullStart: int, fullLength: int} */
    private static function comment(int $start, int $end, int $textStart, int $textLength, string $type, int $fullStart, int $fullLength): array
    {
        return ['kind' => 'comment', 'start' => $start, 'end' => $end, 'textStart' => $textStart, 'textLength' => $textLength, 'commentType' => $type, 'fullStart' => $fullStart, 'fullLength' => $fullLength];
    }
}
