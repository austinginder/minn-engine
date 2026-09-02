<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Content\PostRecord;
use Minn\Auth\TypeCapabilities;
use Minn\Content\PostStatus;
use Minn\Content\Posts;
use Minn\Content\PostWriter;
use Minn\Content\Site;
use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;
use Minn\RestError;
use Minn\Support\Kses;

/**
 * wp/v2 posts and pages, write side: create, update, trash, and force
 * delete, each gated by the capability engine and answering with the
 * edit-context object the reference returns.
 */
final readonly class PostsWriteController
{
    /** The statuses that make a post live: they need the publish capability and give the post its slug. */
    private const LIVE = [PostStatus::Publish->value, PostStatus::Future->value, PostStatus::Private->value];

    public function __construct(
        private Posts $posts,
        private PostWriter $writer,
        private Site $site,
        private PostObject $object,
        private RestUrl $url,
        private Caller $caller,
    ) {
    }

    /** Creates a post or page. */
    #[Route(Method::Post, '/wp/v2/{base:posts|pages}')]
    public function create(Request $request, string $base): Response
    {
        return $this->serveCreate($request, $base === 'pages' ? 'page' : 'post', $base);
    }

    /** Creates a post of any type from the body. */
    public function serveCreate(Request $request, string $type, string $base): Response
    {
        $userId = $this->caller->require('rest_cannot_create', 'Sorry, you are not allowed to create posts as this user.')->id();
        if (!$this->caller->can(TypeCapabilities::edit($type))) {
            throw new RestError('rest_cannot_create', 'Sorry, you are not allowed to create posts as this user.', 403);
        }

        $body = $request->json();
        $status = self::validStatus((string) ($body['status'] ?? 'draft'));
        $this->checkStickyPasswordConflict($body, null);
        $author = $userId;
        if (isset($body['author']) && (int) $body['author'] !== $userId) {
            if (!$this->caller->can(TypeCapabilities::editOthers($type))) {
                throw new RestError('rest_cannot_edit_others', 'Sorry, you are not allowed to update posts as this user.', 403);
            }
            $author = (int) $body['author'];
        }
        if (in_array($status, self::LIVE, true) && !$this->caller->can(TypeCapabilities::publish($type))) {
            throw new RestError('rest_cannot_publish', 'Sorry, you are not allowed to create private posts in this post type.', 403);
        }

        $nowGmt = gmdate('Y-m-d H:i:s');
        $local = $this->site->localNow();
        $title = self::field($body['title'] ?? '');
        $slug = isset($body['slug']) ? $this->writer->uniqueSlug((string) $body['slug'], 0) : '';

        $date = $local;
        $dateGmt = ($status === 'publish' || $status === 'private') ? $nowGmt : '0000-00-00 00:00:00';
        $modified = $local;
        $modifiedGmt = $nowGmt;
        if (isset($body['date']) && (string) $body['date'] !== '') {
            // An explicit date is site-local; publishing into the future schedules.
            [$date, $dateGmt, $stamp] = $this->site->localDate((string) $body['date']);
            $modified = $date;
            $modifiedGmt = $dateGmt;
            if ($status === 'publish' && $stamp > time()) {
                $status = 'future';
            }
        }
        // A post that goes live without a slug gets one from its title;
        // drafts and pending posts keep an empty post_name until published.
        if ($slug === '' && in_array($status, self::LIVE, true) && $title !== '') {
            $slug = $this->writer->uniqueSlug($title, 0);
        }

        // The row, its extended fields, its guid and its terms are one post:
        // a failure part-way through leaves none of it rather than a stub.
        $id = $this->writer->db()->transaction(fn (): int => $this->writeNewPost($body, $type, $author, $status, $slug, $date, $dateGmt, $modified, $modifiedGmt, $title));

        return Reply::item($this->object->edit($this->posts->find($id), $userId), Fields::fromQuery($request->query), 201)
            ->withHeader('Location', $this->url->to('/wp/v2/' . $base . '/' . $id));
    }

    /**
     * Writes a new post and everything that belongs to it, as one unit.
     *
     * @param array<string, mixed> $body
     */
    private function writeNewPost(array $body, string $type, int $author, string $status, string $slug, string $date, string $dateGmt, string $modified, string $modifiedGmt, string $title): int
    {
        $id = $this->writer->insert([
            'post_author' => $author,
            'post_date' => $date,
            'post_date_gmt' => $dateGmt,
            'post_content' => $this->clean(self::field($body['content'] ?? '')),
            'post_title' => $this->clean($title),
            'post_excerpt' => $this->clean(self::field($body['excerpt'] ?? '')),
            'post_status' => $status,
            'comment_status' => in_array($body['comment_status'] ?? '', ['open', 'closed'], true) ? $body['comment_status'] : 'open',
            'ping_status' => in_array($body['ping_status'] ?? '', ['open', 'closed'], true) ? $body['ping_status'] : 'open',
            'post_password' => (string) ($body['password'] ?? ''),
            'post_name' => $slug,
            'post_parent' => $type === 'page' ? (int) ($body['parent'] ?? 0) : 0,
            'menu_order' => $type === 'page' ? (int) ($body['menu_order'] ?? 0) : 0,
            'post_modified' => $modified,
            'post_modified_gmt' => $modifiedGmt,
            'post_type' => $type,
            'guid' => '',
            'to_ping' => '',
            'pinged' => '',
            'post_content_filtered' => '',
        ]);
        $this->writer->applyExtendedFields($id, $body, $type);
        // The GUID is set from the id after insert, as the reference does.
        $this->writer->update($id, ['guid' => $this->url->home('/?' . ($type === 'page' ? 'page_id' : 'p') . '=' . $id)]);
        $this->writer->applyTerms($id, $body);
        // A post with no category given gets the site's default category.
        if ($type === 'post' && (!isset($body['categories']) || $body['categories'] === [])) {
            $this->writer->setTerms($id, 'category', [(int) ($this->site->option('default_category') ?? 1)]);
        }
        return $id;
    }

    /** Updates a post or page. */
    #[Route(Method::Post, '/wp/v2/{base:posts|pages}/{id:\d+}')]
    #[Route(Method::Put, '/wp/v2/{base:posts|pages}/{id:\d+}')]
    #[Route(Method::Patch, '/wp/v2/{base:posts|pages}/{id:\d+}')]
    public function update(Request $request, string $base, string $id): Response
    {
        return $this->serveUpdate($request, $base === 'pages' ? 'page' : 'post', $id);
    }

    /** Updates a post of any type from the body. */
    public function serveUpdate(Request $request, string $type, string $id): Response
    {
        $postId = (int) $id;
        $userId = $this->caller->require('rest_cannot_edit', 'Sorry, you are not allowed to edit this post.')->id();
        $post = $this->posts->find($postId);
        if ($post === null || $post->type !== $type) {
            throw new RestError('rest_post_invalid_id', 'Invalid post ID.', 404);
        }
        if (!$this->caller->can('edit_post', $postId)) {
            throw new RestError('rest_cannot_edit', 'Sorry, you are not allowed to edit this post.', 403);
        }

        $body = $this->scheduledIfFuture($request->json(), $post);
        $this->checkStickyPasswordConflict($body, $post);
        $columns = [...$this->fieldColumns($body, $post, $type), ...$this->statusColumns($body, $post, $type)];
        $columns['post_modified'] = $this->site->localNow();
        $columns['post_modified_gmt'] = gmdate('Y-m-d H:i:s');
        $this->writer->update($postId, $columns);

        $this->writer->applyTerms($postId, $body);
        $this->writer->applyExtendedFields($postId, $body, $type);
        // A publish/unpublish transition changes the terms' published counts.
        if (array_key_exists('status', $body) && $body['status'] !== $post->status) {
            $this->writer->recountTaxonomiesOf($postId);
        }
        $this->writer->maybeSaveRevision($postId, $userId);

        return Reply::item($this->object->edit($this->posts->find($postId), $userId), Fields::fromQuery($request->query));
    }

    /** Trashes or deletes a post or page. */
    #[Route(Method::Delete, '/wp/v2/{base:posts|pages}/{id:\d+}')]
    public function delete(Request $request, string $base, string $id): Response
    {
        return $this->serveDelete($request, $base === 'pages' ? 'page' : 'post', $id);
    }

    /** Trashes a post of any type, or deletes it with force. */
    public function serveDelete(Request $request, string $type, string $id): Response
    {
        $postId = (int) $id;
        $userId = $this->caller->require('rest_cannot_edit', 'Sorry, you are not allowed to edit this post.')->id();
        $post = $this->posts->find($postId);
        if ($post === null || $post->type !== $type) {
            throw new RestError('rest_post_invalid_id', 'Invalid post ID.', 404);
        }
        if (!$this->caller->can('delete_post', $postId)) {
            throw new RestError('rest_cannot_delete', 'Sorry, you are not allowed to delete this post.', 403);
        }
        $fields = Fields::fromQuery($request->query);

        if (!$request->flag('force')) {
            if ($post->isTrashed()) {
                throw new RestError('rest_already_trashed', 'The post has already been deleted.', 410);
            }
            $this->writer->setStatus($postId, 'trash');
            // The pre-trash status is kept the way the reference stores it.
            $this->writer->setMeta($postId, '_wp_trash_meta_status', $post->status);
            $this->writer->setMeta($postId, '_wp_trash_meta_time', (string) time());
            $this->writer->recountTaxonomiesOf($postId);
            return Reply::item($this->object->edit($this->posts->find($postId), $userId), $fields);
        }

        $previous = $this->object->edit($post, $userId);
        $this->writer->destroy($postId);
        return Reply::item(['deleted' => true, 'previous' => $previous], $fields);
    }

    /** A date in the future turns a publish into a schedule, whether the status was sent or kept. */
    private function scheduledIfFuture(array $body, PostRecord $post): array
    {
        if (!isset($body['date']) || (string) $body['date'] === '') {
            return $body;
        }
        [, , $stamp] = $this->site->localDate((string) $body['date']);
        if ($stamp > time() && (string) ($body['status'] ?? $post->status) === 'publish') {
            $body['status'] = 'future';
        }
        return $body;
    }

    /**
     * The columns the body's fields change, minus the status: author (gated
     * by edit_others_*), the two flags, password, the page attributes, the
     * date, the three markup fields through the caller's filter, the slug.
     *
     * @return array<string, mixed>
     */
    private function fieldColumns(array $body, PostRecord $post, string $type): array
    {
        $columns = [];
        if (isset($body['author']) && (int) $body['author'] !== $post->authorId) {
            if (!$this->caller->can(TypeCapabilities::editOthers($type))) {
                throw new RestError('rest_cannot_edit_others', 'Sorry, you are not allowed to update posts as this user.', 403);
            }
            $columns['post_author'] = (int) $body['author'];
        }
        foreach (['comment_status', 'ping_status'] as $flag) {
            if (isset($body[$flag]) && in_array($body[$flag], ['open', 'closed'], true)) {
                $columns[$flag] = (string) $body[$flag];
            }
        }
        if (array_key_exists('password', $body)) {
            $columns['post_password'] = (string) $body['password'];
        }
        if ($type === 'page') {
            foreach (['parent' => 'post_parent', 'menu_order' => 'menu_order'] as $field => $column) {
                if (isset($body[$field])) {
                    $columns[$column] = (int) $body[$field];
                }
            }
        }
        if (isset($body['date']) && (string) $body['date'] !== '') {
            [$columns['post_date'], $columns['post_date_gmt']] = $this->site->localDate((string) $body['date']);
        }
        foreach (['title' => 'post_title', 'content' => 'post_content', 'excerpt' => 'post_excerpt'] as $field => $column) {
            if (array_key_exists($field, $body)) {
                $columns[$column] = $this->clean(self::field($body[$field]));
            }
        }
        if (array_key_exists('slug', $body)) {
            $columns['post_name'] = $this->writer->uniqueSlug((string) $body['slug'], $post->id);
        }
        return $columns;
    }

    /**
     * The status change and what rides with it: going live needs the
     * publish cap and gives a slugless post one from its title; the first
     * move to publish stamps the publish date.
     *
     * @return array<string, mixed>
     */
    private function statusColumns(array $body, PostRecord $post, string $type): array
    {
        if (!array_key_exists('status', $body)) {
            return [];
        }
        $status = self::validStatus((string) $body['status']);
        $live = in_array($status, self::LIVE, true);
        if ($live && !$this->caller->can(TypeCapabilities::publish($type))) {
            throw new RestError('rest_cannot_publish', 'Sorry, you are not allowed to publish posts in this post type.', 403);
        }
        $columns = ['post_status' => $status];
        if ($live && $post->slug === '' && !array_key_exists('slug', $body)) {
            $title = array_key_exists('title', $body) ? self::field($body['title']) : $post->title;
            if ($title !== '') {
                $columns['post_name'] = $this->writer->uniqueSlug($title, $post->id);
            }
        }
        if ($status === 'publish' && !in_array($post->status, ['publish', 'private', 'future'], true)) {
            $columns['post_date'] = $this->site->localNow();
            $columns['post_date_gmt'] = gmdate('Y-m-d H:i:s');
        }
        return $columns;
    }

    /** The reference refuses a sticky and password combination outright. */
    private function checkStickyPasswordConflict(array $body, ?PostRecord $post): void
    {
        $wantsSticky = !empty($body['sticky'])
            || (!isset($body['sticky']) && $post !== null && $this->writer->isSticky($post->id));
        $wantsPassword = (string) ($body['password'] ?? '') !== ''
            || (!array_key_exists('password', $body) && $post !== null && $post->isProtected());
        if ($wantsSticky && $wantsPassword && (isset($body['sticky']) || array_key_exists('password', $body))) {
            throw new RestError('rest_invalid_field', 'A post can not be sticky and have a password.', 400);
        }
    }

    /** Only the registered statuses can be stored. */
    private static function validStatus(string $status): string
    {
        if (!in_array($status, ['publish', 'future', 'draft', 'pending', 'private'], true)) {
            throw new RestError('rest_invalid_param', 'Invalid parameter(s): status', 400, ['params' => ['status' => 'status is not one of publish, future, draft, pending, and private.']]);
        }
        return $status;
    }

    /** Markup from a caller without unfiltered_html goes through the allowlist filter. */
    private function clean(string $markup): string
    {
        return $this->caller->can('unfiltered_html') ? $markup : Kses::filter($markup, Kses::POST);
    }

    /** A field that may arrive as a scalar or as {raw: ...}. */
    public static function field(mixed $value): string
    {
        if (is_array($value)) {
            return (string) ($value['raw'] ?? $value['rendered'] ?? '');
        }
        return (string) $value;
    }
}
