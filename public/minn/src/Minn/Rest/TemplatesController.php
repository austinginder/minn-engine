<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;
use Minn\RestError;
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
    #[Route(Method::Get, '/wp/v2/templates')]
    public function templates(Request $request): Response
    {
        return $this->listing($request, TemplateIndex::TEMPLATE);
    }

    /** The template parts list. */
    #[Route(Method::Get, '/wp/v2/template-parts')]
    public function parts(Request $request): Response
    {
        return $this->listing($request, TemplateIndex::PART);
    }

    /** One template. */
    #[Route(Method::Get, '/wp/v2/templates/{id*}')]
    public function template(Request $request, string $id): Response
    {
        return $this->single($request, TemplateIndex::TEMPLATE, $id);
    }

    /** One template part. */
    #[Route(Method::Get, '/wp/v2/template-parts/{id*}')]
    public function part(Request $request, string $id): Response
    {
        return $this->single($request, TemplateIndex::PART, $id);
    }

    /** Saves a template. */
    #[Route(Method::Post, '/wp/v2/templates/{id*}')]
    #[Route(Method::Put, '/wp/v2/templates/{id*}')]
    #[Route(Method::Patch, '/wp/v2/templates/{id*}')]
    public function saveTemplate(Request $request, string $id): Response
    {
        return $this->save($request, TemplateIndex::TEMPLATE, $id);
    }

    /** Saves a template part. */
    #[Route(Method::Post, '/wp/v2/template-parts/{id*}')]
    #[Route(Method::Put, '/wp/v2/template-parts/{id*}')]
    #[Route(Method::Patch, '/wp/v2/template-parts/{id*}')]
    public function savePart(Request $request, string $id): Response
    {
        return $this->save($request, TemplateIndex::PART, $id);
    }

    /** Deletes a customised template. */
    #[Route(Method::Delete, '/wp/v2/templates/{id*}')]
    public function deleteTemplate(Request $request, string $id): Response
    {
        return $this->delete($request, TemplateIndex::TEMPLATE, $id);
    }

    /** Deletes a customised template part. */
    #[Route(Method::Delete, '/wp/v2/template-parts/{id*}')]
    public function deletePart(Request $request, string $id): Response
    {
        return $this->delete($request, TemplateIndex::PART, $id);
    }

    private function listing(Request $request, string $type): Response
    {
        $index = $this->readable();
        $edit = Context::of($request)->isEdit();
        $fields = Fields::fromQuery($request->query);
        $rows = array_map(
            function (TemplateRecord $record) use ($edit, $fields): array {
                $row = $this->object->view($record, $edit);
                return $fields === null ? $row : $fields->apply($row);
            },
            $index->all($type),
        );
        // The reference paginates neither list, so neither carries the
        // X-WP-Total pair its paginated collections do.
        return Reply::item($rows, null);
    }

    private function single(Request $request, string $type, string $id): Response
    {
        $record = $this->record($type, $id);
        return Reply::item(
            $this->object->view($record, Context::of($request)->isEdit()),
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
            $this->object->view($this->record($type, $id), true),
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
        if ($request->query('force') !== 'true') {
            $this->writer->trash($record);
            return Reply::item($this->object->view($this->trashed($record), true), $fields);
        }
        $previous = $this->object->view($record, true);
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
