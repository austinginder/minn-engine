<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Http\Args;
use Minn\Http\RouteParams;
use Minn\Runtime\Runtime;

/**
 * A post type's collection parameters as the reference builds them for its
 * list (probe rest-post-lists): the shared four, dates, authors when the
 * type supports them, ids, menu order and its orderby for page attributes,
 * parents for a hierarchical type, search columns and semantics, slugs,
 * statuses (every registered one, and any), each REST taxonomy's term
 * filter (a list, or a query with children for a hierarchical one and an
 * operator for the include side) under a relation, stickies for posts, and
 * formats for a type with post formats. Plugins change them through
 * rest_{type}_collection_params, asked once a request.
 */
final class PostCollectionParams implements RouteParams
{
    private const FORMATS = ['standard', 'aside', 'chat', 'gallery', 'link', 'image', 'quote', 'status', 'video', 'audio'];
    private const STATUSES = ['publish', 'future', 'draft', 'pending', 'private', 'trash', 'auto-draft', 'inherit', 'request-pending', 'request-confirmed', 'request-failed', 'request-completed'];
    private const IDS = ['type' => 'array', 'items' => ['type' => 'integer'], 'default' => []];

    /** The parameters of the list a {base} capture names; none for a base no REST post type has. */
    public static function for(array $captures): array
    {
        return self::forType(self::type((string) ($captures['base'] ?? '')));
    }

    /** The parameters of a post type's list, by the type's name. @return array<string, array<string, mixed>> */
    public static function ofType(string $name): array
    {
        $object = Runtime::booted() ? \get_post_type_object($name) : null;
        $base = $object instanceof \WP_Post_Type ? (string) ($object->rest_base ?: $name) : ['post' => 'posts', 'page' => 'pages'][$name] ?? '';
        return self::forType(self::type($base));
    }

    /**
     * A post type's list parameters asked afresh, as the reference's list
     * asks for them each time it runs: rest_{type}_collection_params runs
     * again.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function listed(string $name): array
    {
        $object = Runtime::booted() ? \get_post_type_object($name) : null;
        $base = $object instanceof \WP_Post_Type ? (string) ($object->rest_base ?: $name) : ['post' => 'posts', 'page' => 'pages'][$name] ?? '';
        $type = self::type($base);
        return $type === null ? [] : self::filtered($type);
    }

    /** @param array{name: string, object: ?\WP_Post_Type, author: bool, attributes: bool, hierarchical: bool, formats: bool, taxonomies: array<string, bool>}|null $type @return array<string, array<string, mixed>> */
    private static function forType(?array $type): array
    {
        if ($type === null) {
            return [];
        }
        $key = 'rest_collection_params:' . $type['name'];
        $cached = Runtime::booted() ? Runtime::current()->get($key) : null;
        if (is_array($cached)) {
            return $cached;
        }
        $params = self::filtered($type);
        if (Runtime::booted()) {
            Runtime::current()->set($key, $params);
        }
        return $params;
    }

    /** The parameters built, through rest_{type}_collection_params, each marked optional. @param array{name: string, object: ?\WP_Post_Type, author: bool, attributes: bool, hierarchical: bool, formats: bool, taxonomies: array<string, bool>} $type @return array<string, array<string, mixed>> */
    private static function filtered(array $type): array
    {
        $params = self::build($type);
        if (Runtime::booted()) {
            $params = (array) \apply_filters("rest_{$type['name']}_collection_params", $params, $type['object']);
        }
        $params = array_map(static fn ($arg): array => (array) $arg + ['required' => false], $params);
        if (isset($params['status'])) {
            $params['status'][Args::HANDLER_VALIDATES] = true;
        }
        return $params;
    }

