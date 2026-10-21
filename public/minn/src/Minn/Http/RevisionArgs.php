<?php

declare(strict_types=1);

namespace Minn\Http;

/**
 * The parameters the revisions and autosaves routes accept, as the
 * reference's REST index describes them (captured from it, in its order).
 * The path's own captures are declared too, as the reference declares them,
 * so a query value of the same name stands in for the capture (the router's
 * override) and is judged first. Each set names the code that reads it.
 */
final class RevisionArgs
{
    /** Read by the router's override: a revision's parent post, by id. */
    public const PARENT = [
        'parent' => ['description' => 'The ID for the parent of the revision.', 'type' => 'integer', 'required' => false],
    ];

    /** Read by the router's override: the revision itself, by id. */
    public const ID = [
        'id' => ['description' => 'Unique identifier for the revision.', 'type' => 'integer', 'required' => false],
    ];

    /** Read by the router's override on an autosave's own route; on the autosaves list it is judged and nothing more, as on the reference. */
    public const AUTOSAVE_PARENT = [
        'parent' => ['description' => 'The ID for the parent of the autosave.', 'type' => 'integer', 'required' => false],
    ];

    /** Read by the router's override: the autosave asked for, by id. */
    public const AUTOSAVE_ID = [
        'id' => ['description' => 'The ID for the autosave.', 'type' => 'integer', 'required' => false],
    ];

    /** Read by the router's override: a global styles post that owns the revisions. */
    public const STYLES_PARENT = [
        'parent' => ['description' => 'The ID for the parent of the global styles revision.', 'type' => 'integer', 'required' => false],
    ];

    /** Read by the router's override: one global styles revision. */
    public const STYLES_ID = [
        'id' => ['description' => 'Unique identifier for the global styles revision.', 'type' => 'integer', 'required' => false],
    ];

    /** Read by the router's override: a template (or part) that owns the revisions, by its theme//slug id. */
    public const TEMPLATE_PARENT = [
        'parent' => ['description' => 'The id of a template', 'type' => 'string', 'required' => false],
    ];

    /** Read by the router's override: a template (or part) whose autosaves are listed or written. */
    public const TEMPLATE_ID = [
        'id' => ['description' => 'The id of a template', 'type' => 'string', 'required' => false],
    ];

    /** Read by Rest\RevisionCollection: a revisions list's paging, search, filters and order. */
    public const COLLECTION = [
        'page' => ['description' => 'Current page of the collection.', 'type' => 'integer', 'default' => 1, 'minimum' => 1, 'required' => false],
        'per_page' => ['description' => 'Maximum number of items to be returned in result set.', 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'required' => false],
        'search' => ['description' => 'Limit results to those matching a string.', 'type' => 'string', 'required' => false],
        'exclude' => ['description' => 'Ensure result set excludes specific IDs.', 'type' => 'array', 'items' => ['type' => 'integer'], 'default' => [], 'required' => false],
        'include' => ['description' => 'Limit result set to specific IDs.', 'type' => 'array', 'items' => ['type' => 'integer'], 'default' => [], 'required' => false],
        'offset' => ['description' => 'Offset the result set by a specific number of items.', 'type' => 'integer', 'required' => false],
        'order' => ['description' => 'Order sort attribute ascending or descending.', 'type' => 'string', 'default' => 'desc', 'enum' => ['asc', 'desc'], 'required' => false],
        'orderby' => ['description' => 'Sort collection by object attribute.', 'type' => 'string', 'default' => 'date', 'enum' => ['date', 'id', 'include', 'relevance', 'slug', 'include_slugs', 'title'], 'required' => false],
    ];

    /** Read by Rest\RevisionCollection::paged(): the global styles revisions list pages and offsets, and filters nothing. */
    public const STYLES_COLLECTION = [
        'page' => ['description' => 'Current page of the collection.', 'type' => 'integer', 'default' => 1, 'minimum' => 1, 'required' => false],
        'per_page' => ['description' => 'Maximum number of items to be returned in result set.', 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'required' => false],
        'offset' => ['description' => 'Offset the result set by a specific number of items.', 'type' => 'integer', 'required' => false],
    ];

    /** Read by the revision deletes: a revision is never trashed, so force must be true. */
    public const FORCE = [
        'force' => ['type' => 'boolean', 'default' => false, 'description' => 'Required to be true, as revisions do not support trashing.', 'required' => false],
    ];
}
