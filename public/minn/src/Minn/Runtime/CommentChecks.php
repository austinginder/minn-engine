<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Content\Comments;

/**
 * What WordPress checks of a comment before and after it is stored: the
 * lengths the table allows its fields, each field through its pre_comment_
 * filter, the status a stored comment has in words, and whether a new
 * post of a type starts open to comments or pings.
 */
final class CommentChecks
{
    /**
     * How long each comment form field may be
     * (wp_get_comment_fields_max_lengths), as the table allows, through
     * wp_get_comment_fields_max_lengths.
     *
     * @return array<string, int>
     */
    public static function fieldMaxLengths(): mixed
    {
        return Runtime::hooks()->filter('wp_get_comment_fields_max_lengths', [(new Comments(Runtime::current()->db))->fieldLengths()]);
    }

    /** Whether a comment fits its columns (wp_check_comment_data_max_lengths): true, or the error naming the first field too long. */
    public static function maxLengths(mixed $data): mixed
    {
        $max = self::fieldMaxLengths();
        $limits = ['comment_author' => ['comment_author_column_length', 'Your name is too long.'], 'comment_author_email' => ['comment_author_email_column_length', 'Your email address is too long.'], 'comment_author_url' => ['comment_author_url_column_length', 'Your URL is too long.'], 'comment_content' => ['comment_content_column_length', 'Your comment is too long.']];
        foreach ($limits as $field => [$code, $message]) {
            if (isset($data[$field], $max[$field]) && mb_strlen((string) $data[$field], '8bit') > (int) $max[$field]) {
                return Handoff::newError($code, '<strong>Error:</strong> ' . $message, 200);
            }
        }
        return true;
    }

    /**
     * Each field through its pre_comment_ filter (wp_filter_comment), in the
     * reference's order (the user first, the email last), marked filtered.
     *
     * @return array<string, mixed>
     */
    public static function filter(mixed $data): array
    {
        $data = (array) $data;
        $hooks = Runtime::hooks();
        $map = ['comment_agent' => 'pre_comment_user_agent', 'comment_author' => 'pre_comment_author_name', 'comment_content' => 'pre_comment_content', 'comment_author_IP' => 'pre_comment_user_ip', 'comment_author_url' => 'pre_comment_author_url', 'comment_author_email' => 'pre_comment_author_email'];
        if (isset($data['user_ID'])) {
            $data['user_id'] = $hooks->filter('pre_user_id', [$data['user_ID']]);
        } elseif (isset($data['user_id'])) {
            $data['user_id'] = $hooks->filter('pre_user_id', [$data['user_id']]);
        }
        foreach ($map as $field => $filter) {
            if (isset($data[$field])) {
                $data[$field] = $hooks->filter($filter, [$data[$field]]);
            }
        }
        $data['filtered'] = true;
        return $data;
    }

    /** A comment's status (wp_get_comment_status): approved, unapproved, spam or trash; false for an unknown comment or status. */
    public static function status(mixed $commentId): mixed
    {
        $comment = Records::comment($commentId);
        if ($comment === null) {
            return false;
        }
        return match ((string) $comment->comment_approved) { '1' => 'approved', '0' => 'unapproved', 'spam' => 'spam', 'trash' => 'trash', default => false };
    }

    /**
     * Whether a new post of a type starts open (get_default_comment_status):
     * a page starts closed; another type follows the site's default when it
     * supports comments (or trackbacks, for pings).
     */
    public static function defaultStatus(mixed $type = 'post', mixed $commentType = 'comment'): mixed
    {
        $feature = in_array($commentType, ['pingback', 'trackback'], true) ? 'trackbacks' : 'comments';
        $option = $feature === 'comments' ? 'default_comment_status' : 'default_ping_status';
        $status = $type !== 'page' && Registered::supports((string) $type, $feature) && Runtime::options()->filtered($option) === 'open' ? 'open' : 'closed';
        return Runtime::hooks()->filter('get_default_comment_status', [$status, $type, $commentType]);
    }
}
