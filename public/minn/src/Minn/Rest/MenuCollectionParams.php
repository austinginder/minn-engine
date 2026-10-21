<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Http\Args;
use Minn\Http\RouteParams;

/**
 * The menus list's parameters as the reference declares them: a flat
 * taxonomy's term list (the context and Args::TERMS, an offset and no
 * parent). Plugins change them through rest_nav_menu_collection_params
 * when the list runs (Rest\MenuLists).
 */
final class MenuCollectionParams implements RouteParams
{
    /** The menus list's parameters; the route has no captures to read. */
    public static function for(array $captures): array
    {
        return array_diff_key(Args::CONTEXT + Args::TERMS, ['parent' => true]);
    }
}
