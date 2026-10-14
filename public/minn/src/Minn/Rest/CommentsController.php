<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Objects\Comment;
use Minn\Objects\CommentsQuery;
use Minn\Runtime\CommentEvents;
use Minn\Http\Policy;
use Minn\Http\Args;
use Minn\Http\Subject;
use Minn\Http\Access;
use Minn\Content\PostRecord;
use Minn\Content\CommentRecord;
use Minn\Content\Comments;
use Minn\Content\Posts;
use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;
use Minn\RestError;
use Minn\Runtime\Handoff;
use Minn\Runtime\Runtime;
use Minn\Support\Email;

/**
 * wp/v2/comments: the status tabs with pagination headers, single,
 * signed-in replies, moderation updates, trash, and force delete.
 */
final readonly class CommentsController
{
    public function __construct(
        private Comments $comments,
        private Posts $posts,
        private CommentObject $object,
        private Caller $caller,
    ) {
    }

    /**
     * The comments list as the reference serves it: the request's
     * WP_Comment_Query through rest_comment_query, its totals (counted again
     * when a page comes back empty), and the comments the caller may read.
     */
    #[Route(Method::Get, '/wp/v2/comments', policy: new Policy(Access::Public), args: [Args::CONTEXT, Args::COMMENTS])]
    public function list(Request $request): Response
    {
        $context = Context::of($request);
        $wp = RuntimeRoutes::sanitized($request, Args::CONTEXT + Args::COMMENTS);
        $this->allowed($wp, $context, (string) $request->query('author_email', ''));
        $registered = (array) Runtime::hooks()->filter('rest_comment_collection_params', [Args::CONTEXT + Args::COMMENTS]);
        $args = (array) Runtime::hooks()->filter('rest_comment_query', [CommentListArgs::of($wp, $registered, $request->method->value), $wp]);
        $query = Handoff::newCommentQuery();
        $found = $query->query($args);
        [$total, $pages] = self::totals($query, $args, (int) $wp['per_page']);
        if ($request->method === Method::Head) {
            return Reply::list([], $total, $pages, null);
        }
        $password = is_string($wp['password']) ? $wp['password'] : '';
        $objects = [];
        foreach (is_array($found) ? $found : [] as $comment) {
            if ($comment instanceof Comment && $this->readable($comment, $password)) {
                $objects[] = $this->object->build(CommentRecord::fromRow($comment->to_array()), $context);
            }
        }
        return Reply::list($objects, $total, $pages, Fields::fromQuery($request->query));
    }

    /**
     * The total and the page count: the query's own, or, when it found
     * nothing (a page past the end), a count without the page.
     *
     * @param array<string, mixed> $args
     * @return array{0: int, 1: int}
     */
    private static function totals(CommentsQuery $query, array $args, int $perPage): array
    {
        $total = (int) $query->found_comments;
        if ($total >= 1) {
            return [$total, (int) $query->max_num_pages];
        }
        unset($args['number'], $args['offset']);
        $total = (int) (Handoff::newCommentQuery())->query(['count' => true, 'orderby' => 'none'] + $args);
        return [$total, $perPage > 0 ? (int) ceil($total / $perPage) : 0];
    }

    /** One comment, if the caller may read it. */
    #[Route(Method::Get, '/wp/v2/comments/{id:[\d]+}', policy: new Policy(Access::Public, subject: Subject::Comment, param: 'id'), args: [Args::CONTEXT])]
    public function single(Request $request, string $id): Response
    {
        $comment = $this->plainComment((int) $id);
        $moderator = $this->caller->can('moderate_comments');
        if ($comment->approved !== '1' && !$moderator) {
            throw $this->caller->refuse('rest_cannot_read', 'Sorry, you are not allowed to read this comment.');
        }
        $post = $this->posts->find($comment->postId);
        if (!$moderator && ($post === null || !$post->isPublished() || $post->isProtected()) && !$this->caller->can('edit_post', $comment->postId)) {
            throw $this->caller->refuse('rest_cannot_read', 'Sorry, you are not allowed to read this comment.');
        }
        $context = Context::of($request);
        $edit = $context->isEdit();
        if ($edit && !$moderator) {
            throw $this->caller->refuse('rest_forbidden_context', 'Sorry, you are not allowed to edit comments.');
        }
        return Reply::item($this->object->build($comment, $context), Fields::fromQuery($request->query));
    }

    /** A signed-in reply; the author fields come from the user. */
    #[Route(Method::Post, '/wp/v2/comments', policy: new Policy(Access::SignedIn, signIn: 'rest_comment_login_required', signInMessage: 'Sorry, you must be logged in to comment.'))]
    public function create(Request $request): Response
    {
        $this->caller->require('rest_comment_login_required', 'Sorry, you must be logged in to comment.');
        $body = $request->json();
        // Only a moderator names another author, an address other than the request's, or the status; refused before anything else, as on the reference.
        $moderates = $this->caller->can('moderate_comments');
        $refused = match (true) {
            isset($body['author']) && (int) $body['author'] !== $this->caller->id() && !$moderates => 'author',
            isset($body['author_ip']) && !$moderates && (string) $body['author_ip'] !== $request->remoteAddress => 'author_ip',
            isset($body['status']) && !$moderates => 'status',
            default => null,
        };
        if ($refused !== null) {
            throw $this->caller->refuse($refused === 'author_ip' ? 'rest_comment_invalid_author_ip' : "rest_comment_invalid_{$refused}", "Sorry, you are not allowed to edit '{$refused}' for comments.");
        }
        $postId = (int) ($body['post'] ?? 0);
        $post = $this->posts->find($postId);
        if ($post === null) {
            throw new RestError('rest_comment_invalid_post_id', 'Sorry, you are not allowed to create this comment without a post.', 403);
        }
        if ($post->isTrashed()) {
            throw new RestError('rest_comment_trash_post', 'Sorry, you are not allowed to create a comment on this post.', 403);
        }
        if (!$post->isPublished() && !$this->caller->can('edit_post', $postId)) {
            throw new RestError('rest_comment_draft_post', 'Sorry, you are not allowed to create a comment on this post.', 403);
        }
        if (!$this->readablePost($post, is_string($body['password'] ?? null) ? $body['password'] : '')) {
            throw $this->caller->refuse('rest_cannot_read_post', 'Sorry, you are not allowed to read the post for this comment.');
        }
        if ($post->commentStatus !== 'open') {
            throw new RestError('rest_comment_closed', 'Sorry, comments are closed for this item.', 403);
        }
        $events = $this->events();
        $id = $events->restCreate(['comment_post_ID' => $postId, 'comment_parent' => (int) ($body['parent'] ?? 0)] + $this->prepared($body, $request), $request);
        if (isset($body['status']) && $this->caller->can('moderate_comments')) {
            CommentEvents::changeStatus($id, (string) $body['status']);
        }
        $events->restSaved($id, $request, null);
        return Reply::item($this->object->build($this->comments->find($id), Context::Edit), Fields::fromQuery($request->query), 201)
            ->withHeader('Location', $this->object->url()->to('/wp/v2/comments/' . $id));
    }

    /** Tells plugins about the writes, through the runtime's comment functions. */
    private function events(): CommentEvents
    {
        return new CommentEvents($this->comments);
    }

    /** Status flips and content or author edits, for moderators. */
    #[Route(Method::Post, '/wp/v2/comments/{id:[\d]+}', policy: new Policy(Access::Cap, 'moderate_comments', param: 'id', subject: Subject::Comment, signIn: 'rest_cannot_edit', signInMessage: 'Sorry, you are not allowed to edit this comment.', refuse: 'rest_cannot_edit', message: 'Sorry, you are not allowed to edit this comment.'))]
    #[Route(Method::Put, '/wp/v2/comments/{id:[\d]+}', policy: new Policy(Access::Cap, 'moderate_comments', param: 'id', subject: Subject::Comment, signIn: 'rest_cannot_edit', signInMessage: 'Sorry, you are not allowed to edit this comment.', refuse: 'rest_cannot_edit', message: 'Sorry, you are not allowed to edit this comment.'))]
    #[Route(Method::Patch, '/wp/v2/comments/{id:[\d]+}', policy: new Policy(Access::Cap, 'moderate_comments', param: 'id', subject: Subject::Comment, signIn: 'rest_cannot_edit', signInMessage: 'Sorry, you are not allowed to edit this comment.', refuse: 'rest_cannot_edit', message: 'Sorry, you are not allowed to edit this comment.'))]
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
        }
        $events = $this->events();
        $events->restUpdate($comment, $this->prepared($body, $request), isset($body['status']) ? (string) $body['status'] : null, $request);
        $events->restSaved($commentId, $request, $comment);
        return Reply::item($this->object->build($this->comments->find($commentId), Context::Edit), Fields::fromQuery($request->query));
    }

    /** Trash remembers where the comment came from; force removes it outright. */
    #[Route(Method::Delete, '/wp/v2/comments/{id:[\d]+}', policy: new Policy(Access::Cap, 'moderate_comments', param: 'id', subject: Subject::Comment, signIn: 'rest_cannot_delete', signInMessage: 'Sorry, you are not allowed to delete this comment.', refuse: 'rest_cannot_delete', message: 'Sorry, you are not allowed to delete this comment.'))]
    public function delete(Request $request, string $id): Response
    {
        $commentId = (int) $id;
        $comment = $this->plainComment($commentId);
        if (!$this->caller->can('moderate_comments')) {
            throw $this->caller->refuse('rest_cannot_delete', 'Sorry, you are not allowed to delete this comment.');
        }
        $fields = Fields::fromQuery($request->query);
        $events = $this->events();
        // Whether the comment may go to the trash is asked on every delete, forced or not (probe hook-inputs-2).
        $trashable = (bool) Runtime::hooks()->filter('rest_comment_trashable', [EMPTY_TRASH_DAYS > 0, Handoff::commentInstance($commentId)]);
        if ($request->flagged('force')) {
            $data = ['deleted' => true, 'previous' => array_diff_key($this->object->build($comment, Context::Edit), ['_links' => true])];
            $events->delete($comment);
            $events->restDeleted($comment, $data, $request);
            return Reply::item($data, $fields);
        }
        if (!$trashable) {
            throw new RestError('rest_trash_not_supported', "The comment does not support trashing. Set 'force=true' to delete.", 501);
        }
        if ($comment->approved === 'trash') {
            throw new RestError('rest_already_trashed', 'The comment has already been trashed.', 410);
        }
        $events->trash($comment);
        $trashed = $this->comments->find($commentId);
        $data = $this->object->build($trashed, Context::Edit);
        $events->restDeleted($trashed, $data, $request);
        return Reply::item($data, $fields);
    }

    /**
     * What this caller may ask the list for: the edit context and other
     * statuses are for moderators and editors; comments without a post
     * belong to moderators, an unreadable post's to those who may read it
     * (or who give its password); type, author, author_exclude and
     * author_email to anyone who can edit posts (the email judged as sent,
     * before sanitizing empties a bad one).
     */
    private function allowed(\WP_REST_Request $wp, Context $context, string $email): void
    {
        if ($context->isEdit() && !$this->caller->can('moderate_comments')) {
            throw $this->caller->refuse('rest_forbidden_context', 'Sorry, you are not allowed to edit comments.');
        }
        if ($wp['status'] !== 'approve' && !$this->caller->can('edit_posts')) {
            throw $this->caller->refuse('rest_forbidden_param', 'Query parameter not permitted: status');
        }
        $password = is_string($wp['password']) ? $wp['password'] : '';
        foreach (array_map('intval', (array) $wp['post']) as $postId) {
            if ($postId === 0 && !$this->caller->can('moderate_comments')) {
                throw $this->caller->refuse('rest_cannot_read', 'Sorry, you are not allowed to read comments without a post.');
            }
            $post = $postId === 0 ? null : $this->posts->find($postId);
            if ($post !== null && !$this->readablePost($post, $password)) {
                throw $this->caller->refuse('rest_cannot_read_post', 'Sorry, you are not allowed to read the post for this comment.');
            }
        }
        foreach (['type' => !in_array($wp['type'], ['', 'comment', null], true), 'author' => !empty($wp['author']), 'author_exclude' => !empty($wp['author_exclude'])] as $param => $used) {
            if ($used && !$this->caller->can('edit_posts')) {
                throw $this->caller->refuse('rest_forbidden_param', "Query parameter not permitted: {$param}");
            }
        }
        if ($email !== '') {
            if (Email::check($email) !== null) {
                throw new RestError('rest_invalid_param', 'Invalid parameter(s): author_email', 400, [
                    'params' => ['author_email' => 'Invalid email address.'],
                    'details' => ['author_email' => ['code' => 'rest_invalid_email', 'message' => 'Invalid email address.', 'data' => null]],
                ]);
            }
            if (!$this->caller->can('edit_posts')) {
                throw $this->caller->refuse('rest_forbidden_param', 'Query parameter not permitted: author_email');
            }
        }
    }

    /**
     * A post whose comments this caller may read: a protected one only with
     * its password or for someone who may edit it, as the reference reads
     * it; otherwise one it may read, or any published one.
     */
    private function readablePost(PostRecord $post, string $password): bool
    {
        if ($post->isProtected() && !hash_equals($post->password, $password)) {
            return $this->caller->can('edit_post', $post->id);
        }
        return $this->caller->can('read_post', $post->id) || $post->isPublished();
    }

    /** A listed comment this caller may read: a moderator any, its author their own, anyone an approved one on a post they may read. */
    private function readable(Comment $comment, string $password): bool
    {
        if ($this->caller->can('moderate_comments') || ((int) $comment->user_id > 0 && (int) $comment->user_id === $this->caller->id())) {
            return true;
        }
        $post = (int) $comment->comment_post_ID > 0 ? $this->posts->find((int) $comment->comment_post_ID) : null;
        return $comment->comment_approved === '1' && $post !== null && $this->readablePost($post, $password);
    }

    /** A comment row of the plain kind, or the reference's invalid-id error. */
    private function plainComment(int $id): CommentRecord
    {
        $comment = $this->comments->find($id);
        if ($comment === null || !$comment->isComment()) {
            throw new RestError('rest_comment_invalid_id', 'Invalid comment ID.', 404);
        }
        return $comment;
    }

    /**
     * Comment markup: the comment allowlist unless the caller has
     * unfiltered_html, and every link marked nofollow ugc, as the
     * reference stores it.
     */
    /**
     * The fields a request sends, as the reference's controller prepares
     * them for the runtime: the content, the author fields, the address (a
     * moderator may name one) and the browser.
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function prepared(array $body, Request $request): array
    {
        $prepared = [];
        if (isset($body['content'])) {
            $prepared += ['comment_content' => is_array($body['content']) ? (string) ($body['content']['raw'] ?? '') : (string) $body['content']];
        }
        if (isset($body['author'])) {
            $prepared += ['user_id' => (int) $body['author']];
        }
        foreach (['author_name' => 'comment_author', 'author_email' => 'comment_author_email', 'author_url' => 'comment_author_url'] as $field => $column) {
            if (isset($body[$field])) {
                $prepared[$column] = (string) $body[$field];
            }
        }
        $address = isset($body['author_ip']) && $this->caller->can('moderate_comments') ? (string) $body['author_ip'] : $request->remoteAddress;
        $prepared += ['comment_author_IP' => filter_var($address, FILTER_VALIDATE_IP) !== false ? $address : '127.0.0.1'];
        $agent = (string) ($body['author_user_agent'] ?? '') !== '' ? (string) $body['author_user_agent'] : (string) ($request->header('user-agent') ?? '');
        return $agent === '' ? $prepared : $prepared + ['comment_agent' => $agent];
    }
}
