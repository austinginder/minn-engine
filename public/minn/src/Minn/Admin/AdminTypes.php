<?php

declare(strict_types=1);

namespace Minn\Admin;

use Minn\Auth\Capabilities;
use Minn\Rest\Types;

/**
 * Admin-facing type facts (viewable, labels, supports, the edit gate) live
 * beside, not inside, the wp/v2 registry, so the types route's payload
 * stays byte-faithful.
 */
final class AdminTypes
{
    private ?array $extra = null;

    public function __construct(
        private readonly Types $types,
        private readonly Capabilities $capabilities,
    ) {
    }

    public function extra(): array
    {
        if ($this->extra !== null) {
            return $this->extra;
        }
        $extra = (array) json_decode((string) file_get_contents(MINN_ENGINE_DIR . '/data/types-admin.json'), true);
        foreach ($this->types->all() as $slug => $type) {
            if (isset($extra[$slug])) {
                continue;
            }
            $extra[$slug] = [
                'viewable' => true,
                'labels' => ['singular_name' => (string) ($type['name'] ?? $slug)],
                'supports' => ['title' => true, 'editor' => true],
                'edit_cap' => 'edit_posts',
            ];
        }
        return $this->extra = $extra;
    }

    /** The boot-status types section: edit-visible types for this user, slimmed. */
    public function section(int $userId): array
    {
        $extra = $this->extra();
        $out = [];
        foreach ($this->types->all() as $slug => $type) {
            $admin = $extra[$slug] ?? [];
            if (!$this->capabilities->can($userId, $admin['edit_cap'] ?? 'edit_theme_options')) {
                continue;
            }
            $out[] = [
                'slug' => $slug,
                'rest_base' => $type['rest_base'],
                'name' => $type['name'],
                'viewable' => (bool) ($admin['viewable'] ?? false),
                'labels' => ['singular_name' => $admin['labels']['singular_name'] ?? ''],
                'supports' => $admin['supports'] ?? [],
                'hierarchical' => (bool) ($type['hierarchical'] ?? false),
            ];
        }
        return $out;
    }

    /** Whether a UI post type still supports comments. */
    public function commentsEnabled(): bool
    {
        $extra = $this->extra();
        return !empty($extra['post']['supports']['comments']) || !empty($extra['page']['supports']['comments']);
    }
}
