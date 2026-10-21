<?php

declare(strict_types=1);

namespace Minn\Rest;

/**
 * The font library's parameters as the reference's REST index lists them
 * (font families, font faces, font collections), in its order, each read
 * by Rest\FontsController or Rest\FontCollectionsController. A family's
 * list and a face's list take the posts list's ids, offset, order and
 * exact-search switch, ordered by id or by include alone, and only a
 * family's list takes slugs. Neither takes a search, a status or an
 * author: the reference ignores them. The ids a path names are listed as
 * the reference lists them, except that the index there calls a face's
 * family and id required; here they are optional, because the router
 * looks for a required argument in the query and the path always carries
 * them.
 */
final class FontArgs
{
    private const CONTEXT = ['description' => 'Scope under which the request is made; determines fields present in response.', 'type' => 'string', 'enum' => ['view', 'embed', 'edit'], 'default' => 'view', 'required' => false];
    private const PAGE = ['description' => 'Current page of the collection.', 'type' => 'integer', 'default' => 1, 'minimum' => 1, 'required' => false];
    private const PER_PAGE = ['description' => 'Maximum number of items to be returned in result set.', 'type' => 'integer', 'default' => 10, 'minimum' => 1, 'maximum' => 100, 'required' => false];
    private const FAMILY_ID = ['description' => 'The ID for the parent font family of the font face.', 'type' => 'integer', 'required' => false];

    /** A list of font families or font faces past the shared three, in the reference's order. */
    private const POST_LIST = [
        'exclude' => ['description' => 'Ensure result set excludes specific IDs.', 'type' => 'array', 'items' => ['type' => 'integer'], 'default' => [], 'required' => false],
        'include' => ['description' => 'Limit result set to specific IDs.', 'type' => 'array', 'items' => ['type' => 'integer'], 'default' => [], 'required' => false],
        'search_semantics' => ['description' => 'How to interpret the search input.', 'type' => 'string', 'enum' => ['exact'], 'required' => false],
        'offset' => ['description' => 'Offset the result set by a specific number of items.', 'type' => 'integer', 'required' => false],
        'order' => ['description' => 'Order sort attribute ascending or descending.', 'type' => 'string', 'default' => 'desc', 'enum' => ['asc', 'desc'], 'required' => false],
        'orderby' => ['description' => 'Sort collection by post attribute.', 'type' => 'string', 'default' => 'id', 'enum' => ['id', 'include'], 'required' => false],
    ];

    /** The font families list. */
    public const FAMILIES = ['context' => self::CONTEXT, 'page' => self::PAGE, 'per_page' => self::PER_PAGE] + self::POST_LIST + [
        'slug' => ['description' => 'Limit result set to posts with one or more specific slugs.', 'type' => 'array', 'items' => ['type' => 'string'], 'required' => false],
    ];

    /** One font family. */
    public const FAMILY = [
        'id' => ['description' => 'Unique identifier for the post.', 'type' => 'integer', 'required' => false],
        'context' => self::CONTEXT,
    ];

    /** A font family's faces. */
    public const FACES = ['font_family_id' => self::FAMILY_ID, 'context' => self::CONTEXT, 'page' => self::PAGE, 'per_page' => self::PER_PAGE] + self::POST_LIST;

    /** One font face of a family. */
    public const FACE = [
        'font_family_id' => self::FAMILY_ID,
        'id' => ['description' => 'Unique identifier for the font face.', 'type' => 'integer', 'required' => false],
        'context' => self::CONTEXT,
    ];

    /** Whether a delete bypasses the trash, as a font route lists it. */
    private const FORCE = ['type' => 'boolean', 'default' => false, 'description' => 'Whether to bypass Trash and force deletion.', 'required' => false];

    /** A font family deleted. */
    public const FAMILY_DELETE = ['id' => self::FAMILY['id'], 'force' => self::FORCE];

    /** A font face deleted. */
    public const FACE_DELETE = ['font_family_id' => self::FAMILY_ID, 'id' => self::FACE['id'], 'force' => self::FORCE];

    /** The font collections list. */
    public const COLLECTIONS = ['context' => self::CONTEXT, 'page' => self::PAGE, 'per_page' => self::PER_PAGE];

    /** One font collection. */
    public const COLLECTION = ['context' => self::CONTEXT];
}
