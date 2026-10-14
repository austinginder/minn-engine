<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Support\Escape;

/**
 * What the comment template tags tell about a comment (the one being read
 * when none is named): when it was written and its author's address, each
 * through the filter plugins hook there.
 */
final class CommentTags
{
    /** A comment's author (get_comment_author), "Anonymous" for none, as get_comment_author leaves it. */
    public static function author(mixed $commentId = 0): mixed
    {
        $comment = Records::comment($commentId);
        $author = $comment === null || $comment->comment_author === '' ? 'Anonymous' : $comment->comment_author;
        return Runtime::hooks()->filter('get_comment_author', [$author, $comment?->comment_ID, $comment]);
    }

    /** The author of the comment being read, for a feed (get_comment_author_rss), as comment_author_rss leaves it. */
    public static function authorRss(): mixed
    {
        return Runtime::hooks()->filter('comment_author_rss', [self::author()]);
    }

    /**
     * When a comment was written (get_comment_time): in the format given or
     * the site's time format, local or UTC, names translated unless asked
     * not to be; empty for no comment.
     */
    public static function time(mixed $format = '', mixed $gmt = false, mixed $translate = true, mixed $commentId = 0): mixed
    {
        $comment = Records::comment($commentId);
        if ($comment === null) {
            return '';
        }
        $date = $gmt ? $comment->comment_date_gmt : $comment->comment_date;
        $format = $format === '' ? Runtime::options()->filtered('time_format') : $format;
        return Runtime::hooks()->filter('get_comment_time', [SiteTime::fromStored($format, $date, $translate), $format, $gmt, $translate, $comment]);
    }

    /** A comment author's address, escaped (get_comment_author_url); empty when none was given. */
    public static function authorUrl(mixed $commentId = 0): mixed
    {
        $comment = Records::comment($commentId);
        $url = (string) ($comment->comment_author_url ?? '');
        $url = $url === 'http://' ? '' : $url;
        return Runtime::hooks()->filter('get_comment_author_url', [$url === '' ? '' : Escape::url($url), (string) ($comment->comment_ID ?? ''), $comment]);
    }
}
