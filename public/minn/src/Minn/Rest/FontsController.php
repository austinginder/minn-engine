<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Content\PostRecord;
use Minn\Content\Posts;
use Minn\Http\Access;
use Minn\Http\Method;
use Minn\Http\Policy;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;
use Minn\Objects\Post;
use Minn\Objects\Query;
use Minn\RestError;
use Minn\Runtime\Handoff;
use Minn\Runtime\PostEvents;
use Minn\Runtime\Runtime;

/**
 * wp/v2/font-families and their font-faces, read and deleted (probe: the
 * font library routes on the reference). Only someone who may edit theme
 * options reads or deletes either, refused as rest_cannot_read or
 * rest_cannot_delete; a family or face that does not
 * exist is refused before the caller is judged, a face's family only
 * after. A list is the posts list narrowed to its own parameters: the
 * statuses the caller may read (published and private), newest id first,
 * the ids, offset, slugs (families only) and pages as the posts list
 * reads them, through rest_{type}_query; anything else the request
 * carries is ignored, a search included.
 */
final readonly class FontsController
{
    private const FAMILIES = 'Sorry, you are not allowed to access font families.';
    private const FAMILY = 'Sorry, you are not allowed to access this font family.';
    private const FACES = 'Sorry, you are not allowed to access font faces.';
    private const FACE = 'Sorry, you are not allowed to access this font face.';
    private const DELETE = 'Sorry, you are not allowed to delete this post.';

    public function __construct(private Posts $posts, private FontObject $object)
    {
    }

    /** The font families. */
    #[Route(Method::Get, '/wp/v2/font-families', policy: new Policy(Access::Cap, 'edit_theme_options', signIn: 'rest_cannot_read', signInMessage: self::FAMILIES, refuse: 'rest_cannot_read', message: self::FAMILIES), args: [FontArgs::FAMILIES])]
    public function families(Request $request): Response
    {
        return $this->list($request, 'wp_font_family', FontArgs::FAMILIES, [], $this->object->family(...));
    }

    /** One font family. */
    #[Route(Method::Get, '/wp/v2/font-families/{id:[\d]+}', policy: new Policy(Access::Cap, 'edit_theme_options', signIn: 'rest_cannot_read', signInMessage: self::FAMILY, refuse: 'rest_cannot_read', message: self::FAMILY), args: [FontArgs::FAMILY], refuse: FontFamilyRecord::class)]
    public function family(Request $request, string $id): Response
    {
        return Reply::answer($request, $this->object->family($this->found((int) $id, 'wp_font_family')));
    }

    /** A font family's faces. */
    #[Route(Method::Get, '/wp/v2/font-families/{font_family_id:[\d]+}/font-faces', policy: new Policy(Access::Cap, 'edit_theme_options', signIn: 'rest_cannot_read', signInMessage: self::FACES, refuse: 'rest_cannot_read', message: self::FACES), args: [FontArgs::FACES])]
    public function faces(Request $request, string $fontFamilyId): Response
    {
        $family = $this->parent((int) $fontFamilyId);
        return $this->list($request, 'wp_font_face', FontArgs::FACES, ['post_parent' => $family->id], $this->object->face(...));
    }

    /** One font face of a family. */
    #[Route(Method::Get, '/wp/v2/font-families/{font_family_id:[\d]+}/font-faces/{id:[\d]+}', policy: new Policy(Access::Cap, 'edit_theme_options', signIn: 'rest_cannot_read', signInMessage: self::FACE, refuse: 'rest_cannot_read', message: self::FACE), args: [FontArgs::FACE], refuse: FontFaceRecord::class)]
    public function face(Request $request, string $fontFamilyId, string $id): Response
    {
        $face = $this->found((int) $id, 'wp_font_face');
        if ($face->parentId !== $this->parent((int) $fontFamilyId)->id) {
            throw self::invalidParent();
        }
        return Reply::answer($request, $this->object->face($face));
    }

    /** A font family deleted for good, its faces with it; there is no trash for either. */
    #[Route(Method::Delete, '/wp/v2/font-families/{id:[\d]+}', policy: new Policy(Access::Cap, 'edit_theme_options', signIn: 'rest_cannot_delete', signInMessage: self::DELETE, refuse: 'rest_cannot_delete', message: self::DELETE), args: [FontArgs::FAMILY_DELETE], refuse: FontFamilyRecord::class)]
    public function deleteFamily(Request $request, string $id): Response
    {
        $family = $this->found((int) $id, 'wp_font_family');
        return $this->delete($request, $family, $this->object->family(...));
    }

    /** A font face deleted for good, its file with it. */
    #[Route(Method::Delete, '/wp/v2/font-families/{font_family_id:[\d]+}/font-faces/{id:[\d]+}', policy: new Policy(Access::Cap, 'edit_theme_options', signIn: 'rest_cannot_delete', signInMessage: self::DELETE, refuse: 'rest_cannot_delete', message: self::DELETE), args: [FontArgs::FACE_DELETE], refuse: FontFaceRecord::class)]
    public function deleteFace(Request $request, string $fontFamilyId, string $id): Response
    {
        $face = $this->found((int) $id, 'wp_font_face');
        if ($face->parentId !== $this->parent((int) $fontFamilyId)->id) {
            throw self::invalidParent();
        }
        return $this->delete($request, $face, $this->object->face(...));
    }

    /**
     * A delete as the font routes answer it: refused without force (the
     * reference names font faces for a family too), else the post deleted
     * through the reference's own deleting and answered as it was, in the
     * edit context and without its links, then rest_delete_{type}.
     *
     * @param \Closure(PostRecord): array<string, mixed> $shape
     */
    private function delete(Request $request, PostRecord $post, \Closure $shape): Response
    {
        if (!$request->flagged('force')) {
            throw new RestError('rest_trash_not_supported', 'Font faces do not support trashing. Set "force=true" to delete.', 501);
        }
        $data = ['deleted' => true, 'previous' => array_diff_key($shape($post), ['_links' => true])];
        $events = new PostEvents();
        $events->deleteForGood($post->id);
        $events->restDeleted($post, $data, $request);
        return Reply::answer($request, $data);
    }

    /**
     * A list of one type, as the posts list reads its parameters.
     *
     * @param array<string, array<string, mixed>> $registered
     * @param array<string, mixed> $fixed query variables the route sets itself
     * @param \Closure(PostRecord): array<string, mixed> $shape
     */
    private function list(Request $request, string $type, array $registered, array $fixed, \Closure $shape): Response
    {
        $wp = RuntimeRoutes::sanitized($request, $registered);
        if ($wp['orderby'] === 'include' && empty($wp['include'])) {
            throw new RestError('rest_orderby_include_missing_include', 'You need to define an include parameter to order by include.', 400);
        }
        $args = (array) Runtime::hooks()->filter("rest_{$type}_query", [$fixed + PostListArgs::of($wp, $registered, $type), $wp]);
        $vars = PostListArgs::queryVars($args, $wp);
        $query = Handoff::newQuery();
        $posts = (array) $query->query($vars);
        [$total, $pages] = self::totals($query, $vars);
        $rows = [];
        foreach ($posts as $post) {
            if ($post instanceof Post) {
                $rows[] = $shape(PostRecord::fromRow(get_object_vars($post)));
            }
        }
        return Reply::list($request->method === Method::Head ? [] : $rows, $total, $pages, Fields::fromQuery($request->query));
    }

    /**
     * The total and the page count as the posts list gives them: the query's
     * own, or, for a later page that came back empty, a count without the
     * page; a page past the last is refused.
     *
     * @param array<string, mixed> $vars
     * @return array{0: int, 1: int}
     */
    private static function totals(Query $query, array $vars): array
    {
        $page = (int) ($vars['paged'] ?? 0);
        $total = (int) $query->found_posts;
        if ($total < 1 && $page > 1) {
            unset($vars['paged']);
            $count = Handoff::newQuery();
            $count->query($vars);
            $total = (int) $count->found_posts;
        }
        $perPage = (int) $query->query_vars['posts_per_page'];
        $pages = $perPage !== 0 ? (int) ceil($total / $perPage) : 0;
        if ($page > $pages && $total > 0) {
            throw new RestError('rest_post_invalid_page_number', 'The page number requested is larger than the number of pages available.', 400);
        }
        return [$total, $pages];
    }

    /** The family a face route names, or rest_post_invalid_parent. */
    private function parent(int $id): PostRecord
    {
        $family = $id > 0 ? $this->posts->find($id) : null;
        if ($family === null || $family->type !== 'wp_font_family') {
            throw self::invalidParent();
        }
        return $family;
    }

    /** A post of the type, or rest_post_invalid_id (the refusal has looked already; this keeps the handler honest). */
    private function found(int $id, string $type): PostRecord
    {
        $post = $id > 0 ? $this->posts->find($id) : null;
        if ($post === null || $post->type !== $type) {
            throw new RestError('rest_post_invalid_id', 'Invalid post ID.', 404);
        }
        return $post;
    }

    private static function invalidParent(): RestError
    {
        return new RestError('rest_post_invalid_parent', 'Invalid post parent ID.', 404);
    }
}
