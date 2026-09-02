<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;
use Minn\Http\RouteMiss;

/**
 * wp/v2/{rest_base} for extra post types declared by an active extension.
 * Registered last so core collections (users, comments, menus, ...) match
 * first; an unknown base is declined, so a plugin's own route under wp/v2
 * (answered by the runtime after the engine's routes) is not shadowed.
 */
final readonly class DeclaredPostsController
{
    public function __construct(
        private Types $types,
        private PostsController $reads,
        private PostsWriteController $writes,
    ) {
    }

    /** A declared type's list. */
    #[Route(Method::Get, '/wp/v2/{base:[a-z0-9_-]+}')]
    public function list(Request $request, string $base): Response
    {
        return $this->reads->serveList($request, $this->slug($base));
    }

    /** A declared type's single post. */
    #[Route(Method::Get, '/wp/v2/{base:[a-z0-9_-]+}/{id:\d+}')]
    public function single(Request $request, string $base, string $id): Response
    {
        return $this->reads->serveSingle($request, $this->slug($base), $id);
    }

    /** Creates a post of a declared type. */
    #[Route(Method::Post, '/wp/v2/{base:[a-z0-9_-]+}')]
    public function create(Request $request, string $base): Response
    {
        $slug = $this->slug($base);
        return $this->writes->serveCreate($request, $slug, $base);
    }

    /** Updates a post of a declared type. */
    #[Route(Method::Post, '/wp/v2/{base:[a-z0-9_-]+}/{id:\d+}')]
    #[Route(Method::Put, '/wp/v2/{base:[a-z0-9_-]+}/{id:\d+}')]
    #[Route(Method::Patch, '/wp/v2/{base:[a-z0-9_-]+}/{id:\d+}')]
    public function update(Request $request, string $base, string $id): Response
    {
        return $this->writes->serveUpdate($request, $this->slug($base), $id);
    }

    /** Trashes or deletes a post of a declared type. */
    #[Route(Method::Delete, '/wp/v2/{base:[a-z0-9_-]+}/{id:\d+}')]
    public function delete(Request $request, string $base, string $id): Response
    {
        return $this->writes->serveDelete($request, $this->slug($base), $id);
    }

    private function slug(string $base): string
    {
        $slug = $this->types->slugForRestBase($base);
        if ($slug === null || !$this->types->isDeclared($slug)) {
            throw new RouteMiss();
        }
        return $slug;
    }
}
