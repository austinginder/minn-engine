<?php

declare(strict_types=1);

namespace Minn\Runtime;

use DateTimeZone;
use Minn\Content\PasswordGate;
use Minn\Http\Response;
use Minn\Objects\User;

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

    /**
     * One of the author's fields (get_the_author_meta): the post's author
     * being read unless a user is named; ID as an integer, the user_ columns
     * by their short names, empty for none; as get_the_author_{field}
     * leaves it.
     */
    public static function authorMeta(mixed $field = '', mixed $userId = false): mixed
    {
        $user = $userId === false ? ($GLOBALS['authordata'] ?? null) : Handoff::user((int) $userId);
        if (!$user instanceof User) {
            return '';
        }
        $value = $field === '' ? '' : ($field === 'ID' ? $user->ID : $user->get($field));
        if ($field === 'ID') {
            $value = (int) $user->ID;
        }
        if (in_array($field, ['login', 'pass', 'nicename', 'email', 'url', 'registered', 'activation_key', 'status'], true)) {
            $value = $user->get('user_' . $field);
        }
        return Runtime::hooks()->filter("get_the_author_{$field}", [$value === null || $value === false ? '' : $value, $userId === false ? $user->ID : $userId, $userId === false ? $user->ID : false]);
    }

    /** An author's archive address (get_author_posts_url): /author/{nicename}/ with pretty permalinks, else ?author=; as author_link leaves it. */
    public static function authorPostsUrl(mixed $authorId, mixed $nicename = ''): mixed
    {
        $authorId = (int) $authorId;
        if ($nicename === '') {
            $user = Handoff::user($authorId);
            $nicename = $user ? $user->user_nicename : '';
        }
        $permalinks = Runtime::current()->get('permalinks');
        $link = $permalinks !== null && $permalinks->isPretty() ? SiteUrls::home('/author/' . ($nicename === '' ? '' : $nicename . '/')) : SiteUrls::home('/?author=' . $authorId);
        return Runtime::hooks()->filter('author_link', [$link, $authorId, $nicename]);
    }

    /**
     * A post's title made safe for an attribute (the_title_attribute), its
     * tags gone with those of before and after; printed unless echo is
     * false, null for no title.
     */
    public static function titleAttribute(mixed $args = ''): mixed
    {
        $args = \Minn\Support\Lists::args($args, ['before' => '', 'after' => '', 'echo' => true, 'post' => Records::post()]);
        $title = self::title($args['post']);
        if ($title === '') {
            return null;
        }
        $title = \Minn\Support\Escape::attr(strip_tags($args['before'] . $title . $args['after']));
        if ($args['echo']) {
            Response::write($title);
            return null;
        }
        return $title;
    }

    /** When a post was published, in the format given or the site's date format (get_the_date), as get_the_date leaves it; false for no post. */
    public static function date(mixed $format = '', mixed $post = null): mixed
    {
        $post = Records::post($post);
        if ($post === null) {
            return false;
        }
        $format = $format === '' ? Runtime::options()->filtered('date_format') : $format;
        return Runtime::hooks()->filter('get_the_date', [self::time($format, false, $post, true), $format, $post]);
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
