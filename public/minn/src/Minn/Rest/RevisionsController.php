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

/** wp/v2 revisions and autosaves under posts, pages, and blocks. */
final readonly class RevisionsController
{
    public function __construct(
        private Posts $posts,
        private Revisions $revisions,
        private RestUrl $url,
        private Caller $caller,
    ) {
    }

    /** Real revisions, not autosaves. */
    #[Route(Method::Get, '/wp/v2/{base:posts|pages|blocks}/{id:\d+}/revisions', policy: new Policy(Access::Own, 'edit_post', param: 'id', subject: Subject::PostParent, signIn: 'rest_cannot_read', signInMessage: 'Sorry, you are not allowed to view revisions of this post.', refuse: 'rest_cannot_read', message: 'Sorry, you are not allowed to view revisions of this post.'))]
    public function revisions(Request $request, string $base, string $id): Response
    {
        $this->requireParent((int) $id, $base);
        $rows = $this->revisions->revisionsOf((int) $id);
        return Reply::list(array_map(fn (array $r) => $this->object($r), $rows), count($rows), 1, Fields::fromQuery($request->query));
    }

    /** One revision of a post, page, or block. */
    #[Route(Method::Get, '/wp/v2/{base:posts|pages|blocks}/{id:\d+}/revisions/{revisionId:\d+}', policy: new Policy(Access::Own, 'edit_post', param: 'id', subject: Subject::PostParent, signIn: 'rest_cannot_read', signInMessage: 'Sorry, you are not allowed to view revisions of this post.', refuse: 'rest_cannot_read', message: 'Sorry, you are not allowed to view revisions of this post.'))]
    public function revision(Request $request, string $base, string $id, string $revisionId): Response
    {
        $this->requireParent((int) $id, $base);
        foreach ($this->revisions->revisionsOf((int) $id) as $row) {
            if ((int) $row['ID'] === (int) $revisionId) {
                $context = Context::of($request)->isEdit() ? Context::Edit : Context::View;
                return Reply::item($this->object($row, $context), Fields::fromQuery($request->query));
            }
        }
        throw new RestError('rest_post_invalid_id', 'Invalid revision ID.', 404);
    }

    /** The autosaves of a post. */
    #[Route(Method::Get, '/wp/v2/{base:posts|pages|blocks}/{id:\d+}/autosaves', policy: new Policy(Access::Own, 'edit_post', param: 'id', subject: Subject::PostParent, signIn: 'rest_cannot_read', signInMessage: 'Sorry, you are not allowed to view autosaves of this post.', refuse: 'rest_cannot_read', message: 'Sorry, you are not allowed to view autosaves of this post.'))]
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

    /** One autosave slot per author; the reply carries a preview link. */
    #[Route(Method::Post, '/wp/v2/{base:posts|pages|blocks}/{id:\d+}/autosaves', policy: new Policy(Access::Own, 'edit_post', param: 'id', subject: Subject::Post, signIn: 'rest_cannot_edit', signInMessage: 'Sorry, you are not allowed to edit this post.', refuse: 'rest_cannot_edit', message: 'Sorry, you are not allowed to edit this post.'))]
    public function createAutosave(Request $request, string $base, string $id): Response
    {
        $userId = $this->requireParent((int) $id, $base);
        $body = $request->json();
        $revisionId = $this->revisions->saveAutosave(
            (int) $id,
            $userId,
            $this->clean(PostsWriteController::field($body['title'] ?? '')),
            $this->clean(PostsWriteController::field($body['content'] ?? '')),
            $this->clean(PostsWriteController::field($body['excerpt'] ?? '')),
        );
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
            'guid' => $withPreview ? ['rendered' => $guid, 'raw' => $guid] : ['rendered' => $guid],
            'title' => $blockDual((string) $r['post_title'], Texturize::html((string) $r['post_title'])),
            'content' => $blockDual((string) $r['post_content'], Blocks::render((string) $r['post_content'])),
            'excerpt' => $dual((string) $r['post_excerpt'], $excerpt),
            'meta' => $this->meta($id, (string) $parentType),
        ];
        $object['_links'] = [
            'parent' => [['href' => $this->url->to('/wp/v2/' . PostObject::restBase((string) $parentType) . '/' . $parent)]],
        ];
        return $object;
    }

    /** The autosave POST reply names where to preview the draft, just before its links; a revision never carries it. */
    private function withPreviewLink(array $object, int $parent): array
    {
        $session = $this->caller->session();
        $nonce = $session === null ? substr(bin2hex(random_bytes(8)), 0, 10) : Nonce::create($session->id(), $session->token, 'post_preview_' . $parent);
        $links = $object['_links'];
        unset($object['_links']);
        $object['preview_link'] = $this->url->home('/?p=' . $parent . '&preview_id=' . $parent . '&preview_nonce=' . $nonce . '&preview=true');
        $object['_links'] = $links;
        return $object;
    }

    /** A revision's meta: the footnotes, and for a pattern the sync status core revisions too. */
    private function meta(int $id, string $parentType): array
    {
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
        $post = $this->posts->find($parentId);
        $type = match ($base) { 'pages' => 'page', 'blocks' => 'wp_block', default => 'post' };
        if ($post === null || $post->type !== $type) {
            throw new RestError('rest_post_invalid_parent', 'Invalid post parent ID.', 404);
        }
        if (!$this->caller->can('edit_post', $parentId)) {
            throw new RestError('rest_cannot_read', $refusal, 403);
        }
        return $userId;
    }
}
