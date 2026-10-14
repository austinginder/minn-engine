<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Content\PasswordGate;

/**
 * What the post template tags tell about a post (the one being read when
 * none is named): its id and title, whether a password stands before it,
 * whether comments and pings are open, how many comments it has, and its
 * featured image; each through the filter plugins hook there.
 */
final class PostTags
{
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
