<?php

declare(strict_types=1);

namespace Minn\Admin;

use Minn\Runtime\Registered;

/**
 * What the overview counts as writing: posts, pages and the public custom
 * types shown in REST, as post type => REST base. Stores and page builders
 * keep their own screens and stay out, as does a type whose REST base is a
 * nested path. A plugin's types count once plugins are loaded.
 */
final class OverviewTypes
{
    private const LEFT_OUT = ['attachment', 'product', 'product_variation', 'shop_coupon', 'shop_subscription', 'elementor_library', 'e-floating-buttons', 'e-landing-page', 'jet-theme-core', 'jet-engine', 'jet-menu'];

    /**
     * The types, post and page first.
     *
     * @return array<string, string> post type => REST base
     */
    public static function all(): array
    {
        $out = ['post' => 'posts', 'page' => 'pages'];
        foreach (Registered::loadedPostTypes(['public' => true, 'show_in_rest' => true, '_builtin' => false]) as $type) {
            $base = (string) ($type->rest_base ?: $type->name);
            if (!in_array($type->name, self::LEFT_OUT, true) && !str_contains($base, '/')) {
                $out[(string) $type->name] = $base;
            }
        }
        return $out;
    }

    /** The capability that lets a caller see everyone's records of a type, not only their own. */
    public static function othersCap(string $type): string
    {
        $object = Registered::loadedPostTypes(['name' => $type])[$type] ?? null;
        return $object !== null ? (string) $object->cap->edit_others_posts : ($type === 'page' ? 'edit_others_pages' : 'edit_others_posts');
    }

    /**
     * The types as SQL placeholders and their values.
     *
     * @return array{0: string, 1: list<string>}
     */
    public static function in(array $types): array
    {
        return [implode(',', array_fill(0, count($types), '?')), array_keys($types)];
    }
}
