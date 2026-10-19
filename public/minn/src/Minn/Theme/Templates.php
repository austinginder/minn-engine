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
use Minn\Runtime\Handoff;
use Minn\Runtime\Runtime;
use Minn\Runtime\TermLookup;

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
        foreach ($this->groups($resolution) as $type => $slugs) {
            $found = $this->found($this->filtered((string) $type, $slugs));
            if ($found !== null) {
                return $found;
            }
        }
        return null;
    }

    /**
     * The first of these slugs a template answers, in their order. With the runtime up the answer is the
     * reference's: the templates get_block_templates finds for the slugs (its pre and result filters let a plugin
     * add one or change one, as The Events Calendar and WooCommerce do).
     *
     * @param list<string> $slugs
     * @return array{slug: string, markup: string}|null
     */
    private function found(array $slugs): ?array
    {
        if (!Runtime::booted()) {
            foreach ($slugs as $slug) {
                $markup = $this->template($slug);
                if ($markup !== null) {
                    return ['slug' => $slug, 'markup' => $markup];
                }
            }
            return null;
        }
        $found = [];
        foreach ((array) Handoff::blockTemplates(['slug__in' => $slugs], 'wp_template') as $template) {
            if (is_object($template) && isset($template->slug, $template->content)) {
                $found[(string) $template->slug] ??= (string) $template->content;
            }
        }
        foreach ($slugs as $slug) {
            if (isset($found[$slug])) {
                return ['slug' => $slug, 'markup' => $found[$slug]];
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
     * Plugins reshape a template type's hierarchy through the reference's
     * "{type}_template_hierarchy" filters (WooCommerce sends its taxonomies to
     * archive-product that way, The Events Calendar its archive to its own);
     * the filter sees PHP file names and hands back the same, which become
     * slugs again.
     *
     * @param list<string> $slugs
     * @return list<string>
     */
    private function filtered(string $type, array $slugs): array
    {
        if (!Runtime::booted()) {
            return $slugs;
        }
        $names = array_map(static fn (string $slug) => $slug . '.php', $slugs);
        $names = (array) Runtime::hooks()->filter("{$type}_template_hierarchy", [$names]);
        return array_values(array_unique(array_map(static fn ($name) => (string) preg_replace('/\.php$/', '', (string) $name), $names)));
    }

    /**
     * The block-theme template hierarchy for each kind of resolution, as the
     * reference's template loader walks it: a template type at a time (the
     * front page, the kind's own, then singular or archive, then index), each
     * with its own slugs, until one has a template.
     *
     * @return array<string, list<string>> slugs by template type
     */
    private function groups(Resolution $resolution): array
    {
        $record = $resolution->record ?? [];
        $groups = match ($resolution->kind) {
            Kind::Home => $resolution->postsPage ? ['home' => ['home', 'index']] : ['frontpage' => ['front-page'], 'home' => ['home', 'index']],
            Kind::Single => [
                'single' => ['single-' . ($record['post_type'] ?? 'post') . '-' . ($record['post_name'] ?? ''), 'single-' . ($record['post_type'] ?? 'post'), 'single'],
                'singular' => ['singular'],
            ],
            Kind::Page => ($resolution->front ? ['frontpage' => ['front-page']] : []) + [
                'page' => array_values(array_filter([
                    $this->customTemplate((int) ($record['ID'] ?? 0)),
                    'page-' . ($record['post_name'] ?? ''),
                    'page-' . (int) ($record['ID'] ?? 0),
                    'page',
                ])),
                'singular' => ['singular'],
            ],
            Kind::Category => ['category' => ['category-' . ($record['slug'] ?? ''), 'category-' . (int) ($record['term_id'] ?? 0), 'category'], 'archive' => ['archive']],
            Kind::Tag => ['tag' => ['tag-' . ($record['slug'] ?? ''), 'tag-' . (int) ($record['term_id'] ?? 0), 'tag'], 'archive' => ['archive']],
            Kind::Taxonomy => ['taxonomy' => ['taxonomy-' . ($record['taxonomy'] ?? '') . '-' . ($record['slug'] ?? ''), 'taxonomy-' . ($record['taxonomy'] ?? ''), 'taxonomy'], 'archive' => ['archive']],
            Kind::PostTypeArchive => ['archive' => ['archive-' . ($record['name'] ?? ''), 'archive']],
            Kind::Author => ['author' => ['author-' . ($record['user_nicename'] ?? ''), 'author-' . (int) ($record['ID'] ?? 0), 'author'], 'archive' => ['archive']],
            Kind::Date => ['date' => ['date'], 'archive' => ['archive']],
            Kind::Search => ['search' => ['search']],
            Kind::NotFound => ['404' => ['404']],
            Kind::Redirect => [],
        };
        return $groups + ['index' => ['index']];
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
     * theme, newest first by type: read in one query once a request, with
     * the theme's own wp_theme term (by name, as the reference finds it), so
     * the term lookups the template queries make need no query of their own.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function savedOfTheme(): array
    {
        return RowCache::remember('theme_templates', $this->theme->slug, function (): array {
            $byType = [];
            $terms = [];
            foreach ($this->db->rows(
                "SELECT t.term_id, t.name, t.slug, t.term_group, tt.term_taxonomy_id, tt.taxonomy, tt.description, tt.parent, tt.count,
                        p.ID, p.post_type AS kind, p.post_name, p.post_content
                 FROM {$this->db->table('terms')} t
                 JOIN {$this->db->table('term_taxonomy')} tt ON tt.term_id = t.term_id AND tt.taxonomy = 'wp_theme'
                 LEFT JOIN {$this->db->table('term_relationships')} tr ON tr.term_taxonomy_id = tt.term_taxonomy_id
                 LEFT JOIN {$this->db->table('posts')} p ON p.ID = tr.object_id
                   AND p.post_type IN ('wp_template', 'wp_template_part', 'wp_global_styles') AND p.post_status = 'publish'
                 WHERE t.name = ? ORDER BY p.ID DESC",
                [$this->theme->slug],
            ) as $row) {
                $terms[(int) $row['term_id']] = array_intersect_key($row, array_flip(['term_id', 'name', 'slug', 'term_group', 'term_taxonomy_id', 'taxonomy', 'description', 'parent', 'count']));
                if ($row['ID'] !== null) {
                    $byType[(string) $row['kind']][] = array_intersect_key($row, array_flip(['ID', 'kind', 'post_name', 'post_content']));
                }
            }
            // A name two theme terms share is left to the database, which picks between them.
            if (count($terms) < 2) {
                TermLookup::knownByName('wp_theme', $this->theme->slug, $terms === [] ? null : reset($terms));
            }
            return $byType;
        });
    }

    /** Has the theme's saved templates (and its term) read, if they are not yet: the template-part lookup answers from them. */
    public function load(): void
    {
        $this->savedOfTheme();
    }

    /**
     * The ids the template-part lookup finds for a slug: the newest saved
     * part of the theme by that name, or none.
     *
     * @return list<int>
     */
    public function savedPartIds(string $slug): array
    {
        $row = $this->savedRow('wp_template_part', $slug);
        return $row === null ? [] : [(int) $row['ID']];
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
