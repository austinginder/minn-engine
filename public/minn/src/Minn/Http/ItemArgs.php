<?php

declare(strict_types=1);

namespace Minn\Http;

/**
 * The parameters a single resource's read takes, as the reference's REST
 * index declares them for posts, pages, blocks, navigation, menus, menu
 * items, menu locations and comments. The path names the resource, and a
 * query parameter of the same name stands in for it (the router reads it
 * over the path's), so the id is declared and judged like any other. Each
 * set names the code that reads it, as Args does, and keeps the reference's
 * order.
 */
final class ItemArgs
{
    /** The id a post's read takes, read through the router's capture (Http\Router). */
    private const POST_ID = ['description' => 'Unique identifier for the post.', 'type' => 'integer', 'required' => false];

    /** Read by Rest\PostsController::serveSingle(): a post's password, opening its content or refusing a wrong one. */
    private const POST_PASSWORD = ['description' => 'The password for the post if it is password protected.', 'type' => 'string', 'required' => false];

    /** Read by Rest\PostsController::serveSingle(): a post, page or block, its excerpt cut to the length asked for. */
    public const POST = [
        'id' => self::POST_ID,
        'context' => Args::CONTEXT['context'],
        'excerpt_length' => ['description' => 'Override the default excerpt length.', 'type' => 'integer', 'required' => false],
        'password' => self::POST_PASSWORD,
    ];

    /** Read by Rest\PostsController::serveSingle(): a navigation post, which has no excerpt. */
    public const NAVIGATION = [
        'id' => self::POST_ID,
        'context' => Args::CONTEXT['context'],
        'password' => self::POST_PASSWORD,
    ];

    /** Read by Rest\MenusController::singleItem(): a menu item. */
    public const MENU_ITEM = [
        'id' => self::POST_ID,
        'context' => Args::CONTEXT['context'],
    ];

    /** Read by Rest\MenusController::singleMenu(): a menu. */
    public const MENU = [
        'id' => ['description' => 'Unique identifier for the term.', 'type' => 'integer', 'required' => false],
        'context' => Args::CONTEXT['context'],
    ];

    /** Read by Rest\MenusController::oneLocation(): a menu location by its name. */
    public const MENU_LOCATION = [
        'location' => ['description' => 'An alphanumeric identifier for the menu location.', 'type' => 'string', 'required' => false],
        'context' => Args::CONTEXT['context'],
    ];

    /** Read by Rest\CommentsController::single(): a comment, and the password of the post it is on. */
    public const COMMENT = [
        'id' => ['description' => 'Unique identifier for the comment.', 'type' => 'integer', 'required' => false],
        'context' => Args::CONTEXT['context'],
        'password' => ['description' => 'The password for the parent post of the comment (if the post is password protected).', 'type' => 'string', 'required' => false],
    ];
}
