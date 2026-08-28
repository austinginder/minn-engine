<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;
use Minn\RestError;

/**
 * Routes whose handlers still live in the procedural files. Each method
 * hands off to a legacy function that writes and exits on its own; this
 * class disappears as those files migrate.
 */
final readonly class LegacyRoutes
{
    #[Route(Method::Any, '/minn-admin/v1/{rest*}')]
    public function minnAdmin(Request $request): Response
    {
        minn_v1_dispatch($request->path, $request->method->value);
        throw RestError::noRoute();
    }

    #[Route(Method::Post, '/wp/v2/comments')]
    public function createComment(Request $request): Response
    {
        minn_rest_comments_create();
        throw RestError::noRoute();
    }

    #[Route(Method::Get, '/wp/v2/comments')]
    public function listComments(Request $request): Response
    {
        minn_rest_comments_list();
        throw RestError::noRoute();
    }

    #[Route(Method::Get, '/wp/v2/settings')]
    #[Route(Method::Post, '/wp/v2/settings')]
    #[Route(Method::Put, '/wp/v2/settings')]
    #[Route(Method::Patch, '/wp/v2/settings')]
    public function settings(Request $request): Response
    {
        minn_rest_settings($request->method->value);
        throw RestError::noRoute();
    }

    #[Route(Method::Post, '/wp/v2/{base:posts|pages}/{id:\d+}/autosaves')]
    public function createAutosave(Request $request, string $base, string $id): Response
    {
        minn_rest_autosaves_create(self::type($base), (int) $id);
        throw RestError::noRoute();
    }

    #[Route(Method::Get, '/wp/v2/{base:posts|pages}/{id:\d+}/autosaves')]
    public function listAutosaves(Request $request, string $base, string $id): Response
    {
        minn_rest_autosaves_list(self::type($base), (int) $id);
        throw RestError::noRoute();
    }

    #[Route(Method::Get, '/wp/v2/{base:posts|pages}/{id:\d+}/revisions')]
    public function listRevisions(Request $request, string $base, string $id): Response
    {
        minn_rest_revisions_list(self::type($base), (int) $id);
        throw RestError::noRoute();
    }

    #[Route(Method::Get, '/wp/v2/blocks')]
    public function listBlocks(Request $request): Response
    {
        minn_rest_blocks_list();
        throw RestError::noRoute();
    }

    #[Route(Method::Post, '/wp/v2/media')]
    public function createMedia(Request $request): Response
    {
        minn_rest_media_create();
        throw RestError::noRoute();
    }

    #[Route(Method::Get, '/wp/v2/media')]
    public function listMedia(Request $request): Response
    {
        minn_rest_media_list();
        throw RestError::noRoute();
    }

    #[Route(Method::Post, '/wp/v2/media/{id:\d+}')]
    #[Route(Method::Put, '/wp/v2/media/{id:\d+}')]
    #[Route(Method::Patch, '/wp/v2/media/{id:\d+}')]
    public function updateMedia(Request $request, string $id): Response
    {
        minn_rest_media_update((int) $id);
        throw RestError::noRoute();
    }

    #[Route(Method::Delete, '/wp/v2/media/{id:\d+}')]
    public function deleteMedia(Request $request, string $id): Response
    {
        minn_rest_media_delete((int) $id, self::force($request));
        throw RestError::noRoute();
    }

    #[Route(Method::Get, '/wp/v2/media/{id:\d+}')]
    public function singleMedia(Request $request, string $id): Response
    {
        minn_rest_media_single((int) $id);
        throw RestError::noRoute();
    }

    #[Route(Method::Post, '/wp/v2/comments/{id:\d+}')]
    #[Route(Method::Put, '/wp/v2/comments/{id:\d+}')]
    #[Route(Method::Patch, '/wp/v2/comments/{id:\d+}')]
    public function updateComment(Request $request, string $id): Response
    {
        minn_rest_comments_update((int) $id);
        throw RestError::noRoute();
    }

    #[Route(Method::Delete, '/wp/v2/comments/{id:\d+}')]
    public function deleteComment(Request $request, string $id): Response
    {
        minn_rest_comments_delete((int) $id, self::force($request));
        throw RestError::noRoute();
    }

    #[Route(Method::Get, '/wp/v2/comments/{id:\d+}')]
    public function singleComment(Request $request, string $id): Response
    {
        minn_rest_comments_single((int) $id);
        throw RestError::noRoute();
    }

    #[Route(Method::Post, '/wp/v2/{base:posts|pages}')]
    public function createPost(Request $request, string $base): Response
    {
        minn_rest_create_post(self::type($base));
        throw RestError::noRoute();
    }

    #[Route(Method::Post, '/wp/v2/{base:posts|pages}/{id:\d+}')]
    #[Route(Method::Put, '/wp/v2/{base:posts|pages}/{id:\d+}')]
    #[Route(Method::Patch, '/wp/v2/{base:posts|pages}/{id:\d+}')]
    public function updatePost(Request $request, string $base, string $id): Response
    {
        minn_rest_update_post(self::type($base), (int) $id);
        throw RestError::noRoute();
    }

    #[Route(Method::Delete, '/wp/v2/{base:posts|pages}/{id:\d+}')]
    public function deletePost(Request $request, string $base, string $id): Response
    {
        minn_rest_delete_post(self::type($base), (int) $id);
        throw RestError::noRoute();
    }

    #[Route(Method::Post, '/wp/v2/{base:categories|tags}')]
    public function createTerm(Request $request, string $base): Response
    {
        minn_rest_terms_create($base);
        throw RestError::noRoute();
    }

    #[Route(Method::Post, '/wp/v2/{base:categories|tags}/{id:\d+}')]
    #[Route(Method::Put, '/wp/v2/{base:categories|tags}/{id:\d+}')]
    #[Route(Method::Patch, '/wp/v2/{base:categories|tags}/{id:\d+}')]
    public function updateTerm(Request $request, string $base, string $id): Response
    {
        minn_rest_terms_update($base, (int) $id);
        throw RestError::noRoute();
    }

    #[Route(Method::Delete, '/wp/v2/{base:categories|tags}/{id:\d+}')]
    public function deleteTerm(Request $request, string $base, string $id): Response
    {
        minn_rest_terms_delete($base, (int) $id, self::force($request));
        throw RestError::noRoute();
    }

    #[Route(Method::Post, '/wp/v2/users')]
    public function createUser(Request $request): Response
    {
        minn_rest_users_create();
        throw RestError::noRoute();
    }

    #[Route(Method::Post, '/wp/v2/users/{id:\d+}')]
    #[Route(Method::Put, '/wp/v2/users/{id:\d+}')]
    #[Route(Method::Patch, '/wp/v2/users/{id:\d+}')]
    public function updateUser(Request $request, string $id): Response
    {
        minn_rest_users_update((int) $id);
        throw RestError::noRoute();
    }

    #[Route(Method::Delete, '/wp/v2/users/{id:\d+}')]
    public function deleteUser(Request $request, string $id): Response
    {
        minn_rest_users_delete((int) $id);
        throw RestError::noRoute();
    }

    private static function type(string $base): string
    {
        return $base === 'pages' ? 'page' : 'post';
    }

    private static function force(Request $request): bool
    {
        return filter_var($request->query('force', ''), FILTER_VALIDATE_BOOLEAN);
    }
}
