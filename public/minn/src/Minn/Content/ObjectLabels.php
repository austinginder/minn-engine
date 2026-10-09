<?php

declare(strict_types=1);

namespace Minn\Content;

/**
 * A post type's or taxonomy's labels from its own and the defaults
 * (_get_custom_object_labels, probe post-more): the name from its label when
 * unset, the singular from the name, the admin bar from the singular (else
 * the object's name), the menu from the name, all items from the menu,
 * archives from all items; then the defaults (the caller picks the flat or
 * the hierarchical set) under those, in the defaults' order.
 */
final class ObjectLabels
{
    /**
     * The labels, the object's own filled in by the rules above and laid over the defaults.
     *
     * @param array<string, mixed> $labels the object's own
     * @param array<string, mixed> $defaults key => the default for this object
     * @return array<string, mixed>
     */
    public static function build(array $labels, ?string $label, string $name, array $defaults): array
    {
        if ($label !== null && empty($labels['name'])) {
            $labels['name'] = $label;
        }
        if (!isset($labels['singular_name']) && isset($labels['name'])) {
            $labels['singular_name'] = $labels['name'];
        }
        $labels['name_admin_bar'] ??= $labels['singular_name'] ?? $name;
        $labels['menu_name'] ??= $labels['name'] ?? null;
        $labels['all_items'] ??= $labels['menu_name'];
        $labels['archives'] ??= $labels['all_items'];
        return array_merge($defaults, $labels);
    }
}
