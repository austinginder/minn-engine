<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Content\Comments;
use Minn\Content\Posts;
use Minn\Content\PostWriter;
use Minn\Content\RowCache;
use Minn\Support\Slashes;

/**
 * Comments written as WordPress's comment functions write them: a comment
 * stored and changed, its status set (to the trash or spam with the status
 * it had kept aside, and back), deleted, and a post's count of approved
 * comments kept; each step with the actions and filters the reference
 * fires in its order, and the cached rows let go.
 */
final class CommentWrites
{
    /** The columns a stored comment has, each with what a new comment takes when it gives none (an integer column is read as one). */
    private const DEFAULTS = ['comment_post_ID' => 0, 'comment_author' => '', 'comment_author_email' => '', 'comment_author_url' => '', 'comment_author_IP' => '', 'comment_date' => '', 'comment_date_gmt' => '', 'comment_content' => '', 'comment_karma' => 0, 'comment_approved' => '1', 'comment_agent' => '', 'comment_type' => '', 'comment_parent' => 0, 'user_id' => 0];

    /** A comment stored (wp_insert_comment): its id, the post's count kept when it is approved, then wp_insert_comment told. */
    public static function insert(mixed $commentdata): int
    {
        $data = Slashes::strip((array) $commentdata);
        ['comment_date' => $date, 'comment_date_gmt' => $dateGmt, 'comment_type' => $type] = $data + ['comment_date' => null, 'comment_date_gmt' => null, 'comment_type' => null];
        $date = (string) ($date ?? SiteTime::now('mysql'));
        $columns = [];
        foreach (self::DEFAULTS as $column => $default) {
            $value = $data[$column] ?? $default;
            $columns[$column] = is_int($default) ? (int) $value : (string) $value;
        }
        $columns = array_replace($columns, ['comment_date' => $date, 'comment_date_gmt' => (string) ($dateGmt ?? SiteTime::toGmt($date)), 'comment_type' => ($type ?? '') === '' ? 'comment' : (string) $type]);
        ['comment_post_ID' => $postId, 'comment_approved' => $approved] = $columns;
        $id = self::store()->insert($columns);
        if ($approved === '1') {
            self::updateCount($postId);
        }
        Handoff::cacheDelete($postId, 'posts');
        self::cleanCache($id);
        Runtime::hooks()->action('wp_insert_comment', [$id, Records::comment($id)]);
        return $id;
    }

    /**
     * A post's approved comments counted (wp_update_comment_count): put off
     * while counting is deferred; asked with $deferred, the posts put off
     * are counted now and the answer is true.
     */
    public static function updateCount(mixed $postId, mixed $deferred = false): bool
    {
        if ($deferred) {
            Deferrals::comments(false);
            return true;
        }
        return Deferrals::putOffComments((int) $postId) || self::updateCountNow($postId);
    }

    /**
     * A post's approved comments counted now (wp_update_comment_count_now),
     * unless pre_wp_update_comment_count_now supplies the count; plugins
     * told the old and new counts, then the post edited.
     */
    public static function updateCountNow(mixed $postId): bool
    {
        $postId = (int) $postId;
        $post = $postId > 0 ? Records::post($postId) : null;
        if ($post === null) {
            return false;
        }
        $old = (int) $post->comment_count;
        $given = Runtime::hooks()->filter('pre_wp_update_comment_count_now', [null, $old, $postId]);
        if ($given === null) {
            self::store()->recount($postId);
        } else {
            $db = Runtime::current()->db;
            (new PostWriter($db, new Posts($db), Runtime::current()->site))->update($postId, ['comment_count' => (int) $given]);
        }
        PostCaches::clean($postId);
        $new = (int) Records::post($postId)->comment_count;
        $hooks = Runtime::hooks();
        $hooks->action('wp_update_comment_count', [$postId, $new, $old]);
        $hooks->action("edit_post_{$post->post_type}", [$postId, Records::post($postId)]);
        $hooks->action('edit_post', [$postId, Records::post($postId)]);
        return true;
    }

