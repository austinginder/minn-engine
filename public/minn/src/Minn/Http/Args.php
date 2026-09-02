<?php

declare(strict_types=1);

namespace Minn\Http;

/**
 * The parameters a route accepts, as the reference describes them in the
 * REST index: name => {description, type, ...}. The descriptions and the
 * enums were captured from the reference, so a client that reads either
 * index is told the same thing.
 *
 * A route declares only what it really reads. An argument published here
 * that the handler ignores would be worse than none at all, because a
 * client reading the index would build a request around it, so each set
 * below names the code that consumes it.
 */
final class Args
{
    /** Read by Rest\Context::of(): which view of a resource is wanted. */
    public const CONTEXT = [
        'context' => [
            'description' => 'Scope under which the request is made; determines fields present in response.',
            'type' => 'string',
            'enum' => ['view', 'embed', 'edit'],
            'default' => 'view',
            'required' => false,
        ],
    ];

    /** Read by Rest\Fields::fromQuery(): the subset of fields to return. */
    public const FIELDS = [
        '_fields' => [
            'description' => 'Limit response to specific fields.',
            'type' => 'array',
            'items' => ['type' => 'string'],
            'required' => false,
        ],
    ];

    /** Read by Rest\Embed: whether linked resources are embedded in the response. */
    public const EMBED = [
        '_embed' => [
            'description' => 'Embed the resources linked to the response.',
            'type' => 'string',
            'required' => false,
        ],
    ];

    /** Read by Rest\ListQuery::fromRequest(): how a collection is paged, narrowed and ordered. */
    public const LISTING = [
        'page' => [
            'description' => 'Current page of the collection.',
            'type' => 'integer',
            'default' => 1,
            'minimum' => 1,
            'required' => false,
        ],
        'per_page' => [
            'description' => 'Maximum number of items to be returned in result set.',
            'type' => 'integer',
            'default' => 10,
            'minimum' => 1,
            'maximum' => 100,
            'required' => false,
        ],
        'search' => [
            'description' => 'Limit results to those matching a string.',
            'type' => 'string',
            'required' => false,
        ],
        'include' => [
            'description' => 'Limit result set to specific IDs.',
            'type' => 'array',
            'items' => ['type' => 'integer'],
            'default' => [],
            'required' => false,
        ],
        'exclude' => [
            'description' => 'Ensure result set excludes specific IDs.',
            'type' => 'array',
            'items' => ['type' => 'integer'],
            'default' => [],
            'required' => false,
        ],
        'author' => [
            'description' => 'Limit result set to posts assigned to specific authors.',
            'type' => 'array',
            'items' => ['type' => 'integer'],
            'default' => [],
            'required' => false,
        ],
        'author_exclude' => [
            'description' => 'Ensure result set excludes posts assigned to specific authors.',
            'type' => 'array',
            'items' => ['type' => 'integer'],
            'default' => [],
            'required' => false,
        ],
        'parent' => [
            'description' => 'Limit result set to items with particular parent IDs.',
            'type' => 'array',
            'items' => ['type' => 'integer'],
            'default' => [],
            'required' => false,
        ],
        'parent_exclude' => [
            'description' => 'Limit result set to all items except those of a particular parent ID.',
            'type' => 'array',
            'items' => ['type' => 'integer'],
            'default' => [],
            'required' => false,
        ],
        'slug' => [
            'description' => 'Limit result set to posts with one or more specific slugs.',
            'type' => 'array',
            'items' => ['type' => 'string'],
            'required' => false,
        ],
        'order' => [
            'description' => 'Order sort attribute ascending or descending.',
            'type' => 'string',
            'default' => 'desc',
            'enum' => ['asc', 'desc'],
            'required' => false,
        ],
        'orderby' => [
            'description' => 'Sort collection by post attribute.',
            'type' => 'string',
            'default' => 'date',
            'enum' => ['author', 'date', 'id', 'include', 'modified', 'parent', 'relevance', 'slug', 'include_slugs', 'title'],
            'required' => false,
        ],
    ];

    /** Read by Rest\ListQuery::termFilters(): the taxonomy narrowings a post collection takes. */
    public const TERMS = [
        'categories' => [
            'description' => 'Limit result set to items with specific terms assigned in the categories taxonomy.',
            'type' => 'array',
            'items' => ['type' => 'integer'],
            'required' => false,
        ],
        'categories_exclude' => [
            'description' => 'Limit result set to items except those with specific terms assigned in the categories taxonomy.',
            'type' => 'array',
            'items' => ['type' => 'integer'],
            'required' => false,
        ],
        'tags' => [
            'description' => 'Limit result set to items with specific terms assigned in the tags taxonomy.',
            'type' => 'array',
            'items' => ['type' => 'integer'],
            'required' => false,
        ],
        'tags_exclude' => [
            'description' => 'Limit result set to items except those with specific terms assigned in the tags taxonomy.',
            'type' => 'array',
            'items' => ['type' => 'integer'],
            'required' => false,
        ],
    ];

    /** Read by Rest\PostsController::serveList(): the statuses a listing may ask for. */
    public const STATUS = [
        'status' => [
            'description' => 'Limit result set to posts assigned one or more statuses.',
            'type' => 'array',
            'items' => [
                'enum' => ['publish', 'future', 'draft', 'pending', 'private', 'trash', 'auto-draft', 'inherit', 'request-pending', 'request-confirmed', 'request-failed', 'request-completed', 'any'],
                'type' => 'string',
            ],
            'default' => 'publish',
            'required' => false,
        ],
    ];

    /** Read by Content\PostWriter: whether a delete bypasses the trash. */
    public const FORCE = [
        'force' => [
            'description' => 'Whether to bypass Trash and force deletion.',
            'type' => 'boolean',
            'default' => false,
            'required' => false,
        ],
    ];

    /**
     * The sets merged into one map, as a route's endpoint publishes them.
     *
     * @param list<array<string, array<string, mixed>>> $sets
     * @return array<string, array<string, mixed>>
     */
    public static function merge(array $sets): array
    {
        return array_merge(...($sets ?: [[]]));
    }
}
