<?php

declare(strict_types=1);

namespace Minn\Blocks;

use Minn\Support\Sanitize;

/**
 * The template part block's variations as the reference builds them (probes
 * rest-block-types, tabs-and-parts): one per area, the general and the
 * navigation overlay areas aside (by name),
 * carrying the area's label, description and icon, offered in the inserter
 * only while no part variation belongs to it; and one per template part, in
 * the order the parts are listed, offered in the inserter with the part's
 * slug, theme and area as its attributes and its example.
 */
final class TemplatePartVariations
{
    /**
     * The areas' variations beside the parts' ones.
     *
     * @param list<array<string, mixed>> $areas get_allowed_block_template_part_areas()
     * @param list<array<string, mixed>> $instances the parts' variations
     * @return list<array<string, mixed>>
     */
    public static function areas(array $areas, array $instances): array
    {
        $used = array_flip(array_map(static fn ($instance): string => (string) ($instance['attributes']['area'] ?? ''), $instances));
        $variations = [];
        foreach ($areas as $area) {
            if (in_array($area['area'], ['uncategorized', 'navigation-overlay'], true)) {
                continue;
            }
            $variations[] = ['name' => 'area_' . $area['area'], 'title' => $area['label'], 'description' => $area['description'], 'attributes' => ['area' => $area['area']], 'scope' => isset($used[$area['area']]) ? [] : ['inserter'], 'icon' => $area['icon']];
        }
        return $variations;
    }

    /**
     * One variation per template part.
     *
     * @param list<array<string, mixed>> $areas get_allowed_block_template_part_areas()
     * @param list<object> $parts get_block_templates() for template parts
     * @return list<array<string, mixed>>
     */
    public static function instances(array $areas, array $parts): array
    {
        $icons = array_column($areas, 'icon', 'area');
        $variations = [];
        foreach ($parts as $part) {
            $attributes = ['slug' => (string) $part->slug, 'theme' => (string) $part->theme, 'area' => (string) $part->area];
            // The reference's description here is true (its REST answer reads "1").
            $variations[] = ['name' => 'instance_' . Sanitize::title((string) $part->slug), 'title' => (string) $part->title, 'description' => true, 'attributes' => $attributes, 'scope' => ['inserter'], 'icon' => $icons[$part->area] ?? 'layout', 'example' => ['attributes' => $attributes]];
        }
        return $variations;
    }
}
