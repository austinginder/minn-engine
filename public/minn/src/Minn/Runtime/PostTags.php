<?php

declare(strict_types=1);

namespace Minn\Runtime;

use DateTimeZone;
use Minn\Content\PasswordGate;

/**
 * What the post template tags tell about a post (the one being read when
 * none is named): its id and title, whether a password stands before it,
 * whether comments and pings are open, how many comments it has, and its
 * featured image; each through the filter plugins hook there.
 */
final class PostTags
{
    /**
     * When a post was published (get_post_time): a timestamp for "U" or
     * "G", else the date in the format, local or UTC (a GMT date not set
     * yet worked out from the local one), names translated when asked;
     * false for no post.
     */
    public static function time(mixed $format = 'U', mixed $gmt = false, mixed $post = null, mixed $translate = false): mixed
    {
        $post = Records::post($post);
        if ($post === null) {
            return false;
        }
        $source = $gmt ? $post->post_date_gmt : $post->post_date;
        if ($gmt && str_starts_with($source, '0000')) {
            $source = SiteTime::toGmt($post->post_date);
        }
        $time = self::moment($source, $format, $gmt, $translate);
        return $time === null ? false : Runtime::hooks()->filter('get_post_time', [$time, $format, $gmt]);
    }

    /** When a post last changed (get_post_modified_time), as get_post_time tells when it was published. */
    public static function modifiedTime(mixed $format = 'U', mixed $gmt = false, mixed $post = null, mixed $translate = false): mixed
    {
        $post = Records::post($post);
        if ($post === null) {
            return false;
        }
        $time = self::moment($gmt ? $post->post_modified_gmt : $post->post_modified, $format, $gmt, $translate);
        return $time === null ? false : Runtime::hooks()->filter('get_post_modified_time', [$time, $format, $gmt]);
    }

    /** A stored date as a timestamp for "U" or "G", else in the format (translated when asked); null for one that is not a date. */
    private static function moment(string $source, mixed $format, mixed $gmt, mixed $translate): mixed
    {
        $zone = $gmt ? new DateTimeZone('UTC') : SiteTime::zone();
        $datetime = date_create($source, $zone);
        if ($datetime === false) {
            return null;
        }
        if ($format === 'U' || $format === 'G') {
            return $datetime->getTimestamp();
        }
        return $translate ? SiteTime::format($format, $datetime->getTimestamp(), $gmt ? new DateTimeZone('UTC') : null) : $datetime->format((string) $format);
    }

    /** The id of the post being read (get_the_ID), or false. */
    public static function id(): mixed
    {
        $post = Records::post();
        return $post === null ? false : $post->ID;
    }

    /**
     * A post's title (get_the_title): outside the admin a protected or
     * private one says so (protected_title_format, private_title_format);
     * then the_title. Empty for no post.
     */
    public static function title(mixed $post = 0): mixed
    {
        $post = Records::post($post);
        if ($post === null) {
            return '';
        }
        $title = $post->post_title;
        if (!Runtime::current()->isAdmin) {
            if ($post->post_password !== '') {
                $title = sprintf((string) Runtime::hooks()->filter('protected_title_format', ['Protected: %s', $post]), $title);
            } elseif ($post->post_status === 'private') {
                $title = sprintf((string) Runtime::hooks()->filter('private_title_format', ['Private: %s', $post]), $title);
            }
        }
        return Runtime::hooks()->filter('the_title', [$title, $post->ID]);
    }


    /** Whether a password stands before the post's content for this reader (post_password_required). */
    public static function passwordRequired(mixed $post = null): mixed
    {
        $post = Records::post($post);
        if ($post === null || $post->post_password === '') {
            return Runtime::hooks()->filter('post_password_required', [false, $post]);
        }
        $required = !PasswordGate::cookieUnlocks($post->post_password, Runtime::current()->reader->postPassword);
        return Runtime::hooks()->filter('post_password_required', [$required, $post]);
    }

    /** Whether comments are open on a post (comments_open). */
    public static function commentsOpen(mixed $post = null): bool
    {
        $post = Records::post($post);
        return (bool) Runtime::hooks()->filter('comments_open', [$post !== null && $post->comment_status === 'open', $post?->ID]);
    }

    /** Whether pings are open on a post (pings_open). */
    public static function pingsOpen(mixed $post = null): bool
    {
        $post = Records::post($post);
        return (bool) Runtime::hooks()->filter('pings_open', [$post !== null && $post->ping_status === 'open', $post?->ID]);
    }

    /** The id of a post's featured image (get_post_thumbnail_id), 0 for none, false for no post. */
    public static function thumbnailId(mixed $post = null): mixed
    {
        $post = Records::post($post);
        if ($post === null) {
            return false;
        }
        $id = MetaData::get('post', $post->ID, '_thumbnail_id', true);
        return (int) Runtime::hooks()->filter('post_thumbnail_id', [$id === '' ? 0 : (int) $id, $post]);
    }

    /** Whether a post has a featured image (has_post_thumbnail). */
    public static function hasThumbnail(mixed $post = null): bool
    {
        $id = self::thumbnailId($post);
        return (bool) Runtime::hooks()->filter('has_post_thumbnail', [(bool) $id, $post, $id]);
    }

    /** How many comments a post has (get_comments_number), 0 for none. */
    public static function commentsNumber(mixed $post = 0): mixed
    {
        $post = Records::post($post);
        return Runtime::hooks()->filter('get_comments_number', [$post === null ? 0 : $post->comment_count, $post === null ? 0 : $post->ID]);
    }
}
