<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;

/**
 * wp/v2/navigation: the block theme's navigation menus, stored as
 * wp_navigation posts. The reading and writing is the posts machinery with
 * this type's own capabilities (every one of them edit_theme_options), so
 * only the routes live here.
 */
final readonly class NavigationController
{
    private const TYPE = 'wp_navigation';
    private const BASE = 'navigation';

    public function __construct(
        private PostsController $reads,
        private PostsWriteController $writes,
    ) {
    }

    /** The navigation posts. */
    #[Route(Method::Get, '/wp/v2/navigation')]
    public function list(Request $request): Response
    {
        return $this->reads->serveList($request, self::TYPE);
    }

    /** One navigation post. */
    #[Route(Method::Get, '/wp/v2/navigation/{id:\d+}')]
    public function single(Request $request, string $id): Response
    {
        return $this->reads->serveSingle($request, self::TYPE, $id);
    }

    /** Creates a navigation post. */
    #[Route(Method::Post, '/wp/v2/navigation')]
    public function create(Request $request): Response
    {
        return $this->writes->serveCreate($request, self::TYPE, self::BASE);
    }

    /** Updates a navigation post. */
    #[Route(Method::Post, '/wp/v2/navigation/{id:\d+}')]
    #[Route(Method::Put, '/wp/v2/navigation/{id:\d+}')]
    #[Route(Method::Patch, '/wp/v2/navigation/{id:\d+}')]
    public function update(Request $request, string $id): Response
    {
        return $this->writes->serveUpdate($request, self::TYPE, $id);
    }

    /** Trashes or deletes a navigation post. */
    #[Route(Method::Delete, '/wp/v2/navigation/{id:\d+}')]
    public function delete(Request $request, string $id): Response
    {
        return $this->writes->serveDelete($request, self::TYPE, $id);
    }
}
