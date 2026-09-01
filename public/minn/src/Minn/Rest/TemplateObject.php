<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Content\Posts;
use Minn\Theme\TemplateIndex;
use Minn\Theme\TemplateRecord;

/** The wp/v2/templates and wp/v2/template-parts resource. */
final readonly class TemplateObject
{
    public function __construct(
        private TemplateIndex $index,
        private Posts $posts,
        private RestUrl $url,
        private Caller $caller,
    ) {
    }

    public function base(string $type): string
    {
        return $type === TemplateIndex::PART ? 'template-parts' : 'templates';
    }

    public function view(TemplateRecord $record, bool $edit): array
    {
        $id = $record->id();
        $content = ['raw' => $record->content];
        if ($edit) {
            // The reference reports the block grammar version it found in
            // the markup: 1 once a block delimiter appears, 0 for markup
            // that is only HTML.
            $content['block_version'] = str_contains($record->content, '<!-- wp:') ? 1 : 0;
        }
        $out = [
            'id' => $id,
            'theme' => $record->theme,
            'content' => $content,
            'slug' => $record->slug,
            'source' => $record->source,
            'origin' => $record->origin,
            'type' => $record->type,
            'description' => $record->description,
            'title' => ['raw' => $record->title, 'rendered' => $record->title],
            'status' => $record->status,
            'wp_id' => $record->wpId,
            'has_theme_file' => $record->hasThemeFile,
        ];
        if (!$record->isPart()) {
            $out['is_custom'] = TemplateIndex::isCustom($record->slug);
        }
        $out['author'] = $record->author;
        if ($record->isPart()) {
            $out['area'] = (string) $record->area;
        }
        $out['modified'] = $record->modified;
        $out['date'] = $record->date;
        $out['author_text'] = $this->index->authorText($record);
        $out['original_source'] = $this->index->originalSource($record);
        if ($record->plugin !== null) {
            $out['plugin'] = $record->plugin;
        }
        if ($edit) {
            // Minn Admin's two edit-context fields. A template takes no lock
            // and has no autosave slot, so both are constant here.
            $out['minn_modified'] = false;
            $out['minn_lock'] = null;
        }
        $out['_links'] = $this->links($record);
        return $out;
    }

    /**
     * The write links and their curies show only to a caller who could use
     * them; a reader who may list templates but not change them sees a
     * self link that allows GET and nothing else.
     */
    private function links(TemplateRecord $record): array
    {
        $base = $this->base($record->type);
        $self = $this->url->to("/wp/v2/{$base}/{$record->id()}");
        $canWrite = $this->caller->can('edit_theme_options');
        $links = [
            'self' => [['href' => $self, 'targetHints' => ['allow' => $canWrite ? ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] : ['GET']]]],
            'collection' => [['href' => $this->url->to("/wp/v2/{$base}")]],
            'about' => [['href' => $this->url->to("/wp/v2/types/{$record->type}")]],
        ];
        if (!$canWrite) {
            return $links;
        }
        if ($record->wpId > 0) {
            $links['version-history'] = [['count' => $this->posts->revisionCount($record->wpId), 'href' => $self . '/revisions']];
            $latest = $this->posts->latestRevisionId($record->wpId);
            if ($latest > 0) {
                $links['predecessor-version'] = [['id' => $latest, 'href' => $self . '/revisions/' . $latest]];
            }
        }
        $links['wp:action-publish'] = [['href' => $self]];
        $links['wp:action-unfiltered-html'] = [['href' => $self]];
        $links['curies'] = RestUrl::curies();
        return $links;
    }
}
