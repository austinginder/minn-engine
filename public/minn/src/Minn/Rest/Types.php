<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Runtime\Runtime;

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
        return Runtime::booted() ? $this->core() + $this->registered() : $this->core();
    }

    /** The built-in types and the ones extensions declare. @return array<string, array> */
    private function core(): array
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

    /**
     * The types plugin code registered to show in REST (probe
     * rest-types-edit), in registration order, as the reference describes
     * them: their label, icon, the taxonomies they show in REST.
     *
     * @return array<string, array>
     */
    private function registered(): array
    {
        $registry = Runtime::registry();
        $out = [];
        foreach ($registry->postTypes() as $slug => $row) {
            if (empty($row['show_in_rest']) || !empty($row['_builtin'])) {
                continue;
            }
            $base = (string) ($row['rest_base'] ?: $slug);
            $taxonomies = [];
            foreach ($registry->taxonomies() as $taxonomy => $taxonomyRow) {
                if (!empty($taxonomyRow['show_in_rest']) && in_array((string) $slug, (array) ($taxonomyRow['object_type'] ?? []), true)) {
                    $taxonomies[] = (string) $taxonomy;
                }
            }
            $out[(string) $slug] = [
                'description' => (string) ($row['description'] ?? ''),
                'hierarchical' => (bool) ($row['hierarchical'] ?? false),
                'has_archive' => $row['has_archive'] ?? false,
                'name' => (string) ($row['label'] ?? $slug),
                'slug' => (string) $slug,
                'icon' => $row['menu_icon'] ?? null,
                'taxonomies' => $taxonomies,
                'rest_base' => $base,
                'rest_namespace' => (string) ($row['rest_namespace'] ?: 'wp/v2'),
                'template' => (array) ($row['template'] ?? []),
                'template_lock' => $row['template_lock'] ?? false,
                '_links' => [
                    'collection' => [['href' => $this->url->to('/wp/v2/types')]],
                    'wp:items' => [['href' => $this->url->to('/' . ($row['rest_namespace'] ?: 'wp/v2') . '/' . $base)]],
                    'curies' => RestUrl::curies(),
                ],
            ];
        }
        return $out;
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

    /** The rest_base of every type an extension declared or plugin code registered under wp/v2. @return list<string> */
    public function declaredBases(): array
    {
        $bases = [];
        foreach (array_keys($this->declared + $this->servedRegistered()) as $slug) {
            $bases[] = $this->restBase((string) $slug);
        }
        return $bases;
    }

    /** Whether the engine serves this type through its {base} routes: an extension declared it, or plugin code registered it to show in REST under wp/v2 (probe rest-plugin-types). */
    public function isDeclared(string $slug): bool
    {
        return isset($this->declared[$slug]) || isset($this->servedRegistered()[$slug]);
    }

    /** Whether plugin code registered the type (not a built-in, not an extension's). */
    public function isRegistered(string $slug): bool
    {
        return Runtime::booted() && isset($this->registered()[$slug]);
    }

    /** @return array<string, array> the registered types under wp/v2 */
    private function servedRegistered(): array
    {
        if (!Runtime::booted()) {
            return [];
        }
        return array_filter($this->registered(), static fn (array $type): bool => $type['rest_namespace'] === 'wp/v2');
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
