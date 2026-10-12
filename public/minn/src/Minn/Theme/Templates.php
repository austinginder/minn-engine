<?php

declare(strict_types=1);

namespace Minn\Theme;

use Minn\Content\Posts;
use Minn\Content\RowCache;
use Minn\Db;
use Minn\Front\Kind;
use Minn\Front\Resolution;
use Minn\Json;
use Minn\Runtime\BlockTemplates;
use Minn\Runtime\Runtime;

/**
 * Which template renders a resolution, and where its markup comes from:
 * a wp_template post saved from the site editor (matched by slug and the
 * theme term) wins over the theme's file. Parts resolve the same way
 * through wp_template_part.
 */
final readonly class Templates
{
    public function __construct(
        private Db $db,
        private Posts $posts,
        private Theme $theme,
        private ?BlockTemplates $registered = null,
    ) {
    }

    /**
     * The template that renders a resolution, or null.
     *
     * @return array{slug: string, markup: string}|null
     */
    public function forResolution(Resolution $resolution): ?array
    {
        foreach ($this->candidates($resolution) as $slug) {
            $markup = $this->template($slug);
            if ($markup !== null) {
                return ['slug' => $slug, 'markup' => $markup];
            }
        }
        return null;
    }

    /** A template's markup by slug, saved first. */
    public function template(string $slug): ?string
    {
        return $this->saved('wp_template', $slug) ?? $this->theme->templateFile($slug) ?? $this->registered?->bySlug($slug)['content'] ?? null;
    }

    /** A template part's markup by slug, saved first. */
    public function part(string $slug): ?string
    {
        return $this->partSource($slug)[2];
    }

    /**
     * Where a template part comes from: a part saved for the theme (its
     * post id), the theme's file (its path), or nowhere; with its markup.
     *
     * @return array{0: 'post'|'file'|'none', 1: int|string, 2: ?string}
     */
    public function partSource(string $slug): array
    {
        $row = $this->savedRow('wp_template_part', $slug);
        if ($row !== null) {
            return ['post', (int) $row['ID'], (string) $row['post_content']];
        }
        $path = $this->theme->partPath($slug);
        return $path === null ? ['none', '', null] : ['file', $path, (string) file_get_contents($path)];
    }

    /**
     * The block-theme template hierarchy for each kind of resolution.
     *
     * @return list<string>
     */
    public function candidates(Resolution $resolution): array
    {
        return $this->filtered($resolution, $this->hierarchy($resolution));
    }

    /**
     * Plugins reshape the hierarchy through the reference's
     * "{type}_template_hierarchy" filters (WooCommerce sends its taxonomies to
     * archive-product that way); the filter sees PHP file names and hands
     * back the same, which become slugs again.
     *
     * @param list<string> $slugs
     * @return list<string>
     */
    private function filtered(Resolution $resolution, array $slugs): array
    {
        $type = match ($resolution->kind) {
            Kind::Single => 'single',
            Kind::Page => 'page',
            Kind::Category => 'category',
            Kind::Tag => 'tag',
            Kind::Taxonomy => 'taxonomy',
            Kind::PostTypeArchive => 'archive',
            Kind::Author => 'author',
            Kind::Date => 'date',
            Kind::Search => 'search',
            Kind::NotFound => '404',
            default => null,
        };
        if ($type === null || !Runtime::booted()) {
            return $slugs;
        }
        $names = array_map(static fn (string $slug) => $slug . '.php', $slugs);
        $names = (array) \apply_filters("{$type}_template_hierarchy", $names);
        return array_values(array_unique(array_map(static fn ($name) => (string) preg_replace('/\.php$/', '', (string) $name), $names)));
    }

    /** @return list<string> */
    private function hierarchy(Resolution $resolution): array
    {
        $record = $resolution->record ?? [];
        return match ($resolution->kind) {
            Kind::Home => $resolution->postsPage ? ['home', 'index'] : ['front-page', 'home', 'index'],
            Kind::Single => [
                'single-' . ($record['post_type'] ?? 'post') . '-' . ($record['post_name'] ?? ''),
                'single-' . ($record['post_type'] ?? 'post'),
                'single',
                'singular',
                'index',
            ],
            Kind::Page => array_values(array_filter([
                $resolution->front ? 'front-page' : null,
                $this->customTemplate((int) ($record['ID'] ?? 0)),
                'page-' . ($record['post_name'] ?? ''),
                'page-' . (int) ($record['ID'] ?? 0),
                'page',
                'singular',
                'index',
            ])),
            Kind::Category => ['category-' . ($record['slug'] ?? ''), 'category-' . (int) ($record['term_id'] ?? 0), 'category', 'archive', 'index'],
            Kind::Tag => ['tag-' . ($record['slug'] ?? ''), 'tag-' . (int) ($record['term_id'] ?? 0), 'tag', 'archive', 'index'],
            Kind::Taxonomy => ['taxonomy-' . ($record['taxonomy'] ?? '') . '-' . ($record['slug'] ?? ''), 'taxonomy-' . ($record['taxonomy'] ?? ''), 'taxonomy', 'archive', 'index'],
            Kind::PostTypeArchive => ['archive-' . ($record['name'] ?? ''), 'archive', 'index'],
            Kind::Author => ['author-' . ($record['user_nicename'] ?? ''), 'author-' . (int) ($record['ID'] ?? 0), 'author', 'archive', 'index'],
            Kind::Date => ['date', 'archive', 'index'],
            Kind::Search => ['search', 'index'],
            Kind::NotFound => ['404', 'index'],
            Kind::Redirect => ['index'],
        };
    }

    /**
     * The site editor's saved global styles for the active theme, when any.
     * An emptied node is stored as [] and reads as nothing, the way the
     * reference reads it; a list where a map belongs is dropped the same way.
     */
    public function userStyles(): ?array
    {
        $json = $this->saved('wp_global_styles', null);
        $decoded = $json === null ? null : Json::decode($json, or: null);
        if (!is_array($decoded)) {
            return null;
        }
        return array_filter($decoded, static fn ($node) => !is_array($node) || !array_is_list($node));
    }

    /**
     * Every published template, part and global styles post saved for the
     * theme, newest first by type: read in one query once a request.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function savedOfTheme(): array
    {
        return RowCache::remember('theme_templates', $this->theme->slug, function (): array {
            $byType = [];
            foreach ($this->db->rows(
                "SELECT p.ID, p.post_type AS kind, p.post_name, p.post_content FROM {$this->db->table('posts')} p
                 JOIN {$this->db->table('term_relationships')} tr ON tr.object_id = p.ID
                 JOIN {$this->db->table('term_taxonomy')} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
                 JOIN {$this->db->table('terms')} t ON t.term_id = tt.term_id
                 WHERE p.post_type IN ('wp_template', 'wp_template_part', 'wp_global_styles') AND p.post_status = 'publish'
                   AND tt.taxonomy = 'wp_theme' AND t.slug = ? ORDER BY p.ID DESC",
                [$this->theme->slug],
            ) as $row) {
                $byType[(string) $row['kind']][] = $row;
            }
            return $byType;
        });
    }

    /** A page's chosen custom template, from _wp_page_template meta. */
    public function customTemplate(int $pageId): ?string
    {
        if ($pageId === 0) {
            return null;
        }
        $template = $this->posts->meta($pageId, '_wp_page_template');
        return $template === null || $template === '' || $template === 'default' ? null : $template;
    }

    private function saved(string $type, ?string $slug): ?string
    {
        $row = $this->savedRow($type, $slug);
        return $row === null ? null : (string) $row['post_content'];
    }

    /**
     * The newest published template of a type (and slug) saved for the
     * theme. Every saved one of the type is read once a request, newest
     * first, so a template's candidates and its parts cost one query.
     *
     * @return array{ID: int|string, post_content: string}|null
     */
    private function savedRow(string $type, ?string $slug): ?array
    {
        $rows = $this->savedOfTheme()[$type] ?? [];
        foreach ($rows as $row) {
            if ($slug === null || (string) $row['post_name'] === $slug) {
                return ['ID' => $row['ID'], 'post_content' => (string) $row['post_content']];
            }
        }
        return null;
    }
}
