<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Http\Policy;
use Minn\Http\Subject;
use Minn\Http\Access;
use Minn\Content\PostRecord;
use Minn\Content\Blocks;
use Minn\Content\Posts;
use Minn\Content\Revisions;
use Minn\Content\Texturize;
use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;
use Minn\RestError;
use Minn\Support\Kses;
use Minn\Auth\Nonce;
use Minn\Front\Permalinks;
use Minn\Runtime\PostEvents;

/**
 * wp/v2 revisions under posts, pages, patterns and navigation menus, and
 * autosaves under those and menu items (probes rest-revisions,
 * rest-autosaves). A revision carries the fields its parent's type
 * supports: a navigation menu's its title and content, a menu item's its
 * title alone.
 */
final readonly class RevisionsController
{
    /** The parents' REST bases and their post types. */
    private const TYPES = ['posts' => 'post', 'pages' => 'page', 'blocks' => 'wp_block', 'navigation' => 'wp_navigation', 'menu-items' => 'nav_menu_item'];

    /** The fields a revision carries by its parent's type; any other carries title, content and excerpt. */
    private const FIELDS = ['wp_navigation' => ['title', 'content'], 'nav_menu_item' => ['title']];

    private const DELETE_REFUSAL = 'Sorry, you are not allowed to delete revisions of this post.';

    public function __construct(
        private Posts $posts,
        private Revisions $revisions,
        private RestUrl $url,
        private Caller $caller,
        private ?Permalinks $permalinks = null,
    ) {
    }

    /** Real revisions, not autosaves. */
    #[Route(Method::Get, '/wp/v2/{base:posts|pages|blocks|navigation}/{parent:[\d]+}/revisions', policy: new Policy(Access::Own, 'edit_post', param: 'parent', subject: Subject::PostParent, signIn: 'rest_cannot_read', signInMessage: 'Sorry, you are not allowed to view revisions of this post.', refuse: 'rest_cannot_read', message: 'Sorry, you are not allowed to view revisions of this post.'))]
    public function revisions(Request $request, string $base, string $parent): Response
    {
        $this->requireParent((int) $parent, $base);
        $rows = $this->revisions->allOf((int) $parent);
        return Reply::list(array_map(fn (array $r) => $this->object($r), $rows), count($rows), 1, Fields::fromQuery($request->query));
    }

    /** One revision of a post, page, or block. */
    #[Route(Method::Get, '/wp/v2/{base:posts|pages|blocks|navigation}/{parent:[\d]+}/revisions/{id:[\d]+}', policy: new Policy(Access::Own, 'edit_post', param: 'parent', subject: Subject::PostParent, signIn: 'rest_cannot_read', signInMessage: 'Sorry, you are not allowed to view revisions of this post.', refuse: 'rest_cannot_read', message: 'Sorry, you are not allowed to view revisions of this post.'))]
    public function revision(Request $request, string $base, string $parent, string $id): Response
    {
        $this->requireParent((int) $parent, $base);
        foreach ($this->revisions->allOf((int) $parent) as $row) {
            if ((int) $row['ID'] === (int) $id) {
                $context = Context::of($request)->isEdit() ? Context::Edit : Context::View;
                return Reply::item($this->object($row, $context), Fields::fromQuery($request->query));
            }
        }
        throw new RestError('rest_post_invalid_id', 'Invalid revision ID.', 404);
    }

    /**
     * A revision deleted for good, answered as it was; never trashed. The
     * reference grants deleting a revision to nobody by default, so a
     * request that passes the parent's check is refused at the revision's.
     */
    #[Route(Method::Delete, '/wp/v2/{base:posts|pages|blocks|navigation}/{parent:[\d]+}/revisions/{id:[\d]+}', policy: new Policy(Access::Own, 'delete_post', param: 'parent', subject: Subject::PostParent, signIn: 'rest_cannot_delete', signInMessage: self::DELETE_REFUSAL, refuse: 'rest_cannot_delete', message: self::DELETE_REFUSAL))]
    public function deleteRevision(Request $request, string $base, string $parent, string $id): Response
    {
        $this->caller->require('rest_cannot_delete', self::DELETE_REFUSAL);
        $this->parentOf((int) $parent, $base);
        if (!$this->caller->can('delete_post', (int) $parent)) {
            throw new RestError('rest_cannot_delete', self::DELETE_REFUSAL, 403);
        }
        $revision = $this->posts->find((int) $id);
        if ($revision === null || $revision->type !== 'revision') {
            throw new RestError('rest_post_invalid_id', 'Invalid revision ID.', 404);
        }
        if (!$this->caller->can('delete_post', (int) $id)) {
            throw new RestError('rest_cannot_delete', 'Sorry, you are not allowed to delete this revision.', 403);
        }
        if (!$request->flagged('force')) {
            throw new RestError('rest_trash_not_supported', "Revisions do not support trashing. Set 'force=true' to delete.", 501);
        }
        $previous = array_diff_key($this->object($revision, Context::of($request)->isEdit() ? Context::Edit : Context::View), ['_links' => true]);
        $deleted = (new PostEvents())->deleteForGood((int) $id);
        \do_action('rest_delete_revision', $deleted, $request);
        if (!$deleted) {
            throw new RestError('rest_cannot_delete', 'The post cannot be deleted.', 500);
        }
        return Reply::item(['deleted' => true, 'previous' => $previous], Fields::fromQuery($request->query));
    }

    /** The autosaves of a post. */
    #[Route(Method::Get, '/wp/v2/{base:posts|pages|blocks|navigation|menu-items}/{id:[\d]+}/autosaves', policy: new Policy(Access::Own, 'edit_post', param: 'id', subject: Subject::PostParent, signIn: 'rest_cannot_read', signInMessage: 'Sorry, you are not allowed to view autosaves of this post.', refuse: 'rest_cannot_read', message: 'Sorry, you are not allowed to view autosaves of this post.'))]
    public function autosaves(Request $request, string $base, string $id): Response
    {
        $this->requireParent((int) $id, $base);
        $rows = $this->revisions->autosavesOf((int) $id);
        $objects = array_map(fn (array $r) => $this->object($r), $rows);
        $fields = Fields::fromQuery($request->query);
        if ($fields !== null) {
            $objects = array_map(static fn (array $o) => $fields->apply($o), $objects);
        }
        return Reply::item($objects, null);
    }

    /** The caller's autosave of the parent, whichever autosave id is asked for; the edit view names its preview. */
    #[Route(Method::Get, '/wp/v2/{base:posts|pages|blocks|navigation|menu-items}/{parent:[\d]+}/autosaves/{id:[\d]+}', policy: new Policy(Access::Own, 'edit_post', param: 'parent', subject: Subject::PostParent, signIn: 'rest_cannot_read', signInMessage: 'Sorry, you are not allowed to view revisions of this post.', refuse: 'rest_cannot_read', message: 'Sorry, you are not allowed to view revisions of this post.'))]
    public function autosave(Request $request, string $base, string $parent, string $id): Response
    {
        $userId = $this->requireParent((int) $parent, $base);
        $mine = array_values(array_filter(PostRecord::fromRows($this->revisions->autosavesOf((int) $parent)), static fn (PostRecord $row) => $row->authorId === $userId))[0] ?? null;
        if ($mine === null) {
            throw new RestError('rest_post_no_autosave', 'There is no autosave revision for this post.', 404);
        }
        $context = Context::of($request)->isEdit() ? Context::Edit : Context::View;
        $object = $this->object($mine, $context);
        return Reply::item($context->isEdit() ? $this->withPreviewLink($object, (int) $parent) : $object, Fields::fromQuery($request->query));
    }

    /** One autosave slot per author; the reply carries a preview link. */
    #[Route(Method::Post, '/wp/v2/{base:posts|pages|blocks|navigation|menu-items}/{id:[\d]+}/autosaves', policy: new Policy(Access::Own, 'edit_post', param: 'id', subject: Subject::Post, signIn: 'rest_cannot_edit', signInMessage: 'Sorry, you are not allowed to edit this post.', refuse: 'rest_cannot_edit', message: 'Sorry, you are not allowed to edit this post.'))]
    public function createAutosave(Request $request, string $base, string $id): Response
    {
        $userId = $this->requireParent((int) $id, $base);
        $body = $request->json();
        // A field the request leaves out keeps the user's earlier autosave's (probe rest-revisions).
        $earlier = array_values(array_filter(PostRecord::fromRows($this->revisions->autosavesOf((int) $id)), static fn (PostRecord $row) => $row->authorId === $userId))[0] ?? null;
        // A field the parent's type lacks is saved blank; a menu item keeps no field of an autosave at all (probe rest-autosaves).
        $fields = self::TYPES[$base] === 'nav_menu_item' ? [] : self::FIELDS[self::TYPES[$base]] ?? ['title', 'content', 'excerpt'];
        $field = fn (string $key, string $kept) => !in_array($key, $fields, true) ? '' : (array_key_exists($key, $body) ? $this->clean(PostsWriteController::field($body[$key])) : $kept);
        if ($earlier !== null) {
            // A repeat autosave is heard before it is written: the fields the request gave, then the slot's own (probe hook-inputs-2).
            $given = [];
            foreach (array_intersect(['title', 'content', 'excerpt'], $fields, array_keys($body)) as $key) {
                $given['post_' . $key] = $field($key, '');
            }
            \do_action('wp_creating_autosave', $given + ['post_parent' => (int) $id, 'post_status' => 'inherit', 'post_type' => 'revision', 'post_name' => $id . '-autosave-v1', 'post_date' => '', 'post_date_gmt' => '', 'ID' => $earlier->id, 'post_author' => $userId], true);
        }
        $revisionId = $this->revisions->saveAutosave((int) $id, $userId, $field('title', $earlier->title ?? ''), $field('content', $earlier->content ?? ''), $field('excerpt', $earlier->excerpt ?? ''));
        return Reply::item($this->withPreviewLink($this->object($this->posts->find($revisionId), Context::Edit), (int) $id), Fields::fromQuery($request->query));
    }

    private function clean(string $markup): string
    {
        return $this->caller->can('unfiltered_html') ? $markup : Kses::post($markup);
    }

    /** One revision row as wp/v2 serves it (autosaves and revisions alike). */
    public function object(array|PostRecord $r, Context $context = Context::View): array
    {
        $withPreview = $context->isEdit();
        $id = (int) $r['ID'];
        $parent = (int) $r['post_parent'];
        $parentType = $this->posts->find($parent)?->type ?? 'post';
        $guid = $this->url->home('/?p=' . $id);
        $excerpt = $r['post_excerpt'] === '' ? '' : Blocks::paragraphs((string) $r['post_excerpt']);
        $dual = static fn (string $raw, string $rendered) => $withPreview ? ['raw' => $raw, 'rendered' => $rendered] : ['rendered' => $rendered];
        // A pattern is edited, never viewed, so its revisions carry the title and content raw in either context.
        $blockDual = static fn (string $raw, string $rendered) => $withPreview || $parentType === 'wp_block' ? ['raw' => $raw, 'rendered' => $rendered] : ['rendered' => $rendered];

        $object = [
            'author' => (int) $r['post_author'],
            'date' => PostObject::date((string) $r['post_date']),
            'date_gmt' => PostObject::date((string) $r['post_date_gmt']),
            'id' => $id,
            'modified' => PostObject::date((string) $r['post_modified']),
            'modified_gmt' => PostObject::date((string) $r['post_modified_gmt']),
            'parent' => $parent,
            'slug' => $r['post_name'],
            // A menu item's revision shows its guid raw in either context (probe rest-autosaves).
            'guid' => $withPreview || $parentType === 'nav_menu_item' ? ['rendered' => $guid, 'raw' => $guid] : ['rendered' => $guid],
            'title' => $blockDual((string) $r['post_title'], Texturize::html((string) $r['post_title'])),
            'content' => $blockDual((string) $r['post_content'], Blocks::render((string) $r['post_content'])),
            'excerpt' => $dual((string) $r['post_excerpt'], $excerpt),
        ];
        $object = array_diff_key($object, array_flip(array_diff(['title', 'content', 'excerpt'], self::FIELDS[$parentType] ?? ['title', 'content', 'excerpt'])));
        $object['meta'] = $this->meta($id, (string) $parentType);
        $object['_links'] = [
            'parent' => [['href' => $this->url->to('/wp/v2/' . PostObject::restBase((string) $parentType) . '/' . $parent)]],
        ];
        return $object;
    }

    /** The autosave POST reply names where to preview the draft, just before its links; a revision never carries it. */
    private function withPreviewLink(array $object, int $parent): array
    {
        $links = $object['_links'] ?? null;
        unset($object['_links']);
        $object['preview_link'] = $this->previewLink($parent);
        if ($links !== null) {
            $object['_links'] = $links;
        }
        return $object;
    }

    /**
     * Where to preview the parent with its autosave: its permalink once
     * published, its plain address before; none for a type that is never
     * viewed (a navigation menu, a menu item).
     */
    private function previewLink(int $parent): string
    {
        $post = $this->posts->find($parent);
        if ($post === null || isset(self::FIELDS[$post->type])) {
            return '';
        }
        $session = $this->caller->session();
        $nonce = $session === null ? substr(bin2hex(random_bytes(8)), 0, 10) : Nonce::create($session->id(), $session->token, 'post_preview_' . $parent);
        $query = 'preview_id=' . $parent . '&preview_nonce=' . $nonce . '&preview=true';
        if ($post->status === 'publish' && $this->permalinks !== null) {
            $link = $this->permalinks->forPost($post);
            return $link . (str_contains($link, '?') ? '&' : '?') . $query;
        }
        return $this->url->home('/?p=' . $parent . '&' . $query);
    }

    /** A revision's meta: the footnotes, and for a pattern the sync status core revisions too; none for a navigation menu or a menu item. */
    private function meta(int $id, string $parentType): array
    {
        // Navigation menus and menu items register no meta: theirs is an empty list.
        if (isset(self::FIELDS[$parentType])) {
            return [];
        }
        $meta = [];
        if ($parentType === 'wp_block') {
            // The revision has none of its own; the reference reports the missing meta as null.
            $meta['wp_pattern_sync_status'] = $this->posts->meta($id, 'wp_pattern_sync_status');
        }
        $meta['footnotes'] = $this->posts->meta($id, 'footnotes') ?? '';
        return $meta;
    }

    /** The revision surfaces are gated on edit_post of the parent. */
    private function requireParent(int $parentId, string $base): int
    {
        $refusal = 'Sorry, you are not allowed to view revisions of this post.';
        $userId = $this->caller->require('rest_cannot_read', $refusal)->id();
        $this->parentOf($parentId, $base);
        if (!$this->caller->can('edit_post', $parentId)) {
            throw new RestError('rest_cannot_read', $refusal, 403);
        }
        return $userId;
    }

    /** The parent, of the type the route's base names, or rest_post_invalid_parent. */
    private function parentOf(int $parentId, string $base): PostRecord
    {
        $post = $this->posts->find($parentId);
        if ($post === null || $post->type !== (self::TYPES[$base] ?? 'post')) {
            throw new RestError('rest_post_invalid_parent', 'Invalid post parent ID.', 404);
        }
        return $post;
    }
}
