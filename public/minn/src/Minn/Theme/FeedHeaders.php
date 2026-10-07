<?php

declare(strict_types=1);

namespace Minn\Theme;

use Minn\Http\Request;

/**
 * The headers a feed is sent with, as the reference's send_headers sends
 * them (suite feed-hooks): the feed type's content type, and when the site
 * last changed (its posts, and its comments too for a comments feed) as
 * Last-Modified with an ETag over it; a reader whose copy carries that
 * date or tag is told the copy is current.
 */
final class FeedHeaders
{
    private const FORMAT = 'D, d M Y H:i:s';

    /**
     * The headers, and whether the reader's copy is current.
     *
     * @param array<string, mixed> $vars the request's query variables
     * @return array{0: array<string, string>, 1: bool}
     */
    public static function for(array $vars, ?Request $request): array
    {
        $feed = (string) $vars['feed'];
        $headers = ['Content-Type' => \feed_content_type($feed === 'feed' ? \get_default_feed() : $feed) . '; charset=' . \get_option('blog_charset')];
        $last = (string) \mysql2date(self::FORMAT, \get_lastpostmodified('GMT'), false);
        if (self::carriesComments($vars, $feed)) {
            $comment = (string) \mysql2date(self::FORMAT, \get_lastcommentmodified('GMT'), false);
            $last = strtotime($last) > strtotime($comment) ? $last : $comment;
        }
        $last = ($last !== '' ? $last : gmdate(self::FORMAT)) . ' GMT';
        $etag = '"' . md5($last) . '"';
        $headers['Last-Modified'] = $last;
        $headers['ETag'] = $etag;
        $clientTag = (string) ($request?->header('if-none-match') ?? '');
        $clientDate = trim((string) ($request?->header('if-modified-since') ?? ''));
        $clientTime = $clientDate !== '' ? (int) strtotime($clientDate) : 0;
        $time = (int) strtotime($last);
        $fresh = $clientDate !== '' && $clientTag !== ''
            ? $clientTime >= $time && $clientTag === $etag
            : $clientTime >= $time || $clientTag === $etag;
        return [$headers, $fresh];
    }

    /** @param array<string, mixed> $vars whether the feed's last change counts the comments: a comments feed, or a single post's */
    private static function carriesComments(array $vars, string $feed): bool
    {
        if (!empty($vars['withcomments']) || str_contains($feed, 'comments-')) {
            return true;
        }
        if (!empty($vars['withoutcomments'])) {
            return false;
        }
        foreach (['p', 'name', 'page_id', 'pagename', 'attachment', 'attachment_id'] as $key) {
            if (!empty($vars[$key])) {
                return true;
            }
        }
        return false;
    }
}
