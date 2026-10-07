<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Http\Args;
use Minn\Http\RouteParams;
use Minn\Runtime\Runtime;

/**
 * The users list's parameters as the reference declares them (probe
 * rest-user-lists): the context, Args::USERS, and has_published_posts
 * taking the REST post types by name. Plugins change them through
 * rest_user_collection_params when the list runs.
 */
final class UserCollectionParams implements RouteParams
{
    /** The list's parameters (the route has no captures). */
    public static function for(array $captures): array
    {
        $params = Args::CONTEXT + Args::USERS;
        $types = Runtime::booted() ? \get_post_types(['show_in_rest' => true], 'names') : ['post' => 'post', 'page' => 'page'];
        $params['has_published_posts']['items']['enum'] = $types;
        return $params;
    }
}
