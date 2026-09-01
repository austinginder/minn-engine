<?php

declare(strict_types=1);

namespace Minn\Rest;

/**
 * The engine's registry of built-in post types, seeded from the observed
 * contract (src/data/types.json) with _links attached at runtime.
 */
final class Types
{
    private ?array $types = null;

    /**
     * @param array<string, array<string, mixed>> $declared extra types from active extensions
     */
    public function __construct(
        private readonly RestUrl $url,
        private readonly array $declared = [],
    ) {
    }

    /**
     * Every post type the surface knows, by slug.
     *
     * @return array<string, array> keyed by type slug
     */
    public function all(): array
    {
        if ($this->types === null) {
            $types = (array) json_decode((string) file_get_contents(MINN_ENGINE_DIR . '/data/types.json'), true);
            foreach ($this->declared as $slug => $row) {
                if (isset($types[$slug])) {
                    continue;
                }
                $types[$slug] = [
                    'description' => (string) ($row['description'] ?? ''),
                    'hierarchical' => (bool) ($row['hierarchical'] ?? false),
                    'has_archive' => (bool) ($row['has_archive'] ?? false),
                    'name' => (string) ($row['name'] ?? $slug),
                    'slug' => $slug,
                    'icon' => $row['icon'] ?? null,
                    'taxonomies' => array_values(array_map('strval', (array) ($row['taxonomies'] ?? []))),
                    'rest_base' => (string) ($row['rest_base'] ?? $slug),
                    'rest_namespace' => 'wp/v2',
                    'template' => [],
                    'template_lock' => false,
                ];
            }
            foreach ($types as &$type) {
                $type['_links'] = [
                    'collection' => [['href' => $this->url->to('/wp/v2/types')]],
                    'wp:items' => [['href' => $this->url->to('/wp/v2/' . $type['rest_base'])]],
                    'curies' => RestUrl::curies(),
                ];
            }
            $this->types = $types;
        }
        return $this->types;
    }

    /** One post type by slug, or null. */
    public function find(string $slug): ?array
    {
        return $this->all()[$slug] ?? null;
    }

    /** The type behind a rest_base, or null. */
    public function slugForRestBase(string $base): ?string
    {
        foreach ($this->all() as $slug => $type) {
            if (($type['rest_base'] ?? '') === $base) {
                return $slug;
            }
        }
        return null;
    }

    /** Whether an extension declared this type. */
    public function isDeclared(string $slug): bool
    {
        return isset($this->declared[$slug]);
    }

    /** The rest_base of a type slug. */
    public function restBase(string $slug): string
    {
        if ($slug === 'page') {
            return 'pages';
        }
        if ($slug === 'post') {
            return 'posts';
        }
        return (string) ($this->find($slug)['rest_base'] ?? $slug);
    }
}
