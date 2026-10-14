<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Auth\Can;
use Minn\Content\Comments;
use Minn\Html;
use Minn\Http\Request;
use Minn\I18n\Gettext;
use Minn\Objects\Comment;
use Minn\Support\Slashes;

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
        return Runtime::hooks()->filter('wp_get_comment_fields_max_lengths', [self::comments()->fieldLengths()]);
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

    /**
     * Whether a comment needs no moderation (check_comment): never while
     * moderation is on; the links counted in the comment as comment_text
     * shows it (comment_max_links_url may recount) against comment_max_links;
     * the moderation words; and, when asked, an earlier approved comment from
     * the same author (a pingback from one of the site's own posts, as
     * wp_auto_approve_ping decides; a trackback never).
     */
    public static function passes(mixed $author, mixed $email, mixed $url, mixed $comment, mixed $userIp, mixed $userAgent, mixed $commentType): bool
    {
        $options = Runtime::options();
        $hooks = Runtime::hooks();
        if ((string) $options->filtered('comment_moderation') === '1') {
            return false;
        }
        $shown = (string) $hooks->filter('comment_text', [(string) $comment, null, []]);
        $max = (int) $options->filtered('comment_max_links');
        if ($max > 0 && (int) $hooks->filter('comment_max_links_url', [preg_match_all('/<a [^>]*href/i', $shown), $url, $comment]) >= $max) {
            return false;
        }
        foreach (explode("\n", (string) $options->filtered('moderation_keys')) as $word) {
            $word = trim($word);
            if ($word !== '' && preg_match('#' . preg_quote($word, '#') . '#iu', $author . ' ' . $email . ' ' . $url . ' ' . $comment . ' ' . $userIp . ' ' . $userAgent)) {
                return false;
            }
        }
        if ((string) $options->filtered('comment_previously_approved') === '1') {
            if ($commentType === 'pingback') {
                $linked = PostReads::idFromUrl((string) $url);
                return (bool) $hooks->filter('wp_auto_approve_ping', [$linked > 0, $linked, $url]);
            }
            return $commentType !== 'trackback' && (string) $author !== '' && (string) $email !== '' && self::comments()->previouslyApproved((string) $author, (string) $email);
        }
        return true;
    }

    /**
     * Whether a line of disallowed_keys appears in the comment's fields
     * (wp_check_comment_disallowed_list): a word match, or an address's
     * start; the comment read without its tags and entities too.
     */
    public static function disallowed(mixed $author, mixed $email, mixed $url, mixed $comment, mixed $userIp, mixed $userAgent): bool
    {
        $hooks = Runtime::hooks();
        $hooks->actionDeprecated('wp_blacklist_check', [$author, $email, $url, $comment, $userIp, $userAgent], '5.5.0', 'wp_check_comment_disallowed_list', Gettext::text('Please consider writing more inclusive code.'));
        $hooks->action('wp_check_comment_disallowed_list', [$author, $email, $url, $comment, $userIp, $userAgent]);
        $keys = trim((string) Runtime::options()->filtered('disallowed_keys'));
        if ($keys === '') {
            return false;
        }
        foreach (explode("\n", $keys) as $word) {
            $word = trim($word);
            if ($word === '') {
                continue;
            }
            $pattern = '#' . preg_quote($word, '#') . '#iu';
            $bare = is_scalar($comment) ? Html::stripAllTags((string) $comment) : '';
            foreach ([$author, $email, $url, $comment, html_entity_decode($bare, ENT_QUOTES | ENT_HTML5, 'UTF-8'), $userIp, $userAgent] as $field) {
                if (preg_match($pattern, (string) $field)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * A comment's approval as its data asks (wp_check_comment_data): the
     * post's author and a moderator are approved; anyone else's comment
     * passes check_comment or is held, and the disallowed words send it to
     * the trash (spam with the trash off); pre_comment_approved has the last
     * word. The data may be slashed.
     */
    public static function approval(mixed $data): mixed
    {
        $data = (array) $data;
        $field = static fn (string $key): string => (string) Slashes::strip($data[$key] ?? '');
        $raw = static fn (string $key): mixed => $data[$key] ?? 0;
        $userId = (int) $raw('user_id');
        $post = Records::post((int) $raw('comment_post_ID'));
        if ($userId > 0 && (($post !== null && (int) $post->post_author === $userId) || Can::user($userId, 'moderate_comments'))) {
            $approved = 1;
        } else {
            $approved = self::passes($field('comment_author'), $field('comment_author_email'), $field('comment_author_url'), $field('comment_content'), $field('comment_author_IP'), $field('comment_agent'), $field('comment_type')) ? 1 : 0;
            if (self::disallowed($field('comment_author'), $field('comment_author_email'), $field('comment_author_url'), $field('comment_content'), $field('comment_author_IP'), $field('comment_agent'))) {
                $approved = defined('EMPTY_TRASH_DAYS') && !EMPTY_TRASH_DAYS ? 'spam' : 'trash';
            }
        }
        return Runtime::hooks()->filter('pre_comment_approved', [$approved, $data]);
    }

    /**
     * 1, 0, "spam" or "trash" for a comment that may be stored
     * (wp_allow_comment), in the reference's order: a duplicate, which
     * duplicate_comment_id may name or clear, refuses with 409;
     * check_comment_flood and wp_is_comment_flood refuse a flood with 429;
     * then the approval. The error when asked for one, else the stop page,
     * for a refusal.
     */
    public static function allow(mixed $commentdata, mixed $wpError = false): mixed
    {
        $data = (array) $commentdata;
        $field = static fn (string $key): string => (string) Slashes::strip($data[$key] ?? '');
        $raw = static fn (string $key): mixed => $data[$key] ?? 0;
        $hooks = Runtime::hooks();
        $found = self::comments()->duplicateId((int) $raw('comment_post_ID'), $field('comment_author'), $field('comment_author_email'), $field('comment_content'), (int) $raw('user_id'));
        $dupe = $hooks->filter('duplicate_comment_id', [$found === null ? null : (string) $found, $data]);
        if ($dupe) {
            $hooks->action('comment_duplicate_trigger', [$data]);
            $message = $hooks->filter('comment_duplicate_message', ['Duplicate comment detected; it looks as though you&#8217;ve already said that!']);
            return self::refused($wpError, 'comment_duplicate', $message, 409);
        }
        $hooks->action('check_comment_flood', [$field('comment_author_IP'), $field('comment_author_email'), $field('comment_date_gmt'), $wpError]);
        if ($hooks->filter('wp_is_comment_flood', [false, $field('comment_author_IP'), $field('comment_author_email'), $field('comment_date_gmt'), $wpError])) {
            $message = $hooks->filter('comment_flood_message', ['You are posting comments too quickly. Slow down.']);
            return self::refused($wpError, 'comment_flood', $message, 429);
        }
        return self::approval($data);
    }

    /**
     * A comment from outside stored the way the comment form stores one
     * (wp_new_comment): preprocess_comment; the ids, user, parent, address,
     * agent and dates filled in; wp_allow_comment; the pre_comment_
     * filters; the approval judged again on what they left; the comment
     * stored; comment_post. The id, or the error when asked for one (false
     * without).
     */
    public static function create(mixed $commentdata, mixed $wpError = false): mixed
    {
        $data = self::filledIn((array) Runtime::hooks()->filter('preprocess_comment', [$commentdata]));
        $approved = self::allow($data, $wpError);
        if (Records::isError($approved)) {
            return $wpError ? $approved : false;
        }
        $data = self::filter(array_replace($data, ['comment_approved' => $approved]));
        $data = array_replace($data, ['comment_approved' => self::approval($data)]);
        ['comment_approved' => $approved] = $data;
        if (Records::isError($approved)) {
            return $wpError ? $approved : false;
        }
        $id = CommentWrites::insert($data);
        if (!$id) {
            return $wpError ? Handoff::newError('db_insert_error', 'Could not insert comment into the database.') : false;
        }
        Runtime::hooks()->action('comment_post', [(int) $id, $approved, $data]);
        return (int) $id;
    }

    /** The commenter remembered by the comment_author_ cookies (wp_get_current_commenter), as wp_get_current_commenter leaves them. */
    public static function currentCommenter(): mixed
    {
        $hash = md5((string) Runtime::options()->filtered('siteurl'));
        $commenter = ['comment_author' => (string) (Request::cookieVariable('comment_author_' . $hash) ?? ''), 'comment_author_email' => (string) (Request::cookieVariable('comment_author_email_' . $hash) ?? ''), 'comment_author_url' => (string) (Request::cookieVariable('comment_author_url_' . $hash) ?? '')];
        return Runtime::hooks()->filter('wp_get_current_commenter', [$commenter]);
    }

    /**
     * The address of the reader whose held comment the page may show
     * (wp_get_unapproved_comment_author_email): from a held comment's link
     * (its hash right, under ten minutes old), or else from the commenter's
     * cookie.
     */
    public static function unapprovedEmail(): string
    {
        $unapproved = Request::queryVariable('unapproved');
        $hash = Request::queryVariable('moderation-hash');
        $comment = $unapproved !== null && $hash !== null ? Records::comment((int) $unapproved) : null;
        $fresh = $comment instanceof Comment && (string) $comment->comment_approved === '0' && (int) strtotime($comment->comment_date_gmt . ' UTC') + 600 > time();
        if ($fresh && hash_equals((string) Handoff::hash($comment->comment_date_gmt), (string) $hash)) {
            return (string) $comment->comment_author_email;
        }
        ['comment_author_email' => $email] = (array) self::currentCommenter() + ['comment_author_email' => null];
        return (string) ($email ?? '');
    }

    /**
     * What wp_new_comment fills in: integer ids, the user from user_ID, a
     * parent only when it is approved or held, the request's address and
     * agent, the dates now, the type "comment".
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private static function filledIn(array $data): array
    {
        if (isset($data['user_ID'])) {
            $data['user_ID'] = (int) $data['user_ID'];
            $data['user_id'] = $data['user_ID'];
        }
        $given = ['comment_post_ID' => null, 'comment_parent' => null, 'comment_author_IP' => null, 'comment_agent' => null, 'comment_date' => null, 'comment_date_gmt' => null, 'comment_type' => null];
        ['comment_post_ID' => $postId, 'comment_parent' => $parent, 'comment_author_IP' => $address, 'comment_agent' => $agent, 'comment_date' => $date, 'comment_date_gmt' => $dateGmt, 'comment_type' => $type] = $data + $given;
        $request = Runtime::current()->request;
        $parent = (int) ($parent ?? 0);
        return array_replace($data, [
            'comment_post_ID' => (int) ($postId ?? 0),
            'user_id' => (int) ($data['user_id'] ?? 0),
            'comment_parent' => $parent > 0 && in_array(self::status($parent), ['approved', 'unapproved'], true) ? $parent : 0,
            'comment_author_IP' => (string) preg_replace('/[^0-9a-fA-F:., ]/', '', (string) ($address ?? ($request?->remoteAddress ?? ''))),
            'comment_agent' => substr((string) ($agent ?? ($request?->header('user-agent') ?? '')), 0, 254),
            'comment_date' => $date ?? SiteTime::now('mysql'),
            'comment_date_gmt' => $dateGmt ?? SiteTime::now('mysql', true),
            'comment_type' => (string) ($type ?? '') === '' ? 'comment' : $type,
        ]);
    }

    /** A refusal: the error when one was asked for, else the stop page with the status. */
    private static function refused(mixed $wpError, string $code, mixed $message, int $status): mixed
    {
        if ($wpError) {
            return Handoff::newError($code, $message, $status);
        }
        Handoff::stop($message, $status);
        return null;
    }

    private static function comments(): Comments
    {
        return new Comments(Runtime::current()->db);
    }
}
