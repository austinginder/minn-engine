<?php

declare(strict_types=1);

namespace Minn\Blocks;

/**
 * The template part block's variations as the reference builds them (probe
 * rest-block-types): one per area some part belongs to, the general area
 * aside, carrying the area's label, description and icon; then one per
 * template part, in the order the parts are listed, offered in the
 * inserter with the part's slug, theme and area as its attributes and its
 * example.
 */
final class TemplatePartVariations
{
    /**
     * The variations: the areas' first, then the parts'.
     *
     * @param list<array<string, mixed>> $areas get_allowed_block_template_part_areas()
     * @param list<object> $parts get_block_templates() for template parts
     * @return list<array<string, mixed>>
     */
    public static function build(array $areas, array $parts): array
    {
        $icons = array_column($areas, 'icon', 'area');
        $used = array_flip(array_map(static fn ($part) => (string) $part->area, $parts));
        $variations = [];
        foreach ($areas as $area) {
            if ($area['area'] === 'uncategorized' || !isset($used[$area['area']])) {
                continue;
            }
            $variations[] = ['name' => 'area_' . $area['area'], 'title' => $area['label'], 'description' => $area['description'], 'attributes' => ['area' => $area['area']], 'scope' => [], 'icon' => $area['icon']];
        }
        foreach ($parts as $part) {
            $attributes = ['slug' => (string) $part->slug, 'theme' => (string) $part->theme, 'area' => (string) $part->area];
            // The reference's description here is true (its REST answer reads "1").
            $variations[] = ['name' => 'instance_' . \sanitize_title((string) $part->slug), 'title' => (string) $part->title, 'description' => true, 'attributes' => $attributes, 'scope' => ['inserter'], 'icon' => $icons[$part->area] ?? 'layout', 'example' => ['attributes' => $attributes]];
        }
        return $variations;
    }
}
