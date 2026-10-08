<?php

declare(strict_types=1);

namespace Minn\Content;

/**
 * The [playlist] shortcode's markup as the reference prints it (probe
 * plugin-queue5): the player for audio (with the current-item box) or for
 * video (with its height), the next and previous controls, a noscript list
 * of links to the files, and the tracks as JSON for wp-playlist; and the
 * two Underscore templates the script draws them with
 * (data/playlist-templates.html).
 */
final class Playlist
{
    /**
     * The player and its data.
     *
     * @param list<string> $links one link per track, for the noscript list
     * @param string $json the type, the flags and the tracks, encoded
     */
    public static function markup(string $type, string $style, int $width, int $height, array $links, string $json): string
    {
        return '<div class="wp-playlist wp-' . $type . '-playlist wp-playlist-' . $style . "\">\n"
            . ($type === 'audio' ? "\t\t\t<div class=\"wp-playlist-current-item\"></div>\n" : '')
            . "\t\t<{$type} controls=\"controls\" preload=\"none\" width=\"{$width}\"\n\t\t" . ($type === 'video' ? " height=\"{$height}\"" : '') . "\t></{$type}>\n"
            . "\t<div class=\"wp-playlist-next\"></div>\n\t<div class=\"wp-playlist-prev\"></div>\n\t<noscript>\n\t<ol>\n\t\t"
            . implode('', array_map(static fn (string $link): string => "<li>{$link}</li>", $links)) . "\t</ol>\n\t</noscript>\n"
            . "\t<script type=\"application/json\" class=\"wp-playlist-script\">{$json}</script>\n</div>\n\t";
    }

    /** The two templates, each title wrapped in the quoting format given (its %s takes the title placeholder). */
    public static function templates(string $quoted): string
    {
        $templates = (string) file_get_contents(MINN_ENGINE_DIR . '/data/playlist-templates.html');
        return sprintf($templates, sprintf($quoted, '{{ data.title }}'), sprintf($quoted, '{{{ data.title }}}'));
    }
}
