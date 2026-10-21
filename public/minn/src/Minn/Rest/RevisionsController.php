<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Http\Policy;
use Minn\Http\Subject;
use Minn\Http\Access;
use Minn\Http\Args;
use Minn\Http\RevisionArgs;
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
use Minn\Runtime\Runtime;
use Minn\Support\Kses;
use Minn\Auth\Nonce;
use Minn\Front\Permalinks;
use Minn\Runtime\PostEvents;
use Minn\Runtime\Autosaves;
use Minn\Runtime\Records;
use Minn\Objects\Errors;

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

    /** Every revision of the parent, its autosaves among them, filtered, ordered and paged as the request asks (RevisionCollection). */
    #[Route(Method::Get, '/wp/v2/{base:posts|pages|blocks|navigation}/{parent:[\d]+}/revisions', policy: new Policy(Access::Own, 'edit_post', param: 'parent', subject: Subject::PostParent, signIn: 'rest_cannot_read', signInMessage: 'Sorry, you are not allowed to view revisions of this post.', refuse: 'rest_cannot_read', message: 'Sorry, you are not allowed to view revisions of this post.'), args: [RevisionArgs::PARENT, Args::CONTEXT, RevisionArgs::COLLECTION])]
    public function revisions(Request $request, string $base, string $parent): Response
    {
        $this->requireParent((int) $parent, $base);
        $context = Context::of($request);
        [$rows, $total, $pages] = RevisionCollection::paged($request, RevisionCollection::filtered($request, PostRecord::fromRows($this->revisions->allOf((int) $parent))));
        return Reply::list(array_map(fn (PostRecord $r) => $this->object($r, $context), $rows), $total, $pages, Fields::fromQuery($request->query));
    }

    /** One revision of a post, page, or block. */
    #[Route(Method::Get, '/wp/v2/{base:posts|pages|blocks|navigation}/{parent:[\d]+}/revisions/{id:[\d]+}', policy: new Policy(Access::Own, 'edit_post', param: 'parent', subject: Subject::PostParent, signIn: 'rest_cannot_read', signInMessage: 'Sorry, you are not allowed to view revisions of this post.', refuse: 'rest_cannot_read', message: 'Sorry, you are not allowed to view revisions of this post.'), args: [RevisionArgs::PARENT, RevisionArgs::ID, Args::CONTEXT])]
    public function revision(Request $request, string $base, string $parent, string $id): Response
    {
        $this->requireParent((int) $parent, $base);
        $revision = RevisionCollection::ofParent($this->revisions->one((int) $id), (int) $parent);
        return self::selected($this->object($revision, Context::of($request)), Fields::fromQuery($request->query));
    }

    /**
     * One revision or autosave under the request's _fields: its links ride
     * along exactly when the parent field is asked for, as the reference
     * links one to its parent only then (and _links alone shows nothing).
     */
    private static function selected(array $object, ?Fields $fields): Response
    {
        if ($fields === null || $fields->deferred) {
            return Reply::item($object, $fields);
        }
        $shown = $fields->apply(array_diff_key($object, ['_links' => true]));
        return Reply::item(in_array('parent', $fields->paths, true) && isset($object['_links']) ? $shown + ['_links' => $object['_links']] : $shown, null);
    }

    /**
     * A revision deleted for good, answered as it was; never trashed. The
     * reference grants deleting a revision to nobody by default, so a
     * request that passes the parent's check is refused at the revision's.
     */
    #[Route(Method::Delete, '/wp/v2/{base:posts|pages|blocks|navigation}/{parent:[\d]+}/revisions/{id:[\d]+}', policy: new Policy(Access::Own, 'delete_post', param: 'parent', subject: Subject::PostParent, signIn: 'rest_cannot_delete', signInMessage: self::DELETE_REFUSAL, refuse: 'rest_cannot_delete', message: self::DELETE_REFUSAL), args: [RevisionArgs::PARENT, RevisionArgs::ID, RevisionArgs::FORCE])]
    public function deleteRevision(Request $request, string $base, string $parent, string $id): Response
    {
        $this->caller->require('rest_cannot_delete', self::DELETE_REFUSAL);
        $this->parentOf((int) $parent, $base);
        if (!$this->caller->can('delete_post', (int) $parent)) {
            throw new RestError('rest_cannot_delete', self::DELETE_REFUSAL, 403);
        }
        // Whoever may not list the parent's revisions may not delete one (probe sweep-1020b).
        if (!$this->caller->can('edit_post', (int) $parent)) {
            throw new RestError('rest_cannot_read', 'Sorry, you are not allowed to view revisions of this post.', 403);
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
        Runtime::hooks()->action('rest_delete_revision', [$deleted, $request]);
        if (!$deleted) {
            throw new RestError('rest_cannot_delete', 'The post cannot be deleted.', 500);
        }
        return Reply::item(['deleted' => true, 'previous' => $previous], Fields::fromQuery($request->query));
    }

    /** The autosaves of a post, every author's, in the asked context; the edit view names each one's preview. */
    #[Route(Method::Get, '/wp/v2/{base:posts|pages|blocks|navigation|menu-items}/{id:[\d]+}/autosaves', policy: new Policy(Access::Own, 'edit_post', param: 'id', subject: Subject::PostParent, signIn: 'rest_cannot_read', signInMessage: 'Sorry, you are not allowed to view autosaves of this post.', refuse: 'rest_cannot_read', message: 'Sorry, you are not allowed to view autosaves of this post.'), args: [RevisionArgs::AUTOSAVE_PARENT, Args::CONTEXT])]
    public function autosaves(Request $request, string $base, string $id): Response
    {
        $this->requireParent((int) $id, $base);
        $context = Context::of($request);
        $objects = array_map(
            fn (PostRecord $row): array => $context->isEdit() ? $this->withPreviewLink($this->object($row, $context), (int) $id) : $this->object($row, $context),
            PostRecord::fromRows($this->revisions->autosavesOf((int) $id)),
        );
        $fields = Fields::fromQuery($request->query);
        if ($fields !== null) {
            $objects = array_map(static fn (array $o) => $fields->apply($o), $objects);
        }
        return Reply::item($objects, null);
    }

    /** The parent's newest autosave, whoever saved it and whichever autosave id is asked for; the edit view names its preview. */
    #[Route(Method::Get, '/wp/v2/{base:posts|pages|blocks|navigation|menu-items}/{parent:[\d]+}/autosaves/{id:[\d]+}', policy: new Policy(Access::Own, 'edit_post', param: 'parent', subject: Subject::PostParent, signIn: 'rest_cannot_read', signInMessage: 'Sorry, you are not allowed to view revisions of this post.', refuse: 'rest_cannot_read', message: 'Sorry, you are not allowed to view revisions of this post.'), args: [RevisionArgs::AUTOSAVE_PARENT, RevisionArgs::AUTOSAVE_ID, Args::CONTEXT])]
    public function autosave(Request $request, string $base, string $parent, string $id): Response
    {
        $this->requireParent((int) $parent, $base);
        $newest = PostRecord::fromRows($this->revisions->autosavesOf((int) $parent))[0] ?? null;
        if ($newest === null) {
            throw new RestError('rest_post_no_autosave', 'There is no autosave revision for this post.', 404);
        }
        $context = Context::of($request);
        $object = $this->object($newest, $context);
        return self::selected($context->isEdit() ? $this->withPreviewLink($object, (int) $parent) : $object, Fields::fromQuery($request->query));
    }

    /** One autosave slot per author; the reply carries a preview link. */
    #[Route(Method::Post, '/wp/v2/{base:posts|pages|blocks|navigation|menu-items}/{id:[\d]+}/autosaves', policy: new Policy(Access::Own, 'edit_post', param: 'id', subject: Subject::Post, signIn: 'rest_cannot_edit', signInMessage: 'Sorry, you are not allowed to edit this post.', refuse: 'rest_cannot_edit', message: 'Sorry, you are not allowed to edit this post.'))]
    public function createAutosave(Request $request, string $base, string $id): Response
    {
        $userId = $this->requireParent((int) $id, $base);
        $body = $request->json();
        // A field the parent's type lacks is left out; a menu item keeps no field of an autosave at all (probe rest-autosaves).
        $allowed = self::TYPES[$base] === 'nav_menu_item' ? [] : self::FIELDS[self::TYPES[$base]] ?? ['title', 'content', 'excerpt'];
        $fields = [];
        foreach (array_intersect($allowed, array_keys($body)) as $key) {
            $fields['post_' . $key] = $this->clean(PostsWriteController::field($body[$key]));
        }
        $post = Records::post((int) $id);
        $saved = Autosaves::save($post, $userId, $fields);
        if ($saved instanceof Errors) {
            throw new RestError($saved->get_error_code(), $saved->get_error_message(), 500);
        }
        if ($saved === (int) $id) {
            // The author's own draft was saved in place: the reply is the post, in a revision's shape.
            return Reply::item($this->draftObject($this->posts->find($saved)), Fields::fromQuery($request->query));
        }
        return Reply::item($this->withPreviewLink($this->object($this->posts->find($saved), Context::Edit), (int) $id), Fields::fromQuery($request->query));
    }

    /**
     * A draft saved in place by its autosave, as the reference replies with
     * it: a revision's fields with the post's own parent (linked only when
     * there is one), its own guid, no dates while it floats, and its plain
     * preview link.
     */
    private function draftObject(PostRecord $post): array
    {
        // The post's own parent stands where a revision's would, and is linked the same way.
        $object = $this->object($post, Context::Edit);
        $links = $post->parentId > 0 ? $object['_links'] : null;
        unset($object['_links']);
        $object['guid'] = ['rendered' => $post->guid, 'raw' => $post->guid];
        if ($post->dateGmt === '' || str_starts_with($post->dateGmt, '0000')) {
            $object['date'] = null;
            $object['date_gmt'] = null;
        }
        $object['preview_link'] = $this->url->home('/?' . ($post->type === 'page' ? 'page_id' : 'p') . '=' . $post->id . '&preview=true');
        return $links === null ? $object : $object + ['_links' => $links];
    }

    private function clean(string $markup): string
    {
        return $this->caller->can('unfiltered_html') ? $markup : Kses::post($markup);
    }

    /** One revision row as wp/v2 serves it (autosaves and revisions alike), in the view, edit or embed shape. */
    public function object(array|PostRecord $r, Context $context = Context::View): array
    {
        $record = $r instanceof PostRecord ? $r : PostRecord::fromRow($r);
        $withPreview = $context->isEdit();
        $parentType = $this->posts->find($record->parentId)?->type ?? 'post';
        $guid = $this->url->home('/?p=' . $record->id);
        $excerpt = $record->excerpt === '' ? '' : Blocks::paragraphs($record->excerpt);
        $dual = static fn (string $raw, string $rendered) => $withPreview ? ['raw' => $raw, 'rendered' => $rendered] : ['rendered' => $rendered];
        // A pattern is edited, never viewed, so its revisions carry the title and content raw in either context.
        $blockDual = static fn (string $raw, string $rendered) => $withPreview || $parentType === 'wp_block' ? ['raw' => $raw, 'rendered' => $rendered] : ['rendered' => $rendered];

        $object = [
            'author' => $record->authorId,
            'date' => PostObject::date($record->date),
            'date_gmt' => PostObject::date($record->dateGmt),
            'id' => $record->id,
            'modified' => PostObject::date($record->modified),
            'modified_gmt' => PostObject::date($record->modifiedGmt),
            'parent' => $record->parentId,
            'slug' => $record->slug,
            // A menu item's revision shows its guid raw in either context (probe rest-autosaves).
            'guid' => $withPreview || $parentType === 'nav_menu_item' ? ['rendered' => $guid, 'raw' => $guid] : ['rendered' => $guid],
            'title' => $blockDual($record->title, Texturize::html($record->title)),
            'content' => $blockDual($record->content, Blocks::render($record->content)),
            'excerpt' => $dual($record->excerpt, $excerpt),
        ];
        $object = array_diff_key($object, array_flip(array_diff(['title', 'content', 'excerpt'], self::FIELDS[$parentType] ?? ['title', 'content', 'excerpt'])));
        $object['meta'] = $this->meta($record->id, $parentType);
        $object['_links'] = [
            'parent' => [['href' => $this->url->to('/wp/v2/' . PostObject::restBase($parentType) . '/' . $record->parentId)]],
        ];
        return $context === Context::Embed ? self::embedded($object, $record, $parentType) : $object;
    }

    /**
     * The embed view of a revision: who, when, which and whose, its title and
     * excerpt; a navigation menu's carries its title and content with their
     * raw halves instead.
     *
     * @param array<string, mixed> $object
     * @return array<string, mixed>
     */
    private static function embedded(array $object, PostRecord $record, string $parentType): array
    {
        $object = array_intersect_key($object, array_flip(['author', 'date', 'id', 'parent', 'slug', 'title', 'content', 'excerpt', '_links']));
        if ($parentType !== 'wp_navigation') {
            // Only a menu's embed shows anything raw; a pattern's title is the rendered one alone here.
            unset($object['content']);
            if (isset($object['title'])) {
                $object['title'] = ['rendered' => $object['title']['rendered']];
            }
            return $object;
        }
        $object['title'] = ['raw' => $record->title] + $object['title'];
        $object['content'] = ['raw' => $record->content] + $object['content'];
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
