<?php

declare(strict_types=1);

namespace Minn\Theme;

use Minn\Content\Site;
use Minn\Db;
use Minn\Runtime\BlockTemplates;

/**
 * Every block template and template part the site offers, in the order the
 * reference lists them: the rows the site editor saved first (newest post
 * date first), then the theme's own files in the order the directory hands
 * them back, then whatever a plugin registered. A saved row shadows the
 * theme file of the same slug and reports has_theme_file so the file can
 * be restored by deleting the row.
 */
final class TemplateIndex
{
    public const TEMPLATE = 'wp_template';
    public const PART = 'wp_template_part';

    /** @var array<string, array{title: string, description: string}>|null */
    private static ?array $defaults = null;

    public function __construct(
        private readonly Db $db,
        private readonly Theme $theme,
        private readonly Site $site,
        private readonly ?BlockTemplates $registered = null,
    ) {
    }

    /** @return list<TemplateRecord> */
    public function all(string $type): array
    {
        $records = [];
        foreach ($this->savedRows($type) as $row) {
            $records[] = $this->fromRow($row, $type);
        }
        $taken = array_map(static fn (TemplateRecord $record) => $record->slug, $records);
        foreach ($this->theme->fileSlugs($type === self::PART ? 'parts' : 'templates') as $slug) {
            if (!in_array($slug, $taken, true)) {
                $records[] = $this->fromFile($slug, $type);
                $taken[] = $slug;
            }
        }
        foreach ($this->pluginRows($type) as $row) {
            if (!in_array($row['slug'], $taken, true)) {
                $records[] = $this->fromPlugin($row, $type);
                $taken[] = $row['slug'];
            }
        }
        return $records;
    }

    /** Null when the id names another theme, or a slug nothing provides. */
    public function find(string $type, string $id): ?TemplateRecord
    {
        [$theme, $slug] = array_pad(explode('//', $id, 2), 2, '');
        if ($theme !== $this->theme->slug || $slug === '') {
            return null;
        }
        foreach ($this->all($type) as $record) {
            if ($record->slug === $slug) {
                return $record;
            }
        }
        return null;
    }

    public function themeSlug(): string
    {
        return $this->theme->slug;
    }

    public function hasFile(string $type, string $slug): bool
    {
        return ($type === self::PART ? $this->theme->partFile($slug) : $this->theme->templateFile($slug)) !== null;
    }

    /**
     * Who the caller is told made this: the theme's own name for anything
     * that has a file behind it, the plugin for a registered one, the
     * author's display name for a row a person saved, and the site's name
     * when nobody is recorded.
     */
    public function authorText(TemplateRecord $record): string
    {
        return match ($this->originalSource($record)) {
            'theme' => $this->theme->name(),
            'plugin' => (string) $record->plugin,
            'user' => (string) ($this->db->value(
                "SELECT display_name FROM {$this->db->table('users')} WHERE ID = ?",
                [$record->author],
            ) ?? ''),
            default => (string) ($this->site->option('blogname') ?? ''),
        };
    }

    /** theme when a file backs it, plugin when one registered it, user when someone saved it, else site. */
    public function originalSource(TemplateRecord $record): string
    {
        if ($record->hasThemeFile) {
            return 'theme';
        }
        if ($record->source === 'plugin') {
            return 'plugin';
        }
        return $record->author > 0 ? 'user' : 'site';
    }

    /** A slug the reference does not name in its default template types is a custom template. */
    public static function isCustom(string $slug): bool
    {
        return !isset(self::defaults()[$slug]);
    }

    /** @return array<string, array{title: string, description: string}> */
    public static function defaults(): array
    {
        return self::$defaults ??= (array) json_decode(
            (string) file_get_contents(MINN_ENGINE_DIR . '/data/template-types.json'),
            true,
        );
    }

    /**
     * Markup as a caller of the REST route sees it: patterns spliced in
     * where the template only named them, then every template-part block
     * told which theme it belongs to.
     */
    private function markup(string $content): string
    {
        return TemplatePartTheme::apply(TemplatePatterns::expand($content, $this->theme), $this->theme->slug);
    }

