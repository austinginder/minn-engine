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
        return array_merge($defaults, self::derive($labels, $label, $name));
    }

    /**
     * The object's own labels with what the rules derive added, each only
     * when what it comes from is there (probe post-doubts): what the
     * reference writes back onto the object.
     *
     * @param array<string, mixed> $labels the object's own
     * @return array<string, mixed>
     */
    public static function derive(array $labels, ?string $label, string $name): array
    {
        if ($label !== null && empty($labels['name'])) {
            $labels['name'] = $label;
        }
        if (!isset($labels['singular_name']) && isset($labels['name'])) {
            $labels['singular_name'] = $labels['name'];
        }
        $labels['name_admin_bar'] ??= $labels['singular_name'] ?? $name;
        foreach (['menu_name' => 'name', 'all_items' => 'menu_name', 'archives' => 'all_items'] as $key => $from) {
            if (!isset($labels[$key]) && isset($labels[$from])) {
                $labels[$key] = $labels[$from];
            }
        }
        return $labels;
    }
}
