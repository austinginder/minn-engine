<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Http\Policy;
use Minn\Http\Subject;
use Minn\Http\Access;
use Minn\Content\Posts;
use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;
use Minn\RestError;

/**
 * wp/v2/blocks: synced patterns and reusable blocks, stored as wp_block
 * posts. The reading and writing is the posts machinery with the post
 * type's own capabilities; what is this type's alone is the reading gate:
 * without edit_posts the list is empty and a single block is refused,
 * published or not.
 */
final readonly class BlocksController
{
    private const TYPE = 'wp_block';
    private const BASE = 'blocks';

    public function __construct(
        private PostsController $reads,
        private PostsWriteController $writes,
        private Posts $posts,
        private Caller $caller,
    ) {
    }

    /** The blocks the caller may edit; an empty list for anyone else. */
    #[Route(Method::Get, '/wp/v2/blocks', policy: new Policy(Access::Public))]
    public function list(Request $request): Response
    {
        if (!$this->caller->can('edit_posts')) {
            return Reply::list([], 0, 0, Fields::fromQuery($request->query));
        }
        return $this->reads->serveList($request, self::TYPE);
    }

    /**
     * One block. Editing context is the posts machinery's own gate
     * (rest_forbidden_context); a view is refused to anyone who cannot edit
     * posts, and a trashed or unknown block is not found rather than refused.
     */
    #[Route(Method::Get, '/wp/v2/blocks/{id:[\d]+}', policy: new Policy(Access::Public, subject: Subject::Block, param: 'id'))]
    public function single(Request $request, string $id): Response
    {
        if (Context::of($request)->isEdit()) {
            // Existence (rest_post_invalid_id) and the edit gate (rest_forbidden_context) live in serveSingle.
            return $this->reads->serveSingle($request, self::TYPE, $id);
        }
        $block = $this->posts->find((int) $id);
        if ($block === null || $block->type !== self::TYPE) {
            throw new RestError('rest_post_invalid_id', 'Invalid post ID.', 404);
        }
        // A pattern is not public: reading one, whatever its status, needs the capability to.
        if (!$this->caller->can('read_post', $block->id)) {
            throw $this->caller->refuse('rest_forbidden', 'Sorry, you are not allowed to do that.');
        }
        return $this->reads->serveSingle($request, self::TYPE, $id);
    }

    /** Creates a block: a pattern's create_posts is publish_posts. */
    #[Route(Method::Post, '/wp/v2/blocks', policy: new Policy(Access::Cap, 'publish_posts', signIn: 'rest_cannot_create', signInMessage: 'Sorry, you are not allowed to create posts as this user.', refuse: 'rest_cannot_create', message: 'Sorry, you are not allowed to create posts as this user.'))]
    public function create(Request $request): Response
    {
        return $this->writes->serveCreate($request, self::TYPE, self::BASE);
    }

    /** Updates a block. */
    #[Route(Method::Post, '/wp/v2/blocks/{id:[\d]+}', policy: new Policy(Access::Own, 'edit_post', param: 'id', subject: Subject::Block, signIn: 'rest_cannot_edit', signInMessage: 'Sorry, you are not allowed to edit this post.', refuse: 'rest_cannot_edit', message: 'Sorry, you are not allowed to edit this post.'))]
    #[Route(Method::Put, '/wp/v2/blocks/{id:[\d]+}', policy: new Policy(Access::Own, 'edit_post', param: 'id', subject: Subject::Block, signIn: 'rest_cannot_edit', signInMessage: 'Sorry, you are not allowed to edit this post.', refuse: 'rest_cannot_edit', message: 'Sorry, you are not allowed to edit this post.'))]
    #[Route(Method::Patch, '/wp/v2/blocks/{id:[\d]+}', policy: new Policy(Access::Own, 'edit_post', param: 'id', subject: Subject::Block, signIn: 'rest_cannot_edit', signInMessage: 'Sorry, you are not allowed to edit this post.', refuse: 'rest_cannot_edit', message: 'Sorry, you are not allowed to edit this post.'))]
    public function update(Request $request, string $id): Response
    {
        return $this->writes->serveUpdate($request, self::TYPE, $id);
    }

    /** Trashes or deletes a block. */
    #[Route(Method::Delete, '/wp/v2/blocks/{id:[\d]+}', policy: new Policy(Access::Own, 'delete_post', param: 'id', subject: Subject::Block, signIn: 'rest_cannot_delete', signInMessage: 'Sorry, you are not allowed to delete this post.', refuse: 'rest_cannot_delete', message: 'Sorry, you are not allowed to delete this post.'))]
    public function delete(Request $request, string $id): Response
    {
        return $this->writes->serveDelete($request, self::TYPE, $id);
    }
}
