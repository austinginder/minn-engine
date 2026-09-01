<?php

declare(strict_types=1);

namespace Minn\Theme;

use Minn\Content\PostWriter;
use Minn\Content\Site;
use Minn\Content\Terms;
use Minn\Db;

/**
 * Saving and removing block templates. A template the theme ships is never
 * touched on disk: editing one writes a wp_template row that shadows the
 * file, and deleting that row is what "reset to the theme version" means.
 */
final readonly class TemplateWriter
{
    public function __construct(
        private Db $db,
        private PostWriter $writer,
        private Terms $terms,
        private Site $site,
        private TemplateIndex $index,
    ) {
    }

    /**
     * Writes the site's own copy of a template, creating the row the first
     * time. Returns the post id.
     *
     * @param array{title?: string, content?: string, description?: string, area?: string} $fields
     */
    public function save(TemplateRecord $record, array $fields, int $authorId): int
    {
        $now = $this->site->localNow();
        $columns = array_filter([
            'post_title' => $fields['title'] ?? null,
            'post_content' => $fields['content'] ?? null,
            'post_excerpt' => $fields['description'] ?? null,
        ], static fn (?string $value) => $value !== null);
        if ($record->wpId > 0) {
            $this->writer->update($record->wpId, $columns + [
                'post_modified' => $now,
                'post_modified_gmt' => gmdate('Y-m-d H:i:s'),
            ]);
            $this->setArea($record->wpId, $fields['area'] ?? $record->area, $record->type);
            return $record->wpId;
        }
        $id = $this->writer->insert([
            'post_author' => $authorId,
            'post_date' => $now,
            'post_date_gmt' => gmdate('Y-m-d H:i:s'),
            'post_modified' => $now,
            'post_modified_gmt' => gmdate('Y-m-d H:i:s'),
            'post_content' => $fields['content'] ?? $record->content,
            'post_title' => $fields['title'] ?? $record->title,
            'post_excerpt' => $fields['description'] ?? $record->description,
            'post_status' => 'publish',
            'post_name' => $record->slug,
            'post_type' => $record->type,
            'post_parent' => 0,
            'guid' => '',
            'to_ping' => '',
            'pinged' => '',
            'post_content_filtered' => '',
        ]);
        $this->writer->setTerms($id, 'wp_theme', [$this->themeTermId()]);
        // The reference records where a site copy came from, so the editor
        // can tell "changed the theme's version" from "made a new one".
        if ($record->hasThemeFile) {
            $this->writer->setMeta($id, 'origin', 'theme');
        }
        $this->setArea($id, $fields['area'] ?? $record->area, $record->type);
        return $id;
    }

    /** Moves a site copy to the trash, which hands the theme's file back. */
    public function trash(TemplateRecord $record): void
    {
        $this->writer->setStatus($record->wpId, 'trash');
    }

    /** Deletes a saved template row. */
    public function destroy(TemplateRecord $record): void
    {
        $this->writer->destroy($record->wpId);
    }

    private function setArea(int $postId, ?string $area, string $type): void
    {
        if ($type !== TemplateIndex::PART || $area === null || $area === '') {
            return;
        }
        $term = $this->terms->findBySlug('wp_template_part_area', $area)
            ?? ['term_id' => $this->terms->create($area, $area, 'wp_template_part_area', '', 0)];
        $this->writer->setTerms($postId, 'wp_template_part_area', [(int) $term['term_id']]);
    }

    private function themeTermId(): int
    {
        $slug = $this->index->themeSlug();
        $term = $this->terms->findBySlug('wp_theme', $slug);
        return $term === null
            ? $this->terms->create($slug, $slug, 'wp_theme', '', 0)
            : (int) $term['term_id'];
    }
}
