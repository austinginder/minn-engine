<?php

declare(strict_types=1);

namespace Minn\Runtime;

/**
 * Block templates plugins register at runtime, by their namespaced name
 * ("plugin//slug"). A theme file or a saved template of the same slug
 * wins; otherwise the registered content renders for that slug.
 */
final class BlockTemplates
{
    /** @var array<string, array<string, mixed>> */
    private array $templates = [];

    /** The registered row, or the refusal code the reference reports. */
    public function register(string $name, array $args): array|string
    {
        if (!str_contains($name, '//')) {
            return 'template_no_prefix';
        }
        if (preg_match('/[A-Z]+/', $name)) {
            return 'template_name_no_uppercase';
        }
        if (isset($this->templates[$name])) {
            return 'template_already_registered';
        }
        [$plugin, $slug] = explode('//', $name, 2);
        $row = [
            'name' => $name,
            'plugin' => $plugin,
            'slug' => $slug,
            'title' => (string) ($args['title'] ?? ''),
            'description' => (string) ($args['description'] ?? ''),
            'content' => (string) ($args['content'] ?? ''),
            'post_types' => isset($args['post_types']) ? array_values(array_map('strval', (array) $args['post_types'])) : null,
        ];
        $this->templates[$name] = $row;
        return $row;
    }

    public function unregister(string $name): ?array
    {
        $row = $this->templates[$name] ?? null;
        unset($this->templates[$name]);
        return $row;
    }

    /** @return array<string, array<string, mixed>> by registered name */
    public function all(): array
    {
        return $this->templates;
    }

    public function get(string $name): ?array
    {
        return $this->templates[$name] ?? null;
    }

    public function bySlug(string $slug): ?array
    {
        foreach ($this->templates as $row) {
            if ($row['slug'] === $slug) {
                return $row;
            }
        }
        return null;
    }
}
