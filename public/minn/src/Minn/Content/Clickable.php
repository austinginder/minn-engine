<?php

declare(strict_types=1);

namespace Minn\Content;

use Closure;
use Minn\Html;

/**
 * Bare addresses in text made links (make_clickable, probe
 * formatting-callbacks). Only text is read, never tags or comments, and
 * nothing inside code, pre, script or style. An address must start the text
 * or follow whitespace or "("; trailing . , ; : ! ? stay outside it. URLs
 * (http, https, ftp) are found first, then www. and ftp. hosts, then email
 * addresses; each is handed to its callback, which builds the link. A link
 * made as the whole text of an existing link is unwrapped into it.
 */
final class Clickable
{
    private const SKIP = 'code|pre|script|style';
    private const BEFORE = '(^|[\s(])';
    private const TRAILING = '([.,;:!?]*)(?=[\s<>"]|$)';

    /**
     * The text with its addresses made links: $url gets [whole, before,
     * address, trailing], $web the same for a www. or ftp. host, $email
     * [whole, before, user, domain].
     *
     * @param Closure(list<string>): string $url
     * @param Closure(list<string>): string $web
     * @param Closure(list<string>): string $email
     */
    public static function make(string $text, Closure $url, Closure $web, Closure $email): string
    {
        $pieces = Html::split($text);
        $skipping = 0;
        foreach ($pieces as $i => $piece) {
            if ($i % 2 === 1) {
                if (preg_match('#^<(' . self::SKIP . ')[\s>]#i', $piece) === 1) {
                    $skipping++;
                } elseif (preg_match('#^</(' . self::SKIP . ')>#i', $piece) === 1 && $skipping > 0) {
                    $skipping--;
                }
                continue;
            }
            if ($skipping === 0 && $piece !== '') {
                $pieces[$i] = self::linked($piece, $url, $web, $email);
            }
        }
        return self::unwrapNested(implode('', $pieces));
    }

    /**
     * A URL's trailing ")" taken off while the URL closes more parentheses
     * than it opens: [the URL, what was taken off].
     *
     * @return array{0: string, 1: string}
     */
    public static function unbalancedClosers(string $url): array
    {
        $after = '';
        while (str_ends_with($url, ')') && substr_count($url, ')') > substr_count($url, '(')) {
            $url = substr($url, 0, -1);
            $after = ')' . $after;
        }
        return [$url, $after];
    }

    private static function linked(string $text, Closure $url, Closure $web, Closure $email): string
    {
        $text = (string) preg_replace_callback('~' . self::BEFORE . '((?:https?|ftp)://[^\s<>"]+?)' . self::TRAILING . '~i', static fn (array $m): string => $url($m), $text);
        $text = (string) preg_replace_callback('~' . self::BEFORE . '((?:www|ftp)\.[^\s<>"]+?)' . self::TRAILING . '~i', static fn (array $m): string => $web($m), $text);
        return (string) preg_replace_callback('~' . self::BEFORE . '([.0-9a-z_+-]+)@((?:[0-9a-z-]+\.)+[0-9a-z]{2,})~i', static fn (array $m): string => $email($m), $text);
    }

    /** A link that is the whole text of another link gives the outer one its text. */
    private static function unwrapNested(string $html): string
    {
        return (string) preg_replace('#(<a\s[^>]+?>|<a>)<a [^>]+?>([^>]+?)</a></a>#i', '$1$2</a>', $html);
    }
}
