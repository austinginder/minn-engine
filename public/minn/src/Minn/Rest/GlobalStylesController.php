<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Http\Subject;
use Minn\Http\Access;
use Minn\Http\Args;
use Minn\Http\RevisionArgs;
use Minn\Content\PostRecord;
use Minn\Content\Revisions;
use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Policy;
use Minn\Http\Route;
use Minn\RestError;
use Minn\Html;
use Minn\Runtime\PostWrites;
use Minn\Support\Slashes;
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
    #[Route(Method::Get, '/wp/v2/global-styles/{id:[\/\d+]+}', policy: new Policy(Access::Public, subject: Subject::GlobalStyles, param: 'id'))]
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
    #[Route(Method::Post, '/wp/v2/global-styles/{id:[\/\d+]+}', policy: new Policy(Access::Cap, 'edit_theme_options', param: 'id', subject: Subject::GlobalStyles, signIn: 'rest_cannot_edit', signInMessage: 'Sorry, you are not allowed to edit this global style.', refuse: 'rest_cannot_edit', message: 'Sorry, you are not allowed to edit this global style.'))]
    #[Route(Method::Put, '/wp/v2/global-styles/{id:[\/\d+]+}', policy: new Policy(Access::Cap, 'edit_theme_options', param: 'id', subject: Subject::GlobalStyles, signIn: 'rest_cannot_edit', signInMessage: 'Sorry, you are not allowed to edit this global style.', refuse: 'rest_cannot_edit', message: 'Sorry, you are not allowed to edit this global style.'))]
    #[Route(Method::Patch, '/wp/v2/global-styles/{id:[\/\d+]+}', policy: new Policy(Access::Cap, 'edit_theme_options', param: 'id', subject: Subject::GlobalStyles, signIn: 'rest_cannot_edit', signInMessage: 'Sorry, you are not allowed to edit this global style.', refuse: 'rest_cannot_edit', message: 'Sorry, you are not allowed to edit this global style.'))]
    public function update(Request $request, string $id): Response
    {
        $post = $this->post((int) $id);
        if (!$this->caller->can('edit_theme_options')) {
            throw $this->caller->refuse('rest_cannot_edit', self::CANNOT_EDIT);
        }
        $body = $request->json();
        $styles = self::node($body, 'styles');
        self::refuseIllegalCss($styles['css'] ?? null);
        // Saved as a post update, as the reference's controller saves it: the save filters (where kses strips what a
        // user without unfiltered_html may not save), the post's hooks, and its revision (probe global-styles-unfiltered).
        $changes = (array) $this->styles->changes($post->id, self::title($body['title'] ?? null), self::node($body, 'settings'), $styles);
        PostWrites::update(Slashes::add(['ID' => $post->id] + $changes));
        return Reply::answer($request, $this->object->item($this->post($post->id), Context::Edit));
    }

    /**
     * Custom CSS that would close its style tag is refused, for every user
     * (probe global-styles-unfiltered): "</style" followed by white space, a
     * slash or ">", in any case, quoted as written; or "</style" at its end.
     */
    private static function refuseIllegalCss(mixed $css): void
    {
        if (!is_string($css)) {
            return;
        }
        if (preg_match('#</style[\t\n\f\r />]#i', $css, $match) === 1) {
            throw new RestError('rest_custom_css_illegal_markup', sprintf('The CSS must not contain "%s".', Html::esc($match[0])), 400);
        }
        if (preg_match('#</style$#i', $css, $match) === 1) {
            throw new RestError('rest_custom_css_illegal_markup', sprintf('The CSS must not end in "%s".', Html::esc($match[0])), 400);
        }
    }

    /** The style variations the active theme ships; listed ahead of the theme's own route, whose pattern would take this path too. */
    #[Route(Method::Get, '/wp/v2/global-styles/themes/{stylesheet:[\/\s%\w\.\(\)\[\]\@_\-]+}/variations', policy: new Policy(Access::Cap, 'edit_posts', signIn: 'rest_cannot_read_global_styles', signInMessage: 'Sorry, you are not allowed to access the global styles on this site.', refuse: 'rest_cannot_read_global_styles', message: 'Sorry, you are not allowed to access the global styles on this site.'))]
    public function variations(Request $request, string $stylesheet): Response
    {
        $this->requireTheme($stylesheet);
        return Reply::answer($request, $this->theme->variations());
    }

    /** The active theme's settings and styles, the engine's defaults underneath. */
    #[Route(Method::Get, '/wp/v2/global-styles/themes/{stylesheet:[^\/:<>\*\?"\|]+(?:\/[^\/:<>\*\?"\|]+)?}', policy: new Policy(Access::Cap, 'edit_posts', signIn: 'rest_cannot_read_global_styles', signInMessage: 'Sorry, you are not allowed to access the global styles on this site.', refuse: 'rest_cannot_read_global_styles', message: 'Sorry, you are not allowed to access the global styles on this site.'))]
    public function theme(Request $request, string $stylesheet): Response
    {
        $this->requireTheme($stylesheet);
        return Reply::answer($request, $this->object->theme($this->theme->settings(), $this->theme->styles(), $stylesheet));
    }

    /** The revisions of the saved styles, newest first; all of them unless per_page pages them, an offset where the page starts (RevisionCollection::paged). */
    #[Route(Method::Get, '/wp/v2/global-styles/{parent:[\d]+}/revisions', policy: new Policy(Access::Cap, 'edit_theme_options', param: 'parent', subject: Subject::GlobalStylesParent, signIn: 'rest_cannot_read', signInMessage: 'Sorry, you are not allowed to view revisions of this post.', refuse: 'rest_cannot_read', message: 'Sorry, you are not allowed to view revisions of this post.'), args: [RevisionArgs::PARENT, Args::CONTEXT, RevisionArgs::STYLES_COLLECTION])]
    public function revisions(Request $request, string $parent): Response
    {
        $rows = PostRecord::fromRows($this->revisions->revisionsOf($this->requireParent((int) $parent)));
        [$shown, $total, $pages] = RevisionCollection::paged($request, $rows);
        $context = Context::of($request);
        return Reply::list(array_map(fn (PostRecord $row): array => $this->revisionObject($row, $context), $shown), $total, $pages, Fields::fromQuery($request->query));
    }

    /** One revision of the saved styles. */
    #[Route(Method::Get, '/wp/v2/global-styles/{parent:[\d]+}/revisions/{id:[\d]+}', policy: new Policy(Access::Cap, 'edit_theme_options', param: 'parent', subject: Subject::GlobalStylesParent, signIn: 'rest_cannot_read', signInMessage: 'Sorry, you are not allowed to view revisions of this post.', refuse: 'rest_cannot_read', message: 'Sorry, you are not allowed to view revisions of this post.'), args: [RevisionArgs::STYLES_PARENT, RevisionArgs::STYLES_ID, Args::CONTEXT])]
    public function revision(Request $request, string $parent, string $id): Response
    {
        $revision = RevisionCollection::ofParent($this->revisions->one((int) $id), $this->requireParent((int) $parent));
        return Reply::answer($request, $this->revisionObject($revision, Context::of($request)));
    }

    /** A revision in the asked context: its embed view is who saved it, when, and whose revision it is. */
    private function revisionObject(PostRecord $revision, Context $context): array
    {
        $object = $this->object->revision($revision);
        return $context === Context::Embed ? array_intersect_key($object, array_flip(['author', 'date', 'id', 'parent'])) : $object;
    }

    private function post(int $id): PostRecord
    {
        return $this->styles->find($id) ?? throw new RestError('rest_global_styles_not_found', 'No global styles config exists with that ID.', 404);
    }

    /** The theme routes answer only for the active stylesheet, and only to those who edit posts. */
    private function requireTheme(string $stylesheet): void
    {
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
