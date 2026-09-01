<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Content\PostRecord;
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
use Minn\Mail\Mailer;
use Minn\Mail\Message;

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
        $result = $this->comments->page(
            $tokens,
            $page,
            $perPage,
            publicPostsOnly: !$this->caller->can('moderate_comments'),
            filters: $this->listFilters($request),
        );
        return Reply::list(
            array_map(fn (array $c) => $this->object->build($c, $context->isEdit()), $result['comments']),
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
        $edit = Context::of($request)->isEdit();
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
        } elseif (($this->site->option('moderation_notify') ?? '1') === '1') {
            $this->notifyModerator($post, $content, (string) $user['display_name']);
        }
        return Reply::item($this->object->build($this->comments->find($id), true), Fields::fromQuery($request->query), 201)
            ->withHeader('Location', $this->object->url()->to('/wp/v2/comments/' . $id));
    }

    /** A comment in the queue is announced to the site's address when moderation_notify is on. */
    private function notifyModerator(array|PostRecord $post, string $content, string $author): void
    {
        $siteName = (string) ($this->site->option('blogname') ?? 'Site');
        $home = rtrim((string) ($this->site->option('home') ?? ''), '/');
        Mailer::forSite($this->site)->send(new Message(
            [(string) ($this->site->option('admin_email') ?? '')],
            '[' . $siteName . '] Please moderate: "' . $post['post_title'] . '"',
            "A new comment on the post \"{$post['post_title']}\" is waiting for your approval.\n\n"
            . "Author: {$author}\nComment:\n" . strip_tags($content) . "\n\n"
            . "Moderate it in the admin:\n{$home}/minn-admin/\n",
        ));
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

    /**
     * Collection filters captured from the oracle: include/exclude/parent
     * as id lists (0 is kept), search as a substring across content/author
     * /email, after/before exclusive on site-local comment_date.
     * author / author_exclude / author_email / a non-comment type need
     * edit_posts; post=0 without moderate_comments is rest_cannot_read.
     *
     * @return array<string, mixed>
     */
    private function listFilters(Request $request): array
    {
        $filters = [];
        $post = self::ids((string) $request->query('post', ''));
        if (in_array(0, $post, true) && !$this->caller->can('moderate_comments')) {
            throw $this->caller->refuse('rest_cannot_read', 'Sorry, you are not allowed to read comments without a post.');
        }
        foreach ($post as $postId) {
            if ($postId === 0) {
                continue;
            }
            $row = $this->posts->find($postId);
            if ($row !== null && ($row['post_status'] !== 'publish' || $row['post_password'] !== '') && !$this->caller->can('read_post', $postId)) {
                throw $this->caller->refuse('rest_cannot_read_post', 'Sorry, you are not allowed to read the post for this comment.');
            }
        }
        if ($post !== []) {
            $filters['post'] = $post;
        }
        $include = self::ids((string) $request->query('include', ''));
        if ($include !== []) {
            $filters['include'] = $include;
        }
        $exclude = self::ids((string) $request->query('exclude', ''));
        if ($exclude !== []) {
            $filters['exclude'] = $exclude;
        }
        $parent = self::ids((string) $request->query('parent', ''));
        if ($parent !== []) {
            $filters['parent'] = $parent;
        }
        $parentExclude = self::ids((string) $request->query('parent_exclude', ''));
        if ($parentExclude !== []) {
            $filters['parentExclude'] = $parentExclude;
        }
        $type = $request->query('type');
        if ($type !== null && $type !== '' && $type !== 'comment' && !$this->caller->can('edit_posts')) {
            throw $this->caller->refuse('rest_forbidden_param', 'Query parameter not permitted: type');
        }
        if ($type !== null && $type !== '') {
            $filters['type'] = $type;
        }
        $author = $request->query('author');
        if ($author !== null && $author !== '') {
            if (!$this->caller->can('edit_posts')) {
                throw $this->caller->refuse('rest_forbidden_param', 'Query parameter not permitted: author');
            }
            $ids = self::ids($author);
            if ($ids !== []) {
                $filters['author'] = $ids;
            }
        }
        $authorExclude = $request->query('author_exclude');
        if ($authorExclude !== null && $authorExclude !== '') {
            if (!$this->caller->can('edit_posts')) {
                throw $this->caller->refuse('rest_forbidden_param', 'Query parameter not permitted: author_exclude');
            }
            $ids = self::ids($authorExclude);
            if ($ids !== []) {
                $filters['authorExclude'] = $ids;
            }
        }
        $email = $request->query('author_email');
        if ($email !== null && $email !== '') {
            if (Email::check($email) !== null) {
                throw new RestError('rest_invalid_param', 'Invalid parameter(s): author_email', 400, [
                    'params' => ['author_email' => 'Invalid email address.'],
                    'details' => ['author_email' => ['code' => 'rest_invalid_email', 'message' => 'Invalid email address.', 'data' => null]],
                ]);
            }
            if (!$this->caller->can('edit_posts')) {
                throw $this->caller->refuse('rest_forbidden_param', 'Query parameter not permitted: author_email');
            }
            $filters['authorEmail'] = $email;
        }
        $search = $request->query('search');
        if ($search !== null && $search !== '') {
            $filters['search'] = $search;
        }
        $after = $request->query('after');
        if ($after !== null && $after !== '') {
            $filters['after'] = self::restDate($after, 'after');
        }
        $before = $request->query('before');
        if ($before !== null && $before !== '') {
            $filters['before'] = self::restDate($before, 'before');
        }
        return $filters;
    }

    /** @return list<int> */
    private static function ids(string $csv): array
    {
        $out = [];
        foreach (explode(',', $csv) as $part) {
            $part = trim($part);
            if ($part === '' || !ctype_digit($part)) {
                continue;
            }
            $out[] = (int) $part;
        }
        return array_values(array_unique($out));
    }

    private static function restDate(string $value, string $param): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})?$/', $value) !== 1) {
            throw new RestError('rest_invalid_param', "Invalid parameter(s): {$param}", 400, [
                'params' => [$param => 'Invalid date.'],
                'details' => [$param => ['code' => 'rest_invalid_date', 'message' => 'Invalid date.', 'data' => null]],
            ]);
        }
        return (string) preg_replace('/(Z|[+-]\d{2}:\d{2})$/', '', str_replace('T', ' ', $value));
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
