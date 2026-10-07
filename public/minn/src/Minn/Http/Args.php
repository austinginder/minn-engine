<?php

declare(strict_types=1);

namespace Minn\Http;

/**
 * The parameters a route accepts, as the reference describes them in the
 * REST index: name => {description, type, ...}. The descriptions, types,
 * enums and bounds were captured from the reference, so a client that
 * reads either index is told the same thing, and the router refuses a
 * value the way the reference refuses it, before it judges the caller.
 *
 * A route declares only what it really reads. An argument published here
 * that the handler ignores would be worse than none at all, because a
 * client reading the index would build a request around it, so each set
 * below names the code that consumes it. The sets keep the reference's
 * order, because a refusal lists the invalid parameters in that order.
 * `_fields` and `_embed` are read everywhere and declared nowhere, as the
 * reference lists neither and validates neither.
 */
final class Args
{
    /**
     * The shared collection parameters, judged first: the reference answers
     * a refusal among these alone, and reads the route's own parameters
     * only once these pass.
     */
    public const SHARED = ['context', 'page', 'per_page', 'search'];

    /** The key an argument carries when its handler judges it (a status the caller may not read is refused before the enum is). */
    public const HANDLER_VALIDATES = 'handler_validates';

    /** Read by Rest\Context::of(): which view of a resource is wanted. */
    public const CONTEXT = [
        'context' => ['description' => 'Scope under which the request is made; determines fields present in response.', 'type' => 'string', 'enum' => ['view', 'embed', 'edit'], 'default' => 'view', 'required' => false],
    ];

    /** Read by Content\PostWriter: whether a delete bypasses the trash. */
    public const FORCE = [
        'force' => ['description' => 'Whether to bypass Trash and force deletion.', 'type' => 'boolean', 'default' => false, 'required' => false],
    ];

    /** Read by Rest\ListQuery::fromRequest() and Rest\MediaController::libraryClauses(): the media library. */
    public const MEDIA = [
        'page' => ['description' => 'Current page of the collection.', 'type' => 'integer', 'default' => 1, 'minimum' => 1, 'required' => false],
        'per_page' => ['description' => 'Maximum number of items to be returned in result set.', 'type' => 'integer', 'default' => 10, 'minimum' => 1, 'maximum' => 100, 'required' => false],
        'search' => ['description' => 'Limit results to those matching a string.', 'type' => 'string', 'required' => false],
        'after' => ['description' => 'Limit response to posts published after a given ISO8601 compliant date.', 'type' => 'string', 'format' => 'date-time', 'required' => false],
        'author' => ['description' => 'Limit result set to posts assigned to specific authors.', 'type' => 'array', 'items' => ['type' => 'integer'], 'default' => [], 'required' => false],
        'author_exclude' => ['description' => 'Ensure result set excludes posts assigned to specific authors.', 'type' => 'array', 'items' => ['type' => 'integer'], 'default' => [], 'required' => false],
        'before' => ['description' => 'Limit response to posts published before a given ISO8601 compliant date.', 'type' => 'string', 'format' => 'date-time', 'required' => false],
        'exclude' => ['description' => 'Ensure result set excludes specific IDs.', 'type' => 'array', 'items' => ['type' => 'integer'], 'default' => [], 'required' => false],
        'include' => ['description' => 'Limit result set to specific IDs.', 'type' => 'array', 'items' => ['type' => 'integer'], 'default' => [], 'required' => false],
        'order' => ['description' => 'Order sort attribute ascending or descending.', 'type' => 'string', 'default' => 'desc', 'enum' => ['asc', 'desc'], 'required' => false],
        'orderby' => ['description' => 'Sort collection by post attribute.', 'type' => 'string', 'default' => 'date', 'enum' => ['author', 'date', 'id', 'include', 'modified', 'parent', 'relevance', 'slug', 'include_slugs', 'title'], 'required' => false],
        'parent' => ['description' => 'Limit result set to items with particular parent IDs.', 'type' => 'array', 'items' => ['type' => 'integer'], 'default' => [], 'required' => false],
        'parent_exclude' => ['description' => 'Limit result set to all items except those of a particular parent ID.', 'type' => 'array', 'items' => ['type' => 'integer'], 'default' => [], 'required' => false],
        'slug' => ['description' => 'Limit result set to posts with one or more specific slugs.', 'type' => 'array', 'items' => ['type' => 'string'], 'required' => false],
        'media_type' => ['default' => null, 'description' => 'Limit result set to attachments of a particular media type or media types.', 'type' => 'array', 'items' => ['type' => 'string', 'enum' => ['image', 'video', 'text', 'application', 'audio']], 'required' => false],
        'mime_type' => ['default' => null, 'description' => 'Limit result set to attachments of a particular MIME type or MIME types.', 'type' => 'array', 'items' => ['type' => 'string'], 'required' => false],
    ];

