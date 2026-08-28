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

    public function __construct(private readonly RestUrl $url)
    {
    }

    /** @return array<string, array> keyed by type slug */
    public function all(): array
    {
        if ($this->types === null) {
            $types = (array) json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/data/types.json'), true);
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

    public function find(string $slug): ?array
    {
        return $this->all()[$slug] ?? null;
    }
}