    /**
     * A comment changed (wp_update_comment): the stored comment under the
     * change, through the field filters and comment_save_pre, then
     * wp_update_comment_data (handed the data, the stored comment and the
     * change); the columns written, the post's count, edit_comment with the
     * columns, and the status transition. 0 when nothing changed.
     */
    public static function update(mixed $commentarr, mixed $wpError = false): mixed
    {
        $given = Slashes::strip((array) $commentarr);
        ['comment_ID' => $commentId] = $given + ['comment_ID' => 0];
        $comment = Records::comment((int) $commentId);
        if ($comment === null) {
            return $wpError ? Handoff::newError('invalid_comment_id', 'Invalid comment ID.') : false;
        }
        $stored = $comment->to_array();
        $hooks = Runtime::hooks();
        $commentarr = CommentChecks::filter(array_merge($stored, $given));
        ['comment_content' => $content] = $commentarr + ['comment_content' => null];
        $commentarr = array_replace($commentarr, ['comment_content' => $hooks->filter('comment_save_pre', [$content])]);
        $data = $hooks->filter('wp_update_comment_data', [$commentarr, $stored, $commentarr]);
        if (Records::isError($data)) {
            return $wpError ? $data : 0;
        }
        $columns = array_intersect_key((array) $data, self::DEFAULTS);
        $changed = Comments::changedColumns($columns, $stored);
        if ($changed !== []) {
            self::store()->update((int) $comment->comment_ID, $changed);
        }
        self::cleanCache($comment->comment_ID);
        self::updateCount((int) $comment->comment_post_ID);
        $hooks->action('edit_comment', [$comment->comment_ID, $columns]);
        $updated = Records::comment($comment->comment_ID);
        self::transitionStatus(self::statusWord($updated->comment_approved), self::statusWord($comment->comment_approved), $updated);
        return $changed === [] ? 0 : 1;
    }

    /** A comment's status set (wp_set_comment_status): hold, approve, spam or trash; false for an unknown comment or status. */
    public static function setStatus(mixed $commentId, mixed $status, mixed $wpError = false): bool
    {
        $comment = Records::comment($commentId);
        if ($comment === null) {
            return false;
        }
        $stored = match ((string) $status) {
            'hold', '0' => '0',
            'approve', '1' => '1',
            'spam' => 'spam',
            'trash' => 'trash',
            default => null,
        };
        if ($stored === null) {
            return false;
        }
        self::store()->update((int) $comment->comment_ID, ['comment_approved' => $stored]);
        self::cleanCache($comment->comment_ID);
        $updated = Records::comment($comment->comment_ID);
        Runtime::hooks()->action('wp_set_comment_status', [$comment->comment_ID, $status]);
        self::transitionStatus(self::statusWord($stored), self::statusWord($comment->comment_approved), $updated);
        self::updateCount((int) $comment->comment_post_ID);
        return true;
    }

    /** A stored comment status in the words the transition actions use: approved, unapproved, spam, trash. */
    public static function statusWord(mixed $stored): string
    {
        return match ((string) $stored) {
            '1', 'approve' => 'approved',
            '0', 'hold' => 'unapproved',
            default => (string) $stored,
        };
    }

    /** What plugins are told when a comment changes status (wp_transition_comment_status); one that keeps its status hears only comment_{status}_{type}. */
    public static function transitionStatus(mixed $newStatus, mixed $oldStatus, mixed $comment): void
    {
        $new = self::statusWord($newStatus);
        $old = self::statusWord($oldStatus);
        $hooks = Runtime::hooks();
        if ($new !== $old) {
            $hooks->action('transition_comment_status', [$new, $old, $comment]);
            $hooks->action("comment_{$old}_to_{$new}", [$comment]);
        }
        $type = $comment->comment_type === '' ? 'comment' : $comment->comment_type;
        $hooks->action("comment_{$new}_{$type}", [$comment->comment_ID, $comment]);
    }

    /** Comments' cached rows and meta let go (clean_comment_cache), plugins told about each. */
    public static function cleanCache(mixed $ids): void
    {
        RowCache::forget('comments');
        RowCache::forget('comment_queries');
        foreach ((array) $ids as $id) {
            Handoff::cacheDelete((int) $id, 'comment');
            Handoff::cacheDelete((int) $id, 'comment_meta');
            Runtime::hooks()->action('clean_comment_cache', [(int) $id]);
        }
    }

    /**
     * A comment deleted (wp_delete_comment): to the trash first unless
     * forced, it is there already or the trash is off; its replies moved up
     * to its parent; the post recounted when it was approved.
     */
    public static function delete(mixed $commentId, mixed $force = false): bool
    {
        $comment = Records::comment($commentId);
        if ($comment === null) {
            return false;
        }
        if (!$force && EMPTY_TRASH_DAYS && !in_array($comment->comment_approved, ['trash', 'spam'], true)) {
            return self::trash($comment->comment_ID);
        }
        $hooks = Runtime::hooks();
        $hooks->action('delete_comment', [$comment->comment_ID, $comment]);
        self::store()->orphanReplies((int) $comment->comment_ID, (int) $comment->comment_parent);
        self::store()->delete((int) $comment->comment_ID);
        $hooks->action('deleted_comment', [$comment->comment_ID, $comment]);
        self::cleanCache($comment->comment_ID);
        $hooks->action('wp_set_comment_status', [$comment->comment_ID, 'delete']);
        self::transitionStatus('delete', $comment->comment_approved, $comment);
        if ($comment->comment_approved === '1') {
            self::updateCount((int) $comment->comment_post_ID);
        }
        return true;
    }

