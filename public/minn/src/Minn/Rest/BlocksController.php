<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Http\ItemArgs;
use Minn\Http\Policy;
use Minn\Http\Subject;
use Minn\Http\Access;
use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;

/**
 * wp/v2/blocks: synced patterns and reusable blocks, stored as wp_block
 * posts. The reading and writing is the posts machinery with the post
 * type's own capabilities and the posts list's parameters; what is this
 * type's alone is the reading rule: a pattern is read only by one who may
 * read it (read_post), published or not, so without that the list counts
 * the patterns and shows none, and a single one is refused.
 */
final readonly class BlocksController
{
    private const TYPE = 'wp_block';
    private const BASE = 'blocks';

    public function __construct(
        private PostsController $reads,
        private PostsWriteController $writes,
        private Caller $caller,
    ) {
    }

    /** The blocks, each shown to a caller who may read it. */
    #[Route(Method::Get, '/wp/v2/{base:blocks}', policy: new Policy(Access::Public), params: PostCollectionParams::class)]
    public function list(Request $request): Response
    {
        return $this->reads->serveList($request, self::TYPE, readable: $this->readable(...));
    }

    /**
     * One block. Editing context is the posts machinery's own gate
     * (rest_forbidden_context); a view is refused to anyone who may not read
     * the block, and an unknown one is not found rather than refused.
     */
    #[Route(Method::Get, '/wp/v2/blocks/{id:[\d]+}', policy: new Policy(Access::Public, subject: Subject::Block, param: 'id'), args: [ItemArgs::POST])]
    public function single(Request $request, string $id): Response
    {
        return $this->reads->serveSingle($request, self::TYPE, $id, $this->readable(...));
    }

    /** Whether the caller may read a pattern: read_post, whatever its status. */
    private function readable(int $id): bool
    {
        return $this->caller->can('read_post', $id);
    }

    /** Creates a block: a pattern's create_posts is publish_posts. */
    #[Route(Method::Post, '/wp/v2/blocks', policy: new Policy(Access::Cap, 'publish_posts', signIn: 'rest_cannot_create', signInMessage: 'Sorry, you are not allowed to create posts as this user.', refuse: 'rest_cannot_create', message: 'Sorry, you are not allowed to create posts as this user.'), refuse: ExistingPost::class)]
    public function create(Request $request): Response
    {
        return $this->writes->serveCreate($request, self::TYPE, self::BASE);
    }

    /** Updates a block. */
    #[Route(Method::Post, '/wp/v2/blocks/{id:[\d]+}', policy: new Policy(Access::Own, 'edit_post', param: 'id', subject: Subject::Block, signIn: 'rest_cannot_edit', signInMessage: 'Sorry, you are not allowed to edit this post.', refuse: 'rest_cannot_edit', message: 'Sorry, you are not allowed to edit this post.'), args: [PathArgs::POST_ID])]
    #[Route(Method::Put, '/wp/v2/blocks/{id:[\d]+}', policy: new Policy(Access::Own, 'edit_post', param: 'id', subject: Subject::Block, signIn: 'rest_cannot_edit', signInMessage: 'Sorry, you are not allowed to edit this post.', refuse: 'rest_cannot_edit', message: 'Sorry, you are not allowed to edit this post.'), args: [PathArgs::POST_ID])]
    #[Route(Method::Patch, '/wp/v2/blocks/{id:[\d]+}', policy: new Policy(Access::Own, 'edit_post', param: 'id', subject: Subject::Block, signIn: 'rest_cannot_edit', signInMessage: 'Sorry, you are not allowed to edit this post.', refuse: 'rest_cannot_edit', message: 'Sorry, you are not allowed to edit this post.'), args: [PathArgs::POST_ID])]
    public function update(Request $request, string $id): Response
    {
        return $this->writes->serveUpdate($request, self::TYPE, $id);
    }

    /** Trashes or deletes a block. */
    #[Route(Method::Delete, '/wp/v2/blocks/{id:[\d]+}', policy: new Policy(Access::Own, 'delete_post', param: 'id', subject: Subject::Block, signIn: 'rest_cannot_delete', signInMessage: 'Sorry, you are not allowed to delete this post.', refuse: 'rest_cannot_delete', message: 'Sorry, you are not allowed to delete this post.'), args: [PathArgs::POST_ID])]
    public function delete(Request $request, string $id): Response
    {
        return $this->writes->serveDelete($request, self::TYPE, $id);
    }
}
