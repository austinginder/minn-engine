<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Runtime\CommentEvents;
use Minn\Http\Policy;
use Minn\Http\Args;
use Minn\Http\Subject;
use Minn\Http\Access;
use Minn\Content\CommentFilter;
use Minn\Content\CommentRecord;
use Minn\Content\Comments;
use Minn\Content\Posts;
use Minn\Content\Site;
use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;
use Minn\RestError;
use Minn\Support\Email;
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

    /** The comments list with its status tabs and pagination headers. */
    #[Route(Method::Get, '/wp/v2/comments', policy: new Policy(Access::Public), args: [Args::CONTEXT, Args::COMMENTS])]
    public function list(Request $request): Response
    {
        $context = Context::of($request);
        $status = (string) $request->query('status', 'approve');
        if ($context->isEdit() && !$this->caller->can('moderate_comments')) {
            throw $this->caller->refuse('rest_forbidden_context', 'Sorry, you are not allowed to edit comments.');
        }
        if ($status !== 'approve' && !$this->caller->can('edit_posts')) {
            throw $this->caller->refuse('rest_forbidden_param', 'Query parameter not permitted: status');
        }
        // An unknown status simply matches nothing.
        $tokens = Comments::tokensFor($status) ?? [$status];
        $perPage = max(1, min(100, (int) $request->query('per_page', '10')));
        $page = max(1, (int) $request->query('page', '1'));
        $filter = $this->guarded(self::filter($request));
        $result = $this->comments->page(
            $tokens,
            $page,
            $perPage,
            $this->caller->can('moderate_comments') ? $filter : $filter->onPublicPosts(),
        );
        return Reply::list(
            array_map(fn (CommentRecord $c) => $this->object->build($c, $context), $result['comments']),
            $result['total'],
            (int) ceil($result['total'] / $perPage),
            Fields::fromQuery($request->query),
        );
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
        $session = $this->caller->require('rest_comment_login_required', 'Sorry, you must be logged in to comment.');
        $user = $session->user;
        $body = $request->json();
        $postId = (int) ($body['post'] ?? 0);
        $content = is_array($body['content'] ?? null) ? (string) ($body['content']['raw'] ?? '') : (string) ($body['content'] ?? '');
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
        if ($post->commentStatus !== 'open') {
            throw new RestError('rest_comment_closed', 'Sorry, comments are closed for this item.', 403);
        }
        $events = $this->events();
        if ($events->live()) {
            $id = $events->restCreate(['comment_post_ID' => $postId, 'comment_parent' => (int) ($body['parent'] ?? 0)] + $this->prepared($body, $request), $request);
            if (isset($body['status']) && $this->caller->can('moderate_comments')) {
                CommentEvents::changeStatus($id, (string) $body['status']);
            }
            $events->restSaved($id, $request, null);
            return Reply::item($this->object->build($this->comments->find($id), Context::Edit), Fields::fromQuery($request->query), 201)
                ->withHeader('Location', $this->object->url()->to('/wp/v2/comments/' . $id));
        }
        if (trim($content) === '') {
            throw new RestError('rest_comment_content_invalid', 'Invalid comment content.', 400);
        }
        $content = $this->cleanComment($content);
        // A moderator self-approves; everyone else lands in the queue (the
        // previously-approved shortcut is a recorded gap).
        $approved = $this->caller->can('moderate_comments') ? '1' : '0';
        $now = gmdate('Y-m-d H:i:s');
        $columns = [
            'comment_post_ID' => $postId,
            'comment_author' => $user->displayName,
            'comment_author_email' => $user->email,
            'comment_author_url' => $user->url,
            'comment_author_IP' => $request->remoteAddress,
            'comment_date' => $this->site->localNow(),
            'comment_date_gmt' => $now,
            'comment_content' => $content,
            'comment_karma' => 0,
            'comment_approved' => $approved,
            'comment_agent' => substr((string) ($request->header('user-agent') ?? ''), 0, 254),
            'comment_type' => 'comment',
            'comment_parent' => (int) ($body['parent'] ?? 0),
            'user_id' => $session->id(),
        ];
        // Through REST the reference writes the comment with wp_insert_comment,
        // which sends no notice: a held comment waits in the queue unannounced.
        $events->allow($request->remoteAddress, $user->email, $now);
        $id = $events->insert($columns);
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
        $status = null;
        if (isset($body['status'])) {
            $tokens = Comments::tokensFor((string) $body['status']);
            if ($tokens === null || count($tokens) > 1) {
                throw new RestError('rest_invalid_param', 'Invalid parameter(s): status', 400);
            }
            $status = $tokens[0];
        }
        $events = $this->events();
        if ($events->live()) {
            $events->restUpdate($comment, $this->prepared($body, $request), isset($body['status']) ? (string) $body['status'] : null, $request);
            $events->restSaved($commentId, $request, $comment);
            return Reply::item($this->object->build($this->comments->find($commentId), Context::Edit), Fields::fromQuery($request->query));
        }
        $columns = [];
        $fields = ['author_name' => 'comment_author', 'author_email' => 'comment_author_email', 'author_url' => 'comment_author_url'];
        if (isset($body['content'])) {
            $content = is_array($body['content']) ? (string) ($body['content']['raw'] ?? '') : (string) $body['content'];
            $columns += ['comment_content' => $this->cleanComment($content)];
        }
        foreach ($fields as $field => $column) {
            if (isset($body[$field])) {
                $columns[$column] = $column === 'comment_author_url' ? Kses::url((string) $body[$field]) : Kses::text((string) $body[$field]);
            }
        }
        $events->update($comment, $columns, $status);
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
        if ($request->flag('force')) {
            $data = ['deleted' => true, 'previous' => $this->object->build($comment, Context::Edit)];
            $events->delete($comment);
            $events->restDeleted($comment, $data, $request);
            return Reply::item($data, $fields);
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

    /** The collection parameters as the reference reads them; the caps are checked by guarded(). */
    private static function filter(Request $request): CommentFilter
    {
        return new CommentFilter(
            post: ListQuery::idsWithZero((string) $request->query('post', '')),
            include: ListQuery::idsWithZero((string) $request->query('include', '')),
            exclude: ListQuery::idsWithZero((string) $request->query('exclude', '')),
            parent: ListQuery::idsWithZero((string) $request->query('parent', '')),
            parentExclude: ListQuery::idsWithZero((string) $request->query('parent_exclude', '')),
            author: ListQuery::idsWithZero((string) $request->query('author', '')),
            authorExclude: ListQuery::idsWithZero((string) $request->query('author_exclude', '')),
            authorEmail: (string) $request->query('author_email', ''),
            type: (string) $request->query('type', 'comment') ?: 'comment',
            search: (string) $request->query('search', ''),
            after: self::date($request->query('after'), 'after'),
            before: self::date($request->query('before'), 'before'),
        );
    }

    /**
     * The filter, once this caller may use it: comments without a post
     * belong to moderators, an unreadable post's comments to its editors,
     * and type, author, and author_email to anyone who can edit posts.
     */
    private function guarded(CommentFilter $filter): CommentFilter
    {
        if (in_array(0, $filter->post, true) && !$this->caller->can('moderate_comments')) {
            throw $this->caller->refuse('rest_cannot_read', 'Sorry, you are not allowed to read comments without a post.');
        }
        foreach ($filter->post as $postId) {
            $post = $postId === 0 ? null : $this->posts->find($postId);
            if ($post !== null && (!$post->isPublished() || $post->isProtected()) && !$this->caller->can('read_post', $postId)) {
                throw $this->caller->refuse('rest_cannot_read_post', 'Sorry, you are not allowed to read the post for this comment.');
            }
        }
        foreach (['type' => !$filter->isPlainType(), 'author' => $filter->author !== [], 'author_exclude' => $filter->authorExclude !== []] as $param => $used) {
            if ($used && !$this->caller->can('edit_posts')) {
                throw $this->caller->refuse('rest_forbidden_param', "Query parameter not permitted: {$param}");
            }
        }
        if ($filter->authorEmail !== '') {
            if (Email::check($filter->authorEmail) !== null) {
                throw new RestError('rest_invalid_param', 'Invalid parameter(s): author_email', 400, [
                    'params' => ['author_email' => 'Invalid email address.'],
                    'details' => ['author_email' => ['code' => 'rest_invalid_email', 'message' => 'Invalid email address.', 'data' => null]],
                ]);
            }
            if (!$this->caller->can('edit_posts')) {
                throw $this->caller->refuse('rest_forbidden_param', 'Query parameter not permitted: author_email');
            }
        }
        return $filter;
    }

    /** A REST date-time as site-local "Y-m-d H:i:s", or the reference's parameter error. */
    private static function date(?string $value, string $param): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})?$/', $value) !== 1) {
            throw new RestError('rest_invalid_param', "Invalid parameter(s): {$param}", 400, [
                'params' => [$param => 'Invalid date.'],
                'details' => [$param => ['code' => 'rest_invalid_date', 'message' => 'Invalid date.', 'data' => null]],
            ]);
        }
        return (string) preg_replace('/(Z|[+-]\d{2}:\d{2})$/', '', str_replace('T', ' ', $value));
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

    private function cleanComment(string $content): string
    {
        if (!$this->caller->can('unfiltered_html')) {
            $content = Kses::comment($content);
        }
        return (string) preg_replace_callback('/<a\s([^>]*)>/i', static function (array $m): string {
            $attributes = preg_replace('/\s*\brel\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $m[1]);
            return '<a ' . trim((string) $attributes) . ' rel="nofollow ugc">';
        }, $content);
    }
}
