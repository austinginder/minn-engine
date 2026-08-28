<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Content\Comments;
use Minn\Content\Posts;
use Minn\Content\Site;
use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;
use Minn\RestError;
use Minn\Support\Kses;

/**
 * wp/v2/comments: the status tabs with pagination headers, single,
 * signed-in replies, moderation updates, trash, and force delete.
 */
final readonly class CommentsController
{
    public function __construct(
        private Comments $comments,
        private Posts $posts,
        private Site $site,
        private CommentObject $object,
        private Caller $caller,
    ) {
    }

    #[Route(Method::Get, '/wp/v2/comments')]
    public function list(Request $request): Response
    {
        $context = $request->query('context') === 'edit' ? 'edit' : 'view';
        $status = (string) $request->query('status', 'approve');
        if ($context === 'edit' && !$this->caller->can('moderate_comments')) {
            throw $this->caller->refuse('rest_forbidden_context', 'Sorry, you are not allowed to edit comments.');
        }
        if ($status !== 'approve' && !$this->caller->can('edit_posts')) {
            throw $this->caller->refuse('rest_forbidden_param', 'Query parameter not permitted: status');
        }
        // An unknown status simply matches nothing.
        $tokens = Comments::tokensFor($status) ?? [$status];
        $perPage = max(1, min(100, (int) $request->query('per_page', '10')));
        $page = max(1, (int) $request->query('page', '1'));
        $result = $this->comments->page($tokens, $page, $perPage, publicPostsOnly: !$this->caller->can('moderate_comments'));
        return Reply::list(
            array_map(fn (array $c) => $this->object->build($c, $context === 'edit'), $result['comments']),
            $result['total'],
            (int) ceil($result['total'] / $perPage),
            Fields::fromQuery($request->query),
        );
    }

    #[Route(Method::Get, '/wp/v2/comments/{id:\d+}')]
    public function single(Request $request, string $id): Response
    {
        $comment = $this->plainComment((int) $id);
        $moderator = $this->caller->can('moderate_comments');
        if ($comment['comment_approved'] !== '1' && !$moderator) {
            throw $this->caller->refuse('rest_cannot_read', 'Sorry, you are not allowed to read this comment.');
        }
        $post = $this->posts->find((int) $comment['comment_post_ID']);
        if (!$moderator && ($post === null || $post['post_status'] !== 'publish' || $post['post_password'] !== '') && !$this->caller->can('edit_post', (int) $comment['comment_post_ID'])) {
            throw $this->caller->refuse('rest_cannot_read', 'Sorry, you are not allowed to read this comment.');
        }
        $edit = $request->query('context') === 'edit';
        if ($edit && !$moderator) {
            throw $this->caller->refuse('rest_forbidden_context', 'Sorry, you are not allowed to edit comments.');
        }
        return Reply::item($this->object->build($comment, $edit), Fields::fromQuery($request->query));
    }

    /** A signed-in reply; the author fields come from the user. */
    #[Route(Method::Post, '/wp/v2/comments')]
    public function create(Request $request): Response
    {
        $session = $this->caller->require('rest_comment_login_required', 'Sorry, you must be logged in to comment.');
        $user = $session->user;
        $body = $request->json();
        $postId = (int) ($body['post'] ?? 0);
        $content = is_array($body['content'] ?? null) ? (string) ($body['content']['raw'] ?? '') : (string) ($body['content'] ?? '');
        $post = $this->posts->find($postId);
        if ($post === null) {
            throw new RestError('rest_comment_invalid_post_id', 'Sorry, you are not allowed to create this comment without a post.', 403);
        }
        if ($post['post_status'] === 'trash') {
            throw new RestError('rest_comment_trash_post', 'Sorry, you are not allowed to create a comment on this post.', 403);
        }
        if ($post['post_status'] !== 'publish' && !$this->caller->can('edit_post', $postId)) {
            throw new RestError('rest_comment_draft_post', 'Sorry, you are not allowed to create a comment on this post.', 403);
        }
        if ($post['comment_status'] !== 'open') {
            throw new RestError('rest_comment_closed', 'Sorry, comments are closed for this item.', 403);
        }
        if (trim($content) === '') {
            throw new RestError('rest_comment_content_invalid', 'Invalid comment content.', 400);
        }
        $content = $this->cleanComment($content);
        // A moderator self-approves; everyone else lands in the queue (the
        // previously-approved shortcut is a recorded gap).
        $approved = $this->caller->can('moderate_comments') ? '1' : '0';
        $id = $this->comments->insert([
            'comment_post_ID' => $postId,
            'comment_author' => $user['display_name'],
            'comment_author_email' => $user['user_email'],
            'comment_author_url' => $user['user_url'],
            'comment_author_IP' => $request->remoteAddress,
            'comment_date' => $this->site->localNow(),
            'comment_date_gmt' => gmdate('Y-m-d H:i:s'),
            'comment_content' => $content,
            'comment_karma' => 0,
            'comment_approved' => $approved,
            'comment_agent' => substr((string) ($request->header('user-agent') ?? ''), 0, 254),
            'comment_type' => 'comment',
            'comment_parent' => (int) ($body['parent'] ?? 0),
            'user_id' => $session->id(),
        ]);
        if ($approved === '1') {
            $this->comments->recount($postId);
        }
        return Reply::item($this->object->build($this->comments->find($id), true), Fields::fromQuery($request->query), 201)
            ->withHeader('Location', $this->object->url()->to('/wp/v2/comments/' . $id));
    }

    /** Status flips and content or author edits, for moderators. */
    #[Route(Method::Post, '/wp/v2/comments/{id:\d+}')]
    #[Route(Method::Put, '/wp/v2/comments/{id:\d+}')]
    #[Route(Method::Patch, '/wp/v2/comments/{id:\d+}')]
    public function update(Request $request, string $id): Response
    {
        $commentId = (int) $id;
        $comment = $this->plainComment($commentId);
        if (!$this->caller->can('moderate_comments')) {
            throw $this->caller->refuse('rest_cannot_edit', 'Sorry, you are not allowed to edit this comment.');
        }
        $body = $request->json();
        if (isset($body['status'])) {
            $tokens = Comments::tokensFor((string) $body['status']);
            if ($tokens === null || count($tokens) > 1) {
                throw new RestError('rest_invalid_param', 'Invalid parameter(s): status', 400);
            }
            $this->comments->update($commentId, ['comment_approved' => $tokens[0]]);
            $this->comments->recount((int) $comment['comment_post_ID']);
        }
        if (isset($body['content'])) {
            $content = is_array($body['content']) ? (string) ($body['content']['raw'] ?? '') : (string) $body['content'];
            $this->comments->update($commentId, ['comment_content' => $this->cleanComment($content)]);
        }
        $columns = ['author_name' => 'comment_author', 'author_email' => 'comment_author_email', 'author_url' => 'comment_author_url'];
        foreach ($columns as $field => $column) {
            if (isset($body[$field])) {
                $value = $column === 'comment_author_url' ? Kses::url((string) $body[$field]) : Kses::text((string) $body[$field]);
                $this->comments->update($commentId, [$column => $value]);
            }
        }
        return Reply::item($this->object->build($this->comments->find($commentId), true), Fields::fromQuery($request->query));
    }

    /** Trash remembers where the comment came from; force removes it outright. */
    #[Route(Method::Delete, '/wp/v2/comments/{id:\d+}')]
    public function delete(Request $request, string $id): Response
    {
        $commentId = (int) $id;
        $comment = $this->plainComment($commentId);
        if (!$this->caller->can('moderate_comments')) {
            throw $this->caller->refuse('rest_cannot_delete', 'Sorry, you are not allowed to delete this comment.');
        }
        $postId = (int) $comment['comment_post_ID'];
        $fields = Fields::fromQuery($request->query);
        if (filter_var($request->query('force', ''), FILTER_VALIDATE_BOOLEAN)) {
            $previous = $this->object->build($comment, true);
            $this->comments->delete($commentId);
            $this->comments->recount($postId);
            return Reply::item(['deleted' => true, 'previous' => $previous], $fields);
        }
        if ($comment['comment_approved'] === 'trash') {
            throw new RestError('rest_already_trashed', 'The comment has already been trashed.', 410);
        }
        $this->comments->addMeta($commentId, '_wp_trash_meta_status', (string) $comment['comment_approved']);
        $this->comments->addMeta($commentId, '_wp_trash_meta_time', (string) time());
        $this->comments->update($commentId, ['comment_approved' => 'trash']);
        $this->comments->recount($postId);
        return Reply::item($this->object->build($this->comments->find($commentId), true), $fields);
    }

    /** A comment row of the plain kind, or the reference's invalid-id error. */
    private function plainComment(int $id): array
    {
        $comment = $this->comments->find($id);
        if ($comment === null || !in_array($comment['comment_type'], ['', 'comment'], true)) {
            throw new RestError('rest_comment_invalid_id', 'Invalid comment ID.', 404);
        }
        return $comment;
    }

    /**
     * Comment markup: the comment allowlist unless the caller has
     * unfiltered_html, and every link marked nofollow ugc, as the
     * reference stores it.
     */
    private function cleanComment(string $content): string
    {
        if (!$this->caller->can('unfiltered_html')) {
            $content = Kses::filter($content, Kses::COMMENT);
        }
        return (string) preg_replace_callback('/<a\s([^>]*)>/i', static function (array $m): string {
            $attributes = preg_replace('/\s*\brel\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $m[1]);
            return '<a ' . trim((string) $attributes) . ' rel="nofollow ugc">';
        }, $content);
    }
}
