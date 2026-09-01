<?php

declare(strict_types=1);

namespace Minn\Theme;

use Minn\Content\Posts;
use Minn\Db;
use Minn\Front\Kind;
use Minn\Front\Resolution;
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
        return $this->saved('wp_template_part', $slug) ?? $this->theme->partFile($slug);
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

    /** The site editor's saved global styles for the active theme, when any. */
    public function userStyles(): ?array
    {
        $json = $this->saved('wp_global_styles', null);
        $decoded = $json === null ? null : json_decode($json, true);
        return is_array($decoded) ? $decoded : null;
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
        $row = $this->db->row(
            "SELECT p.post_content FROM {$this->db->table('posts')} p
             JOIN {$this->db->table('term_relationships')} tr ON tr.object_id = p.ID
             JOIN {$this->db->table('term_taxonomy')} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
             JOIN {$this->db->table('terms')} t ON t.term_id = tt.term_id
             WHERE p.post_type = ? AND (? IS NULL OR p.post_name = ?) AND p.post_status = 'publish'
               AND tt.taxonomy = 'wp_theme' AND t.slug = ? ORDER BY p.ID DESC LIMIT 1",
            [$type, $slug, $slug, $this->theme->slug],
        );
        return $row === null ? null : (string) $row['post_content'];
    }
}
