<?php

declare(strict_types=1);

namespace Minn\Admin;

use Minn\Content\PostWriter;
use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Access;
use Minn\Http\Policy;
use Minn\Http\Route;
use Minn\Rest\Caller;
use Minn\Rest\Reply;
use Minn\RestError;

/**
 * The editor's edit lock in minn-admin/v1. Its template and pattern lists
 * are Minn Admin's own routes, answered by the plugin from the theme and
 * the pattern registry.
 */
final readonly class EditorController
{
    public function __construct(
        private PostWriter $writer,
        private Caller $caller,
    ) {
    }

    /** Takes the edit lock on a post. */
    #[Route(Method::Post, '/minn-admin/v1/posts/{id:\d+}/lock', policy: new Policy(Access::Floor))]
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
    #[Route(Method::Post, '/minn-admin/v1/posts/{id:\d+}/unlock', policy: new Policy(Access::Floor))]
    public function unlock(Request $request, string $id): Response
    {
        if (!$this->caller->can('edit_post', (int) $id)) {
            throw new RestError('rest_cannot_edit', 'Sorry, you are not allowed to edit this post.', 403);
        }
        $this->writer->deleteMeta((int) $id, '_edit_lock');
        return Reply::answer($request, ['unlocked' => true]);
    }
}
