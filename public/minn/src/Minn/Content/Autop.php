<?php

declare(strict_types=1);

namespace Minn\Content;

/**
 * Classic-content paragraphing: blank lines become paragraphs, single
 * newlines become line breaks, and block-level tags are never wrapped.
 * The behaviour is pinned by the api suite row for row.
 */
final class Autop
{
    private const BLOCKS = '(?:table|thead|tfoot|caption|col|colgroup|tbody|tr|td|th|div|dl|dd|dt|ul|ol|li|pre|form|map|area|blockquote|address|style|p|h[1-6]|hr|fieldset|legend|section|article|aside|hgroup|header|footer|nav|figure|figcaption|details|menu|summary)';

    /** The classic paragraph rules: a blank line makes a paragraph, a single newline a line break when asked. */
    public static function apply(string $text, bool $lineBreaks = true): string
    {
        if (trim($text) === '') {
            return '';
        }
        $blocks = self::BLOCKS;
        $text .= "\n";
        $text = (string) preg_replace('|<br\s*/?>\s*<br\s*/?>|', "\n\n", $text);
        $text = (string) preg_replace('!(<' . $blocks . '[\s/>])!', "\n\n$1", $text);
        $text = (string) preg_replace('!(</' . $blocks . '>)!', "$1\n\n", $text);
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = (string) preg_replace("/\n\n+/", "\n\n", $text);
        $wrapped = '';
        foreach (preg_split('/\n\s*\n/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $paragraph) {
            $wrapped .= '<p>' . trim($paragraph, "\n") . "</p>\n";
        }
        $text = (string) preg_replace('|<p>\s*</p>|', '', $wrapped);
        $text = (string) preg_replace('!<p>\s*(</?' . $blocks . '[^>]*>)\s*</p>!', '$1', $text);
        $text = (string) preg_replace('|<p>(<li.+?)</p>|', '$1', $text);
        $text = (string) preg_replace('!<p>\s*(</?' . $blocks . '[^>]*>)!', '$1', $text);
        $text = (string) preg_replace('!(</?' . $blocks . '[^>]*>)\s*</p>!', '$1', $text);
        if ($lineBreaks) {
            $text = (string) preg_replace('|(?<!<br />)\s*\n|', "<br />\n", $text);
            $text = (string) preg_replace('!(</?' . $blocks . '[^>]*>)\s*<br />!', '$1', $text);
            $text = (string) preg_replace('!<br />(\s*</?(?:p|li|div|dl|dd|dt|th|pre|td|ul|ol)[^>]*>)!', '$1', $text);
        }
        return (string) preg_replace("|\n</p>$|", '</p>', $text);
    }
}