    /** A comment to the trash (wp_trash_comment), or deleted when the trash is off. */
    public static function trash(mixed $commentId): bool
    {
        return EMPTY_TRASH_DAYS ? self::setAside($commentId, 'trash', 'trash_comment', 'trashed_comment') : self::delete($commentId, true);
    }

    /** A comment back from the trash (wp_untrash_comment). */
    public static function untrash(mixed $commentId): bool
    {
        return self::restore($commentId, 'untrash_comment', 'untrashed_comment');
    }

    /** A comment marked spam (wp_spam_comment). */
    public static function spam(mixed $commentId): bool
    {
        return self::setAside($commentId, 'spam', 'spam_comment', 'spammed_comment');
    }

    /** A comment back from spam (wp_unspam_comment). */
    public static function unspam(mixed $commentId): bool
    {
        return self::restore($commentId, 'unspam_comment', 'unspammed_comment');
    }

    /**
     * A post's comments to the trash with it (wp_trash_post_comments): each
     * becomes post-trashed, its status kept on the post to give back; how
     * many changed (one set aside with its post already does not count).
     */
    public static function trashPostComments(mixed $post = null): mixed
    {
        $post = Records::post($post);
        if ($post === null) {
            return null;
        }
        $hooks = Runtime::hooks();
        $hooks->action('trash_post_comments', [$post->ID]);
        $statuses = self::store()->statusesOf($post->ID);
        if ($statuses === []) {
            return null;
        }
        MetaData::add('post', $post->ID, '_wp_trash_meta_comments_status', $statuses);
        self::store()->setStatusOf(array_keys($statuses), 'post-trashed');
        self::cleanCache(array_keys($statuses));
        $hooks->action('trashed_post_comments', [$post->ID, $statuses]);
        return count(array_filter($statuses, static fn (mixed $status): bool => (string) $status !== 'post-trashed'));
    }

    /** A post back from the trash gives its comments their statuses back (wp_untrash_post_comments). */
    public static function untrashPostComments(mixed $post = null): mixed
    {
        $post = Records::post($post);
        if ($post === null) {
            return null;
        }
        $statuses = MetaData::get('post', $post->ID, '_wp_trash_meta_comments_status', true);
        if (!is_array($statuses) || $statuses === []) {
            return true;
        }
        $hooks = Runtime::hooks();
        $hooks->action('untrash_post_comments', [$post->ID]);
        foreach (array_keys(array_flip(array_map(static fn (mixed $status): string => (string) $status, $statuses))) as $status) {
            self::store()->setStatusOf(array_map('intval', array_keys($statuses, $status, false)), (string) $status);
        }
        self::cleanCache(array_keys($statuses));
        MetaData::delete('post', $post->ID, '_wp_trash_meta_comments_status');
        $hooks->action('untrashed_post_comments', [$post->ID]);
        return null;
    }

    /** A comment moved to the trash or spam: the first action, the status change, the status it had kept aside with the time, the second action. */
    private static function setAside(mixed $commentId, string $status, string $before, string $after): bool
    {
        $comment = Records::comment($commentId);
        if ($comment === null) {
            return false;
        }
        $hooks = Runtime::hooks();
        $hooks->action($before, [$comment->comment_ID, $comment]);
        if (!self::setStatus($comment->comment_ID, $status)) {
            return false;
        }
        MetaData::delete('comment', $comment->comment_ID, '_wp_trash_meta_status');
        MetaData::delete('comment', $comment->comment_ID, '_wp_trash_meta_time');
        MetaData::add('comment', $comment->comment_ID, '_wp_trash_meta_status', $comment->comment_approved);
        MetaData::add('comment', $comment->comment_ID, '_wp_trash_meta_time', time());
        $hooks->action($after, [$comment->comment_ID, $comment]);
        return true;
    }

    /** A comment back from the trash or spam to the status kept aside (held when none was), between the two actions. */
    private static function restore(mixed $commentId, string $before, string $after): bool
    {
        $comment = Records::comment($commentId);
        if ($comment === null) {
            return false;
        }
        $hooks = Runtime::hooks();
        $hooks->action($before, [$comment->comment_ID, $comment]);
        $status = (string) (MetaData::get('comment', $comment->comment_ID, '_wp_trash_meta_status', true) ?: '0');
        if (!self::setStatus($comment->comment_ID, $status)) {
            return false;
        }
        MetaData::delete('comment', $comment->comment_ID, '_wp_trash_meta_status');
        MetaData::delete('comment', $comment->comment_ID, '_wp_trash_meta_time');
        $hooks->action($after, [$comment->comment_ID, $comment]);
        return true;
    }

    private static function store(): Comments
    {
        return new Comments(Runtime::current()->db);
    }
}
