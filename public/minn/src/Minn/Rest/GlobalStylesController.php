<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Content\PostRecord;
use Minn\Content\Revisions;
use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;
use Minn\RestError;
use Minn\Theme\ThemeStyles;
use Minn\Theme\UserStyles;

/**
 * wp/v2/global-styles: the site editor's saved styles (one post per
 * theme, read and written by id, with its revisions) and the active
 * theme's own styles and variations. There is no collection, no create
 * and no delete; the post is made on first use, as on the reference.
 */
final readonly class GlobalStylesController
{
    private const CANNOT_EDIT = 'Sorry, you are not allowed to edit this global style.';
    private const CANNOT_READ_REVISIONS = 'Sorry, you are not allowed to view revisions of this post.';

    public function __construct(
        private UserStyles $styles,
        private ThemeStyles $theme,
        private GlobalStylesObject $object,
        private Revisions $revisions,
        private Caller $caller,
    ) {
    }

    /** The saved styles by id: anyone who edits posts may read them, editing context needs the theme. */
    #[Route(Method::Get, '/wp/v2/global-styles/{id:\d+}')]
    public function single(Request $request, string $id): Response
    {
        $post = $this->post((int) $id);
        if (Context::of($request)->isEdit() && !$this->caller->can('edit_theme_options')) {
            throw $this->caller->refuse('rest_forbidden_context', self::CANNOT_EDIT);
        }
        if (!$this->caller->can('edit_posts')) {
            throw $this->caller->refuse('rest_cannot_view', 'Sorry, you are not allowed to view this global style.');
        }
        return Reply::answer($request, $this->object->item($post, Context::of($request)));
    }

    /** Replaces the title, settings, or styles the body names; what it leaves out stays. */
    #[Route(Method::Post, '/wp/v2/global-styles/{id:\d+}')]
    #[Route(Method::Put, '/wp/v2/global-styles/{id:\d+}')]
    #[Route(Method::Patch, '/wp/v2/global-styles/{id:\d+}')]
    public function update(Request $request, string $id): Response
    {
        $post = $this->post((int) $id);
        if (!$this->caller->can('edit_theme_options')) {
            throw $this->caller->refuse('rest_cannot_edit', self::CANNOT_EDIT);
        }
        $body = $request->json();
        $this->styles->save($post->id, $this->caller->id(), self::title($body['title'] ?? null), self::node($body, 'settings'), self::node($body, 'styles'));
        return Reply::answer($request, $this->object->item($this->post($post->id), Context::Edit));
    }

    /** The active theme's settings and styles, the engine's defaults underneath. */
    #[Route(Method::Get, '/wp/v2/global-styles/themes/{stylesheet:[^/]+}')]
    public function theme(Request $request, string $stylesheet): Response
    {
        $this->requireTheme($stylesheet);
        return Reply::answer($request, $this->object->theme($this->theme->settings(), $this->theme->styles(), $stylesheet));
    }

    /** The style variations the active theme ships. */
    #[Route(Method::Get, '/wp/v2/global-styles/themes/{stylesheet:[^/]+}/variations')]
    public function variations(Request $request, string $stylesheet): Response
    {
        $this->requireTheme($stylesheet);
        return Reply::answer($request, $this->theme->variations());
    }

    /** The revisions of the saved styles, newest first; all of them unless per_page pages them. */
    #[Route(Method::Get, '/wp/v2/global-styles/{parent:\d+}/revisions')]
    public function revisions(Request $request, string $parent): Response
    {
        $rows = PostRecord::fromRows($this->revisions->revisionsOf($this->requireParent((int) $parent)));
        $perPage = (int) $request->query('per_page', '0');
        $page = max(1, (int) $request->query('page', '1'));
        $pages = $perPage > 0 ? (int) ceil(count($rows) / $perPage) : 1;
        // A page past the end is an error only when there are revisions to page through.
        if ($rows !== [] && $page > $pages) {
            throw new RestError('rest_revision_invalid_page_number', 'The page number requested is larger than the number of pages available.', 400);
        }
        $shown = $perPage > 0 ? array_slice($rows, ($page - 1) * $perPage, $perPage) : $rows;
        return Reply::list(array_map($this->object->revision(...), $shown), count($rows), $pages, Fields::fromQuery($request->query));
    }

    /** One revision of the saved styles. */
    #[Route(Method::Get, '/wp/v2/global-styles/{parent:\d+}/revisions/{id:\d+}')]
    public function revision(Request $request, string $parent, string $id): Response
    {
        foreach (PostRecord::fromRows($this->revisions->revisionsOf($this->requireParent((int) $parent))) as $revision) {
            if ($revision->id === (int) $id) {
                return Reply::answer($request, $this->object->revision($revision));
            }
        }
        throw new RestError('rest_post_invalid_id', 'Invalid revision ID.', 404);
    }

    private function post(int $id): PostRecord
    {
        return $this->styles->find($id) ?? throw new RestError('rest_global_styles_not_found', 'No global styles config exists with that ID.', 404);
    }

    /** The theme routes answer only for the active stylesheet, and only to those who edit posts. */
    private function requireTheme(string $stylesheet): void
    {
        if (!$this->caller->can('edit_posts')) {
            throw $this->caller->refuse('rest_cannot_read_global_styles', 'Sorry, you are not allowed to access the global styles on this site.');
        }
        if ($stylesheet !== $this->theme->stylesheet) {
            throw new RestError('rest_theme_not_found', 'Theme not found.', 404);
        }
    }

    /** Revisions are for those who edit the theme, of a parent that is a global-styles post. */
    private function requireParent(int $parentId): int
    {
        $this->caller->require('rest_cannot_read', self::CANNOT_READ_REVISIONS);
        if ($this->styles->find($parentId) === null) {
            throw new RestError('rest_post_invalid_parent', 'Invalid post parent ID.', 404);
        }
        if (!$this->caller->can('edit_theme_options')) {
            throw new RestError('rest_cannot_read', self::CANNOT_READ_REVISIONS, 403);
        }
        return $parentId;
    }

    /** A title arrives as a string or as {raw}; anything else is ignored. */
    private static function title(mixed $title): ?string
    {
        if (is_array($title)) {
            $title = $title['raw'] ?? null;
        }
        return is_string($title) ? $title : null;
    }

    /** settings and styles are objects when present; a scalar is the reference's type error, null is ignored. */
    private static function node(array $body, string $name): ?array
    {
        $value = $body[$name] ?? null;
        if ($value === null) {
            return null;
        }
        if (!is_array($value)) {
            throw new RestError('rest_invalid_param', "Invalid parameter(s): {$name}", 400, [
                'params' => [$name => "{$name} is not of type object."],
                'details' => [$name => ['code' => 'rest_invalid_type', 'message' => "{$name} is not of type object.", 'data' => ['param' => $name]]],
            ]);
        }
        return $value;
    }
}
