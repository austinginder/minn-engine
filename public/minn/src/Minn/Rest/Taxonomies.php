<?php

declare(strict_types=1);

namespace Minn\Rest;

/**
 * The taxonomy registry the wp/v2 surface describes: the core set seeded
 * from the reference (data/taxonomies.json) with the reference's own
 * labels, capabilities, and visibility flags.
 */
final class Taxonomies
{
    private ?array $taxonomies = null;

    public function __construct(private readonly RestUrl $url)
    {
    }

    /** @return array<string, array> keyed by taxonomy slug */
    public function all(): array
    {
        if ($this->taxonomies === null) {
            $taxonomies = (array) json_decode((string) file_get_contents(MINN_ENGINE_DIR . '/data/taxonomies.json'), true);
            foreach ($taxonomies as &$taxonomy) {
                $taxonomy['_links'] = [
                    'collection' => [['href' => $this->url->to('/wp/v2/taxonomies')]],
                    'wp:items' => [['href' => $this->url->to('/wp/v2/' . $taxonomy['rest_base'])]],
                    'curies' => RestUrl::curies(),
                ];
            }
            $this->taxonomies = $taxonomies;
        }
        return $this->taxonomies;
    }

    public function find(string $slug): ?array
    {
        return $this->all()[$slug] ?? null;
    }

    /** The taxonomies attached to a post type, in registry order. @return array<string, array> */
    public function forType(string $type): array
    {
        return array_filter($this->all(), static fn (array $taxonomy) => in_array($type, $taxonomy['types'], true));
    }

    /** The reference's view-context projection: the keys an anonymous reader sees. */
    public static function view(array $taxonomy): array
    {
        $keep = ['name', 'slug', 'description', 'types', 'hierarchical', 'rest_base', 'rest_namespace', '_links'];
        return array_intersect_key($taxonomy, array_flip($keep));
    }
}
