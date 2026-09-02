<?php

declare(strict_types=1);

namespace Minn\Admin;

use Minn\Content\PostWriter;
use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;
use Minn\Rest\Caller;
use Minn\Rest\Reply;
use Minn\RestError;

/**
 * The editor's helpers in minn-admin/v1: the edit lock, and the template
 * and pattern lists (honestly empty: the engine carries no theme content).
 */
final readonly class EditorController
{
    public function __construct(
        private PostWriter $writer,
        private Caller $caller,
    ) {
    }

    /** Takes the edit lock on a post. */
    #[Route(Method::Post, '/minn-admin/v1/posts/{id:\d+}/lock')]
    public function lock(Request $request, string $id): Response
    {
        $userId = $this->caller->requireFloor();
        if (!$this->caller->can('edit_post', (int) $id)) {
            throw new RestError('rest_cannot_edit', 'Sorry, you are not allowed to edit this post.', 403);
        }
        $this->writer->setMeta((int) $id, '_edit_lock', time() . ':' . $userId);
        return Reply::answer($request, ['acquired' => true]);
    }

    /** Releases the edit lock on a post. */
    #[Route(Method::Post, '/minn-admin/v1/posts/{id:\d+}/unlock')]
    public function unlock(Request $request, string $id): Response
    {
        $this->caller->requireFloor();
        if (!$this->caller->can('edit_post', (int) $id)) {
            throw new RestError('rest_cannot_edit', 'Sorry, you are not allowed to edit this post.', 403);
        }
        $this->writer->deleteMeta((int) $id, '_edit_lock');
        return Reply::answer($request, ['unlocked' => true]);
    }

    /** No theme, no page templates: an honest empty set. */
    #[Route(Method::Get, '/minn-admin/v1/templates')]
    public function templates(Request $request): Response
    {
        $this->caller->requireFloor();
        return Reply::answer($request, ['templates' => []]);
    }

    /** Theme patterns are GPL theme content the engine does not carry. */
    #[Route(Method::Get, '/minn-admin/v1/patterns')]
    public function patterns(Request $request): Response
    {
        $this->caller->requireFloor();
        return Reply::answer($request, ['patterns' => []]);
    }
}