    /**
     * The type a REST base names, with what its parameters depend on. Posts
     * and pages are known before plugins load; other types once they have.
     *
     * @return array{name: string, object: ?\WP_Post_Type, author: bool, attributes: bool, hierarchical: bool, formats: bool, taxonomies: array<string, bool>}|null
     */
    private static function type(string $base): ?array
    {
        if (!Runtime::booted()) {
            return match ($base) {
                'posts' => ['name' => 'post', 'object' => null, 'author' => true, 'attributes' => false, 'hierarchical' => false, 'formats' => true, 'taxonomies' => ['categories' => true, 'tags' => false]],
                'pages' => ['name' => 'page', 'object' => null, 'author' => true, 'attributes' => true, 'hierarchical' => true, 'formats' => false, 'taxonomies' => []],
                default => null,
            };
        }
        foreach (\get_post_types(['show_in_rest' => true], 'objects') as $name => $object) {
            if (($object->rest_namespace ?: 'wp/v2') === 'wp/v2' && ($object->rest_base ?: $name) === $base) {
                $taxonomies = [];
                foreach (\get_object_taxonomies($name, 'objects') as $taxonomy) {
                    if (!empty($taxonomy->show_in_rest)) {
                        $taxonomies[(string) ($taxonomy->rest_base ?: $taxonomy->name)] = (bool) $taxonomy->hierarchical;
                    }
                }
                return ['name' => (string) $name, 'object' => $object, 'author' => \post_type_supports($name, 'author'), 'attributes' => $name === 'page' || \post_type_supports($name, 'page-attributes'), 'hierarchical' => (bool) $object->hierarchical || $name === 'attachment', 'formats' => \post_type_supports($name, 'post-formats'), 'taxonomies' => $taxonomies];
            }
        }
        return null;
    }

    /** @param array{name: string, author: bool, attributes: bool, hierarchical: bool, formats: bool, taxonomies: array<string, bool>} $type @return array<string, array<string, mixed>> */
    private static function build(array $type): array
    {
        $date = static fn (string $what): array => ['description' => "Limit response to posts {$what} a given ISO8601 compliant date.", 'type' => 'string', 'format' => 'date-time'];
        $params = [
            'context' => ['description' => 'Scope under which the request is made; determines fields present in response.', 'type' => 'string', 'enum' => ['view', 'embed', 'edit'], 'default' => 'view'],
            'page' => ['description' => 'Current page of the collection.', 'type' => 'integer', 'default' => 1, 'minimum' => 1],
            'per_page' => ['description' => 'Maximum number of items to be returned in result set.', 'type' => 'integer', 'default' => 10, 'minimum' => 1, 'maximum' => 100],
            'search' => ['description' => 'Limit results to those matching a string.', 'type' => 'string'],
            'after' => $date('published after'),
            'modified_after' => $date('modified after'),
        ];
        if ($type['author']) {
            $params['author'] = ['description' => 'Limit result set to posts assigned to specific authors.'] + self::IDS;
            $params['author_exclude'] = ['description' => 'Ensure result set excludes posts assigned to specific authors.'] + self::IDS;
        }
        $params += [
            'before' => $date('published before'),
            'modified_before' => $date('modified before'),
            'exclude' => ['description' => 'Ensure result set excludes specific IDs.'] + self::IDS,
            'include' => ['description' => 'Limit result set to specific IDs.'] + self::IDS,
        ];
        $menuOrder = $type['attributes'] ? 'menu_order' : null;
        if ($menuOrder !== null) {
            $params += [$menuOrder => ['description' => 'Limit result set to posts with a specific menu_order value.', 'type' => 'integer']];
        }
        $orderby = array_values(array_filter(['author', 'date', 'id', 'include', 'modified', 'parent', 'relevance', 'slug', 'include_slugs', 'title', $menuOrder]));
        $params += [
            'search_semantics' => ['description' => 'How to interpret the search input.', 'type' => 'string', 'enum' => ['exact']],
            'offset' => ['description' => 'Offset the result set by a specific number of items.', 'type' => 'integer'],
            'order' => ['description' => 'Order sort attribute ascending or descending.', 'type' => 'string', 'default' => 'desc', 'enum' => ['asc', 'desc']],
            'orderby' => ['description' => 'Sort collection by post attribute.', 'type' => 'string', 'default' => 'date', 'enum' => $orderby],
        ];
        if ($type['hierarchical']) {
            $params['parent'] = ['description' => 'Limit result set to items with particular parent IDs.'] + self::IDS;
            $params['parent_exclude'] = ['description' => 'Limit result set to all items except those of a particular parent ID.'] + self::IDS;
        }
        $params += [
            'search_columns' => ['default' => [], 'description' => 'Array of column names to be searched.', 'type' => 'array', 'items' => ['enum' => ['post_title', 'post_content', 'post_excerpt'], 'type' => 'string']],
            'slug' => ['description' => 'Limit result set to posts with one or more specific slugs.', 'type' => 'array', 'items' => ['type' => 'string']],
            'status' => $type['name'] === 'attachment'
                ? ['default' => 'inherit', 'description' => 'Limit result set to posts assigned one or more statuses.', 'type' => 'array', 'items' => ['enum' => ['inherit', 'private', 'trash'], 'type' => 'string']]
                : ['default' => 'publish', 'description' => 'Limit result set to posts assigned one or more statuses.', 'type' => 'array', 'items' => ['enum' => [...self::statuses(), 'any'], 'type' => 'string']],
        ];
        $params += self::taxonomies($type['taxonomies']);
        if ($type['name'] === 'attachment') {
            $params['media_type'] = ['default' => null, 'description' => 'Limit result set to attachments of a particular media type or media types.', 'type' => 'array', 'items' => ['type' => 'string', 'enum' => array_keys(self::mediaTypes())]];
            $params['mime_type'] = ['default' => null, 'description' => 'Limit result set to attachments of a particular MIME type or MIME types.', 'type' => 'array', 'items' => ['type' => 'string']];
        }
        if ($type['name'] === 'post') {
            $params['sticky'] = ['description' => 'Limit result set to items that are sticky.', 'type' => 'boolean'];
            $params['ignore_sticky'] = ['description' => 'Whether to ignore sticky posts or not.', 'type' => 'boolean', 'default' => true];
        }
        if ($type['formats']) {
            $params['format'] = ['description' => 'Limit result set to items assigned one or more given formats.', 'type' => 'array', 'uniqueItems' => true, 'items' => ['enum' => self::FORMATS, 'type' => 'string']];
        }
        return $params;
    }

