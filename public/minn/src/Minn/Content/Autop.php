<?php

declare(strict_types=1);

namespace Minn\Content;

/**
 * Classic-content paragraphing (wpautop): blank lines become paragraphs,
 * single newlines become line breaks when asked, and block-level tags are
 * never wrapped. Rebuilt against the reference's output on a corpus of
 * fuzzed and real content plus targeted experiments (probe autop); every
 * rule below is one that corpus demanded, written as the behaviour seen,
 * in the order it applies:
 *
 * - <pre> elements are cut out first and put back untouched at the end.
 * - A pair of <br> tags is a paragraph break; block tags get blank lines
 *   around them, so they always sit outside paragraphs.
 * - Newlines inside a tag, comment or CDATA section (an unclosed one runs
 *   to the end) are held aside, so they never split a paragraph and come
 *   back as plain newlines. While held, such a newline ends the tag as far
 *   as the block rules can tell.
 * - Whitespace around object, param, embed, audio, video, source, track,
 *   figcaption and option tags is squeezed out when those tags are used.
 * - Blank-line separated runs become <p> paragraphs, then paragraphs that
 *   only hold a block tag lose their <p> and </p>.
 * - With line breaks on, remaining newlines become <br />, except inside
 *   script, style, svg and math elements, and breaks next to block tags go.
 */
final class Autop
{
    /** Tag names that open and close blocks; the cleanup rules match them as name prefixes, case-sensitively. */
    private const BLOCK = '(?:table|thead|tfoot|caption|col|colgroup|tbody|tr|td|th|div|dl|dd|dt|ul|ol|li|pre'
        . '|form|map|area|blockquote|address|style|p|h[1-6]|hr|fieldset|legend|section|article|aside|hgroup'
        . '|header|footer|nav|figure|figcaption|details|menu|summary)';

    /** Tags (again by name prefix) that drop a <br /> written just before them. */
    private const BREAK_DROPPERS = '(?:p|li|div|dl|dd|dt|th|pre|td|ul|ol)';

    /**
     * Core of a held newline. It reads as a tag of its own: a run of plain
     * text stops at its "<" and a tag match ends at its ">", which is how a
     * held newline keeps the block rules from reaching past it.
     */
    private const HELD_CORE = "<\u{E000}>";

    /** A newline inside a tag, comment or CDATA section while paragraphs are made. */
    private const HELD_NEWLINE = ' ' . self::HELD_CORE . ' ';

    /** A newline inside script, style, svg or math while line breaks are added. */
    private const RAW_NEWLINE = "\u{E001}";

    /** The classic paragraph rules: a blank line makes a paragraph, a single newline a line break when asked. */
    public static function apply(string $text, bool $lineBreaks = true): string
    {
        if (trim($text) === '') {
            return '';
        }
        [$text, $stash] = self::stashPre($text . "\n");
        $text = self::spaceBlocks($text);
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = self::holdTagNewlines($text);
        $text = self::squeezeMedia($text);
        $text = self::squeezeFormParts($text);
        $text = self::tidyParagraphs(self::wrapParagraphs($text));
        if ($lineBreaks) {
            $text = self::addLineBreaks($text);
        }
        $text = self::tidyBreaks($text);
        // A newline left just before the closing </p> of the last paragraph goes.
        $text = (string) preg_replace('#\n</p>(?=\n?\z)#', '</p>', $text);
        $text = strtr($text, $stash);
        return str_replace([self::HELD_NEWLINE, self::HELD_CORE], "\n", $text);
    }

    /** Cuts each <pre> element out and leaves a stand-in that acts as an empty block. */
    private static function stashPre(string $text): array
    {
        if (!str_contains($text, '<pre')) {
            return [$text, []];
        }
        // The text is cut at every "</pre>". A piece holding "<pre" gives up
        // everything from there on; a piece without one keeps its text and
        // its stray "</pre>" is dropped. Text after the last "</pre>" stays.
        $pieces = explode('</pre>', $text);
        $tail = (string) array_pop($pieces);
        $kept = '';
        $stash = [];
        foreach ($pieces as $piece) {
            $start = strpos($piece, '<pre');
            if ($start === false) {
                $kept .= $piece;
                continue;
            }
            $key = '<pre data-minn-pre="' . count($stash) . '"></pre>';
            $stash[$key] = substr($piece, $start) . '</pre>';
            $kept .= substr($piece, 0, $start) . $key;
        }
        return [$kept . $tail, $stash];
    }

    /** Turns <br> pairs into blank lines and puts blank lines before block openers and after block closers. */
    private static function spaceBlocks(string $text): string
    {
        $text = (string) preg_replace('#<br\s*/?>\s*<br\s*/?>#', "\n\n", $text);
        $text = (string) preg_replace('#<' . self::BLOCK . '[\s/>]#', "\n\n\$0", $text);
        $text = (string) preg_replace('#</' . self::BLOCK . '>#', "\$0\n\n", $text);
        return (string) preg_replace('#<hr\s*/?>#', "\$0\n\n", $text);
    }