    /** Read by Rest\UserCollectionParams: the user collection. */
    public const USERS = [
        'page' => ['description' => 'Current page of the collection.', 'type' => 'integer', 'default' => 1, 'minimum' => 1, 'required' => false],
        'per_page' => ['description' => 'Maximum number of items to be returned in result set.', 'type' => 'integer', 'default' => 10, 'minimum' => 1, 'maximum' => 100, 'required' => false],
        'search' => ['description' => 'Limit results to those matching a string.', 'type' => 'string', 'required' => false],
        'exclude' => ['description' => 'Ensure result set excludes specific IDs.', 'type' => 'array', 'items' => ['type' => 'integer'], 'default' => [], 'required' => false],
        'include' => ['description' => 'Limit result set to specific IDs.', 'type' => 'array', 'items' => ['type' => 'integer'], 'default' => [], 'required' => false],
        'offset' => ['description' => 'Offset the result set by a specific number of items.', 'type' => 'integer', 'required' => false],
        'order' => ['default' => 'asc', 'description' => 'Order sort attribute ascending or descending.', 'enum' => ['asc', 'desc'], 'type' => 'string', 'required' => false],
        'orderby' => ['default' => 'name', 'description' => 'Sort collection by user attribute.', 'enum' => ['id', 'include', 'name', 'registered_date', 'slug', 'include_slugs', 'email', 'url'], 'type' => 'string', 'required' => false],
        'slug' => ['description' => 'Limit result set to users with one or more specific slugs.', 'type' => 'array', 'items' => ['type' => 'string'], 'required' => false],
        'roles' => ['description' => 'Limit result set to users matching at least one specific role provided. Accepts csv list or single role.', 'type' => 'array', 'items' => ['type' => 'string'], 'required' => false],
        'capabilities' => ['description' => 'Limit result set to users matching at least one specific capability provided. Accepts csv list or single capability.', 'type' => 'array', 'items' => ['type' => 'string'], 'required' => false],
        'who' => ['description' => 'Limit result set to users who are considered authors.', 'type' => 'string', 'enum' => ['authors'], 'required' => false],
        // The post types' enum is filled in at run time (Rest\UserCollectionParams).
        'has_published_posts' => ['description' => 'Limit result set to users who have published posts.', 'type' => ['boolean', 'array'], 'items' => ['type' => 'string', 'enum' => []], 'required' => false],
        'search_columns' => ['default' => [], 'description' => 'Array of column names to be searched.', 'type' => 'array', 'items' => ['enum' => ['email', 'name', 'id', 'username', 'slug'], 'type' => 'string'], 'required' => false],
    ];

    /** Read by Rest\TermCollectionParams: a term collection, either shape. */
    public const TERMS = [
        'page' => ['description' => 'Current page of the collection.', 'type' => 'integer', 'default' => 1, 'minimum' => 1, 'required' => false],
        'per_page' => ['description' => 'Maximum number of items to be returned in result set.', 'type' => 'integer', 'default' => 10, 'minimum' => 1, 'maximum' => 100, 'required' => false],
        'search' => ['description' => 'Limit results to those matching a string.', 'type' => 'string', 'required' => false],
        'exclude' => ['description' => 'Ensure result set excludes specific IDs.', 'type' => 'array', 'items' => ['type' => 'integer'], 'default' => [], 'required' => false],
        'include' => ['description' => 'Limit result set to specific IDs.', 'type' => 'array', 'items' => ['type' => 'integer'], 'default' => [], 'required' => false],
        // A flat taxonomy's list takes an offset, a hierarchical one's a parent (Rest\TermCollectionParams keeps the one that applies).
        'offset' => ['description' => 'Offset the result set by a specific number of items.', 'type' => 'integer', 'required' => false],
        'order' => ['description' => 'Order sort attribute ascending or descending.', 'type' => 'string', 'default' => 'asc', 'enum' => ['asc', 'desc'], 'required' => false],
        'orderby' => ['description' => 'Sort collection by term attribute.', 'type' => 'string', 'default' => 'name', 'enum' => ['id', 'include', 'name', 'slug', 'include_slugs', 'term_group', 'description', 'count'], 'required' => false],
        'hide_empty' => ['description' => 'Whether to hide terms not assigned to any posts.', 'type' => 'boolean', 'default' => false, 'required' => false],
        'parent' => ['description' => 'Limit result set to terms assigned to a specific parent.', 'type' => 'integer', 'required' => false],
        'post' => ['description' => 'Limit result set to terms assigned to a specific post.', 'type' => 'integer', 'default' => null, 'required' => false],
        'slug' => ['description' => 'Limit result set to terms with one or more specific slugs.', 'type' => 'array', 'items' => ['type' => 'string'], 'required' => false],
    ];