    /**
     * The allowed MIME types by media type (the part before the slash), in
     * the order the site allows them, each once.
     *
     * @return array<string, list<string>>
     */
    public static function mediaTypes(): array
    {
        $types = [];
        foreach (Runtime::booted() ? \get_allowed_mime_types() : ['jpg|jpeg|jpe' => 'image/jpeg'] as $mime) {
            $types[strtok((string) $mime, '/')][(string) $mime] = (string) $mime;
        }
        // Each MIME type once, where it first appears (two extensions can share one).
        return array_map('array_values', $types);
    }

    /** @return list<string> */
    private static function statuses(): array
    {
        return Runtime::booted() ? array_values(array_map('strval', array_keys(\get_post_stati()))) : self::STATUSES;
    }

    /**
     * The term filters: a relation, then each taxonomy's include and exclude.
     *
     * @param array<string, bool> $taxonomies REST base => hierarchical
     * @return array<string, array<string, mixed>>
     */
    private static function taxonomies(array $taxonomies): array
    {
        if ($taxonomies === []) {
            return [];
        }
        $params = ['tax_relation' => ['description' => 'Limit result set based on relationship between multiple taxonomies.', 'type' => 'string', 'enum' => ['AND', 'OR']]];
        foreach ($taxonomies as $base => $hierarchical) {
            $children = $hierarchical ? ['include_children' => ['description' => 'Whether to include child terms in the terms limiting the result set.', 'type' => 'boolean', 'default' => false]] : [];
            $operator = ['operator' => ['description' => 'Whether items must be assigned all or any of the specified terms.', 'type' => 'string', 'enum' => ['AND', 'OR'], 'default' => 'OR']];
            $params[$base] = self::termFilter("Limit result set to items with specific terms assigned in the {$base} taxonomy.", $children + $operator);
            $params["{$base}_exclude"] = self::termFilter("Limit result set to items except those with specific terms assigned in the {$base} taxonomy.", $children);
        }
        return $params;
    }

    /** One term filter: an id list, or a query object with these properties beside its terms. @param array<string, array<string, mixed>> $properties @return array<string, mixed> */
    private static function termFilter(string $description, array $properties): array
    {
        return [
            'description' => $description,
            'type' => ['object', 'array'],
            'oneOf' => [
                ['title' => 'Term ID List', 'description' => 'Match terms with the listed IDs.', 'type' => 'array', 'items' => ['type' => 'integer']],
                ['title' => 'Term ID Taxonomy Query', 'description' => 'Perform an advanced term query.', 'type' => 'object', 'properties' => ['terms' => ['description' => 'Term IDs.', 'type' => 'array', 'items' => ['type' => 'integer'], 'default' => []]] + $properties, 'additionalProperties' => false],
            ],
        ];
    }
}
