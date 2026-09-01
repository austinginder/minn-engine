<?php

declare(strict_types=1);

namespace Minn\Content;

/**
 * The `<!--more-->` marker that splits a post into the part a listing shows
 * and the part only the single view does. The marker may carry its own link
 * text; a second marker further down is ordinary content and stays where it
 * is, as does the `<!--noteaser-->` flag that follows some of them.
 */
final class MoreTag
{
    /**
     * @return array{main: string, extended: string, more_text: string}
     */
    public static function split(string $content): array
    {
        if (preg_match('/<!--more(.*?)?-->/', $content, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            return ['main' => $content, 'extended' => '', 'more_text' => ''];
        }
        $at = (int) $matches[0][1];
        return [
            'main' => substr($content, 0, $at),
            'extended' => substr($content, $at + strlen((string) $matches[0][0])),
            'more_text' => trim((string) ($matches[1][0] ?? '')),
        ];
    }
}
