<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Http\Args;

/**
 * The arguments a route's path names, as the reference lists them among the
 * route's args: each capture again, described and typed. Declared, they are
 * judged like any other argument, and a value of the same name in the query
 * (or a write's body) takes the capture's place before the caller is judged
 * (Http\Router), as on the reference: /wp/v2/users/1?id=abc is refused as an
 * invalid id, and /wp/v2/users/1?id=2 answers user 2. A route lists one only
 * where the reference lists it (a widget's GET names no id), and in the
 * reference's order, which a refusal's list follows.
 */
final class PathArgs
{
    /** A user's id: wp/v2/users/{id}. */
    public const USER_ID = [
        'id' => ['description' => 'Unique identifier for the user.', 'type' => 'integer', 'required' => false],
    ];

    /** A post's id: wp/v2/{type base}/{id}, media, blocks, navigation, menu items. */
    public const POST_ID = [
        'id' => ['description' => 'Unique identifier for the post.', 'type' => 'integer', 'required' => false],
    ];

    /** A comment's id: wp/v2/comments/{id}. */
    public const COMMENT_ID = [
        'id' => ['description' => 'Unique identifier for the comment.', 'type' => 'integer', 'required' => false],
    ];

    /** A global styles config's id: wp/v2/global-styles/{id}, read (its writes name none). */
    public const GLOBAL_STYLES_ID = [
        'id' => ['description' => 'ID of global styles config.', 'type' => 'integer', 'required' => false],
    ];

    /** A term's id: wp/v2/{taxonomy base}/{id}, and a menu's. */
    public const TERM_ID = [
        'id' => ['description' => 'Unique identifier for the term.', 'type' => 'integer', 'required' => false],
    ];

    /** A post status's name: wp/v2/statuses/{status}. */
    public const STATUS = [
        'status' => ['description' => 'An alphanumeric identifier for the status.', 'type' => 'string', 'required' => false],
    ];

    /** The block a render asks for: wp/v2/block-renderer/{name}. */
    public const RENDERED_BLOCK = [
        'name' => ['description' => 'Unique registered name for the block.', 'type' => 'string', 'required' => false],
    ];

    /** A block type's namespace: wp/v2/block-types/{namespace}. */
    public const BLOCK_NAMESPACE = [
        'namespace' => ['description' => 'Block namespace.', 'type' => 'string', 'required' => false],
    ];

    /** A block type's name within its namespace: wp/v2/block-types/{namespace}/{name}. */
    public const BLOCK_NAME = [
        'name' => ['description' => 'Block name.', 'type' => 'string', 'required' => false],
    ];

    /** A theme's stylesheet: wp/v2/themes/{stylesheet}. */
    public const STYLESHEET = [
        'stylesheet' => ['description' => 'The theme\'s stylesheet. This uniquely identifies the theme.', 'type' => 'string', 'required' => false],
    ];

    /**
     * A plugin's file without its .php: wp/v2/plugins/{plugin}. The reference
     * gives it no description and judges it with a check of its own, whose
     * refusal is the bare "Invalid parameter.".
     */
    public const PLUGIN = [
        'plugin' => ['type' => 'string', 'pattern' => '[^.\/]+(?:\/[^.\/]+)?', 'required' => false, Args::BARE_REFUSAL => true],
    ];

    /** A registered sidebar's id: wp/v2/sidebars/{id}. */
    public const SIDEBAR_ID = [
        'id' => ['description' => 'The id of a registered sidebar', 'type' => 'string', 'required' => false],
    ];

    /** A widget type's id: wp/v2/widget-types/{id}. */
    public const WIDGET_TYPE_ID = [
        'id' => ['description' => 'The widget type id.', 'type' => 'string', 'required' => false],
    ];
}
