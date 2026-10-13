<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Http\Access;
use Minn\Http\Args;
use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Policy;
use Minn\Http\Route;
use Minn\RestError;
use Minn\Runtime\Runtime;
use Minn\Theme\TemplateIndex;
use Minn\Theme\TemplateRecord;
use Minn\Theme\TemplateWriter;
use Minn\Content\Slug;

/**
 * wp/v2/templates and wp/v2/template-parts: the block theme's templates as
 * one list, whether they come from the theme's files, from a row the site
 * saved over one, or from a plugin. Reading needs only edit_posts (the
 * reference lets an editor or author see the layout); changing anything
 * needs edit_theme_options.
 */
final readonly class TemplatesController
{
    /** What creating a template must send (the reference's create arguments). */
    private const CREATE_BODY = ['slug' => ['description' => 'Unique slug identifying the template.', 'type' => 'string', 'minLength' => 1, 'pattern' => '[a-zA-Z0-9_\\%-]+', 'required' => true]];

    private const LOOKUP = [
        'slug' => ['description' => 'The slug of the template to get the fallback for', 'type' => 'string', 'required' => true],
        'is_custom' => ['description' => 'Indicates if a template is custom or part of the template hierarchy', 'type' => 'boolean', 'required' => false],
        'template_prefix' => ['description' => 'The template prefix for the created template. This is used to extract the main template type, e.g. in `taxonomy-books` extracts the `taxonomy`', 'type' => 'string', 'required' => false],
    ];

    /** The list filters both routes take, as the reference declares them. */
    private const LIST = [
        'wp_id' => ['description' => 'Limit to the specified post id.', 'type' => 'integer'],
        'area' => ['description' => 'Limit to the specified template part area.', 'type' => 'string'],
        'post_type' => ['description' => 'Post type to get the templates for.', 'type' => 'string'],
    ];

    private const READ_CAP = 'edit_posts';
    private const WRITE_CAP = 'edit_theme_options';
    private const REFUSAL = 'Sorry, you are not allowed to access the templates on this site.';

    public function __construct(
        private TemplateIndex $index,
        private TemplateWriter $writer,
        private TemplateObject $object,
        private Caller $caller,
    ) {
    }

    /** The templates list. */
    #[Route(Method::Get, '/wp/v2/templates', policy: new Policy(Access::Cap, self::READ_CAP, signIn: 'rest_cannot_manage_templates', signInMessage: self::REFUSAL, refuse: 'rest_cannot_manage_templates', message: self::REFUSAL), args: [Args::CONTEXT, self::LIST])]
    public function templates(Request $request): Response
    {
        return $this->listing($request, TemplateIndex::TEMPLATE);
    }

    /** The template parts list. */
    #[Route(Method::Get, '/wp/v2/template-parts', policy: new Policy(Access::Cap, self::READ_CAP, signIn: 'rest_cannot_manage_templates', signInMessage: self::REFUSAL, refuse: 'rest_cannot_manage_templates', message: self::REFUSAL), args: [Args::CONTEXT, self::LIST])]
    public function parts(Request $request): Response
    {
        return $this->listing($request, TemplateIndex::PART);
    }

    /** The template a slug would use: the first in its hierarchy the theme or the site has. */
    #[Route(Method::Get, '/wp/v2/templates/lookup', policy: new Policy(Access::Cap, self::READ_CAP, signIn: 'rest_cannot_manage_templates', signInMessage: self::REFUSAL, refuse: 'rest_cannot_manage_templates', message: self::REFUSAL), args: [self::LOOKUP])]
    public function lookupTemplate(Request $request): Response
    {
        return $this->lookup($request, TemplateIndex::TEMPLATE);
    }

    /** The same lookup under the parts route: it searches templates, not parts, as the reference's does. */
    #[Route(Method::Get, '/wp/v2/template-parts/lookup', policy: new Policy(Access::Cap, self::READ_CAP, signIn: 'rest_cannot_manage_templates', signInMessage: self::REFUSAL, refuse: 'rest_cannot_manage_templates', message: self::REFUSAL), args: [self::LOOKUP])]
    public function lookupPart(Request $request): Response
    {
        return $this->lookup($request, TemplateIndex::PART);
    }

    /** One template. */
    #[Route(Method::Get, '/wp/v2/templates/{id:([^\/:<>\*\?"\|]+(?:\/[^\/:<>\*\?"\|]+)?)[\/\w%-]+}', policy: new Policy(Access::Cap, self::READ_CAP, signIn: 'rest_cannot_manage_templates', signInMessage: self::REFUSAL, refuse: 'rest_cannot_manage_templates', message: self::REFUSAL))]
    public function template(Request $request, string $id): Response
    {
        return $this->single($request, TemplateIndex::TEMPLATE, $id);
    }

    /** One template part. */
    #[Route(Method::Get, '/wp/v2/template-parts/{id:([^\/:<>\*\?"\|]+(?:\/[^\/:<>\*\?"\|]+)?)[\/\w%-]+}', policy: new Policy(Access::Cap, self::READ_CAP, signIn: 'rest_cannot_manage_templates', signInMessage: self::REFUSAL, refuse: 'rest_cannot_manage_templates', message: self::REFUSAL))]
    public function part(Request $request, string $id): Response
    {
        return $this->single($request, TemplateIndex::PART, $id);
    }

    /** Creates a template. */
    #[Route(Method::Post, '/wp/v2/templates', policy: new Policy(Access::Cap, self::READ_CAP, caps: [self::WRITE_CAP], signIn: 'rest_cannot_manage_templates', signInMessage: self::REFUSAL, refuse: 'rest_cannot_manage_templates', message: self::REFUSAL), body: [self::CREATE_BODY])]
    public function createTemplate(Request $request): Response
    {
        return $this->create($request, TemplateIndex::TEMPLATE);
    }

    /** Creates a template part. */
    #[Route(Method::Post, '/wp/v2/template-parts', policy: new Policy(Access::Cap, self::READ_CAP, caps: [self::WRITE_CAP], signIn: 'rest_cannot_manage_templates', signInMessage: self::REFUSAL, refuse: 'rest_cannot_manage_templates', message: self::REFUSAL), body: [self::CREATE_BODY])]
    public function createPart(Request $request): Response
    {
        return $this->create($request, TemplateIndex::PART);
    }

    /** Saves a template. */
    #[Route(Method::Post, '/wp/v2/templates/{id:([^\/:<>\*\?"\|]+(?:\/[^\/:<>\*\?"\|]+)?)[\/\w%-]+}', policy: new Policy(Access::Cap, self::READ_CAP, caps: [self::WRITE_CAP], signIn: 'rest_cannot_manage_templates', signInMessage: self::REFUSAL, refuse: 'rest_cannot_manage_templates', message: self::REFUSAL))]
    #[Route(Method::Put, '/wp/v2/templates/{id:([^\/:<>\*\?"\|]+(?:\/[^\/:<>\*\?"\|]+)?)[\/\w%-]+}', policy: new Policy(Access::Cap, self::READ_CAP, caps: [self::WRITE_CAP], signIn: 'rest_cannot_manage_templates', signInMessage: self::REFUSAL, refuse: 'rest_cannot_manage_templates', message: self::REFUSAL))]
    #[Route(Method::Patch, '/wp/v2/templates/{id:([^\/:<>\*\?"\|]+(?:\/[^\/:<>\*\?"\|]+)?)[\/\w%-]+}', policy: new Policy(Access::Cap, self::READ_CAP, caps: [self::WRITE_CAP], signIn: 'rest_cannot_manage_templates', signInMessage: self::REFUSAL, refuse: 'rest_cannot_manage_templates', message: self::REFUSAL))]
    public function saveTemplate(Request $request, string $id): Response
    {
        return $this->save($request, TemplateIndex::TEMPLATE, $id);
    }

    /** Saves a template part. */
    #[Route(Method::Post, '/wp/v2/template-parts/{id:([^\/:<>\*\?"\|]+(?:\/[^\/:<>\*\?"\|]+)?)[\/\w%-]+}', policy: new Policy(Access::Cap, self::READ_CAP, caps: [self::WRITE_CAP], signIn: 'rest_cannot_manage_templates', signInMessage: self::REFUSAL, refuse: 'rest_cannot_manage_templates', message: self::REFUSAL))]
    #[Route(Method::Put, '/wp/v2/template-parts/{id:([^\/:<>\*\?"\|]+(?:\/[^\/:<>\*\?"\|]+)?)[\/\w%-]+}', policy: new Policy(Access::Cap, self::READ_CAP, caps: [self::WRITE_CAP], signIn: 'rest_cannot_manage_templates', signInMessage: self::REFUSAL, refuse: 'rest_cannot_manage_templates', message: self::REFUSAL))]
    #[Route(Method::Patch, '/wp/v2/template-parts/{id:([^\/:<>\*\?"\|]+(?:\/[^\/:<>\*\?"\|]+)?)[\/\w%-]+}', policy: new Policy(Access::Cap, self::READ_CAP, caps: [self::WRITE_CAP], signIn: 'rest_cannot_manage_templates', signInMessage: self::REFUSAL, refuse: 'rest_cannot_manage_templates', message: self::REFUSAL))]
    public function savePart(Request $request, string $id): Response
    {
        return $this->save($request, TemplateIndex::PART, $id);
    }

    /** Deletes a customised template. */
    #[Route(Method::Delete, '/wp/v2/templates/{id:([^\/:<>\*\?"\|]+(?:\/[^\/:<>\*\?"\|]+)?)[\/\w%-]+}', policy: new Policy(Access::Cap, self::READ_CAP, caps: [self::WRITE_CAP], signIn: 'rest_cannot_manage_templates', signInMessage: self::REFUSAL, refuse: 'rest_cannot_manage_templates', message: self::REFUSAL))]
    public function deleteTemplate(Request $request, string $id): Response
    {
        return $this->delete($request, TemplateIndex::TEMPLATE, $id);
    }

    /** Deletes a customised template part. */
    #[Route(Method::Delete, '/wp/v2/template-parts/{id:([^\/:<>\*\?"\|]+(?:\/[^\/:<>\*\?"\|]+)?)[\/\w%-]+}', policy: new Policy(Access::Cap, self::READ_CAP, caps: [self::WRITE_CAP], signIn: 'rest_cannot_manage_templates', signInMessage: self::REFUSAL, refuse: 'rest_cannot_manage_templates', message: self::REFUSAL))]
    public function deletePart(Request $request, string $id): Response
    {
        return $this->delete($request, TemplateIndex::PART, $id);
    }

    private function listing(Request $request, string $type): Response
    {
        $index = $this->readable();
        $context = Context::of($request);
        $fields = Fields::fromQuery($request->query);
        $rows = array_map(
            function (TemplateRecord $record) use ($context, $fields): array {
                $row = $this->object->view($record, $context);
                return $fields === null ? $row : $fields->apply($row);
            },
            array_values(array_filter($index->all($type), fn (TemplateRecord $record): bool => $this->listed($record, $request->query, $index))),
        );
        // The reference paginates neither list, so neither carries the
        // X-WP-Total pair its paginated collections do.
        return Reply::item($rows, null);
    }

    /**
     * Whether a record passes the list's filters (probe patch-security):
     * wp_id matches only a stored one with that id, so a theme file (wp_id 0)
     * never does; post_type keeps templates that declare that type; area
     * keeps parts in that area. Each filter applies only where it means
     * something.
     *
     * @param array<string, mixed> $query
     */
    private function listed(TemplateRecord $record, array $query, TemplateIndex $index): bool
    {
        if (isset($query['wp_id']) && ($record->wpId === 0 || $record->wpId !== (int) $query['wp_id'])) {
            return false;
        }
        if (isset($query['post_type']) && !$record->isPart() && !in_array((string) $query['post_type'], $index->postTypes($record->slug), true)) {
            return false;
        }
        return !isset($query['area']) || !$record->isPart() || $record->area === (string) $query['area'];
    }

    /**
     * The first template in the slug's hierarchy that exists, shown as the
     * route's own type shows an item (the parts route searches templates
     * too, as the reference's does); the plugins' templates count once
     * plugins are loaded.
     */
    private function lookup(Request $request, string $as): Response
    {
        if (!Runtime::booted()) {
            throw RestError::noRoute();
        }
        $hierarchy = \get_template_hierarchy((string) $request->query['slug'], SchemaValues::toBoolean($request->query['is_custom'] ?? false), (string) ($request->query['template_prefix'] ?? ''));
        $found = [];
        foreach (\get_block_templates(['slug__in' => $hierarchy], TemplateIndex::TEMPLATE) as $template) {
            $found[$template->slug] ??= $template->id;
        }
        foreach ($hierarchy as $slug) {
            if (isset($found[$slug])) {
                return Reply::item($this->object->view($this->record(TemplateIndex::TEMPLATE, $found[$slug]), Context::of($request), $as), Fields::fromQuery($request->query));
            }
        }
        throw new RestError('rest_template_not_found', 'No templates exist with that id.', 404);
    }

    private function single(Request $request, string $type, string $id): Response
    {
        $record = $this->record($type, $id);
        return Reply::item(
            $this->object->view($record, Context::of($request)),
            Fields::fromQuery($request->query),
        );
    }

    private function save(Request $request, string $type, string $id): Response
    {
        $this->requireWrite();
        $record = $this->record($type, $id);
        $this->writer->save($record, $this->fields($request->json()), $this->caller->id());
        return Reply::item(
            $this->object->view($this->record($type, $id), Context::Edit),
            Fields::fromQuery($request->query),
        );
    }

    /**
     * A new template from the collection (probe rest-template-create): its
     * slug sanitised and made unique among the site's own copies of the
     * type (-2, -3...). A slug only a theme file holds makes the site's copy
     * of that file, blank but for what the body sends. Whatever status is
     * asked for, it is published; the answer is in the request's context.
     */
    private function create(Request $request, string $type): Response
    {
        $this->requireWrite();
        $index = $this->readable();
        $slug = Slug::sanitize((string) ($request->json()['slug'] ?? ''));
        $taken = array_map(static fn (TemplateRecord $record) => $record->slug, array_filter($index->all($type), static fn (TemplateRecord $record) => $record->wpId > 0));
        for ($n = 2, $unique = $slug; in_array($unique, $taken, true); $n++) {
            $unique = $slug . '-' . $n;
        }
        // Over a theme file's slug the copy keeps nothing of the file: what the body leaves out is blank.
        $record = new TemplateRecord(theme: $index->themeSlug(), slug: $unique, type: $type, content: '', title: '', description: '', source: 'custom', origin: null, hasThemeFile: $index->hasFile($type, $unique), author: 0, modified: null, date: null, wpId: 0, area: $type === TemplateIndex::PART ? 'uncategorized' : null);
        $this->writer->create($record, $this->fields($request->json()), $this->caller->id());
        // The answer is in the request's own context, the view unless it asks for edit.
        return Reply::item($this->object->view($this->record($type, $index->themeSlug() . '//' . $unique), Context::of($request)), Fields::fromQuery($request->query), 201);
    }

    /**
     * The fields a write sets, those the body sends.
     *
     * @param array<string, mixed> $body
     * @return array<string, string>
     */
    private function fields(array $body): array
    {
        return array_filter(
            [
                'title' => $this->text($body['title'] ?? null),
                'content' => $this->text($body['content'] ?? null),
                'description' => $this->text($body['description'] ?? null),
                'area' => isset($body['area']) ? (string) $body['area'] : null,
            ],
            static fn (?string $value) => $value !== null,
        );
    }

    /**
     * Removing a template means removing the site's own copy of it. Without
     * force that copy goes to the trash, which already hands the theme's
     * file back; with force the row goes for good. A template that exists
     * only as a theme file has nothing to remove.
     */
    private function delete(Request $request, string $type, string $id): Response
    {
        $this->requireWrite();
        $record = $this->record($type, $id);
        if ($record->wpId === 0) {
            throw new RestError('rest_invalid_template', "Templates based on theme files can't be removed.", 400);
        }
        $fields = Fields::fromQuery($request->query);
        if (!$request->flagged('force')) {
            $this->writer->trash($record);
            return Reply::item($this->object->view($this->trashed($record), Context::Edit), $fields);
        }
        $previous = $this->object->view($record, Context::Edit);
        $this->writer->destroy($record);
        return Reply::item(['deleted' => true, 'previous' => $previous], $fields);
    }

    private function trashed(TemplateRecord $record): TemplateRecord
    {
        return new TemplateRecord(
            theme: $record->theme,
            slug: $record->slug,
            type: $record->type,
            content: $record->content,
            title: $record->title,
            description: $record->description,
            source: $record->source,
            origin: $record->origin,
            hasThemeFile: $record->hasThemeFile,
            author: $record->author,
            modified: $record->modified,
            date: $record->date,
            wpId: $record->wpId,
            status: 'trash',
            area: $record->area,
            plugin: $record->plugin,
        );
    }

    private function record(string $type, string $id): TemplateRecord
    {
        $record = $this->readable()->find($type, rawurldecode($id));
        if ($record === null) {
            throw new RestError('rest_template_not_found', 'No templates exist with that id.', 404);
        }
        return $record;
    }

    /** Reading the layout is an editor's business, not only an administrator's. */
    private function readable(): TemplateIndex
    {
        if ($this->caller->session() === null) {
            throw new RestError('rest_cannot_manage_templates', self::REFUSAL, 401);
        }
        if (!$this->caller->can(self::READ_CAP)) {
            throw new RestError('rest_cannot_manage_templates', self::REFUSAL, 403);
        }
        return $this->index;
    }

    private function requireWrite(): void
    {
        $this->readable();
        if (!$this->caller->can(self::WRITE_CAP)) {
            throw new RestError('rest_cannot_manage_templates', self::REFUSAL, 403);
        }
    }

    /** A title or content field arrives either bare or as the raw member of its object. */
    private function text(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = $value['raw'] ?? null;
        }
        return is_string($value) ? $value : null;
    }
}
