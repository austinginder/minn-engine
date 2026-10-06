<?php

declare(strict_types=1);

namespace Minn\Theme;

/**
 * The templates a block theme falls back through for a template slug, as
 * get_template_hierarchy builds them (probe rest-templates-lookup): the
 * slug itself, then its family's chain to index. A single entry's slug
 * reaches single-{type} and single only when the type is registered (and
 * goes straight to singular when not); a taxonomy's reaches taxonomy-{tax}
 * and taxonomy only when the taxonomy is; pages, categories, tags and
 * authors step through their bare name when the slug has more to it. A
 * prefix names the next step outright. A custom template is a page's.
 */
final class TemplateHierarchy
{
    /**
     * A slug's fallback chain, most specific first, ending at index.
     *
     * @param list<string> $types the registered post types
     * @param list<string> $taxonomies the registered taxonomies
     * @return list<string>
     */
    public static function for(string $slug, bool $custom, string $prefix, array $types, array $taxonomies): array
    {
        if ($custom) {
            return ['page', 'singular', 'index'];
        }
        if ($slug === 'index') {
            return ['index'];
        }
        $chain = self::chain($prefix !== '' ? $prefix : $slug, $types, $taxonomies);
        return array_values(array_unique([$slug, ...($prefix !== '' && $prefix !== $slug ? [$prefix] : []), ...$chain]));
    }

    /**
     * What follows a slug (or a prefix) down to index.
     *
     * @param list<string> $types
     * @param list<string> $taxonomies
     * @return list<string>
     */
    private static function chain(string $slug, array $types, array $taxonomies): array
    {
        [$family, $rest] = array_pad(explode('-', $slug, 2), 2, '');
        return match (true) {
            $slug === 'front-page' => ['home', 'index'],
            $slug === 'attachment' => ['single', 'singular', 'index'],
            in_array($slug, ['home', 'singular', 'search', '404', 'embed', 'archive'], true) => ['index'],
            $slug === 'date' => ['archive', 'index'],
            $family === 'single' => self::single($rest, $types),
            $family === 'taxonomy' => self::taxonomy($rest, $taxonomies),
            in_array($family, ['category', 'tag', 'author'], true) => [...($rest !== '' ? [$family] : []), 'archive', 'index'],
            $family === 'page' => [...($rest !== '' ? ['page'] : []), 'singular', 'index'],
            str_starts_with($slug, 'page') => ['singular', 'index'],
            $family === 'archive' => ['archive', 'index'],
            default => ['index'],
        };
    }

    /** @param list<string> $types @return list<string> */
    private static function single(string $rest, array $types): array
    {
        if ($rest === '') {
            return ['singular', 'index'];
        }
        $type = self::registered($rest, $types);
        return $type === null ? ['singular', 'index'] : ['single-' . $type, 'single', 'singular', 'index'];
    }

    /** @param list<string> $taxonomies @return list<string> */
    private static function taxonomy(string $rest, array $taxonomies): array
    {
        $taxonomy = self::registered($rest, $taxonomies);
        return $taxonomy === null ? ['archive', 'index'] : ['taxonomy-' . $taxonomy, 'taxonomy', 'archive', 'index'];
    }

    /** The registered name a slug's remainder starts with (the whole of it, or before a hyphen), the longest first. @param list<string> $names */
    private static function registered(string $rest, array $names): ?string
    {
        usort($names, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
        foreach ($names as $name) {
            if ($rest === $name || str_starts_with($rest, $name . '-')) {
                return $name;
            }
        }
        return null;
    }
}
