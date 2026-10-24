<?php

declare(strict_types=1);

namespace Minn\Runtime;

/**
 * The post types and taxonomies active extensions declare in minn.json
 * ("types", "taxonomies"), registered on init as a plugin registers its
 * own: through the facade's registration, so each takes the same
 * defaults, rewrite rules, archive, template hierarchy, REST shape and
 * admin row a plugin's would. The WordPress layer binds those two
 * registrations as it loads (bind). A change to the declared set drops the stored
 * rewrite rules, as a plugin flushes them when it starts registering a
 * type, so the next request makes them again with the new addresses.
 *
 * A type's row: slug, name (the plural label), singular, rest_base,
 * hierarchical, has_archive (true, or the archive's path), rewrite (a slug,
 * or {slug, with_front}), supports, taxonomies, description, icon. A
 * taxonomy's: slug, name, singular, types (the post types it groups),
 * rest_base, hierarchical, rewrite.
 */
final class DeclaredTypes
{
    /** The declared set the stored rewrite rules were made with, as a hash. */
    private const SETTLED = 'minn_declared_types';

    /** @var array{0?: \Closure, 1?: \Closure} the WordPress layer's register_post_type and register_taxonomy */
    private static array $registrars = [];

    /** What a declared type supports when its row names nothing. */
    private const SUPPORTS = ['title', 'editor', 'author', 'thumbnail', 'excerpt', 'custom-fields', 'comments', 'revisions'];

    /** The WordPress layer's registrations (register_post_type, register_taxonomy), bound as it loads. */
    public static function bind(\Closure $postType, \Closure $taxonomy): void
    {
        self::$registrars = [$postType, $taxonomy];
    }

    /**
     * Registers the declared set on init, at a plugin's priority, and drops
     * the stored rewrite rules when the set changed since they were made.
     *
     * @param array<string, array<string, mixed>> $types slug => row
     * @param array<string, array<string, mixed>> $taxonomies slug => row
     */
    public static function onInit(array $types, array $taxonomies): void
    {
        self::settle($types, $taxonomies);
        if (($types === [] && $taxonomies === []) || self::$registrars === []) {
            return;
        }
        [$registerPostType, $registerTaxonomy] = self::$registrars;
        // Taxonomies first: their rewrite rules then come before the types', so a term's address under a
        // type's prefix (wiki/topic/music/) is read as the term, not as an attachment of an entry "topic".
        Runtime::hooks()->add('init', static function () use ($types, $taxonomies, $registerPostType, $registerTaxonomy): void {
            foreach ($taxonomies as $slug => $row) {
                $registerTaxonomy((string) $slug, array_values(array_map('strval', (array) ($row['types'] ?? []))), self::taxonomyArgs((string) $slug, $row));
            }
            foreach ($types as $slug => $row) {
                $registerPostType((string) $slug, self::typeArgs((string) $slug, $row));
            }
        }, 10, 0);
    }

    /**
     * A type's registration arguments from its row.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function typeArgs(string $slug, array $row): array
    {
        $args = self::common($slug, $row) + [
            'has_archive' => is_string($row['has_archive'] ?? null) ? $row['has_archive'] : (bool) ($row['has_archive'] ?? false),
            'supports' => array_values(array_map('strval', (array) ($row['supports'] ?? self::SUPPORTS))),
            'taxonomies' => array_values(array_map('strval', (array) ($row['taxonomies'] ?? []))),
        ];
        if (isset($row['description'])) {
            $args['description'] = (string) $row['description'];
        }
        if (isset($row['icon'])) {
            $args['menu_icon'] = (string) $row['icon'];
        }
        return $args;
    }

    /**
     * A taxonomy's registration arguments from its row.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function taxonomyArgs(string $slug, array $row): array
    {
        return self::common($slug, $row);
    }

    /**
     * What types and taxonomies share: labels, public and in REST, the base,
     * the hierarchy, and a rewrite when the row names one.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function common(string $slug, array $row): array
    {
        $name = (string) ($row['name'] ?? $slug);
        $args = [
            'label' => $name,
            'labels' => ['name' => $name, 'singular_name' => (string) ($row['singular'] ?? $name)],
            'public' => true,
            'show_in_rest' => true,
            'rest_base' => (string) ($row['rest_base'] ?? $slug),
            'hierarchical' => (bool) ($row['hierarchical'] ?? false),
        ];
        $rewrite = $row['rewrite'] ?? null;
        if (is_string($rewrite) && $rewrite !== '') {
            $args['rewrite'] = ['slug' => trim($rewrite, '/')];
        } elseif (is_array($rewrite)) {
            $args['rewrite'] = $rewrite;
        } elseif ($rewrite === false) {
            $args['rewrite'] = false;
        }
        return $args;
    }

    /**
     * Drops the stored rewrite rules when the declared set is not the one
     * they were made with, and records the new set. An empty set on a site
     * that never declared anything writes nothing.
     *
     * @param array<string, array<string, mixed>> $types
     * @param array<string, array<string, mixed>> $taxonomies
     */
    private static function settle(array $types, array $taxonomies): void
    {
        $hash = $types === [] && $taxonomies === [] ? '' : md5(serialize([$types, $taxonomies]));
        $options = Runtime::options();
        $settled = (string) ($options->get(self::SETTLED) ?? '');
        if ($settled === $hash) {
            return;
        }
        $options->delete('rewrite_rules');
        if ($hash === '') {
            $options->delete(self::SETTLED);
            return;
        }
        $options->update(self::SETTLED, $hash, 'on');
    }
}