    /** Read by Rest\CommentsController::list(): the comment collection. */
    public const COMMENTS = [
        'page' => ['description' => 'Current page of the collection.', 'type' => 'integer', 'default' => 1, 'minimum' => 1, 'required' => false],
        'per_page' => ['description' => 'Maximum number of items to be returned in result set.', 'type' => 'integer', 'default' => 10, 'minimum' => 1, 'maximum' => 100, 'required' => false],
        'search' => ['description' => 'Limit results to those matching a string.', 'type' => 'string', 'required' => false],
        'after' => ['description' => 'Limit response to comments published after a given ISO8601 compliant date.', 'type' => 'string', 'format' => 'date-time', 'required' => false],
        'author' => ['description' => 'Limit result set to comments assigned to specific user IDs. Requires authorization.', 'type' => 'array', 'items' => ['type' => 'integer'], 'required' => false],
        'author_exclude' => ['description' => 'Ensure result set excludes comments assigned to specific user IDs. Requires authorization.', 'type' => 'array', 'items' => ['type' => 'integer'], 'required' => false],
        'author_email' => ['default' => null, 'description' => 'Limit result set to that from a specific author email. Requires authorization.', 'format' => 'email', 'type' => 'string', 'required' => false],
        'before' => ['description' => 'Limit response to comments published before a given ISO8601 compliant date.', 'type' => 'string', 'format' => 'date-time', 'required' => false],
        'exclude' => ['description' => 'Ensure result set excludes specific IDs.', 'type' => 'array', 'items' => ['type' => 'integer'], 'default' => [], 'required' => false],
        'include' => ['description' => 'Limit result set to specific IDs.', 'type' => 'array', 'items' => ['type' => 'integer'], 'default' => [], 'required' => false],
        'offset' => ['description' => 'Offset the result set by a specific number of items.', 'type' => 'integer', 'required' => false],
        'order' => ['description' => 'Order sort attribute ascending or descending.', 'type' => 'string', 'default' => 'desc', 'enum' => ['asc', 'desc'], 'required' => false],
        'orderby' => ['description' => 'Sort collection by comment attribute.', 'type' => 'string', 'default' => 'date_gmt', 'enum' => ['date', 'date_gmt', 'id', 'include', 'post', 'parent', 'type'], 'required' => false],
        'parent' => ['default' => [], 'description' => 'Limit result set to comments of specific parent IDs.', 'type' => 'array', 'items' => ['type' => 'integer'], 'required' => false],
        'parent_exclude' => ['default' => [], 'description' => 'Ensure result set excludes specific parent IDs.', 'type' => 'array', 'items' => ['type' => 'integer'], 'required' => false],
        'post' => ['default' => [], 'description' => 'Limit result set to comments assigned to specific post IDs.', 'type' => 'array', 'items' => ['type' => 'integer'], 'required' => false],
        'status' => ['default' => 'approve', 'description' => 'Limit result set to comments assigned a specific status. Requires authorization.', 'type' => 'string', 'required' => false],
        'type' => ['default' => 'comment', 'description' => 'Limit result set to comments assigned a specific type. Requires authorization.', 'type' => 'string', 'required' => false],
        'password' => ['description' => 'The password for the post if it is password protected.', 'type' => 'string', 'required' => false],
    ];

