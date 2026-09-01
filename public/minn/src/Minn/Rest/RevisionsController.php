<?php

declare(strict_types=1);

namespace Minn\Rest;

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

/** wp/v2 revisions and autosaves under posts and pages, plus wp/v2/blocks. */
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
    #[Route(Method::Get, '/wp/v2/{base:posts|pages}/{id:\d+}/revisions')]
    public function revisions(Request $request, string $base, string $id): Response
    {
        $this->requireParent((int) $id, $base);
        $rows = $this->revisions->of((int) $id, autosaves: false);
        return Reply::list(array_map(fn (array $r) => $this->object($r), $rows), count($rows), 1, Fields::fromQuery($request->query));
    }

    #[Route(Method::Get, '/wp/v2/{base:posts|pages}/{id:\d+}/autosaves')]
    public function autosaves(Request $request, string $base, string $id): Response
    {
        $this->requireParent((int) $id, $base);
        $rows = $this->revisions->of((int) $id, autosaves: true);
        $objects = array_map(fn (array $r) => $this->object($r), $rows);
        $fields = Fields::fromQuery($request->query);
        if ($fields !== null) {
            $objects = array_map(static fn (array $o) => $fields->apply($o), $objects);
        }
        return Reply::item($objects, null);
    }

    /** One autosave slot per author; the reply carries a preview link. */
    #[Route(Method::Post, '/wp/v2/{base:posts|pages}/{id:\d+}/autosaves')]
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
        return Reply::item($this->object($this->posts->find($revisionId), withPreview: true), Fields::fromQuery($request->query));
    }

    private function clean(string $markup): string
    {
        return $this->caller->can('unfiltered_html') ? $markup : Kses::filter($markup, Kses::POST);
    }

    /** Reusable blocks and synced patterns (wp_block rows). */
    #[Route(Method::Get, '/wp/v2/blocks')]
    public function blocks(Request $request): Response
    {
        if (Context::of($request)->isEdit() && !$this->caller->can('edit_posts')) {
            throw $this->caller->refuse('rest_forbidden_context', 'Sorry, you are not allowed to edit posts in this post type.');
        }
        // Reusable blocks are not public: without edit_posts the list is empty, as on the reference.
        $rows = $this->caller->can('edit_posts') ? $this->posts->blocks((string) $request->query('status', 'publish')) : [];
        $objects = [];
        foreach ($rows as $row) {
            $rowId = (int) $row['ID'];
            $objects[] = [
                'id' => $rowId,
                'title' => ['raw' => $row['post_title']],
                'meta' => ['footnotes' => $this->posts->meta($rowId, 'footnotes') ?? ''],
                'wp_pattern_sync_status' => (string) ($this->posts->meta($rowId, 'wp_pattern_sync_status') ?? ''),
            ];
        }
        return Reply::list($objects, count($rows), $rows === [] ? 0 : 1, Fields::fromQuery($request->query));
    }

    /** One revision row as wp/v2 serves it (autosaves and revisions alike). */
    public function object(array|PostRecord $r, bool $withPreview = false): array
    {
        $id = (int) $r['ID'];
        $parent = (int) $r['post_parent'];
        $guid = $this->url->home('/?p=' . $id);
        $excerpt = $r['post_excerpt'] === '' ? '' : Blocks::paragraphs((string) $r['post_excerpt']);
        $dual = static fn (string $raw, string $rendered) => $withPreview ? ['raw' => $raw, 'rendered' => $rendered] : ['rendered' => $rendered];

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
            'title' => $dual((string) $r['post_title'], Texturize::html((string) $r['post_title'])),
            'content' => $dual((string) $r['post_content'], Blocks::render((string) $r['post_content'])),
            'excerpt' => $dual((string) $r['post_excerpt'], $excerpt),
            'meta' => ['footnotes' => $this->posts->meta($id, 'footnotes') ?? ''],
        ];
        if ($withPreview) {
            $session = $this->caller->session();
            $nonce = $session === null ? substr(bin2hex(random_bytes(8)), 0, 10) : Nonce::create($session->id(), $session->token, 'post_preview_' . $parent);
            $object['preview_link'] = $this->url->home(
                '/?p=' . $parent . '&preview_id=' . $parent . '&preview_nonce=' . $nonce . '&preview=true',
            );
        }
        $parentType = $this->posts->find($parent)['post_type'] ?? 'post';
        $object['_links'] = [
            'parent' => [['href' => $this->url->to('/wp/v2/' . PostObject::restBase((string) $parentType) . '/' . $parent)]],
        ];
        return $object;
    }

    /** The revision surfaces are gated on edit_post of the parent. */
    private function requireParent(int $parentId, string $base): int
    {
        $refusal = 'Sorry, you are not allowed to view revisions of this post.';
        $userId = $this->caller->require('rest_cannot_read', $refusal)->id();
        $post = $this->posts->find($parentId);
        if ($post === null || $post['post_type'] !== ($base === 'pages' ? 'page' : 'post')) {
            throw new RestError('rest_post_invalid_parent', 'Invalid post parent ID.', 404);
        }
        if (!$this->caller->can('edit_post', $parentId)) {
            throw new RestError('rest_cannot_read', $refusal, 403);
        }
        return $userId;
    }
}