    /** Holds newlines found inside tags, comments and CDATA sections. */
    private static function holdTagNewlines(string $text): string
    {
        // A comment opens at "<!--" and closes at the first "-->" after the
        // "<!" (so "<!-->" is complete); CDATA closes at the first "]]>"; any
        // other tag closes at the first ">". An unclosed one runs to the end.
        return (string) preg_replace_callback(
            '#<!(?=--).*?(?:-->|\z)|<!\[CDATA\[.*?(?:\]\]>|\z)|<[^>]*>?#s',
            static fn (array $m): string => str_replace("\n", self::HELD_NEWLINE, $m[0]),
            $text,
        );
    }

    /** Squeezes whitespace around embedded media tags, each family only when the text uses it. */
    private static function squeezeMedia(string $text): string
    {
        if (str_contains($text, '</object>')) {
            $text = (string) preg_replace('#(<object[^>]*>)\s*#', '$1', $text);
            $text = (string) preg_replace('#\s*</object>#', '</object>', $text);
            $text = (string) preg_replace('#\s*(</?(?:param|embed)[^>]*>)\s*#', '$1', $text);
        }
        if (str_contains($text, '<source') || str_contains($text, '<track')) {
            $text = (string) preg_replace('#([<\[](?:audio|video)[^>\]]*[>\]])\s*#', '$1', $text);
            $text = (string) preg_replace('#\s*([<\[]/(?:audio|video)[>\]])#', '$1', $text);
            $text = (string) preg_replace('#\s*(<(?:source|track)[^>]*>)\s*#', '$1', $text);
        }
        return $text;
    }

    /** Squeezes whitespace before figcaption and option openers and after their closers, when used. */
    private static function squeezeFormParts(string $text): string
    {
        if (str_contains($text, '<figcaption')) {
            $text = (string) preg_replace('#\s*(<figcaption[^>]*>)#', '$1', $text);
            $text = (string) preg_replace('#</figcaption>\s*#', '</figcaption>', $text);
        }
        if (str_contains($text, '<option')) {
            $text = (string) preg_replace('#\s*<option#', '<option', $text);
            $text = (string) preg_replace('#</option>\s*#', '</option>', $text);
        }
        return $text;
    }

    /** Wraps every blank-line separated run in <p>...</p>, trimming only its outer newlines. */
    private static function wrapParagraphs(string $text): string
    {
        $wrapped = '';
        foreach (preg_split('#\n\s*\n#', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $run) {
            $wrapped .= '<p>' . trim($run, "\n") . "</p>\n";
        }
        return $wrapped;
    }

    /** Removes empty paragraphs and the <p> or </p> that would wrap a block tag. */
    private static function tidyParagraphs(string $text): string
    {
        $block = self::BLOCK;
        $text = (string) preg_replace('#<p>\s*</p>#', '', $text);
        $text = (string) preg_replace('#<p>([^<]+)</(div|address|form)>#', '<p>$1</p></$2>', $text);
        $text = (string) preg_replace('#<p>\s*(</?' . $block . '[^>]*>)\s*</p>#', '$1', $text);
        $text = (string) preg_replace('#<p>(<li.+?)</p>#', '$1', $text);
        $text = (string) preg_replace('#<p><blockquote([^>]*)>#i', '<blockquote$1><p>', $text);
        $text = str_replace('</blockquote></p>', '</p></blockquote>', $text);
        $text = (string) preg_replace('#<p>\s*(</?' . $block . '[^>]*>)#', '$1', $text);
        return (string) preg_replace('#(</?' . $block . '[^>]*>)\s*</p>#', '$1', $text);
    }

    /** Writes <br> as <br /> and turns each newline (and the whitespace before it) into a line break. */
    private static function addLineBreaks(string $text): string
    {
        $text = str_replace(['<br>', '<br/>'], '<br />', $text);
        $text = (string) preg_replace_callback(
            '#<(script|style|svg|math).*?</\1>#s',
            static fn (array $m): string => str_replace("\n", self::RAW_NEWLINE, $m[0]),
            $text,
        );
        $text = (string) preg_replace('#(?<!<br />)\s*\n#', "<br />\n", $text);
        return str_replace(self::RAW_NEWLINE, "\n", $text);
    }

    /** Drops a <br /> right after a block tag, or right before one of the tags that swallow it. */
    private static function tidyBreaks(string $text): string
    {
        $text = (string) preg_replace('#(</?' . self::BLOCK . '[^>]*>)\s*<br />#', '$1', $text);
        return (string) preg_replace('#<br />(\s*</?' . self::BREAK_DROPPERS . '[^>]*>)#', '$1', $text);
    }
}
