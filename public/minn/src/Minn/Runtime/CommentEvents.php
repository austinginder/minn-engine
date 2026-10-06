<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Content\CommentRecord;
use Minn\Content\Comments;
use Minn\Http\Request;
use Minn\Rest\RuntimeRoutes;

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
