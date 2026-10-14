<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Objects\Query;

/**
 * The query the request answers ($wp_query), as WordPress keeps it: the
 * main query ($wp_the_query) unless a plugin swapped in another, an empty
 * one made when there is none yet.
 */
final class MainQuery
{
    /** The query being answered: $wp_query, else the main one, made empty when there is none. */
    public static function current(): Query
    {
        if (!(($GLOBALS['wp_the_query'] ?? null) instanceof Query)) {
            $GLOBALS['wp_the_query'] = Handoff::newQuery();
            $GLOBALS['wp_the_query']->posts = [];
            $GLOBALS['wp_the_query']->post_count = 0;
        }
        if (!(($GLOBALS['wp_query'] ?? null) instanceof Query)) {
            $GLOBALS['wp_query'] = $GLOBALS['wp_the_query'];
        }
        return $GLOBALS['wp_query'];
    }
}
