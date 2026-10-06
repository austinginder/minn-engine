<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Runtime\PostSave;
use Minn\Http\Policy;
use Minn\Http\Subject;
use Minn\Http\Access;
use Minn\Content\PostRecord;
use Minn\Runtime\PostEvents;
use Minn\Auth\TypeCapabilities;
use Minn\Content\PostStatus;
use Minn\Content\Posts;
use Minn\Content\PostWriter;
use Minn\Content\Site;
use Minn\Content\Slug;
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
    #[Route(Method::Post, '/wp/v2/{base:posts}', policy: new Policy(Access::Cap, 'edit_posts', signIn: 'rest_cannot_create', signInMessage: 'Sorry, you are not allowed to create posts as this user.', refuse: 'rest_cannot_create', message: 'Sorry, you are not allowed to create posts as this user.'))]
    #[Route(Method::Post, '/wp/v2/{base:pages}', policy: new Policy(Access::Cap, 'edit_pages', signIn: 'rest_cannot_create', signInMessage: 'Sorry, you are not allowed to create posts as this user.', refuse: 'rest_cannot_create', message: 'Sorry, you are not allowed to create posts as this user.'))]
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
        // A draft or pending post keeps the slug it asks for as it is; a pending one from someone who may not publish keeps none.
        $slug = match (true) {
            !isset($body['slug']) || ($status === 'pending' && !$this->caller->can(TypeCapabilities::publish($type))) => '',
            PostSave::keepsSlug($status, $type) => Slug::sanitize((string) $body['slug']),
            default => $this->writer->uniqueSlug((string) $body['slug'], 0, $type, (int) ($body['parent'] ?? 0)),
        };

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
            $slug = $this->writer->uniqueSlug($title, 0, $type, (int) ($body['parent'] ?? 0));
        }

        $columns = $this->newColumns($body, $type, $author, $status, $slug, $date, $dateGmt, $modified, $modifiedGmt, $title);
        $columns = PostSave::filter($columns, null, $body, $request, $type, $this->writer->slugs());
        $events = $this->events();
        $events->beforeSave($columns, null);
        // The row, its default category and its guid are the post the save
        // actions describe; the request's own fields and terms follow
        // rest_insert_{type}, as on the reference.
        $id = $this->writer->db()->transaction(fn (): int => $this->writeNewPost($columns));
        if ($type === 'post') {
            $events->ensureCategory($this->writer, $id);
        }
        $events->saved($id, null);
        $events->restInserted($id, $request, null);
        $this->writer->applyExtendedFields($id, $body, $type);
        $events->applyTerms($this->writer, $id, $body);
        $events->restAfterInsert($id, $request, null);
        $events->afterInsert($id, null);

        return Reply::item($this->object->edit($this->posts->find($id), $userId), Fields::fromQuery($request->query), 201)
            ->withHeader('Location', $this->url->to('/wp/v2/' . $base . '/' . $id));
    }

    /** Tells plugins about the writes, through the runtime's lifecycle. */
    private function events(): PostEvents
    {
        return new PostEvents();
    }

    /**
     * The row of a new post, from the body and what the create settled.
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function newColumns(array $body, string $type, int $author, string $status, string $slug, string $date, string $dateGmt, string $modified, string $modifiedGmt, string $title): array
    {
        return [
            'post_author' => $author,
            'post_date' => $date,
            'post_date_gmt' => $dateGmt,
            'post_content' => $this->clean(self::field($body['content'] ?? '')),
            'post_title' => $this->clean($title),
            'post_excerpt' => $this->clean(self::field($body['excerpt'] ?? '')),
            'post_status' => $status,
            'comment_status' => in_array($body['comment_status'] ?? '', ['open', 'closed'], true) ? $body['comment_status'] : $this->site->defaultDiscussion($type, 'comment'),
            'ping_status' => in_array($body['ping_status'] ?? '', ['open', 'closed'], true) ? $body['ping_status'] : $this->site->defaultDiscussion($type, 'pingback'),
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
        ];
    }

    /**
     * Writes a new post's row as the insert does: its guid becomes the
     * permalink as of now (pretty only when live). The default category
     * follows, and the body's own terms come after rest_insert_{type}, so
     * categories: [] leaves none.
     *
     * @param array<string, mixed> $columns
     */
    private function writeNewPost(array $columns): int
    {
        $id = $this->writer->insert($columns);
        $this->writer->update($id, ['guid' => $this->object->permalink($this->posts->find($id))]);
        return $id;
    }

    /** Updates a post or page. */
    #[Route(Method::Post, '/wp/v2/{base:posts|pages}/{id:[\d]+}', policy: new Policy(Access::Own, 'edit_post', param: 'id', subject: Subject::Post, signIn: 'rest_cannot_edit', signInMessage: 'Sorry, you are not allowed to edit this post.', refuse: 'rest_cannot_edit', message: 'Sorry, you are not allowed to edit this post.'))]
    #[Route(Method::Put, '/wp/v2/{base:posts|pages}/{id:[\d]+}', policy: new Policy(Access::Own, 'edit_post', param: 'id', subject: Subject::Post, signIn: 'rest_cannot_edit', signInMessage: 'Sorry, you are not allowed to edit this post.', refuse: 'rest_cannot_edit', message: 'Sorry, you are not allowed to edit this post.'))]
    #[Route(Method::Patch, '/wp/v2/{base:posts|pages}/{id:[\d]+}', policy: new Policy(Access::Own, 'edit_post', param: 'id', subject: Subject::Post, signIn: 'rest_cannot_edit', signInMessage: 'Sorry, you are not allowed to edit this post.', refuse: 'rest_cannot_edit', message: 'Sorry, you are not allowed to edit this post.'))]
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
        $columns = [...$this->floatingDate($body, $post), ...$this->fieldColumns($body, $post, $type), ...$this->statusColumns($body, $post, $type)];
        $columns['post_modified'] = $this->site->localNow();
        $columns['post_modified_gmt'] = gmdate('Y-m-d H:i:s');
        $columns = PostSave::filter($columns, $post, $body, $request, $type, $this->writer->slugs());
        $events = $this->events();
        $events->beforeSave($columns, $post);
        $this->writer->update($postId, $columns);
        if ($type === 'post') {
            $events->ensureCategory($this->writer, $postId);
        }
        // A publish/unpublish transition changes the terms' published counts;
        // with plugins loaded the reference's transition default recounts them.
        if (!$events->live() && array_key_exists('status', $body) && $body['status'] !== $post->status) {
            $this->writer->recountTaxonomiesOf($postId);
        }
        $events->saved($postId, $post);
        $events->restInserted($postId, $request, $post);
        $events->applyTerms($this->writer, $postId, $body);
        $this->writer->applyExtendedFields($postId, $body, $type);
        $events->restAfterInsert($postId, $request, $post);
        $this->rememberOld($post, $this->posts->find($postId));
        if (!$events->live()) {
            $this->writer->maybeSaveRevision($postId, $userId);
        }
        // The revision of the update is saved from wp_after_insert_post, as on the reference.
        $events->afterInsert($postId, $post);

        return Reply::item($this->object->edit($this->posts->find($postId), $userId), Fields::fromQuery($request->query));
    }

    /** Trashes or deletes a post or page. */
    #[Route(Method::Delete, '/wp/v2/{base:posts|pages}/{id:[\d]+}', policy: new Policy(Access::Own, 'delete_post', param: 'id', subject: Subject::Post, signIn: 'rest_cannot_delete', signInMessage: 'Sorry, you are not allowed to delete this post.', refuse: 'rest_cannot_delete', message: 'Sorry, you are not allowed to delete this post.'))]
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

        $events = $this->events();
        if (!$request->flag('force')) {
            if ($post->isTrashed()) {
                throw new RestError('rest_already_trashed', 'The post has already been deleted.', 410);
            }
            $this->trash($post, $userId);
            $trashed = $this->posts->find($postId);
            $data = $this->object->edit($trashed, $userId);
            $events->restDeleted($trashed, $data, $request);
            return Reply::item($data, $fields);
        }

        $previous = $this->object->edit($post, $userId);
        $events->live() ? \wp_delete_post($postId, true) : $this->writer->destroy($postId);
        $data = ['deleted' => true, 'previous' => $previous];
        $events->restDeleted($post, $data, $request);
        return Reply::item($data, $fields);
    }

    /**
     * Moves a post to the trash through the runtime's wp_trash_post, which
     * tells plugins and keeps what the way back needs; without plugins
     * loaded, the same rows by hand.
     */
    private function trash(PostRecord $post, int $userId): void
    {
        if ($this->events()->live()) {
            \wp_trash_post($post->id);
            return;
        }
        $this->writer->trash($post);
        $this->writer->setMeta($post->id, '_wp_trash_meta_status', $post->status);
        $this->writer->setMeta($post->id, '_wp_trash_meta_time', (string) time());
        $this->writer->setMeta($post->id, '_wp_desired_post_slug', $post->slug);
        $this->writer->maybeSaveRevision($post->id, $userId);
    }

    /**
     * After a save: a published post (not a page) that moved keeps its old
     * slug on record, and one that stayed published keeps its old date; one
     * brought back from the trash has its wanted slug back and stops waiting
     * for it.
     */
    private function rememberOld(PostRecord $before, ?PostRecord $after): void
    {
        if ($after === null) {
            return;
        }
        if ($before->isTrashed() && !$after->isTrashed() && $after->slug !== $before->slug) {
            $this->writer->deleteMeta($after->id, '_wp_desired_post_slug');
        }
        // With plugins loaded the reference's own post_updated hooks keep the old slug and date, and a site may unhook them.
        if ($this->events()->live() || $after->status !== PostStatus::Publish->value || $after->type === 'page') {
            return;
        }
        $this->writer->rememberOld($after->id, '_wp_old_slug', $before->slug, $after->slug);
        if ($before->status === PostStatus::Publish->value) {
            $this->writer->rememberOld($after->id, '_wp_old_date', substr($before->date, 0, 10), substr($after->date, 0, 10));
        }
    }

    /**
     * A draft that was never given a date (its GMT date is zero) has a
     * floating one: every save moves it to now, and the GMT date stays zero
     * until the post leaves draft or pending, as on the reference.
     *
     * @return array<string, string>
     */
    private function floatingDate(array $body, PostRecord $post): array
    {
        if (isset($body['date']) || !PostWriter::floating($post)) {
            return [];
        }
        $status = (string) ($body['status'] ?? $post->status);
        return ['post_date' => $this->site->localNow(), 'post_date_gmt' => in_array($status, PostWriter::FLOATING, true) ? PostWriter::ZERO_DATE : gmdate('Y-m-d H:i:s')];
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
            $columns['post_name'] = $this->writer->uniqueSlug((string) $body['slug'], $post->id, $type, (int) ($body['parent'] ?? $post->parentId));
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
        // Back from the trash into the open, a post takes the slug it had.
        $desired = $post->isTrashed() && $live && !array_key_exists('slug', $body) ? (string) ($this->posts->meta($post->id, '_wp_desired_post_slug') ?? '') : '';
        if ($desired !== '' && str_ends_with($post->slug, '__trashed')) {
            $columns += ['post_name' => $this->writer->uniqueSlug($desired, $post->id, $type, $post->parentId)];
        }
        if ($live && $post->slug === '' && !array_key_exists('slug', $body)) {
            $title = array_key_exists('title', $body) ? self::field($body['title']) : $post->title;
            if ($title !== '') {
                $columns['post_name'] = $this->writer->uniqueSlug($title, $post->id, $type, $post->parentId);
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
            throw RestError::invalidParam('status', 'status is not one of publish, future, draft, pending, and private.');
        }
        return $status;
    }

    /** Markup from a caller without unfiltered_html goes through the allowlist filter. */
    private function clean(string $markup): string
    {
        return $this->caller->can('unfiltered_html') ? $markup : Kses::post($markup);
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