    /**
     * Read by Rest\UsersController::create(): the body of a user create. The locale takes any
     * string here; the reference's enum is the site's installed languages, which is not a constant.
     */
    public const USER_CREATE = [
        'username' => ['description' => 'Login name for the user.', 'type' => 'string', 'required' => true],
        'name' => ['description' => 'Display name for the user.', 'type' => 'string', 'required' => false],
        'first_name' => ['description' => 'First name for the user.', 'type' => 'string', 'required' => false],
        'last_name' => ['description' => 'Last name for the user.', 'type' => 'string', 'required' => false],
        'email' => ['description' => 'The email address for the user.', 'type' => 'string', 'format' => 'email', 'required' => true],
        'url' => ['description' => 'URL of the user.', 'type' => 'string', 'format' => 'uri', 'required' => false],
        'description' => ['description' => 'Description of the user.', 'type' => 'string', 'required' => false],
        'locale' => ['description' => 'Locale for the user.', 'type' => 'string', 'required' => false],
        'nickname' => ['description' => 'The nickname for the user.', 'type' => 'string', 'required' => false],
        'roles' => ['description' => 'Roles assigned to the user.', 'type' => 'array', 'items' => ['type' => 'string'], 'required' => false],
        'password' => ['description' => 'Password for the user (never included).', 'type' => 'string', 'required' => true],
    ];

    /** Read by Rest\UsersController::update(): the body of a user edit; nothing is required. */
    public const USER_EDIT = [
        'name' => ['description' => 'Display name for the user.', 'type' => 'string', 'required' => false],
        'first_name' => ['description' => 'First name for the user.', 'type' => 'string', 'required' => false],
        'last_name' => ['description' => 'Last name for the user.', 'type' => 'string', 'required' => false],
        'email' => ['description' => 'The email address for the user.', 'type' => 'string', 'format' => 'email', 'required' => false],
        'url' => ['description' => 'URL of the user.', 'type' => 'string', 'format' => 'uri', 'required' => false],
        'description' => ['description' => 'Description of the user.', 'type' => 'string', 'required' => false],
        'locale' => ['description' => 'Locale for the user.', 'type' => 'string', 'required' => false],
        'nickname' => ['description' => 'The nickname for the user.', 'type' => 'string', 'required' => false],
        'slug' => ['description' => 'An alphanumeric identifier for the user.', 'type' => 'string', 'required' => false],
        'roles' => ['description' => 'Roles assigned to the user.', 'type' => 'array', 'items' => ['type' => 'string'], 'required' => false],
        'password' => ['description' => 'Password for the user (never included).', 'type' => 'string', 'required' => false],
        'meta' => ['description' => 'Meta fields.', 'type' => 'object', 'properties' => ['show_admin_bar_front' => ['type' => 'string', 'default' => 'true']], 'required' => false],
    ];

    /** Read by Rest\UsersController::delete(): the reassignment a user delete requires, and the force it insists on. */
    public const USER_DELETE = [
        'force' => ['type' => 'boolean', 'default' => false, 'description' => 'Required to be true, as users do not support trashing.', 'required' => false],
        'reassign' => ['type' => 'integer', 'description' => 'Reassign the deleted user\'s posts and links to this user ID.', 'required' => true, self::HANDLER_VALIDATES => true],
    ];

    /** Read by Rest\ApplicationPasswordsController::create(): the body of a new application password. */
    public const APPLICATION_PASSWORD = [
        'app_id' => ['description' => 'A UUID provided by the application to uniquely identify it. It is recommended to use an UUID v5 with the URL or DNS namespace.', 'type' => 'string', 'oneOf' => [['type' => 'string', 'format' => 'uuid'], ['type' => 'string', 'enum' => ['']]], 'required' => false],
        'name' => ['description' => 'The name of the application password.', 'type' => 'string', 'minLength' => 1, 'pattern' => '.*\\S.*', 'required' => true],
    ];

    /** Read by Rest\SearchController::list(): the search collection, whose context has no edit view. */
    public const SEARCH = [
        'context' => ['description' => 'Scope under which the request is made; determines fields present in response.', 'type' => 'string', 'enum' => ['view', 'embed'], 'default' => 'view', 'required' => false],
        'page' => ['description' => 'Current page of the collection.', 'type' => 'integer', 'default' => 1, 'minimum' => 1, 'required' => false],
        'per_page' => ['description' => 'Maximum number of items to be returned in result set.', 'type' => 'integer', 'default' => 10, 'minimum' => 1, 'maximum' => 100, 'required' => false],
        'search' => ['description' => 'Limit results to those matching a string.', 'type' => 'string', 'required' => false],
        'type' => ['default' => 'post', 'description' => 'Limit results to items of an object type.', 'type' => 'string', 'enum' => ['post', 'term', 'post-format'], 'required' => false],
        'subtype' => ['default' => 'any', 'description' => 'Limit results to items of one or more object subtypes.', 'type' => 'array', 'items' => ['enum' => ['post', 'page', 'category', 'post_tag', 'any'], 'type' => 'string'], 'required' => false],
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