    /** @return list<array<string, mixed>> */
    private function savedRows(string $type): array
    {
        return $this->db->rows(
            "SELECT p.* FROM {$this->db->table('posts')} p
             JOIN {$this->db->table('term_relationships')} tr ON tr.object_id = p.ID
             JOIN {$this->db->table('term_taxonomy')} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
             JOIN {$this->db->table('terms')} t ON t.term_id = tt.term_id
             WHERE p.post_type = ? AND p.post_status = 'publish'
               AND tt.taxonomy = 'wp_theme' AND t.slug = ?
             ORDER BY p.post_date DESC, p.ID DESC",
            [$type, $this->theme->slug],
        );
    }

    /** @return list<array<string, mixed>> */
    private function pluginRows(string $type): array
    {
        if ($type !== self::TEMPLATE || $this->registered === null) {
            return [];
        }
        return array_values($this->registered->all());
    }

    /** @param array<string, mixed> $row */
    private function fromRow(array $row, string $type): TemplateRecord
    {
        $slug = (string) $row['post_name'];
        $origin = $this->db->value(
            "SELECT meta_value FROM {$this->db->table('postmeta')} WHERE post_id = ? AND meta_key = 'origin' LIMIT 1",
            [(int) $row['ID']],
        );
        return new TemplateRecord(
            theme: $this->theme->slug,
            slug: $slug,
            type: $type,
            content: $this->markup((string) $row['post_content']),
            title: (string) $row['post_title'],
            description: (string) $row['post_excerpt'],
            source: 'custom',
            origin: $origin === null ? null : (string) $origin,
            hasThemeFile: $this->hasFile($type, $slug),
            author: (int) $row['post_author'],
            modified: str_replace(' ', 'T', (string) $row['post_modified']),
            date: str_replace(' ', 'T', (string) $row['post_date']),
            wpId: (int) $row['ID'],
            status: (string) $row['post_status'],
            area: $type === self::PART ? $this->savedArea((int) $row['ID'], $slug) : null,
        );
    }

    private function fromFile(string $slug, string $type): TemplateRecord
    {
        $content = $type === self::PART ? $this->theme->partFile($slug) : $this->theme->templateFile($slug);
        return new TemplateRecord(
            theme: $this->theme->slug,
            slug: $slug,
            type: $type,
            content: $this->markup((string) $content),
            title: $this->fileTitle($slug, $type),
            description: $type === self::PART ? '' : (string) (self::defaults()[$slug]['description'] ?? ''),
            source: 'theme',
            origin: null,
            hasThemeFile: true,
            author: 0,
            modified: null,
            date: null,
            wpId: 0,
            area: $type === self::PART ? $this->theme->partArea($slug) : null,
        );
    }

    /** @param array<string, mixed> $row */
    private function fromPlugin(array $row, string $type): TemplateRecord
    {
        return new TemplateRecord(
            theme: $this->theme->slug,
            slug: (string) $row['slug'],
            type: $type,
            content: $this->markup((string) $row['content']),
            title: (string) $row['title'],
            description: (string) $row['description'],
            source: 'plugin',
            origin: 'plugin',
            hasThemeFile: false,
            author: 0,
            modified: null,
            date: null,
            wpId: 0,
            plugin: (string) $row['plugin'],
        );
    }

    /**
     * A theme file's title: the default template types name the hierarchy
     * slugs, theme.json names the rest, and a slug neither knows about is
     * its own title, verbatim rather than prettified.
     */
    private function fileTitle(string $slug, string $type): string
    {
        if ($type === self::PART) {
            return $this->theme->partTitle($slug) ?? $slug;
        }
        $declared = $this->theme->customTemplate($slug);
        return (string) (self::defaults()[$slug]['title'] ?? $declared['title'] ?? $slug);
    }

    private function savedArea(int $postId, string $slug): string
    {
        $area = $this->db->value(
            "SELECT t.slug FROM {$this->db->table('terms')} t
             JOIN {$this->db->table('term_taxonomy')} tt ON tt.term_id = t.term_id
             JOIN {$this->db->table('term_relationships')} tr ON tr.term_taxonomy_id = tt.term_taxonomy_id
             WHERE tr.object_id = ? AND tt.taxonomy = 'wp_template_part_area' LIMIT 1",
            [$postId],
        );
        return $area === null ? $this->theme->partArea($slug) : (string) $area;
    }
}
