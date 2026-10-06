<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Http\Access;
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

/**
 * wp/v2/templates and wp/v2/template-parts: the block theme's templates as
 * one list, whether they come from the theme's files, from a row the site
 * saved over one, or from a plugin. Reading needs only edit_posts (the
 * reference lets an editor or author see the layout); changing anything
 * needs edit_theme_options.
 */
final readonly class TemplatesController
{
    private const LOOKUP = [
        'slug' => ['description' => 'The slug of the template to get the fallback for', 'type' => 'string', 'required' => true],
        'is_custom' => ['description' => 'Indicates if a template is custom or part of the template hierarchy', 'type' => 'boolean', 'required' => false],
        'template_prefix' => ['description' => 'The template prefix for the created template. This is used to extract the main template type, e.g. in `taxonomy-books` extracts the `taxonomy`', 'type' => 'string', 'required' => false],
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
    #[Route(Method::Get, '/wp/v2/templates', policy: new Policy(Access::Cap, self::READ_CAP, signIn: 'rest_cannot_manage_templates', signInMessage: self::REFUSAL, refuse: 'rest_cannot_manage_templates', message: self::REFUSAL))]
    public function templates(Request $request): Response
    {
        return $this->listing($request, TemplateIndex::TEMPLATE);
    }

    /** The template parts list. */
    #[Route(Method::Get, '/wp/v2/template-parts', policy: new Policy(Access::Cap, self::READ_CAP, signIn: 'rest_cannot_manage_templates', signInMessage: self::REFUSAL, refuse: 'rest_cannot_manage_templates', message: self::REFUSAL))]
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
        $edit = $context->isEdit();
        $fields = Fields::fromQuery($request->query);
        $rows = array_map(
            function (TemplateRecord $record) use ($context, $fields): array {
                $row = $this->object->view($record, $context);
                return $fields === null ? $row : $fields->apply($row);
            },
            $index->all($type),
        );
        // The reference paginates neither list, so neither carries the
        // X-WP-Total pair its paginated collections do.
        return Reply::item($rows, null);
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
        $hierarchy = \get_template_hierarchy((string) $request->query['slug'], \rest_sanitize_boolean($request->query['is_custom'] ?? false), (string) ($request->query['template_prefix'] ?? ''));
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
        $body = $request->json();
        $fields = array_filter(
            [
                'title' => $this->text($body['title'] ?? null),
                'content' => $this->text($body['content'] ?? null),
                'description' => $this->text($body['description'] ?? null),
                'area' => isset($body['area']) ? (string) $body['area'] : null,
            ],
            static fn (?string $value) => $value !== null,
        );
        $this->writer->save($record, $fields, $this->caller->id());
        return Reply::item(
            $this->object->view($this->record($type, $id), Context::Edit),
            Fields::fromQuery($request->query),
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
        if (!$request->flag('force')) {
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
