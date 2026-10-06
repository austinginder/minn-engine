<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Content\CommentRecord;
use Minn\Content\Comments;
use Minn\Http\Request;
use Minn\Rest\RuntimeRoutes;
use Minn\RestError;

/**
 * What the reference's REST comments controller tells plugins, for the
 * engine's own: with plugins loaded every change goes through the
 * runtime's comment functions (wp_insert_comment, wp_update_comment,
 * wp_set_comment_status, wp_trash_comment, wp_delete_comment), which fire
 * the reference's actions in its order, and the REST actions follow.
 * Without a booted runtime the rows are written as before and nothing is
 * told.
 */
final readonly class CommentEvents
{
    public function __construct(private Comments $comments)
    {
    }

    /** Whether plugins are loaded to be told anything. */
    public function live(): bool
    {
        return Runtime::booted();
    }

    /** The check a new comment passes on the reference before it is written: check_comment_flood, with the address, the email and the GMT date. */
    public function allow(string $address, string $email, string $dateGmt): void
    {
        if ($this->live()) {
            \do_action('check_comment_flood', $address, $email, $dateGmt, true);
        }
    }

    /**
     * A comment created over REST with plugins loaded, as the reference's
     * controller creates one (probe rest-comment-save): the request's
     * fields through rest_preprocess_comment, the signed-in author filled
     * in, the content check (allow_empty_comment), the date, the length
     * check, wp_allow_comment (a duplicate is a 409, a flood a 400),
     * rest_pre_insert_comment, then wp_insert_comment over
     * wp_filter_comment; a refusal is its REST error.
     *
     * @param array<string, mixed> $prepared the request's fields as the controller prepares them
     */
    public function restCreate(array $prepared, Request $request): int
    {
        $wpRequest = RuntimeRoutes::wpRequest($request);
        $prepared = array_merge(self::preprocessed($prepared, $wpRequest), ['comment_type' => 'comment']) + ['comment_content' => ''];
        $user = \wp_get_current_user();
        $authored = array_filter(array_intersect_key($prepared, array_flip(['user_id', 'comment_author', 'comment_author_email', 'comment_author_url'])));
        if ($user->ID > 0 && $authored === []) {
            $prepared = ['user_id' => $user->ID, 'comment_author' => $user->display_name, 'comment_author_email' => $user->user_email, 'comment_author_url' => $user->user_url] + $prepared;
        }
        self::requireContent($prepared);
        $prepared += ['comment_date_gmt' => \current_time('mysql', true), 'comment_author_email' => '', 'comment_author_url' => '', 'comment_agent' => ''];
        self::requireLengths($prepared);
        $approved = \wp_allow_comment($prepared, true);
        if ($approved instanceof \WP_Error) {
            $status = ['comment_duplicate' => 409, 'comment_flood' => 400][$approved->get_error_code()] ?? null;
            throw $status === null ? self::refusal($approved) : new RestError($approved->get_error_code(), $approved->get_error_message(), $status);
        }
        $prepared = \apply_filters('rest_pre_insert_comment', array_merge($prepared, ['comment_approved' => $approved]), $wpRequest);
        if ($prepared instanceof \WP_Error) {
            throw self::refusal($prepared);
        }
        $id = (int) \wp_insert_comment(\wp_filter_comment(\wp_slash((array) $prepared)));
        if ($id < 1) {
            throw new RestError('rest_comment_failed_create', 'Creating comment failed.', 500);
        }
        return $id;
    }

    /**
     * A comment changed over REST with plugins loaded: the request's fields
     * through rest_preprocess_comment, the content and length checks,
     * wp_update_comment, then the status, as the reference's controller
     * changes one.
     *
     * @param array<string, mixed> $prepared
     */
    public function restUpdate(CommentRecord $comment, array $prepared, ?string $status, Request $request): void
    {
        $prepared = self::preprocessed($prepared, RuntimeRoutes::wpRequest($request));
        if ($prepared !== []) {
            // An edit's content is checked only when it sends some.
            if (array_key_exists('comment_content', $prepared)) {
                self::requireContent($prepared);
            }
            self::requireLengths($prepared);
            $updated = \wp_update_comment(\wp_slash(['comment_ID' => $comment->id] + $prepared), true);
            if ($updated instanceof \WP_Error) {
                throw new RestError('rest_comment_failed_edit', 'Updating comment failed.', 500);
            }
        }
        if ($status !== null) {
            self::changeStatus($comment->id, $status);
        }
    }

    /**
     * A REST status asked for, as the reference's controller changes it:
     * nothing when it is already so; approve and hold through
     * wp_set_comment_status with that word, spam, unspam, trash and untrash
     * through their own functions.
     */
    public static function changeStatus(int $id, string $asked): void
    {
        if ($asked === \wp_get_comment_status($id)) {
            return;
        }
        match ($asked) {
            'approved', 'approve', '1' => \wp_set_comment_status($id, 'approve'),
            'hold', '0' => \wp_set_comment_status($id, 'hold'),
            'spam' => \wp_spam_comment($id),
            'unspam' => \wp_unspam_comment($id),
            'trash' => \wp_trash_comment($id),
            'untrash' => \wp_untrash_comment($id),
            default => null,
        };
    }

    /**
     * rest_preprocess_comment over the prepared comment.
     *
     * @param array<string, mixed> $prepared
     * @return array<string, mixed>
     */
    private static function preprocessed(array $prepared, \WP_REST_Request $request): array
    {
        $prepared = \apply_filters('rest_preprocess_comment', $prepared, $request);
        if ($prepared instanceof \WP_Error) {
            throw self::refusal($prepared);
        }
        return (array) $prepared;
    }

    /**
     * The reference's content check: allow_empty_comment, handed the comment
     * with the author, parent, post and user filled in; empty content is a 400.
     *
     * @param array<string, mixed> $prepared
     */
    private static function requireContent(array $prepared): void
    {
        $check = $prepared + ['comment_post_ID' => 0, 'comment_author' => null, 'comment_author_email' => null, 'comment_author_url' => null, 'comment_parent' => 0, 'user_id' => 0];
        $content = (string) (self::field($check, 'comment_content') ?? '');
        if (!\apply_filters('allow_empty_comment', false, $check) && trim(strip_tags($content)) === '') {
            throw new RestError('rest_comment_content_invalid', 'Invalid comment content.', 400);
        }
    }

    /** @param array<string, mixed> $row */
    private static function field(array $row, string $key): mixed
    {
        return $row[$key] ?? null;
    }

    /** @param array<string, mixed> $prepared */
    private static function requireLengths(array $prepared): void
    {
        $long = \wp_check_comment_data_max_lengths($prepared);
        if ($long instanceof \WP_Error) {
            throw new RestError($long->get_error_code(), $long->get_error_message(), 400);
        }
    }

    /** A plugin's error as REST serves it: its own status, or 500. */
    private static function refusal(\WP_Error $error): RestError
    {
        $data = $error->get_error_data();
        return is_array($data) && isset($data['status']) ? new RestError($error->get_error_code(), $error->get_error_message(), (int) $data['status']) : RestError::bare($error->get_error_code(), $error->get_error_message());
    }

    /** Writes a new comment and returns its id; an approved one is counted on its post. @param array<string, mixed> $columns */
    public function insert(array $columns): int
    {
        if ($this->live()) {
            return (int) \wp_insert_comment($columns);
        }
        $id = $this->comments->insert($columns);
        $comment = $this->comments->find($id);
        if ($comment !== null && $comment->isApproved()) {
            $this->comments->recount($comment->postId);
        }
        return $id;
    }

    /**
     * An edit through REST: the fields, as wp_update_comment writes them
     * (which tells plugins even when nothing changed), then the status
     * through wp_set_comment_status when it moves.
     *
     * @param array<string, string> $columns
     */
    public function update(CommentRecord $comment, array $columns, ?string $status): void
    {
        if (!$this->live()) {
            if ($columns !== []) {
                $this->comments->update($comment->id, $columns);
            }
            if ($status !== null) {
                $this->comments->update($comment->id, ['comment_approved' => $status]);
                $this->comments->recount($comment->postId);
            }
            return;
        }
        \wp_update_comment(['comment_ID' => $comment->id] + $columns);
        if ($status === null || $status === $comment->approved) {
            return;
        }
        // The REST controller names the status in words, and sends spam and trash through their own functions.
        match ($status) {
            '1' => \wp_set_comment_status($comment->id, 'approve'),
            '0' => \wp_set_comment_status($comment->id, 'hold'),
            'spam' => \wp_spam_comment($comment->id),
            'trash' => \wp_trash_comment($comment->id),
            default => \wp_set_comment_status($comment->id, $status),
        };
    }

    /** Moves a comment to the trash, keeping its status and the time for the way back. */
    public function trash(CommentRecord $comment): void
    {
        if ($this->live()) {
            \wp_trash_comment($comment->id);
            return;
        }
        $this->comments->addMeta($comment->id, '_wp_trash_meta_status', $comment->approved);
        $this->comments->addMeta($comment->id, '_wp_trash_meta_time', (string) time());
        $this->comments->update($comment->id, ['comment_approved' => 'trash']);
        $this->comments->recount($comment->postId);
    }

    /** Removes a comment for good. */
    public function delete(CommentRecord $comment): void
    {
        if ($this->live()) {
            \wp_delete_comment($comment->id, true);
            return;
        }
        $this->comments->delete($comment->id);
        $this->comments->recount($comment->postId);
    }

    /** rest_insert_comment, then rest_after_insert_comment, with the comment as it stands and the request; no $before is a new comment. */
    public function restSaved(int $id, Request $request, ?CommentRecord $before): void
    {
        if (!$this->live()) {
            return;
        }
        $wpRequest = RuntimeRoutes::wpRequest($request);
        \do_action('rest_insert_comment', \get_comment($id), $wpRequest, $before === null);
        \do_action('rest_after_insert_comment', \get_comment($id), $wpRequest, $before === null);
    }

    /** rest_delete_comment, after a trash or a delete, with the comment as it was and the response. @param array<string, mixed> $data */
    public function restDeleted(CommentRecord $comment, array $data, Request $request): void
    {
        if ($this->live()) {
            \do_action('rest_delete_comment', new \WP_Comment((object) $comment->row()), new \WP_REST_Response($data, 200), RuntimeRoutes::wpRequest($request));
        }
    }
}
